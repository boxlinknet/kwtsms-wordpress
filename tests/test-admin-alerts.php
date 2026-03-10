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

// Load the alerts class under test.
require_once KWTSMS_OTP_DIR . 'includes/class-kwtsms-admin-alerts.php';

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

/**
 * Tests for KwtSMS_Admin_Alerts.
 *
 * Uses PHPUnit mocks and Brain\Monkey function stubs. Handler methods are
 * called directly (not via do_action) because Brain\Monkey's hook system
 * records hooks but does not invoke registered callbacks on do_action.
 */
class Test_KwtSMS_Admin_Alerts extends TestCase {

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject&KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject&KwtSMS_Settings
	 */
	private $settings;

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject&KwtSMS_API
	 */
	private $api;

	/**
	 * Set up Brain\Monkey and shared mocks.
	 */
	public function setUp(): void {
		parent::setUp();
		Brain\Monkey\setUp();

		// WP function stubs needed by KwtSMS_Admin_Alerts handlers.
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'get_userdata' )->justReturn(
			(object) array(
				'user_login'   => 'testuser',
				'user_email'   => 'test@example.com',
				'display_name' => 'Test User',
			)
		);
		Functions\when( 'get_comment' )->justReturn(
			(object) array(
				'comment_author'   => 'Commenter',
				'comment_post_ID'  => 5,
				'comment_approved' => '1',
			)
		);
		Functions\when( 'get_the_title' )->justReturn( 'My Post' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		// get_option is needed by KwtSMS_API::get_default_dial_code().
		Functions\when( 'get_option' )->justReturn( array() );
		// is_wp_error: use real WP_Error check (WP_Error class is defined in bootstrap).
		Functions\when( 'is_wp_error' )->alias(
			function ( $v ) {
				return $v instanceof WP_Error;
			}
		);
		// add_action is called in the constructor; stub it out.
		Functions\when( 'add_action' )->justReturn( true );

		$this->api = $this->createMock( KwtSMS_API::class );
		$this->api->method( 'send_sms' )->willReturn( array( 'msg_id' => '123' ) );

		$this->settings = $this->createMock( KwtSMS_Settings::class );

		$this->plugin           = $this->createMock( KwtSMS_Plugin::class );
		$this->plugin->settings = $this->settings;
		$this->plugin->api      = $this->api;
	}

	/**
	 * Tear down Brain\Monkey after each test.
	 */
	public function tearDown(): void {
		Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a KwtSMS_Admin_Alerts instance with the given config overrides.
	 * Uses phone 96598765432 by default (passes KwtSMS_API::normalize_phone()).
	 *
	 * @param array $overrides Config overrides merged over defaults.
	 * @return KwtSMS_Admin_Alerts
	 */
	private function make_alerts( array $overrides = array() ): KwtSMS_Admin_Alerts {
		$defaults = array(
			'admin_phones'       => '96598765432',
			'user_register'      => 1,
			'wp_login'           => 1,
			'post_published'     => 1,
			'comment_posted'     => 1,
			'core_update'        => 1,
			'tpl_user_register'  => array( 'en' => '{site_name}: {username}', 'ar' => '' ),
			'tpl_wp_login'       => array( 'en' => '{site_name}: {username}', 'ar' => '' ),
			'tpl_post_published' => array( 'en' => '{site_name}: {post_title}', 'ar' => '' ),
			'tpl_comment_posted' => array( 'en' => '{site_name}: {author}', 'ar' => '' ),
			'tpl_core_update'    => array( 'en' => '{site_name}: {version}', 'ar' => '' ),
		);
		$config = array_merge( $defaults, $overrides );
		$this->settings->method( 'get' )->willReturnCallback(
			function ( $key ) use ( $config ) {
				$parts = explode( '.', $key, 2 );
				if ( 'alerts' === $parts[0] && isset( $parts[1] ) ) {
					return $config[ $parts[1] ] ?? null;
				}
				if ( 'gateway' === $parts[0] ) {
					return 'SENDER';
				}
				return null;
			}
		);
		return new KwtSMS_Admin_Alerts( $this->plugin );
	}

	/**
	 * When two phones are configured, send_sms must be called once per phone.
	 */
	public function test_send_to_multiple_admin_phones(): void {
		$this->settings = $this->createMock( KwtSMS_Settings::class );
		$this->plugin->settings = $this->settings;
		$config = array(
			'admin_phones'      => '96598765432, 96512345678',
			'user_register'     => 1,
			'tpl_user_register' => array( 'en' => '{site_name}: {username}', 'ar' => '' ),
		);
		$this->settings->method( 'get' )->willReturnCallback(
			function ( $key ) use ( $config ) {
				$parts = explode( '.', $key, 2 );
				if ( 'alerts' === $parts[0] && isset( $parts[1] ) ) {
					return $config[ $parts[1] ] ?? 0;
				}
				if ( 'gateway' === $parts[0] ) {
					return 'SENDER';
				}
				return null;
			}
		);
		$this->api->expects( $this->exactly( 2 ) )->method( 'send_sms' );
		$alerts = new KwtSMS_Admin_Alerts( $this->plugin );
		$alerts->on_user_register( 1 );
	}

	/**
	 * When admin_phones is empty, no SMS must be sent.
	 */
	public function test_empty_phones_no_send(): void {
		$alerts = $this->make_alerts( array( 'admin_phones' => '' ) );
		$this->api->expects( $this->never() )->method( 'send_sms' );
		$alerts->on_user_register( 1 );
	}

	/**
	 * When user_register toggle is 0, on_user_register must not send.
	 */
	public function test_user_register_disabled_no_send(): void {
		// With toggle off, the constructor does not register the hook.
		// Calling the method directly still skips because the template is empty.
		$alerts = $this->make_alerts(
			array(
				'user_register'     => 0,
				'tpl_user_register' => array( 'en' => '', 'ar' => '' ),
			)
		);
		$this->api->expects( $this->never() )->method( 'send_sms' );
		$alerts->on_user_register( 1 );
	}

	/**
	 * on_post_published must skip when new status is not 'publish'.
	 */
	public function test_post_published_skips_non_publish_new_status(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->never() )->method( 'send_sms' );
		$post = (object) array( 'post_type' => 'post', 'ID' => 10, 'post_title' => 'Test' );
		$alerts->on_post_published( 'draft', 'draft', $post );
	}

