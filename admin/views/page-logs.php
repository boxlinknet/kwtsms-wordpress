<?php
/**
 * Admin View: Logs Page — SMS History + OTP Attempts.
 *
 * Tab 1 — SMS History: full unredacted send log (phone + message).
 * Tab 2 — OTP Attempts: verification events with IP, action, and result.
 *
 * Both logs support pagination (20 rows/page) and CSV export.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this — admin controller, injected via include inside a KwtSMS_Admin method */

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms' ) );
}

$active_tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'sms_history';
$items_per_page = 20;
$current_page   = max( 1, absint( $_GET['paged'] ?? 1 ) );

// Debug log tab variables — only relevant when debug_logging is enabled.
// NOTE: download/clear/export handlers are registered on admin_init in KwtSMS_Admin::handle_log_exports()
// so that Content-Type headers can be sent before any HTML output.
$debug_log_path   = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/kwtsms-debug.log' : '';
$debug_logging_on = (bool) $this->plugin->settings->get( 'general.debug_logging', 0 );
$debug_log_exists = $debug_log_path && file_exists( $debug_log_path );
$show_debug_tab   = $debug_logging_on && $debug_log_exists;

// -------------------------------------------------------------------------
// Load log data for display.
// -------------------------------------------------------------------------
$sms_history = get_option( 'kwtsms_otp_sms_history', array() );
$attempt_log = get_option( 'kwtsms_otp_attempt_log', array() );
if ( ! is_array( $sms_history ) ) {
	$sms_history = array(); }
if ( ! is_array( $attempt_log ) ) {
	$attempt_log = array(); }

$active_log    = 'sms_history' === $active_tab ? $sms_history : $attempt_log;
$total_entries = count( $active_log );
$total_pages   = max( 1, (int) ceil( $total_entries / $per_page ) );
$current_page  = min( $current_page, $total_pages );
$offset        = ( $current_page - 1 ) * $per_page;
$page_entries  = array_slice( $active_log, $offset, $per_page );

// Helper: build tab URL.
function kwtsms_logs_tab_url( $tab, $extra = array() ) {
	return add_query_arg(
		array_merge(
			array(
				'page' => 'kwtsms-otp-logs',
				'tab'  => $tab,
			),
			$extra
		),
		admin_url( 'admin.php' )
	);
}

// Helper: human-readable result label.
function kwtsms_attempt_result_label( $result ) {
	$labels = array(
		'success'      => '<span style="color:#46b450;">' . esc_html__( 'Success', 'wp-kwtsms' ) . '</span>',
		'wrong_code'   => '<span style="color:#f0ad4e;">' . esc_html__( 'Wrong code', 'wp-kwtsms' ) . '</span>',
		'expired'      => '<span style="color:#888;">' . esc_html__( 'Expired', 'wp-kwtsms' ) . '</span>',
		'locked'       => '<span style="color:#dc3232;">' . esc_html__( 'Locked (max attempts)', 'wp-kwtsms' ) . '</span>',
		'rate_limited' => '<span style="color:#dc3232;">' . esc_html__( 'Rate limited', 'wp-kwtsms' ) . '</span>',
		'brute_force'  => '<span style="color:#dc3232;font-weight:bold;">' . esc_html__( '⚠ Brute force', 'wp-kwtsms' ) . '</span>',
	);
	return $labels[ $result ] ?? esc_html( $result );
}
?>

