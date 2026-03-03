<?php
/**
 * WooCommerce Integration.
 *
 * Provides:
 * - Order status SMS notifications (processing, shipped, completed, cancelled)
 * - Phone field on WC registration form + welcome SMS after registration
 * - Checkout OTP gate: require phone + OTP for guest orders (optional, admin-controlled)
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Woo
 *
 * Hooks into WooCommerce events to send SMS notifications and to gate
 * guest checkout behind an OTP verification step.
 */
class KwtSMS_Woo {

	/**
	 * Reference to the main plugin instance.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Map WooCommerce order status slugs to their settings template keys.
	 *
	 * @var array<string,string>
	 */
	private static $status_template_map = array(
		'processing' => 'woo_processing',
		'on-hold'    => 'woo_shipped',
		'completed'  => 'woo_completed',
		'cancelled'  => 'woo_cancelled',
		'pending'    => 'woo_pending',
		'refunded'   => 'woo_refunded',
		'failed'     => 'woo_failed',
	);

	/**
	 * Transient prefix for checkout OTP sessions.
	 *
	 * @var string
	 */
	const CHECKOUT_OTP_PREFIX = 'kwtsms_checkout_otp_';

	/**
	 * Constructor.
	 *
	 * Registers all WooCommerce hooks. If the WooCommerce integration is
	 * disabled via the admin settings, no hooks are registered and the class
	 * exits immediately. The checkout OTP gate hooks are only added when that
	 * sub-feature is also enabled.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		// Bail entirely if the WooCommerce integration is disabled.
		if ( ! $this->plugin->settings->get( 'integrations.woo_enabled', 1 ) ) {
			return;
		}

		// Order status notifications.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );

		// Registration: phone field + welcome SMS.
		add_action( 'woocommerce_register_form',       array( $this, 'render_wc_phone_field' ) );
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_wc_phone_field' ), 10, 3 );
		add_action( 'woocommerce_created_customer',    array( $this, 'save_wc_customer_phone' ) );

		// Checkout OTP gate (only when enabled).
		if ( $this->is_checkout_otp_enabled() ) {
			add_action( 'woocommerce_after_order_notes',      array( $this, 'render_checkout_otp_field' ) );
			add_action( 'woocommerce_checkout_process',       array( $this, 'process_checkout_otp' ) );
			add_action( 'woocommerce_checkout_order_created', array( $this, 'clear_checkout_otp_session' ) );
		}
	}

	// =========================================================================
	// Order status notifications
	// =========================================================================

	/**
	 * Send an SMS notification when an order status changes.
	 *
	 * Supported transitions:
	 *   pending|on-hold → processing  (order confirmed / payment received)
	 *   processing      → on-hold     (order shipped — labelled "Shipped")
	 *   *               → completed   (order completed)
	 *   *               → cancelled   (order cancelled)
	 *
	 * Phone resolution priority:
	 *   1. kwtsms_phone user meta (normalised at save time)
	 *   2. Billing phone from order (normalised on-the-fly)
	 *
	 * @param int           $order_id   WooCommerce order ID.
	 * @param string        $old_status Previous status slug.
	 * @param string        $new_status New status slug.
	 * @param WC_Order|null $order      Order object (passed by WC since 3.0).
	 */
	public function on_order_status_changed( $order_id, $old_status, $new_status, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		// Get customer phone — prefer kwtsms_phone user meta, fall back to billing phone.
		$user_id = $order->get_customer_id();
		$phone   = '';
		if ( $user_id ) {
			$phone = get_user_meta( $user_id, 'kwtsms_phone', true );
		}
		if ( empty( $phone ) ) {
			$raw = $order->get_billing_phone();
			if ( ! empty( $raw ) ) {
				$raw        = KwtSMS_API::prepend_country_code_if_local( $raw, KwtSMS_API::get_default_dial_code() );
				$normalized = KwtSMS_API::normalize_phone( $raw );
				$phone      = is_wp_error( $normalized ) ? '' : $normalized;
			}
		}

		if ( empty( $phone ) ) {
			return; // No phone — skip.
		}

		$message = $this->build_order_message( $new_status, $order );
		if ( empty( $message ) ) {
			return; // Status not in our notification set.
		}

		$this->plugin->api->send_sms(
			$phone,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'woo_order'
		);

		// Admin SMS notification — send to configured admin phone(s) for selected statuses.
		$admin_phone     = $this->plugin->settings->get( 'integrations.woo_admin_phone', '' );
		$notify_statuses = $this->plugin->settings->get( 'integrations.woo_notify_admin_statuses', array() );
		if ( ! empty( $admin_phone ) && in_array( $new_status, (array) $notify_statuses, true ) ) {
			$admin_msg = sprintf(
				/* translators: 1: order id 2: customer name 3: status 4: total */
				__( 'New order #%1$s — %2$s — %3$s — %4$s', 'wp-kwtsms' ),
				$order->get_id(),
				$order->get_formatted_billing_full_name(),
				wc_get_order_status_name( $new_status ),
				wp_strip_all_tags( $order->get_formatted_order_total() )
			);
			foreach ( array_map( 'trim', explode( ',', $admin_phone ) ) as $admin_p ) {
				$admin_p          = KwtSMS_API::prepend_country_code_if_local( $admin_p, KwtSMS_API::get_default_dial_code() );
				$normalized_admin = KwtSMS_API::normalize_phone( $admin_p );
				if ( ! is_wp_error( $normalized_admin ) ) {
					$this->plugin->api->send_sms(
						$normalized_admin,
						$this->plugin->settings->get( 'gateway.sender_id', '' ),
						$admin_msg,
						'woo_admin'
					);
				}
			}
		}
	}

