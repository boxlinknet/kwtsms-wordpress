<?php
/**
 * Tests for KwtSMS_Admin::sanitize_alerts_settings().
 *
 * Verifies that the alerts settings sanitization callback correctly handles
 * phone numbers, per-event toggle checkboxes, message templates, and missing
 * keys (which should default to zero / empty string).
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

// Load the admin class (not included in bootstrap because it registers WP hooks).
require_once KWTSMS_OTP_DIR . 'admin/class-kwtsms-admin.php';

/**
 * Class Test_Admin_Alerts_Sanitize
 */
class Test_Admin_Alerts_Sanitize extends TestCase {

	/**
	 * Admin instance under test.
	 *
	 * @var KwtSMS_Admin
	 */
	private $admin;

	/**
	 * Set up Brain\Monkey and create the admin instance.
	 */
	protected function setUp(): void {
		parent::setUp();
		Brain\Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'absint' )->alias( 'intval' );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );

		$plugin           = $this->createMock( KwtSMS_Plugin::class );
		$plugin->settings = $this->createMock( KwtSMS_Settings::class );
		$this->admin      = new KwtSMS_Admin( $plugin );
	}

	/**
	 * Tear down Brain\Monkey after each test.
	 */
	protected function tearDown(): void {
		Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Valid phone numbers should be stored as-is.
	 */
	public function test_sanitize_alerts_keeps_valid_phones(): void {
		$input  = array( 'admin_phones' => '96598765432, 96512345678' );
		$result = $this->admin->sanitize_alerts_settings( $input );
		$this->assertSame( '96598765432, 96512345678', $result['admin_phones'] );
	}

	/**
	 * Toggle checkboxes must be cast to int 1 or 0.
	 */
	public function test_sanitize_alerts_toggles_cast_to_int(): void {
		$input  = array(
			'user_register'  => '1',
			'wp_login'       => '0',
			'post_published' => '',
			'comment_posted' => '1',
			'core_update'    => '1',
		);
		$result = $this->admin->sanitize_alerts_settings( $input );
		$this->assertSame( 1, $result['user_register'] );
		$this->assertSame( 0, $result['wp_login'] );
		$this->assertSame( 0, $result['post_published'] );
		$this->assertSame( 1, $result['comment_posted'] );
		$this->assertSame( 1, $result['core_update'] );
	}

	/**
	 * Template keys _en and _ar should be stored under the base key as a sub-array.
	 */
	public function test_sanitize_alerts_templates_sanitized(): void {
		$input  = array(
			'tpl_user_register_en' => '<script>bad</script>{site_name}: {username}',
			'tpl_user_register_ar' => '{site_name}: {username}',
		);
		$result = $this->admin->sanitize_alerts_settings( $input );
		// wp_kses_post strips script tags — mocked as returnArg in test.
		$this->assertArrayHasKey( 'tpl_user_register', $result );
		$this->assertArrayHasKey( 'en', $result['tpl_user_register'] );
		$this->assertArrayHasKey( 'ar', $result['tpl_user_register'] );
	}

	/**
	 * Missing toggle keys must default to 0 and missing phone to empty string.
	 */
	public function test_sanitize_alerts_missing_keys_default_to_zero(): void {
		$result = $this->admin->sanitize_alerts_settings( array() );
		$this->assertSame( 0, $result['user_register'] );
		$this->assertSame( 0, $result['wp_login'] );
		$this->assertSame( 0, $result['post_published'] );
		$this->assertSame( 0, $result['comment_posted'] );
		$this->assertSame( 0, $result['core_update'] );
		$this->assertSame( '', $result['admin_phones'] );
	}
}
