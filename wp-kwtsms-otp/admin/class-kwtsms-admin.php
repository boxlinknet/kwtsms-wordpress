<?php
/**
 * Admin Controller — menus, settings registration, admin notices.
 *
 * Registers three subpages under a top-level "kwtsms OTP" menu:
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
	}

	// =========================================================================
	// Menu registration
	// =========================================================================

	/**
	 * Register the top-level admin menu and subpages.
	 */
	public function register_menus() {
		// Top-level menu — uses a kwtsms-branded SVG icon as base64 data URI.
		$icon_url = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect width="20" height="20" rx="3" fill="#FFA200"/><text x="3" y="15" font-size="12" font-family="Arial" fill="#fff" font-weight="bold">OTP</text></svg>'
		);

		$this->page_hooks[] = add_menu_page(
			__( 'kwtsms OTP', 'wp-kwtsms-otp' ),
			__( 'kwtsms OTP', 'wp-kwtsms-otp' ),
			'manage_options',
			'kwtsms-otp',
			array( $this, 'render_general_page' ),
			$icon_url,
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

		return array(
			'otp_mode'             => in_array( $raw['otp_mode'] ?? '', array( '2fa', 'passwordless', 'both' ), true )
				? $raw['otp_mode']
				: $defaults['otp_mode'],
			'otp_length'           => in_array( (int) ( $raw['otp_length'] ?? 6 ), array( 4, 6 ), true )
				? (int) $raw['otp_length']
				: 6,
			'otp_expiry'           => max( 1, min( 30, absint( $raw['otp_expiry'] ?? 3 ) ) ),
			'max_attempts'         => max( 1, min( 10, absint( $raw['max_attempts'] ?? 3 ) ) ),
			'resend_cooldown'      => max( 30, min( 300, absint( $raw['resend_cooldown'] ?? 60 ) ) ),
			'login_otp'            => ! empty( $raw['login_otp'] ) ? 1 : 0,
			'reset_otp'            => ! empty( $raw['reset_otp'] ) ? 1 : 0,
			'captcha_provider'     => in_array( $raw['captcha_provider'] ?? '', array( 'none', 'recaptcha', 'turnstile' ), true )
				? $raw['captcha_provider']
				: 'none',
			'recaptcha_site_key'   => sanitize_text_field( $raw['recaptcha_site_key'] ?? '' ),
			'recaptcha_secret_key' => sanitize_text_field( $raw['recaptcha_secret_key'] ?? '' ),
			'turnstile_site_key'   => sanitize_text_field( $raw['turnstile_site_key'] ?? '' ),
			'turnstile_secret_key' => sanitize_text_field( $raw['turnstile_secret_key'] ?? '' ),
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

		return array(
			'api_username' => sanitize_text_field( $raw['api_username'] ?? '' ),
			'api_password' => sanitize_text_field( $raw['api_password'] ?? '' ),
			'sender_id'    => sanitize_text_field( $raw['sender_id'] ?? '' ),
			'test_mode'    => ! empty( $raw['test_mode'] ) ? 1 : 0,
			'test_phone'   => $test_phone,
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
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'kwtsms_admin_nonce' ),
				'strings'     => array(
					'verifying'  => __( 'Verifying...', 'wp-kwtsms-otp' ),
					'verified'   => __( 'Credentials verified!', 'wp-kwtsms-otp' ),
					'error'      => __( 'Verification failed.', 'wp-kwtsms-otp' ),
					'sending'    => __( 'Sending...', 'wp-kwtsms-otp' ),
					'sent'       => __( 'Test SMS sent! Check your phone.', 'wp-kwtsms-otp' ),
					'characters' => __( 'characters', 'wp-kwtsms-otp' ),
					'smsPages'   => __( 'SMS page(s)', 'wp-kwtsms-otp' ),
				),
			)
		);
	}

	// =========================================================================
	// Admin notices
	// =========================================================================

	/**
	 * Display contextual admin notices on the plugin settings pages.
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

		if ( ! $is_plugin_page ) {
			return;
		}

		// Notice: site is not HTTPS.
		if ( ! is_ssl() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'kwtsms OTP Warning:', 'wp-kwtsms-otp' ),
				esc_html__( 'Your site is not served over HTTPS. OTP codes may be intercepted in transit. Enable SSL for security.', 'wp-kwtsms-otp' )
			);
		}

		// Notice: API credentials not configured.
		$username = $this->plugin->settings->get( 'gateway.api_username', '' );
		$password = $this->plugin->settings->get( 'gateway.api_password', '' );
		if ( empty( $username ) || empty( $password ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'kwtsms OTP:', 'wp-kwtsms-otp' ),
				esc_html__( 'API credentials are not configured. The plugin will not be able to send SMS messages.', 'wp-kwtsms-otp' ),
				esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ),
				esc_html__( 'Configure now →', 'wp-kwtsms-otp' )
			);
		}

		// Notice: test mode is active.
		if ( $this->plugin->settings->get( 'gateway.test_mode', 1 ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html__( 'kwtsms OTP is in Test Mode. SMS messages will be queued but not delivered. OTP codes are written to wp-content/debug.log.', 'wp-kwtsms-otp' )
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

	// =========================================================================
	// Dashboard Widget
	// =========================================================================

	/**
	 * Register the kwtsms OTP dashboard widget.
	 */
	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'kwtsms_otp_dashboard_widget',
			__( 'kwtsms OTP', 'wp-kwtsms-otp' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the kwtsms OTP dashboard widget.
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
