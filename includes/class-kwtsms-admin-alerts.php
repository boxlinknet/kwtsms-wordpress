<?php
/**
 * Admin Site Alerts: send SMS to admin phone(s) on key WordPress events.
 *
 * Supported events (each individually toggleable):
 *   - New user registered  (user_register)
 *   - User logged in       (wp_login)
 *   - Post published       (transition_post_status)
 *   - Comment posted       (comment_post)
 *   - WordPress updated    (upgrader_process_complete)
 *
 * All hooks are skipped when admin_phones is empty.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Admin_Alerts
 *
 * Registers WordPress action hooks for site events and sends SMS
 * notifications to configured admin phone numbers.
 */
class KwtSMS_Admin_Alerts {

	/**
	 * Plugin manager.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * Reads settings and registers hooks for enabled events.
	 * Does nothing when no admin phones are configured.
	 *
	 * @param KwtSMS_Plugin $plugin Plugin manager.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		// Skip hook registration when no admin phones are configured.
		$phones = trim( (string) $this->plugin->settings->get( 'alerts.admin_phones', '' ) );
		if ( '' === $phones ) {
			return;
		}

		if ( $this->plugin->settings->get( 'alerts.user_register', 1 ) ) {
			add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );
		}

		if ( $this->plugin->settings->get( 'alerts.wp_login', 0 ) ) {
			add_action( 'wp_login', array( $this, 'on_wp_login' ), 10, 2 );
		}

		if ( $this->plugin->settings->get( 'alerts.post_published', 1 ) ) {
			add_action( 'transition_post_status', array( $this, 'on_post_published' ), 10, 3 );
		}

		if ( $this->plugin->settings->get( 'alerts.comment_posted', 1 ) ) {
			add_action( 'comment_post', array( $this, 'on_comment_posted' ), 10, 3 );
		}

		if ( $this->plugin->settings->get( 'alerts.core_update', 1 ) ) {
			add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_complete' ), 10, 2 );
		}
	}

	// =========================================================================
	// Event handlers
	// =========================================================================

	/**
	 * Handle new user registration.
	 *
	 * @param int $user_id Newly registered user ID.
	 */
	public function on_user_register( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return;
		}

		$this->send_to_all_admins(
			'tpl_user_register',
			array(
				'{username}'  => $user->user_login,
				'{email}'     => $user->user_email,
				'{site_name}' => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Handle user login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       Logged-in user object (required by hook signature).
	 */
	public function on_wp_login( $user_login, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->send_to_all_admins(
			'tpl_wp_login',
			array(
				'{username}'  => $user_login,
				'{site_name}' => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Handle post status transition: alert only on first publish of 'post' post type.
	 *
	 * Only fires when transitioning from any non-publish status to 'publish'
	 * for the 'post' post type. Re-publishes and other post types are ignored
	 * to avoid duplicate alerts and noise from attachments, products, etc.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 */
	public function on_post_published( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$this->send_to_all_admins(
			'tpl_post_published',
			array(
				'{post_title}' => $post->post_title,
				'{site_name}'  => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Handle new comment posted.
	 *
	 * Skips unapproved (held for moderation) comments to avoid spam noise.
	 *
	 * @param int        $comment_id       Comment ID.
	 * @param int|string $comment_approved Approval status: 1 (approved), 0, or 'spam'.
	 * @param array      $commentdata      Raw comment data (required by hook signature).
	 */
	public function on_comment_posted( $comment_id, $comment_approved, $commentdata ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 1 !== (int) $comment_approved ) {
			return;
		}

		$comment = get_comment( (int) $comment_id );
		if ( ! $comment ) {
			return;
		}

		$this->send_to_all_admins(
			'tpl_comment_posted',
			array(
				'{author}'     => $comment->comment_author,
				'{post_title}' => get_the_title( (int) $comment->comment_post_ID ),
				'{site_name}'  => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Handle WordPress core update completion.
	 *
	 * Fires only when a core (not plugin or theme) update completes.
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance (unused).
	 * @param array       $hook_extra Extra data including type and action.
	 */
	public function on_upgrader_complete( $upgrader, $hook_extra ) {
		if ( 'update' !== ( $hook_extra['action'] ?? '' ) || 'core' !== ( $hook_extra['type'] ?? '' ) ) {
			return;
		}

		global $wp_version;

		$this->send_to_all_admins(
			'tpl_core_update',
			array(
				'{version}'   => (string) $wp_version,
				'{site_name}' => get_bloginfo( 'name' ),
			)
		);
	}

	// =========================================================================
	// Send helper
	// =========================================================================

	/**
	 * Resolve the template, replace placeholders, and send to all admin phones.
	 *
	 * Always uses the English ('en') template since admin notifications are
	 * site-operator-facing. Invalid or non-normalizable phone numbers are
	 * silently skipped so one bad entry does not block the rest.
	 *
	 * @param string $tpl_key      Template key in alerts settings (e.g. 'tpl_user_register').
	 * @param array  $placeholders Map of {placeholder} => replacement value.
	 */
	private function send_to_all_admins( $tpl_key, array $placeholders ) {
		$tpl = $this->plugin->settings->get( 'alerts.' . $tpl_key, array() );
		if ( ! is_array( $tpl ) ) {
			return;
		}

		// Always use English for admin-facing messages.
		$message = (string) ( $tpl['en'] ?? '' );
		if ( '' === $message ) {
			return;
		}
		$message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $message );

		$sender_id  = (string) $this->plugin->settings->get( 'gateway.sender_id', '' );
		$raw_phones = (string) $this->plugin->settings->get( 'alerts.admin_phones', '' );

		foreach ( preg_split( '/[\s,]+/', $raw_phones, -1, PREG_SPLIT_NO_EMPTY ) as $raw ) {
			$phone = KwtSMS_API::prepend_country_code_if_local( $raw, KwtSMS_API::get_default_dial_code() );
			$phone = KwtSMS_API::normalize_phone( $phone );
			if ( is_wp_error( $phone ) ) {
				continue;
			}
			$this->plugin->api->send_sms( $phone, $sender_id, $message, 'admin_alert' );
		}
	}
}
