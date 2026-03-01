<?php
/**
 * Tests for KwtSMS_Integrations loader and KwtSMS_Woo integration.
 *
 * These tests validate structure and hook registration without requiring a
 * full WooCommerce installation. WC-specific classes are stubbed where needed.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_Woo
 */
class Test_KwtSMS_Woo extends TestCase {

	/**
	 * Hooks captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	/**
	 * Hooks captured during add_filter calls.
	 *
	 * @var string[]
	 */
	private $registered_filters = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_actions = array();
		$this->registered_filters = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		// Capture add_action / add_filter calls into instance arrays.
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
	// Class / constant existence
	// =========================================================================

	public function test_woo_class_exists() {
		$this->assertTrue( class_exists( 'KwtSMS_Woo' ) );
	}

	public function test_integrations_class_exists() {
		$this->assertTrue( class_exists( 'KwtSMS_Integrations' ) );
	}

	public function test_checkout_otp_prefix_constant() {
		$this->assertSame( 'kwtsms_checkout_otp_', KwtSMS_Woo::CHECKOUT_OTP_PREFIX );
	}

	// =========================================================================
	// Integration loader file structure
	// =========================================================================

	public function test_integrations_loader_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/class-kwtsms-integrations.php' );
	}

	public function test_woo_integration_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/integrations/class-kwtsms-woo.php' );
	}

	// =========================================================================
	// KwtSMS_Woo hook registration via constructor
	// =========================================================================

	public function test_woo_constructor_registers_order_status_hook() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_order_status_changed', $this->registered_actions );
	}

	public function test_woo_constructor_registers_wc_register_form_action() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_register_form', $this->registered_actions );
	}

	public function test_woo_constructor_registers_wc_created_customer_action() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_created_customer', $this->registered_actions );
	}

	public function test_woo_constructor_registers_wc_registration_errors_filter() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_registration_errors', $this->registered_filters );
	}

	public function test_woo_constructor_registers_checkout_otp_hooks_when_enabled() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 1 ) ) );

		$this->assertContains( 'woocommerce_after_order_notes', $this->registered_actions );
		$this->assertContains( 'woocommerce_checkout_process', $this->registered_actions );
		$this->assertContains( 'woocommerce_checkout_order_created', $this->registered_actions );
	}

	public function test_woo_constructor_skips_checkout_otp_hooks_when_disabled() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 0 ) ) );

		$this->assertNotContains( 'woocommerce_checkout_process', $this->registered_actions );
		$this->assertNotContains( 'woocommerce_after_order_notes', $this->registered_actions );
		$this->assertNotContains( 'woocommerce_checkout_order_created', $this->registered_actions );
	}

	// =========================================================================
	// Integration loader — boot() logic
	// =========================================================================

	public function test_integrations_loader_boots_woo_when_woocommerce_class_exists() {
		// Ensure WooCommerce class stub is present to trigger boot().
		if ( ! class_exists( 'WooCommerce' ) ) {
			// phpcs:ignore
			eval( 'class WooCommerce {}' );
		}

		$plugin = $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 0 ) );

		$loader = new KwtSMS_Integrations( $plugin );
		$loader->boot();

		// Order status hook + register_form + created_customer = 3 minimum.
		$this->assertGreaterThanOrEqual( 3, count( $this->registered_actions ) );
	}

	public function test_integrations_loader_stores_plugin_reference() {
		$plugin = $this->make_plugin_stub( array( 'integrations.woo_checkout_otp' => 0 ) );
		// Instantiation without error is sufficient — the constructor stores $plugin
		// and schedules boot() on plugins_loaded. The add_action call is captured.
		$loader = new KwtSMS_Integrations( $plugin );

		$this->assertContains( 'plugins_loaded', $this->registered_actions );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a KwtSMS_Plugin mock whose settings->get() returns from a map.
	 *
	 * Uses getMockBuilder with disableOriginalConstructor() so that PHPUnit
	 * creates a proper KwtSMS_Plugin instance without executing the real
	 * private constructor (which requires a full WordPress environment).
	 *
	 * @param array $settings_map Key (dot-notation) → value overrides.
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub( array $settings_map = array() ) {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get', 'get_all_templates' ) )
			->getMock();

		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) use ( $settings_map ) {
				return array_key_exists( $key, $settings_map ) ? $settings_map[ $key ] : $default;
			}
		);

		$settings->method( 'get_all_templates' )->willReturn( array() );

		/** @var KwtSMS_Plugin $plugin */
		$plugin = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->getMock();

		$plugin->settings = $settings;

		return $plugin;
	}
}

