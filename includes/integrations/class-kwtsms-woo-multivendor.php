<?php
/**
 * WooCommerce Multivendor SMS Support.
 *
 * Provides two features:
 *
 * 1. Instant new order SMS to admin, fires on woocommerce_checkout_order_processed
 *    (classic checkout) and woocommerce_store_api_checkout_order_processed (block checkout)
 *    (once per order, regardless of payment method or initial status).
 *
 * 2. Vendor SMS, when Dokan, WCFM, or WC Vendors is active, sends an SMS to
 *    the vendor of each ordered product.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Woo_Multivendor
 */
class KwtSMS_Woo_Multivendor {

	/**
	 * Plugin reference.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param KwtSMS_Plugin $plugin Plugin manager.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		if ( ! $this->plugin->settings->get( 'integrations.woo_enabled', 1 ) ) {
			return;
		}

		// Instant new order alert (fires before any status change).
		if ( $this->plugin->settings->get( 'integrations.woo_instant_order_enabled', 0 ) ) {
			// Classic checkout.
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_new_order' ), 10, 3 );
			// Block checkout (WooCommerce Store API).
			add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_new_order_from_block' ), 10, 1 );
		}

		// Vendor SMS (only when a multivendor plugin is detected).
		if ( $this->plugin->settings->get( 'integrations.woo_vendor_sms_enabled', 0 ) ) {
			if ( $this->is_multivendor_active() ) {
				// Classic checkout.
				add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_vendor_order' ), 20, 3 );
				// Block checkout.
				add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_vendor_order_from_block' ), 20, 1 );
			}
		}
	}

	/**
	 * Check if any supported multivendor plugin is active.
	 *
	 * @return bool
	 */
	private function is_multivendor_active() {
		return function_exists( 'dokan' )
			|| class_exists( 'WCFM' )
			|| class_exists( 'WCV_Vendors' );
	}

	/**
	 * Send instant new order SMS to configured admin phone(s).
	 *
	 * @param int           $order_id    New order ID.
	 * @param array         $posted_data Raw checkout POST data (unused).
	 * @param WC_Order|null $order       Order object.
	 */
	public function on_new_order( $order_id, $posted_data, $order = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInMiddle
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			$this->plugin->api->write_debug_log( 'woo_instant_order', 'Skipped instant order SMS: could not load order #' . $order_id );
			return;
		}

		$admin_phone = (string) $this->plugin->settings->get( 'integrations.woo_instant_order_phone', '' );
		if ( '' === trim( $admin_phone ) ) {
			$this->plugin->api->write_debug_log( 'woo_instant_order', 'Skipped instant order SMS for order #' . $order->get_order_number() . ': no admin phone configured' );
			return;
		}

		$decimals = absint( get_option( 'woocommerce_price_num_decimals', 2 ) );
		$total    = number_format( (float) $order->get_total(), $decimals ) . ' ' . get_woocommerce_currency();
		$message  = $this->build_message(
			'woo_tpl_instant_order',
			array(
				'{site_name}'     => get_bloginfo( 'name' ),
				'{order_id}'      => (string) $order->get_order_number(),
				'{customer_name}' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'{total}'         => $total,
			)
		);

		if ( '' === $message ) {
			$this->plugin->api->write_debug_log( 'woo_instant_order', 'Skipped instant order SMS for order #' . $order->get_order_number() . ': template empty or missing' );
			return;
		}

		$sender_id = (string) $this->plugin->settings->get( 'gateway.sender_id', '' );

