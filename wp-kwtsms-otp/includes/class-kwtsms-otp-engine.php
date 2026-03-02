<?php
/**
 * OTP Engine — generate, store, verify, rate-limit.
 *
 * Uses WordPress Transients API for ephemeral storage.
 * OTP codes are compared with hash_equals() to prevent timing attacks.
 * Codes are single-use: deleted immediately on successful verification.
 *
 * Transient key schema:
 *   kwtsms_otp_{md5(identifier)}         — the OTP record
 *   kwtsms_otp_rate_{md5(phone)}         — sliding-window timestamp array per phone
 *   kwtsms_otp_ip_{md5(ip)}             — sliding-window timestamp array per IP address
 *   kwtsms_otp_acct_{md5(user_id)}      — sliding-window timestamp array per WordPress user ID
 *   kwtsms_otp_lock_{md5(identifier)}    — lockout flag after max attempts
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_OTP_Engine
 */
class KwtSMS_OTP_Engine {

	/**
	 * Maximum OTP send attempts per phone per rate-limit window.
	 *
	 * @var int
	 */
	const RATE_LIMIT_MAX = 3;

	/**
	 * Rate-limit window in seconds (10 minutes).
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 600;

	/**
	 * Maximum OTP send attempts per IP address per rate-limit window.
	 *
	 * @var int
	 */
	const IP_RATE_LIMIT_MAX = 10;

	/**
	 * IP rate-limit window in seconds (10 minutes).
	 *
	 * @var int
	 */
	const IP_RATE_LIMIT_WINDOW = 600;

	/**
	 * Maximum OTP send attempts per WordPress user ID per rate-limit window.
	 *
	 * @var int
	 */
	const USER_RATE_LIMIT_MAX = 5;

	/**
	 * Per-account rate-limit window in seconds (10 minutes).
	 *
	 * @var int
	 */
	const USER_RATE_LIMIT_WINDOW = 600;

	/**
	 * Settings helper.
	 *
	 * @var KwtSMS_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param KwtSMS_Settings $settings Plugin settings instance.
	 */
	public function __construct( KwtSMS_Settings $settings ) {
		$this->settings = $settings;
	}

	// =========================================================================
	// OTP lifecycle
	// =========================================================================

	/**
	 * Generate a new OTP for a user and store it as a transient.
	 *
	 * Any existing OTP for this identifier is overwritten.
	 *
	 * @param int|string $identifier User ID (int) or phone number (string for passwordless).
	 * @param string     $action     Context: 'login' | 'reset' | 'passwordless'.
	 *
	 * @return string The generated OTP code.
	 */
	public function generate( $identifier, $action ) {
		$length = (int) $this->settings->get( 'general.otp_length', 6 );
		$code   = $this->generate_code( $length );

		$expiry = (int) $this->settings->get( 'general.otp_expiry', 5 ) * MINUTE_IN_SECONDS;
		$key    = $this->transient_key( $identifier );

		set_transient(
			$key,
			array(
				'code'     => $code,
				'attempts' => 0,
				'action'   => $action,
				'created'  => time(),
			),
			$expiry
		);

		return $code;
	}

