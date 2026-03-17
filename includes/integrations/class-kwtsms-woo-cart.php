<?php
/**
 * WooCommerce Cart Abandonment Recovery.
 *
 * Detects abandoned carts by storing cart state and phone on cart update,
 * then running a WP Cron job to send a recovery SMS (with optional coupon)
 * after the configured delay. Tracks recovery when an order is placed.
 *
 * Cart records stored in wp_options 'kwtsms_abandoned_carts' as a JSON array.
 * Maximum 500 records; oldest entries pruned automatically.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Woo_Cart
 */
class KwtSMS_Woo_Cart {

	/**
	 * WP options key for abandoned cart records.
	 */
	const CARTS_OPTION = 'kwtsms_abandoned_carts';

	/**
	 * WP Cron event name.
	 */
	const CRON_EVENT = 'kwtsms_check_abandoned_carts';

	/**
	 * Maximum number of cart records to keep.
	 */
	const MAX_RECORDS = 500;

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

		if ( ! $this->plugin->settings->get( 'integrations.woo_cart_abandon_enabled', 0 ) ) {
			return;
		}

		// Track cart updates (when phone is known).
		add_action( 'woocommerce_cart_updated', array( $this, 'on_cart_updated' ) );

		// Clear abandoned state when order is placed.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'on_order_created' ) );

		// Schedule cron if not already scheduled.
		if ( ! wp_next_scheduled( self::CRON_EVENT ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_EVENT );
		}

