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

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kwtsms-otp' ) );
}

$active_tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'sms_history';
$per_page      = 20;
$current_page  = max( 1, absint( $_GET['paged'] ?? 1 ) );

// -------------------------------------------------------------------------
// Handle CSV export.
// -------------------------------------------------------------------------
if ( isset( $_GET['action'], $_GET['_wpnonce'] ) && 'export_csv' === $_GET['action'] ) {
	$log_key = sanitize_key( $_GET['log'] ?? '' );
	if ( in_array( $log_key, array( 'sms_history', 'attempt_log' ), true ) &&
		wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'kwtsms_export_csv_' . $log_key )
	) {
		$log = get_option( 'kwtsms_otp_' . $log_key, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$filename = 'kwtsms-' . $log_key . '-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( 'sms_history' === $log_key ) {
			fputcsv( $out, array( 'Date/Time', 'Type', 'Phone', 'Message', 'Status', 'Msg ID' ) );
			foreach ( $log as $entry ) {
				fputcsv( $out, array(
					date_i18n( 'Y-m-d H:i:s', $entry['time'] ?? 0 ),
					$entry['type']    ?? '',
					$entry['phone']   ?? '',
					$entry['message'] ?? '',
					$entry['status']  ?? '',
					$entry['msg_id']  ?? '',
				) );
			}
		} else {
			fputcsv( $out, array( 'Date/Time', 'User ID', 'Phone', 'IP Address', 'Action', 'Result' ) );
			foreach ( $log as $entry ) {
				$user_id = $entry['user_id'] ?? null;
				fputcsv( $out, array(
					date_i18n( 'Y-m-d H:i:s', $entry['time'] ?? 0 ),
					is_null( $user_id ) ? 'N/A' : (int) $user_id,
					$entry['phone']  ?? '',
					$entry['ip']     ?? '',
					$entry['action'] ?? '',
					$entry['result'] ?? '',
				) );
			}
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}

// -------------------------------------------------------------------------
// Load log data for display.
// -------------------------------------------------------------------------
$sms_history  = get_option( 'kwtsms_otp_sms_history', array() );
$attempt_log  = get_option( 'kwtsms_otp_attempt_log', array() );
if ( ! is_array( $sms_history ) ) { $sms_history = array(); }
if ( ! is_array( $attempt_log ) ) { $attempt_log = array(); }

$active_log        = 'sms_history' === $active_tab ? $sms_history : $attempt_log;
$total_entries     = count( $active_log );
$total_pages       = max( 1, (int) ceil( $total_entries / $per_page ) );
$current_page      = min( $current_page, $total_pages );
$offset            = ( $current_page - 1 ) * $per_page;
$page_entries      = array_slice( $active_log, $offset, $per_page );

// Helper: build tab URL.
function kwtsms_logs_tab_url( $tab, $extra = array() ) {
	return add_query_arg(
		array_merge( array( 'page' => 'kwtsms-otp-logs', 'tab' => $tab ), $extra ),
		admin_url( 'admin.php' )
	);
}

// Helper: human-readable result label.
function kwtsms_attempt_result_label( $result ) {
	$labels = array(
		'success'      => '<span style="color:#46b450;">' . esc_html__( 'Success', 'wp-kwtsms-otp' ) . '</span>',
		'wrong_code'   => '<span style="color:#f0ad4e;">' . esc_html__( 'Wrong code', 'wp-kwtsms-otp' ) . '</span>',
		'expired'      => '<span style="color:#888;">' . esc_html__( 'Expired', 'wp-kwtsms-otp' ) . '</span>',
		'locked'       => '<span style="color:#dc3232;">' . esc_html__( 'Locked (max attempts)', 'wp-kwtsms-otp' ) . '</span>',
		'rate_limited' => '<span style="color:#dc3232;">' . esc_html__( 'Rate limited', 'wp-kwtsms-otp' ) . '</span>',
		'brute_force'  => '<span style="color:#dc3232;font-weight:bold;">' . esc_html__( '⚠ Brute force', 'wp-kwtsms-otp' ) . '</span>',
	);
	return $labels[ $result ] ?? esc_html( $result );
}
?>

<div class="wrap kwtsms-admin-wrap">
	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtSMS OTP — Logs', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<!-- Tabs -->
	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( kwtsms_logs_tab_url( 'sms_history' ) ); ?>"
			class="nav-tab <?php echo 'sms_history' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'SMS History', 'wp-kwtsms-otp' ); ?>
		</a>
		<a href="<?php echo esc_url( kwtsms_logs_tab_url( 'attempt_log' ) ); ?>"
			class="nav-tab <?php echo 'attempt_log' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'OTP Attempts', 'wp-kwtsms-otp' ); ?>
		</a>
	</nav>

	<div class="kwtsms-log-toolbar" style="display:flex;gap:10px;align-items:center;margin:16px 0;">
		<!-- CSV Export -->
		<a href="<?php echo esc_url( add_query_arg( array(
			'action'   => 'export_csv',
			'log'      => $active_tab,
			'_wpnonce' => wp_create_nonce( 'kwtsms_export_csv_' . $active_tab ),
		), admin_url( 'admin.php?page=kwtsms-otp-logs' ) ) ); ?>"
			class="button">
			⬇ <?php esc_html_e( 'Export CSV', 'wp-kwtsms-otp' ); ?>
		</a>

		<span style="color:#888;font-size:13px;">
			<?php
			printf(
				/* translators: %d total entries */
				esc_html__( '%d entries total', 'wp-kwtsms-otp' ),
				(int) $total_entries
			);
			?>
		</span>
	</div>

	<?php if ( 'sms_history' === $active_tab ) : ?>
	<!-- ===== SMS History Tab ===== -->
	<?php if ( empty( $page_entries ) ) : ?>
	<p><?php esc_html_e( 'No SMS history yet.', 'wp-kwtsms-otp' ); ?></p>
	<?php else : ?>
	<table class="widefat striped kwtsms-log-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date / Time', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Type', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Message', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-kwtsms-otp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $page_entries as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ?? 0 ) ); ?></td>
				<td><?php echo esc_html( $entry['type'] ?? '' ); ?></td>
				<td><code><?php echo esc_html( $entry['phone'] ?? '' ); ?></code></td>
				<td style="max-width:400px;word-break:break-word;"><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
				<td style="color:<?php echo 'sent' === ( $entry['status'] ?? '' ) ? '#46b450' : '#dc3232'; ?>;">
					<?php echo 'sent' === ( $entry['status'] ?? '' ) ? esc_html__( 'Sent', 'wp-kwtsms-otp' ) : esc_html__( 'Failed', 'wp-kwtsms-otp' ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<?php else : ?>
	<!-- ===== OTP Attempts Tab ===== -->
	<?php if ( empty( $page_entries ) ) : ?>
	<p><?php esc_html_e( 'No OTP attempts logged yet.', 'wp-kwtsms-otp' ); ?></p>
	<?php else : ?>
	<table class="widefat striped kwtsms-log-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date / Time', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'User', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'IP Address', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Action', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Result', 'wp-kwtsms-otp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $page_entries as $entry ) :
				$user_id = $entry['user_id'] ?? null;
				if ( $user_id ) {
					$user_data = get_userdata( (int) $user_id );
					$user_label = $user_data ? esc_html( $user_data->user_login ) . ' (#' . (int) $user_id . ')' : '#' . (int) $user_id;
				} else {
					$user_label = '—';
				}
			?>
			<tr>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ?? 0 ) ); ?></td>
				<td><?php echo $user_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><code><?php echo esc_html( $entry['phone'] ?? '' ); ?></code></td>
				<td><?php echo esc_html( $entry['ip'] ?? '' ); ?></td>
				<td><?php echo esc_html( $entry['action'] ?? '' ); ?></td>
				<td><?php echo kwtsms_attempt_result_label( $entry['result'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
	<?php endif; ?>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav" style="margin-top:12px;">
		<div class="tablenav-pages">
			<?php
			echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'base'    => add_query_arg( 'paged', '%#%', kwtsms_logs_tab_url( $active_tab ) ),
				'format'  => '',
				'current' => $current_page,
				'total'   => $total_pages,
			) );
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
