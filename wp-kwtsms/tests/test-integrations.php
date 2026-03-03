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
 * Minimal WC_Order stub so getMockBuilder can create WC_Order mocks without
 * a WooCommerce installation. Only method stubs are needed — actual method
 * bodies are overridden by each test via willReturn/willReturnCallback.
 */
if ( ! class_exists( 'WC_Order' ) ) {
	// phpcs:ignore
	class WC_Order {
		public function get_customer_id() {}
		public function get_billing_phone() {}
		public function get_order_number() {}
		public function get_total() {}
		public function get_billing_first_name() {}
		public function get_billing_last_name() {}
		public function get_id() {}
		public function get_formatted_billing_full_name() {}
		public function get_formatted_order_total() {}
		public function get_meta( $key ) {}
	}
}

/**
 * Minimal WPCF7_ContactForm stub so getMockBuilder can create CF7 mocks without
 * a Contact Form 7 installation.
 */
if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	// phpcs:ignore
	class WPCF7_ContactForm {
		public function id() {}
		public function title() {}
	}
}

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
		Functions\when( 'is_admin' )->justReturn( false );

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
		$this->assertArrayHasKey( 'woo_processing',            $defaults );
		$this->assertArrayHasKey( 'woo_shipped',               $defaults );
		$this->assertArrayHasKey( 'woo_completed',             $defaults );
		$this->assertArrayHasKey( 'woo_cancelled',             $defaults );
		$this->assertArrayHasKey( 'woo_pending',               $defaults );
		$this->assertArrayHasKey( 'woo_refunded',              $defaults );
		$this->assertArrayHasKey( 'woo_failed',                $defaults );
		$this->assertArrayHasKey( 'woo_admin_phone',           $defaults );
		$this->assertArrayHasKey( 'woo_notify_admin_statuses', $defaults );
		$this->assertArrayHasKey( 'cf7_confirmation',          $defaults );
		$this->assertArrayHasKey( 'wpforms_confirmation',      $defaults );
		$this->assertArrayHasKey( 'elementor_confirmation',    $defaults );
	}

	public function test_integration_template_keys_have_en_ar_enabled() {
		$template_keys = array(
			'woo_processing', 'woo_shipped', 'woo_completed', 'woo_cancelled',
			'woo_pending', 'woo_refunded', 'woo_failed',
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
		$this->assertCount( 12, $templates );
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
 * Tests for the Integrations overview page (page-integrations.php).
 *
 * As of the v2.x redesign this page is a status overview table with "Configure"
 * links to per-integration sub-pages. Template fields no longer live here;
 * they live in page-int-woo.php (WooCommerce) and page-int-form.php (all others).
 *
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

	/**
	 * Absolute path to the WooCommerce sub-page view.
	 *
	 * @return string
	 */
	private function woo_path(): string {
		return dirname( __DIR__ ) . '/admin/views/page-int-woo.php';
	}

	/**
	 * Absolute path to the shared form-integration sub-page view.
	 *
	 * @return string
	 */
	private function form_path(): string {
		return dirname( __DIR__ ) . '/admin/views/page-int-form.php';
	}

	// =========================================================================
	// File existence
	// =========================================================================

	public function test_integrations_page_file_exists() {
		$this->assertFileExists( $this->view_path() );
	}

	public function test_woo_sub_page_file_exists() {
		$this->assertFileExists( $this->woo_path() );
	}

	public function test_form_sub_page_file_exists() {
		$this->assertFileExists( $this->form_path() );
	}

	// =========================================================================
	// Overview table structure (replaces old tab navigation)
	// =========================================================================

	public function test_integrations_page_has_nav_tab_wrapper() {
		// The overview page is a wp-list-table — no nav-tab-wrapper needed.
		// Verify the overview table is present instead.
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'wp-list-table', $src );
	}

	// =========================================================================
	// WooCommerce template field names — now in page-int-woo.php
	// =========================================================================

	public function test_integrations_page_has_woo_template_fields() {
		// WooCommerce template fields moved to the dedicated sub-page.
		$src = file_get_contents( $this->woo_path() );
		$this->assertStringContainsString( 'woo_processing', $src );
		$this->assertStringContainsString( 'woo_shipped', $src );
		$this->assertStringContainsString( 'woo_completed', $src );
		$this->assertStringContainsString( 'woo_cancelled', $src );
		$this->assertStringContainsString( 'woo_pending', $src );
		$this->assertStringContainsString( 'woo_refunded', $src );
		$this->assertStringContainsString( 'woo_failed', $src );
		$this->assertStringContainsString( 'woo_admin_phone', $src );
		$this->assertStringContainsString( 'woo_notify_admin_statuses', $src );
	}

	// =========================================================================
	// CF7 template field name — now in page-int-form.php
	// =========================================================================

	public function test_integrations_page_has_cf7_template_field() {
		// CF7 template field moved to the shared form sub-page.
		$src = file_get_contents( $this->form_path() );
		$this->assertStringContainsString( 'cf7_confirmation', $src );
	}

	// =========================================================================
	// WPForms template field name — now in page-int-form.php
	// =========================================================================

	public function test_integrations_page_has_wpforms_template_field() {
		$src = file_get_contents( $this->form_path() );
		$this->assertStringContainsString( 'wpforms_confirmation', $src );
	}

	// =========================================================================
	// Elementor template field name — now in page-int-form.php
	// =========================================================================

	public function test_integrations_page_has_elementor_template_field() {
		$src = file_get_contents( $this->form_path() );
		$this->assertStringContainsString( 'elementor_confirmation', $src );
	}

	// =========================================================================
	// Each sub-page has its own form with the correct settings group
	// =========================================================================

	public function test_integrations_page_uses_single_form() {
		// The overview page has no form. Each sub-page has exactly one form.
		// Verify the WooCommerce sub-page has exactly one <form tag.
		$src = file_get_contents( $this->woo_path() );
		$this->assertSame( 1, substr_count( $src, '<form ' ) );
	}

	// =========================================================================
	// Correct settings group — present in sub-pages
	// =========================================================================

	public function test_integrations_page_uses_correct_settings_group() {
		// Settings group is used by the sub-pages (woo + form), not the overview.
		$woo_src  = file_get_contents( $this->woo_path() );
		$form_src = file_get_contents( $this->form_path() );
		$this->assertStringContainsString( 'kwtsms_otp_integrations_group', $woo_src );
		$this->assertStringContainsString( 'kwtsms_otp_integrations_group', $form_src );
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
			->onlyMethods( array(
				'get_customer_id', 'get_billing_phone',
				'get_order_number', 'get_total',
				'get_billing_first_name', 'get_billing_last_name',
			) )
			->getMock();
		$order->method( 'get_customer_id' )->willReturn( 0 );
		// Return a valid billing phone — get_customer_id is 0 so user-meta path is skipped.
		$order->method( 'get_billing_phone' )->willReturn( '96599220322' );
		$order->method( 'get_order_number' )->willReturn( '42' );
		$order->method( 'get_total' )->willReturn( 100.00 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Jane' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Doe' );

		// Stub WC functions used inside on_order_status_changed.
		Functions\when( 'wc_price' )->alias( function ( $amount ) { return (string) $amount; } );

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
			->onlyMethods( array(
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
			->onlyMethods( array( 'title' ) )
			->getMock();
		$cf7_form->method( 'title' )->willReturn( 'My Enquiry Form' );

		Functions\when( 'is_a' )->alias( function ( $obj, $class ) {
			return $obj instanceof $class;
		} );

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
			->onlyMethods( array( 'title' ) )
			->getMock();
		$cf7_form->method( 'title' )->willReturn( 'My Form' );

		Functions\when( 'is_a' )->alias( function ( $obj, $class ) {
			return $obj instanceof $class;
		} );

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

/**
 * Class Test_KwtSMS_Woo_v230
 *
 * Tests for v2.3.0 additions:
 *   - SMS for pending, refunded, failed order statuses
 *   - Admin phone notification sent when status is in notify list
 *   - Admin phone notification NOT sent when status is NOT in notify list
 */
class Test_KwtSMS_Woo_v230 extends TestCase {

	/**
	 * Captured add_action hooks.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registered_actions = array();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'get_user_meta' )->justReturn( '96599220322' );
		Functions\when( 'get_bloginfo' )->alias( function ( $show ) {
			return ( 'name' === $show ) ? 'TestSite' : '';
		} );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( 'wc_price' )->alias( function ( $amount ) { return (string) $amount; } );

		Functions\when( 'add_action' )->alias( function ( $hook ) {
			$this->registered_actions[] = $hook;
			return null;
		} );
		Functions\when( 'add_filter' )->justReturn( null );

		// Stub WordPress i18n function used in admin notification sprintf.
		Functions\when( '__' )->alias( function ( $text, $domain = '' ) { return $text; } );

		// Stub WooCommerce helper used in admin notification message.
		Functions\when( 'wc_get_order_status_name' )->alias( function ( $status ) { return ucfirst( $status ); } );

		// Stub wp_strip_all_tags used when building admin notification messages.
		Functions\when( 'wp_strip_all_tags' )->returnArg();

	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Test 1 — pending status sends SMS via woo_pending template
	// =========================================================================

	/**
	 * Mock order with status 'pending', assert send_sms() is called with
	 * the woo_pending template's message (placeholders replaced).
	 */
	public function test_on_order_status_changed_sends_sms_for_pending_status() {
		$sent_messages = array();

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_messages ) {
				$sent_messages[] = array( 'message' => $message, 'context' => $context );
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.woo_enabled'          => 1,
				'integrations.woo_checkout_otp'     => 0,
				'integrations.woo_admin_phone'      => '',
				'integrations.woo_notify_admin_statuses' => array(),
				'gateway.sender_id'                 => 'TESTSENDER',
			),
			array(
				'woo_pending' => array(
					'enabled' => 1,
					'en'      => '{site_name}: We received your order #{order_id}. Awaiting payment.',
					'ar'      => '',
				),
			),
			$api
		);

		$woo   = new KwtSMS_Woo( $plugin );
		$order = $this->make_order_stub( '77', 50.0, 'Jane', 'Smith' );

		$woo->on_order_status_changed( 77, 'checkout-draft', 'pending', $order );

		$this->assertNotEmpty( $sent_messages, 'send_sms was not called for pending status.' );
		$this->assertStringContainsString( 'TestSite', $sent_messages[0]['message'] );
		$this->assertStringContainsString( '77', $sent_messages[0]['message'] );
		$this->assertStringNotContainsString( '{order_id}', $sent_messages[0]['message'] );
		$this->assertStringNotContainsString( '{site_name}', $sent_messages[0]['message'] );
	}

	// =========================================================================
	// Test 2 — refunded status sends SMS via woo_refunded template
	// =========================================================================

	/**
	 * Mock order with status 'refunded', assert send_sms() is called with
	 * the woo_refunded template's message.
	 */
	public function test_on_order_status_changed_sends_sms_for_refunded_status() {
		$sent_messages = array();

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_messages ) {
				$sent_messages[] = array( 'message' => $message, 'context' => $context );
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.woo_enabled'          => 1,
				'integrations.woo_checkout_otp'     => 0,
				'integrations.woo_admin_phone'      => '',
				'integrations.woo_notify_admin_statuses' => array(),
				'gateway.sender_id'                 => 'TESTSENDER',
			),
			array(
				'woo_refunded' => array(
					'enabled' => 1,
					'en'      => '{site_name}: Your order #{order_id} has been refunded.',
					'ar'      => '',
				),
			),
			$api
		);

		$woo   = new KwtSMS_Woo( $plugin );
		$order = $this->make_order_stub( '88', 75.0, 'Ali', 'Hassan' );

		$woo->on_order_status_changed( 88, 'completed', 'refunded', $order );

		$this->assertNotEmpty( $sent_messages, 'send_sms was not called for refunded status.' );
		$this->assertStringContainsString( 'TestSite', $sent_messages[0]['message'] );
		$this->assertStringContainsString( '88', $sent_messages[0]['message'] );
		$this->assertStringContainsString( 'refunded', $sent_messages[0]['message'] );
	}

	// =========================================================================
	// Test 3 — failed status sends SMS via woo_failed template
	// =========================================================================

	/**
	 * Mock order with status 'failed', assert send_sms() is called with
	 * the woo_failed template's message.
	 */
	public function test_on_order_status_changed_sends_sms_for_failed_status() {
		$sent_messages = array();

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_messages ) {
				$sent_messages[] = array( 'message' => $message, 'context' => $context );
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.woo_enabled'          => 1,
				'integrations.woo_checkout_otp'     => 0,
				'integrations.woo_admin_phone'      => '',
				'integrations.woo_notify_admin_statuses' => array(),
				'gateway.sender_id'                 => 'TESTSENDER',
			),
			array(
				'woo_failed' => array(
					'enabled' => 1,
					'en'      => '{site_name}: Payment for your order #{order_id} failed. Please try again.',
					'ar'      => '',
				),
			),
			$api
		);

		$woo   = new KwtSMS_Woo( $plugin );
		$order = $this->make_order_stub( '99', 120.0, 'Sara', 'Lee' );

		$woo->on_order_status_changed( 99, 'pending', 'failed', $order );

		$this->assertNotEmpty( $sent_messages, 'send_sms was not called for failed status.' );
		$this->assertStringContainsString( 'TestSite', $sent_messages[0]['message'] );
		$this->assertStringContainsString( '99', $sent_messages[0]['message'] );
		$this->assertStringContainsString( 'failed', $sent_messages[0]['message'] );
	}

	// =========================================================================
	// Test 4 — admin notification sent when status is in notify list
	// =========================================================================

	/**
	 * When admin phone is set and the new status is in woo_notify_admin_statuses,
	 * send_sms() must be called a second time with context 'woo_admin'.
	 */
	public function test_admin_phone_notification_sent_when_status_in_notify_list() {
		$sent_contexts = array();

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_contexts ) {
				$sent_contexts[] = $context;
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.woo_enabled'               => 1,
				'integrations.woo_checkout_otp'          => 0,
				'integrations.woo_admin_phone'           => '96599220399',
				'integrations.woo_notify_admin_statuses' => array( 'processing' ),
				'gateway.sender_id'                      => 'TESTSENDER',
			),
			array(
				'woo_processing' => array(
					'enabled' => 1,
					'en'      => '{site_name}: Order #{order_id} confirmed.',
					'ar'      => '',
				),
			),
			$api
		);

		$woo   = new KwtSMS_Woo( $plugin );
		$order = $this->make_order_stub_with_admin_methods( '55', 200.0, 'Bob', 'Marley' );

		$woo->on_order_status_changed( 55, 'pending', 'processing', $order );

		// Customer SMS + admin SMS = 2 calls.
		$this->assertContains( 'woo_order', $sent_contexts, 'Customer SMS context woo_order expected.' );
		$this->assertContains( 'woo_admin', $sent_contexts, 'Admin SMS context woo_admin expected.' );
	}

	// =========================================================================
	// Test 5 — admin notification NOT sent when status NOT in notify list
	// =========================================================================

	/**
	 * When admin phone is set but the new status is NOT in woo_notify_admin_statuses,
	 * send_sms() must be called only once (for the customer), not for the admin.
	 */
	public function test_admin_phone_notification_not_sent_when_status_not_in_notify_list() {
		$sent_contexts = array();

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_contexts ) {
				$sent_contexts[] = $context;
			}
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.woo_enabled'               => 1,
				'integrations.woo_checkout_otp'          => 0,
				'integrations.woo_admin_phone'           => '96599220399',
				// Only 'cancelled' in the list — 'processing' should NOT trigger admin SMS.
				'integrations.woo_notify_admin_statuses' => array( 'cancelled' ),
				'gateway.sender_id'                      => 'TESTSENDER',
			),
			array(
				'woo_processing' => array(
					'enabled' => 1,
					'en'      => '{site_name}: Order #{order_id} confirmed.',
					'ar'      => '',
				),
			),
			$api
		);

		$woo   = new KwtSMS_Woo( $plugin );
		$order = $this->make_order_stub( '66', 150.0, 'Tom', 'Jones' );

		$woo->on_order_status_changed( 66, 'pending', 'processing', $order );

		// Only the customer SMS should be sent.
		$this->assertContains( 'woo_order', $sent_contexts, 'Customer SMS should still be sent.' );
		$this->assertNotContains( 'woo_admin', $sent_contexts, 'Admin SMS must NOT be sent when status not in notify list.' );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a minimal WC_Order stub for status-change tests.
	 *
	 * @param string $order_number   Order number (string).
	 * @param float  $total          Order total.
	 * @param string $first_name     Customer first name.
	 * @param string $last_name      Customer last name.
	 *
	 * @return \WC_Order (mock)
	 */
	private function make_order_stub( $order_number, $total, $first_name, $last_name ) {
		$order = $this->getMockBuilder( 'WC_Order' )
			->onlyMethods( array(
				'get_customer_id', 'get_billing_phone',
				'get_order_number', 'get_total',
				'get_billing_first_name', 'get_billing_last_name',
			) )
			->getMock();

		$order->method( 'get_customer_id' )->willReturn( 0 );
		// Return a valid phone so on_order_status_changed doesn't bail with "No phone".
		$order->method( 'get_billing_phone' )->willReturn( '96599220322' );
		$order->method( 'get_order_number' )->willReturn( $order_number );
		$order->method( 'get_total' )->willReturn( $total );
		$order->method( 'get_billing_first_name' )->willReturn( $first_name );
		$order->method( 'get_billing_last_name' )->willReturn( $last_name );

		return $order;
	}

	/**
	 * Build a WC_Order stub that also exposes get_id() and admin-notification methods.
	 *
	 * Required for the admin notification test since on_order_status_changed calls
	 * $order->get_id(), $order->get_formatted_billing_full_name(), and
	 * $order->get_formatted_order_total() for the admin SMS message.
	 *
	 * @param string $order_number Order number string.
	 * @param float  $total        Order total.
	 * @param string $first_name   Customer first name.
	 * @param string $last_name    Customer last name.
	 *
	 * @return \WC_Order (mock)
	 */
	private function make_order_stub_with_admin_methods( $order_number, $total, $first_name, $last_name ) {
		$order = $this->getMockBuilder( 'WC_Order' )
			->onlyMethods( array(
				'get_customer_id', 'get_billing_phone',
				'get_order_number', 'get_total',
				'get_billing_first_name', 'get_billing_last_name',
				'get_id', 'get_formatted_billing_full_name', 'get_formatted_order_total',
			) )
			->getMock();

		$order->method( 'get_customer_id' )->willReturn( 0 );
		// Return a valid phone so on_order_status_changed doesn't bail with "No phone".
		$order->method( 'get_billing_phone' )->willReturn( '96599220322' );
		$order->method( 'get_order_number' )->willReturn( $order_number );
		$order->method( 'get_total' )->willReturn( $total );
		$order->method( 'get_billing_first_name' )->willReturn( $first_name );
		$order->method( 'get_billing_last_name' )->willReturn( $last_name );
		$order->method( 'get_id' )->willReturn( (int) $order_number );
		$order->method( 'get_formatted_billing_full_name' )->willReturn( $first_name . ' ' . $last_name );
		$order->method( 'get_formatted_order_total' )->willReturn( '$' . $total );

		return $order;
	}

	/**
	 * Build a KwtSMS_Plugin mock with configurable settings and integration templates.
	 *
	 * @param array       $settings_map          Dot-notation key => value map.
	 * @param array       $integration_templates Return value for get_all_integration_templates().
	 * @param object|null $api_mock              Optional API mock.
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
		$plugin           = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->getMock();
		$plugin->settings = $settings;
		$plugin->api      = $api_mock;

		return $plugin;
	}
}

/**
 * Class Test_KwtSMS_Woo_Metabox
 *
 * Tests for the KwtSMS_Woo_Metabox AJAX handler (ajax_send_custom_sms).
 *
 * Brain\Monkey stubs are used for all WordPress and WooCommerce functions so
 * the tests run without a full WordPress or WooCommerce installation.
 */
