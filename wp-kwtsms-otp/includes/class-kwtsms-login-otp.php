<?php
/**
 * OTP Login — 2FA and Passwordless flows.
 *
 * 2FA Flow:
 *   1. authenticate filter (priority 30): intercepts validated WP_User,
 *      stores partial auth transient, sets httponly cookie, returns WP_Error.
 *   2. wp_login_failed action: detects our error code, redirects to OTP page.
 *   3. login_init (action=kwtsms_otp): renders OTP form.
 *   4. POST to kwtsms_otp: verifies OTP, issues auth cookies on success.
 *
 * Passwordless Flow:
 *   1. login_init (action=kwtsms_passwordless): renders phone entry form.
 *   2. POST: normalises phone, checks rate limit, finds user by meta,
 *      generates OTP, sends SMS, redirects to OTP entry.
 *   3. OTP entry (same kwtsms_otp action, context=passwordless).
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Login_OTP
 */
class KwtSMS_Login_OTP {

	/**
	 * Plugin manager (provides api, otp, settings).
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Partial auth cookie name.
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
		$this->cookie_name = 'kwtsms_partial_' . COOKIEHASH;

		add_filter( 'authenticate', array( $this, 'intercept_login' ), 30, 3 );
		add_action( 'wp_login_failed', array( $this, 'redirect_to_otp' ), 10, 2 );
		add_action( 'login_init', array( $this, 'handle_login_actions' ) );
		add_action( 'login_form', array( $this, 'add_passwordless_link' ) );
	}

	// =========================================================================
	// 2FA intercept
	// =========================================================================

	/**
	 * Intercept validated login credentials and require OTP.
	 *
	 * Fires on the `authenticate` filter at priority 30 (after WP at 20).
	 * Only acts if $user is a valid WP_User and 2FA is enabled.
	 *
	 * @param null|WP_User|WP_Error $user     Authenticated user or error.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 *
	 * @return WP_User|WP_Error
	 */
	public function intercept_login( $user, $username, $password ) {
		// 2FA is only active when mode is '2fa' or 'both'.
		$mode = $this->plugin->settings->get( 'general.otp_mode', '2fa' );
		if ( 'passwordless' === $mode ) {
			return $user;
		}

		// Only intercept successful logins.
		if ( ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		// Per-role enforcement: if a non-empty role list is configured, skip OTP
		// for users whose roles do not intersect with the required list.
		// Empty list (default) means all users must pass OTP.
		$required_roles = $this->plugin->settings->get( 'general.otp_required_roles', array() );
		if ( ! empty( $required_roles ) ) {
			$user_roles = $user->roles ?? array();
			// On multisite, super admins have an empty roles array.
			// Treat them as 'administrator' so they are subject to the same
			// OTP enforcement as regular administrators.
			if ( empty( $user_roles ) && function_exists( 'is_super_admin' ) && is_super_admin( $user->ID ) ) {
				$user_roles = array( 'administrator' );
			}
			$intersect = array_intersect( $user_roles, (array) $required_roles );
			if ( empty( $intersect ) ) {
				// User's role is not in the required list — bypass OTP, allow direct login.
				return $user;
			}
		}

		$phone = get_user_meta( $user->ID, 'kwtsms_phone', true );

		// If user has no phone, honour admin setting for bypass.
		if ( empty( $phone ) ) {
			// For now: skip 2FA, let them log in without OTP.
			return $user;
		}

		// Rate-limit checks: per-phone, per-IP, per-account.
		if ( $this->plugin->otp->is_rate_limited( $phone, 'login', $user->ID ) ) {
			return new WP_Error(
				'kwtsms_rate_limited',
				__( 'Too many OTP requests for this number. Please wait a few minutes before trying again.', 'wp-kwtsms-otp' )
			);
		}
		if ( $this->plugin->otp->is_ip_rate_limited( 'login', $user->ID, $phone ) ) {
			return new WP_Error(
				'kwtsms_rate_limited',
				__( 'Too many OTP requests from this location. Please wait a few minutes before trying again.', 'wp-kwtsms-otp' )
			);
		}
		if ( $this->plugin->otp->is_user_rate_limited( $user->ID, 'login', $phone ) ) {
			return new WP_Error(
				'kwtsms_rate_limited',
				__( 'Too many OTP requests for this account. Please wait a few minutes before trying again.', 'wp-kwtsms-otp' )
			);
		}

		// Anti-enumeration: silently fail without allocating a session.
		// User sees the OTP screen but has no valid transient to verify against.
		if ( $this->plugin->otp->is_phone_blocked( $phone ) ) {
			return new WP_Error( 'kwtsms_otp_required', '' );
		}

		// Generate OTP and send SMS.
		$otp_code = $this->plugin->otp->generate( $user->ID, 'login' );
		$message  = $this->plugin->otp->build_message( $otp_code, 'login_otp' );
		$result   = $this->plugin->api->send_sms(
			$phone,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'login'
		);

		if ( is_wp_error( $result ) ) {
			// SMS failed — log error but allow login to proceed (fail-open).
			// This prevents SMS gateway issues from locking out all users.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions
				error_log( '[kwtsms-otp] SMS send failed for user ' . $user->ID . ': ' . $result->get_error_message() );
			}
			return $user;
		}

		$this->plugin->otp->increment_rate( $phone );
		$this->plugin->otp->increment_ip_rate();
		$this->plugin->otp->increment_user_rate( $user->ID );

		// Create partial auth session.
		$token = wp_generate_password( 40, false );
		set_transient(
			'kwtsms_partial_auth_' . $token,
			array(
				'user_id' => $user->ID,
				'action'  => 'login',
				'phone'   => $phone,
			),
			15 * MINUTE_IN_SECONDS
		);

		// Store token in httponly cookie.
		$this->set_partial_auth_cookie( $token );

		return new WP_Error( 'kwtsms_otp_required', '' );
	}

