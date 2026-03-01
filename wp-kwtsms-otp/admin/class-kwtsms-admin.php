<?php
/**
 * Admin Controller — menus, settings registration, admin notices.
 *
 * Registers three subpages under a top-level "kwtSMS" menu:
 *   - General Settings  (OTP behaviour, CAPTCHA)
 *   - Gateway Settings  (API credentials, SenderID, balance)
 *   - SMS Templates     (EN + AR templates with character counters)
 *
 * Settings are registered via the WordPress Settings API with sanitize callbacks.
 * Scripts and styles are enqueued only on plugin admin pages.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Admin
 */
class KwtSMS_Admin {

	/**
	 * Plugin manager.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Admin page hook suffixes (used to scope asset enqueuing).
	 *
	 * @var string[]
	 */
	private $page_hooks = array();

	/**
	 * Constructor.
	 *
	 * @param KwtSMS_Plugin $plugin Plugin manager.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'wp_ajax_kwtsms_get_coverage', array( $this, 'ajax_get_coverage' ) );
		add_action( 'wp_ajax_kwtsms_clear_log', array( $this, 'ajax_clear_log' ) );
		add_action( 'wp_ajax_kwtsms_logout_gateway', array( $this, 'ajax_logout_gateway' ) );
	}

	// =========================================================================
	// Menu registration
	// =========================================================================

	/**
	 * Register the top-level admin menu and subpages.
	 */
	public function register_menus() {
		$this->page_hooks[] = add_menu_page(
			__( 'kwtSMS', 'wp-kwtsms-otp' ),
			__( 'kwtSMS', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp',
			array( $this, 'render_general_page' ),
			'dashicons-format-chat',
			80
		);

		$this->page_hooks[] = add_submenu_page(
			'kwtsms-otp',
			__( 'General Settings', 'wp-kwtsms-otp' ),
			__( 'General', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp',
			array( $this, 'render_general_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'kwtsms-otp',
			__( 'Gateway Settings', 'wp-kwtsms-otp' ),
			__( 'Gateway', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp-gateway',
			array( $this, 'render_gateway_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'kwtsms-otp',
			__( 'SMS Templates', 'wp-kwtsms-otp' ),
			__( 'Templates', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp-templates',
			array( $this, 'render_templates_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'kwtsms-otp',
			__( 'Integrations', 'wp-kwtsms-otp' ),
			__( 'Integrations', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp-integrations',
			array( $this, 'render_integrations_page' )
		);

		// Conditionally register sub-pages for each active integration.
		$integrations_active = array(
			'woo'       => class_exists( 'WooCommerce' ),
			'cf7'       => class_exists( 'WPCF7' ),
			'wpforms'   => function_exists( 'wpforms' ) || class_exists( 'WPForms\WPForms' ),
			'elementor' => did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ),
			'gf'        => class_exists( 'GFForms' ),
			'nf'        => class_exists( 'Ninja_Forms' ),
		);

		$int_labels = array(
			'woo'       => __( 'WooCommerce', 'wp-kwtsms-otp' ),
			'cf7'       => __( 'Contact Form 7', 'wp-kwtsms-otp' ),
			'wpforms'   => __( 'WPForms', 'wp-kwtsms-otp' ),
			'elementor' => __( 'Elementor', 'wp-kwtsms-otp' ),
			'gf'        => __( 'Gravity Forms', 'wp-kwtsms-otp' ),
			'nf'        => __( 'Ninja Forms', 'wp-kwtsms-otp' ),
		);

		foreach ( $integrations_active as $key => $active ) {
			if ( ! $active ) {
				continue;
			}
			$this->page_hooks[] = add_submenu_page(
				'kwtsms-otp',
				sprintf( __( '%s Settings', 'wp-kwtsms-otp' ), $int_labels[ $key ] ),
				'&#8627; ' . $int_labels[ $key ],
				'manage_options',
				'kwtsms-otp-int-' . $key,
				array( $this, 'render_int_' . $key . '_page' )
			);
		}

		$this->page_hooks[] = add_submenu_page(
			'kwtsms-otp',
			__( 'kwtSMS Logs', 'wp-kwtsms-otp' ),
			__( 'Logs', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp-logs',
			array( $this, 'render_logs_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'kwtsms-otp',
			__( 'kwtSMS Help & Support', 'wp-kwtsms-otp' ),
			__( 'Help', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp-help',
			array( $this, 'render_help_page' )
		);
	}

	// =========================================================================
	// Settings registration
	// =========================================================================

	/**
	 * Register all plugin settings with the WordPress Settings API.
	 *
	 * Sanitize callbacks ensure no unsafe data is stored.
	 */
	public function register_settings() {
		// ----- General settings -----
		register_setting(
			'kwtsms_otp_general_group',
			'kwtsms_otp_general',
			array(
				'sanitize_callback' => array( $this, 'sanitize_general_settings' ),
			)
		);

		add_settings_section(
			'kwtsms_general_otp_behavior',
			__( 'OTP Behaviour', 'wp-kwtsms-otp' ),
			'__return_null',
			'kwtsms_general_settings'
		);

		add_settings_section(
			'kwtsms_general_captcha',
			__( 'CAPTCHA Protection', 'wp-kwtsms-otp' ),
			'__return_null',
			'kwtsms_general_settings'
		);

		// ----- Gateway settings -----
		register_setting(
			'kwtsms_otp_gateway_group',
			'kwtsms_otp_gateway',
			array(
				'sanitize_callback' => array( $this, 'sanitize_gateway_settings' ),
			)
		);

		// ----- Template settings -----
		register_setting(
			'kwtsms_otp_templates_group',
			'kwtsms_otp_templates',
			array(
				'sanitize_callback' => array( $this, 'sanitize_template_settings' ),
			)
		);

		// ----- Integration settings -----
		register_setting(
			'kwtsms_otp_integrations_group',
			'kwtsms_otp_integrations',
			array(
				'sanitize_callback' => array( $this, 'sanitize_integrations_settings' ),
			)
		);
	}

	// =========================================================================
	// Sanitize callbacks
	// =========================================================================

	/**
	 * Sanitize general settings.
	 *
	 * @param mixed $raw Raw form input.
	 *
	 * @return array Sanitized settings array.
	 */
	public function sanitize_general_settings( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$defaults = KwtSMS_Settings::DEFAULTS['general'];

		// Sanitize allowed_countries: must be an array of valid ISO2 codes (2 uppercase letters).
		$allowed_raw = $raw['allowed_countries'] ?? $defaults['allowed_countries'];
		if ( ! is_array( $allowed_raw ) ) {
			// Accept JSON string from hidden input.
			$decoded = json_decode( stripslashes( (string) $allowed_raw ), true );
			$allowed_raw = is_array( $decoded ) ? $decoded : $defaults['allowed_countries'];
		}
		$allowed_countries = array_values(
			array_filter(
				array_map( 'strtoupper', array_map( 'sanitize_text_field', $allowed_raw ) ),
				static function( $code ) {
					return preg_match( '/^[A-Z]{2}$/', $code );
				}
			)
		);
		if ( empty( $allowed_countries ) ) {
			$allowed_countries = $defaults['allowed_countries'];
		}

		// Sanitize default_country_code: must be a 2-letter uppercase ISO2 code.
		$default_cc = strtoupper( sanitize_text_field( $raw['default_country_code'] ?? $defaults['default_country_code'] ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $default_cc ) ) {
			$default_cc = $defaults['default_country_code'];
		}

		// Sanitize otp_required_roles: allow only role slugs that exist in WP.
		$all_roles        = array_keys( wp_roles()->get_names() );
		$raw_roles        = array_map( 'sanitize_text_field', (array) ( $raw['otp_required_roles'] ?? array() ) );
		$otp_required_roles = array_values( array_intersect( $raw_roles, $all_roles ) );

		return array(
			'otp_mode'             => in_array( $raw['otp_mode'] ?? '', array( '2fa', 'passwordless', 'both' ), true )
				? $raw['otp_mode']
				: $defaults['otp_mode'],
			'otp_length'           => in_array( (int) ( $raw['otp_length'] ?? 6 ), array( 4, 6 ), true )
				? (int) $raw['otp_length']
				: 6,
			'otp_expiry'           => max( 1, min( 30, absint( $raw['otp_expiry'] ?? 5 ) ) ),
			'max_attempts'         => max( 1, min( 10, absint( $raw['max_attempts'] ?? 3 ) ) ),
			'resend_cooldown'      => max( 30, min( 600, absint( $raw['resend_cooldown'] ?? 120 ) ) ),
			'login_otp'            => ! empty( $raw['login_otp'] ) ? 1 : 0,
			'reset_otp'            => ! empty( $raw['reset_otp'] ) ? 1 : 0,
			'captcha_provider'     => in_array( $raw['captcha_provider'] ?? '', array( 'none', 'recaptcha', 'turnstile' ), true )
				? $raw['captcha_provider']
				: 'none',
			'recaptcha_site_key'   => sanitize_text_field( $raw['recaptcha_site_key'] ?? '' ),
			'recaptcha_secret_key' => sanitize_text_field( $raw['recaptcha_secret_key'] ?? '' ),
			'turnstile_site_key'   => sanitize_text_field( $raw['turnstile_site_key'] ?? '' ),
			'turnstile_secret_key' => sanitize_text_field( $raw['turnstile_secret_key'] ?? '' ),
			'referral_link'        => ! empty( $raw['referral_link'] ) ? 1 : 0,
			'default_country_code' => $default_cc,
			'allowed_countries'    => $allowed_countries,
			'debug_logging'        => ! empty( $raw['debug_logging'] ) ? 1 : 0,
			'blocked_phones'       => sanitize_textarea_field( wp_unslash( $raw['blocked_phones'] ?? '' ) ),
			'otp_required_roles'   => $otp_required_roles,
		);
	}

	/**
	 * Sanitize gateway settings.
	 *
	 * @param mixed $raw Raw form input.
	 *
	 * @return array Sanitized settings array.
	 */
	public function sanitize_gateway_settings( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$test_phone = sanitize_text_field( $raw['test_phone'] ?? '' );
		if ( ! empty( $test_phone ) ) {
			$normalized = KwtSMS_API::normalize_phone( $test_phone );
			$test_phone = is_wp_error( $normalized ) ? '' : $normalized;
		}

		// Warn if the API username looks like a phone number.
		$api_username_raw = sanitize_text_field( $raw['api_username'] ?? '' );
		if ( ! empty( $api_username_raw ) && preg_match( '/^\+?[\d\s()\-]{8,}$/', $api_username_raw ) ) {
			add_settings_error(
				'kwtsms_otp_gateway',
				'phone_as_username',
				__( 'API Username appears to be a phone number. Please enter your kwtSMS API username, not your phone number. Sign up at kwtsms.com to obtain API access.', 'wp-kwtsms-otp' ),
				'warning'
			);
		}

		// Preserve credentials_verified flag only if the credentials are unchanged.
		$current_gw          = get_option( 'kwtsms_otp_gateway', array() );
		$api_password_raw    = sanitize_text_field( $raw['api_password'] ?? '' );
		$creds_unchanged     = (
			$api_username_raw === ( $current_gw['api_username'] ?? '' ) &&
			$api_password_raw === ( $current_gw['api_password'] ?? '' )
		);
		$credentials_verified = $creds_unchanged ? (int) ( $current_gw['credentials_verified'] ?? 0 ) : 0;

		// Carry over previously fetched gateway data by default.
		$sender_ids        = $current_gw['sender_ids']         ?? array();
		$balance_available = $current_gw['balance_available']  ?? null;
		$balance_purchased = $current_gw['balance_purchased']  ?? null;
		$balance_updated   = $current_gw['balance_updated_at'] ?? 0;
		$coverage          = $current_gw['coverage']           ?? array();
		$sender_id_out     = sanitize_text_field( $raw['sender_id'] ?? '' );

		// Auto-verify when credentials are new or changed.
		if ( ! $creds_unchanged && ! empty( $api_username_raw ) && ! empty( $api_password_raw ) ) {
			$api               = new KwtSMS_API( $api_username_raw, $api_password_raw, false );
			$sender_ids_result = $api->get_sender_ids();

			if ( is_wp_error( $sender_ids_result ) ) {
				add_settings_error(
					'kwtsms_otp_gateway',
					'credentials_invalid',
					sprintf(
						/* translators: %s: error message from kwtSMS API */
						__( 'API credentials could not be verified: %s', 'wp-kwtsms-otp' ),
						$sender_ids_result->get_error_message()
					),
					'error'
				);
			} else {
				$credentials_verified = 1;
				$sender_ids           = $sender_ids_result;

				// Auto-select first sender ID if none was previously saved.
				if ( empty( $sender_id_out ) && ! empty( $sender_ids ) ) {
					$sender_id_out = $sender_ids[0];
				}

				$balance_result    = $api->get_balance();
				$coverage_result   = $api->get_coverage();

				if ( ! is_wp_error( $balance_result ) ) {
					$balance_available = $balance_result['available'] ?? null;
					$balance_purchased = $balance_result['purchased'] ?? null;
					$balance_updated   = time();
				}
				if ( ! is_wp_error( $coverage_result ) ) {
					$coverage = (array) $coverage_result;
				}

				add_settings_error(
					'kwtsms_otp_gateway',
					'credentials_verified',
					__( 'API credentials verified successfully.', 'wp-kwtsms-otp' ),
					'success'
				);
			}
		}

		return array(
			'api_username'         => $api_username_raw,
			'api_password'         => $api_password_raw,
			'sender_id'            => $sender_id_out,
			'test_mode'            => ! empty( $raw['test_mode'] ) ? 1 : 0,
			'test_phone'           => $test_phone,
			'credentials_verified' => $credentials_verified,
			'sender_ids'           => $sender_ids,
			'balance_available'    => $balance_available,
			'balance_purchased'    => $balance_purchased,
			'balance_updated_at'   => $balance_updated,
			'coverage'             => $coverage,
		);
	}

	/**
	 * Sanitize template settings.
	 *
	 * Strips HTML tags and emojis from message templates.
	 *
	 * @param mixed $raw Raw form input.
	 *
	 * @return array Sanitized settings array.
	 */
	public function sanitize_template_settings( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$sanitized = array();
		$allowed_keys = array( 'login_otp', 'reset_otp', 'welcome_sms' );

		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
				continue;
			}

			$template = $raw[ $key ];
			$sanitized[ $key ] = array(
				'enabled' => ! empty( $template['enabled'] ) ? 1 : 0,
				'en'      => $this->sanitize_template_content( $template['en'] ?? '' ),
				'ar'      => $this->sanitize_template_content( $template['ar'] ?? '' ),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize integration settings.
	 *
	 * Handles both boolean enable flags and template content arrays.
	 * Uses the currently saved option as a base so that saving one
	 * integration's sub-form does not wipe the settings of others.
	 * The hidden `_save_section` field identifies which sub-form was
	 * submitted; 'all' (or absent) means the legacy full-page form.
	 *
	 * @param mixed $raw Raw form input.
	 *
	 * @return array Sanitized settings array.
	 */
	public function sanitize_integrations_settings( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		// Start from currently saved values so that saving one integration's
		// sub-form does not wipe the other integrations' settings.
		$current = get_option( 'kwtsms_otp_integrations', array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		// _save_section identifies which integration sub-form was submitted.
		// 'all' or empty means the legacy full-page form — sanitize everything.
		$section = sanitize_key( $raw['_save_section'] ?? 'all' );

		$valid_modes = array( 'notification', 'gate' );

		$allowed_statuses  = array_map( 'sanitize_key', array(
			'processing', 'on-hold', 'completed', 'cancelled',
			'pending', 'refunded', 'failed',
		) );
		$raw_notify        = $raw['woo_notify_admin_statuses'] ?? array();
		$notify_admin_statuses = array_values(
			array_filter(
				(array) $raw_notify,
				function ( $s ) use ( $allowed_statuses ) {
					return in_array( sanitize_key( $s ), $allowed_statuses, true );
				}
			)
		);

		// Use current saved values as base; overwrite only the submitted section.
		$sanitized = $current;

		$update_woo       = in_array( $section, array( 'all', 'woo' ), true );
		$update_cf7       = in_array( $section, array( 'all', 'cf7' ), true );
		$update_wpforms   = in_array( $section, array( 'all', 'wpforms' ), true );
		$update_elementor = in_array( $section, array( 'all', 'elementor' ), true );
		$update_gf        = in_array( $section, array( 'all', 'gf' ), true );
		$update_nf        = in_array( $section, array( 'all', 'nf' ), true );

		if ( $update_woo ) {
			$sanitized['woo_enabled']              = ! empty( $raw['woo_enabled'] ) ? 1 : 0;
			$sanitized['woo_checkout_otp']         = ! empty( $raw['woo_checkout_otp'] ) ? 1 : 0;
			$sanitized['woo_admin_phone']          = sanitize_text_field( wp_unslash( $raw['woo_admin_phone'] ?? '' ) );
			$sanitized['woo_notify_admin_statuses'] = $notify_admin_statuses;
			foreach ( array( 'woo_processing', 'woo_shipped', 'woo_completed', 'woo_cancelled', 'woo_pending', 'woo_refunded', 'woo_failed' ) as $key ) {
				if ( isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) {
					$sanitized[ $key ] = array(
						'enabled' => ! empty( $raw[ $key ]['enabled'] ) ? 1 : 0,
						'en'      => $this->sanitize_template_content( $raw[ $key ]['en'] ?? '' ),
						'ar'      => $this->sanitize_template_content( $raw[ $key ]['ar'] ?? '' ),
					);
				}
			}
		}

		if ( $update_cf7 ) {
			$sanitized['cf7_enabled'] = ! empty( $raw['cf7_enabled'] ) ? 1 : 0;
			$sanitized['cf7_mode']    = in_array( $raw['cf7_mode'] ?? '', $valid_modes, true ) ? $raw['cf7_mode'] : 'notification';
			if ( isset( $raw['cf7_confirmation'] ) && is_array( $raw['cf7_confirmation'] ) ) {
				$sanitized['cf7_confirmation'] = array(
					'enabled' => ! empty( $raw['cf7_confirmation']['enabled'] ) ? 1 : 0,
					'en'      => $this->sanitize_template_content( $raw['cf7_confirmation']['en'] ?? '' ),
					'ar'      => $this->sanitize_template_content( $raw['cf7_confirmation']['ar'] ?? '' ),
				);
			}
		}

		if ( $update_wpforms ) {
			$sanitized['wpforms_enabled'] = ! empty( $raw['wpforms_enabled'] ) ? 1 : 0;
			$sanitized['wpforms_mode']    = in_array( $raw['wpforms_mode'] ?? '', $valid_modes, true ) ? $raw['wpforms_mode'] : 'notification';
			if ( isset( $raw['wpforms_confirmation'] ) && is_array( $raw['wpforms_confirmation'] ) ) {
				$sanitized['wpforms_confirmation'] = array(
					'enabled' => ! empty( $raw['wpforms_confirmation']['enabled'] ) ? 1 : 0,
					'en'      => $this->sanitize_template_content( $raw['wpforms_confirmation']['en'] ?? '' ),
					'ar'      => $this->sanitize_template_content( $raw['wpforms_confirmation']['ar'] ?? '' ),
				);
			}
		}

		if ( $update_elementor ) {
			$sanitized['elementor_enabled'] = ! empty( $raw['elementor_enabled'] ) ? 1 : 0;
			$sanitized['elementor_mode']    = in_array( $raw['elementor_mode'] ?? '', $valid_modes, true ) ? $raw['elementor_mode'] : 'notification';
			if ( isset( $raw['elementor_confirmation'] ) && is_array( $raw['elementor_confirmation'] ) ) {
				$sanitized['elementor_confirmation'] = array(
					'enabled' => ! empty( $raw['elementor_confirmation']['enabled'] ) ? 1 : 0,
					'en'      => $this->sanitize_template_content( $raw['elementor_confirmation']['en'] ?? '' ),
					'ar'      => $this->sanitize_template_content( $raw['elementor_confirmation']['ar'] ?? '' ),
				);
			}
		}

		if ( $update_gf ) {
			$sanitized['gf_enabled'] = ! empty( $raw['gf_enabled'] ) ? 1 : 0;
			$sanitized['gf_mode']    = in_array( $raw['gf_mode'] ?? '', $valid_modes, true ) ? $raw['gf_mode'] : 'notification';
			if ( isset( $raw['gf_confirmation'] ) && is_array( $raw['gf_confirmation'] ) ) {
				$sanitized['gf_confirmation'] = array(
					'enabled' => ! empty( $raw['gf_confirmation']['enabled'] ) ? 1 : 0,
					'en'      => $this->sanitize_template_content( $raw['gf_confirmation']['en'] ?? '' ),
					'ar'      => $this->sanitize_template_content( $raw['gf_confirmation']['ar'] ?? '' ),
				);
			}
		}

		if ( $update_nf ) {
			$sanitized['nf_enabled'] = ! empty( $raw['nf_enabled'] ) ? 1 : 0;
			$sanitized['nf_mode']    = in_array( $raw['nf_mode'] ?? '', $valid_modes, true ) ? $raw['nf_mode'] : 'notification';
			if ( isset( $raw['nf_confirmation'] ) && is_array( $raw['nf_confirmation'] ) ) {
				$sanitized['nf_confirmation'] = array(
					'enabled' => ! empty( $raw['nf_confirmation']['enabled'] ) ? 1 : 0,
					'en'      => $this->sanitize_template_content( $raw['nf_confirmation']['en'] ?? '' ),
					'ar'      => $this->sanitize_template_content( $raw['nf_confirmation']['ar'] ?? '' ),
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Strip HTML and emoji from a template string.
	 *
	 * @param string $content Raw template content.
	 *
	 * @return string Clean template.
	 */
	private function sanitize_template_content( $content ) {
		$content = wp_strip_all_tags( sanitize_textarea_field( wp_unslash( $content ) ) );
		// Strip emoji (U+1F000 and above, common emoji blocks).
		$content = preg_replace(
			'/[\x{1F000}-\x{1FFFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u',
			'',
			$content
		);
		return trim( $content ?? '' );
	}

	// =========================================================================
	// Asset enqueuing
	// =========================================================================

	/**
	 * Enqueue admin CSS and JS only on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'kwtsms-admin',
			KWTSMS_OTP_URL . 'assets/css/admin.css',
			array(),
			KWTSMS_OTP_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'kwtsms-admin-rtl',
				KWTSMS_OTP_URL . 'assets/css/admin-rtl.css',
				array( 'kwtsms-admin' ),
				KWTSMS_OTP_VERSION
			);
		}

		wp_enqueue_script(
			'kwtsms-admin',
			KWTSMS_OTP_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			KWTSMS_OTP_VERSION,
			true
		);

		wp_localize_script(
			'kwtsms-admin',
			'kwtSmsAdminData',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'kwtsms_admin_nonce' ),
				'credentialsVerified'  => (bool) $this->plugin->settings->get( 'gateway.credentials_verified', false ),
				'savedSenderIds'      => array_values( (array) $this->plugin->settings->get( 'gateway.sender_ids', array() ) ),
				'savedBalance'        => array(
					'available'  => $this->plugin->settings->get( 'gateway.balance_available', null ),
					'purchased'  => $this->plugin->settings->get( 'gateway.balance_purchased', null ),
					'updated_at' => (int) $this->plugin->settings->get( 'gateway.balance_updated_at', 0 ),
				),
				'savedCoverage'       => array_values( (array) $this->plugin->settings->get( 'gateway.coverage', array() ) ),
				'strings'              => array(
					'verifying'          => __( 'Verifying...', 'wp-kwtsms-otp' ),
					'verified'           => __( 'Credentials verified!', 'wp-kwtsms-otp' ),
					'error'              => __( 'Verification failed.', 'wp-kwtsms-otp' ),
					'sending'            => __( 'Sending...', 'wp-kwtsms-otp' ),
					'sent'               => __( 'Test SMS sent! Check your phone.', 'wp-kwtsms-otp' ),
					'characters'         => __( 'characters', 'wp-kwtsms-otp' ),
					'smsPages'           => __( 'SMS page(s)', 'wp-kwtsms-otp' ),
					'usernameIsPhone'    => __( 'This looks like a phone number. Your API Username must be your kwtSMS account username — not a phone number. Sign up at kwtsms.com to obtain API credentials.', 'wp-kwtsms-otp' ),
					'loadingCoverage'    => __( 'Loading coverage...', 'wp-kwtsms-otp' ),
					'coverageError'      => __( 'Could not load coverage data.', 'wp-kwtsms-otp' ),
					'credentialsMissing' => __( 'Please enter your API username and password, then click "Save Settings" before performing this action.', 'wp-kwtsms-otp' ),
					'connectedAs'        => __( 'Connected as %s', 'wp-kwtsms-otp' ),
				),
			)
		);
	}

	// =========================================================================
	// Admin notices
	// =========================================================================

	/**
	 * Hook callback for admin_notices — skips plugin pages (they render inline).
	 *
	 * This prevents duplicate rendering: plugin pages call render_page_notices()
	 * directly inside the .wrap div so notices appear before the logo header.
	 */
	public function show_admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_plugin_page = in_array(
			$screen->id,
			array_filter( $this->page_hooks ),
			true
		) || strpos( $screen->id, 'kwtsms' ) !== false;

		// Plugin pages render notices inline (before the logo) — skip here.
		if ( $is_plugin_page ) {
			return;
		}
	}

	/**
	 * Render contextual plugin notices inline.
	 *
	 * Called at the top of every plugin page template, before the logo header,
	 * so notices visually appear above the branding rather than beside it.
	 * Also calls settings_errors() to show Settings API save/validation messages.
	 */
	public function render_page_notices() {
		// Settings API messages (e.g. "Settings saved.", validation errors).
		// Capture output and add 'inline' class so WP admin JS doesn't move
		// these notices to after the <h1> inside our flex header row.
		ob_start();
		settings_errors();
		$se_html = ob_get_clean();
		// WP outputs settings_errors with single-quoted class attr (class='notice ...').
		// Add 'inline' so WP admin JS doesn't relocate the notice into our flex header.
		$se_html = str_replace( "class='notice ", "class='notice inline ", $se_html );
		echo $se_html; // phpcs:ignore WordPress.Security.EscapeOutput

		// Notice: site is not HTTPS.
		// The 'inline' class prevents WP admin JS from relocating the notice
		// to after the first <h1> (which is inside our flex header row).
		if ( ! is_ssl() ) {
			printf(
				'<div class="notice notice-warning inline"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'kwtSMS Warning:', 'wp-kwtsms-otp' ),
				esc_html__( 'Your site is not served over HTTPS. OTP codes may be intercepted in transit. Enable SSL for security.', 'wp-kwtsms-otp' )
			);
		}

		// Notice: API credentials not configured.
		$username = $this->plugin->settings->get( 'gateway.api_username', '' );
		$password = $this->plugin->settings->get( 'gateway.api_password', '' );
		if ( empty( $username ) || empty( $password ) ) {
			printf(
				'<div class="notice notice-error inline"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'kwtSMS:', 'wp-kwtsms-otp' ),
				esc_html__( 'API credentials are not configured. The plugin will not be able to send SMS messages.', 'wp-kwtsms-otp' ),
				esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ),
				esc_html__( 'Configure now →', 'wp-kwtsms-otp' )
			);
		}

		// Notice: test mode is active.
		if ( $this->plugin->settings->get( 'gateway.test_mode', 1 ) ) {
			printf(
				'<div class="notice notice-info inline"><p>%s</p></div>',
				esc_html__( 'kwtSMS is in Test Mode. SMS messages will be queued but not delivered. OTP codes are written to wp-content/debug.log.', 'wp-kwtsms-otp' )
			);
		}
	}

	// =========================================================================
	// Page renderers
	// =========================================================================

	/**
	 * Render the General Settings page.
	 */
	public function render_general_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		include KWTSMS_OTP_DIR . 'admin/views/page-general.php';
	}

	/**
	 * Render the Gateway Settings page.
	 */
	public function render_gateway_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		include KWTSMS_OTP_DIR . 'admin/views/page-gateway.php';
	}

	/**
	 * Render the SMS Templates page.
	 */
	public function render_templates_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		include KWTSMS_OTP_DIR . 'admin/views/page-templates.php';
	}

	/**
	 * Render the Integrations admin page.
	 */
	public function render_integrations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		include KWTSMS_OTP_DIR . 'admin/views/page-integrations.php';
	}

	/**
	 * Render WooCommerce integration settings sub-page.
	 */
	public function render_int_woo_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		include KWTSMS_OTP_DIR . 'admin/views/page-int-woo.php';
	}

	/**
	 * Render Contact Form 7 integration settings sub-page.
	 */
	public function render_int_cf7_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		$int_key = 'cf7';
		include KWTSMS_OTP_DIR . 'admin/views/page-int-form.php';
	}

	/**
	 * Render WPForms integration settings sub-page.
	 */
	public function render_int_wpforms_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		$int_key = 'wpforms';
		include KWTSMS_OTP_DIR . 'admin/views/page-int-form.php';
	}

	/**
	 * Render Elementor integration settings sub-page.
	 */
	public function render_int_elementor_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		$int_key = 'elementor';
		include KWTSMS_OTP_DIR . 'admin/views/page-int-form.php';
	}

	/**
	 * Render Gravity Forms integration settings sub-page.
	 */
	public function render_int_gf_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		$int_key = 'gf';
		include KWTSMS_OTP_DIR . 'admin/views/page-int-form.php';
	}

	/**
	 * Render Ninja Forms integration settings sub-page.
	 */
	public function render_int_nf_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		$int_key = 'nf';
		include KWTSMS_OTP_DIR . 'admin/views/page-int-form.php';
	}

	/**
	 * Render the Logs page (SMS History + OTP Attempts).
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		include KWTSMS_OTP_DIR . 'admin/views/page-logs.php';
	}

	/**
	 * Render the Help & Support page.
	 */
	public function render_help_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
		}
		include KWTSMS_OTP_DIR . 'admin/views/page-help.php';
	}

	// =========================================================================
	// AJAX: Coverage
	// =========================================================================

	/**
	 * AJAX handler — fetch SMS coverage data.
	 *
	 * Security: nonce + manage_options capability.
	 */
	public function ajax_get_coverage() {
		check_ajax_referer( 'kwtsms_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-kwtsms-otp' ) ), 403 );
		}

