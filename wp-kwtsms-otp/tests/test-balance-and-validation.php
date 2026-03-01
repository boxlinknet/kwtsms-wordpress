<?php
/**
 * Tests for Task 2: Balance Persistence + Test Number Validation (v2.2.0).
 *
 * Covers:
 *   - normalize_phone WP_Error for short/local-only numbers (no country code)
 *   - check_balance_before_send() logic: zero, positive, null
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_Balance_And_Validation
 */
class Test_Balance_And_Validation extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Shared stubs for all tests in this class.
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_textarea_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// 2a. Test phone validation — normalize_phone rejects local-only numbers
	// =========================================================================

	/**
	 * An 7-digit number (no country code — too short) must be rejected by
	 * normalize_phone with an 'invalid_phone' WP_Error.
	 *
	 * The AJAX handler ajax_send_test_sms() calls normalize_phone() before
	 * sending, so this test validates that the underlying guard works correctly.
	 *
	 * @covers KwtSMS_API::normalize_phone
	 */
	public function test_send_test_sms_rejects_phone_without_country_code() {
		// 7 digits — too short; missing country code.
		$result = KwtSMS_API::normalize_phone( '9922032' );
		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'normalize_phone must return WP_Error for a 7-digit (no country code) number.'
		);
		$this->assertSame( 'invalid_phone', $result->get_error_code() );
		$this->assertStringContainsString(
			'country code',
			strtolower( $result->get_error_message() ),
			'Error message must mention country code.'
		);
	}

	// =========================================================================
	// 2b. check_balance_before_send() — zero saved balance, API confirms zero
	// =========================================================================

	/**
	 * When saved balance is 0 and the live API also returns 0, the method must
	 * return a WP_Error with code 'no_balance'.
	 *
	 * @covers KwtSMS_API::check_balance_before_send
	 */
	public function test_check_balance_before_send_blocks_when_zero_and_api_confirms() {
		// get_option is called twice:
		//   1. check_balance_before_send() reads gateway balance
		//   2. update_saved_balance() reads gateway option to merge balance
		// Use when() (not expect()->once()) so both calls are satisfied.
		Functions\when( 'get_option' )
			->justReturn( array( 'balance_available' => 0.0 ) );

		// Live API confirms 0 available credits.
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"result":"SUCCESS","available":0,"purchased":100}',
			)
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'{"result":"SUCCESS","available":0,"purchased":100}'
		);
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'update_option' )->justReturn( true );

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		$result = $api->check_balance_before_send();

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'check_balance_before_send must return WP_Error when balance is confirmed zero.'
		);
		$this->assertSame( 'no_balance', $result->get_error_code() );
	}

	// =========================================================================
	// 2b. check_balance_before_send() — saved balance positive → allow
	// =========================================================================

	/**
	 * When saved balance is positive, no API call should be made and true
	 * must be returned immediately.
	 *
	 * @covers KwtSMS_API::check_balance_before_send
	 */
	public function test_check_balance_before_send_allows_when_api_returns_positive() {
		// Saved balance: 5.0 → allow immediately without an API call.
		Functions\when( 'get_option' )
			->justReturn( array( 'balance_available' => 5.0 ) );

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		$result = $api->check_balance_before_send();

		$this->assertTrue(
			$result,
			'check_balance_before_send must return true when saved balance > 0.'
		);
	}

	// =========================================================================
	// 2b. check_balance_before_send() — null saved balance → allow
	// =========================================================================

	/**
	 * When no balance has ever been fetched (key absent → null via ?? null),
	 * the check must allow the send attempt rather than blocking on fresh installs.
	 *
	 * @covers KwtSMS_API::check_balance_before_send
	 */
	public function test_check_balance_before_send_allows_when_balance_null() {
		// No balance stored yet — gateway option has no balance_available key.
		Functions\when( 'get_option' )
			->justReturn( array() ); // balance_available absent → null via ?? null

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		$result = $api->check_balance_before_send();

		$this->assertTrue(
			$result,
			'check_balance_before_send must return true when balance_available is null (not yet loaded).'
		);
	}
}
