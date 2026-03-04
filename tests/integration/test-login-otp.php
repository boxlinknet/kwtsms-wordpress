<?php
/**
 * Integration tests — Login OTP / 2FA and Passwordless flows.
 *
 * Covers:
 *  1. Per-role enforcement: users whose role is not in otp_required_roles
 *     bypass OTP and get the original WP_User back from the authenticate filter.
 *  2. Passwordless user lookup by kwtsms_phone meta (WP_User_Query).
 *  3. Passwordless lookup returns nothing for unregistered phones.
 *  4. Phone normalisation (dot-format) before meta lookup.
 *  5. Phone normalisation (Arabic numerals) before meta lookup.
 *
 * Full 2FA session/cookie tests require setcookie() which does not work in
 * the phpunit context — those are covered by E2E browser tests.
 *
 * @package KwtSMS_OTP\Tests\Integration
 */

/**
 * Class Test_Integration_Login_OTP
 */
class Test_Integration_Login_OTP extends WP_UnitTestCase {

	/**
	 * Captured HTTP calls made during the test.
	 *
	 * @var array
	 */
	private array $api_calls = [];

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->api_calls = [];
		add_filter( 'pre_http_request', [ $this, 'intercept_http' ], 10, 3 );

		// Minimal gateway settings.
		update_option( 'kwtsms_otp_gateway', [
			'api_username'         => 'testuser',
			'api_password'         => 'testpass',
			'sender_id'            => 'KWTSMS',
			'test_mode'            => 1,
			'credentials_verified' => 1,
			'balance_available'    => 10.0,
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
	 * HTTP interceptor — returns a fake 200 OK response.
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
	// Tests
	// =========================================================================

	/**
	 * When the admin configures otp_required_roles=['subscriber'] and a user
	 * with role 'administrator' logs in, the authenticate filter should return
	 * the original WP_User (bypass OTP — admin role not in required list).
	 *
	 * This test verifies the role-bypass logic in KwtSMS_Login_OTP::intercept_login()
	 * by calling apply_filters('authenticate', ...) directly.
	 */
	public function test_2fa_skips_otp_for_excluded_role(): void {
		// Require OTP only for subscribers.
		update_option( 'kwtsms_otp_general', [
			'otp_mode'           => '2fa',
			'otp_required_roles' => [ 'subscriber' ],
			'login_otp'          => 1,
		] );

		// Create an administrator with a phone (so it would normally be intercepted).
		$admin_id = $this->factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'rolebypassadmin',
			'user_pass'  => 'testpass123',
		] );
		update_user_meta( $admin_id, 'kwtsms_phone', '96599220322' );

		$wp_user = new WP_User( $admin_id );

		// Apply the authenticate filter as WordPress would during wp_signon().
		// KwtSMS_Login_OTP::intercept_login() is hooked at priority 30.
		$result = apply_filters( 'authenticate', $wp_user, 'rolebypassadmin', 'testpass123' );

		$this->assertInstanceOf(
			WP_User::class,
			$result,
			'Administrator not in otp_required_roles should receive the original WP_User, not a WP_Error.'
		);
		$this->assertSame(
			$admin_id,
			$result->ID,
			'Returned WP_User should be the same administrator.'
		);
	}