		add_action( self::CRON_EVENT, array( $this, 'process_abandoned_carts' ) );
	}

	/**
	 * Record or update a cart entry when the cart is modified.
	 *
	 * Only runs when a phone number is known (logged-in user with kwtsms_phone,
	 * or phone entered in checkout fields and stored in WC session).
	 */
	public function on_cart_updated() {
		$phone = $this->resolve_phone();
		if ( '' === $phone ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			$this->remove_cart_record( $phone );
			return;
		}

		$total      = wp_strip_all_tags( WC()->cart->get_cart_total() );
		$first_name = '';

		$user_id = get_current_user_id();
		if ( $user_id ) {
			$first_name = (string) get_user_meta( $user_id, 'first_name', true );
		}
		if ( '' === $first_name && WC()->customer ) {
			$first_name = (string) WC()->customer->get_billing_first_name();
		}

		$this->upsert_cart_record(
			array(
				'phone'      => $phone,
				'cart_total' => $total,
				'first_name' => sanitize_text_field( $first_name ),
				'timestamp'  => time(),
				'sms_sent'   => false,
				'recovered'  => false,
			)
		);
	}

	/**
	 * Mark a cart record as recovered when an order is placed.
	 *
	 * @param mixed $order The newly created order (WC_Order when WC is active).
	 */
	public function on_order_created( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$user_id = $order->get_customer_id();
		$phone   = '';

		if ( $user_id ) {
			$phone = (string) get_user_meta( $user_id, 'kwtsms_phone', true );
		}
		if ( '' === $phone ) {
			$raw  = (string) $order->get_billing_phone();
			$raw  = KwtSMS_API::prepend_country_code_if_local( $raw, KwtSMS_API::get_default_dial_code() );
			$norm = KwtSMS_API::normalize_phone( $raw );
			if ( ! is_wp_error( $norm ) ) {
				$phone = $norm;
			}
		}

		if ( '' !== $phone ) {
			$this->mark_recovered( $phone );
		}
	}

	/**
	 * Cron job: scan records, send SMS to carts abandoned beyond the delay.
	 */
	public function process_abandoned_carts() {
		$this->with_cart_lock(
			function () {
				$carts = $this->get_carts();
				if ( empty( $carts ) ) {
					return;
				}

				$delay_minutes = (int) $this->plugin->settings->get( 'integrations.woo_cart_abandon_delay', 60 );
				$cutoff        = time() - ( $delay_minutes * MINUTE_IN_SECONDS );
				$changed       = false;

				foreach ( $carts as &$cart ) {
					if ( $cart['sms_sent'] || $cart['recovered'] ) {
						continue;
					}
					if ( $cart['timestamp'] > $cutoff ) {
						continue; // Not yet old enough.
					}

					$sent = $this->send_recovery_sms( $cart );
					if ( $sent ) {
						$cart['sms_sent'] = true;
						$changed          = true;
					}
				}
				unset( $cart );

				if ( $changed ) {
					update_option( self::CARTS_OPTION, wp_json_encode( $carts ), false );
				}
			}
		);
	}

	/**
	 * Send a cart recovery SMS to the given cart record.
	 *
	 * @param array $cart Cart record.
	 *
	 * @return bool True if SMS was sent successfully.
	 */
	private function send_recovery_sms( array $cart ) {
		$templates = $this->plugin->settings->get_all_integration_templates();
		$tpl       = isset( $templates['woo_tpl_cart_abandon'] ) ? $templates['woo_tpl_cart_abandon'] : array();

		if ( empty( $tpl['en'] ) ) {
			$this->plugin->api->write_debug_log( 'cart_abandon', 'Skipped cart recovery SMS for phone ' . $cart['phone'] . ': template empty or missing' );
			return false;
		}

		$lang    = ( function_exists( 'is_rtl' ) && is_rtl() ) ? 'ar' : 'en';
		$message = isset( $tpl[ $lang ] ) ? $tpl[ $lang ] : $tpl['en'];

		$discount    = (int) $this->plugin->settings->get( 'integrations.woo_cart_abandon_coupon', 10 );
		$coupon_code = '';

		if ( $discount > 0 ) {
			$coupon_code = $this->create_coupon( $cart['phone'], $discount );
		}

		$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

		$message = str_replace(
			array( '{first_name}', '{cart_total}', '{coupon_code}', '{discount}', '{cart_url}', '{site_name}' ),
			array( $cart['first_name'], $cart['cart_total'], $coupon_code, (string) $discount, $cart_url, get_bloginfo( 'name' ) ),
			$message
		);

		$result = $this->plugin->api->send_sms(
			$cart['phone'],
			(string) $this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'cart_abandon'
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Create a single-use WooCommerce coupon for cart recovery.
	 *
	 * @param string $phone    Customer phone (used to generate a unique code).
	 * @param int    $discount Discount percentage.
	 *
	 * @return string Coupon code.
	 */
	private function create_coupon( $phone, $discount ) {
		$code   = 'KWTSMS' . strtoupper( bin2hex( random_bytes( 4 ) ) );
		$expiry = (int) $this->plugin->settings->get( 'integrations.woo_cart_abandon_expiry', 48 );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( (float) $discount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_date_expires( time() + $expiry * HOUR_IN_SECONDS );
		$coupon->save();
		if ( ! $coupon->get_id() ) {
			return '';
		}
		return $code;
	}

	// =========================================================================
	// Cart record helpers
	// =========================================================================

	/**
	 * Get all cart records.
	 *
	 * @return array
	 */
	private function get_carts() {
		$raw = get_option( self::CARTS_OPTION, '[]' );
		$arr = json_decode( (string) $raw, true );
		return is_array( $arr ) ? $arr : array();
	}

	/**
	 * Acquire a MySQL advisory lock, run a callback, then release the lock.
	 *
	 * Prevents concurrent read-modify-write races on the cart option. Falls back
	 * gracefully on non-MySQL environments (e.g. SQLite in WP Playground) where
	 * GET_LOCK is not supported.
	 *
	 * @param callable $callback Callback to execute inside the lock.
	 */
	private function with_cart_lock( callable $callback ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$locked = $wpdb->get_var( "SELECT GET_LOCK('kwtsms_cart_lock', 5)" );
		try {
			$callback();
		} finally {
			if ( '1' === (string) $locked ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( "SELECT RELEASE_LOCK('kwtsms_cart_lock')" );
			}
		}
	}

	/**
	 * Insert or update a cart record (keyed by phone).
	 *
	 * @param array $record Cart record array.
	 */
	private function upsert_cart_record( array $record ) {
		$this->with_cart_lock(
			function () use ( $record ) {
				$carts = $this->get_carts();

				$found = false;
				foreach ( $carts as &$existing ) {
					if ( $existing['phone'] === $record['phone'] ) {
						$existing['cart_total'] = $record['cart_total'];
						$existing['first_name'] = $record['first_name'];
						$existing['timestamp']  = $record['timestamp'];
						$existing['recovered']  = false;
						$existing['sms_sent']   = false;
						$found                  = true;
						break;
					}
				}
				unset( $existing );

				if ( ! $found ) {
					$carts[] = $record;
				}

				// Prune oldest records if over limit.
				if ( count( $carts ) > self::MAX_RECORDS ) {
					usort(
						$carts,
						function ( $a, $b ) {
							return $a['timestamp'] - $b['timestamp'];
						}
					);
					$carts = array_slice( $carts, -self::MAX_RECORDS );
				}

				update_option( self::CARTS_OPTION, wp_json_encode( $carts ), false );
			}
		);
	}

	/**
	 * Remove a cart record by phone (called when cart is emptied).
	 *
	 * @param string $phone Normalized phone number.
	 */
	private function remove_cart_record( $phone ) {
		$this->with_cart_lock(
			function () use ( $phone ) {
				$carts = $this->get_carts();
				$carts = array_filter(
					$carts,
					function ( $c ) use ( $phone ) {
						// Keep records from other phones, and keep recovered records for stats.
						return $c['phone'] !== $phone || ! empty( $c['recovered'] );
					}
				);
				update_option( self::CARTS_OPTION, wp_json_encode( array_values( $carts ) ), false );
			}
		);
	}

	/**
	 * Mark a phone's cart record as recovered.
	 *
	 * @param string $phone Normalized phone number.
	 */
	private function mark_recovered( $phone ) {
		$this->with_cart_lock(
			function () use ( $phone ) {
				$carts   = $this->get_carts();
				$changed = false;
				foreach ( $carts as &$cart ) {
					if ( $cart['phone'] === $phone && ! $cart['recovered'] ) {
						$cart['recovered'] = true;
						$changed           = true;
					}
				}
				unset( $cart );
				if ( $changed ) {
					update_option( self::CARTS_OPTION, wp_json_encode( $carts ), false );
				}
			}
		);
	}

	/**
	 * Resolve the current user's phone for cart tracking.
	 *
	 * Priority: kwtsms_phone user meta, then WC billing phone from session.
	 *
	 * @return string Normalized phone, or empty string if not available.
	 */
	private function resolve_phone() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$phone = (string) get_user_meta( $user_id, 'kwtsms_phone', true );
			if ( '' !== $phone ) {
				return $phone;
			}
		}

		// Try WC session billing phone (entered but not yet submitted).
		if ( function_exists( 'WC' ) && WC()->session ) {
			$customer = WC()->session->get( 'customer' );
			if ( is_array( $customer ) ) {
				$raw = isset( $customer['phone'] ) ? $customer['phone'] : '';
				if ( '' !== $raw ) {
					$raw  = KwtSMS_API::prepend_country_code_if_local( $raw, KwtSMS_API::get_default_dial_code() );
					$norm = KwtSMS_API::normalize_phone( $raw );
					return is_wp_error( $norm ) ? '' : $norm;
				}
			}
		}

		return '';
	}

	// =========================================================================
	// Dashboard card data
	// =========================================================================

	/**
	 * Get cart abandonment stats for the dashboard widget.
	 *
	 * @return array{total: int, sms_sent: int, recovered: int, rate: int}
	 */
	public function get_stats() {
		$carts     = $this->get_carts();
		$total     = count( $carts );
		$sms_sent  = count(
			array_filter(
				$carts,
				function ( $c ) {
					return $c['sms_sent'];
				}
			)
		);
		$recovered = count(
			array_filter(
				$carts,
				function ( $c ) {
					return $c['recovered'] && $c['sms_sent'];
				}
			)
		);
		$rate      = $sms_sent > 0 ? (int) round( $recovered / $sms_sent * 100 ) : 0;

		return compact( 'total', 'sms_sent', 'recovered', 'rate' );
	}
}
