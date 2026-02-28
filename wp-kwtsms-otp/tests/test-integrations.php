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
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_order_status_changed', $this->registered_actions );
	}

	public function test_woo_constructor_registers_wc_register_form_action() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_register_form', $this->registered_actions );
	}

	public function test_woo_constructor_registers_wc_created_customer_action() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_created_customer', $this->registered_actions );
	}

	public function test_woo_constructor_registers_wc_registration_errors_filter() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 0 ) ) );

		$this->assertContains( 'woocommerce_registration_errors', $this->registered_filters );
	}

	public function test_woo_constructor_registers_checkout_otp_hooks_when_enabled() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 1 ) ) );

		$this->assertContains( 'woocommerce_after_order_notes', $this->registered_actions );
		$this->assertContains( 'woocommerce_checkout_process', $this->registered_actions );
		$this->assertContains( 'woocommerce_checkout_order_created', $this->registered_actions );
	}

	public function test_woo_constructor_skips_checkout_otp_hooks_when_disabled() {
		new KwtSMS_Woo( $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 0 ) ) );

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

		$plugin = $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 0 ) );

		$loader = new KwtSMS_Integrations( $plugin );
		$loader->boot();

		// Order status hook + register_form + created_customer = 3 minimum.
		$this->assertGreaterThanOrEqual( 3, count( $this->registered_actions ) );
	}

	public function test_integrations_loader_stores_plugin_reference() {
		$plugin = $this->make_plugin_stub( array( 'general.woo_checkout_otp' => 0 ) );
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
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub() {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		$settings->method( 'get' )->willReturn( '' );

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
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub() {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		$settings->method( 'get' )->willReturn( '' );

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
	 * @return KwtSMS_Plugin
	 */
	private function make_plugin_stub() {
		$settings = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get' ) )
			->getMock();
		$settings->method( 'get' )->willReturn( '' );

		/** @var KwtSMS_Plugin $plugin */
		$plugin           = $this->getMockBuilder( 'KwtSMS_Plugin' )
			->disableOriginalConstructor()
			->getMock();
		$plugin->settings = $settings;

		return $plugin;
	}
}