/**
 * Class Test_KwtSMS_CF7
 *
 * Tests for the Contact Form 7 integration class.
 */
class Test_KwtSMS_CF7 extends TestCase {

	/**
	 * Hooks captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_actions = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );

		Functions\when( 'add_action' )->alias( function ( $hook ) {
			$this->registered_actions[] = $hook;
			return null;
		} );
		Functions\when( 'add_filter' )->justReturn( null );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Class / file existence
	// =========================================================================

	public function test_class_exists() {
		$this->assertTrue( class_exists( 'KwtSMS_CF7' ) );
	}

	public function test_integration_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/integrations/class-kwtsms-cf7.php' );
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	public function test_constructor_registers_wpcf7_mail_sent_hook() {
		$plugin = $this->make_plugin_stub();
		new KwtSMS_CF7( $plugin );

		$this->assertContains( 'wpcf7_mail_sent', $this->registered_actions );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a minimal KwtSMS_Plugin stub for CF7 tests.
	 *
	 * Returns 1 for the `integrations.cf7_enabled` key so that the constructor
	 * does not bail early, allowing hook registration tests to pass.
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub() {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) {
				// Return enabled=1 so the constructor does not bail early.
				if ( 'integrations.cf7_enabled' === $key ) {
					return 1;
				}
				return $default ?? '';
			}
		);

		/** @var KwtSMS_Plugin $plugin */
		$plugin           = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->getMock();
		$plugin->settings = $settings;

		return $plugin;
	}
}

/**
 * Class Test_KwtSMS_WPForms
 *
 * Tests for the WPForms integration class.
 */
class Test_KwtSMS_WPForms extends TestCase {

	/**
	 * Hooks captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_actions = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );

		Functions\when( 'add_action' )->alias( function ( $hook ) {
			$this->registered_actions[] = $hook;
			return null;
		} );
		Functions\when( 'add_filter' )->justReturn( null );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Class / file existence
	// =========================================================================

	public function test_class_exists() {
		$this->assertTrue( class_exists( 'KwtSMS_WPForms' ) );
	}

	public function test_integration_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/integrations/class-kwtsms-wpforms.php' );
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	public function test_constructor_registers_wpforms_process_complete_hook() {
		$plugin = $this->make_plugin_stub();
		new KwtSMS_WPForms( $plugin );

		$this->assertContains( 'wpforms_process_complete', $this->registered_actions );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a minimal KwtSMS_Plugin stub for WPForms tests.
	 *
	 * Returns 1 for the `integrations.wpforms_enabled` key so that the constructor
	 * does not bail early, allowing hook registration tests to pass.
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub() {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) {
				// Return enabled=1 so the constructor does not bail early.
				if ( 'integrations.wpforms_enabled' === $key ) {
					return 1;
				}
				return $default ?? '';
			}
		);

		/** @var KwtSMS_Plugin $plugin */
		$plugin           = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->getMock();
		$plugin->settings = $settings;

		return $plugin;
	}
}

/**
 * Class Test_KwtSMS_Elementor
 *
 * Tests for the Elementor Pro Forms integration class.
 */
class Test_KwtSMS_Elementor extends TestCase {

	/**
	 * Hooks captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_actions = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );

		Functions\when( 'add_action' )->alias( function ( $hook ) {
			$this->registered_actions[] = $hook;
			return null;
		} );
		Functions\when( 'add_filter' )->justReturn( null );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Class / file existence
	// =========================================================================

	public function test_class_exists() {
		$this->assertTrue( class_exists( 'KwtSMS_Elementor' ) );
	}

	public function test_integration_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/integrations/class-kwtsms-elementor.php' );
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	public function test_constructor_registers_elementor_forms_hook() {
		$plugin = $this->make_plugin_stub();
		new KwtSMS_Elementor( $plugin );

		$this->assertContains( 'elementor_pro/forms/new_record', $this->registered_actions );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a minimal KwtSMS_Plugin stub for Elementor tests.
	 *
	 * Returns 1 for the `integrations.elementor_enabled` key so that the
	 * constructor does not bail early, allowing hook registration tests to pass.
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub() {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) {
				// Return enabled=1 so the constructor does not bail early.
				if ( 'integrations.elementor_enabled' === $key ) {
					return 1;
				}
				return $default ?? '';
			}
		);

		/** @var KwtSMS_Plugin $plugin */
		$plugin           = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->getMock();
		$plugin->settings = $settings;