	/**
	 * Build an SMS message for a given order status transition.
	 *
	 * Delegates to render_order_template() which reads the saved template from
	 * settings. Returns an empty string when the status is not handled or when
	 * the individual template is disabled by the admin.
	 *
	 * @param string   $status New status slug.
	 * @param WC_Order $order  The order.
	 *
	 * @return string SMS message, or empty string if status not handled / disabled.
	 */
	private function build_order_message( $status, WC_Order $order ) {
		$order_id       = $order->get_order_number();
		$total          = wp_strip_all_tags( wc_price( $order->get_total() ) );
		$site_name      = get_bloginfo( 'name' );
		$customer_name  = trim(
			$order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
		);

		$vars = array(
			'{site_name}'      => $site_name,
			'{order_id}'       => $order_id,
			'{total}'          => $total,
			'{customer_name}'  => $customer_name,
		);

		return $this->render_order_template( $status, $vars );
	}

	/**
	 * Render an order SMS template from saved settings.
	 *
	 * Looks up the template key for the given status, loads the saved template
	 * from the integrations settings group, checks the per-template `enabled`
	 * flag, selects the correct language string (ar for RTL sites, en otherwise),
	 * and performs placeholder substitution.
	 *
	 * @param string $status WooCommerce order status slug.
	 * @param array  $vars   Map of placeholder strings to replacement values.
	 *
	 * @return string Rendered SMS message, or empty string if not applicable.
	 */
	private function render_order_template( $status, array $vars ) {
		// Look up the settings key for this status.
		$template_key = self::$status_template_map[ $status ] ?? '';
		if ( '' === $template_key ) {
			return '';
		}

		// Load the template array from settings (merges saved + defaults).
		$templates = $this->plugin->settings->get_all_integration_templates();
		$template  = $templates[ $template_key ] ?? array();

		// Respect per-template enabled flag.
		if ( empty( $template['enabled'] ) ) {
			return '';
		}

		// Select language: Arabic for RTL sites, English otherwise.
		$lang    = ( function_exists( 'is_rtl' ) && is_rtl() ) ? 'ar' : 'en';
		$message = $template[ $lang ] ?? $template['en'] ?? '';

		if ( '' === $message ) {
			return '';
		}

		// Replace all placeholders.
		return str_replace( array_keys( $vars ), array_values( $vars ), $message );
	}

	// =========================================================================
	// Registration: phone field + welcome SMS
	// =========================================================================

