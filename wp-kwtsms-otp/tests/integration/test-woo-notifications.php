<?php
/**
 * Integration tests — WooCommerce order-status SMS notifications.
 *
 * Verifies that KwtSMS_Woo::on_order_status_changed() fires an HTTP request
 * to kwtsms.com for enabled templates, and does not fire when disabled.
 *
 * WC_Order is stubbed below because WooCommerce is not installed in the test
 * environment. The stub mirrors the method signatures used by KwtSMS_Woo.
 *
 * Integration template option key layout (stored flat in kwtsms_otp_integrations):
 *   woo_processing  => [ 'enabled'=>1, 'en'=>'...', 'ar'=>'' ]
 *   woo_completed   => [ 'enabled'=>1, 'en'=>'...', 'ar'=>'' ]
 *   woo_cancelled   => [ 'enabled'=>0, 'en'=>'',    'ar'=>'' ]
 *
 * @package KwtSMS_OTP\Tests\Integration
 */

// Provide a minimal WC_Order stub when WooCommerce is not active.
if ( ! class_exists( 'WC_Order' ) ) {
	// phpcs:ignore
	class WC_Order {
		public function get_id() {}
		public function get_customer_id() {}
		public function get_billing_phone() {}
		public function get_order_number() {}
		public function get_total() {}
		public function get_billing_first_name() {}
		public function get_billing_last_name() {}
		public function get_formatted_billing_full_name() {}
		public function get_formatted_order_total() {}
		public function get_meta( $key ) {}
	}
}

// Stub wc_get_order() so KwtSMS_Woo can call it when $order is null.
if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $order_id ) { // phpcs:ignore
		return false; // Return false to avoid needing a full order object.
	}
}

// Stub wc_price() used in build_order_message().
if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( $amount ) { // phpcs:ignore
		return (string) $amount;
	}
}

// Stub wc_get_order_status_name() used in admin SMS notification.
if ( ! function_exists( 'wc_get_order_status_name' ) ) {
	function wc_get_order_status_name( $status ) { // phpcs:ignore
		return $status;
	}
}