	/**
	 * Verify a submitted OTP code.
	 *
	 * Uses timing-safe comparison (hash_equals) to prevent brute-force
	 * timing analysis. Logs each verification attempt to kwtsms_otp_attempt_log.
	 *
	 * @param int|string $identifier User ID or phone.
	 * @param string     $submitted  The code the user entered.
	 * @param string     $action     Context for logging: 'login'|'passwordless'|'reset'.
	 * @param int|null   $user_id    WordPress user ID for logging (null if unknown).
	 * @param string     $phone      Phone number for logging (masked).
	 *
	 * @return string One of: 'valid' | 'invalid' | 'expired' | 'max_attempts' | 'not_found'
	 */
	public function verify( $identifier, $submitted, $action = 'login', $user_id = null, $phone = '' ) {
		$key  = $this->transient_key( $identifier );
		$data = get_transient( $key );
		$ip   = $this->get_client_ip();

		if ( false === $data ) {
			KwtSMS_API::append_attempt_log( $user_id, $phone, $ip, $action, 'expired' );
			return 'expired';
		}

		$max_attempts = (int) $this->settings->get( 'general.max_attempts', 3 );

		// Check if already locked out.
		if ( $data['attempts'] >= $max_attempts ) {
			KwtSMS_API::append_attempt_log( $user_id, $phone, $ip, $action, 'locked' );
			return 'max_attempts';
		}

		// Double-check expiry manually (transient TTL may have slight drift).
		$expiry_seconds = (int) $this->settings->get( 'general.otp_expiry', 5 ) * MINUTE_IN_SECONDS;
		if ( ( time() - $data['created'] ) > $expiry_seconds ) {
			delete_transient( $key );
			KwtSMS_API::append_attempt_log( $user_id, $phone, $ip, $action, 'expired' );
			return 'expired';
		}

		// Timing-safe comparison.
		if ( ! hash_equals( (string) $data['code'], (string) $submitted ) ) {
			$data['attempts']++;
			if ( $data['attempts'] >= $max_attempts ) {
				// Delete OTP on lockout — user must request a new one.
				delete_transient( $key );
				KwtSMS_API::append_attempt_log( $user_id, $phone, $ip, $action, 'locked' );
				return 'max_attempts';
			}
			// Update attempt count.
			$remaining_ttl = $expiry_seconds - ( time() - $data['created'] );
			set_transient( $key, $data, max( 1, $remaining_ttl ) );
			KwtSMS_API::append_attempt_log( $user_id, $phone, $ip, $action, 'wrong_code' );
			return 'invalid';
		}

		// Valid — delete immediately to make it single-use.
		delete_transient( $key );
		KwtSMS_API::append_attempt_log( $user_id, $phone, $ip, $action, 'success' );
		return 'valid';
	}

	/**
	 * Get the number of remaining verification attempts.
	 *
	 * @param int|string $identifier User ID or phone.
	 *
	 * @return int Remaining attempts, or 0 if OTP not found.
	 */
	public function get_remaining_attempts( $identifier ) {
		$data = get_transient( $this->transient_key( $identifier ) );
		if ( false === $data ) {
			return 0;
		}
		$max = (int) $this->settings->get( 'general.max_attempts', 3 );
		return max( 0, $max - (int) $data['attempts'] );
	}

	// =========================================================================
	// Rate limiting
	// =========================================================================

	/**
	 * Check whether a phone number has exceeded the OTP send rate limit.
	 *
	 * Uses a sliding-window algorithm: the transient stores an array of Unix
	 * timestamps, one per request. On each call, timestamps older than the
	 * window are pruned and the current timestamp is recorded (if not at the
	 * limit), making it impossible to game the boundary.
	 *
	 * Combines the former is_rate_limited() + increment_rate() into one call.
	 * The timestamp is always recorded when the caller is not blocked, so
	 * callers must NOT call a separate increment method.
	 *
	 * @param string   $phone   Normalised phone number.
	 * @param string   $action  Context: 'login'|'passwordless'|'reset'.
	 * @param int|null $user_id User ID for logging.
	 *
	 * @return bool True if rate-limited (limit reached, timestamp NOT recorded).
	 *              False if not limited (timestamp recorded for this request).
	 */
	public function is_rate_limited( $phone, $action = 'login', $user_id = null ) {
		$limited = $this->is_rate_limited_sliding(
			$this->rate_key( $phone ),
			self::RATE_LIMIT_MAX,
			self::RATE_LIMIT_WINDOW
		);
		if ( $limited ) {
			KwtSMS_API::append_attempt_log( $user_id, $phone, $this->get_client_ip(), $action, 'rate_limited' );
		}
		return $limited;
	}

	/**
	 * Check whether the current IP has exceeded the OTP send rate limit.
	 *
	 * Uses a sliding-window algorithm (see is_rate_limited() for details).
	 * Fails open (returns false) if the IP cannot be determined, so that
	 * users behind proxies or in unusual network environments are not
	 * incorrectly blocked.
	 *
	 * Combines the former is_ip_rate_limited() + increment_ip_rate() into one
	 * call. Callers must NOT call a separate increment method.
	 *
	 * @param string   $action  Context: 'login'|'passwordless'|'reset'.
	 * @param int|null $user_id User ID for logging.
	 * @param string   $phone   Phone for logging.
	 *
	 * @return bool True if the IP is rate-limited.
	 */
	public function is_ip_rate_limited( $action = 'login', $user_id = null, $phone = '' ) {
		$ip = $this->get_client_ip();
		if ( '' === $ip ) {
			return false; // Cannot determine IP — fail open.
		}
		$limited = $this->is_rate_limited_sliding(
			'kwtsms_otp_ip_' . md5( $ip ),
			self::IP_RATE_LIMIT_MAX,
			self::IP_RATE_LIMIT_WINDOW
		);
		if ( $limited ) {
			KwtSMS_API::append_attempt_log( $user_id, $phone, $ip, $action, 'rate_limited' );
		}
		return $limited;
	}

