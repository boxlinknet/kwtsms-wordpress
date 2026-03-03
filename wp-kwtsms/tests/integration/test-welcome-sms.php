<?php
/**
 * Integration tests — Welcome SMS on user_register.
 *
 * Verifies that maybe_send_welcome_on_register() sends (or skips) an HTTP
 * request to kwtsms.com based on the welcome_sms_enabled setting and whether
 * the user has a kwtsms_phone meta value.
 *
 * Uses pre_http_request to intercept all outgoing HTTP calls so no real
 * network traffic is produced.
 *
 * @package KwtSMS_OTP\Tests\Integration
 */

/**
 * Class Test_Integration_Welcome_SMS
 */
class Test_Integration_Welcome_SMS extends WP_UnitTestCase {

	/**
	 * Captured HTTP calls made during the test.
	 *
	 * Each entry: [ 'url' => string, 'args' => array ]
	 *
	 * @var array
	 */
	private array $api_calls = [];

	/**
	 * Set up: reset api_calls, install HTTP interceptor, seed gateway settings,
	 * and reset the singleton's settings cache so our option changes take effect.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->api_calls = [];
		add_filter( 'pre_http_request', [ $this, 'intercept_http' ], 10, 3 );

		// Minimal gateway config so KwtSMS_API does not bail on missing creds.
		update_option( 'kwtsms_otp_gateway', [
			'api_username'         => 'testuser',
			'api_password'         => 'testpass',
			'sender_id'            => 'KWTSMS',
			'test_mode'            => 1,
			'credentials_verified' => 1,
			'balance_available'    => 10.0,
		] );

		// The KwtSMS_Plugin singleton was created at plugins_loaded time.
		// Its KwtSMS_Settings object has an in-memory cache of the option values
		// loaded at that point (which were empty). We must reset that cache so
		// subsequent settings reads reflect the values we write in set_up().
		// We also rebuild the KwtSMS_API instance with fresh credentials.
		$this->reset_plugin_singleton_state();
	}

	/**
	 * Flush the singleton's settings cache and rebuild the API instance.
	 *
	 * Uses reflection to clear KwtSMS_Settings::$cache so that the next
	 * settings read goes to the DB (where we stored our test credentials).
	 * Also replaces KwtSMS_Plugin::$api with a fresh instance using the
	 * test credentials so send_sms() can actually pass the credential check.
	 */
	private function reset_plugin_singleton_state(): void {
		if ( ! class_exists( 'KwtSMS_Plugin' ) ) {
			return;
		}
		$plugin = KwtSMS_Plugin::get_instance();

		// Clear the settings in-memory cache via reflection.
		$settings_ref   = new ReflectionObject( $plugin->settings );
		$cache_prop     = $settings_ref->getProperty( 'cache' );
		$cache_prop->setAccessible( true );
		$cache_prop->setValue( $plugin->settings, [] );

		// Replace the API instance with one that uses the test credentials.
		$plugin_ref  = new ReflectionObject( $plugin );
		$api_prop    = $plugin_ref->getProperty( 'api' );
		$api_prop->setAccessible( true );
		$api_prop->setValue( $plugin, new KwtSMS_API( 'testuser', 'testpass', true, false ) );
	}

	/**
	 * Tear down: remove interceptor and restore defaults.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'intercept_http' ] );
		delete_option( 'kwtsms_otp_gateway' );
		delete_option( 'kwtsms_otp_general' );
		parent::tear_down();
	}

	/**
	 * HTTP interceptor — records every outgoing request and returns a fake 200.
	 *
	 * @param false|array|WP_Error $preempt Return value to short-circuit request.
	 * @param array                $args    Request arguments.
	 * @param string               $url     Request URL.
	 *
	 * @return array Fake HTTP response.
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
	 * Count how many captured HTTP calls targeted kwtsms.com/API/send/.
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
	 * A user with a kwtsms_phone meta and welcome_sms_enabled=1 triggers an
	 * HTTP POST to kwtsms.com when user_register fires.
	 */
	public function test_welcome_sms_sent_on_user_register(): void {
		// Enable welcome SMS.
		update_option( 'kwtsms_otp_general', [
			'welcome_sms_enabled'  => 1,
			'allowed_countries'    => [], // No restriction.
			'default_country_code' => 'KW',
		] );

		// Set up a welcome template so the message is non-empty.
		update_option( 'kwtsms_otp_templates', [
			'welcome_sms' => [
				'enabled' => 1,
				'en'      => 'Welcome {name}! Your account is ready.',
				'ar'      => '',
			],
		] );

		// Create a user, then manually set the kwtsms_phone meta BEFORE firing
		// user_register so maybe_send_welcome_on_register() can find it.
		$user_id = $this->factory()->user->create( [
			'user_login' => 'welcometestuser',
			'user_email' => 'welcometest@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', '96599220322' );

		// Reset recorded calls (the factory may have triggered hooks).
		$this->api_calls = [];

		// Fire the hook that triggers maybe_send_welcome_on_register().
		do_action( 'user_register', $user_id );

		$this->assertGreaterThanOrEqual(
			1,
			$this->count_sms_calls(),
			'Expected at least one HTTP POST to kwtsms.com/send/ when welcome_sms_enabled=1 and user has phone.'
		);
	}

	/**
	 * When welcome_sms_enabled=0, no HTTP call is made even if the user has a phone.
	 */
	public function test_welcome_sms_not_sent_when_disabled(): void {
		update_option( 'kwtsms_otp_general', [
			'welcome_sms_enabled'  => 0,
			'allowed_countries'    => [],
			'default_country_code' => 'KW',
		] );

		$user_id = $this->factory()->user->create( [
			'user_login' => 'disabledwelcomeuser',
			'user_email' => 'disabledwelcome@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', '96599220322' );

		$this->api_calls = [];
		do_action( 'user_register', $user_id );

		$this->assertSame(
			0,
			$this->count_sms_calls(),
			'Expected no HTTP call when welcome_sms_enabled=0.'
		);
	}

	/**
	 * When the user has no kwtsms_phone meta, no HTTP call is made even if
	 * welcome_sms_enabled=1.
	 */
	public function test_welcome_sms_not_sent_when_user_has_no_phone(): void {
		update_option( 'kwtsms_otp_general', [
			'welcome_sms_enabled'  => 1,
			'allowed_countries'    => [],
			'default_country_code' => 'KW',
		] );

		update_option( 'kwtsms_otp_templates', [
			'welcome_sms' => [
				'enabled' => 1,
				'en'      => 'Welcome {name}!',
				'ar'      => '',
			],
		] );

		$user_id = $this->factory()->user->create( [
			'user_login' => 'nophoneuser',
			'user_email' => 'nophone@example.com',
		] );
		// Intentionally do NOT set kwtsms_phone meta.

		$this->api_calls = [];
		do_action( 'user_register', $user_id );

		$this->assertSame(
			0,
			$this->count_sms_calls(),
			'Expected no HTTP call when user has no kwtsms_phone meta.'
		);
	}
}