	/**
	 * Render phone field on the WooCommerce registration form (My Account page).
	 *
	 * The field is optional — an empty submission is silently skipped.
	 * Re-populates the field value from $_POST on validation failure.
	 */
	public function render_wc_phone_field() {
		$phone = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone_reg'] ?? '' ) );
		?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="kwtsms_phone_reg">
				<?php esc_html_e( 'Phone Number (optional)', 'wp-kwtsms' ); ?>
			</label>
			<input
				type="tel"
				class="woocommerce-Input woocommerce-Input--text input-text"
				name="kwtsms_phone_reg"
				id="kwtsms_phone_reg"
				autocomplete="tel"
				value="<?php echo esc_attr( $phone ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. 96598765432', 'wp-kwtsms' ); ?>"
			/>
			<span class="description" style="font-size:12px;">
				<?php esc_html_e( 'Enter with country code. Used for SMS order notifications.', 'wp-kwtsms' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Validate the phone field on WC registration.
	 *
	 * An empty value is allowed (field is optional). A non-empty value must
	 * pass KwtSMS_API::normalize_phone() validation.
	 *
	 * @param WP_Error $errors   Existing validation errors.
	 * @param string   $username Submitted username.
	 * @param string   $email    Submitted email.
	 *
	 * @return WP_Error The (potentially augmented) errors object.
	 */
	public function validate_wc_phone_field( $errors, $username, $email ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone_reg'] ?? '' ) );
		if ( '' !== $phone ) {
			$phone      = KwtSMS_API::prepend_country_code_if_local( $phone, KwtSMS_API::get_default_dial_code() );
			$normalized = KwtSMS_API::normalize_phone( $phone );
			if ( is_wp_error( $normalized ) ) {
				$errors->add( 'kwtsms_invalid_phone', $normalized->get_error_message() );
			}
		}
		return $errors;
	}

	/**
	 * Save phone meta after WC account creation.
	 *
	 * Only runs when a non-empty phone was submitted. The phone is normalised
	 * before storage. If normalisation fails the whole step is skipped.
	 *
	 * Welcome SMS is handled by the `user_register` hook in KwtSMS_Plugin
	 * (fires for all registration types — WC checkout, WC My Account, standard WP).
	 *
	 * @param int $customer_id New customer user ID.
	 */
	public function save_wc_customer_phone( $customer_id ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone_reg'] ?? '' ) );
		if ( '' === $phone ) {
			return;
		}

		$phone      = KwtSMS_API::prepend_country_code_if_local( $phone, KwtSMS_API::get_default_dial_code() );
		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return;
		}

		update_user_meta( $customer_id, 'kwtsms_phone', $normalized );
	}

	// =========================================================================
	// Checkout OTP gate
	// =========================================================================

	/**
	 * Check if the checkout OTP gate is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_checkout_otp_enabled() {
		return (bool) $this->plugin->settings->get( 'integrations.woo_checkout_otp', 0 );
	}

	/**
	 * Render the OTP input field in the checkout form.
	 *
	 * If the current session has already been OTP-verified, shows a
	 * "Phone verified" confirmation and a hidden field instead of the input
	 * so the process_checkout_otp handler can short-circuit immediately.
	 *
	 * @param WC_Checkout $checkout WooCommerce checkout instance.
	 */
	public function render_checkout_otp_field( WC_Checkout $checkout ) {
		// Check if OTP has already been verified for this session.
		$session_key = $this->get_checkout_session_key();
		if ( $session_key && get_transient( self::CHECKOUT_OTP_PREFIX . $session_key ) ) {
			echo '<p style="color:#46b450;font-weight:600;">' . esc_html__( 'Phone verified', 'wp-kwtsms' ) . '</p>';
			echo '<input type="hidden" name="kwtsms_checkout_verified" value="1" />';
			return;
		}

		$token = wp_generate_password( 20, false );
		$nonce = wp_create_nonce( 'kwtsms_otp_nonce' );
		?>
		<div id="kwtsms-checkout-otp" style="margin-bottom:20px;padding:16px;border:1px solid #ddd;border-radius:4px;background:#fff8f0;">
			<h4 style="margin:0 0 8px;"><?php esc_html_e( 'Phone Verification', 'wp-kwtsms' ); ?></h4>
			<p style="font-size:14px;margin:0 0 10px;"><?php esc_html_e( 'We will send an OTP to your billing phone to verify your order.', 'wp-kwtsms' ); ?></p>
			<input type="hidden" name="kwtsms_checkout_token" value="<?php echo esc_attr( $token ); ?>" />
			<input type="hidden" name="kwtsms_checkout_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
			<input
				type="text"
				name="kwtsms_checkout_otp_code"
				inputmode="numeric"
				pattern="[0-9]*"
				autocomplete="one-time-code"
				placeholder="<?php esc_attr_e( 'Enter OTP code (leave blank to receive code first)', 'wp-kwtsms' ); ?>"
				style="width:100%;padding:8px;margin-top:4px;box-sizing:border-box;"
			/>
		</div>
		<?php
	}

	/**
	 * Process the checkout OTP field during WooCommerce checkout validation.
	 *
	 * Two-step flow:
	 *   1. First submit (no OTP code supplied): generate + send OTP to billing
	 *      phone, store pending transient, add a WC notice to prompt the user
	 *      to enter the code, and block order creation.
	 *   2. Second submit (OTP code present): verify against the stored transient.
	 *      On success, mark the session as verified and allow order creation.
	 *      On failure, add an error notice and block order creation.
	 */
	public function process_checkout_otp() {
		// If already verified for this session, skip.
		$session_key = $this->get_checkout_session_key();
		if ( $session_key && get_transient( self::CHECKOUT_OTP_PREFIX . $session_key ) ) {
			return;
		}

		// Nonce check.
		$nonce = sanitize_key( wp_unslash( $_POST['kwtsms_checkout_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'kwtsms_otp_nonce' ) ) {
			wc_add_notice( __( 'Security check failed. Please refresh and try again.', 'wp-kwtsms' ), 'error' );
			return;
		}

		$token    = sanitize_text_field( wp_unslash( $_POST['kwtsms_checkout_token'] ?? '' ) );
		$otp_code = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['kwtsms_checkout_otp_code'] ?? '' ) ) );

		// Get billing phone.
		$raw_phone  = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) );
		$raw_phone  = KwtSMS_API::prepend_country_code_if_local( $raw_phone, KwtSMS_API::get_default_dial_code() );
		$normalized = KwtSMS_API::normalize_phone( $raw_phone );
		if ( is_wp_error( $normalized ) ) {
			wc_add_notice( $normalized->get_error_message(), 'error' );
			return;
		}

		$transient_key = self::CHECKOUT_OTP_PREFIX . $token;

		if ( '' === $otp_code ) {
			// First submit — generate and send OTP.
			$otp    = $this->plugin->otp->generate( 'checkout_' . $token, 'checkout' );
			$msg    = $this->plugin->otp->build_message( $otp, 'login_otp' );
			$result = $this->plugin->api->send_sms(
				$normalized,
				$this->plugin->settings->get( 'gateway.sender_id', '' ),
				$msg,
				'checkout'
			);
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
				return;
			}
			// Store the phone so we can verify the same number on the second submit.
			set_transient( $transient_key . '_pending', $normalized, 15 * MINUTE_IN_SECONDS );
			wc_add_notice( __( 'An OTP has been sent to your phone. Enter it above and place the order again.', 'wp-kwtsms' ), 'notice' );
			return; // Prevent order creation on first submit.
		}

		// Second submit — verify OTP.
		$stored_phone = get_transient( $transient_key . '_pending' );
		if ( ! $stored_phone || $stored_phone !== $normalized ) {
			wc_add_notice( __( 'Session expired. Please refresh and try again.', 'wp-kwtsms' ), 'error' );
			return;
		}

		$result = $this->plugin->otp->verify( 'checkout_' . $token, $otp_code, 'checkout' );
		if ( 'valid' !== $result ) {
			wc_add_notice( __( 'Incorrect or expired OTP. Please try again.', 'wp-kwtsms' ), 'error' );
			return;
		}

		// Mark session as verified so subsequent page loads do not re-prompt.
		if ( $session_key ) {
			set_transient( self::CHECKOUT_OTP_PREFIX . $session_key, 1, HOUR_IN_SECONDS );
		}
		delete_transient( $transient_key . '_pending' );
	}

	/**
	 * Clear the checkout OTP session transient after an order is successfully created.
	 *
	 * Prevents the "verified" state from persisting indefinitely in the transient
	 * store after the order has been placed.
	 *
	 * @param WC_Order $order The newly created order.
	 */
	public function clear_checkout_otp_session( WC_Order $order ) {
		$session_key = $this->get_checkout_session_key();
		if ( $session_key ) {
			delete_transient( self::CHECKOUT_OTP_PREFIX . $session_key );
		}
	}

	/**
	 * Get a session-specific key for checkout OTP verification.
	 *
	 * Uses the WooCommerce session customer ID when available. Falls back to
	 * null (meaning per-session verification is skipped) when WC sessions are
	 * unavailable (e.g. during unit tests or very early in the request).
	 *
	 * @return string|null Session key, or null if WC session is unavailable.
	 */
	private function get_checkout_session_key() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return WC()->session->get_customer_id() ?: null;
		}
		return null;
	}
}
