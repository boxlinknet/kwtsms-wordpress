<?php
/**
 * kwtsms REST API Client.
 *
 * Wraps all calls to the kwtsms JSON REST API (v4.1).
 * Always uses HTTPS + POST as required by the API documentation.
 * Credentials are never logged or output to the browser.
 *
 * API base: https://www.kwtsms.com/API/
 *
 * @package KwtSMS_OTP
 * @see     docs/KwtSMS.com_API_Documentation_v41.pdf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_API
 */
class KwtSMS_API {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	const BASE_URL = 'https://www.kwtsms.com/API/';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 15;

	/**
	 * API username.
	 *
	 * @var string
	 */
	private $username;

	/**
	 * API password.
	 *
	 * @var string
	 */
	private $password;

	/**
	 * Whether to send in test mode (test=1 — messages queued but not delivered).
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Whether detailed debug logging to file is enabled.
	 *
	 * @var bool
	 */
	private $debug_mode;

	/**
	 * Constructor.
	 *
	 * @param string $username   kwtsms API username.
	 * @param string $password   kwtsms API password.
	 * @param bool   $test_mode  Whether to use test mode.
	 * @param bool   $debug_mode Whether to write detailed debug logs to file.
	 */
	public function __construct( $username, $password, $test_mode = false, $debug_mode = false ) {
		$this->username   = $username;
		$this->password   = $password;
		$this->test_mode  = (bool) $test_mode;
		$this->debug_mode = (bool) $debug_mode;
	}

	// =========================================================================
	// Public API methods
	// =========================================================================

	/**
	 * Retrieve account balance.
	 *
	 * @return array{available: float, purchased: float}|WP_Error
	 */
	public function get_balance() {
		$response = $this->request( 'balance/', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'available'  => (float) ( $response['available'] ?? 0 ),
			'purchased'  => (float) ( $response['purchased'] ?? 0 ),
		);
	}

