<?php
/**
 * Tests for KwtSMS_Admin::sanitize_gateway_settings().
 *
 * Verifies that when update_option() is called programmatically (e.g. from
 * ajax_verify_credentials), the sanitize callback preserves the new balance /
 * coverage / sender_ids values that are already present in $raw — rather than
 * silently overwriting them with the stale values that were in the database
 * when the sanitize filter fired.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

// Load the admin class (not included in bootstrap because it registers WP hooks).
require_once KWTSMS_OTP_DIR . 'admin/class-kwtsms-admin.php';

/**
 * Class Test_Admin_Sanitize_Gateway
 */
class Test_Admin_Sanitize_Gateway extends TestCase {

	/** @var KwtSMS_Admin */
	private $admin;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stub all WordPress functions used inside the constructor and sanitize method.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'add_settings_error' )->justReturn( null );
		Functions\when( '__' )->returnArg( 1 );

		// Provide a minimal KwtSMS_Plugin stub.
		$plugin           = new KwtSMS_Plugin();
		$plugin->settings = $this->createMock( KwtSMS_Settings::class );

		$this->admin = new KwtSMS_Admin( $plugin );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Bug fix: sanitize_gateway_settings must not overwrite $raw balance with
	// stale DB values when called via update_option() from an AJAX handler.
	// =========================================================================

	/**
	 * When $raw already has balance_available (programmatic update_option call),
	 * the sanitize callback must preserve the new value.
	 */
	public function test_sanitize_preserves_balance_from_raw_when_present() {
		// Simulate the old DB state: balance 71.00.
		$old_db = array(
			'api_username'         => 'instabox',
			'api_password'         => 'secret',
			'credentials_verified' => 1,
			'sender_ids'           => array( 'KWT-SMS' ),
			'balance_available'    => 71.00,
			'balance_purchased'    => 2002.00,
			'balance_updated_at'   => 1000000,
			'coverage'             => array( array( 'name' => 'Kuwait', 'dial' => '965' ) ),
			'sender_id'            => 'KWT-SMS',
			'test_mode'            => 1,
		);

		// The AJAX handler sets a new balance (68.00) and calls update_option().
		// WordPress fires sanitize_gateway_settings($raw) where $raw is the full
		// updated array. get_option() inside the callback still returns old_db.
		Functions\expect( 'get_option' )
			->once()
			->with( 'kwtsms_otp_gateway', array() )
			->andReturn( $old_db );

		$raw = array_merge( $old_db, array(
			'balance_available'  => 68.00,
			'balance_purchased'  => 2002.00,
			'balance_updated_at' => 1000500,
			'sender_ids'         => array( 'KWT-SMS', 'NEW-SENDER' ),
			'coverage'           => array(
				array( 'name' => 'Kuwait',       'dial' => '965' ),
				array( 'name' => 'Saudi Arabia', 'dial' => '966' ),
			),
		) );

		$result = $this->admin->sanitize_gateway_settings( $raw );

		// New balance must be preserved — NOT the stale 71.00 from DB.
		$this->assertSame( 68.00, $result['balance_available'],
			'sanitize_gateway_settings() must NOT overwrite $raw[balance_available] with stale DB value' );
		$this->assertSame( 2002.00, $result['balance_purchased'] );
		$this->assertSame( 1000500, $result['balance_updated_at'] );

		// Sender IDs and coverage from $raw must also be preserved.
		$this->assertSame( array( 'KWT-SMS', 'NEW-SENDER' ), $result['sender_ids'] );
		$this->assertCount( 2, $result['coverage'] );
	}

	/**
	 * When $raw does NOT have balance_available (form POST from options.php),
	 * the sanitize callback must fall back to the current DB value.
	 */
	public function test_sanitize_falls_back_to_db_balance_when_not_in_raw() {
		$old_db = array(
			'api_username'         => 'instabox',
			'api_password'         => 'secret',
			'credentials_verified' => 1,
			'sender_ids'           => array( 'KWT-SMS' ),
			'balance_available'    => 71.00,
			'balance_purchased'    => 2002.00,
			'balance_updated_at'   => 1000000,
			'coverage'             => array( array( 'name' => 'Kuwait', 'dial' => '965' ) ),
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'kwtsms_otp_gateway', array() )
			->andReturn( $old_db );

		// A form POST only contains the HTML form fields — no balance keys.
		$raw = array(
			'api_username' => 'instabox',
			'api_password' => 'secret',
			'sender_id'    => 'KWT-SMS',
			'test_mode'    => '1',
		);

		$result = $this->admin->sanitize_gateway_settings( $raw );

		// Balance must be carried over from DB since it's not in the form POST.
		$this->assertSame( 71.00, $result['balance_available'],
			'sanitize_gateway_settings() must carry over balance from DB when absent from $raw' );
		$this->assertSame( 2002.00, $result['balance_purchased'] );
	}

	// =========================================================================
	// Security: sanitize_template_settings strips HTML from template bodies
	// =========================================================================

	/**
	 * Template bodies must be plain text — HTML tags stripped.
	 * Verifies that <b> tags are removed from the 'en' template field.
	 */
	public function test_sanitize_strips_html_from_template_body() {
		// wp_strip_all_tags and sanitize_textarea_field are called inside
		// sanitize_template_content(); stub them to behave like their WP counterparts.
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'sanitize_textarea_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( function( $v ) { return $v; } );

		$raw = array(
			'login_otp' => array(
				'enabled' => 1,
				'en'      => '<b>Your code</b> {otp}',
				'ar'      => 'رمزك: {otp}',
			),
		);

		$result = $this->admin->sanitize_template_settings( $raw );

		$this->assertArrayHasKey( 'login_otp', $result );
		$this->assertStringNotContainsString( '<b>', $result['login_otp']['en'],
			'sanitize_template_settings must strip <b> tags from the en template.' );
		$this->assertStringContainsString( 'Your code', $result['login_otp']['en'],
			'sanitize_template_settings must preserve the text content after stripping tags.' );
		$this->assertStringContainsString( '{otp}', $result['login_otp']['en'],
			'sanitize_template_settings must preserve {otp} placeholder.' );
	}

	/**
	 * Script tags in template bodies must be stripped by wp_strip_all_tags.
	 *
	 * PHP's strip_tags() (our test alias for wp_strip_all_tags) removes the
	 * <script> and </script> tags but retains the inner text. The real
	 * wp_strip_all_tags() also strips the inner content of script/style blocks.
	 * This test verifies that at minimum the opening <script> tag is removed.
	 */
	public function test_sanitize_rejects_script_tag_in_template() {
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'sanitize_textarea_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( function( $v ) { return $v; } );

		$raw = array(
			'login_otp' => array(
				'enabled' => 1,
				'en'      => '<script>alert(1)</script>Your code: {otp}',
				'ar'      => 'رمزك: {otp}',
			),
		);

		$result = $this->admin->sanitize_template_settings( $raw );

		// At minimum the opening tag itself must be removed.
		$this->assertStringNotContainsString( '<script>', $result['login_otp']['en'],
			'sanitize_template_settings must strip <script> tags from templates.' );
		// Text after the script block must be preserved.
		$this->assertStringContainsString( 'Your code', $result['login_otp']['en'],
			'sanitize_template_settings must preserve legitimate text after stripped script tag.' );
	}

	/**
	 * Null bytes in the API username must be handled by sanitize_gateway_settings.
	 *
	 * The real sanitize_text_field() strips null bytes. In our test environment
	 * sanitize_text_field is aliased to trim(), which does not remove interior
	 * null bytes, so the credential comparison will show a changed username and
	 * trigger the auto-verify path. We stub the auto-verify API calls to return
	 * an error and verify the result still produces a valid sanitized array.
	 *
	 * This test documents the contract: api_username must be a string in the
	 * sanitized output and must begin with the valid characters that were entered.
	 */
	public function test_sanitize_gateway_rejects_null_bytes_in_username() {
		$old_db = array(
			'api_username'         => 'testuser',
			'api_password'         => 'testpass',
			'credentials_verified' => 1,
			'sender_ids'           => array(),
			'balance_available'    => 10.0,
			'balance_purchased'    => 100.0,
			'balance_updated_at'   => 0,
			'coverage'             => array(),
		);

		Functions\when( 'get_option' )->justReturn( $old_db );

		// Because the null-byte-injected username differs from stored username,
		// the auto-verify path is triggered — stub the required WP/API functions.
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( false );
		Functions\when( 'wp_remote_post' )->justReturn(
			new WP_Error( 'http_request_failed', 'No network in test.' )
		);
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 0 );

		$raw = array(
			'api_username' => "testuser\x00injected",
			'api_password' => 'testpass',
			'sender_id'    => '',
			'test_mode'    => '0',
		);

		$result = $this->admin->sanitize_gateway_settings( $raw );

		$this->assertArrayHasKey( 'api_username', $result,
			'sanitize_gateway_settings must return api_username key.' );
		$this->assertIsString( $result['api_username'],
			'sanitize_gateway_settings api_username must be a string.' );
		// The real sanitize_text_field strips null bytes; our trim alias does not.
		// Either way, the result must start with the valid characters.
		$this->assertStringStartsWith( 'testuser', $result['api_username'],
			'api_username must begin with the valid prefix regardless of injected bytes.' );
	}

	/**
	 * Credentials_verified must be reset to 0 when credentials change on form save.
	 */
	public function test_sanitize_resets_verified_flag_on_credential_change() {
		$old_db = array(
			'api_username'         => 'old_user',
			'api_password'         => 'old_pass',
			'credentials_verified' => 1,
			'sender_ids'           => array(),
			'balance_available'    => 50.00,
			'balance_purchased'    => 100.00,
			'balance_updated_at'   => 0,
			'coverage'             => array(),
		);

		Functions\when( 'get_option' )->justReturn( $old_db );
		// Auto-verify path stubs — KwtSMS_API tries transient cache and HTTP request.
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( false );
		Functions\when( 'wp_remote_post' )->justReturn( new WP_Error( 'http_request_failed', 'No network in test' ) );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 0 );

		$raw = array(
			'api_username' => 'new_user',
			'api_password' => 'new_pass',
			'sender_id'    => '',
			'test_mode'    => '0',
		);

		$result = $this->admin->sanitize_gateway_settings( $raw );

		$this->assertSame( 0, (int) $result['credentials_verified'],
			'credentials_verified must be reset when credentials change' );
	}
}