		$dial_code = KwtSMS_API::get_default_dial_code();
		$phones    = array();
		foreach ( preg_split( '/[\s,]+/', $admin_phone, -1, PREG_SPLIT_NO_EMPTY ) as $raw ) {
			$phones[] = KwtSMS_API::prepend_country_code_if_local( $raw, $dial_code );
		}
		$this->plugin->api->send( $phones, $sender_id, $message, 'woo_instant_order' );
	}

	/**
	 * Send SMS to each vendor whose product is in the new order.
	 *
	 * Supports Dokan, WCFM, and WC Vendors. Vendor phone is read from user
	 * meta key 'kwtsms_phone'. Vendors without a phone are silently skipped.
	 *
	 * @param int           $order_id    New order ID.
	 * @param array         $posted_data Raw checkout POST data (unused).
	 * @param WC_Order|null $order       Order object.
	 */
	public function on_vendor_order( $order_id, $posted_data, $order = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInMiddle
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			$this->plugin->api->write_debug_log( 'vendor_sms', 'Skipped vendor SMS: could not load order #' . $order_id );
			return;
		}

		$decimals  = absint( get_option( 'woocommerce_price_num_decimals', 2 ) );
		$total     = number_format( (float) $order->get_total(), $decimals ) . ' ' . get_woocommerce_currency();
		$sender_id = (string) $this->plugin->settings->get( 'gateway.sender_id', '' );
		$notified  = array(); // Track vendor IDs already notified to avoid duplicate SMS.

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$vendor_id  = $this->get_vendor_id( $product_id );

			if ( ! $vendor_id || in_array( $vendor_id, $notified, true ) ) {
				continue;
			}

			$vendor_phone = (string) get_user_meta( $vendor_id, 'kwtsms_phone', true );
			if ( '' === $vendor_phone ) {
				$this->plugin->api->write_debug_log( 'vendor_sms', 'Skipped vendor ID ' . $vendor_id . ' (no phone on file) for order #' . $order->get_order_number() );
				$notified[] = $vendor_id;
				continue;
			}

			$message = $this->build_message(
				'woo_tpl_vendor_new_order',
				array(
					'{site_name}'    => get_bloginfo( 'name' ),
					'{order_id}'     => (string) $order->get_order_number(),
					'{product_name}' => $item->get_name(),
					'{total}'        => $total,
				)
			);

			if ( '' !== $message ) {
				$this->plugin->api->send( $vendor_phone, $sender_id, $message, 'woo_vendor' );
			} else {
				$this->plugin->api->write_debug_log( 'vendor_sms', 'Skipped vendor SMS for vendor ID ' . $vendor_id . ' on order #' . $order->get_order_number() . ': template empty or missing' );
			}

			$notified[] = $vendor_id;
		}
	}

	/**
	 * Bridge: block checkout passes WC_Order directly, no posted_data.
	 *
	 * @param WC_Order $order New order object.
	 */
	public function on_new_order_from_block( $order ) {
		$this->on_new_order( $order->get_id(), array(), $order );
	}

	/**
	 * Bridge: vendor SMS for block checkout.
	 *
	 * @param WC_Order $order New order object.
	 */
	public function on_vendor_order_from_block( $order ) {
		$this->on_vendor_order( $order->get_id(), array(), $order );
	}

	/**
	 * Resolve the vendor user ID for a product.
	 *
	 * Tries Dokan, WCFM, and WC Vendors in order. Falls back to post author.
	 *
	 * @param int $product_id WooCommerce product ID.
	 *
	 * @return int Vendor user ID, or 0.
	 */
	private function get_vendor_id( $product_id ) {
		// Dokan.
		if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
			$vendor = dokan_get_vendor_by_product( $product_id );
			if ( $vendor && method_exists( $vendor, 'get_id' ) ) {
				return (int) $vendor->get_id();
			}
		}

		// WCFM.
		if ( function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
			return (int) wcfm_get_vendor_id_by_post( $product_id );
		}

		// WC Vendors.
		if ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_from_product' ) ) {
			return (int) WCV_Vendors::get_vendor_from_product( $product_id );
		}

		// Fallback: read post author as vendor.
		$post = get_post( $product_id );
		return $post ? (int) $post->post_author : 0;
	}

	/**
	 * Build a message from integration template settings.
	 *
	 * @param string $tpl_key      Template key under integrations.
	 * @param array  $placeholders Placeholder map.
	 *
	 * @return string Rendered message or empty string.
	 */
	private function build_message( $tpl_key, array $placeholders ) {
		$templates = $this->plugin->settings->get_all_integration_templates();
		$tpl       = $templates[ $tpl_key ] ?? array();

		if ( empty( $tpl['en'] ) ) {
			return '';
		}

		$lang    = ( function_exists( 'is_rtl' ) && is_rtl() ) ? 'ar' : 'en';
		$message = $tpl[ $lang ] ?? $tpl['en'];

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $message );
	}
}
