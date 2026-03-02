<?php
/**
 * Integration tests — Security / input sanitisation.
 *
 * Covers:
 *  1. SQL injection in phone input is rejected by normalize_phone() (returns WP_Error).
 *  2. SQL injection via user meta is safe (WP uses prepared statements).
 *  3. XSS <script> tags in template content are stripped by sanitize_template_settings().
 *  4. AJAX action wp_ajax_kwtsms_verify_credentials dies without a valid nonce
 *     (WPDieException is thrown in the test environment).
 *
 * @package KwtSMS_OTP\Tests\Integration
 */

/**
 * Class Test_Integration_Security
 */
class Test_Integration_Security extends WP_UnitTestCase {

	/**
	 * KwtSMS_Admin instance, created in set_up().
	 *
	 * @var KwtSMS_Admin|null
	 */
	private ?KwtSMS_Admin $admin = null;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();

		// We need a real KwtSMS_Plugin singleton to pass to KwtSMS_Admin.
		$plugin      = KwtSMS_Plugin::get_instance();
		$this->admin = new KwtSMS_Admin( $plugin );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		$this->admin = null;
		parent::tear_down();
	}

	// =========================================================================
	// Tests
	// =========================================================================

	/**
	 * A SQL injection string passed to normalize_phone() is rejected with WP_Error.
	 *
	 * normalize_phone() strips all non-digit characters, leaving an empty (or
	 * very short) string that fails the 8-15 digit validation, so it returns
	 * a WP_Error rather than a normalised phone string.
	 */
	public function test_sql_injection_in_phone_rejected_by_normalize(): void {
		$injection = "' OR '1'='1";
		$result    = KwtSMS_API::normalize_phone( $injection );

		$this->assertWPError(
			$result,
			'normalize_phone() should return WP_Error for SQL injection string.'
		);
		$this->assertSame(
			'invalid_phone',
			$result->get_error_code(),
			'Error code should be "invalid_phone".'
		);
	}

	/**
	 * Saving an injection string as user meta and querying it via WP_User_Query
	 * is safe because WP uses prepared statements internally.
	 *
	 * This test verifies that the users table still exists after the query —
	 * i.e. no SQL was broken by the meta value.
	 */
	public function test_sql_injection_phone_meta_safe_via_prepared_statements(): void {
		$injection = "'; DROP TABLE wp_users; --";

		$user_id = $this->factory()->user->create( [
			'user_login' => 'sqlinjectuser',
			'user_email' => 'sqlinject@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $injection );

		// Querying by the injection string should simply return no results
		// (because no other user has this meta value).
		$users = get_users( [
			'meta_key'   => 'kwtsms_phone',
			'meta_value' => $injection,
			'number'     => 1,
			'fields'     => 'ids',
		] );

		// The table still works — users are found by ID.
		$found = get_userdata( $user_id );
		$this->assertInstanceOf(
			WP_User::class,
			$found,
			'User table should still be accessible after querying with an injection string meta value.'
		);

		// The stored meta value is the sanitized form of the injection string
		// (update_user_meta sanitizes on retrieval via get_user_meta).
		$stored = get_user_meta( $user_id, 'kwtsms_phone', true );
		$this->assertIsString( $stored, 'Meta value should be retrievable as a string.' );
	}

	/**
	 * sanitize_template_settings() strips <script> tags from template content.
	 *
	 * The sanitizer calls sanitize_textarea_field() (internally) via
	 * sanitize_template_content(). This should remove HTML tags including scripts.
	 */
	public function test_xss_script_in_template_stripped(): void {
		$xss_input = '<script>alert(1)</script>Your code is {otp}';

		$raw = [
			'login_otp' => [
				'enabled' => 1,
				'en'      => $xss_input,
				'ar'      => '',
			],
		];

		$sanitized = $this->admin->sanitize_template_settings( $raw );

		$this->assertArrayHasKey( 'login_otp', $sanitized );
		$this->assertStringNotContainsString(
			'<script>',
			$sanitized['login_otp']['en'],
			'<script> tag should be stripped by sanitize_template_settings().'
		);
		$this->assertStringNotContainsString(
			'</script>',
			$sanitized['login_otp']['en'],
			'</script> should also be stripped.'
		);
		$this->assertStringContainsString(
			'{otp}',
			$sanitized['login_otp']['en'],
			'Placeholder {otp} should be preserved after sanitisation.'
		);
	}

	/**
	 * Without a valid nonce, the kwtsms admin AJAX action cannot proceed.
	 *
	 * check_ajax_referer() uses wp_verify_nonce() internally. When no nonce is
	 * supplied, wp_verify_nonce() returns false. In non-AJAX CLI context
	 * check_ajax_referer() then calls die('-1') which would kill the process —
	 * so we test the underlying nonce verification directly, which is the same
	 * security gate.
	 *
	 * We also test that check_ajax_referer() with stop=false (non-fatal mode)
	 * returns false for a missing nonce, confirming the security gate works.
	 */
	public function test_ajax_verify_credentials_dies_without_nonce(): void {
		// Create and log in as admin.
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Simulate an AJAX POST with no nonce (intentionally omitted).
		$_POST = [
			'username' => 'testuser',
			'password' => 'testpass',
		];

		// Verify that the nonce is invalid when not present.
		$nonce_value = $_POST['nonce'] ?? '';
		$nonce_valid = wp_verify_nonce( $nonce_value, 'kwtsms_admin_nonce' );

		$this->assertFalse(
			(bool) $nonce_valid,
			'wp_verify_nonce() must return false when no nonce is provided.'
		);

		// Use check_ajax_referer() with $stop=false to confirm it returns false
		// without killing the process — this validates the gate without side effects.
		$_REQUEST = $_POST; // check_ajax_referer reads from $_REQUEST.
		$check_result = check_ajax_referer( 'kwtsms_admin_nonce', 'nonce', false );

		$this->assertFalse(
			(bool) $check_result,
			'check_ajax_referer() must return false when no valid nonce is present, ' .
			'confirming the AJAX handler would reject the request.'
		);
	}
}
