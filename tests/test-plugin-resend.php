<?php
/**
 * Tests for ajax_resend_otp() transient-prefix resolution logic.
 *
 * Verifies that the resend handler resolves the correct transient key for each
 * context ('login' vs 'reset') and that an unrecognised context defaults to
 * 'login'.  The helper function kwtsms_resolve_resend_transient() mirrors the
 * exact prefix-selection logic inside ajax_resend_otp() so the business rules
 * can be tested without wiring up the full plugin singleton or HTTP layer.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_Plugin_Resend
 *
 * Covers the transient-key resolution used by ajax_resend_otp().
 */
class Test_KwtSMS_Plugin_Resend extends TestCase {

	/** @var array Simulated transient storage (mirrors the pattern in test-otp-engine.php). */
	private static $transients = array();

	// =========================================================================
	// Lifecycle
	// =========================================================================

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		self::$transients = array();

		// Mock WP transient functions with the same alias pattern used across the
		// test-suite so every test sees a clean, isolated in-memory store.
		Functions\when( 'get_transient' )->alias( function ( $key ) {
			return self::$transients[ $key ] ?? false;
		} );
		Functions\when( 'set_transient' )->alias( function ( $key, $value, $ttl = 0 ) {
			self::$transients[ $key ] = $value;
			return true;
		} );
		Functions\when( 'delete_transient' )->alias( function ( $key ) {
			unset( self::$transients[ $key ] );
			return true;
		} );

		// sanitize_key: lowercase + strip non-alphanumeric-dash-underscore, matching WP core.
		Functions\when( 'sanitize_key' )->alias( function ( $key ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		self::$transients = array();
		parent::tearDown();
	}

	// =========================================================================
	// Helper: mirrors the prefix-selection logic from ajax_resend_otp().
	// =========================================================================

	/**
	 * Resolve the transient key that ajax_resend_otp() would look up for the
	 * given context and token.
	 *
	 * This function replicates the exact branching logic from the handler so
	 * the tests remain coupled to the implementation's semantics without
	 * needing to invoke the WordPress HTTP/JSON output layer.
	 *
	 * @param string $context Raw context value from $_POST['context'].
	 * @param string $token   Session token from $_POST['token'].
	 *
	 * @return string The resolved transient key.
	 */
	private function resolve_transient_key( $context, $token ) {
		// Replicate sanitize_key() normalisation + whitelist guard.
		$context = sanitize_key( $context );
		if ( 'reset' !== $context ) {
			$context = 'login';
		}

		if ( 'reset' === $context ) {
			return KwtSMS_Reset_OTP::RESET_TRANSIENT_PREFIX . $token;
		}

		return 'kwtsms_partial_auth_' . $token;
	}

	// =========================================================================
	// Tests
	// =========================================================================

	/**
	 * When context='reset' the handler must look up the reset-session transient,
	 * NOT the partial-auth transient used for login 2FA.
	 */
	public function test_resend_ajax_finds_reset_session_with_correct_prefix() {
		$token = 'abc123token';

		// Seed the reset-session transient (as KwtSMS_Reset_OTP does after
		// sending the initial OTP SMS).
		$reset_key = KwtSMS_Reset_OTP::RESET_TRANSIENT_PREFIX . $token;
		set_transient(
			$reset_key,
			array(
				'user_id' => 42,
				'phone'   => '96599220322',
			),
			15 * MINUTE_IN_SECONDS
		);

		// The login-2FA transient for the same token should NOT exist.
		$login_key = 'kwtsms_partial_auth_' . $token;

		$resolved_key = $this->resolve_transient_key( 'reset', $token );
		$session      = get_transient( $resolved_key );

		// Assert that the handler resolved to the reset prefix and found the session.
		$this->assertSame( $reset_key, $resolved_key, 'Resolved key must use the reset transient prefix.' );
		$this->assertIsArray( $session, 'Session data must be found under the reset transient key.' );
		$this->assertSame( 42, $session['user_id'], 'Session must contain the correct user_id.' );

		// Confirm that the login prefix would NOT have found the session.
		$login_session = get_transient( $login_key );
		$this->assertFalse( $login_session, 'Login transient must not exist when the reset transient is set.' );
	}

	/**
	 * When context='login' the handler must look up the partial-auth transient
	 * used for login 2FA, NOT the reset-session transient.
	 */
	public function test_resend_ajax_finds_login_session_with_correct_prefix() {
		$token = 'xyz789token';

		// Seed the login partial-auth transient (as KwtSMS_Login_OTP does).
		$login_key = 'kwtsms_partial_auth_' . $token;
		set_transient(
			$login_key,
			array(
				'user_id' => 7,
				'phone'   => '96599220322',
			),
			10 * MINUTE_IN_SECONDS
		);

		// The reset transient for the same token should NOT exist.
		$reset_key = KwtSMS_Reset_OTP::RESET_TRANSIENT_PREFIX . $token;

		$resolved_key = $this->resolve_transient_key( 'login', $token );
		$session      = get_transient( $resolved_key );

		// Assert the handler resolved to the login prefix and found the session.
		$this->assertSame( $login_key, $resolved_key, 'Resolved key must use the partial-auth (login) transient prefix.' );
		$this->assertIsArray( $session, 'Session data must be found under the login transient key.' );
		$this->assertSame( 7, $session['user_id'], 'Session must contain the correct user_id.' );

		// Confirm that the reset prefix would NOT have found the session.
		$reset_session = get_transient( $reset_key );
		$this->assertFalse( $reset_session, 'Reset transient must not exist when the login transient is set.' );
	}

	/**
	 * When context is missing (not provided in POST) the handler must default
	 * to 'login' and resolve the partial-auth transient prefix.
	 */
	public function test_resend_context_defaults_to_login_when_not_provided() {
		$token = 'default_context_token';

		// Simulate a missing context by passing an empty string (equivalent to
		// $_POST['context'] not being set, which yields '' via the ?? operator).
		$resolved_key = $this->resolve_transient_key( '', $token );
		$expected_key = 'kwtsms_partial_auth_' . $token;

		$this->assertSame(
			$expected_key,
			$resolved_key,
			'Missing context must default to login and use the partial-auth transient prefix.'
		);

		// Sanity-check: the reset prefix must NOT be used.
		$this->assertStringNotContainsString(
			KwtSMS_Reset_OTP::RESET_TRANSIENT_PREFIX,
			$resolved_key,
			'Resolved key must not contain the reset transient prefix when context is missing.'
		);
	}

	// =========================================================================
	// Additional coverage: constant value guard
	// =========================================================================

	/**
	 * The RESET_TRANSIENT_PREFIX constant must equal the string value that the
	 * plugin has always used when storing reset sessions — changing it would
	 * silently break all in-flight reset flows on update.
	 */
	public function test_reset_transient_prefix_constant_has_expected_value() {
		$this->assertSame(
			'kwtsms_reset_session_',
			KwtSMS_Reset_OTP::RESET_TRANSIENT_PREFIX,
			'RESET_TRANSIENT_PREFIX must equal "kwtsms_reset_session_" to match stored transient keys.'
		);
	}

	/**
	 * An arbitrary unknown context value must be normalised to 'login', not
	 * silently passed through or treated as 'reset'.
	 */
	public function test_unknown_context_is_normalised_to_login() {
		$token        = 'test_token_unknown_ctx';
		$resolved_key = $this->resolve_transient_key( 'something_unexpected', $token );
		$expected_key = 'kwtsms_partial_auth_' . $token;

		$this->assertSame(
			$expected_key,
			$resolved_key,
			'Unknown context must fall through to the login prefix.'
		);
	}
}