// Ensure the KwtSMS_Woo class is loaded. Normally KwtSMS_Integrations boots it
// on plugins_loaded when class_exists('WooCommerce') is true. Since WooCommerce
// is not installed in the test environment, we load the class file directly so
// we can instantiate it manually in set_up().
if ( ! class_exists( 'KwtSMS_Woo' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-kwtsms-woo.php';
}

/**
 * Class Test_Integration_Woo_Notifications
 */
class Test_Integration_Woo_Notifications extends WP_UnitTestCase {

	/**
	 * Captured HTTP calls.
	 *
	 * @var array
	 */
	private array $api_calls = [];

	/**
	 * KwtSMS_Woo instance created in set_up() to register hooks for testing.
	 *
	 * KwtSMS_Integrations::boot() only instantiates KwtSMS_Woo when
	 * class_exists('WooCommerce') is true. Since WooCommerce is not installed
	 * in the test environment, we instantiate it directly here so that the
	 * woocommerce_order_status_changed hook is registered for each test.
	 *
	 * @var KwtSMS_Woo|null
	 */
	private ?KwtSMS_Woo $woo = null;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->api_calls = [];
		add_filter( 'pre_http_request', [ $this, 'intercept_http' ], 10, 3 );

		// Minimal gateway settings.
		update_option( 'kwtsms_otp_gateway', [
			'api_username'         => 'testuser',
			'api_password'         => 'testpass',
			'sender_id'            => 'KWTSMS',
			'test_mode'            => 1,
			'credentials_verified' => 1,
			'balance_available'    => 10.0,
		] );

		// Integration template settings.
		// KwtSMS_Woo reads from kwtsms_otp_integrations with flat keys like woo_processing.
		update_option( 'kwtsms_otp_integrations', [
			'woo_enabled'    => 1,
			'woo_processing' => [
				'enabled' => 1,
				'en'      => 'Order #{order_id} is processing',
				'ar'      => '',
			],
			'woo_completed'  => [
				'enabled' => 1,
				'en'      => 'Order #{order_id} completed',
				'ar'      => '',
			],
			'woo_cancelled'  => [
				'enabled' => 0,
				'en'      => '',
				'ar'      => '',
			],
			// Other statuses use defaults (disabled).
			'woo_shipped'    => [ 'enabled' => 0, 'en' => '', 'ar' => '' ],
			'woo_pending'    => [ 'enabled' => 0, 'en' => '', 'ar' => '' ],
			'woo_refunded'   => [ 'enabled' => 0, 'en' => '', 'ar' => '' ],
			'woo_failed'     => [ 'enabled' => 0, 'en' => '', 'ar' => '' ],
		] );

		// No country restrictions.
		update_option( 'kwtsms_otp_general', [
			'allowed_countries'    => [],
			'default_country_code' => 'KW',
		] );

		// Reset the plugin singleton's settings cache and API instance so that
		// the option values written above are visible to the running code.
		$this->reset_plugin_singleton_state();

		// Manually instantiate KwtSMS_Woo using the refreshed plugin singleton.
		// Normally KwtSMS_Integrations::boot() does this on plugins_loaded when
		// WooCommerce is active — but WooCommerce is not installed in the test
		// environment, so we create the instance directly. Its constructor will
		// register the woocommerce_order_status_changed action.
		$plugin     = KwtSMS_Plugin::get_instance();
		$this->woo  = new KwtSMS_Woo( $plugin );
	}

	/**
	 * Flush the singleton's settings cache and rebuild the API instance.
	 *
	 * Uses reflection to clear KwtSMS_Settings::$cache so that the next
	 * settings read pulls fresh values from the DB.
	 * Also replaces KwtSMS_Plugin::$api with a fresh instance using the
	 * test credentials so send_sms() can pass the credential check.
	 */
	private function reset_plugin_singleton_state(): void {
		if ( ! class_exists( 'KwtSMS_Plugin' ) ) {
			return;
		}
		$plugin = KwtSMS_Plugin::get_instance();

		// Clear the settings in-memory cache via reflection.
		$settings_ref = new ReflectionObject( $plugin->settings );
		$cache_prop   = $settings_ref->getProperty( 'cache' );
		$cache_prop->setAccessible( true );
		$cache_prop->setValue( $plugin->settings, [] );

		// Replace the API instance with one that uses the test credentials.
		$plugin_ref = new ReflectionObject( $plugin );
		$api_prop   = $plugin_ref->getProperty( 'api' );
		$api_prop->setAccessible( true );
		$api_prop->setValue( $plugin, new KwtSMS_API( 'testuser', 'testpass', true, false ) );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'intercept_http' ] );

		// Remove the woocommerce_order_status_changed hook registered by our
		// KwtSMS_Woo instance so it does not bleed into subsequent tests.
		if ( null !== $this->woo ) {
			remove_action( 'woocommerce_order_status_changed', [ $this->woo, 'on_order_status_changed' ] );
			$this->woo = null;
		}

		delete_option( 'kwtsms_otp_gateway' );
		delete_option( 'kwtsms_otp_integrations' );
		delete_option( 'kwtsms_otp_general' );
		parent::tear_down();
	}

	/**
	 * HTTP interceptor.
	 *
	 * @param false|array|WP_Error $preempt Preempt value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 *
	 * @return array
	 */
	public function intercept_http( $preempt, $args, $url ): array {
		$this->api_calls[] = [ 'url' => $url, 'args' => $args ];
		return [
			'response' => [ 'code' => 200 ],
			'body'     => '{"result":"OK","msg-id":"test123"}',
			'headers'  => [],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Count HTTP POST calls to kwtsms.com/API/send/.
	 *
	 * @return int
	 */
	private function count_sms_calls(): int {
		return count( array_filter(
			$this->api_calls,
			static function ( $call ) {
				return str_contains( $call['url'], 'kwtsms.com' )
					&& str_contains( $call['url'], 'send/' );
			}
		) );
	}

	/**
	 * Build a mock WC_Order for a registered user (non-guest).
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $phone    Phone stored in user meta.
	 * @param int    $order_id Order ID to return from get_order_number().
	 *
	 * @return WC_Order Mock order object.
	 */
	private function make_order_for_user( int $user_id, string $phone, int $order_id = 1 ): WC_Order {
		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->onlyMethods( [
				'get_id', 'get_customer_id', 'get_billing_phone',
				'get_order_number', 'get_total', 'get_billing_first_name',
				'get_billing_last_name', 'get_formatted_billing_full_name',
				'get_formatted_order_total', 'get_meta',
			] )
			->getMock();

		$order->method( 'get_id' )->willReturn( $order_id );
		$order->method( 'get_customer_id' )->willReturn( $user_id );
		$order->method( 'get_billing_phone' )->willReturn( $phone );
		$order->method( 'get_order_number' )->willReturn( (string) $order_id );
		$order->method( 'get_total' )->willReturn( 50.00 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Test' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Customer' );
		$order->method( 'get_formatted_billing_full_name' )->willReturn( 'Test Customer' );
		$order->method( 'get_formatted_order_total' )->willReturn( '50.00 KWD' );
		$order->method( 'get_meta' )->willReturn( '' );

		return $order;
	}

	/**
	 * Build a mock WC_Order for a guest (customer_id=0) with a billing phone.
	 *
	 * @param string $billing_phone Billing phone to return.
	 * @param int    $order_id      Order ID.
	 *
	 * @return WC_Order Mock order object.
	 */
	private function make_guest_order( string $billing_phone, int $order_id = 2 ): WC_Order {
		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->onlyMethods( [
				'get_id', 'get_customer_id', 'get_billing_phone',
				'get_order_number', 'get_total', 'get_billing_first_name',
				'get_billing_last_name', 'get_formatted_billing_full_name',
				'get_formatted_order_total', 'get_meta',
			] )
			->getMock();

		$order->method( 'get_id' )->willReturn( $order_id );
		$order->method( 'get_customer_id' )->willReturn( 0 ); // Guest order.
		$order->method( 'get_billing_phone' )->willReturn( $billing_phone );
		$order->method( 'get_order_number' )->willReturn( (string) $order_id );
		$order->method( 'get_total' )->willReturn( 30.00 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Guest' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Buyer' );
		$order->method( 'get_formatted_billing_full_name' )->willReturn( 'Guest Buyer' );
		$order->method( 'get_formatted_order_total' )->willReturn( '30.00 KWD' );
		$order->method( 'get_meta' )->willReturn( '' );

		return $order;
	}

	// =========================================================================
	// Tests
	// =========================================================================

	/**
	 * Firing woocommerce_order_status_changed with new_status='processing'
	 * and an enabled processing template triggers an HTTP call to kwtsms.com.
	 */
	public function test_processing_status_triggers_sms(): void {
		$phone   = '96599220322';
		$user_id = $this->factory()->user->create( [
			'user_login' => 'wooprocessinguser',
			'user_email' => 'wooprocessing@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $phone );

		$order = $this->make_order_for_user( $user_id, $phone, 101 );

		$this->api_calls = [];
		do_action( 'woocommerce_order_status_changed', 101, 'pending', 'processing', $order );

		$this->assertGreaterThanOrEqual(
			1,
			$this->count_sms_calls(),
			'Expected HTTP call to kwtsms.com/send/ for processing status with enabled template.'
		);
	}

	/**
	 * Firing woocommerce_order_status_changed with new_status='completed'
	 * and an enabled completed template triggers an HTTP call to kwtsms.com.
	 */
	public function test_completed_status_triggers_sms(): void {
		$phone   = '96599220323';
		$user_id = $this->factory()->user->create( [
			'user_login' => 'woocompleteduser',
			'user_email' => 'woocompleted@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $phone );

		$order = $this->make_order_for_user( $user_id, $phone, 102 );

		$this->api_calls = [];
		do_action( 'woocommerce_order_status_changed', 102, 'processing', 'completed', $order );

		$this->assertGreaterThanOrEqual(
			1,
			$this->count_sms_calls(),
			'Expected HTTP call for completed status with enabled template.'
		);
	}

	/**
	 * When the cancelled template is disabled (enabled=0), no HTTP call is made.
	 */
	public function test_no_sms_when_template_disabled(): void {
		$phone   = '96599220324';
		$user_id = $this->factory()->user->create( [
			'user_login' => 'woocancelleduser',
			'user_email' => 'woocancelled@example.com',
		] );
		update_user_meta( $user_id, 'kwtsms_phone', $phone );

		$order = $this->make_order_for_user( $user_id, $phone, 103 );

		$this->api_calls = [];
		do_action( 'woocommerce_order_status_changed', 103, 'processing', 'cancelled', $order );

		$this->assertSame(
			0,
			$this->count_sms_calls(),
			'Expected no HTTP call for cancelled status when its template is disabled.'
		);
	}

	/**
	 * A guest order (customer_id=0) uses the billing phone from the order object.
	 * When the billing phone is valid, an SMS is sent for an enabled status.
	 */
	public function test_guest_order_uses_billing_phone(): void {
		// The billing phone is a full E.164 Kuwaiti number.
		$billing_phone = '96599220325';

		$order = $this->make_guest_order( $billing_phone, 104 );

		$this->api_calls = [];
		do_action( 'woocommerce_order_status_changed', 104, 'pending', 'processing', $order );

		$this->assertGreaterThanOrEqual(
			1,
			$this->count_sms_calls(),
			'Expected HTTP call for guest order — should use billing phone as fallback.'
		);
	}
}