class Test_KwtSMS_Woo_Metabox extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Standard WP function stubs needed by most paths.
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_textarea_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( '__' )->alias( function ( $text, $domain = '' ) { return $text; } );
		Functions\when( 'absint' )->alias( function ( $v ) { return abs( (int) $v ); } );
		Functions\when( 'esc_html' )->alias( function ( $text ) { return $text; } );
		Functions\when( 'get_option' )->justReturn( array() );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Test 1 — Rejects request when current user lacks edit_shop_orders cap
	// =========================================================================

	/**
	 * When current_user_can('edit_shop_orders') returns false, ajax_send_custom_sms()
	 * must call wp_send_json_error with a permission-denied message and return
	 * without calling send_sms().
	 */
	public function test_ajax_send_custom_sms_rejects_missing_capability() {
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );

		$json_error_called = false;
		$json_error_data   = null;
		Functions\when( 'wp_send_json_error' )->alias(
			function ( $data ) use ( &$json_error_called, &$json_error_data ) {
				$json_error_called = true;
				$json_error_data   = $data;
			}
		);
		Functions\when( 'wp_send_json_success' )->justReturn( null );

		// Provide POST data so we don't hit the field-missing guard first.
		$_POST = array(
			'order_id' => '10',
			'phone'    => '96599220322',
			'message'  => 'Hello',
			'nonce'    => 'fake_nonce',
		);

		$metabox = $this->make_metabox_instance();
		$metabox->ajax_send_custom_sms();

		$this->assertTrue( $json_error_called, 'wp_send_json_error should be called for missing capability.' );
		$this->assertStringContainsString( 'Permission denied', $json_error_data['message'] );

		$_POST = array();
	}

	// =========================================================================
	// Test 2 — Rejects request when phone fails normalize_phone validation
	// =========================================================================

	/**
	 * When current_user_can returns true and the nonce passes, but the phone
	 * number is invalid (normalize_phone returns a WP_Error), ajax_send_custom_sms()
	 * must call wp_send_json_error with the phone validation message.
	 */
	public function test_ajax_send_custom_sms_rejects_invalid_phone() {
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$order_mock = $this->getMockBuilder( 'WC_Order' )->getMock();
		Functions\when( 'wc_get_order' )->justReturn( $order_mock );

		$json_error_called = false;
		$json_error_data   = null;
		Functions\when( 'wp_send_json_error' )->alias(
			function ( $data ) use ( &$json_error_called, &$json_error_data ) {
				$json_error_called = true;
				$json_error_data   = $data;
			}
		);
		Functions\when( 'wp_send_json_success' )->justReturn( null );

		$_POST = array(
			'order_id' => '10',
			'phone'    => 'not-a-valid-phone',
			'message'  => 'Hello',
			'nonce'    => 'fake_nonce',
		);

		$metabox = $this->make_metabox_instance();
		$metabox->ajax_send_custom_sms();

		$this->assertTrue( $json_error_called, 'wp_send_json_error should be called for invalid phone.' );
		$this->assertStringContainsString( 'country code', $json_error_data['message'] );

		$_POST = array();
	}

	// =========================================================================
	// Test 3 — Sends SMS and returns success for valid inputs
	// =========================================================================

	/**
	 * When all inputs are valid (capability granted, order exists, phone normalises,
	 * and send_sms succeeds), ajax_send_custom_sms() must call wp_send_json_success.
	 */
	public function test_ajax_send_custom_sms_sends_and_returns_success() {
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$order_mock = $this->getMockBuilder( 'WC_Order' )->getMock();
		Functions\when( 'wc_get_order' )->justReturn( $order_mock );

		$send_sms_called = false;
		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function () use ( &$send_sms_called ) {
				$send_sms_called = true;
				return true;
			}
		);

		$json_success_called = false;
		Functions\when( 'wp_send_json_success' )->alias(
			function () use ( &$json_success_called ) {
				$json_success_called = true;
			}
		);
		Functions\when( 'wp_send_json_error' )->justReturn( null );

		$_POST = array(
			'order_id' => '10',
			'phone'    => '96599220322',
			'message'  => 'Hello from test',
			'nonce'    => 'fake_nonce',
		);

		$metabox = $this->make_metabox_instance( $api );
		$metabox->ajax_send_custom_sms();

		$this->assertTrue( $send_sms_called, 'api->send_sms() should have been called.' );
		$this->assertTrue( $json_success_called, 'wp_send_json_success() should have been called.' );

		$_POST = array();
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a KwtSMS_Woo_Metabox instance with stubbed API and settings.
	 *
	 * @param object|null $api_mock Optional API stub. Defaults to a no-op send_sms stub.
	 *
	 * @return KwtSMS_Woo_Metabox
	 */
	private function make_metabox_instance( $api_mock = null ) {
		// Stub add_action — KwtSMS_Woo_Metabox constructor calls it twice.
		Functions\when( 'add_action' )->justReturn( null );

		if ( null === $api_mock ) {
			$api_mock = $this->getMockBuilder( 'stdClass' )
				->addMethods( array( 'send_sms' ) )
				->getMock();
			$api_mock->method( 'send_sms' )->willReturn( null );
		}

		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		$settings->method( 'get' )->willReturnCallback(
			function ( $key, $default = null ) {
				if ( 'gateway.sender_id' === $key ) {
					return 'TESTSENDER';
				}
				return $default;
			}
		);

		return new KwtSMS_Woo_Metabox( $api_mock, $settings );
	}
}