		return $plugin;
	}
}

/**
 * Class Test_KwtSMS_Integrations_Settings
 *
 * Tests for the kwtsms_otp_integrations settings schema.
 */
class Test_KwtSMS_Integrations_Settings extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_integrations_defaults_contain_all_expected_keys() {
		$defaults = KwtSMS_Settings::DEFAULTS['integrations'];
		$this->assertArrayHasKey( 'woo_enabled',            $defaults );
		$this->assertArrayHasKey( 'cf7_enabled',            $defaults );
		$this->assertArrayHasKey( 'wpforms_enabled',        $defaults );
		$this->assertArrayHasKey( 'elementor_enabled',      $defaults );
		$this->assertArrayHasKey( 'woo_checkout_otp',       $defaults );
		$this->assertArrayHasKey( 'woo_processing',         $defaults );
		$this->assertArrayHasKey( 'woo_shipped',            $defaults );
		$this->assertArrayHasKey( 'woo_completed',          $defaults );
		$this->assertArrayHasKey( 'woo_cancelled',          $defaults );
		$this->assertArrayHasKey( 'cf7_confirmation',       $defaults );
		$this->assertArrayHasKey( 'wpforms_confirmation',   $defaults );
		$this->assertArrayHasKey( 'elementor_confirmation', $defaults );
	}

	public function test_integration_template_keys_have_en_ar_enabled() {
		$template_keys = array(
			'woo_processing', 'woo_shipped', 'woo_completed', 'woo_cancelled',
			'cf7_confirmation', 'wpforms_confirmation', 'elementor_confirmation',
		);
		$defaults = KwtSMS_Settings::DEFAULTS['integrations'];
		foreach ( $template_keys as $key ) {
			$this->assertArrayHasKey( 'en',      $defaults[ $key ], "Missing 'en' in $key" );
			$this->assertArrayHasKey( 'ar',      $defaults[ $key ], "Missing 'ar' in $key" );
			$this->assertArrayHasKey( 'enabled', $defaults[ $key ], "Missing 'enabled' in $key" );
			$this->assertNotEmpty( $defaults[ $key ]['en'],  "Empty 'en' in $key" );
			$this->assertNotEmpty( $defaults[ $key ]['ar'],  "Empty 'ar' in $key" );
		}
	}

	public function test_get_all_integration_templates_returns_merged_array() {
		$settings  = new KwtSMS_Settings();
		$templates = $settings->get_all_integration_templates();
		$this->assertIsArray( $templates );
		$this->assertCount( 7, $templates );
		$this->assertArrayHasKey( 'woo_processing', $templates );
		$this->assertArrayHasKey( 'cf7_confirmation', $templates );
		$this->assertArrayHasKey( 'elementor_confirmation', $templates );
	}

	public function test_integrations_settings_get_returns_default_woo_enabled() {
		$settings = new KwtSMS_Settings();
		$enabled  = $settings->get( 'integrations.woo_enabled', null );
		$this->assertSame( 1, $enabled );
	}
}

/**
 * Class Test_KwtSMS_Integrations_Page
 *
 * Tests for the rewritten Integrations admin view (page-integrations.php).
 * All assertions are static file-content checks — no WordPress runtime needed.
 */
class Test_KwtSMS_Integrations_Page extends TestCase {

	/**
	 * Absolute path to the view file under test.
	 *
	 * @return string
	 */
	private function view_path(): string {
		return dirname( __DIR__ ) . '/admin/views/page-integrations.php';
	}

	// =========================================================================
	// File existence
	// =========================================================================

	public function test_integrations_page_file_exists() {
		$this->assertFileExists( $this->view_path() );
	}

	// =========================================================================
	// Tab navigation structure
	// =========================================================================

