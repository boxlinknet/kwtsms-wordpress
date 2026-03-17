<?php
/**
 * Lightweight GitHub version checker.
 *
 * Checks the GitHub Releases API once a day (via WP cron) to see if a newer
 * version of the plugin is available. If so, shows an admin notice with a
 * link to the release page. This is NOT a plugin updater: it does not modify
 * the update_plugins transient, does not download anything, and does not
 * trigger WordPress auto-updates. It is purely informational.
 *
 * WordPress.org hosted plugins get updates through the directory. This notice
 * serves users who installed from GitHub directly.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Version_Check
 */
class KwtSMS_Version_Check {

	/**
	 * GitHub repo owner/name.
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'boxlinknet/kwtsms-wordpress';

	/**
	 * Transient key for caching the latest version.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'kwtsms_latest_version';

	/**
	 * WP Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'kwtsms_daily_version_check';

	/**
	 * Boot: register cron schedule and admin notice.
	 */
	public function __construct() {
		// Schedule the daily cron if not already scheduled.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
		add_action( self::CRON_HOOK, array( $this, 'check_latest_version' ) );

		// Show admin notice if a newer version is available.
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'maybe_show_update_notice' ) );
			add_action( 'wp_ajax_kwtsms_dismiss_version_notice', array( $this, 'dismiss_notice' ) );
		}
	}

	/**
	 * Query the GitHub Releases API for the latest release tag.
	 *
	 * Stores the result in a transient (24 hours). Only makes one API call
	 * per day regardless of how many admin pages are loaded.
	 */
	public function check_latest_version() {
		$url = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'kwtsms-wordpress/' . KWTSMS_OTP_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['tag_name'] ) ) {
			return;
		}

		// Strip leading 'v' from tag (e.g. 'v3.4.0' becomes '3.4.0').
		$latest = ltrim( $data['tag_name'], 'v' );

		set_transient(
			self::TRANSIENT_KEY,
			array(
				'version'    => sanitize_text_field( $latest ),
				'url'        => esc_url_raw( $data['html_url'] ?? '' ),
				'published'  => sanitize_text_field( $data['published_at'] ?? '' ),
				'checked_at' => time(),
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Show an admin notice when a newer version is available.
	 *
	 * Only shown to users with manage_options capability. Dismissible via
	 * AJAX (stores a user meta flag for the specific version so the notice
	 * reappears for the next release).
	 */
	public function maybe_show_update_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$cached = get_transient( self::TRANSIENT_KEY );
		if ( ! $cached || empty( $cached['version'] ) ) {
			return;
		}

		$latest  = $cached['version'];
		$current = KWTSMS_OTP_VERSION;

		// No update available.
		if ( version_compare( $current, $latest, '>=' ) ) {
			return;
		}

		// Check if the user dismissed this specific version.
		$dismissed = get_user_meta( get_current_user_id(), 'kwtsms_dismissed_version', true );
		if ( $dismissed === $latest ) {
			return;
		}

		$release_url = $cached['url'];
		$nonce       = wp_create_nonce( 'kwtsms_dismiss_version' );
		?>
		<div class="notice notice-info is-dismissible kwtsms-version-notice" data-version="<?php echo esc_attr( $latest ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong>kwtSMS</strong>:
				<?php
				printf(
					/* translators: 1: latest version, 2: current version, 3: release URL */
					esc_html__( 'Version %1$s is available (you have %2$s). %3$s', 'kwtsms' ),
					'<strong>' . esc_html( $latest ) . '</strong>',
					esc_html( $current ),
					'<a href="' . esc_url( $release_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View release notes', 'kwtsms' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
		$kwtsms_dismiss_js = 'jQuery(function($){'
			. "$('.kwtsms-version-notice').on('click','.notice-dismiss',function(){"
			. "var n=$(this).closest('.kwtsms-version-notice');"
			. "$.post(ajaxurl,{action:'kwtsms_dismiss_version_notice',version:n.data('version'),nonce:n.data('nonce')});"
			. '});'
			. '});';
		wp_register_script( 'kwtsms-version-dismiss', '', array( 'jquery' ), KWTSMS_OTP_VERSION, true );
		wp_enqueue_script( 'kwtsms-version-dismiss' );
		wp_add_inline_script( 'kwtsms-version-dismiss', $kwtsms_dismiss_js );
	}

	/**
	 * AJAX handler: dismiss the update notice for the current version.
	 */
	public function dismiss_notice() {
		check_ajax_referer( 'kwtsms_dismiss_version', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$version = sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) );
		if ( $version ) {
			update_user_meta( get_current_user_id(), 'kwtsms_dismissed_version', $version );
		}

		wp_send_json_success();
	}

	/**
	 * Unschedule the cron on plugin deactivation.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_transient( self::TRANSIENT_KEY );
	}
}
