<?php
/**
 * Tests for the Form OTP Verification Gate.
 *
 * Covers gate-mode blocking and notification-mode passthrough for:
 *   - CF7 (wpcf7_before_send_mail filter)
 *   - WPForms (wpforms_process_initial_errors filter)
 *   - KwtSMS_Plugin::verify_form_token() helper
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Minimal stub for WPCF7_Submission used in gate-mode tests.
 */
if ( ! class_exists( 'WPCF7_Submission' ) ) {
	// phpcs:ignore
	class WPCF7_Submission {
		public $response = '';
		public function set_response( $msg ) {
			$this->response = $msg;
		}
		public static function get_instance() {
			return null;
		}
	}
}

/**
 * Class Test_Form_Gate_CF7
 *
 * Tests for KwtSMS_CF7 gate-mode behaviour.
 */
class Test_Form_Gate_CF7 extends TestCase {

	/**
	 * Hooks captured during add_filter calls.
	 *
	 * @var string[]
	 */
	private $registered_filters = array();

	/**
	 * Hooks captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_filters = array();
		$this->registered_actions = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );

		Functions\when( 'add_action' )->alias( function ( $hook ) {
			$this->registered_actions[] = $hook;
			return null;
		} );
		Functions\when( 'add_filter' )->alias( function ( $hook ) {
			$this->registered_filters[] = $hook;
			return null;
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// test_cf7_gate_mode_blocks_submit_without_verified_token
	// =========================================================================

	/**
	 * In gate mode, wpcf7_before_send_mail is registered (not wpcf7_mail_sent).
	 *
	 * When no verified token is present in $_POST, gate_verify_token() sets
	 * $abort = true to block CF7 from sending mail.
	 */
	public function test_cf7_gate_mode_blocks_submit_without_verified_token() {
		$plugin = $this->make_plugin_stub( 'gate', false );
		new KwtSMS_CF7( $plugin );

		// Gate mode hooks the filter, not the action.
		$this->assertContains( 'wpcf7_before_send_mail', $this->registered_filters );
		$this->assertNotContains( 'wpcf7_mail_sent', $this->registered_actions );

		// Simulate the filter callback directly.
		$cf7        = $this->getMockBuilder( 'WPCF7_ContactForm' )->disableOriginalConstructor()->getMock();
		$abort      = false;
		$submission = new WPCF7_Submission();

		// No token in POST.
		$_POST = array();

		$gate    = new KwtSMS_CF7( $plugin );
		$gate->gate_verify_token( $cf7, $abort, $submission );

		$this->assertTrue( $abort, 'gate_verify_token should set $abort to true when no token is present' );
	}

	// =========================================================================
	// test_cf7_gate_mode_allows_submit_with_verified_token
	// =========================================================================

	/**
	 * When a valid, verified token is present, gate_verify_token() must NOT
	 * set $abort, allowing CF7 to proceed with mail delivery.
	 */
	public function test_cf7_gate_mode_allows_submit_with_verified_token() {
		// Token that will be treated as verified.
		$token = str_repeat( 'a', 32 );

		$plugin = $this->make_plugin_stub( 'gate', true, $token );

		$cf7        = $this->getMockBuilder( 'WPCF7_ContactForm' )->disableOriginalConstructor()->getMock();
		$abort      = false;
		$submission = new WPCF7_Submission();

		// Inject verified token into POST.
		$_POST = array( 'kwtsms_form_verified_token' => $token );

		$gate = new KwtSMS_CF7( $plugin );
		$gate->gate_verify_token( $cf7, $abort, $submission );

		$this->assertFalse( $abort, 'gate_verify_token should not set $abort when token is verified' );
	}

	// =========================================================================
	// test_notification_mode_sends_sms_after_submit
	// =========================================================================

