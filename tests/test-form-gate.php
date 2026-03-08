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
 *
 * The static $test_instance property allows individual tests to inject a
 * configurable double: call WPCF7_Submission::set_test_instance( $obj )
 * before exercising gate_verify_token(), then reset it to null afterwards.
 * When no test instance is set, get_instance() returns null (original behaviour).
 */
if ( ! class_exists( 'WPCF7_Submission' ) ) {
	// phpcs:ignore
	class WPCF7_Submission {
		/** @var WPCF7_Submission|null Configurable double for unit tests. */
		private static $test_instance = null;

		public $response = '';

		public function set_response( $msg ) {
			$this->response = $msg;
		}

		/**
		 * Register a test double that get_instance() will return.
		 *
		 * Call with null to restore the default (returns null) behaviour.
		 *
		 * @param WPCF7_Submission|null $instance
		 */
		public static function set_test_instance( $instance ) {
			self::$test_instance = $instance;
		}

		/** @return WPCF7_Submission|null */
		public static function get_instance() {
			return self::$test_instance;
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
		Functions\when( '__' )->returnArg();

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
		WPCF7_Submission::set_test_instance( null );
		$_POST = array();
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

		// No token in POST — WPCF7_Submission::get_instance() returns null,
		// so gate_verify_token gets an empty posted array.
		$_POST = array();
		WPCF7_Submission::set_test_instance( null );

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

		// Provide a WPCF7_Submission that returns the token in posted data.
		$sub_stub = $this->getMockBuilder( 'WPCF7_Submission' )
			->addMethods( array( 'get_posted_data' ) )
			->getMock();
		$sub_stub->method( 'get_posted_data' )->willReturn(
			array( 'kwtsms_form_verified_token' => $token )
		);
		WPCF7_Submission::set_test_instance( $sub_stub );

		// Also stub delete_transient so the success path doesn't error.
		Functions\when( 'delete_transient' )->justReturn( null );

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

		$this->assertContains( 'wpcf7_submit', $this->registered_actions );
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
		$_POST = array();
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

		Functions\when( 'delete_transient' )->justReturn( null );

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
 * Class Test_Form_Gate_Security
 *
 * Security regression tests for the Form OTP Verification Gate (v2.7.0):
 *   1. Attempt counter — wrong codes increment the counter; five wrong codes
 *      trigger a lockout and delete the transient.
 *   2. Token consumption — gate_verify_token() / gate_add_error() delete the
 *      transient on a successful (verified) token so it cannot be replayed.
 *
 * All WordPress functions (check_ajax_referer, get_transient, set_transient,
 * delete_transient, wp_check_password, wp_send_json_error, wp_send_json_success,
 * __) are stubbed via Brain\Monkey; no WordPress install is required.
 */
class Test_Form_Gate_Security extends TestCase {

	/**
	 * Track every delete_transient key that was requested.
	 *
	 * @var string[]
	 */
	private $deleted_transients = array();

	/**
	 * Track every set_transient call as [ key, data, ttl ].
	 *
	 * @var array[]
	 */
	private $set_transient_calls = array();

	/**
	 * Track wp_send_json_error payloads.
	 *
	 * @var array[]
	 */
	private $json_errors = array();

	/**
	 * Track wp_send_json_success payloads.
	 *
	 * @var array[]
	 */
	private $json_successes = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->deleted_transients  = array();
		$this->set_transient_calls = array();
		$this->json_errors         = array();
		$this->json_successes      = array();

		// Core WP function stubs.
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'absint' )->alias( 'intval' );
		Functions\when( '__' )->alias( function ( $text ) { return $text; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( null );

		// Nonce check always passes — we are not testing nonce logic here.
		Functions\when( 'check_ajax_referer' )->justReturn( true );

		// Capture delete_transient calls.
		$deleted_transients = &$this->deleted_transients;
		Functions\when( 'delete_transient' )->alias(
			function ( $key ) use ( &$deleted_transients ) {
				$deleted_transients[] = $key;
			}
		);

		// Capture set_transient calls.
		$set_transient_calls = &$this->set_transient_calls;
		Functions\when( 'set_transient' )->alias(
			function ( $key, $data, $ttl ) use ( &$set_transient_calls ) {
				$set_transient_calls[] = array(
					'key'  => $key,
					'data' => $data,
					'ttl'  => $ttl,
				);
			}
		);

		// Capture wp_send_json_error; suppress the real die() it would trigger.
		$json_errors = &$this->json_errors;
		Functions\when( 'wp_send_json_error' )->alias(
			function ( $payload = null ) use ( &$json_errors ) {
				$json_errors[] = $payload;
			}
		);

		// Capture wp_send_json_success.
		$json_successes = &$this->json_successes;
		Functions\when( 'wp_send_json_success' )->alias(
			function ( $payload = null ) use ( &$json_successes ) {
				$json_successes[] = $payload;
			}
		);
	}

	protected function tearDown(): void {
		WPCF7_Submission::set_test_instance( null );
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Test 1a — wrong code increments the attempt counter
	// =========================================================================

	/**
	 * Submitting a wrong code when attempts=0 must:
	 *  - call set_transient with attempts incremented to 1.
	 *  - call wp_send_json_error (not success).
	 *  - NOT call delete_transient (lockout not triggered yet).
	 */
	public function test_verify_otp_increments_attempts_on_wrong_code() {
		$token = str_repeat( 'a', 32 );

		Functions\when( 'get_transient' )->justReturn(
			array(
				'phone'    => '96598765432',
				'otp_hash' => 'some-bcrypt-hash',
				'verified' => false,
				'attempts' => 0,
			)
		);
		// Wrong code — wp_check_password returns false.
		Functions\when( 'wp_check_password' )->justReturn( false );

		$_POST = array(
			'nonce' => 'fake-nonce',
			'token' => $token,
			'code'  => '000000',
		);

		$plugin = $this->make_plugin_stub();
		$plugin->ajax_form_verify_otp();

		// set_transient must have been called with attempts incremented to 1.
		$this->assertNotEmpty(
			$this->set_transient_calls,
			'set_transient must be called to persist the incremented attempt count'
		);
		$call = $this->set_transient_calls[0];
		$this->assertSame(
			'kwtsms_form_otp_' . $token,
			$call['key'],
			'set_transient key must be kwtsms_form_otp_{token}'
		);
		$this->assertSame(
			1,
			$call['data']['attempts'],
			'Attempt counter must be incremented to 1'
		);

		// wp_send_json_error must have been called.
		$this->assertNotEmpty( $this->json_errors, 'wp_send_json_error must be called on wrong code' );

		// delete_transient must NOT have been called (not yet at the max of 5).
		$this->assertEmpty(
			$this->deleted_transients,
			'delete_transient must NOT be called when attempts < 5'
		);
	}

	// =========================================================================
	// Test 1b — attempts already at max (5) triggers lockout
	// =========================================================================

	/**
	 * When the transient already has attempts=5 (at the maximum), the handler
	 * must:
	 *  - call delete_transient with the session key to destroy it.
	 *  - call wp_send_json_error with "Too many incorrect attempts" message.
	 *  - NOT call set_transient (counter is not re-saved after deletion).
	 */
	public function test_verify_otp_locks_out_when_attempts_reaches_max() {
		$token = str_repeat( 'b', 32 );

		Functions\when( 'get_transient' )->justReturn(
			array(
				'phone'    => '96598765432',
				'otp_hash' => 'irrelevant-hash',
				'verified' => false,
				'attempts' => 5,
			)
		);
		Functions\when( 'wp_check_password' )->justReturn( false );

		$_POST = array(
			'nonce' => 'fake-nonce',
			'token' => $token,
			'code'  => '111111',
		);

		$plugin = $this->make_plugin_stub();
		$plugin->ajax_form_verify_otp();

		// delete_transient must have been called with the session key.
		$this->assertContains(
			'kwtsms_form_otp_' . $token,
			$this->deleted_transients,
			'delete_transient must be called with kwtsms_form_otp_{token} on lockout'
		);

		// wp_send_json_error must have been called with the lockout message.
		$this->assertNotEmpty( $this->json_errors, 'wp_send_json_error must be called on lockout' );
		$payload = $this->json_errors[0];
		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertStringContainsString(
			'Too many incorrect attempts',
			$payload['message'],
			'Lockout error must mention "Too many incorrect attempts"'
		);

		// set_transient must NOT be called (session deleted, not re-saved).
		$this->assertEmpty(
			$this->set_transient_calls,
			'set_transient must NOT be called after lockout deletion'
		);
	}

	// =========================================================================
	// Test 2a — CF7 gate deletes transient on verified token
	// =========================================================================

	/**
	 * When gate_verify_token() on KwtSMS_CF7 receives a verified token it must
	 * call delete_transient('kwtsms_form_otp_{token}') to consume the single-use
	 * token before allowing CF7 to send mail.
	 *
	 * The WPCF7_Submission stub supports a configurable test instance via
	 * WPCF7_Submission::set_test_instance(), which lets us control what
	 * get_posted_data() returns without runkit or static override hacks.
	 */
	public function test_cf7_gate_deletes_transient_on_verified_token() {
		$token = str_repeat( 'c', 32 );

		// Provide a WPCF7_Submission that returns the token in posted data.
		$sub_stub = $this->getMockBuilder( 'WPCF7_Submission' )
			->addMethods( array( 'get_posted_data' ) )
			->getMock();
		$sub_stub->method( 'get_posted_data' )->willReturn(
			array( 'kwtsms_form_verified_token' => $token )
		);
		WPCF7_Submission::set_test_instance( $sub_stub );

		$plugin = $this->make_plugin_stub_returning( true );
		$gate   = new KwtSMS_CF7( $plugin );

		$cf7        = $this->getMockBuilder( 'WPCF7_ContactForm' )->disableOriginalConstructor()->getMock();
		$abort      = false;
		$submission = new WPCF7_Submission();

		$gate->gate_verify_token( $cf7, $abort, $submission );

		// Token verified — $abort must stay false and transient must be consumed.
		$this->assertFalse( $abort, 'gate_verify_token must not abort when token is verified' );
		$this->assertContains(
			'kwtsms_form_otp_' . $token,
			$this->deleted_transients,
			'CF7 gate must call delete_transient(kwtsms_form_otp_{token}) on verified token'
		);
	}

	// =========================================================================
	// Test 2b — WPForms gate deletes transient on verified token
	// =========================================================================

	/**
	 * When gate_add_error() on KwtSMS_WPForms finds a verified token it must
	 * call delete_transient('kwtsms_form_otp_{token}') to consume the token.
	 */
	public function test_wpforms_gate_deletes_transient_on_verified_token() {
		$token = str_repeat( 'd', 32 );

		// Inject the verified token into POST (WPForms gate reads $_POST directly).
		$_POST = array( 'kwtsms_form_verified_token' => $token );

		$plugin    = $this->make_plugin_stub_returning( true );
		$wpforms   = new KwtSMS_WPForms( $plugin );
		$form_data = array( 'id' => 99 );

		$wpforms->gate_add_error( array(), $form_data );

		$this->assertContains(
			'kwtsms_form_otp_' . $token,
			$this->deleted_transients,
			'WPForms gate must call delete_transient(kwtsms_form_otp_{token}) on verified token'
		);
	}

	// =========================================================================
	// Test 2c — Elementor gate deletes transient on verified token
	// =========================================================================

	/**
	 * When gate_add_error() on KwtSMS_Elementor finds a verified token it must
	 * call delete_transient('kwtsms_form_otp_{token}') to consume the token and
	 * must NOT call $handler->add_error() (no validation error is shown).
	 */
	public function test_elementor_gate_deletes_transient_on_verified_token() {
		$token = str_repeat( 'e', 32 );

		// Inject the verified token into POST (Elementor gate reads $_POST directly).
		$_POST = array( 'kwtsms_form_verified_token' => $token );

		$plugin    = $this->make_plugin_stub_returning( true );
		$elementor = new KwtSMS_Elementor( $plugin );

		// Build minimal Elementor record and handler stubs.
		$record = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		// gate_add_error returns early on success; get() is never called.
		$record->method( 'get' )->willReturn( array() );

		$handler = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'add_error' ) )
			->getMock();
		// On a verified token, no validation error should be added.
		$handler->expects( $this->never() )->method( 'add_error' );

		$elementor->gate_add_error( $record, $handler );

		$this->assertContains(
			'kwtsms_form_otp_' . $token,
			$this->deleted_transients,
			'Elementor gate must call delete_transient(kwtsms_form_otp_{token}) on verified token'
		);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Create a KwtSMS_Plugin stub (from bootstrap) with the constructor disabled.
	 *
	 * Used for AJAX handler tests where verify_form_token() is not overridden
	 * and the bootstrap stub's real implementation is exercised.
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub() {
		return $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->onlyMethods( array() )
			->getMock();
	}

	/**
	 * Create a KwtSMS_Plugin stub where verify_form_token() returns a fixed value.
	 *
	 * Also configures a settings stub so that all integration-enabled and
	 * integration-mode checks return the "gate, enabled" combination needed
	 * to exercise the gate code path.
	 *
	 * @param bool $verified Return value for verify_form_token().
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub_returning( $verified ) {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();

		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) {
				if ( false !== strpos( $key, '_enabled' ) ) {
					return 1;
				}
				if ( false !== strpos( $key, '_mode' ) ) {
					return 'gate';
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
		$plugin->method( 'verify_form_token' )->willReturn( $verified );

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
			array( 'phone' => '96598765432', 'otp_hash' => 'x', 'verified' => false )
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
			array( 'phone' => '96598765432', 'otp_hash' => 'x', 'verified' => true )
		);

		$result = $this->invoke_verify_form_token( $token );
		$this->assertTrue( $result );
	}

	/**
	 * verify_form_token() rejects tokens that are not 32 lowercase hex chars.
	 */
	public function test_verify_form_token_rejects_invalid_token_format() {
		Functions\when( 'get_transient' )->justReturn(
			array( 'phone' => '96598765432', 'otp_hash' => 'x', 'verified' => true )
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