	public function test_integrations_page_has_nav_tab_wrapper() {
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'nav-tab-wrapper', $src );
	}

	// =========================================================================
	// WooCommerce template field names
	// =========================================================================

	public function test_integrations_page_has_woo_template_fields() {
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'woo_processing', $src );
		$this->assertStringContainsString( 'woo_shipped', $src );
		$this->assertStringContainsString( 'woo_completed', $src );
		$this->assertStringContainsString( 'woo_cancelled', $src );
	}

	// =========================================================================
	// CF7 template field name
	// =========================================================================

	public function test_integrations_page_has_cf7_template_field() {
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'cf7_confirmation', $src );
	}

	// =========================================================================
	// WPForms template field name
	// =========================================================================

	public function test_integrations_page_has_wpforms_template_field() {
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'wpforms_confirmation', $src );
	}

	// =========================================================================
	// Elementor template field name
	// =========================================================================

	public function test_integrations_page_has_elementor_template_field() {
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'elementor_confirmation', $src );
	}

	// =========================================================================
	// Single-form constraint
	// =========================================================================

	public function test_integrations_page_uses_single_form() {
		$src = file_get_contents( $this->view_path() );
		// The view must contain exactly one opening <form tag so that all
		// tab inputs are submitted together, preventing data loss.
		$this->assertSame( 1, substr_count( $src, '<form ' ) );
	}

	// =========================================================================
	// Correct settings group
	// =========================================================================

	public function test_integrations_page_uses_correct_settings_group() {
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'kwtsms_otp_integrations_group', $src );
	}
}

/**
 * Class Test_KwtSMS_Integration_Wiring
 *
 * Tests that all 4 integration classes:
 *  - Respect their top-level enable flag (no hooks registered when disabled).
 *  - Use saved templates from settings instead of hardcoded strings.
 *  - Respect the per-template `enabled` sub-key.
 *  - Replace placeholders at runtime.
 */
class Test_KwtSMS_Integration_Wiring extends TestCase {