	/**
	 * In notification mode (default), wpcf7_mail_sent action is registered
	 * and wpcf7_before_send_mail filter is NOT registered. This ensures the
	 * existing behaviour is unchanged when gate mode is not selected.
	 */
	public function test_notification_mode_sends_sms_after_submit() {
		// Mode = 'notification' (default).
		$plugin = $this->make_plugin_stub( 'notification', false );
		new KwtSMS_CF7( $plugin );

		$this->assertContains( 'wpcf7_mail_sent', $this->registered_actions );
		$this->assertNotContains( 'wpcf7_before_send_mail', $this->registered_filters );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a KwtSMS_Plugin stub for CF7 gate tests.
	 *
	 * @param string      $mode          'notification' or 'gate'.
	 * @param bool        $token_valid   Whether verify_form_token() returns true.
	 * @param string|null $expected_token Token value to expect.
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub( $mode, $token_valid, $expected_token = '' ) {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();

		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) use ( $mode ) {
				if ( 'integrations.cf7_enabled' === $key ) {
					return 1;
				}
				if ( 'integrations.cf7_mode' === $key ) {
					return $mode;
				}
				return $default;
			}
		);

		/** @var KwtSMS_Plugin $plugin */
		$plugin           = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'verify_form_token' ) )
			->getMock();
		$plugin->settings = $settings;

		$plugin->method( 'verify_form_token' )->willReturnCallback(
			function ( $token ) use ( $token_valid, $expected_token ) {
				if ( $expected_token && $token === $expected_token ) {
					return $token_valid;
				}
				return $token_valid;
			}
		);

		return $plugin;
	}
}

/**
 * Class Test_Form_Gate_WPForms
 *
 * Tests for KwtSMS_WPForms gate-mode behaviour.
 */
class Test_Form_Gate_WPForms extends TestCase {

	/**
	 * Hooks captured during add_filter calls.
	 *
	 * @var string[]
	 */
	private $registered_filters = array();

	/**
	 * Hooks captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_filters = array();
		$this->registered_actions = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'absint' )->alias( 'intval' );
		Functions\when( '__' )->alias( function ( $text ) { return $text; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );

		Functions\when( 'add_action' )->alias( function ( $hook ) {
			$this->registered_actions[] = $hook;
			return null;
		} );
		Functions\when( 'add_filter' )->alias( function ( $hook ) {
			$this->registered_filters[] = $hook;
			return null;
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// test_wpforms_gate_adds_error_when_unverified
	// =========================================================================

	/**
	 * In gate mode with no verified token, gate_add_error() must inject an
	 * error entry into the errors array under the form's header key.
	 */
	public function test_wpforms_gate_adds_error_when_unverified() {
		$plugin = $this->make_plugin_stub( 'gate', false );

		// No token in POST.
		$_POST = array();

		$wpforms   = new KwtSMS_WPForms( $plugin );
		$form_data = array( 'id' => 42 );
		$errors    = array();

		$result = $wpforms->gate_add_error( $errors, $form_data );

		$this->assertArrayHasKey( 42, $result, 'Errors array must have an entry keyed to form_id 42' );
		$this->assertArrayHasKey( 'header', $result[42], 'Error entry must have a "header" sub-key' );
		$this->assertNotEmpty( $result[42]['header'], 'Header error message must not be empty' );
	}

	/**
	 * In gate mode with a verified token, gate_add_error() must NOT inject
	 * any additional errors.
	 */
	public function test_wpforms_gate_does_not_add_error_when_verified() {
		$token  = str_repeat( 'b', 32 );
		$plugin = $this->make_plugin_stub( 'gate', true );

		$_POST = array( 'kwtsms_form_verified_token' => $token );

		$wpforms   = new KwtSMS_WPForms( $plugin );
		$form_data = array( 'id' => 42 );
		$errors    = array();

		$result = $wpforms->gate_add_error( $errors, $form_data );

		$this->assertEmpty( $result, 'No errors should be added when token is verified' );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a KwtSMS_Plugin stub for WPForms gate tests.
	 *
	 * @param string $mode        'notification' or 'gate'.
	 * @param bool   $token_valid Whether verify_form_token() returns true.
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub( $mode, $token_valid ) {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();

		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) use ( $mode ) {
				if ( 'integrations.wpforms_enabled' === $key ) {
					return 1;
				}
				if ( 'integrations.wpforms_mode' === $key ) {
					return $mode;
				}
				return $default;
			}
		);