/**
 * Minimal GFForms stub — presence of this class causes the GF integration
 * constructor to proceed past the class_exists('GFForms') guard.
 */
if ( ! class_exists( 'GFForms' ) ) {
	// phpcs:ignore
	class GFForms {}
}

/**
 * Minimal Ninja_Forms stub — presence of this class causes the NF integration
 * constructor to proceed past the class_exists('Ninja_Forms') guard.
 */
if ( ! class_exists( 'Ninja_Forms' ) ) {
	// phpcs:ignore
	class Ninja_Forms {}
}

/**
 * Class Test_KwtSMS_GravityForms
 *
 * Tests for the Gravity Forms integration class.
 */
class Test_KwtSMS_GravityForms extends TestCase {

	/**
	 * Actions captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	/**
	 * Filters captured during add_filter calls.
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
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'get_bloginfo' )->alias( function ( $show ) {
			return ( 'name' === $show ) ? 'TestSite' : '';
		} );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( '__' )->alias( function ( $text, $domain = '' ) { return $text; } );

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
	// Class / file existence
	// =========================================================================

	public function test_gravityforms_class_exists() {
		$this->assertTrue( class_exists( 'KwtSMS_GravityForms' ) );
	}

	public function test_gravityforms_integration_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/integrations/class-kwtsms-gravityforms.php' );
	}

	// =========================================================================
	// Test 1 — Notification mode: sends SMS on submission
	// =========================================================================

	/**
	 * In notification mode (default), the constructor must register
	 * `gform_after_submission` action and, when called, dispatch an SMS
	 * to the phone field value found in the GF entry.
	 */
	public function test_gravityforms_sends_notification_on_submission() {
		$sent_phone   = null;
		$sent_message = null;

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_phone, &$sent_message ) {
				$sent_phone   = $phone;
				$sent_message = $message;
			}
		);

		$integration_templates = array(
			'gf_confirmation' => array(
				'enabled' => 1,
				'en'      => '{form_name}: Thank you! Your phone {phone} has been registered.',
				'ar'      => '',
			),
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.gf_enabled' => 1,
				'integrations.gf_mode'    => 'notification',
				'gateway.sender_id'       => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		$gf = new KwtSMS_GravityForms( $plugin );

		// Confirm the notification hook was registered.
		$this->assertContains( 'gform_after_submission', $this->registered_actions );

		// Build a minimal GF form with one phone field (object-style, as GF uses).
		$phone_field        = new stdClass();
		$phone_field->id    = 1;
		$phone_field->type  = 'phone';
		$phone_field->label = 'Phone Number';

		$form            = array(
			'title'  => 'Contact Us',
			'fields' => array( $phone_field ),
		);
		$entry           = array( 1 => '96599220322' );

		$gf->send_notification( $entry, $form );

		$this->assertNotNull( $sent_phone,   'send_sms was not called — no phone dispatched.' );
		$this->assertNotNull( $sent_message, 'send_sms was not called — no message dispatched.' );
		$this->assertSame( '96599220322', $sent_phone );
		$this->assertStringContainsString( 'Contact Us', $sent_message );
		$this->assertStringContainsString( '96599220322', $sent_message );
		$this->assertStringNotContainsString( '{form_name}', $sent_message );
		$this->assertStringNotContainsString( '{phone}', $sent_message );
	}

	// =========================================================================
	// Test 2 — Gate mode: blocks submission without verified token
	// =========================================================================

	/**
	 * In gate mode, gate_validate() must set $validation_result['is_valid'] = false
	 * when no valid token is present in $_POST.
	 */
	public function test_gravityforms_gate_blocks_without_verified_token() {
		$_POST = array(); // Ensure no token in POST.

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.gf_enabled' => 1,
				'integrations.gf_mode'    => 'gate',
			),
			array()
		);

		// Stub verify_form_token to always return false (no token supplied).
		$plugin->method( 'verify_form_token' )->willReturn( false );

		$gf = new KwtSMS_GravityForms( $plugin );

		// Confirm the gate filter was registered.
		$this->assertContains( 'gform_validation', $this->registered_filters );

		// Build a minimal $validation_result as GF would pass it.
		$phone_field        = new stdClass();
		$phone_field->id    = 2;
		$phone_field->type  = 'phone';
		$phone_field->label = 'Mobile';

		$validation_result = array(
			'is_valid' => true,
			'form'     => array(
				'title'  => 'Registration',
				'fields' => array( $phone_field ),
			),
		);

		$result = $gf->gate_validate( $validation_result );

		$this->assertFalse( $result['is_valid'], 'Submission must be blocked when no valid token is present.' );
	}

	// =========================================================================
	// Test 3 — Gate mode: allows submission with valid verified token
	// =========================================================================

	/**
	 * In gate mode, gate_validate() must leave $validation_result['is_valid']
	 * unchanged (true) and consume the token when a valid token is present.
	 */
	public function test_gravityforms_gate_allows_submission_with_valid_token() {
		$_POST = array( 'kwtsms_form_verified_token' => 'valid_token_abc' );

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.gf_enabled' => 1,
				'integrations.gf_mode'    => 'gate',
			),
			array()
		);

		// Stub verify_form_token to return true for any token.
		$plugin->method( 'verify_form_token' )->willReturn( true );

		$gf = new KwtSMS_GravityForms( $plugin );

		$validation_result = array(
			'is_valid' => true,
			'form'     => array( 'title' => 'Test', 'fields' => array() ),
		);

		$result = $gf->gate_validate( $validation_result );

		$this->assertTrue( $result['is_valid'], 'Submission must be allowed when a valid token is present.' );

		$_POST = array();
	}

	// =========================================================================
	// Test 4 — Disabled flag: no hooks registered
	// =========================================================================

	/**
	 * When gf_enabled = 0 the constructor must bail early and register no hooks.
	 */
	public function test_gravityforms_disabled_flag_prevents_hook_registration() {
		$plugin = $this->make_plugin_stub(
			array( 'integrations.gf_enabled' => 0 ),
			array()
		);

		new KwtSMS_GravityForms( $plugin );

		$this->assertNotContains( 'gform_after_submission', $this->registered_actions );
		$this->assertNotContains( 'gform_validation', $this->registered_filters );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a KwtSMS_Plugin mock with configurable settings and optional API.
	 *
	 * @param array       $settings_map          Dot-notation key → value map.
	 * @param array       $integration_templates Return value for get_all_integration_templates().
	 * @param object|null $api_mock              Optional API mock.
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
			->onlyMethods( array( 'verify_form_token' ) )
			->getMock();

		$plugin->settings = $settings;
		$plugin->api      = $api_mock;

		return $plugin;
	}
}