		$coverage = $this->plugin->api->get_coverage();

		if ( is_wp_error( $coverage ) ) {
			wp_send_json_error( array( 'message' => $coverage->get_error_message() ) );
		}

		wp_send_json_success( array( 'coverage' => $coverage ) );
	}

	// =========================================================================
	// AJAX: Logout (clear credentials_verified flag)
	// =========================================================================

	/**
	 * AJAX: Clear credentials_verified flag (Logout).
	 *
	 * Security: nonce + manage_options.
	 */
	public function ajax_logout_gateway() {
		check_ajax_referer( 'kwtsms_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-kwtsms-otp' ) ), 403 );
		}

		$gw                         = get_option( 'kwtsms_otp_gateway', array() );
		$gw['credentials_verified'] = 0;
		update_option( 'kwtsms_otp_gateway', $gw );

		wp_send_json_success();
	}

	// =========================================================================
	// AJAX: Clear log
	// =========================================================================

	/**
	 * AJAX handler — clear a named log option.
	 *
	 * Accepts 'log' param: 'sms_history' | 'attempt_log'.
	 * Security: nonce + manage_options capability.
	 */
	public function ajax_clear_log() {
		check_ajax_referer( 'kwtsms_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-kwtsms-otp' ) ), 403 );
		}

		$log_key = sanitize_key( $_POST['log'] ?? '' );
		$allowed = array( 'sms_history', 'attempt_log' );

		if ( ! in_array( $log_key, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log key.', 'wp-kwtsms-otp' ) ) );
		}

		delete_option( 'kwtsms_otp_' . $log_key );
		wp_send_json_success();
	}

	// =========================================================================
	// Dashboard Widget
	// =========================================================================

	/**
	 * Register the kwtSMS dashboard widget.
	 */
	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'kwtsms_otp_dashboard_widget',
			__( 'kwtSMS', 'wp-kwtsms-otp' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the kwtSMS dashboard widget.
	 */
	public function render_dashboard_widget() {
		$log = get_option( 'kwtsms_otp_send_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$today_count  = 0;
		$today_start  = strtotime( 'today midnight' );
		$failed_count = 0;

		foreach ( $log as $entry ) {
			if ( isset( $entry['time'] ) && $entry['time'] >= $today_start ) {
				$today_count++;
				if ( 'failed' === ( $entry['status'] ?? '' ) ) {
					$failed_count++;
				}
			}
		}

		$test_mode = (bool) $this->plugin->settings->get( 'gateway.test_mode', false );
		?>
		<div style="padding:4px 0;">
			<?php if ( $test_mode ) : ?>
			<p style="background:#fff3cd;border-left:3px solid #FFA200;padding:6px 10px;margin:0 0 10px;">
				<?php esc_html_e( 'Test mode is active.', 'wp-kwtsms-otp' ); ?>
			</p>
			<?php endif; ?>

			<p>
				<strong><?php esc_html_e( 'OTPs sent today:', 'wp-kwtsms-otp' ); ?></strong>
				<?php echo (int) $today_count; ?>
				<?php if ( $failed_count > 0 ) : ?>
				<span style="color:#dc3232;">(<?php echo (int) $failed_count; ?> <?php esc_html_e( 'failed', 'wp-kwtsms-otp' ); ?>)</span>
				<?php endif; ?>
			</p>

			<?php if ( ! empty( $log ) ) : ?>
			<table style="width:100%;font-size:12px;border-collapse:collapse;">
				<thead>
					<tr>
						<th style="text-align:left;padding:2px 4px;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Time', 'wp-kwtsms-otp' ); ?></th>
						<th style="text-align:left;padding:2px 4px;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Phone', 'wp-kwtsms-otp' ); ?></th>
						<th style="text-align:left;padding:2px 4px;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Status', 'wp-kwtsms-otp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $log, 0, 5 ) as $entry ) : ?>
					<tr>
						<td style="padding:2px 4px;"><?php echo esc_html( date_i18n( get_option( 'time_format' ), $entry['time'] ?? 0 ) ); ?></td>
						<td style="padding:2px 4px;"><?php echo esc_html( $entry['phone'] ?? '' ); ?></td>
						<td style="padding:2px 4px;color:<?php echo 'sent' === ( $entry['status'] ?? '' ) ? '#46b450' : '#dc3232'; ?>;">
							<?php echo 'sent' === ( $entry['status'] ?? '' ) ? esc_html__( 'Sent', 'wp-kwtsms-otp' ) : esc_html__( 'Failed', 'wp-kwtsms-otp' ); ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin:6px 0 0;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'View full log →', 'wp-kwtsms-otp' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