	/**
	 * WP_User_Query can find a user by kwtsms_phone meta value.
	 *
	 * This is the core lookup used by the passwordless flow. We verify it works
	 * correctly in the real WordPress DB before testing the full flow.
	 */
	public function test_passwordless_finds_user_by_phone_meta(): void {
		$phone   = '96599220322';
		$user_id = $this->factory()->user->create( [
			'user_login' => 'phonelookupuser',
			'user_email' => 'phonelookup@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $phone );

		$users = get_users( [
			'meta_key'   => 'kwtsms_phone',
			'meta_value' => $phone,
			'number'     => 1,
			'fields'     => 'ids',
		] );

		$this->assertNotEmpty( $users, 'Expected to find the user by kwtsms_phone meta.' );
		$this->assertSame( $user_id, (int) $users[0], 'Returned user ID should match the created user.' );
	}

	/**
	 * WP_User_Query returns empty for a phone number not stored in any user's meta.
	 */
	public function test_passwordless_no_match_for_unregistered_phone(): void {
		$unregistered_phone = '96500000000';

		$users = get_users( [
			'meta_key'   => 'kwtsms_phone',
			'meta_value' => $unregistered_phone,
			'number'     => 1,
			'fields'     => 'ids',
		] );

		$this->assertEmpty(
			$users,
			'Expected empty result for a phone not stored in any user meta.'
		);
	}

	/**
	 * normalize_phone('+965.99220322') normalises to '96599220322', which then
	 * matches a user whose kwtsms_phone is stored as '96599220322'.
	 */
	public function test_passwordless_normalizes_dot_format_before_lookup(): void {
		$stored_phone = '96599220322';
		$user_id      = $this->factory()->user->create( [
			'user_login' => 'dotnormalizeuser',
			'user_email' => 'dotnorm@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $stored_phone );

		// Simulate dot-format input as a user might type.
		$raw_input  = '+965.99220322';
		$normalized = KwtSMS_API::normalize_phone( $raw_input );

		$this->assertNotWPError( $normalized, 'normalize_phone() should succeed for dot-format input.' );
		$this->assertSame( $stored_phone, $normalized, 'Dot-format phone should normalize to ' . $stored_phone );

		// Lookup by the normalised value should find the user.
		$users = get_users( [
			'meta_key'   => 'kwtsms_phone',
			'meta_value' => $normalized,
			'number'     => 1,
			'fields'     => 'ids',
		] );

		$this->assertNotEmpty( $users, 'Expected user found after normalising dot-format phone.' );
		$this->assertSame( $user_id, (int) $users[0] );
	}

	/**
	 * normalize_phone() converts Arabic/Hindi numerals before lookup.
	 *
	 * Input: '٩٦٥٩٩٢٢٠٣٢٢' (Arabic-Indic digits for 96599220322)
	 * Expected output: '96599220322'
	 */
	public function test_passwordless_normalizes_arabic_numerals_before_lookup(): void {
		$stored_phone = '96599220322';
		$user_id      = $this->factory()->user->create( [
			'user_login' => 'arabicnumuser',
			'user_email' => 'arabicnum@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $stored_phone );

		// Arabic-Indic numerals for 96599220322.
		$arabic_input = '٩٦٥٩٩٢٢٠٣٢٢';
		$normalized   = KwtSMS_API::normalize_phone( $arabic_input );

		$this->assertNotWPError( $normalized, 'normalize_phone() should handle Arabic numerals.' );
		$this->assertSame(
			$stored_phone,
			$normalized,
			'Arabic numerals should normalise to ASCII equivalent.'
		);

		// Lookup should succeed.
		$users = get_users( [
			'meta_key'   => 'kwtsms_phone',
			'meta_value' => $normalized,
			'number'     => 1,
			'fields'     => 'ids',
		] );

		$this->assertNotEmpty( $users, 'Expected user found after normalising Arabic numeral phone.' );
		$this->assertSame( $user_id, (int) $users[0] );
	}

	/**
	 * Full 2FA intercept with cookie + redirect is skipped in unit context.
	 *
	 * The authenticate filter flow requires setcookie() and wp_safe_redirect()
	 * with exit(), neither of which is safe in a PHPUnit process. This is
	 * covered by E2E browser tests in tests/browser/.
	 */
	public function test_2fa_creates_partial_session_for_required_role(): void {
		$this->markTestSkipped(
			'Full 2FA intercept requires cookie setting and redirect/exit — ' .
			'covered by E2E browser tests in tests/browser/.'
		);
	}
}
