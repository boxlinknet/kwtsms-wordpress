<?php
/**
 * PHPUnit bootstrap for wp-kwtsms.
 *
 * Uses Brain\Monkey to mock WordPress functions without a full WP install.
 *
 * @package KwtSMS_OTP
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Brain\Monkey setup is done in each test's setUp/tearDown via the trait.
// Load plugin classes directly (they guard with defined('ABSPATH') || exit).
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'KWTSMS_OTP_VERSION' ) ) {
	define( 'KWTSMS_OTP_VERSION', '1.0.0-test' );
}
if ( ! defined( 'KWTSMS_OTP_DIR' ) ) {
	define( 'KWTSMS_OTP_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'KWTSMS_OTP_URL' ) ) {
	define( 'KWTSMS_OTP_URL', 'http://example.com/wp-content/plugins/wp-kwtsms/' );
}

require_once __DIR__ . '/../includes/class-kwtsms-api.php';
require_once __DIR__ . '/../includes/class-kwtsms-settings.php';
require_once __DIR__ . '/../includes/class-kwtsms-captcha.php';
require_once __DIR__ . '/../includes/class-kwtsms-otp-engine.php';
require_once __DIR__ . '/../includes/class-kwtsms-reset-otp.php';
require_once __DIR__ . '/../includes/class-kwtsms-user-meta.php';
require_once __DIR__ . '/../includes/class-kwtsms-integrations.php';
require_once __DIR__ . '/../includes/integrations/class-kwtsms-woo.php';
require_once __DIR__ . '/../includes/integrations/class-kwtsms-woo-metabox.php';
require_once __DIR__ . '/../includes/integrations/class-kwtsms-cf7.php';
require_once __DIR__ . '/../includes/integrations/class-kwtsms-wpforms.php';
require_once __DIR__ . '/../includes/integrations/class-kwtsms-elementor.php';
require_once __DIR__ . '/../includes/integrations/class-kwtsms-gravityforms.php';
require_once __DIR__ . '/../includes/integrations/class-kwtsms-ninjaforms.php';

/**
 * Minimal WC_Order stub so getMockBuilder can create WC_Order mocks without
 * a WooCommerce installation. Only method stubs are needed — actual method
 * bodies are never called because tests override them via willReturn/willReturnCallback.
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
		public function get_billing_phone_raw() {}
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
 * Minimal KwtSMS_Plugin stub for unit tests.
 *
 * The real KwtSMS_Plugin has a private constructor (singleton) and depends on
 * the full WordPress environment. This stub satisfies the type-hints on
 * KwtSMS_Woo and KwtSMS_Integrations so they can be unit-tested without
 * bootstrapping WordPress.
 */
if ( ! class_exists( 'KwtSMS_Plugin' ) ) {
	// phpcs:ignore
	class KwtSMS_Plugin {
		/** @var KwtSMS_Settings|null */
		public $settings = null;
		/** @var KwtSMS_API|null */
		public $api = null;
		/** @var KwtSMS_OTP_Engine|null */
		public $otp = null;

		/**
		 * Stub for the form OTP token verifier.
		 *
		 * In unit tests this is either mocked (addMethods) or the real
		 * implementation is loaded from class-kwtsms-plugin.php. This stub
		 * ensures the method exists on the class so getMockBuilder can target it.
		 *
		 * @param string $token 32-char hex token.
		 * @return bool
		 */
		public function verify_form_token( $token ) {
			$token = trim( $token );
			if ( empty( $token ) || ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
				return false;
			}
			$data = get_transient( 'kwtsms_form_otp_' . $token );
			if ( ! is_array( $data ) ) {
				return false;
			}
			return ! empty( $data['verified'] );
		}

		/**
		 * Stub for the form OTP verify AJAX handler.
		 *
		 * Mirrors the production logic in KwtSMS_Plugin::ajax_form_verify_otp()
		 * so that unit tests can exercise attempt-counter and lockout behaviour
		 * using Brain\Monkey WP-function stubs without a full WordPress install.
		 */
		public function ajax_form_verify_otp() {
			check_ajax_referer( 'kwtsms_form_otp_nonce', 'nonce' );

			$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
			$code  = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );

			if ( empty( $token ) || empty( $code ) ) {
				wp_send_json_error( array( 'message' => __( 'Token and code are required.', 'wp-kwtsms' ) ) );
				return;
			}

			if ( ! preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid token.', 'wp-kwtsms' ) ) );
				return;
			}

			$transient_key = 'kwtsms_form_otp_' . $token;
			$data          = get_transient( $transient_key );

			if ( ! is_array( $data ) ) {
				wp_send_json_error( array( 'message' => __( 'Session expired. Please request a new code.', 'wp-kwtsms' ) ) );
				return;
			}

			// Enforce max 5 attempts per token.
			$attempts = (int) ( $data['attempts'] ?? 0 );
			if ( $attempts >= 5 ) {
				delete_transient( $transient_key );
				wp_send_json_error( array( 'message' => __( 'Too many incorrect attempts. Please request a new code.', 'wp-kwtsms' ) ) );
				return;
			}

			// Verify the submitted code against the stored bcrypt hash.
			if ( ! wp_check_password( $code, $data['otp_hash'] ) ) {
				$data['attempts'] = $attempts + 1;
				set_transient( $transient_key, $data, 900 );
				wp_send_json_error( array( 'message' => __( 'Incorrect code. Please try again.', 'wp-kwtsms' ) ) );
				return;
			}

			// Mark as verified; keep the transient alive for the form submission check.
			$data['verified'] = true;
			set_transient( $transient_key, $data, 900 );

			wp_send_json_success( array( 'message' => __( 'Phone number verified successfully.', 'wp-kwtsms' ) ) );
		}
	}
}