<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'Logs', 'wp-kwtsms' ); ?></h1>
	</div>

	<!-- Tabs -->
	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( kwtsms_logs_tab_url( 'sms_history' ) ); ?>"
			class="nav-tab <?php echo 'sms_history' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'SMS History', 'wp-kwtsms' ); ?>
		</a>
		<a href="<?php echo esc_url( kwtsms_logs_tab_url( 'attempt_log' ) ); ?>"
			class="nav-tab <?php echo 'attempt_log' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'OTP Attempts', 'wp-kwtsms' ); ?>
		</a>
		<?php if ( $show_debug_tab ) : ?>
		<a href="<?php echo esc_url( kwtsms_logs_tab_url( 'debug_log' ) ); ?>"
			class="nav-tab <?php echo 'debug_log' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Debug Log', 'wp-kwtsms' ); ?>
		</a>
		<?php endif; ?>
	</nav>

	<?php if ( 'debug_log' !== $active_tab && $total_entries > 0 ) : ?>
	<div class="kwtsms-log-toolbar" style="display:flex;gap:10px;align-items:center;margin:16px 0;">
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'action'   => 'export_csv',
					'log'      => $active_tab,
					'_wpnonce' => wp_create_nonce( 'kwtsms_export_csv_' . $active_tab ),
				),
				admin_url( 'admin.php?page=kwtsms-otp-logs' )
			)
		);
		?>
		"
			class="button">
			⬇ <?php esc_html_e( 'Export CSV', 'wp-kwtsms' ); ?>
		</a>

		<span style="color:#888;font-size:13px;">
			<?php
			printf(
				/* translators: %d total entries */
				esc_html__( '%d entries total', 'wp-kwtsms' ),
				(int) $total_entries
			);
			?>
		</span>
	</div>
	<?php endif; ?>

	<?php if ( 'sms_history' === $active_tab ) : ?>
	<!-- ===== SMS History Tab ===== -->
		<?php if ( empty( $page_entries ) ) : ?>
	<p><?php esc_html_e( 'No SMS history yet.', 'wp-kwtsms' ); ?></p>
	<?php else : ?>
	<table class="widefat striped kwtsms-log-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date / Time', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Sender ID', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Message', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Type', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Result', 'wp-kwtsms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $page_entries as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ?? 0 ) ); ?></td>
				<td><?php echo esc_html( $entry['sender_id'] ?? '' ); ?></td>
				<td style="max-width:400px;word-break:break-word;"><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
				<td><?php echo esc_html( $entry['phone'] ?? '' ); ?></td>
				<td><?php echo esc_html( $entry['type'] ?? '' ); ?></td>
				<td style="color:<?php echo 'sent' === ( $entry['status'] ?? '' ) ? '#46b450' : '#dc3232'; ?>;">
					<?php echo 'sent' === ( $entry['status'] ?? '' ) ? esc_html__( 'Sent', 'wp-kwtsms' ) : esc_html__( 'Failed', 'wp-kwtsms' ); ?>
				</td>
				<td>
					<?php
					$gr = $entry['gateway_result'] ?? array();
					if ( ! empty( $gr ) ) :
						$gr_ok   = ! empty( $gr['ok'] );
						$gr_code = $gr['code'] ?? '';
						$gr_msg  = $gr['message'] ?? '';
						if ( $gr_ok ) {
							$gr_label = esc_html__( 'OK', 'wp-kwtsms' );
							$gr_color = '#46b450';
						} else {
							$parts    = array_filter( array( $gr_code, $gr_msg ) );
							$gr_label = $parts ? esc_html( implode( ': ', $parts ) ) : esc_html__( 'Error', 'wp-kwtsms' );
							$gr_color = '#dc3232';
						}
						printf( '<span style="color:%s;">%s</span>', esc_attr( $gr_color ), $gr_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					endif;
					?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<?php elseif ( 'attempt_log' === $active_tab ) : ?>
	<!-- ===== OTP Attempts Tab ===== -->
		<?php if ( empty( $page_entries ) ) : ?>
	<p><?php esc_html_e( 'No OTP attempts logged yet.', 'wp-kwtsms' ); ?></p>
	<?php else : ?>
	<table class="widefat striped kwtsms-log-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date / Time', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'User', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'IP Address', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Action', 'wp-kwtsms' ); ?></th>
				<th><?php esc_html_e( 'Result', 'wp-kwtsms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $page_entries as $entry ) :
				$user_id = $entry['user_id'] ?? null;
				if ( $user_id ) {
					$user_data  = get_userdata( (int) $user_id );
					$user_label = $user_data ? esc_html( $user_data->user_login ) . ' (#' . (int) $user_id . ')' : '#' . (int) $user_id;
				} else {
					$user_label = '—';
				}
				?>
			<tr>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ?? 0 ) ); ?></td>
				<td><?php echo $user_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo esc_html( $entry['phone'] ?? '' ); ?></td>
				<td><?php echo esc_html( $entry['ip'] ?? '' ); ?></td>
				<td><?php echo esc_html( $entry['action'] ?? '' ); ?></td>
				<td><?php echo kwtsms_attempt_result_label( $entry['result'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<?php elseif ( 'debug_log' === $active_tab && $show_debug_tab ) : ?>
	<!-- ===== Debug Log Tab ===== -->
		<?php
		// Read file, reverse lines (newest first), paginate.
		$lines_raw       = file( $debug_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$lines_raw       = $lines_raw ? $lines_raw : array();
		$lines           = array_reverse( $lines_raw );
		$total_lines     = count( $lines );
		$per_page_dbg    = 100;
		$total_pages_dbg = max( 1, (int) ceil( $total_lines / $per_page_dbg ) );
		$cur_page_dbg    = min( max( 1, absint( $_GET['paged'] ?? 1 ) ), $total_pages_dbg );
		$offset_dbg      = ( $cur_page_dbg - 1 ) * $per_page_dbg;
		$page_lines      = array_slice( $lines, $offset_dbg, $per_page_dbg );
		?>

	<div class="kwtsms-log-toolbar" style="display:flex;gap:10px;align-items:center;margin:16px 0;">
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'action'   => 'download_debug_log',
					'_wpnonce' => wp_create_nonce( 'kwtsms_download_debug_log' ),
				),
				admin_url( 'admin.php?page=kwtsms-otp-logs&tab=debug_log' )
			)
		);
		?>
		"
			class="button">
			&#11015; <?php esc_html_e( 'Download', 'wp-kwtsms' ); ?>
		</a>

		<span style="color:#888;font-size:13px;">
			<?php
			printf(
				/* translators: %d: number of log lines */
				esc_html__( '%d lines total', 'wp-kwtsms' ),
				(int) $total_lines
			);
			?>
		</span>
		<span style="color:#888;font-size:13px;margin-left:auto;">
		<?php
			// Show relative path so it reads the same on any server layout.
			$debug_log_display = str_replace( trailingslashit( ABSPATH ), '', $debug_log_path );
			echo esc_html( $debug_log_display );
		?>
		</span>
	</div>

		<?php if ( empty( $page_lines ) ) : ?>
	<p><?php esc_html_e( 'The debug log is empty.', 'wp-kwtsms' ); ?></p>
	<?php else : ?>
	<pre style="background:#1e1e1e;color:#d4d4d4;font-size:12px;line-height:1.6;padding:16px;border-radius:4px;overflow:auto;max-height:600px;white-space:pre-wrap;">
		<?php
		foreach ( $page_lines as $line ) {
			echo esc_html( $line ) . "\n";
		}
		?>
	</pre>

		<?php if ( $total_pages_dbg > 1 ) : ?>
	<div class="tablenav" style="margin-top:12px;">
		<div class="tablenav-pages">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML.
			echo paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%', kwtsms_logs_tab_url( 'debug_log' ) ),
					'format'  => '',
					'current' => $cur_page_dbg,
					'total'   => $total_pages_dbg,
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>
	<?php endif; ?>

	<?php endif; // end three-way tab ?>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav" style="margin-top:12px;">
		<div class="tablenav-pages">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML.
			echo paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%', kwtsms_logs_tab_url( $active_tab ) ),
					'format'  => '',
					'current' => $current_page,
					'total'   => $total_pages,
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