	/**
	 * Hooks captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	/**
	 * Hooks captured during add_filter calls.
	 *
	 * @var string[]
	 */
	private $registered_filters = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_actions = array();
		$this->registered_filters = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'get_bloginfo' )->alias( function ( $show ) {
			return 'TestSite' === $show || 'name' === $show ? 'TestSite' : '';
		} );
		Functions\when( 'is_rtl' )->justReturn( false );

		// Capture add_action / add_filter calls into instance arrays.
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
	// Test 1 — WooCommerce: disabled flag prevents hook registration
	// =========================================================================

	/**
	 * When `integrations.woo_enabled` is 0, the constructor must bail early and
	 * register no WooCommerce hooks at all.
	 */
	public function test_woo_disabled_flag_prevents_hook_registration() {
		new KwtSMS_Woo( $this->make_plugin_stub(
			array( 'integrations.woo_enabled' => 0 ),
			array()
		) );

		$this->assertNotContains( 'woocommerce_order_status_changed', $this->registered_actions );
		$this->assertNotContains( 'woocommerce_register_form', $this->registered_actions );
		$this->assertNotContains( 'woocommerce_created_customer', $this->registered_actions );
	}

	// =========================================================================
	// Test 2 — WooCommerce: build_order_message uses saved template
	// =========================================================================

	/**
	 * When a custom `en` template is in settings, build_order_message() (via
	 * on_order_status_changed) must produce a message using that template with
	 * placeholders replaced.
	 *
	 * We call on_order_status_changed() directly with a fake WC_Order stub so
	 * we can inspect the SMS that is dispatched via api->send_sms().
	 */
	public function test_woo_build_message_uses_saved_template() {
		$custom_template = array(
			'enabled' => 1,
			'en'      => 'Hello from {site_name}: order #{order_id} total {total}',
			'ar'      => 'AR placeholder',
		);

		$integration_templates = array(
			'woo_processing' => $custom_template,
			'woo_shipped'    => array( 'enabled' => 1, 'en' => '', 'ar' => '' ),
			'woo_completed'  => array( 'enabled' => 1, 'en' => '', 'ar' => '' ),
			'woo_cancelled'  => array( 'enabled' => 1, 'en' => '', 'ar' => '' ),
		);

		$sent_message = null;

		// Build a plugin stub that records the send_sms call.
		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_message ) {
				$sent_message = $message;
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.woo_enabled'     => 1,
				'integrations.woo_checkout_otp' => 0,
				'gateway.sender_id'            => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		$woo = new KwtSMS_Woo( $plugin );

		// Stub WC_Order.
		$order = $this->getMockBuilder( 'WC_Order' )
			->addMethods( array(
				'get_customer_id', 'get_billing_phone',
				'get_order_number', 'get_total',
				'get_billing_first_name', 'get_billing_last_name',
			) )
			->getMock();
		$order->method( 'get_customer_id' )->willReturn( 0 );
		$order->method( 'get_billing_phone' )->willReturn( '' );
		$order->method( 'get_order_number' )->willReturn( '42' );
		$order->method( 'get_total' )->willReturn( 100.00 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Jane' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Doe' );

		// Stub WC functions used inside on_order_status_changed.
		Functions\when( 'wc_price' )->alias( function ( $amount ) { return (string) $amount; } );

		// Inject a known phone directly via get_user_meta stub: not possible since
		// get_user_meta is already mocked to return ''. Instead stub billing phone.
		Functions\when( 'get_user_meta' )->justReturn( '96599220322' );

		$woo->on_order_status_changed( 42, 'pending', 'processing', $order );

		$this->assertNotNull( $sent_message, 'send_sms was not called — no message was sent.' );
		$this->assertStringContainsString( 'TestSite', $sent_message );
		$this->assertStringContainsString( '42', $sent_message );
		$this->assertStringNotContainsString( '{site_name}', $sent_message );
		$this->assertStringNotContainsString( '{order_id}', $sent_message );
	}

	// =========================================================================
	// Test 3 — WooCommerce: disabled per-template flag skips SMS
	// =========================================================================

	/**
	 * When `woo_processing.enabled` is 0, build_order_message() must return an
	 * empty string and no SMS must be sent.
	 */
	public function test_woo_template_disabled_skips_sms() {
		$integration_templates = array(
			'woo_processing' => array(
				'enabled' => 0,
				'en'      => 'Should not be sent',
				'ar'      => '',
			),
			'woo_shipped'    => array( 'enabled' => 1, 'en' => '', 'ar' => '' ),
			'woo_completed'  => array( 'enabled' => 1, 'en' => '', 'ar' => '' ),
			'woo_cancelled'  => array( 'enabled' => 1, 'en' => '', 'ar' => '' ),
		);

		$send_was_called = false;

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function () use ( &$send_was_called ) {
				$send_was_called = true;
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.woo_enabled'      => 1,
				'integrations.woo_checkout_otp' => 0,
				'gateway.sender_id'             => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		$woo = new KwtSMS_Woo( $plugin );

		$order = $this->getMockBuilder( 'WC_Order' )
			->addMethods( array(
				'get_customer_id', 'get_billing_phone',
				'get_order_number', 'get_total',
				'get_billing_first_name', 'get_billing_last_name',
			) )
			->getMock();
		$order->method( 'get_customer_id' )->willReturn( 0 );
		$order->method( 'get_billing_phone' )->willReturn( '' );
		$order->method( 'get_order_number' )->willReturn( '99' );
		$order->method( 'get_total' )->willReturn( 50.00 );
		$order->method( 'get_billing_first_name' )->willReturn( 'John' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Doe' );

		Functions\when( 'wc_price' )->alias( function ( $amount ) { return (string) $amount; } );
		Functions\when( 'get_user_meta' )->justReturn( '96599220322' );

		$woo->on_order_status_changed( 99, 'pending', 'processing', $order );

		$this->assertFalse( $send_was_called, 'send_sms should not be called when template is disabled.' );
	}

	// =========================================================================
	// Test 4 — CF7: disabled flag prevents hook registration
	// =========================================================================

	/**
	 * When `integrations.cf7_enabled` is 0, the CF7 constructor must bail early
	 * and the wpcf7_mail_sent hook must not be registered.
	 */
	public function test_cf7_disabled_flag_prevents_sending() {
		new KwtSMS_CF7( $this->make_plugin_stub(
			array( 'integrations.cf7_enabled' => 0 ),
			array()
		) );

		$this->assertNotContains( 'wpcf7_mail_sent', $this->registered_actions );
	}

	// =========================================================================
	// Test 5 — CF7: uses saved template with {form_name} replaced
	// =========================================================================

	/**
	 * When a custom `en` template is in cf7_confirmation settings,
	 * send_confirmation_sms() must produce a message with {site_name} and
	 * {form_name} replaced.
	 *
	 * Brain\Monkey cannot stub the static WPCF7_Submission::get_instance() call,
	 * so we use an anonymous subclass that overrides the protected
	 * get_submission_phone() method to return a fixed phone string directly.
	 */
	public function test_cf7_uses_saved_template() {
		$integration_templates = array(
			'cf7_confirmation' => array(
				'enabled' => 1,
				'en'      => 'Hi! {site_name} got your "{form_name}" form.',
				'ar'      => '',
			),
		);

		$sent_message = null;

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_message ) {
				$sent_message = $message;
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.cf7_enabled' => 1,
				'gateway.sender_id'        => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		// Anonymous subclass overrides get_submission_phone() to bypass the
		// unmockable WPCF7_Submission::get_instance() static call.
		$cf7 = new class( $plugin ) extends KwtSMS_CF7 {
			protected function get_submission_phone() {
				return '96599220322';
			}
		};

		// Stub the CF7 form object.
		$cf7_form = $this->getMockBuilder( 'WPCF7_ContactForm' )
			->addMethods( array( 'title' ) )
			->getMock();
		$cf7_form->method( 'title' )->willReturn( 'My Enquiry Form' );

		Functions\when( 'is_a' )->alias( function ( $obj, $class ) {
			return $obj instanceof $class;
		} );

		// Stub KwtSMS_API::normalize_phone to pass through the phone unchanged.
		Functions\when( 'KwtSMS_API::normalize_phone' )->justReturn( '96599220322' );

		$cf7->send_confirmation_sms( $cf7_form );

		$this->assertNotNull( $sent_message, 'send_sms was not called — no message was sent.' );
		$this->assertStringContainsString( 'TestSite', $sent_message );
		$this->assertStringContainsString( 'My Enquiry Form', $sent_message );
		$this->assertStringNotContainsString( '{site_name}', $sent_message );
		$this->assertStringNotContainsString( '{form_name}', $sent_message );
	}

	// =========================================================================
	// Test 6 — WPForms: disabled flag prevents hook registration
	// =========================================================================

	/**
	 * When `integrations.wpforms_enabled` is 0, the WPForms constructor must bail
	 * early and the wpforms_process_complete hook must not be registered.
	 */
	public function test_wpforms_disabled_flag_prevents_sending() {
		new KwtSMS_WPForms( $this->make_plugin_stub(
			array( 'integrations.wpforms_enabled' => 0 ),
			array()
		) );

		$this->assertNotContains( 'wpforms_process_complete', $this->registered_actions );
	}

	// =========================================================================
	// Test 7 — Elementor: disabled flag prevents hook registration
	// =========================================================================

	/**
	 * When `integrations.elementor_enabled` is 0, the Elementor constructor must
	 * bail early and the elementor_pro/forms/new_record hook must not be registered.
	 */
	public function test_elementor_disabled_flag_prevents_sending() {
		new KwtSMS_Elementor( $this->make_plugin_stub(
			array( 'integrations.elementor_enabled' => 0 ),
			array()
		) );

		$this->assertNotContains( 'elementor_pro/forms/new_record', $this->registered_actions );
	}

	// =========================================================================
	// Test 8 — WPForms: uses saved template with placeholders replaced
	// =========================================================================

	/**
	 * When a custom `en` template is in wpforms_confirmation settings,
	 * send_confirmation_sms() must produce a message with {site_name} and
	 * {form_name} replaced.
	 *
	 * WPForms' send_confirmation_sms() accepts plain PHP arrays, so no
	 * unmockable static calls are required — we pass a minimal $fields array
	 * whose type is 'phone' and a $form_data array with a form_title.
	 */
	public function test_wpforms_uses_saved_template() {
		$integration_templates = array(
			'wpforms_confirmation' => array(
				'enabled' => 1,
				'en'      => 'Site: {site_name} | Form: {form_name}',
				'ar'      => '',
			),
		);

		$sent_message = null;

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_message ) {
				$sent_message = $message;
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.wpforms_enabled' => 1,
				'gateway.sender_id'            => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		$wpforms = new KwtSMS_WPForms( $plugin );

		// Minimal WPForms submission data.
		$fields = array(
			array( 'type' => 'phone', 'name' => 'Phone', 'value' => '96599220322' ),
		);
		$form_data = array(
			'settings' => array( 'form_title' => 'My Contact Form' ),
		);

		// Stub KwtSMS_API::normalize_phone to pass through the phone unchanged.
		Functions\when( 'KwtSMS_API::normalize_phone' )->justReturn( '96599220322' );

		$wpforms->send_confirmation_sms( $fields, array(), $form_data, 0 );

		$this->assertNotNull( $sent_message, 'send_sms was not called — no message was sent.' );
		$this->assertStringContainsString( 'TestSite', $sent_message );
		$this->assertStringContainsString( 'My Contact Form', $sent_message );
		$this->assertStringNotContainsString( '{site_name}', $sent_message );
		$this->assertStringNotContainsString( '{form_name}', $sent_message );
	}

	// =========================================================================
	// Test 9 — CF7: per-template disabled flag suppresses SMS
	// =========================================================================

	/**
	 * When cf7_enabled=1 but cf7_confirmation.enabled=0, the hook is registered
	 * but send_confirmation_sms() must bail before calling send_sms().
	 *
	 * Uses the same anonymous subclass trick as test_cf7_uses_saved_template to
	 * bypass the unmockable WPCF7_Submission::get_instance() static call.
	 */
	public function test_cf7_template_disabled_skips_sms() {
		$integration_templates = array(
			'cf7_confirmation' => array(
				'enabled' => 0,
				'en'      => 'Should not be sent',
				'ar'      => '',
			),
		);

		$send_was_called = false;

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function () use ( &$send_was_called ) {
				$send_was_called = true;
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.cf7_enabled' => 1,
				'gateway.sender_id'        => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		// Anonymous subclass overrides get_submission_phone() to bypass the
		// unmockable WPCF7_Submission::get_instance() static call.
		$cf7 = new class( $plugin ) extends KwtSMS_CF7 {
			protected function get_submission_phone() {
				return '96599220322';
			}
		};

		// The hook must still be registered (cf7_enabled=1).
		$this->assertContains( 'wpcf7_mail_sent', $this->registered_actions );

		$cf7_form = $this->getMockBuilder( 'WPCF7_ContactForm' )
			->addMethods( array( 'title' ) )
			->getMock();
		$cf7_form->method( 'title' )->willReturn( 'My Form' );

		Functions\when( 'is_a' )->alias( function ( $obj, $class ) {
			return $obj instanceof $class;
		} );

		Functions\when( 'KwtSMS_API::normalize_phone' )->justReturn( '96599220322' );

		$cf7->send_confirmation_sms( $cf7_form );

		$this->assertFalse( $send_was_called, 'send_sms must not be called when cf7_confirmation.enabled=0.' );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a KwtSMS_Plugin mock with configurable settings and integration templates.
	 *
	 * The settings stub supports:
	 *   - get($key, $default) — returns value from $settings_map or $default
	 *   - get_all_integration_templates() — returns $integration_templates
	 *
	 * Optionally accepts a pre-built API mock; otherwise creates a no-op stub.
	 *
	 * @param array       $settings_map          Dot-notation key → value map.
	 * @param array       $integration_templates Return value for get_all_integration_templates().
	 * @param object|null $api_mock              Optional API mock (stdClass with send_sms method).
	 *
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub(
		array $settings_map = array(),
		array $integration_templates = array(),
		$api_mock = null
	) {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get', 'get_all_templates', 'get_all_integration_templates' ) )
			->getMock();

		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) use ( $settings_map ) {
				return array_key_exists( $key, $settings_map ) ? $settings_map[ $key ] : $default;
			}
		);

		$settings->method( 'get_all_templates' )->willReturn( array() );
		$settings->method( 'get_all_integration_templates' )->willReturn( $integration_templates );

		if ( null === $api_mock ) {
			$api_mock = $this->getMockBuilder( 'stdClass' )
				->addMethods( array( 'send_sms' ) )
				->getMock();
			$api_mock->method( 'send_sms' )->willReturn( null );
		}

		/** @var KwtSMS_Plugin $plugin */
		$plugin = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->getMock();

		$plugin->settings = $settings;
		$plugin->api      = $api_mock;

		return $plugin;
	}
}