		/** @var KwtSMS_Plugin $plugin */
		$plugin           = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'verify_form_token' ) )
			->getMock();
		$plugin->settings = $settings;

		$plugin->method( 'verify_form_token' )->willReturn( $token_valid );

		return $plugin;
	}
}

/**
 * Class Test_Form_Gate_Token
 *
 * Tests for KwtSMS_Plugin::verify_form_token().
 *
 * We test the token verification logic in isolation by reading the
 * actual implementation from the class. Brain\Monkey stubs the WP
 * transient functions.
 */
class Test_Form_Gate_Token extends TestCase {

	/** @var KwtSMS_Plugin */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( null );

		$this->plugin = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->onlyMethods( array() )
			->getMock();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * verify_form_token() returns false for empty token.
	 */
	public function test_verify_form_token_returns_false_for_empty_token() {
		Functions\when( 'get_transient' )->justReturn( false );

		$result = $this->invoke_verify_form_token( '' );
		$this->assertFalse( $result );
	}

	/**
	 * verify_form_token() returns false when transient does not exist.
	 */
	public function test_verify_form_token_returns_false_when_no_transient() {
		Functions\when( 'get_transient' )->justReturn( false );

		$token  = str_repeat( 'c', 32 );
		$result = $this->invoke_verify_form_token( $token );
		$this->assertFalse( $result );
	}

	/**
	 * verify_form_token() returns false when transient exists but verified=false.
	 */
	public function test_verify_form_token_returns_false_when_not_verified() {
		$token = str_repeat( 'd', 32 );
		Functions\when( 'get_transient' )->justReturn(
			array( 'phone' => '96599220322', 'otp_hash' => 'x', 'verified' => false )
		);

		$result = $this->invoke_verify_form_token( $token );
		$this->assertFalse( $result );
	}

	/**
	 * verify_form_token() returns true when transient exists with verified=true.
	 */
	public function test_verify_form_token_returns_true_when_verified() {
		$token = str_repeat( 'e', 32 );
		Functions\when( 'get_transient' )->justReturn(
			array( 'phone' => '96599220322', 'otp_hash' => 'x', 'verified' => true )
		);

		$result = $this->invoke_verify_form_token( $token );
		$this->assertTrue( $result );
	}

	/**
	 * verify_form_token() rejects tokens that are not 32 lowercase hex chars.
	 */
	public function test_verify_form_token_rejects_invalid_token_format() {
		Functions\when( 'get_transient' )->justReturn(
			array( 'phone' => '96599220322', 'otp_hash' => 'x', 'verified' => true )
		);

		// 16 chars instead of 32.
		$this->assertFalse( $this->invoke_verify_form_token( str_repeat( 'f', 16 ) ) );
		// Non-hex characters.
		$this->assertFalse( $this->invoke_verify_form_token( str_repeat( 'z', 32 ) ) );
	}

	// =========================================================================
	// Helper
	// =========================================================================

	/**
	 * Invoke the verify_form_token method on a real KwtSMS_Plugin instance
	 * that has been created with a disabled constructor.
	 *
	 * @param string $token
	 * @return bool
	 */
	private function invoke_verify_form_token( $token ) {
		// Use reflection to call the public method on the mock (disabled ctor).
		$ref    = new ReflectionClass( 'KwtSMS_Plugin' );
		$method = $ref->getMethod( 'verify_form_token' );
		return $method->invoke( $this->plugin, $token );
	}
}
