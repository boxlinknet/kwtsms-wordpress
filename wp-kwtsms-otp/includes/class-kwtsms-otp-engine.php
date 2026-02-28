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
 *   kwtsms_otp_rate_{md5(phone)}         — rate-limit counter per phone
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

		$expiry = (int) $this->settings->get( 'general.otp_expiry', 3 ) * MINUTE_IN_SECONDS;
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
	 * timing analysis.
	 *
	 * @param int|string $identifier User ID or phone.
	 * @param string     $submitted  The code the user entered.
	 *
	 * @return string One of: 'valid' | 'invalid' | 'expired' | 'max_attempts' | 'not_found'
	 */
	public function verify( $identifier, $submitted ) {
		$key  = $this->transient_key( $identifier );
		$data = get_transient( $key );

		if ( false === $data ) {
			return 'expired';
		}

		$max_attempts = (int) $this->settings->get( 'general.max_attempts', 3 );

		// Check if already locked out.
		if ( $data['attempts'] >= $max_attempts ) {
			return 'max_attempts';
		}

		// Double-check expiry manually (transient TTL may have slight drift).
		$expiry_seconds = (int) $this->settings->get( 'general.otp_expiry', 3 ) * MINUTE_IN_SECONDS;
		if ( ( time() - $data['created'] ) > $expiry_seconds ) {
			delete_transient( $key );
			return 'expired';
		}

		// Timing-safe comparison.
		if ( ! hash_equals( (string) $data['code'], (string) $submitted ) ) {
			$data['attempts']++;
			if ( $data['attempts'] >= $max_attempts ) {
				// Delete OTP on lockout — user must request a new one.
				delete_transient( $key );
				return 'max_attempts';
			}
			// Update attempt count.
			$remaining_ttl = $expiry_seconds - ( time() - $data['created'] );
			set_transient( $key, $data, max( 1, $remaining_ttl ) );
			return 'invalid';
		}

		// Valid — delete immediately to make it single-use.
		delete_transient( $key );
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
	 * Maximum 3 OTP requests per phone per 10 minutes.
	 *
	 * @param string $phone Normalised phone number.
	 *
	 * @return bool True if rate-limited.
	 */
	public function is_rate_limited( $phone ) {
		$count = (int) get_transient( $this->rate_key( $phone ) );
		return $count >= self::RATE_LIMIT_MAX;
	}

	/**
	 * Increment the rate-limit counter for a phone number.
	 *
	 * @param string $phone Normalised phone number.
	 */
	public function increment_rate( $phone ) {
		$key   = $this->rate_key( $phone );
		$count = (int) get_transient( $key );

		if ( 0 === $count ) {
			set_transient( $key, 1, self::RATE_LIMIT_WINDOW );
		} else {
			// Extend the window on each increment.
			set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
		}
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
	public function build_message( $otp_code, $template_id ) {
		$templates = $this->settings->get_all_templates();
		$template  = $templates[ $template_id ] ?? $templates['login_otp'];

		// Choose Arabic template for Arabic locales; English for everything else.
		$locale  = get_locale();
		$lang    = ( strpos( $locale, 'ar' ) === 0 ) ? 'ar' : 'en';
		$message = $template[ $lang ] ?? $template['en'] ?? '';

		// Replace placeholders.
		$expiry    = (int) $this->settings->get( 'general.otp_expiry', 3 );
		$site_name = get_bloginfo( 'name' );

		$message = str_replace(
			array( '{otp}', '{site_name}', '{expiry_minutes}' ),
			array( $otp_code, $site_name, $expiry ),
			$message
		);

		// Sanitise: strip HTML, remove emoji and unsupported Unicode.
		$message = wp_strip_all_tags( $message );
		$message = $this->strip_emoji( $message );

		return $message;
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

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