	/**
	 * Retrieve available sender IDs for this account.
	 *
	 * Results are cached for 5 minutes to avoid redundant API calls.
	 *
	 * @return string[]|WP_Error Array of sender ID strings on success.
	 */
	public function get_sender_ids() {
		$cache_key = 'kwtsms_senderids_' . md5( $this->username );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request( 'senderid/', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$ids = isset( $response['senderid'] ) && is_array( $response['senderid'] )
			? array_map( 'sanitize_text_field', $response['senderid'] )
			: array();

		// Cache for 5 minutes.
		set_transient( $cache_key, $ids, 5 * MINUTE_IN_SECONDS );

		return $ids;
	}

	/**
	 * Send an SMS message.
	 *
	 * Phone number must already be normalised (digits only, with country code).
	 * Message must be sanitised (no emoji, no HTML).
	 *
	 * @param string $phone     Recipient phone in international format (e.g. 96598765432).
	 * @param string $sender_id Approved sender ID for this account.
	 * @param string $message   Message text (English or Arabic).
	 * @param string $type      Context type for logging: 'login'|'reset'|'passwordless'|'welcome'|'test'.
	 *
	 * @return array{msg_id: string, balance_after: float}|WP_Error
	 */
	public function send_sms( $phone, $sender_id, $message, $type = 'login' ) {
		$this->write_debug_log( 'send_sms()', "type={$type} phone={$phone} sender={$sender_id}" );

		// ── Sanity checks ──────────────────────────────────────────────────────
		if ( empty( $phone ) ) {
			$err = new WP_Error(
				'kwtsms_missing_phone',
				__( 'Cannot send SMS: phone number is missing. Please check user phone in their profile.', 'wp-kwtsms-otp' )
			);
			$this->write_debug_log( 'send_sms()', 'ABORT: phone missing' );
			self::append_send_log( '?', 'failed', $type );
			self::append_sms_history( $phone, $message, 'failed', $type, '' );
			return $err;
		}

		if ( empty( $message ) ) {
			$err = new WP_Error(
				'kwtsms_missing_message',
				__( 'Cannot send SMS: message is empty. Please check your SMS templates in Settings → kwtSMS → Templates.', 'wp-kwtsms-otp' )
			);
			$this->write_debug_log( 'send_sms()', 'ABORT: message empty' );
			self::append_send_log( $phone, 'failed', $type );
			self::append_sms_history( $phone, $message, 'failed', $type, '' );
			return $err;
		}

		// In test mode: sender_id not needed for actual delivery — skip sender check.
		if ( ! $this->test_mode && empty( $sender_id ) ) {
			$err = new WP_Error(
				'kwtsms_missing_sender_id',
				__( 'Cannot send SMS: no Sender ID configured. Go to Settings → kwtSMS → Gateway, save your credentials, then choose a Sender ID from the dropdown.', 'wp-kwtsms-otp' )
			);
			$this->write_debug_log( 'send_sms()', 'ABORT: sender_id empty (live mode)' );
			self::append_send_log( $phone, 'failed', $type );
			self::append_sms_history( $phone, $message, 'failed', $type, '' );
			return $err;
		}

		// ── Balance check ─────────────────────────────────────────────────────
		// Only run in live mode — test mode never consumes credits.
		if ( ! $this->test_mode ) {
			$balance_check = $this->check_balance_before_send();
			if ( is_wp_error( $balance_check ) ) {
				$this->write_debug_log( 'send_sms()', 'ABORT: ' . $balance_check->get_error_message() );
				self::append_send_log( $phone, 'failed', $type );
				self::append_sms_history( $phone, $message, 'failed', $type, '' );
				return $balance_check;
			}
		}

		$payload = array(
			'sender'  => $sender_id,
			'mobile'  => $phone,
			'message' => $message,
		);

		// In test mode: log OTP to debug.log and return mock success — no real API call.
		if ( $this->test_mode ) {
			$log_line = '[kwtsms-otp TEST] SMS to ' . $phone . ': ' . $message;
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions
				error_log( $log_line );
			}
			// Also write to plugin dir so it's readable from mounted filesystem.
			if ( defined( 'KWTSMS_OTP_DIR' ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( KWTSMS_OTP_DIR . 'test-otp.log', date( 'Y-m-d H:i:s' ) . ' ' . $log_line . PHP_EOL, FILE_APPEND );
			}
			$test_msg_id = 'TEST-' . time();
			$result      = array(
				'msg_id'        => $test_msg_id,
				'balance_after' => 0.0,
			);
			$this->write_debug_log( 'send_sms()', "TEST mode — mock sent, msg_id={$test_msg_id}" );
			self::append_send_log( $phone, 'sent', $type );
			self::append_sms_history( $phone, $message, 'sent', $type, $test_msg_id );
			return $result;
		}

		$response = $this->request( 'send/', $payload );

		if ( is_wp_error( $response ) ) {
			$this->write_debug_log( 'send_sms()', 'FAILED: ' . $response->get_error_message() );
			self::append_send_log( $phone, 'failed', $type );
			self::append_sms_history( $phone, $message, 'failed', $type, '' );
			return $response;
		}

		$msg_id = sanitize_text_field( $response['msg-id'] ?? '' );
		$this->write_debug_log( 'send_sms()', "SUCCESS: msg-id={$msg_id}" );
		self::append_send_log( $phone, 'sent', $type );
		self::append_sms_history( $phone, $message, 'sent', $type, $msg_id );
		// Update saved balance so the UI reflects the latest balance after each live send.
		self::update_saved_balance( (float) ( $response['balance-after'] ?? 0 ) );
		return array(
			'msg_id'        => $msg_id,
			'balance_after' => (float) ( $response['balance-after'] ?? 0 ),
		);
	}

	/**
	 * Retrieve SMS coverage information for the account.
	 *
	 * Returns an array of coverage data (countries and their status).
	 *
	 * @return array|WP_Error Coverage data array on success.
	 */
	public function get_coverage() {
		$response = $this->request( 'coverage/', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Normalize: some API versions return under 'countries', some at root.
		return isset( $response['countries'] ) ? $response['countries'] : $response;
	}

	/**
	 * Check whether the account has sufficient balance before sending an SMS.
	 *
	 * Logic:
	 *   1. If no balance is saved yet (null), allow — the balance simply hasn't
	 *      been fetched. The send will fail at the API level if truly out of credits.
	 *   2. If saved available > 0, allow immediately without an extra API call.
	 *   3. If saved available <= 0, make one live API call to double-check.
	 *      a. If the API is unreachable (WP_Error), allow — better to attempt.
	 *      b. If live available > 0, update saved balance and allow.
	 *      c. If live available <= 0, return WP_Error with a user-friendly message.
	 *
	 * @return true|WP_Error True if sending is allowed; WP_Error if insufficient credits.
	 */
	public function check_balance_before_send() {
		$gw        = get_option( 'kwtsms_otp_gateway', array() );
		$available = $gw['balance_available'] ?? null;

		// Not loaded yet — allow the attempt.
		if ( null === $available ) {
			return true;
		}

		// Positive balance — allow immediately.
		if ( (float) $available > 0 ) {
			return true;
		}

		// Saved balance is 0 or negative — double-check via a live API call.
		$live = $this->get_balance();

		// API unreachable — allow the attempt (fail gracefully at API level).
		if ( is_wp_error( $live ) ) {
			return true;
		}

		// Persist the refreshed balance.
		self::update_saved_balance( $live['available'], $live['purchased'] );

		if ( $live['available'] <= 0 ) {
			return new WP_Error(
				'no_balance',
				__( 'Insufficient SMS credits. Please top up your kwtsms account.', 'wp-kwtsms-otp' )
			);
		}

		return true;
	}

	/**
	 * Persist the latest account balance to the gateway option.
	 *
	 * Called after a successful live send or explicit balance check.
	 *
	 * @param float $available  Available credits.
	 * @param float $purchased  Total purchased credits (0 if unknown).
	 */
	public static function update_saved_balance( $available, $purchased = 0.0 ) {
		$gw                      = get_option( 'kwtsms_otp_gateway', array() );
		$gw['balance_available']  = (float) $available;
		$gw['balance_purchased']  = $purchased > 0.0 ? (float) $purchased : ( $gw['balance_purchased'] ?? null );
		$gw['balance_updated_at'] = time();
		update_option( 'kwtsms_otp_gateway', $gw );
	}

	/**
	 * Validate phone numbers before sending.
	 *
	 * @param string $phone Normalised phone number.
	 *
	 * @return string 'OK'|'ER'|'NR' or WP_Error on HTTP failure.
	 */
	public function validate_number( $phone ) {
		$response = $this->request( 'validate/', array( 'mobile' => $phone ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// The API returns an object with ER/NR/OK arrays.
		if ( isset( $response['mobile']['OK'] ) && in_array( $phone, (array) $response['mobile']['OK'], true ) ) {
			return 'OK';
		}
		if ( isset( $response['mobile']['NR'] ) && in_array( $phone, (array) $response['mobile']['NR'], true ) ) {
			return 'NR';
		}
		return 'ER';
	}

	// =========================================================================
	// Send log (last 20 OTP sends for admin log viewer)
	// =========================================================================

	/**
	 * Append an entry to the OTP send log (stored in wp_options, max 20 entries).
	 *
	 * @param string $phone  Normalised phone number.
	 * @param string $status 'sent' or 'failed'.
	 * @param string $type   Context type: 'login'|'reset'|'passwordless'|'welcome'|'test'.
	 */
	public static function append_send_log( $phone, $status, $type = '' ) {
		$log = get_option( 'kwtsms_otp_send_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'   => time(),
				'phone'  => sanitize_text_field( $phone ), // full phone — admin-only view
				'status' => $status,
				'type'   => sanitize_key( $type ),
			)
		);

		// Keep only last 20 entries.
		if ( count( $log ) > 20 ) {
			$log = array_slice( $log, 0, 20 );
		}

		update_option( 'kwtsms_otp_send_log', $log, false );
	}

	/**
	 * Append a full (unredacted) SMS send entry to the SMS history log.
	 *
	 * Stored in wp_options as kwtsms_otp_sms_history (max 500 entries).
	 * The full phone number and message text are stored here — only visible
	 * to administrators via the Logs page.
	 *
	 * @param string $phone   Full normalised phone number (e.g. 96598765432).
	 * @param string $message The exact message that was sent.
	 * @param string $status  'sent' or 'failed'.
	 * @param string $type    Context: 'login'|'reset'|'passwordless'|'welcome'|'test'.
	 * @param string $msg_id  Message ID returned by API, or empty on failure.
	 */
	public static function append_sms_history( $phone, $message, $status, $type, $msg_id = '' ) {
		$log = get_option( 'kwtsms_otp_sms_history', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'phone'   => sanitize_text_field( $phone ),
				'message' => sanitize_textarea_field( $message ),
				'status'  => in_array( $status, array( 'sent', 'failed' ), true ) ? $status : 'failed',
				'type'    => sanitize_key( $type ),
				'msg_id'  => sanitize_text_field( $msg_id ),
			)
		);

		// Cap at 500 entries.
		if ( count( $log ) > 500 ) {
			$log = array_slice( $log, 0, 500 );
		}

		update_option( 'kwtsms_otp_sms_history', $log, false );
	}

	/**
	 * Append an OTP attempt event to the attempt log.
	 *
	 * Stored in wp_options as kwtsms_otp_attempt_log (max 500 entries).
	 * Phone number is stored unredacted (admin-only view).
	 *
	 * @param int|null $user_id  WordPress user ID, or null for unauthenticated attempts.
	 * @param string   $phone    Normalised phone number.
	 * @param string   $ip       Client IP address.
	 * @param string   $action   'login'|'passwordless'|'reset'.
	 * @param string   $result   'success'|'wrong_code'|'expired'|'locked'|'rate_limited'|'brute_force'.
	 */
	public static function append_attempt_log( $user_id, $phone, $ip, $action, $result ) {
		$log = get_option( 'kwtsms_otp_attempt_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'user_id' => is_null( $user_id ) ? null : absint( $user_id ),
				'phone'   => sanitize_text_field( (string) $phone ),
				'ip'      => sanitize_text_field( $ip ),
				'action'  => sanitize_key( $action ),
				'result'  => sanitize_key( $result ),
			)
		);

		// Cap at 500 entries.
		if ( count( $log ) > 500 ) {
			$log = array_slice( $log, 0, 500 );
		}

		update_option( 'kwtsms_otp_attempt_log', $log, false );
	}

	// =========================================================================
	// Phone normalisation (static — used throughout the plugin)
	// =========================================================================

	/**
	 * Normalise a phone number to international format (digits only, no prefix).
	 *
	 * Handles all common input variants:
	 *   +96598765432     → 96598765432
	 *   0096598765432    → 96598765432
	 *   965 9922 0322    → 96598765432
	 *   965-9922-0322    → 96598765432
	 *   ٩٦٥٩٩٢٢٠٣٢٢      → 96598765432  (Arabic/Hindi numerals)
	 *
	 * @param string $phone Raw phone input from user.
	 *
	 * @return string|WP_Error Normalised number, or WP_Error if invalid.
	 */
	public static function normalize_phone( $phone ) {
		// 1. Convert Arabic/Hindi-Indic numerals (٠١٢٣٤٥٦٧٨٩) to ASCII digits.
		$arabic_numerals = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$ascii_digits    = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$phone           = str_replace( $arabic_numerals, $ascii_digits, $phone );

		// Also convert Eastern Arabic-Indic numerals (used in Persian/Urdu contexts).
		$eastern_numerals = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$phone            = str_replace( $eastern_numerals, $ascii_digits, $phone );

		// 2. Strip leading + or 00.
		$phone = preg_replace( '/^\+/', '', $phone );
		$phone = preg_replace( '/^00/', '', $phone );

		// 3. Remove all non-digit characters (spaces, dashes, dots, parentheses).
		$phone = preg_replace( '/\D/', '', $phone );

		// 4. Validate: must be digits only, 8–15 characters.
		if ( ! preg_match( '/^\d{8,15}$/', $phone ) ) {
			return new WP_Error(
				'invalid_phone',
				/* translators: %s: the entered phone number */
				sprintf(
					__( 'Please enter a valid phone number with country code (e.g. 96598765432). Got: %s', 'wp-kwtsms-otp' ),
					esc_html( $phone )
				)
			);
		}

		return $phone;
	}

	// =========================================================================
	// Error code mapping
	// =========================================================================

	/**
	 * Map a kwtsms API error code to a user-friendly translated message.
	 *
	 * Error codes are defined in the kwtsms API Documentation v4.1, pages 11-12.
	 * Raw error codes are NEVER shown to end users — only the mapped messages.
	 *
	 * @param string $code API error code (e.g. 'ERR003').
	 *
	 * @return string Translated user-facing message.
	 */
	public static function map_error_code( $code ) {
		$messages = array(
			'ERR001' => __( 'SMS service is temporarily unavailable. Please try again later.', 'wp-kwtsms-otp' ),
			'ERR002' => __( 'Gateway configuration error. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR003' => __( 'Gateway authentication failed. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR004' => __( 'SMS gateway is not enabled on this account. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR005' => __( 'SMS gateway account is suspended. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR006' => __( 'Phone number is not valid for SMS delivery.', 'wp-kwtsms-otp' ),
			'ERR007' => __( 'Too many recipients. Please try again.', 'wp-kwtsms-otp' ),
			'ERR008' => __( 'The configured sender ID is not allowed. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR009' => __( 'SMS template is empty. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR010' => __( 'Insufficient SMS credits. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR011' => __( 'Insufficient SMS credits. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR012' => __( 'Message is too long. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR013' => __( 'SMS queue is full. Please try again in a few minutes.', 'wp-kwtsms-otp' ),
			'ERR019' => __( 'Delivery report not found.', 'wp-kwtsms-otp' ),
			'ERR020' => __( 'Message not found.', 'wp-kwtsms-otp' ),
			'ERR021' => __( 'Delivery report is not available for this message.', 'wp-kwtsms-otp' ),
			'ERR022' => __( 'Delivery reports are not ready yet. Please check back in 24 hours.', 'wp-kwtsms-otp' ),
			'ERR023' => __( 'Could not retrieve delivery report.', 'wp-kwtsms-otp' ),
			'ERR024' => __( 'Your request was blocked by a security policy. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR025' => __( 'Phone number is not valid. Please enter a valid mobile number with country code.', 'wp-kwtsms-otp' ),
			'ERR026' => __( 'No SMS coverage for this destination. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR027' => __( 'Message contains unsupported characters. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR028' => __( 'Please wait at least 15 seconds before requesting another code to the same number.', 'wp-kwtsms-otp' ),
			'ERR029' => __( 'Message not found or message ID is incorrect.', 'wp-kwtsms-otp' ),
			'ERR030' => __( 'Message is stuck in queue. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR031' => __( 'Message was rejected due to policy violations. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR032' => __( 'Message was rejected as spam. Please contact the site administrator.', 'wp-kwtsms-otp' ),
			'ERR033' => __( 'No SMS coverage configured. Please contact the site administrator.', 'wp-kwtsms-otp' ),
		);

		return isset( $messages[ $code ] )
			? $messages[ $code ]
			: /* translators: %s: internal error code */ sprintf( __( 'An unexpected error occurred (%s). Please try again.', 'wp-kwtsms-otp' ), esc_html( $code ) );
	}

	// =========================================================================
	// Private HTTP layer
	// =========================================================================

	/**
	 * Send a POST request to the kwtsms JSON API.
	 *
	 * Always uses HTTPS. Always sends credentials in the JSON body (never in URL).
	 * Content-Type and Accept headers are set to application/json.
	 *
	 * @param string $endpoint API endpoint path (e.g. 'send/').
	 * @param array  $payload  Additional payload fields (credentials are appended here).
	 *
	 * @return array|WP_Error Decoded JSON response array on success.
	 */
	private function request( $endpoint, array $payload ) {
		if ( empty( $this->username ) || empty( $this->password ) ) {
			$err = new WP_Error(
				'kwtsms_no_credentials',
				__( 'kwtSMS API credentials are not configured. Please go to Settings → kwtSMS → Gateway and enter your API username and password.', 'wp-kwtsms-otp' )
			);
			$this->write_debug_log( "request({$endpoint})", 'ABORT: credentials missing (username or password empty)' );
			return $err;
		}

		// Credentials go in the body, never in the URL.
		$body = array_merge(
			array(
				'username' => $this->username,
				'password' => $this->password,
			),
			$payload
		);

		$url = self::BASE_URL . ltrim( $endpoint, '/' );

		// Log request (mask password in log).
		$log_payload = $payload;
		$this->write_debug_log( "request({$endpoint})", 'POST ' . $url . ' payload=' . wp_json_encode( $log_payload ) );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				// Enforce SSL verification.
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$err = new WP_Error(
				'kwtsms_http_error',
				__( 'Could not connect to the SMS gateway. Please check your internet connection.', 'wp-kwtsms-otp' )
			);
			$this->write_debug_log( "request({$endpoint})", 'HTTP error: ' . $response->get_error_message() );
			return $err;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );

		$this->write_debug_log( "request({$endpoint})", "HTTP {$http_code} response: {$raw_body}" );

		$data = json_decode( $raw_body, true );

		if ( null === $data ) {
			$err = new WP_Error(
				'kwtsms_invalid_response',
				/* translators: %d: HTTP status code */ sprintf( __( 'Unexpected response from SMS gateway (HTTP %d). This may indicate a server-side issue at kwtsms.com.', 'wp-kwtsms-otp' ), (int) $http_code )
			);
			$this->write_debug_log( "request({$endpoint})", "JSON decode failed. Raw: {$raw_body}" );
			return $err;
		}

		// API returns {"result":"ERROR","code":"ERRxxx","description":"..."} on failure.
		if ( isset( $data['result'] ) && 'ERROR' === $data['result'] ) {
			$error_code = $data['code'] ?? 'UNKNOWN';
			$description = $data['description'] ?? '';
			$this->write_debug_log(
				"request({$endpoint})",
				"API ERROR: code={$error_code} description={$description} — this usually means wrong credentials, no Sender ID, or no credits."
			);
			return new WP_Error(
				'kwtsms_api_error_' . strtolower( $error_code ),
				self::map_error_code( $error_code ),
				array( 'api_code' => $error_code, 'description' => $description )
			);
		}

		$this->write_debug_log( "request({$endpoint})", 'SUCCESS result=' . wp_json_encode( $data ) );
		return $data;
	}

	// =========================================================================
	// Debug logging
	// =========================================================================

	/**
	 * Write a timestamped entry to the kwtsms debug log file.
	 *
	 * Only writes if debug_mode is enabled (admin setting: General → Debug Logging).
	 * Log file: wp-content/kwtsms-debug.log
	 *
	 * @param string $context Short label for the calling function.
	 * @param string $message Log message (credentials are never logged).
	 */
	/**
	 * Maximum debug log file size in bytes before rotation (1 MiB).
	 *
	 * @var int
	 */
	const DEBUG_LOG_MAX_BYTES = 1048576;

	private function write_debug_log( $context, $message ) {
		if ( ! $this->debug_mode ) {
			return;
		}

		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}

		$log_path = WP_CONTENT_DIR . '/kwtsms-debug.log';

		// Rotate when the file reaches the size limit.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
		if ( file_exists( $log_path ) && filesize( $log_path ) >= self::DEBUG_LOG_MAX_BYTES ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			rename( $log_path, $log_path . '.1' );
		}

		$line = '[' . date( 'Y-m-d H:i:s' ) . '] [kwtsms-otp] [' . $context . '] ' . $message . PHP_EOL; // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_path, $line, FILE_APPEND );
	}
}
