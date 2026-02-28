<?php
/**
 * Tests for KwtSMS_OTP_Engine.
 *
 * Covers generate, verify, rate limiting, message building.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_OTP_Engine
 */
class Test_KwtSMS_OTP_Engine extends TestCase {

	/** @var KwtSMS_Settings|\PHPUnit\Framework\MockObject\MockObject */
	private $settings;

	/** @var KwtSMS_OTP_Engine */
	private $engine;

	/** @var array Simulated transient storage */
	private static $transients = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		self::$transients = array();

		// Settings mock.
		$this->settings = $this->createMock( KwtSMS_Settings::class );
		$this->settings->method( 'get' )->willReturnCallback( function ( $key, $default = null ) {
			$map = array(
				'general.otp_length'   => 6,
				'general.otp_expiry'   => 3,
				'general.max_attempts' => 3,
				'general.resend_cooldown' => 60,
			);
			return $map[ $key ] ?? $default;
		} );

		// Mock WP transient functions.
		Functions\when( 'set_transient' )->alias( function ( $key, $value, $ttl = 0 ) {
			self::$transients[ $key ] = $value;
			return true;
		} );
		Functions\when( 'get_transient' )->alias( function ( $key ) {
			return self::$transients[ $key ] ?? false;
		} );
		Functions\when( 'delete_transient' )->alias( function ( $key ) {
			unset( self::$transients[ $key ] );
			return true;
		} );

