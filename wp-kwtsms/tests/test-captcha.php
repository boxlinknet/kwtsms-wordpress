<?php
/**
 * Tests for KwtSMS_Captcha — provider routing and token verification.
 *
 * Covers:
 *  - provider='none'      verify() always returns true (no network call)
 *  - provider='recaptcha'  missing token  WP_Error
 *  - provider='recaptcha'  empty secret   returns true (skip verification)
 *  - provider='recaptcha'  API success:false  WP_Error('kwtsms_captcha_failed')
 *  - provider='recaptcha'  score < 0.5   WP_Error('kwtsms_captcha_score')
 *  - provider='recaptcha'  score >= 0.5  true
 *  - provider='turnstile'  missing token  WP_Error
 *  - provider='turnstile'  API success:false  WP_Error('kwtsms_captcha_failed')
 *  - provider='turnstile'  API success:true  true
 *  - render_widget()  returns empty string for provider='none'
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_Captcha
 */
class Test_KwtSMS_Captcha extends TestCase {

	/** @var KwtSMS_Settings|\PHPUnit\Framework\MockObject\MockObject */
	private $settings;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// The KwtSMS_Captcha constructor calls add_action() — mock it.
		Functions\when( 'add_action' )->justReturn( true );

		// sanitize_text_field is called inside verify().
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );

		// __() is called inside WP_Error messages.
		Functions\when( '__' )->returnArg( 1 );

		// esc_attr is used by render_turnstile().
		Functions\when( 'esc_attr' )->returnArg( 1 );

		// Build settings mock.
		$this->settings = $this->createMock( KwtSMS_Settings::class );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Helper: configure the settings mock for a specific provider
	// =========================================================================

	/**
	 * Configure the settings mock to return a given captcha provider and keys.
	 *
	 * @param string $provider         'none', 'recaptcha', or 'turnstile'.
	 * @param string $site_key         Provider site key (used for script enqueue / widget render).
	 * @param string $secret_key       Provider secret key (used for server-side verification).
	 */
	private function configure_settings( $provider, $site_key = '', $secret_key = '' ) {
		$this->settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) use ( $provider, $site_key, $secret_key ) {
				$map = array(
					'general.captcha_provider'     => $provider,
					'general.recaptcha_site_key'   => $site_key,
					'general.recaptcha_secret_key' => $secret_key,
					'general.turnstile_site_key'   => $site_key,
					'general.turnstile_secret_key' => $secret_key,
				);
				return $map[ $key ] ?? $default;
			}
		);
	}

	// =========================================================================
	// provider = 'none'
	// =========================================================================

	public function test_verify_none_provider_returns_true_without_post_data() {
		$this->configure_settings( 'none' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$result = $captcha->verify( array() );

		$this->assertTrue( $result );
	}

	public function test_verify_none_provider_ignores_any_token_in_post_data() {
		$this->configure_settings( 'none' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		// Even with a bogus token submitted, provider='none' must pass through.
		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'bogus_token' ) );

		$this->assertTrue( $result );
	}

	public function test_render_widget_returns_empty_string_for_none_provider() {
		$this->configure_settings( 'none' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$html = $captcha->render_widget();

		$this->assertSame( '', $html );
	}

	// =========================================================================
	// provider = 'recaptcha' — missing token
	// =========================================================================

	public function test_verify_recaptcha_missing_token_returns_wp_error() {
		$this->configure_settings( 'recaptcha', 'site_key_123', 'secret_key_456' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		// No token in POST — must fail.
		$result = $captcha->verify( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_missing', $result->get_error_code() );
	}

	public function test_verify_recaptcha_empty_string_token_returns_wp_error() {
		$this->configure_settings( 'recaptcha', 'site_key_123', 'secret_key_456' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => '' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_missing', $result->get_error_code() );
	}

	// =========================================================================
	// provider = 'recaptcha' — empty secret (skip verification)
	// =========================================================================

	public function test_verify_recaptcha_empty_secret_returns_true() {
		// Secret key is blank  verification is skipped (fail open).
		$this->configure_settings( 'recaptcha', 'site_key_123', '' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'some_token' ) );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// provider = 'recaptcha' — network failure (fail open)
	// =========================================================================

	public function test_verify_recaptcha_network_error_returns_true() {
		$this->configure_settings( 'recaptcha', 'sk', 'secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		// wp_remote_post returns WP_Error to simulate network failure.
		$wp_error = new WP_Error( 'http_request_failed', 'Network error' );
		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'some_token' ) );

		// Fail open — must return true so legitimate users are not blocked.
		$this->assertTrue( $result );
	}

	// =========================================================================
	// provider = 'recaptcha' — API returns success:false
	// =========================================================================

	public function test_verify_recaptcha_api_success_false_returns_wp_error() {
		$this->configure_settings( 'recaptcha', 'sk', 'secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$api_body = json_encode( array( 'success' => false ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'some_token' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_failed', $result->get_error_code() );
	}

	// =========================================================================
	// provider = 'recaptcha' — score too low
	// =========================================================================

	public function test_verify_recaptcha_score_below_threshold_returns_wp_error() {
		$this->configure_settings( 'recaptcha', 'sk', 'secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		// score=0.3 is below the 0.5 threshold.
		$api_body = json_encode( array( 'success' => true, 'score' => 0.3 ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'some_token' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_score', $result->get_error_code() );
	}

	public function test_verify_recaptcha_score_exactly_at_threshold_boundary_below_returns_error() {
		// 0.499 is still below 0.5 — must return WP_Error.
		$this->configure_settings( 'recaptcha', 'sk', 'secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$api_body = json_encode( array( 'success' => true, 'score' => 0.499 ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'token' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_score', $result->get_error_code() );
	}

	// =========================================================================
	// provider = 'recaptcha' — score at or above threshold  true
	// =========================================================================

	public function test_verify_recaptcha_score_exactly_at_threshold_returns_true() {
		// score=0.5 meets the threshold (condition is score < 0.5, so 0.5 is allowed).
		$this->configure_settings( 'recaptcha', 'sk', 'secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$api_body = json_encode( array( 'success' => true, 'score' => 0.5 ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'token' ) );

		$this->assertTrue( $result );
	}

	public function test_verify_recaptcha_high_score_returns_true() {
		$this->configure_settings( 'recaptcha', 'sk', 'secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		// score=0.9 — clearly human.
		$api_body = json_encode( array( 'success' => true, 'score' => 0.9 ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'token' ) );

		$this->assertTrue( $result );
	}

	public function test_verify_recaptcha_success_without_score_field_returns_true() {
		// Some reCAPTCHA v3 responses omit the score field — should still pass
		// as long as success=true.
		$this->configure_settings( 'recaptcha', 'sk', 'secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$api_body = json_encode( array( 'success' => true ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'kwtsms_recaptcha_token' => 'token' ) );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// provider = 'turnstile' — missing token
	// =========================================================================

	public function test_verify_turnstile_missing_token_returns_wp_error() {
		$this->configure_settings( 'turnstile', 'ts_site', 'ts_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$result = $captcha->verify( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_missing', $result->get_error_code() );
	}

	public function test_verify_turnstile_empty_string_token_returns_wp_error() {
		$this->configure_settings( 'turnstile', 'ts_site', 'ts_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$result = $captcha->verify( array( 'cf-turnstile-response' => '' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_missing', $result->get_error_code() );
	}

	// =========================================================================
	// provider = 'turnstile' — empty secret (skip verification)
	// =========================================================================

	public function test_verify_turnstile_empty_secret_returns_true() {
		$this->configure_settings( 'turnstile', 'ts_site', '' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$result = $captcha->verify( array( 'cf-turnstile-response' => 'some_token' ) );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// provider = 'turnstile' — network failure (fail open)
	// =========================================================================

	public function test_verify_turnstile_network_error_returns_true() {
		$this->configure_settings( 'turnstile', 'ts_site', 'ts_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$wp_error = new WP_Error( 'http_request_failed', 'Network error' );
		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );

		$result = $captcha->verify( array( 'cf-turnstile-response' => 'some_token' ) );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// provider = 'turnstile' — API returns success:false
	// =========================================================================

	public function test_verify_turnstile_api_success_false_returns_wp_error() {
		$this->configure_settings( 'turnstile', 'ts_site', 'ts_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$api_body = json_encode( array( 'success' => false ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'cf-turnstile-response' => 'some_token' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_captcha_failed', $result->get_error_code() );
	}

	// =========================================================================
	// provider = 'turnstile' — API returns success:true
	// =========================================================================

	public function test_verify_turnstile_api_success_true_returns_true() {
		$this->configure_settings( 'turnstile', 'ts_site', 'ts_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$api_body = json_encode( array( 'success' => true ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'cf-turnstile-response' => 'valid_cf_token' ) );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// Turnstile does NOT use a score field — extra challenge data is ignored
	// =========================================================================

	public function test_verify_turnstile_extra_fields_in_response_do_not_affect_result() {
		$this->configure_settings( 'turnstile', 'ts_site', 'ts_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		// Turnstile may return extra fields — only 'success' matters.
		$api_body = json_encode( array(
			'success'    => true,
			'hostname'   => 'example.com',
			'challenge_ts' => '2026-03-01T12:00:00Z',
		) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => $api_body ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_body );

		$result = $captcha->verify( array( 'cf-turnstile-response' => 'cf_token' ) );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// render_widget() — correct HTML snippets
	// =========================================================================

	public function test_render_widget_recaptcha_contains_hidden_input_when_site_key_set() {
		$this->configure_settings( 'recaptcha', 'my_site_key', 'my_secret' );
		// wp_json_encode is called in the reCAPTCHA widget render.
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$captcha = new KwtSMS_Captcha( $this->settings );
		$html    = $captcha->render_widget();

		$this->assertStringContainsString( 'kwtsms_recaptcha_token', $html );
		$this->assertStringContainsString( 'my_site_key', $html );
	}

	public function test_render_widget_recaptcha_returns_empty_when_site_key_missing() {
		$this->configure_settings( 'recaptcha', '', 'my_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$html = $captcha->render_widget();

		$this->assertSame( '', $html );
	}

	public function test_render_widget_turnstile_contains_cf_div_when_site_key_set() {
		$this->configure_settings( 'turnstile', 'cf_site_key', 'cf_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$html = $captcha->render_widget();

		$this->assertStringContainsString( 'cf-turnstile', $html );
		$this->assertStringContainsString( 'cf_site_key', $html );
	}

	public function test_render_widget_turnstile_returns_empty_when_site_key_missing() {
		$this->configure_settings( 'turnstile', '', 'cf_secret' );
		$captcha = new KwtSMS_Captcha( $this->settings );

		$html = $captcha->render_widget();

		$this->assertSame( '', $html );
	}
}
