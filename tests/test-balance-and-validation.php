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
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
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
	// 2b. check_balance_before_send() — saved balance positive  allow
	// =========================================================================

	/**
	 * When saved balance is positive, no API call should be made and true
	 * must be returned immediately.
	 *
	 * @covers KwtSMS_API::check_balance_before_send
	 */
	public function test_check_balance_before_send_allows_when_api_returns_positive() {
		// Saved balance: 5.0  allow immediately without an API call.
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
	// 2b. check_balance_before_send() — null saved balance  allow
	// =========================================================================

	/**
	 * When no balance has ever been fetched (key absent  null via ?? null),
	 * the check must allow the send attempt rather than blocking on fresh installs.
	 *
	 * @covers KwtSMS_API::check_balance_before_send
	 */
	public function test_check_balance_before_send_allows_when_balance_null() {
		// No balance stored yet — gateway option has no balance_available key.
		Functions\when( 'get_option' )
			->justReturn( array() ); // balance_available absent  null via ?? null

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		$result = $api->check_balance_before_send();

		$this->assertTrue(
			$result,
			'check_balance_before_send must return true when balance_available is null (not yet loaded).'
		);
	}

	// =========================================================================
	// Country allow-list enforcement in send_sms()
	// =========================================================================

	/**
	 * When a phone's country code (966 = Saudi Arabia) is NOT in the allowed
	 * list (only KW permitted), send_sms() must return WP_Error with code
	 * 'country_not_allowed' before making any API call.
	 *
	 * @covers KwtSMS_API::send_sms
	 * @covers KwtSMS_API::get_iso2_from_phone
	 */
	public function test_send_blocked_when_country_not_in_allowed_list() {
		// General settings: only Kuwait (KW) is allowed.
		Functions\when( 'get_option' )->alias( function ( $key, $default = null ) {
			if ( 'kwtsms_otp_general' === $key ) {
				return array( 'allowed_countries' => array( 'KW' ) );
			}
			// send_log and sms_history both read and write via get/update_option.
			return is_array( $default ) ? $default : array();
		} );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		// 96698765432  prefix 966  Saudi Arabia (SA) — not in allowed list.
		$result = $api->send_sms( '96698765432', 'KWTSMS', 'Hello', 'login' );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'send_sms must return WP_Error when destination country is not in the allowed list.'
		);
		$this->assertSame(
			'country_not_allowed',
			$result->get_error_code(),
			'WP_Error code must be country_not_allowed.'
		);
	}

	/**
	 * When a phone's country code (965 = Kuwait) IS in the allowed list,
	 * send_sms() must NOT return a country_not_allowed error.
	 *
	 * @covers KwtSMS_API::send_sms
	 * @covers KwtSMS_API::get_iso2_from_phone
	 */
	public function test_send_allowed_when_country_in_allowed_list() {
		// General settings: Kuwait (KW) and Saudi Arabia (SA) are allowed.
		Functions\when( 'get_option' )->alias( function ( $key, $default = null ) {
			if ( 'kwtsms_otp_general' === $key ) {
				return array( 'allowed_countries' => array( 'KW', 'SA' ) );
			}
			if ( 'kwtsms_otp_gateway' === $key ) {
				// Positive balance so the live-mode balance check passes immediately.
				return array( 'balance_available' => 10.0 );
			}
			return is_array( $default ) ? $default : array();
		} );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		// Stub wp_remote_post to return a successful API response.
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"result":"SUCCESS","msg-id":"test123","balance-after":9}',
			)
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'{"result":"SUCCESS","msg-id":"test123","balance-after":9}'
		);

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		// 96598765432  prefix 965  Kuwait (KW) — in allowed list.
		$result = $api->send_sms( '96598765432', 'KWTSMS', 'Hello', 'login' );

		// The result must not be a country_not_allowed error.
		if ( $result instanceof WP_Error ) {
			$this->assertNotSame(
				'country_not_allowed',
				$result->get_error_code(),
				'send_sms must NOT return country_not_allowed when the country is in the allowed list.'
			);
		} else {
			// Successful send returns an array with msg_id.
			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'msg_id', $result );
		}
	}

	/**
	 * When allowed_countries is an empty array (no restriction configured),
	 * send_sms() must not block any country.
	 *
	 * @covers KwtSMS_API::send_sms
	 */
	public function test_send_allowed_when_allowed_list_empty() {
		// General settings: empty allowed_countries  no country restriction.
		Functions\when( 'get_option' )->alias( function ( $key, $default = null ) {
			if ( 'kwtsms_otp_general' === $key ) {
				return array( 'allowed_countries' => array() );
			}
			if ( 'kwtsms_otp_gateway' === $key ) {
				return array( 'balance_available' => 10.0 );
			}
			return is_array( $default ) ? $default : array();
		} );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		// A US number: 1 = US — would be blocked if country restriction were active.
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"result":"SUCCESS","msg-id":"us123","balance-after":9}',
			)
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'{"result":"SUCCESS","msg-id":"us123","balance-after":9}'
		);

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		// 12025550100  prefix 1  USA — should NOT be blocked when list is empty.
		$result = $api->send_sms( '12025550100', 'KWTSMS', 'Hello', 'login' );

		// Must not be a country_not_allowed error.
		if ( $result instanceof WP_Error ) {
			$this->assertNotSame(
				'country_not_allowed',
				$result->get_error_code(),
				'send_sms must NOT return country_not_allowed when allowed_countries list is empty.'
			);
		} else {
			$this->assertIsArray( $result );
		}
	}

	// =========================================================================
	// 2b. check_balance_before_send() — zero saved balance but API unreachable  allow
	// =========================================================================

	/**
	 * When saved balance is 0 but the live API call returns a WP_Error (e.g.
	 * the kwtSMS service is temporarily unreachable), the method must fail-open
	 * and return true so the SMS attempt is not silently dropped.
	 *
	 * This exercises the `if ( is_wp_error($live) ) return true;` branch in
	 * check_balance_before_send() at line ~295 of class-kwtsms-api.php.
	 *
	 * @covers KwtSMS_API::check_balance_before_send
	 */
	public function test_check_balance_before_send_allows_when_api_unreachable() {
		// Saved balance shows zero — triggers the live API double-check.
		Functions\when( 'get_option' )
			->justReturn( array( 'balance_available' => 0.0 ) );

		// is_wp_error must work correctly so the WP_Error branch is reached.
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );

		// Simulate an unreachable API: wp_remote_post returns a WP_Error.
		Functions\when( 'wp_remote_post' )->justReturn(
			new WP_Error( 'http_request_failed', 'Could not connect to kwtSMS API.' )
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 0 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$api    = new KwtSMS_API( 'testuser', 'testpass', false );
		$result = $api->check_balance_before_send();

		$this->assertTrue(
			$result,
			'check_balance_before_send must return true (fail-open) when the API is unreachable.'
		);
	}
}
