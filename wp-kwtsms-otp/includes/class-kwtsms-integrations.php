<?php
/**
 * Integration Loader.
 *
 * Detects active third-party plugins and boots the corresponding integration
 * classes. Each integration is self-contained and lazy-loaded only when
 * the parent plugin is active.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Integrations
 *
 * Scans for supported third-party plugins at plugins_loaded priority 20
 * (after all plugins have had a chance to declare themselves) and instantiates
 * the corresponding integration class when found.
 */
class KwtSMS_Integrations {

	/**
	 * Reference to the main plugin instance.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * Schedules integration boot at plugins_loaded priority 20 so that
	 * third-party plugin classes (e.g. WooCommerce) are fully loaded before
	 * we try to detect them.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;
		// Boot integrations after all plugins are loaded (priority 20 so WC is ready).
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	/**
	 * Detect active plugins and instantiate their integrations.
	 *
	 * Each integration file is only require_once'd when the corresponding
	 * plugin is active — keeping the plugin footprint minimal on sites that
	 * do not use these third-party tools.
	 */
	public function boot() {
		if ( class_exists( 'WooCommerce' ) ) {
			require_once KWTSMS_OTP_DIR . 'includes/integrations/class-kwtsms-woo.php';
			new KwtSMS_Woo( $this->plugin );
		}
		// Future: CF7, WPForms, Elementor integrations go here.
	}
}