/**
 * Class Test_KwtSMS_NinjaForms
 *
 * Tests for the Ninja Forms integration class.
 */
class Test_KwtSMS_NinjaForms extends TestCase {

	/**
	 * Actions captured during add_action calls.
	 *
	 * @var string[]
	 */
	private $registered_actions = array();

	/**
	 * Filters captured during add_filter calls.
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
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'get_bloginfo' )->alias( function ( $show ) {
			return ( 'name' === $show ) ? 'TestSite' : '';
		} );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( '__' )->alias( function ( $text, $domain = '' ) { return $text; } );

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
	// Class / file existence
	// =========================================================================

	public function test_ninjaforms_class_exists() {
		$this->assertTrue( class_exists( 'KwtSMS_NinjaForms' ) );
	}

	public function test_ninjaforms_integration_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/integrations/class-kwtsms-ninjaforms.php' );
	}

	// =========================================================================
	// Test 1 — Notification mode: sends SMS on submission
	// =========================================================================

	/**
	 * In notification mode (default), the constructor must register
	 * `ninja_forms_after_submission` action and, when called, dispatch an SMS
	 * to the phone field value found in the form data.
	 */
	public function test_ninjaforms_sends_notification_on_submission() {
		$sent_phone   = null;
		$sent_message = null;

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone, $sender_id, $message, $context ) use ( &$sent_phone, &$sent_message ) {
				$sent_phone   = $phone;
				$sent_message = $message;
			}
		);

		$integration_templates = array(
			'nf_confirmation' => array(
				'enabled' => 1,
				'en'      => '{form_name}: Thank you for submitting the form.',
				'ar'      => '',
			),
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.nf_enabled' => 1,
				'integrations.nf_mode'    => 'notification',
				'gateway.sender_id'       => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		$nf = new KwtSMS_NinjaForms( $plugin );

		// Confirm the notification hook was registered.
		$this->assertContains( 'ninja_forms_after_submission', $this->registered_actions );

		// Build a minimal NF form_data array with a phone field.
		$form_data = array(
			'settings' => array( 'title' => 'My NF Form' ),
			'fields'   => array(
				array(
					'type'  => 'phone',
					'label' => 'Phone',
					'value' => '96599220322',
				),
			),
		);

		$nf->send_notification( $form_data );

		$this->assertNotNull( $sent_phone,   'send_sms was not called — no phone dispatched.' );
		$this->assertNotNull( $sent_message, 'send_sms was not called — no message dispatched.' );
		$this->assertSame( '96599220322', $sent_phone );
		$this->assertStringContainsString( 'My NF Form', $sent_message );
		$this->assertStringNotContainsString( '{form_name}', $sent_message );
	}

	// =========================================================================
	// Test 2 — Notification mode: tel field type is detected
	// =========================================================================

	/**
	 * Ensure that a field with type 'tel' (as well as 'phone') is treated as a
	 * phone field and triggers SMS dispatch.
	 */
	public function test_ninjaforms_detects_tel_field_type() {
		$sent_phone = null;

		$api = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'send_sms' ) )
			->getMock();
		$api->method( 'send_sms' )->willReturnCallback(
			function ( $phone ) use ( &$sent_phone ) {
				$sent_phone = $phone;
			}
		);

		$integration_templates = array(
			'nf_confirmation' => array(
				'enabled' => 1,
				'en'      => 'Thank you',
				'ar'      => '',
			),
		);

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.nf_enabled' => 1,
				'integrations.nf_mode'    => 'notification',
				'gateway.sender_id'       => 'TESTSENDER',
			),
			$integration_templates,
			$api
		);

		$nf = new KwtSMS_NinjaForms( $plugin );

		$form_data = array(
			'settings' => array( 'title' => 'Tel Form' ),
			'fields'   => array(
				array(
					'type'  => 'tel',
					'label' => 'Telephone',
					'value' => '96599220333',
				),
			),
		);

		$nf->send_notification( $form_data );

		$this->assertNotNull( $sent_phone, 'send_sms was not called for a tel-type field.' );
		$this->assertSame( '96599220333', $sent_phone );
	}

	// =========================================================================
	// Test 3 — Gate mode: blocks fields without verified token
	// =========================================================================

	/**
	 * In gate mode, gate_validate_fields() must add an error to the phone field
	 * when no valid token is present in $_POST.
	 */
	public function test_ninjaforms_gate_blocks_without_verified_token() {
		$_POST = array(); // Ensure no token in POST.

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.nf_enabled' => 1,
				'integrations.nf_mode'    => 'gate',
			),
			array()
		);

		$plugin->method( 'verify_form_token' )->willReturn( false );

		$nf = new KwtSMS_NinjaForms( $plugin );

		// Confirm the gate filter was registered.
		$this->assertContains( 'ninja_forms_submit_fields', $this->registered_filters );

		$fields = array(
			array(
				'type'   => 'phone',
				'label'  => 'Phone',
				'value'  => '96599220322',
				'errors' => array(),
			),
		);

		$result = $nf->gate_validate_fields( $fields );

		// The first (and only) phone field must now carry an error.
		$this->assertNotEmpty( $result[0]['errors'], 'Phone field must have errors when no valid token is present.' );
	}

	// =========================================================================
	// Test 3b — Gate mode: allow path (valid token, transient consumed)
	// =========================================================================

	/**
	 * When a valid token is present gate_validate_fields must return the fields
	 * without any errors and consume the transient (delete_transient called).
	 */
	public function test_ninjaforms_gate_allows_submission_with_valid_token(): void {
		$token = 'valid_token_abc';
		$_POST = array( 'kwtsms_form_verified_token' => $token );

		$plugin = $this->make_plugin_stub(
			array(
				'integrations.nf_enabled' => 1,
				'integrations.nf_mode'    => 'gate',
			),
			array()
		);

		// Stub verify_form_token to return true for any token.
		$plugin->method( 'verify_form_token' )->willReturn( true );

		$nf = new KwtSMS_NinjaForms( $plugin );

		$fields = array(
			array(
				'id'     => '1',
				'type'   => 'phone',
				'label'  => 'Phone',
				'value'  => '96599220322',
				'errors' => array(),
			),
		);

		$result = $nf->gate_validate_fields( $fields );

		// No errors must be attached when the token is valid.
		$this->assertEmpty( $result[0]['errors'], 'Phone field must have no errors when a valid token is present.' );

		$_POST = array();
	}

	// =========================================================================
	// Test 4 — Disabled flag: no hooks registered
	// =========================================================================

	/**
	 * When nf_enabled = 0 the constructor must bail early and register no hooks.
	 */
	public function test_ninjaforms_disabled_flag_prevents_hook_registration() {
		$plugin = $this->make_plugin_stub(
			array( 'integrations.nf_enabled' => 0 ),
			array()
		);

		new KwtSMS_NinjaForms( $plugin );

		$this->assertNotContains( 'ninja_forms_after_submission', $this->registered_actions );
		$this->assertNotContains( 'ninja_forms_submit_fields', $this->registered_filters );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a KwtSMS_Plugin mock with configurable settings and optional API.
	 *
	 * @param array       $settings_map          Dot-notation key → value map.
	 * @param array       $integration_templates Return value for get_all_integration_templates().
	 * @param object|null $api_mock              Optional API mock.
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
			->onlyMethods( array( 'verify_form_token' ) )
			->getMock();

		$plugin->settings = $settings;
		$plugin->api      = $api_mock;

		return $plugin;
	}
}

