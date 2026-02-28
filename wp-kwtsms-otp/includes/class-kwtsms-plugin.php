<?php
/**
 * Main plugin manager / service locator.
 *
 * Bootstraps and wires all plugin modules. Uses a singleton pattern so only
 * one instance is created per request. Each module is responsible for
 * registering its own hooks in its constructor.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Plugin
 *
 * Central manager that initialises all plugin components.
 */
class KwtSMS_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var KwtSMS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings helper.
	 *
	 * @var KwtSMS_Settings
	 */
	public $settings;

	/**
	 * kwtsms API client.
	 *
	 * @var KwtSMS_API
	 */
	public $api;

	/**
	 * OTP engine.
	 *
	 * @var KwtSMS_OTP_Engine
	 */
	public $otp;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return KwtSMS_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — private to enforce singleton.
	 *
	 * Instantiates all modules. Module constructors register their own hooks,
	 * keeping this class thin.
	 */
	private function __construct() {
		$this->settings = new KwtSMS_Settings();
		$this->api      = new KwtSMS_API(
			$this->settings->get( 'gateway.api_username', '' ),
			$this->settings->get( 'gateway.api_password', '' ),
			(bool) $this->settings->get( 'gateway.test_mode', true ),
			(bool) $this->settings->get( 'general.debug_logging', 0 )
		);
		$this->otp = new KwtSMS_OTP_Engine( $this->settings );

		// Admin-only modules — only load on admin requests to keep frontend lean.
		if ( is_admin() ) {
			new KwtSMS_Admin( $this );
		}

		// Frontend / login modules — load on all requests.
		new KwtSMS_User_Meta();

		// Load login/reset OTP only when enabled in settings.
		if ( $this->settings->get( 'general.login_otp', 1 ) ) {
			new KwtSMS_Login_OTP( $this );
		}
		if ( $this->settings->get( 'general.reset_otp', 1 ) ) {
			new KwtSMS_Reset_OTP( $this );
		}

		// CAPTCHA module — always loaded so it can enqueue on login page.
		new KwtSMS_Captcha( $this->settings );

		// AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Register wp_ajax_* handlers used across modules.
	 *
	 * Keeping registrations here avoids scattering add_action calls.
	 */
	private function register_ajax_handlers() {
		// Credential verification (admin only).
		add_action( 'wp_ajax_kwtsms_verify_credentials', array( $this, 'ajax_verify_credentials' ) );

		// OTP resend (guests — not yet logged in).
		add_action( 'wp_ajax_nopriv_kwtsms_resend_otp', array( $this, 'ajax_resend_otp' ) );
		add_action( 'wp_ajax_kwtsms_resend_otp', array( $this, 'ajax_resend_otp' ) );

		// Test SMS send (admin only).
		add_action( 'wp_ajax_kwtsms_send_test_sms', array( $this, 'ajax_send_test_sms' ) );
	}

	/**
	 * AJAX: verify API credentials and return sender IDs + balance.
	 *
	 * Called from the Gateway Settings page when the admin clicks
	 * "Save & Verify Credentials".
	 *
	 * Security: nonce + manage_options capability check.
	 */
	public function ajax_verify_credentials() {
		check_ajax_referer( 'kwtsms_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-kwtsms-otp' ) ), 403 );
		}

		$username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		$password = sanitize_text_field( wp_unslash( $_POST['password'] ?? '' ) );

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Username and password are required.', 'wp-kwtsms-otp' ) ) );
		}

		// Use a temporary API instance with the submitted (not yet saved) credentials.
		$api        = new KwtSMS_API( $username, $password, false );
		$sender_ids = $api->get_sender_ids();
		$balance    = $api->get_balance();

		if ( is_wp_error( $sender_ids ) ) {
			// Clear saved verified state so dependent features stay locked.
			$gw = get_option( 'kwtsms_otp_gateway', array() );
			if ( ! empty( $gw['credentials_verified'] ) ) {
				$gw['credentials_verified'] = 0;
				update_option( 'kwtsms_otp_gateway', $gw );
			}
			wp_send_json_error(
				array( 'message' => $sender_ids->get_error_message() )
			);
		}

		// Also fetch coverage to persist alongside sender IDs and balance.
		$coverage     = $api->get_coverage();
		$coverage_arr = ( ! is_wp_error( $coverage ) ) ? (array) $coverage : array();

		// Persist the verified state, credentials, and all fetched gateway data
		// so that page reloads (and multi-worker SQLite in WP Playground) do not
		// lose the sender ID list, balance, or coverage between AJAX and form-save.
		$gw                         = get_option( 'kwtsms_otp_gateway', array() );
		$gw['credentials_verified'] = 1;
		$gw['api_username']         = $username;
		$gw['api_password']         = $password;
		$gw['sender_ids']           = (array) $sender_ids;
		$gw['coverage']             = $coverage_arr;
		if ( ! is_wp_error( $balance ) ) {
			$gw['balance_available']  = $balance['available']  ?? null;
			$gw['balance_purchased']  = $balance['purchased']  ?? null;
			$gw['balance_updated_at'] = time();
		}
		update_option( 'kwtsms_otp_gateway', $gw );

		$balance_data = is_wp_error( $balance ) ? null : $balance;

		wp_send_json_success(
			array(
				'sender_ids' => $sender_ids,
				'balance'    => $balance_data,
				'coverage'   => $coverage_arr,
			)
		);
	}

	/**
	 * AJAX: resend OTP to the user's phone.
	 *
	 * Called from the OTP entry page when the user clicks "Resend".
	 * Rate limiting is enforced inside KwtSMS_OTP_Engine.
	 */
	public function ajax_resend_otp() {
		check_ajax_referer( 'kwtsms_otp_nonce', 'nonce' );

		$session_token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

		if ( empty( $session_token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'wp-kwtsms-otp' ) ) );
		}

		// Retrieve partial auth to find the user.
		$partial = get_transient( 'kwtsms_partial_auth_' . $session_token );
		if ( ! $partial ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please log in again.', 'wp-kwtsms-otp' ) ) );
		}

		$user_id = absint( $partial['user_id'] );
		$phone   = get_user_meta( $user_id, 'kwtsms_phone', true );

		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'No phone number on this account.', 'wp-kwtsms-otp' ) ) );
		}

		// Rate limit check.
		if ( $this->otp->is_rate_limited( $phone ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'Too many requests. Please wait before trying again.', 'wp-kwtsms-otp' ),
					'cooldown' => $this->settings->get( 'general.resend_cooldown', 60 ),
				)
			);
		}

		// Generate and send new OTP.
		$otp_code = $this->otp->generate( $user_id, 'login' );
		$message  = $this->otp->build_message( $otp_code, 'login_otp' );
		$result   = $this->api->send_sms(
			$phone,
			$this->settings->get( 'gateway.sender_id', '' ),
			$message,
			'login'
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->otp->increment_rate( $phone );

		wp_send_json_success(
			array(
				'message'  => __( 'A new code has been sent to your phone.', 'wp-kwtsms-otp' ),
				'cooldown' => $this->settings->get( 'general.resend_cooldown', 60 ),
			)
		);
	}

	/**
	 * AJAX: send a test SMS to the configured test phone.
	 *
	 * Security: nonce + manage_options.
	 */
	public function ajax_send_test_sms() {
		check_ajax_referer( 'kwtsms_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-kwtsms-otp' ) ), 403 );
		}

		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		if ( empty( $phone ) ) {
			$phone = $this->settings->get( 'gateway.test_phone', '' );
		}

		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			wp_send_json_error( array( 'message' => $normalized->get_error_message() ) );
		}

		$test_code = $this->otp->generate( 'test_admin', 'login' );
		$message   = $this->otp->build_message( $test_code, 'login_otp' );
		$result    = $this->api->send_sms(
			$normalized,
			$this->settings->get( 'gateway.sender_id', '' ),
			$message,
			'test'
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$is_test_mode = (bool) $this->settings->get( 'gateway.test_mode', false );

		wp_send_json_success(
			array(
				'phone'     => esc_html( $normalized ),
				'code'      => esc_html( $test_code ),
				'test_mode' => $is_test_mode,
			)
		);
	}
}