	/**
	 * Check whether a user account has exceeded the OTP send rate limit.
	 *
	 * Uses a sliding-window algorithm (see is_rate_limited() for details).
	 * Only applies when $user_id is a positive integer. Guests (user_id = 0
	 * or null) are not subject to this limit; they are covered by the per-IP
	 * and per-phone limits instead.
	 *
	 * Combines the former is_user_rate_limited() + increment_user_rate() into
	 * one call. Callers must NOT call a separate increment method.
	 *
	 * @param int      $user_id WordPress user ID.
	 * @param string   $action  Context: 'login'|'passwordless'|'reset'.
	 * @param string   $phone   Phone for logging.
	 *
	 * @return bool True if the account is rate-limited.
	 */
	public function is_user_rate_limited( $user_id, $action = 'login', $phone = '' ) {
		if ( ! is_int( $user_id ) || $user_id <= 0 ) {
			return false;
		}
		$limited = $this->is_rate_limited_sliding(
			'kwtsms_otp_acct_' . md5( (string) $user_id ),
			self::USER_RATE_LIMIT_MAX,
			self::USER_RATE_LIMIT_WINDOW
		);
		if ( $limited ) {
			KwtSMS_API::append_attempt_log( $user_id, $phone, $this->get_client_ip(), $action, 'rate_limited' );
		}
		return $limited;
	}

	// =========================================================================
	// Message building
	// =========================================================================

	/**
	 * Build a translated SMS message from the template for a given event.
	 *
	 * Picks English or Arabic template based on current WordPress locale.
	 * Replaces {placeholders} with live values.
	 * Sanitises output: strips HTML tags, emoji, and unsupported characters.
	 *
	 * @param string $otp_code     The generated OTP code.
	 * @param string $template_id  Template key: 'login_otp' | 'reset_otp'.
	 *
	 * @return string Ready-to-send SMS message text.
	 */
	/**
	 * Build an SMS message from a template, replacing standard and extra placeholders.
	 *
	 * @param string $otp_code    OTP code to substitute for {otp} (pass '' for non-OTP templates).
	 * @param string $template_id Template key: 'login_otp' | 'reset_otp' | 'welcome_sms'.
	 * @param array  $extra_vars  Optional map of placeholder → value for template-specific vars,
	 *                            e.g. array( '{name}' => 'Ahmad' ) for the welcome SMS.
	 *
	 * @return string The fully rendered, sanitised SMS message.
	 */
	public function build_message( $otp_code, $template_id, array $extra_vars = array() ) {
		$templates = $this->settings->get_all_templates();
		$template  = $templates[ $template_id ] ?? $templates['login_otp'];

		// Choose Arabic template for Arabic locales; English for everything else.
		$locale  = get_locale();
		$lang    = ( strpos( $locale, 'ar' ) === 0 ) ? 'ar' : 'en';
		$message = $template[ $lang ] ?? $template['en'] ?? '';

		// Replace standard placeholders.
		$expiry    = (int) $this->settings->get( 'general.otp_expiry', 5 );
		$site_name = get_bloginfo( 'name' );

		$message = str_replace(
			array( '{otp}', '{site_name}', '{expiry_minutes}' ),
			array( $otp_code, $site_name, $expiry ),
			$message
		);

		// Replace template-specific extra placeholders (e.g. {name} for welcome SMS).
		if ( ! empty( $extra_vars ) ) {
			$message = str_replace( array_keys( $extra_vars ), array_values( $extra_vars ), $message );
		}

		// Sanitise: strip HTML, remove emoji and unsupported Unicode.
		$message = wp_strip_all_tags( $message );
		$message = $this->strip_emoji( $message );

		return $message;
	}

