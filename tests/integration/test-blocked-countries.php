<?php
/**
 * Integration tests — Country allow-list enforcement in KwtSMS_API::send_sms().
 *
 * The allowed_countries setting is read directly from wp_options inside
 * send_sms(). These tests configure the setting, call send_sms() through the
 * real API instance, and verify:
 *   - A phone with a disallowed country prefix is rejected with a WP_Error.
 *   - No HTTP call is made when the country is blocked.
 *   - A phone with an allowed prefix is not blocked.
 *   - An empty allowed_countries array means no restriction.
 *
 * @package KwtSMS_OTP\Tests\Integration
 */

/**
 * Class Test_Integration_Blocked_Countries
 */
class Test_Integration_Blocked_Countries extends WP_UnitTestCase {

	/**
	 * Captured HTTP calls.
	 *
	 * @var array
	 */
	private array $api_calls = [];

	/**
	 * KwtSMS_API instance used across tests.
	 *
	 * @var KwtSMS_API
	 */
	private KwtSMS_API $api;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->api_calls = [];
		add_filter( 'pre_http_request', [ $this, 'intercept_http' ], 10, 3 );

		// Build a real KwtSMS_API instance with dummy credentials in test mode.
		// test_mode=true means the API would add test=1 to the payload;
		// the HTTP interceptor prevents any real network call regardless.
		$this->api = new KwtSMS_API( 'testuser', 'testpass', true, false );

		// Gateway option so balance check passes (test_mode skips balance check,
		// but we set it anyway for consistency).
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
		delete_option( 'kwtsms_otp_general' );
		delete_option( 'kwtsms_otp_gateway' );
		parent::tear_down();
	}

	/**
	 * HTTP interceptor — records every call and returns a fake success.
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

	/**
	 * Count HTTP calls to kwtsms.com/send/.
	 *
	 * @return int
	 */
	private function count_sms_calls(): int {
		return count( array_filter(
			$this->api_calls,
			static function ( $call ) {
				return str_contains( $call['url'], 'kwtsms.com' )
					&& str_contains( $call['url'], 'send/' );
			}
		) );
	}

	// =========================================================================
	// Tests
	// =========================================================================

	/**
	 * send_sms() returns a WP_Error with code 'country_not_allowed' when the
	 * destination phone's country prefix is not in the allowed_countries list.
	 *
	 * Phone 96699220322 starts with 966 (Saudi Arabia, ISO2 SA).
	 * allowed_countries=['KW'] should block it.
	 */
	public function test_sms_blocked_when_country_not_in_allowed_list(): void {
		update_option( 'kwtsms_otp_general', [
			'allowed_countries' => [ 'KW' ], // Only Kuwait allowed.
		] );

		// Saudi Arabia phone (966 prefix).
		$sa_phone = '96699220322';
		$result   = $this->api->send_sms( $sa_phone, 'KWTSMS', 'Test message', 'test' );

		$this->assertWPError( $result, 'Expected WP_Error for disallowed country.' );
		$this->assertSame(
			'country_not_allowed',
			$result->get_error_code(),
			'Error code should be "country_not_allowed".'
		);
	}

	/**
	 * When a country is blocked, no HTTP call is made to kwtsms.com.
	 */
	public function test_no_http_call_made_when_country_blocked(): void {
		update_option( 'kwtsms_otp_general', [
			'allowed_countries' => [ 'KW' ],
		] );

		$sa_phone = '96699220322';
		$this->api->send_sms( $sa_phone, 'KWTSMS', 'Test message', 'test' );

		$this->assertSame(
			0,
			$this->count_sms_calls(),
			'No HTTP call should be made to kwtsms.com/send/ when the country is blocked.'
		);
	}

	/**
	 * send_sms() does NOT return 'country_not_allowed' when the phone's country
	 * is explicitly included in allowed_countries.
	 *
	 * Phone 96598765432 starts with 965 (Kuwait, ISO2 KW).
	 * allowed_countries=['KW','SA'] allows it.
	 */
	public function test_sms_allowed_when_country_in_allowed_list(): void {
		update_option( 'kwtsms_otp_general', [
			'allowed_countries' => [ 'KW', 'SA' ],
		] );

		// Kuwait phone (965 prefix).
		$kw_phone = '96598765432';
		$result   = $this->api->send_sms( $kw_phone, 'KWTSMS', 'Test message', 'test' );

		// Result should NOT be a country_not_allowed error.
		// It may be a different error (e.g. if the interceptor response parsing fails)
		// or a success array — but not a country block.
		if ( is_wp_error( $result ) ) {
			$this->assertNotSame(
				'country_not_allowed',
				$result->get_error_code(),
				'KW phone should not be blocked when KW is in allowed_countries.'
			);
		} else {
			// Successfully dispatched — the HTTP interceptor returned a valid JSON body.
			$this->assertIsArray( $result );
		}
	}

	/**
	 * When allowed_countries is empty, send_sms() does not apply any country
	 * restriction — any phone is allowed.
	 */
	public function test_sms_allowed_when_allowed_list_empty(): void {
		update_option( 'kwtsms_otp_general', [
			'allowed_countries' => [], // No restriction.
		] );

		// Bahrain phone (973 prefix, not explicitly in any common list).
		$bh_phone = '97339123456';
		$result   = $this->api->send_sms( $bh_phone, 'KWTSMS', 'Test message', 'test' );

		if ( is_wp_error( $result ) ) {
			$this->assertNotSame(
				'country_not_allowed',
				$result->get_error_code(),
				'No country should be blocked when allowed_countries is empty.'
			);
		} else {
			$this->assertIsArray( $result );
		}
	}
}