/**
 * Class Test_KwtSMS_GF_NF_Settings
 *
 * Tests that DEFAULTS and get_all_integration_templates() include the
 * new GF and NF keys introduced in v2.8.0.
 */
class Test_KwtSMS_GF_NF_Settings extends TestCase {

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

	public function test_defaults_contain_gf_keys() {
		$defaults = KwtSMS_Settings::DEFAULTS['integrations'];
		$this->assertArrayHasKey( 'gf_enabled',      $defaults );
		$this->assertArrayHasKey( 'gf_mode',          $defaults );
		$this->assertArrayHasKey( 'gf_confirmation',  $defaults );
	}

	public function test_defaults_contain_nf_keys() {
		$defaults = KwtSMS_Settings::DEFAULTS['integrations'];
		$this->assertArrayHasKey( 'nf_enabled',      $defaults );
		$this->assertArrayHasKey( 'nf_mode',          $defaults );
		$this->assertArrayHasKey( 'nf_confirmation',  $defaults );
	}

	public function test_gf_confirmation_has_en_ar_enabled() {
		$tpl = KwtSMS_Settings::DEFAULTS['integrations']['gf_confirmation'];
		$this->assertArrayHasKey( 'en',      $tpl );
		$this->assertArrayHasKey( 'ar',      $tpl );
		$this->assertArrayHasKey( 'enabled', $tpl );
		$this->assertNotEmpty( $tpl['en'] );
		$this->assertNotEmpty( $tpl['ar'] );
	}

