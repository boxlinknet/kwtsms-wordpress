<?php
/**
 * Password Reset via OTP.
 *
 * Replaces (or supplements) the default WordPress email-based password reset
 * with an SMS OTP verification step.
 *
 * Flow:
 *   1. User visits /wp-login.php?action=lostpassword.
 *   2. lostpassword_post: we intercept, look up user by login/email/phone,
 *      generate OTP, send SMS, store partial reset session, redirect to OTP page.
 *   3. OTP entry page (action=kwtsms_reset_otp): user enters code.
 *   4. On valid OTP: generate WP reset key, redirect to standard rp page.
 *   5. User sets new password via standard WP reset flow.
 *
 * Fallback: if user has no phone on file and email reset is not disabled,
 * fall back to the default WP email reset flow.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Reset_OTP
 */
class KwtSMS_Reset_OTP {

	/**
	 * Plugin manager.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Transient key prefix for reset sessions.
	 *
	 * @var string
	 */
	const RESET_TRANSIENT_PREFIX = 'kwtsms_reset_session_';

	/**
	 * Cookie name for reset session.
	 *
	 * @var string
	 */
	private $cookie_name;

	/**
	 * Constructor.
	 *
	 * @param KwtSMS_Plugin $plugin Plugin manager.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin      = $plugin;
		$this->cookie_name = 'kwtsms_reset_' . COOKIEHASH;

		add_action( 'login_init', array( $this, 'maybe_intercept_password_reset' ), 1 );
		add_action( 'login_form_lostpassword', array( $this, 'add_phone_field_to_lost_password' ) );
		add_action( 'login_init', array( $this, 'handle_reset_otp_action' ) );
	}

	/**
	 * Intercept the lostpassword form submission early, before any output.
	 *
	 * Fires on `login_init` at priority 1 — before login_header() is called
	 * and before login_form_lostpassword outputs content, so wp_safe_redirect
	 * can send headers cleanly.
	 *
	 * Only acts on POST requests to action=lostpassword.
	 */
	public function maybe_intercept_password_reset() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
		if ( 'lostpassword' !== $action ) {
			return;
		}