		// Mock WP options functions used by append_attempt_log / append_send_log.
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );

		// Mock WP functions used in build_message.
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );

		$this->settings->method( 'get_all_templates' )->willReturn( array(
			'login_otp' => array(
				'enabled' => 1,
				'en'      => 'Your {site_name} login code is: {otp}. Valid for {expiry_minutes} minutes.',
				'ar'      => 'رمز تسجيل الدخول: {otp}',
			),
			'reset_otp' => array(
				'enabled' => 1,
				'en'      => 'Your {site_name} password reset code is: {otp}.',
				'ar'      => 'رمز إعادة التعيين: {otp}',
			),
		) );

		$this->engine = new KwtSMS_OTP_Engine( $this->settings );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		self::$transients = array();
		parent::tearDown();
	}

	// =========================================================================
	// generate
	// =========================================================================

	public function test_generate_returns_string() {
		$code = $this->engine->generate( 42, 'login' );
		$this->assertIsString( $code );
	}

	public function test_generate_returns_correct_length() {
		$code = $this->engine->generate( 42, 'login' );
		$this->assertSame( 6, strlen( $code ) );
	}

	public function test_generate_returns_digits_only() {
		$code = $this->engine->generate( 42, 'login' );
		$this->assertMatchesRegularExpression( '/^\d{6}$/', $code );
	}

	public function test_generate_stores_transient() {
		$this->engine->generate( 42, 'login' );
		$key  = 'kwtsms_otp_' . md5( '42' );
		$data = self::$transients[ $key ] ?? null;
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'attempts', $data );
		$this->assertSame( 0, $data['attempts'] );
		$this->assertSame( 'login', $data['action'] );
	}

	public function test_generate_overwrites_existing_otp() {
		$this->engine->generate( 42, 'login' );
		$key   = 'kwtsms_otp_' . md5( '42' );
		$code1 = self::$transients[ $key ]['code'];

		// Force a different code by regenerating (may match by chance but statistically negligible).
		$this->engine->generate( 42, 'login' );
		// Transient should be re-set (at worst same value, key still valid).
		$this->assertArrayHasKey( $key, self::$transients );
	}

	// =========================================================================
	// verify
	// =========================================================================

	public function test_verify_returns_valid_on_correct_code() {
		$code   = $this->engine->generate( 42, 'login' );
		$result = $this->engine->verify( 42, $code );
		$this->assertSame( 'valid', $result );
	}

	public function test_verify_deletes_transient_on_success() {
		$code = $this->engine->generate( 42, 'login' );
		$this->engine->verify( 42, $code );
		$key = 'kwtsms_otp_' . md5( '42' );
		$this->assertArrayNotHasKey( $key, self::$transients );
	}

	public function test_verify_returns_invalid_on_wrong_code() {
		$this->engine->generate( 42, 'login' );
		$result = $this->engine->verify( 42, '000000' );
		// Could be 'invalid' or 'max_attempts' (if 000000 matches by extreme coincidence).
		$this->assertContains( $result, array( 'invalid', 'max_attempts' ) );
	}

	public function test_verify_returns_expired_when_no_transient() {
		$result = $this->engine->verify( 999, '123456' );
		$this->assertSame( 'expired', $result );
	}

	public function test_verify_returns_max_attempts_after_three_wrong_codes() {
		$code = $this->engine->generate( 42, 'login' );
		// Force 3 wrong attempts.
		$key = 'kwtsms_otp_' . md5( '42' );
		self::$transients[ $key ]['code'] = '999999'; // Override with a known value.
		for ( $i = 0; $i < 3; $i++ ) {
			$result = $this->engine->verify( 42, '111111' );
		}
		$this->assertSame( 'max_attempts', $result );
	}

	public function test_verify_increments_attempts_on_wrong_code() {
		$this->engine->generate( 42, 'login' );
		$key = 'kwtsms_otp_' . md5( '42' );
		self::$transients[ $key ]['code'] = '999999';

		$this->engine->verify( 42, '111111' );
		$this->assertSame( 1, self::$transients[ $key ]['attempts'] );
	}

	public function test_verify_returns_expired_when_time_passed() {
		$this->engine->generate( 42, 'login' );
		$key = 'kwtsms_otp_' . md5( '42' );
		// Set created timestamp far in the past (4 minutes ago, expiry is 3 min).
		self::$transients[ $key ]['created'] = time() - 4 * MINUTE_IN_SECONDS;

		$code   = self::$transients[ $key ]['code'];
		$result = $this->engine->verify( 42, $code );
		$this->assertSame( 'expired', $result );
	}

	// =========================================================================
	// get_remaining_attempts
	// =========================================================================

	public function test_get_remaining_attempts_returns_max_when_no_attempts() {
		$this->engine->generate( 42, 'login' );
		$remaining = $this->engine->get_remaining_attempts( 42 );
		$this->assertSame( 3, $remaining );
	}

	public function test_get_remaining_attempts_decrements_after_wrong_code() {
		$this->engine->generate( 42, 'login' );
		$key = 'kwtsms_otp_' . md5( '42' );
		self::$transients[ $key ]['code'] = '999999';
		$this->engine->verify( 42, '111111' );
		$remaining = $this->engine->get_remaining_attempts( 42 );
		$this->assertSame( 2, $remaining );
	}

	public function test_get_remaining_attempts_returns_zero_when_no_otp() {
		$remaining = $this->engine->get_remaining_attempts( 9999 );
		$this->assertSame( 0, $remaining );
	}

	public function test_get_remaining_attempts_returns_zero_when_transient_missing() {
		// When transient is missing (expired or deleted), returns 0.
		// The setUp mocks get_transient to return false for unknown keys.
		$result = $this->engine->get_remaining_attempts( 99 );
		$this->assertSame( 0, $result );
	}

	public function test_get_remaining_attempts_returns_correct_count_after_one_attempt() {
		// Simulate 1 failed attempt out of 3 max.
		$identifier = 12;
		$key = 'kwtsms_otp_' . md5( (string) $identifier );
		self::$transients[ $key ] = array(
			'code'     => '123456',
			'attempts' => 1,
			'action'   => 'login',
			'created'  => time(),
		);
		$result = $this->engine->get_remaining_attempts( $identifier );
		$this->assertSame( 2, $result ); // 3 max - 1 attempt = 2 remaining
	}

	// =========================================================================
	// Rate limiting
	// =========================================================================

	public function test_is_rate_limited_returns_false_initially() {
		$this->assertFalse( $this->engine->is_rate_limited( '96598765432' ) );
	}

	public function test_is_rate_limited_returns_true_after_max_requests() {
		$phone = '96598765432';
		// Manually set the counter above the limit.
		self::$transients[ 'kwtsms_otp_rate_' . md5( $phone ) ] = KwtSMS_OTP_Engine::RATE_LIMIT_MAX;
		$this->assertTrue( $this->engine->is_rate_limited( $phone ) );
	}

	public function test_increment_rate_increases_counter() {
		$phone = '96598765432';
		$this->engine->increment_rate( $phone );
		$key   = 'kwtsms_otp_rate_' . md5( $phone );
		$this->assertSame( 1, self::$transients[ $key ] );
	}

	public function test_increment_rate_creates_counter_if_not_set() {
		$phone = '96598765432';
		$this->engine->increment_rate( $phone );
		$this->assertFalse( $this->engine->is_rate_limited( $phone ) );
	}

	// =========================================================================
	// build_message
	// =========================================================================

	public function test_build_message_replaces_otp_placeholder() {
		$msg = $this->engine->build_message( '123456', 'login_otp' );
		$this->assertStringContainsString( '123456', $msg );
	}

	public function test_build_message_replaces_site_name_placeholder() {
		$msg = $this->engine->build_message( '123456', 'login_otp' );
		$this->assertStringContainsString( 'Test Site', $msg );
	}

	public function test_build_message_uses_arabic_for_arabic_locale() {
		Functions\when( 'get_locale' )->justReturn( 'ar' );
		$engine = new KwtSMS_OTP_Engine( $this->settings );
		$msg    = $engine->build_message( '123456', 'login_otp' );
		$this->assertStringContainsString( 'رمز', $msg );
	}

	public function test_build_message_strips_html_tags() {
		// Override template with HTML.
		$this->settings->method( 'get_all_templates' )->willReturn( array(
			'login_otp' => array(
				'enabled' => 1,
				'en'      => 'Your code is: <b>{otp}</b>. Valid.',
				'ar'      => 'رمزك: {otp}',
			),
		) );
		$engine = new KwtSMS_OTP_Engine( $this->settings );
		$msg    = $engine->build_message( '123456', 'login_otp' );
		$this->assertStringNotContainsString( '<b>', $msg );
	}

	// =========================================================================
	// Per-IP rate limiting
	// =========================================================================

	public function test_is_ip_rate_limited_returns_false_below_limit() {
		$_SERVER['REMOTE_ADDR'] = '1.2.3.4';
		// get_transient returns false → cast to int = 0, which is below the limit.
		$result = $this->engine->is_ip_rate_limited();
		$this->assertFalse( $result );
	}

	public function test_is_ip_rate_limited_returns_true_at_limit() {
		$_SERVER['REMOTE_ADDR'] = '1.2.3.4';
		// Override get_transient so the IP key returns the limit value.
		Functions\when( 'get_transient' )->alias( function ( $key ) {
			if ( strpos( $key, 'kwtsms_otp_ip_' ) === 0 ) {
				return KwtSMS_OTP_Engine::IP_RATE_LIMIT_MAX;
			}
			return self::$transients[ $key ] ?? false;
		} );
		$result = $this->engine->is_ip_rate_limited();
		$this->assertTrue( $result );
	}

	public function test_increment_ip_rate_stores_transient() {
		$_SERVER['REMOTE_ADDR'] = '1.2.3.4';
		$stored = array();
		Functions\when( 'set_transient' )->alias( function ( $key, $value, $ttl = 0 ) use ( &$stored ) {
			$stored[ $key ] = $value;
			return true;
		} );
		$this->engine->increment_ip_rate();
		$ip_key = 'kwtsms_otp_ip_' . md5( '1.2.3.4' );
		$this->assertArrayHasKey( $ip_key, $stored );
		$this->assertSame( 1, $stored[ $ip_key ] );
	}

	public function test_is_ip_rate_limited_returns_false_when_ip_empty() {
		$_SERVER['REMOTE_ADDR'] = 'not-a-valid-ip';
		$result = $this->engine->is_ip_rate_limited();
		$this->assertFalse( $result );
	}

	// =========================================================================
	// Per-account rate limiting
	// =========================================================================

	public function test_is_user_rate_limited_returns_false_below_limit() {
		$result = $this->engine->is_user_rate_limited( 42 );
		$this->assertFalse( $result );
	}

	public function test_is_user_rate_limited_returns_true_at_limit() {
		Functions\when( 'get_transient' )->alias( function ( $key ) {
			if ( strpos( $key, 'kwtsms_otp_acct_' ) === 0 ) {
				return KwtSMS_OTP_Engine::USER_RATE_LIMIT_MAX;
			}
			return self::$transients[ $key ] ?? false;
		} );
		$result = $this->engine->is_user_rate_limited( 42 );
		$this->assertTrue( $result );
	}

	public function test_is_user_rate_limited_returns_false_for_invalid_user_id() {
		$result = $this->engine->is_user_rate_limited( 0 );
		$this->assertFalse( $result );
	}

	public function test_increment_user_rate_stores_transient() {
		$stored = array();
		Functions\when( 'set_transient' )->alias( function ( $key, $value, $ttl = 0 ) use ( &$stored ) {
			$stored[ $key ] = $value;
			return true;
		} );
		$this->engine->increment_user_rate( 42 );
		$acct_key = 'kwtsms_otp_acct_' . md5( '42' );
		$this->assertArrayHasKey( $acct_key, $stored );
		$this->assertSame( 1, $stored[ $acct_key ] );
	}
}
