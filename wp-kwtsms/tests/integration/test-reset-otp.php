<?php
/**
 * Integration tests — Password Reset OTP flow.
 *
 * Covers user lookup (by login, email, phone), transient creation/retrieval,
 * OTP verification (valid case), and expiry enforcement.
 *
 * All tests use the real WordPress DB via WP_UnitTestCase.
 *
 * @package KwtSMS_OTP\Tests\Integration
 */

/**
 * Class Test_Integration_Reset_OTP
 */
class Test_Integration_Reset_OTP extends WP_UnitTestCase {

	/**
	 * Captured HTTP calls.
	 *
	 * @var array
	 */
	private array $api_calls = [];

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->api_calls = [];
		add_filter( 'pre_http_request', [ $this, 'intercept_http' ], 10, 3 );

		// Minimal gateway settings so send_sms() does not bail on missing creds.
		update_option( 'kwtsms_otp_gateway', [
			'api_username'         => 'testuser',
			'api_password'         => 'testpass',
			'sender_id'            => 'KWTSMS',
			'test_mode'            => 1,
			'credentials_verified' => 1,
			'balance_available'    => 10.0,
		] );

		// Enable reset OTP.
		update_option( 'kwtsms_otp_general', [
			'reset_otp'            => 1,
			'otp_length'           => 6,
			'otp_expiry'           => 5,
			'max_attempts'         => 3,
			'allowed_countries'    => [],
			'default_country_code' => 'KW',
		] );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'intercept_http' ] );
		delete_option( 'kwtsms_otp_gateway' );
		delete_option( 'kwtsms_otp_general' );
		parent::tear_down();
	}

	/**
	 * HTTP interceptor.
	 *
	 * @param false|array|WP_Error $preempt Preempt value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 *
	 * @return array
	 */
	public function intercept_http( $preempt, $args, $url ): array {
		$this->api_calls[] = [ 'url' => $url, 'args' => $args ];
		return [
			'response' => [ 'code' => 200 ],
			'body'     => '{"result":"OK","msg-id":"test123"}',
			'headers'  => [],
			'cookies'  => [],
			'filename' => null,
		];
	}

	// =========================================================================
	// User lookup tests
	// =========================================================================

	/**
	 * get_user_by('login', ...) returns the correct WP_User.
	 */
	public function test_reset_lookup_by_username_returns_correct_user(): void {
		$user_id = $this->factory()->user->create( [
			'user_login' => 'resetlookupuser',
			'user_email' => 'resetlookup@example.com',
		] );

		$found = get_user_by( 'login', 'resetlookupuser' );

		$this->assertInstanceOf( WP_User::class, $found );
		$this->assertSame( $user_id, $found->ID );
	}

	/**
	 * get_user_by('email', ...) returns the correct WP_User.
	 */
	public function test_reset_lookup_by_email_returns_correct_user(): void {
		$user_id = $this->factory()->user->create( [
			'user_login' => 'resetemlookup',
			'user_email' => 'resetemlookup@example.com',
		] );

		$found = get_user_by( 'email', 'resetemlookup@example.com' );

		$this->assertInstanceOf( WP_User::class, $found );
		$this->assertSame( $user_id, $found->ID );
	}

	/**
	 * WP_User_Query with meta_key=kwtsms_phone finds the correct user.
	 */
	public function test_reset_lookup_by_phone_returns_correct_user(): void {
		$phone   = '96598765432';
		$user_id = $this->factory()->user->create( [
			'user_login' => 'resetphonelookup',
			'user_email' => 'resetphone@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $phone );

		$users = get_users( [
			'meta_key'   => 'kwtsms_phone',
			'meta_value' => $phone,
			'number'     => 1,
			'fields'     => 'ids',
		] );

		$this->assertNotEmpty( $users, 'Expected to find user by phone meta.' );
		$this->assertSame( $user_id, (int) $users[0] );
	}

	/**
	 * A user with no kwtsms_phone meta yields empty WP_User_Query results.
	 * No crash or exception should occur.
	 */
	public function test_reset_user_without_phone_handled_gracefully(): void {
		$this->factory()->user->create( [
			'user_login' => 'nophoneresetuser',
			'user_email' => 'nophonereset@example.com',
		] );
		// Intentionally no kwtsms_phone meta.

		$users = get_users( [
			'meta_key'   => 'kwtsms_phone',
			'meta_value' => '96500000001', // Phone not stored for any user.
			'number'     => 1,
			'fields'     => 'ids',
		] );

		$this->assertEmpty( $users, 'Expected empty result for user with no phone meta.' );
	}

	// =========================================================================
	// Transient creation and retrieval
	// =========================================================================

	/**
	 * set_transient() for a reset session can be read back with get_transient().
	 */
	public function test_reset_otp_transient_created_and_readable(): void {
		$token = wp_generate_password( 40, false );
		$data  = [
			'user_id' => 42,
			'phone'   => '96598765432',
			'action'  => 'reset',
		];

		$key = KwtSMS_Reset_OTP::RESET_TRANSIENT_PREFIX . $token;
		set_transient( $key, $data, 600 );

		$retrieved = get_transient( $key );

		$this->assertIsArray( $retrieved, 'Transient should be readable as an array.' );
		$this->assertSame( 42, $retrieved['user_id'] );
		$this->assertSame( '96598765432', $retrieved['phone'] );
		$this->assertSame( 'reset', $retrieved['action'] );
	}

	// =========================================================================
	// OTP verify() tests
	// =========================================================================

	/**
	 * KwtSMS_OTP_Engine::verify() returns 'valid' when the correct code is submitted.
	 *
	 * Uses the real KwtSMS_Plugin singleton and OTP engine wired to the live DB.
	 */
	public function test_reset_otp_verification_succeeds_with_correct_code(): void {
		$plugin = KwtSMS_Plugin::get_instance();

		$user_id    = $this->factory()->user->create( [
			'user_login' => 'otpverifyuser',
			'user_email' => 'otpverify@example.com',
		] );
		$identifier = $user_id;

		// Generate an OTP and store it via the engine.
		$otp_code = $plugin->otp->generate( $identifier, 'reset' );

		$this->assertNotEmpty( $otp_code, 'generate() should return a non-empty OTP code.' );

		// Verify the correct code.
		$result = $plugin->otp->verify( $identifier, $otp_code, 'reset', $user_id, '' );

		$this->assertSame(
			'valid',
			$result,
			'verify() should return "valid" when the correct OTP is submitted.'
		);
	}

	/**
	 * verify() returns 'expired' when the OTP transient has already been used
	 * (deleted on first successful verify — single-use).
	 *
	 * Note: The KwtSMS_OTP_Engine uses the transient TTL for expiry, not a
	 * separate timestamp. To test actual expiry without sleeping for 5 minutes,
	 * we manually delete the transient and call verify() — it returns 'expired'
	 * because no transient is found (same code path as natural TTL expiry).
	 */
	public function test_reset_otp_expiry_enforced(): void {
		$plugin = KwtSMS_Plugin::get_instance();

		$user_id = $this->factory()->user->create( [
			'user_login' => 'otpexpireuser',
			'user_email' => 'otpexpire@example.com',
		] );

		// Generate an OTP.
		$plugin->otp->generate( $user_id, 'reset' );

		// Manually delete the transient to simulate expiry (TTL elapsed).
		$transient_key = 'kwtsms_otp_' . md5( (string) $user_id );
		delete_transient( $transient_key );

		// verify() should now return 'expired' because the transient is gone.
		$result = $plugin->otp->verify( $user_id, '123456', 'reset', $user_id, '' );

		$this->assertSame(
			'expired',
			$result,
			'verify() should return "expired" when the OTP transient has been deleted (TTL elapsed).'
		);
	}
}