	// =========================================================================
	// Phone blocking
	// =========================================================================

	/**
	 * Handle a complete OTP request: generate code, build message, send SMS.
	 *
	 * Checks whether the phone is on the admin-configured block list first.
	 * Blocked phones receive a silent success (no SMS sent, no error exposed)
	 * to prevent enumeration of the block list by attackers.
	 *
	 * @param string $normalized_phone Normalised E.164-style phone number (digits only).
	 * @param int    $identifier       User ID or 0 for guest/phone-based identifiers.
	 * @param string $template_id      Template key: 'login_otp' | 'reset_otp'.
	 * @param string $action           Context: 'login' | 'reset' | 'passwordless'.
	 * @param string $sender_id        Sender ID to use for the SMS.
	 *
	 * @return true|array True if the phone is blocked (silent success), or an array with
	 *                     ['otp_code', 'message', 'phone', 'sender', 'action'] if not blocked.
	 */
	public function request_otp( $normalized_phone, $identifier, $template_id, $action, $sender_id ) {
		// Blocked phone: silently pretend success — no SMS sent, no error exposed.
		if ( $this->is_phone_blocked( $normalized_phone ) ) {
			return true; // pretend success, no SMS sent
		}

		$otp_code = $this->generate( $identifier, $action );
		$message  = $this->build_message( $otp_code, $template_id );

		return array(
			'otp_code' => $otp_code,
			'message'  => $message,
			'phone'    => $normalized_phone,
			'sender'   => $sender_id,
			'action'   => $action,
		);
	}