	/**
	 * After login failure — redirect to OTP page if our error caused it.
	 *
	 * @param string   $username Username.
	 * @param WP_Error $error    The error returned from authenticate.
	 */
	public function redirect_to_otp( $username, $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}
		if ( ! in_array( 'kwtsms_otp_required', $error->get_error_codes(), true ) ) {
			return;
		}

		$redirect_to = sanitize_url( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		$otp_url     = add_query_arg(
			array(
				'action'      => 'kwtsms_otp',
				'redirect_to' => rawurlencode( $redirect_to ),
			),
			wp_login_url()
		);

		wp_safe_redirect( $otp_url );
		exit;
	}

	// =========================================================================
	// Login page action handlers
	// =========================================================================

	/**
	 * Handle custom wp-login.php actions for OTP and passwordless flows.
	 *
	 * Fires on `login_init` at the very top of wp-login.php.
	 */
	public function handle_login_actions() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login';

		if ( 'kwtsms_otp' === $action ) {
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				$this->handle_otp_submission();
			}
			$this->render_otp_page();
			exit;
		}

		if ( 'kwtsms_passwordless' === $action ) {
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				$this->handle_passwordless_submission();
			}
			$this->render_passwordless_page();
			exit;
		}
	}

	/**
	 * Inject a "Login with SMS" link below the login form.
	 */
	public function add_passwordless_link() {
		$mode = $this->plugin->settings->get( 'general.otp_mode', '2fa' );
		if ( ! in_array( $mode, array( 'passwordless', 'both' ), true ) ) {
			return;
		}
		printf(
			'<p style="text-align:center;margin-top:10px;"><a href="%s">%s</a></p>',
			esc_url( add_query_arg( 'action', 'kwtsms_passwordless', wp_login_url() ) ),
			esc_html__( 'Login with SMS OTP', 'wp-kwtsms-otp' )
		);
	}

	// =========================================================================
	// OTP form handler
	// =========================================================================

	/**
	 * Handle OTP code submission (both 2FA and passwordless contexts).
	 *
	 * On success: issues WP auth cookies and redirects.
	 * On failure: falls through and re-renders the OTP page with an error.
	 */
	private function handle_otp_submission() {
		// Nonce check.
		if ( ! isset( $_POST['kwtsms_otp_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['kwtsms_otp_nonce'] ) ),
				'kwtsms_otp_submit'
			)
		) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'wp-kwtsms-otp' ) );
		}

		// Retrieve session token from cookie.
		$token = $this->get_partial_auth_token();
		if ( empty( $token ) ) {
			$this->render_otp_page( __( 'Session expired. Please log in again.', 'wp-kwtsms-otp' ) );
			exit;
		}

		$partial = get_transient( 'kwtsms_partial_auth_' . $token );
		if ( ! $partial ) {
			$this->clear_partial_auth_cookie();
			$this->render_otp_page( __( 'Session expired. Please log in again.', 'wp-kwtsms-otp' ) );
			exit;
		}

		$user_id    = absint( $partial['user_id'] );
		$otp_action = sanitize_key( $partial['action'] ?? 'login' );
		$otp_phone  = sanitize_text_field( $partial['phone'] ?? '' );
		$raw_code   = sanitize_text_field( wp_unslash( $_POST['kwtsms_code'] ?? '' ) );
		$submitted  = preg_replace( '/\D/', '', $raw_code ); // Digits only.

		// Detect suspicious input — log as hacking attempt before processing.
		$expected_length = (int) $this->plugin->settings->get( 'general.otp_length', 6 );
		$ip              = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP ) ? ( $_SERVER['REMOTE_ADDR'] ?? '' ) : '';
		$raw_len         = strlen( $raw_code );

		// Log if: raw input had non-digit chars, code is empty, or code length is wrong.
		$is_suspicious = ( $raw_len > 0 && $raw_len !== strlen( $submitted ) ) // non-digit chars present
			|| ( $raw_len > $expected_length + 4 ) // excessively long — possible injection
			|| ( $raw_len > 0 && strlen( $submitted ) !== $expected_length && strlen( $submitted ) !== 0 ); // wrong length

		if ( $is_suspicious ) {
			KwtSMS_API::append_attempt_log( $user_id, $otp_phone, $ip, $otp_action, 'invalid_input' );
		}

		// Empty code — no point calling verify.
		if ( '' === $submitted ) {
			$this->render_otp_page( __( 'Please enter your verification code.', 'wp-kwtsms-otp' ), $token );
			exit;
		}

		$result = $this->plugin->otp->verify( $user_id, $submitted, $otp_action, $user_id, $otp_phone );

		switch ( $result ) {
			case 'valid':
				delete_transient( 'kwtsms_partial_auth_' . $token );
				$this->clear_partial_auth_cookie();
				$this->issue_auth_and_redirect( $user_id );
				break;

			case 'invalid':
				$remaining = $this->plugin->otp->get_remaining_attempts( $user_id );
				if ( 0 === $remaining ) {
					// Transient expired between verify() and get_remaining_attempts() — treat as expired.
					$this->render_otp_page(
						__( 'Your code has expired. Click "Resend" to get a new one.', 'wp-kwtsms-otp' ),
						$token
					);
				} else {
					$this->render_otp_page(
						sprintf(
							/* translators: %d: number of remaining attempts */
							_n(
								'Incorrect code. %d attempt remaining.',
								'Incorrect code. %d attempts remaining.',
								$remaining,
								'wp-kwtsms-otp'
							),
							$remaining
						),
						$token
					);
				}
				exit;

			case 'expired':
				$this->render_otp_page(
					__( 'Your code has expired. Click "Resend" to get a new one.', 'wp-kwtsms-otp' ),
					$token
				);
				exit;

			case 'max_attempts':
				delete_transient( 'kwtsms_partial_auth_' . $token );
				$this->clear_partial_auth_cookie();
				$this->render_otp_page(
					__( 'Too many incorrect attempts. Please log in again to request a new code.', 'wp-kwtsms-otp' )
				);
				exit;

			default:
				$this->render_otp_page( __( 'Something went wrong. Please try again.', 'wp-kwtsms-otp' ), $token );
				exit;
		}
	}

	/**
	 * Issue WordPress auth cookies and redirect after successful OTP verification.
	 *
	 * @param int $user_id Authenticated user ID.
	 */
	private function issue_auth_and_redirect( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect_to = sanitize_url( wp_unslash( $_GET['redirect_to'] ?? '' ) );
		if ( empty( $redirect_to ) ) {
			$redirect_to = admin_url();
		}
		wp_safe_redirect( $redirect_to );
		exit;
	}

	// =========================================================================
	// Passwordless form handler
	// =========================================================================

	/**
	 * Handle passwordless phone-number form submission.
	 *
	 * Looks up user by phone meta, generates OTP, redirects to OTP entry.
	 * Shows the same "check your phone" message regardless of whether the
	 * phone is registered (anti-enumeration).
	 */
	private function handle_passwordless_submission() {
		if ( ! isset( $_POST['kwtsms_passwordless_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['kwtsms_passwordless_nonce'] ) ),
				'kwtsms_passwordless_submit'
			)
		) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'wp-kwtsms-otp' ) );
		}

		// CAPTCHA verification.
		$captcha = new KwtSMS_Captcha( $this->plugin->settings );
		$captcha_result = $captcha->verify( $_POST );
		if ( is_wp_error( $captcha_result ) ) {
			$this->render_passwordless_page( $captcha_result->get_error_message() );
			exit;
		}

		$raw_phone  = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone'] ?? '' ) );
		$normalized = KwtSMS_API::normalize_phone( $raw_phone );

		if ( is_wp_error( $normalized ) ) {
			$this->render_passwordless_page( $normalized->get_error_message() );
			exit;
		}

		// Rate-limit checks: per-phone and per-IP (no user_id known yet).
		if ( $this->plugin->otp->is_rate_limited( $normalized, 'passwordless' ) ) {
			$this->render_passwordless_page(
				__( 'Too many requests. Please wait a few minutes before trying again.', 'wp-kwtsms-otp' )
			);
			exit;
		}
		if ( $this->plugin->otp->is_ip_rate_limited( 'passwordless', null, $normalized ) ) {
			$this->render_passwordless_page(
				__( 'Too many requests from this location. Please wait a few minutes before trying again.', 'wp-kwtsms-otp' )
			);
			exit;
		}

		// Generic response — prevents phone enumeration.
		$generic_message = __( 'If an account is associated with this number, an OTP will be sent shortly.', 'wp-kwtsms-otp' );

		// Look up user by kwtsms_phone meta.
		$users = get_users(
			array(
				'meta_key'   => 'kwtsms_phone',
				'meta_value' => $normalized,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);

		if ( empty( $users ) ) {
			// No match — show generic message, no OTP sent.
			$this->render_passwordless_page( '', $generic_message );
			exit;
		}

		$user_id = (int) $users[0];

		// Per-role enforcement: check whether this user's role requires OTP.
		// If a non-empty role list is configured and the user's role is not in
		// it, skip OTP entirely and log them in directly via auth cookies.
		$required_roles = $this->plugin->settings->get( 'general.otp_required_roles', array() );
		if ( ! empty( $required_roles ) ) {
			$user_obj   = get_userdata( $user_id );
			$user_roles = ( $user_obj ? $user_obj->roles : array() ) ?? array();
			// On multisite, super admins have an empty roles array —
			// treat them as 'administrator' for the purpose of this check.
			if ( empty( $user_roles ) && function_exists( 'is_super_admin' ) && is_super_admin( $user_id ) ) {
				$user_roles = array( 'administrator' );
			}
			$intersect = array_intersect( $user_roles, (array) $required_roles );
			if ( empty( $intersect ) ) {
				// User's role is not subject to OTP — issue auth cookies directly.
				$this->issue_auth_and_redirect( $user_id );
			}
		}

		// Per-account rate-limit check now that user_id is known.
		if ( $this->plugin->otp->is_user_rate_limited( $user_id, 'passwordless', $normalized ) ) {
			$this->render_passwordless_page(
				__( 'Too many requests. Please wait a few minutes before trying again.', 'wp-kwtsms-otp' )
			);
			exit;
		}

		// Anti-enumeration: silently succeed for blocked phones without sending SMS.
		if ( $this->plugin->otp->is_phone_blocked( $normalized ) ) {
			$this->render_passwordless_page( '', $generic_message );
			exit;
		}

		$otp_code = $this->plugin->otp->generate( $user_id, 'passwordless' );
		$message  = $this->plugin->otp->build_message( $otp_code, 'login_otp' );
		$result   = $this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'passwordless'
		);

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions
				error_log( '[kwtsms-otp] Passwordless SMS failed: ' . $result->get_error_message() );
			}
		}

		$this->plugin->otp->increment_rate( $normalized );
		$this->plugin->otp->increment_ip_rate();
		$this->plugin->otp->increment_user_rate( $user_id );

		// Create partial auth session.
		$token = wp_generate_password( 40, false );
		set_transient(
			'kwtsms_partial_auth_' . $token,
			array(
				'user_id' => $user_id,
				'action'  => 'passwordless',
				'phone'   => $normalized,
			),
			15 * MINUTE_IN_SECONDS
		);
		$this->set_partial_auth_cookie( $token );

		$otp_url = add_query_arg(
			array(
				'action'  => 'kwtsms_otp',
				'context' => 'passwordless',
			),
			wp_login_url()
		);
		wp_safe_redirect( $otp_url );
		exit;
	}

	// =========================================================================
	// Page rendering
	// =========================================================================

	/**
	 * Render the OTP code entry page.
	 *
	 * @param string $error_message Optional error message to display.
	 * @param string $token         Optional session token (from cookie if omitted).
	 */
	private function render_otp_page( $error_message = '', $token = '' ) {
		nocache_headers();
		$token           = $token ?: $this->get_partial_auth_token();
		$otp_length      = (int) $this->plugin->settings->get( 'general.otp_length', 6 );
		$cooldown        = (int) $this->plugin->settings->get( 'general.resend_cooldown', 120 );
		$redirect_to     = sanitize_url( wp_unslash( $_GET['redirect_to'] ?? '' ) );
		$nonce_resend    = wp_create_nonce( 'kwtsms_otp_nonce' );
		$login_url       = wp_login_url();
		$plugin_settings = $this->plugin->settings;

		include KWTSMS_OTP_DIR . 'includes/views/page-otp.php';
	}

	/**
	 * Render the passwordless phone number entry page.
	 *
	 * @param string $error_message   Optional error.
	 * @param string $success_message Optional success/info message.
	 */
	private function render_passwordless_page( $error_message = '', $success_message = '' ) {
		nocache_headers();
		$plugin_settings  = $this->plugin->settings;

		// Load country data for the dial-code dropdown.
		$all_countries   = include KWTSMS_OTP_DIR . 'includes/data/country-codes.php';
		$allowed_iso2    = (array) $plugin_settings->get( 'general.allowed_countries', array( 'KW', 'SA', 'AE', 'BH', 'QA', 'OM' ) );
		$default_iso2    = (string) $plugin_settings->get( 'general.default_country_code', 'KW' );

		// Filter to only allowed countries, preserving order from allowed_iso2.
		$cc_by_iso2 = array();
		foreach ( $all_countries as $cc ) {
			$cc_by_iso2[ $cc['iso2'] ] = $cc;
		}
		$allowed_countries = array();
		foreach ( $allowed_iso2 as $iso2 ) {
			if ( isset( $cc_by_iso2[ $iso2 ] ) ) {
				$allowed_countries[] = $cc_by_iso2[ $iso2 ];
			}
		}
		// Fallback: if no allowed countries match, show all.
		if ( empty( $allowed_countries ) ) {
			$allowed_countries = $all_countries;
		}

		// GeoIP detect — pre-select visitor's country (or fall back to default).
		$detected_iso2 = KwtSMS_GeoIP::detect_iso2() ?? $default_iso2;

		// If detected country not in allowed list, fall back to default.
		$allowed_iso2_flat = array_column( $allowed_countries, 'iso2' );
		if ( ! in_array( $detected_iso2, $allowed_iso2_flat, true ) ) {
			$detected_iso2 = in_array( $default_iso2, $allowed_iso2_flat, true ) ? $default_iso2 : ( $allowed_iso2_flat[0] ?? '' );
		}

		include KWTSMS_OTP_DIR . 'includes/views/page-passwordless.php';
	}

	// =========================================================================
	// Cookie helpers
	// =========================================================================

	/**
	 * Set the partial auth cookie.
	 *
	 * @param string $token Session token.
	 */
	private function set_partial_auth_cookie( $token ) {
		$expiry  = time() + 15 * MINUTE_IN_SECONDS;
		$secure  = is_ssl();
		$options = array(
			'expires'  => $expiry,
			'path'     => COOKIEPATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Strict',
		);
		setcookie( $this->cookie_name, $token, $options );
	}

	/**
	 * Get the partial auth token from the cookie.
	 *
	 * @return string Token, or empty string if not set.
	 */
	private function get_partial_auth_token() {
		return sanitize_text_field( $_COOKIE[ $this->cookie_name ] ?? '' );
	}

	/**
	 * Clear the partial auth cookie.
	 */
	private function clear_partial_auth_cookie() {
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
