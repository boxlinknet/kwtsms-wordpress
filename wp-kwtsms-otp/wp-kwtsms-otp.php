<?php
/**
 * Plugin Name:       kwtSMS OTP Authentication
 * Plugin URI:        https://www.kwtsms.com
 * Description:       Secure SMS-based OTP login and password reset for WordPress, powered by the kwtSMS gateway. Supports 2FA mode, passwordless login, Google reCAPTCHA v3, and Cloudflare Turnstile. Fully multilingual (English + Arabic / RTL).
 * Version:           1.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            kwtsms
 * Author URI:        https://www.kwtsms.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-kwtsms-otp
 * Domain Path:       /languages
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'KWTSMS_OTP_VERSION', '1.6.0' );
define( 'KWTSMS_OTP_FILE', __FILE__ );
define( 'KWTSMS_OTP_DIR', plugin_dir_path( __FILE__ ) );
define( 'KWTSMS_OTP_URL', plugin_dir_url( __FILE__ ) );
define( 'KWTSMS_OTP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * Maps class names to files in /includes and /admin.
 * Naming convention: KwtSMS_Foo_Bar → includes/class-kwtsms-foo-bar.php
 *
 * @param string $class_name The fully-qualified class name.
 */
function kwtsms_otp_autoload( $class_name ) {
	// Only handle classes with our prefix.
	if ( strpos( $class_name, 'KwtSMS_' ) !== 0 ) {
		return;
	}

	// Convert class name to file name:
	// KwtSMS_OTP_Engine → class-kwtsms-otp-engine.php
	$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	$locations = array(
		KWTSMS_OTP_DIR . 'includes/' . $file_name,
		KWTSMS_OTP_DIR . 'admin/' . $file_name,
	);

	foreach ( $locations as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
}
spl_autoload_register( 'kwtsms_otp_autoload' );

/**
 * Activation hook — runs when plugin is first activated.
 *
 * Sets default options and records the installed version.
 * Must be registered at top-level (not inside another hook).
 */
function kwtsms_otp_activate() {
	// Set installed version for future upgrade routines.
	update_option( 'kwtsms_otp_version', KWTSMS_OTP_VERSION );

	// Seed default settings if not already present.
	if ( ! get_option( 'kwtsms_otp_general' ) ) {
		update_option(
			'kwtsms_otp_general',
			array(
				'otp_mode'             => '2fa',
				'otp_length'           => 6,
				'otp_expiry'           => 5,
				'max_attempts'         => 3,
				'resend_cooldown'      => 120,
				'login_otp'            => 1,
				'reset_otp'            => 1,
				'captcha_provider'     => 'none',
				'referral_link'        => 1,
				'default_country_code' => 'KW',
				'allowed_countries'    => array( 'KW', 'SA', 'AE', 'BH', 'QA', 'OM' ),
			)
		);
	}
}
register_activation_hook( KWTSMS_OTP_FILE, 'kwtsms_otp_activate' );

/**
 * Deactivation hook.
 *
 * Does not remove data — that is handled by uninstall.php.
 */
function kwtsms_otp_deactivate() {
	// Nothing to flush right now; placeholder for future rewrite rule cleanup.
}
register_deactivation_hook( KWTSMS_OTP_FILE, 'kwtsms_otp_deactivate' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 *
 * Using plugins_loaded ensures other plugins (e.g. WooCommerce) are available
 * before we register hooks that may depend on them.
 */
function kwtsms_otp_init() {
	// Emergency bypass — if defined in wp-config.php, skip all OTP logic.
	// Useful when an admin is locked out. See Help page for instructions.
	if ( defined( 'KWTSMS_OTP_DISABLED' ) && KWTSMS_OTP_DISABLED ) {
		return;
	}

	// Load translations first so all subsequent strings are translatable.
	load_plugin_textdomain(
		'wp-kwtsms-otp',
		false,
		dirname( KWTSMS_OTP_BASENAME ) . '/languages'
	);

	// Boot the main plugin manager.
	KwtSMS_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'kwtsms_otp_init' );