	/**
	 * Check whether a phone number is on the admin block list.
	 *
	 * Accepts a newline- or comma-separated list stored in general.blocked_phones.
	 * Each entry is normalised (digits only) before comparison so formatting
	 * differences (e.g. spaces, dashes) do not affect matching.
	 *
	 * Made public so call sites (login, passwordless, reset, resend) can check
	 * the block list before calling generate() + send_sms() directly.
	 *
	 * @param string $phone Normalised phone number (digits only).
	 *
	 * @return bool True if the phone is blocked.
	 */
	public function is_phone_blocked( string $phone ): bool {
		$list = $this->settings->get( 'general.blocked_phones', '' );
		if ( empty( $list ) ) {
			return false;
		}
		$blocked = preg_split( '/[\r\n,]+/', $list, -1, PREG_SPLIT_NO_EMPTY );
		$blocked = array_map(
			static function ( $p ) {
				return preg_replace( '/\D/', '', trim( $p ) );
			},
			$blocked
		);
		return in_array( $phone, array_filter( $blocked ), true );
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Sliding-window rate-limit primitive.
	 *
	 * The transient stores an array of Unix timestamps — one entry per
	 * recorded request. On every call:
	 *
	 *  1. Load the existing array (or start with an empty one).
	 *  2. Prune timestamps that fall outside the window (older than $window
	 *     seconds ago). Uses array_values() to re-index after filtering.
	 *  3. If the count of remaining timestamps is >= $max, the caller is
	 *     rate-limited: persist the pruned array with a TTL derived from the
	 *     oldest remaining timestamp (so the transient expires naturally when
	 *     all its entries fall outside the window) and return true WITHOUT
	 *     recording the current timestamp.
	 *  4. Otherwise, if $record is true, append the current Unix timestamp,
	 *     persist the updated array with a fresh TTL equal to $window, and
	 *     return false. If $record is false, skip recording (check-only mode).
	 *
	 * This eliminates the fixed-window boundary exploit: no matter when
	 * within the window a request arrives, only requests in the last
	 * $window seconds are counted.
	 *
	 * @param string $key    Transient key (already namespaced by the caller).
	 * @param int    $max    Maximum requests allowed within $window seconds.
	 * @param int    $window Sliding window size in seconds.
	 * @param bool   $record Whether to record the current timestamp when under
	 *                       the limit. Pass false to perform a check-only read
	 *                       (e.g. for pre-flight checks) without consuming a
	 *                       slot. Defaults to true (record on every allowed call).
	 *
	 * @return bool True if the caller is rate-limited (limit reached).
	 *              False if not limited.
	 */
	private function is_rate_limited_sliding( string $key, int $max, int $window, bool $record = true ): bool {
		$data = get_transient( $key );
		$data = is_array( $data ) ? $data : array();
		$now  = time();

		// Prune timestamps outside the current window.
		$data = array_values(
			array_filter(
				$data,
				function ( $ts ) use ( $now, $window ) {
					return $ts > $now - $window;
				}
			)
		);

		if ( count( $data ) >= $max ) {
			// At limit — persist the pruned array but do NOT record the current
			// timestamp; the caller is blocked.
			//
			// Use a TTL derived from the oldest timestamp so the transient
			// auto-expires when all its entries naturally fall outside the
			// window. This prevents an attacker from extending the transient
			// lifetime indefinitely by hammering the endpoint (which would
			// repeatedly reset a full-$window TTL).
			$oldest = min( $data );
			$ttl    = max( 1, $window - ( $now - $oldest ) );
			set_transient( $key, $data, $ttl );
			return true;
		}

		if ( $record ) {
			// Under limit — record the current request timestamp.
			// Note: timestamp is recorded here before the SMS send. If the send
			// fails, the slot is still consumed. This is intentional — it
			// prevents an attacker from making unlimited attempts by triggering
			// gateway errors.
			$data[] = $now;
			set_transient( $key, $data, $window );
		}

		return false;
	}

	/**
	 * Generate a cryptographically random numeric OTP code.
	 *
	 * Uses random_int() which is CSPRNG-backed.
	 *
	 * @param int $length Number of digits (4 or 6).
	 *
	 * @return string Zero-padded numeric code.
	 */
	private function generate_code( $length ) {
		$length = in_array( (int) $length, array( 4, 6 ), true ) ? (int) $length : 6;
		$max    = (int) str_repeat( '9', $length );
		$code   = random_int( 0, $max );
		return str_pad( (string) $code, $length, '0', STR_PAD_LEFT );
	}

	/**
	 * Get the transient key for an OTP record.
	 *
	 * MD5-hashes the identifier to keep key length within WP's 172-char limit.
	 *
	 * @param int|string $identifier User ID or phone.
	 *
	 * @return string Transient key.
	 */
	private function transient_key( $identifier ) {
		return 'kwtsms_otp_' . md5( (string) $identifier );
	}

	/**
	 * Get the transient key for the rate-limit counter of a phone number.
	 *
	 * @param string $phone Normalised phone number.
	 *
	 * @return string Transient key.
	 */
	private function rate_key( $phone ) {
		return 'kwtsms_otp_rate_' . md5( $phone );
	}

	/**
	 * Get the client IP address (best-effort, for logging only).
	 *
	 * @return string IP address, or empty string.
	 */
	/**
	 * Get the client IP address, respecting reverse-proxy headers.
	 *
	 * Uses X-Forwarded-For / X-Real-IP only when REMOTE_ADDR is a private or
	 * loopback address (indicating a trusted proxy is in front of the server).
	 * This prevents trivial IP spoofing by external clients on direct connections
	 * while still working correctly behind AWS ALB, Cloudflare, or Nginx proxies.
	 *
	 * @return string Validated IP address, or empty string.
	 */
	private function get_client_ip() {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';

		// Only trust proxy headers when REMOTE_ADDR is a known private/loopback range.
		$private_prefixes = array( '127.', '10.', '172.', '192.168.' );
		$is_private       = false;
		foreach ( $private_prefixes as $prefix ) {
			if ( 0 === strpos( $ip, $prefix ) ) {
				$is_private = true;
				break;
			}
		}

		if ( $is_private ) {
			foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					// X-Forwarded-For may be a comma-separated list; take the first (client) IP.
					$forwarded = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
					if ( filter_var( $forwarded, FILTER_VALIDATE_IP ) ) {
						$ip = $forwarded;
						break;
					}
				}
			}
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Strip emoji and non-SMS-compatible Unicode characters from a string.
	 *
	 * The kwtsms API rejects messages containing emoji — they cause the message
	 * to be stuck in the queue without delivery.
	 *
	 * @param string $text Input text.
	 *
	 * @return string Cleaned text.
	 */
	private function strip_emoji( $text ) {
		// Remove emoji and supplementary Unicode characters (U+1F000 and above).
		return preg_replace(
			'/[\x{1F000}-\x{1FFFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u',
			'',
			$text
		) ?? $text;
	}
}