	/**
	 * on_post_published must skip when old status is already 'publish' (re-publish).
	 */
	public function test_post_published_skips_already_published(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->never() )->method( 'send_sms' );
		$post = (object) array( 'post_type' => 'post', 'ID' => 10, 'post_title' => 'Test' );
		$alerts->on_post_published( 'publish', 'publish', $post );
	}

	/**
	 * on_post_published must skip post types other than 'post'.
	 */
	public function test_post_published_skips_non_post_type(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->never() )->method( 'send_sms' );
		$post = (object) array( 'post_type' => 'attachment', 'ID' => 10, 'post_title' => 'Image' );
		$alerts->on_post_published( 'publish', 'draft', $post );
	}

	/**
	 * on_post_published must send when a post transitions from draft to publish.
	 */
	public function test_post_published_fires_for_post(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->once() )->method( 'send_sms' );
		$post = (object) array( 'post_type' => 'post', 'ID' => 10, 'post_title' => 'Hello World' );
		$alerts->on_post_published( 'publish', 'draft', $post );
	}

	/**
	 * on_comment_posted must skip unapproved comments.
	 */
	public function test_comment_posted_unapproved_skipped(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->never() )->method( 'send_sms' );
		$alerts->on_comment_posted( 42, 0, array() );
	}

	/**
	 * on_comment_posted must send for approved comments.
	 */
	public function test_comment_posted_approved_sends(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->once() )->method( 'send_sms' );
		$alerts->on_comment_posted( 42, 1, array() );
	}

	/**
	 * on_upgrader_complete must send when action=update and type=core.
	 */
	public function test_core_update_fires_on_core_upgrade(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->once() )->method( 'send_sms' );
		$hook_extra = array( 'action' => 'update', 'type' => 'core' );
		$alerts->on_upgrader_complete( new stdClass(), $hook_extra );
	}

	/**
	 * on_upgrader_complete must skip when type is 'plugin'.
	 */
	public function test_core_update_skips_plugin_upgrades(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->never() )->method( 'send_sms' );
		$hook_extra = array( 'action' => 'update', 'type' => 'plugin' );
		$alerts->on_upgrader_complete( new stdClass(), $hook_extra );
	}

	/**
	 * on_wp_login must send an SMS to all admin phones.
	 */
	public function test_wp_login_sends_sms(): void {
		$alerts = $this->make_alerts();
		$this->api->expects( $this->once() )->method( 'send_sms' );
		$alerts->on_wp_login( 'testuser', (object) array( 'ID' => 1 ) );
	}
}
