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
			'api_username'         => 'YOUR_API_USERNAME',
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
			'api_username'         => 'YOUR_API_USERNAME',
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
			'api_username' => 'YOUR_API_USERNAME',
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