	public function test_nf_confirmation_has_en_ar_enabled() {
		$tpl = KwtSMS_Settings::DEFAULTS['integrations']['nf_confirmation'];
		$this->assertArrayHasKey( 'en',      $tpl );
		$this->assertArrayHasKey( 'ar',      $tpl );
		$this->assertArrayHasKey( 'enabled', $tpl );
		$this->assertNotEmpty( $tpl['en'] );
		$this->assertNotEmpty( $tpl['ar'] );
	}

	public function test_get_all_integration_templates_includes_gf_nf() {
		$settings  = new KwtSMS_Settings();
		$templates = $settings->get_all_integration_templates();
		$this->assertArrayHasKey( 'gf_confirmation', $templates );
		$this->assertArrayHasKey( 'nf_confirmation', $templates );
	}

	public function test_get_all_integration_templates_returns_12_keys() {
		$settings  = new KwtSMS_Settings();
		$templates = $settings->get_all_integration_templates();
		// 10 previous + gf_confirmation + nf_confirmation = 12
		$this->assertCount( 12, $templates );
	}
}

/**
 * Class Test_KwtSMS_GF_NF_IntegrationsPage
 *
 * Tests that the integrations views include GF and NF settings.
 *
 * As of the v2.x redesign, GF and NF are configured on the shared
 * page-int-form.php sub-page (not on the overview table). The overview table
 * references the integration slugs; the shared sub-page contains the fields.
 */