		// Try to find user from form input (login, email, or phone).
		$input = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );

		if ( empty( $input ) ) {
			return;
		}

		$resolved_user = $this->resolve_user( $input );

		if ( ! ( $resolved_user instanceof WP_User ) ) {
			// No user found — fall through to WP default (generic confirmation).
			return;
		}

		$phone = get_user_meta( $resolved_user->ID, 'kwtsms_phone', true );

		if ( empty( $phone ) ) {
			// No phone — fall back to email reset.
			return;
		}

		// Rate-limit check: inject error via lostpassword_post so WP renders form with error.
		if ( $this->plugin->otp->is_rate_limited( $phone ) ) {
			add_action(
				'lostpassword_post',
				function ( $errors ) {
					$errors->add(
						'kwtsms_rate_limited',
						__( 'Too many requests. Please wait a few minutes before trying again.', 'wp-kwtsms-otp' )
					);
				}
			);
			return;
		}

		// Generate OTP and send SMS.
		$otp_code = $this->plugin->otp->generate( $resolved_user->ID, 'reset' );
		$message  = $this->plugin->otp->build_message( $otp_code, 'reset_otp' );
		$result   = $this->plugin->api->send_sms(
			$phone,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message
		);

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions
				error_log( '[kwtsms-otp] Reset SMS failed for user ' . $resolved_user->ID . ': ' . $result->get_error_message() );
			}
			// Fail gracefully — fall back to email reset.
			return;
		}

		$this->plugin->otp->increment_rate( $phone );

		// Store reset session.
		$token = wp_generate_password( 40, false );
		set_transient(
			self::RESET_TRANSIENT_PREFIX . $token,
			array(
				'user_id' => $resolved_user->ID,
				'phone'   => $phone,
			),
			15 * MINUTE_IN_SECONDS
		);

		// Set cookie.
		$this->set_reset_cookie( $token );

		// Prevent WP from sending the email reset.
		add_filter( 'send_retrieve_password_email', '__return_false' );

		// Redirect to OTP page — no output has been sent yet, so headers are clean.
		wp_safe_redirect(
			add_query_arg( 'action', 'kwtsms_reset_otp', wp_login_url() )
		);
		exit;
	}

	/**
	 * Add a note about SMS OTP to the lost-password form.
	 */
	public function add_phone_field_to_lost_password() {
		printf(
			'<p class="message" style="margin-bottom:12px;">%s</p>',
			esc_html__( 'If your account has a phone number registered, an OTP will be sent instead of an email reset link.', 'wp-kwtsms-otp' )
		);
	}

	/**
	 * Handle the kwtsms_reset_otp action on wp-login.php.
	 *
	 * Fires on `login_init`. If action matches, handles GET (render) and POST (verify).
	 */
	public function handle_reset_otp_action() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login';

		if ( 'kwtsms_reset_otp' !== $action ) {
			return;
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_reset_otp_submission();
		}

		$this->render_reset_otp_page();
		exit;
	}

	/**
	 * Handle the OTP verification form for password reset.
	 */
	private function handle_reset_otp_submission() {
		if ( ! isset( $_POST['kwtsms_reset_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['kwtsms_reset_nonce'] ) ),
				'kwtsms_reset_otp_submit'
			)
		) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'wp-kwtsms-otp' ) );
		}

		$token = $this->get_reset_cookie_token();
		if ( empty( $token ) ) {
			$this->render_reset_otp_page( __( 'Session expired. Please start over.', 'wp-kwtsms-otp' ) );
			exit;
		}

		$session = get_transient( self::RESET_TRANSIENT_PREFIX . $token );
		if ( ! $session ) {
			$this->clear_reset_cookie();
			$this->render_reset_otp_page( __( 'Session expired. Please start over.', 'wp-kwtsms-otp' ) );
			exit;
		}

		$user_id   = absint( $session['user_id'] );
		$submitted = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['kwtsms_code'] ?? '' ) ) );
		$result    = $this->plugin->otp->verify( $user_id, $submitted );

		switch ( $result ) {
			case 'valid':
				delete_transient( self::RESET_TRANSIENT_PREFIX . $token );
				$this->clear_reset_cookie();

				// Generate a WP password reset key so the user can use the standard reset form.
				$user = get_userdata( $user_id );
				if ( ! $user ) {
					wp_safe_redirect( wp_login_url() );
					exit;
				}

				$reset_key = get_password_reset_key( $user );
				if ( is_wp_error( $reset_key ) ) {
					$this->render_reset_otp_page( __( 'Could not generate reset link. Please try again.', 'wp-kwtsms-otp' ) );
					exit;
				}

				$rp_url = add_query_arg(
					array(
						'action' => 'rp',
						'key'    => rawurlencode( $reset_key ),
						'login'  => rawurlencode( $user->user_login ),
					),
					network_site_url( 'wp-login.php', 'login' )
				);

				wp_safe_redirect( $rp_url );
				exit;

			case 'invalid':
				$remaining = $this->plugin->otp->get_remaining_attempts( $user_id );
				$this->render_reset_otp_page(
					sprintf(
						/* translators: %d: remaining attempts */
						_n(
							'Incorrect code. %d attempt remaining.',
							'Incorrect code. %d attempts remaining.',
							$remaining,
							'wp-kwtsms-otp'
						),
						$remaining
					)
				);
				exit;

			case 'expired':
				$this->render_reset_otp_page(
					__( 'Your code has expired. Please go back and request a new one.', 'wp-kwtsms-otp' )
				);
				exit;

			case 'max_attempts':
				delete_transient( self::RESET_TRANSIENT_PREFIX . $token );
				$this->clear_reset_cookie();
				$this->render_reset_otp_page(
					__( 'Too many incorrect attempts. Please start the password reset process again.', 'wp-kwtsms-otp' )
				);
				exit;

			default:
				$this->render_reset_otp_page( __( 'Something went wrong. Please try again.', 'wp-kwtsms-otp' ) );
				exit;
		}
	}

	/**
	 * Render the reset OTP entry page.
	 *
	 * @param string $error_message Optional error message.
	 */
	private function render_reset_otp_page( $error_message = '' ) {
		nocache_headers();
		$otp_length   = (int) $this->plugin->settings->get( 'general.otp_length', 6 );
		$cooldown     = (int) $this->plugin->settings->get( 'general.resend_cooldown', 60 );
		$is_reset     = true;
		$login_url    = wp_login_url();
		$nonce_resend = wp_create_nonce( 'kwtsms_otp_nonce' );
		$token        = $this->get_reset_cookie_token() ?? '';
		$redirect_to  = '';
		include KWTSMS_OTP_DIR . 'includes/views/page-otp.php';
	}

	// =========================================================================
	// User resolution
	// =========================================================================

	/**
	 * Resolve a user from login name, email, or phone number.
	 *
	 * @param string $input The user-provided identifier.
	 *
	 * @return WP_User|false
	 */
	private function resolve_user( $input ) {
		// Try login.
		$user = get_user_by( 'login', $input );
		if ( $user ) {
			return $user;
		}

		// Try email.
		$user = get_user_by( 'email', $input );
		if ( $user ) {
			return $user;
		}

		// Try phone meta (normalize first).
		$normalized = KwtSMS_API::normalize_phone( $input );
		if ( ! is_wp_error( $normalized ) ) {
			$users = get_users(
				array(
					'meta_key'   => 'kwtsms_phone',
					'meta_value' => $normalized,
					'number'     => 1,
					'fields'     => 'all',
				)
			);
			if ( ! empty( $users ) ) {
				return $users[0];
			}
		}

		return false;
	}

	// =========================================================================
	// Cookie helpers
	// =========================================================================

	/**
	 * Set the reset session cookie.
	 *
	 * @param string $token Session token.
	 */
	private function set_reset_cookie( $token ) {
		setcookie(
			$this->cookie_name,
			$token,
			array(
				'expires'  => time() + 15 * MINUTE_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}

	/**
	 * Get the reset session token from cookie.
	 *
	 * @return string
	 */
	private function get_reset_cookie_token() {
		return sanitize_text_field( $_COOKIE[ $this->cookie_name ] ?? '' );
	}

	/**
	 * Clear the reset session cookie.
	 */
	private function clear_reset_cookie() {
		setcookie(
			$this->cookie_name,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}
}
