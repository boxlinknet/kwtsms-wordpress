<?php
/**
 * Settings storage and retrieval helper.
 *
 * All plugin settings are stored in three wp_options entries:
 *   - kwtsms_otp_general   — OTP behaviour, CAPTCHA provider
 *   - kwtsms_otp_gateway   — API credentials, sender ID, test mode
 *   - kwtsms_otp_templates — SMS message templates (EN + AR)
 *
 * Access values with dot-notation: $settings->get('gateway.sender_id')
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Settings
 */
class KwtSMS_Settings {

	/**
	 * Default values for all settings groups.
	 *
	 * @var array
	 */
	const DEFAULTS = array(
		'general'   => array(
			'otp_mode'             => '2fa',     // '2fa' | 'passwordless' | 'both'
			'otp_length'           => 6,          // 4 or 6
			'otp_expiry'           => 5,          // minutes
			'max_attempts'         => 3,
			'resend_cooldown'      => 120,        // seconds
			'login_otp'            => 1,
			'reset_otp'            => 1,
			'captcha_provider'     => 'none',     // 'none' | 'recaptcha' | 'turnstile'
			'recaptcha_site_key'   => '',
			'recaptcha_secret_key' => '',
			'turnstile_site_key'   => '',
			'turnstile_secret_key' => '',
			'referral_link'        => 1,          // show "SMS service by kwtSMS.com" on login pages
			'default_country_code' => 'KW',       // ISO2 default for phone dropdown
			'allowed_countries'    => array( 'KW', 'SA', 'AE', 'BH', 'QA', 'OM' ), // GCC default
		),
		'gateway'   => array(
			'api_username' => '',
			'api_password' => '',
			'sender_id'    => '',
			'test_mode'    => 1,
			'test_phone'   => '',
		),
		'templates' => array(
			'login_otp' => array(
				'enabled' => 1,
				'en'      => 'Your {site_name} login code is: {otp}. Valid for {expiry_minutes} minutes. Do not share this code.',
				'ar'      => 'رمز تسجيل الدخول إلى {site_name} هو: {otp}. صالح لمدة {expiry_minutes} دقائق. لا تشارك هذا الرمز.',
			),
			'reset_otp' => array(
				'enabled' => 1,
				'en'      => 'Your {site_name} password reset code is: {otp}. Valid for {expiry_minutes} minutes.',
				'ar'      => 'رمز إعادة تعيين كلمة المرور الخاصة بـ {site_name} هو: {otp}. صالح لمدة {expiry_minutes} دقائق.',
			),
			'welcome_sms' => array(
				'enabled' => 0,
				'en'      => 'Welcome to {site_name}, {name}! Your account has been created.',
				'ar'      => 'مرحباً بك في {site_name}، {name}! تم إنشاء حسابك بنجاح.',
			),
		),
	);

	/**
	 * In-memory cache of loaded option groups.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Retrieve a setting value using dot-notation key.
	 *
	 * Examples:
	 *   get('gateway.api_username')
	 *   get('general.otp_length', 6)
	 *   get('templates.login_otp.en')
	 *
	 * @param string $key     Dot-separated key: group.field or group.subkey.field.
	 * @param mixed  $default Fallback if key is not found.
	 *
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$parts = explode( '.', $key, 3 );
		$group = $parts[0] ?? '';
		$field = $parts[1] ?? null;
		$sub   = $parts[2] ?? null;

		$data = $this->load_group( $group );

		if ( null === $field ) {
			return $data;
		}

		if ( null === $sub ) {
			$value = $data[ $field ] ?? null;
		} else {
			$value = $data[ $field ][ $sub ] ?? null;
		}

		if ( null === $value ) {
			// Fall back to constant defaults.
			$defaults = self::DEFAULTS[ $group ] ?? array();
			if ( null === $sub ) {
				$value = $defaults[ $field ] ?? $default;
			} else {
				$value = $defaults[ $field ][ $sub ] ?? $default;
			}
		}

		return null !== $value ? $value : $default;
	}

	/**
	 * Update a single setting value.
	 *
	 * @param string $key   Dot-separated key.
	 * @param mixed  $value New value.
	 */
	public function set( $key, $value ) {
		$parts = explode( '.', $key, 3 );
		$group = $parts[0] ?? '';
		$field = $parts[1] ?? null;
		$sub   = $parts[2] ?? null;

		$data = $this->load_group( $group );

		if ( null === $field ) {
			return;
		}

		if ( null === $sub ) {
			$data[ $field ] = $value;
		} else {
			if ( ! isset( $data[ $field ] ) || ! is_array( $data[ $field ] ) ) {
				$data[ $field ] = array();
			}
			$data[ $field ][ $sub ] = $value;
		}

		update_option( 'kwtsms_otp_' . $group, $data );
		$this->cache[ $group ] = $data;
	}

	/**
	 * Get all templates with defaults merged in.
	 *
	 * @return array
	 */
	public function get_all_templates() {
		$saved    = $this->load_group( 'templates' );
		$defaults = self::DEFAULTS['templates'];

		$merged = array();
		foreach ( $defaults as $key => $default_template ) {
			$merged[ $key ] = array_merge( $default_template, $saved[ $key ] ?? array() );
		}

		return $merged;
	}

	/**
	 * Load and cache an option group from the database.
	 *
	 * @param string $group Option group name (general|gateway|templates).
	 *
	 * @return array
	 */
	private function load_group( $group ) {
		if ( ! isset( $this->cache[ $group ] ) ) {
			$data = get_option( 'kwtsms_otp_' . $group, array() );
			$this->cache[ $group ] = is_array( $data ) ? $data : array();
		}
		return $this->cache[ $group ];
	}
}