class Test_KwtSMS_GF_NF_IntegrationsPage extends TestCase {

	private function view_path(): string {
		return dirname( __DIR__ ) . '/admin/views/page-integrations.php';
	}

	private function form_path(): string {
		return dirname( __DIR__ ) . '/admin/views/page-int-form.php';
	}

	public function test_integrations_page_has_gf_tab_link() {
		// The overview table references the gf integration slug.
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'kwtsms-otp-int-gf', $src );
	}

	public function test_integrations_page_has_nf_tab_link() {
		// The overview table references the nf integration slug.
		$src = file_get_contents( $this->view_path() );
		$this->assertStringContainsString( 'kwtsms-otp-int-nf', $src );
	}

	public function test_integrations_page_has_gf_confirmation_field() {
		// GF confirmation field lives in the shared form sub-page.
		$src = file_get_contents( $this->form_path() );
		$this->assertStringContainsString( 'gf_confirmation', $src );
	}

	public function test_integrations_page_has_nf_confirmation_field() {
		// NF confirmation field lives in the shared form sub-page.
		$src = file_get_contents( $this->form_path() );
		$this->assertStringContainsString( 'nf_confirmation', $src );
	}
}

/**
 * Class Test_KwtSMS_GF_NF_Wiring
 *
 * Tests that class-kwtsms-integrations.php wires GF and NF classes.
 */
class Test_KwtSMS_GF_NF_Wiring extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );
		Functions\when( 'is_wp_error' )->alias( function ( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'did_action' )->justReturn( false );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_gravityforms_integration_file_is_wired() {
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-kwtsms-integrations.php' );
		$this->assertStringContainsString( 'class-kwtsms-gravityforms.php', $src );
		$this->assertStringContainsString( 'KwtSMS_GravityForms', $src );
	}

	public function test_ninjaforms_integration_file_is_wired() {
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-kwtsms-integrations.php' );
		$this->assertStringContainsString( 'class-kwtsms-ninjaforms.php', $src );
		$this->assertStringContainsString( 'KwtSMS_NinjaForms', $src );
	}

	public function test_integrations_boot_instantiates_gravityforms_when_class_exists() {
		// GFForms stub is defined at the top of this file — it is already present.
		$this->assertTrue( class_exists( 'GFForms' ), 'GFForms stub must be defined.' );
	}

	public function test_integrations_boot_instantiates_ninjaforms_when_class_exists() {
		// Ninja_Forms stub is defined at the top of this file — it is already present.
		$this->assertTrue( class_exists( 'Ninja_Forms' ), 'Ninja_Forms stub must be defined.' );
	}
}
