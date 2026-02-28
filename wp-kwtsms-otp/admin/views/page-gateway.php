<?php
/**
 * Admin View: Gateway Settings Page.
 *
 * API credentials, sender ID selection, balance display, test mode.
 * The "Save & Verify" button triggers an AJAX credential check.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */
$settings   = $this->plugin->settings;
$gateway    = $settings->get( 'gateway' ) + KwtSMS_Settings::DEFAULTS['gateway'];
$sender_id  = $gateway['sender_id'] ?? '';
$test_mode  = ! empty( $gateway['test_mode'] );
$test_phone = $gateway['test_phone'] ?? '';
?>
<div class="wrap kwtsms-admin-wrap">
	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtsms" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtsms OTP — Gateway Settings', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<!-- Balance Display -->
	<div class="kwtsms-balance-card" id="kwtsms-balance-card">
		<div class="kwtsms-balance-label"><?php esc_html_e( 'Account Balance', 'wp-kwtsms-otp' ); ?></div>
		<div class="kwtsms-balance-value" id="kwtsms-balance">—</div>
		<div class="kwtsms-balance-sub" id="kwtsms-balance-purchased"></div>
	</div>

	<!-- API Status -->
	<div id="kwtsms-api-status" class="kwtsms-api-status" style="display:none;" aria-live="polite"></div>

	<form method="post" action="options.php" id="kwtsms-gateway-form">
		<?php settings_fields( 'kwtsms_otp_gateway_group' ); ?>

		<h2 class="title"><?php esc_html_e( 'API Credentials', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_api_username"><?php esc_html_e( 'API Username', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="text" name="kwtsms_otp_gateway[api_username]" id="kwtsms_api_username"
						value="<?php echo esc_attr( $gateway['api_username'] ); ?>"
						class="regular-text" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Your kwtsms account username.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_api_password"><?php esc_html_e( 'API Password', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="password" name="kwtsms_otp_gateway[api_password]" id="kwtsms_api_password"
						value="<?php echo esc_attr( $gateway['api_password'] ); ?>"
						class="regular-text" autocomplete="new-password" />
					<p class="description"><?php esc_html_e( 'Your kwtsms account password. Stored server-side only, never exposed to the browser.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_sender_id"><?php esc_html_e( 'Sender ID', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<div style="display:flex;align-items:center;gap:10px;">
						<select name="kwtsms_otp_gateway[sender_id]" id="kwtsms_sender_id">
							<?php if ( ! empty( $sender_id ) ) : ?>
								<option value="<?php echo esc_attr( $sender_id ); ?>" selected>
									<?php echo esc_html( $sender_id ); ?>
								</option>
							<?php else : ?>
								<option value=""><?php esc_html_e( '— Save & Verify to load —', 'wp-kwtsms-otp' ); ?></option>
							<?php endif; ?>
						</select>
						<button type="button" id="kwtsms-reload-senders" class="button">
							↻ <?php esc_html_e( 'Reload', 'wp-kwtsms-otp' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Select the sender ID approved for your kwtsms account.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

		</table>

		<h2 class="title"><?php esc_html_e( 'Test Mode', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_test_mode"><?php esc_html_e( 'Enable Test Mode', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_gateway[test_mode]" id="kwtsms_test_mode"
							value="1" <?php checked( $test_mode ); ?> />
						<?php esc_html_e( 'Messages are queued but not delivered. OTP codes are written to wp-content/debug.log.', 'wp-kwtsms-otp' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_test_phone"><?php esc_html_e( 'Test Phone', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="tel" name="kwtsms_otp_gateway[test_phone]" id="kwtsms_test_phone"
						value="<?php echo esc_attr( $test_phone ); ?>"
						class="regular-text" placeholder="96599220322" />
					<p class="description"><?php esc_html_e( 'Phone number to receive test SMS. Include country code, no spaces.', 'wp-kwtsms-otp' ); ?></p>
					<button type="button" id="kwtsms-send-test-sms" class="button" style="margin-top:8px;">
						<?php esc_html_e( 'Send Test SMS Now', 'wp-kwtsms-otp' ); ?>
					</button>
					<span id="kwtsms-test-sms-result" style="margin-left:10px;" aria-live="polite"></span>
				</td>
			</tr>

		</table>

		<div style="display:flex;gap:12px;margin-top:20px;align-items:center;">
			<?php submit_button( __( 'Save Settings', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn', 'submit', false ); ?>
			<button type="button" id="kwtsms-verify-btn" class="button button-secondary">
				<?php esc_html_e( 'Save & Verify Credentials', 'wp-kwtsms-otp' ); ?>
			</button>
		</div>
	</form>

	<?php
	// OTP Send Log — last 20 entries.
	$send_log = get_option( 'kwtsms_otp_send_log', array() );
	if ( ! empty( $send_log ) ) :
	?>
	<hr style="margin:30px 0;" />
	<h2><?php esc_html_e( 'Recent OTP Activity', 'wp-kwtsms-otp' ); ?></h2>
	<table class="widefat striped kwtsms-otp-log" style="max-width:700px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date / Time', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-kwtsms-otp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $send_log as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ?? 0 ) ); ?></td>
				<td><?php echo esc_html( $entry['phone'] ?? '' ); ?></td>
				<td style="color:<?php echo 'sent' === ( $entry['status'] ?? '' ) ? '#46b450' : '#dc3232'; ?>;">
					<?php echo 'sent' === ( $entry['status'] ?? '' ) ? esc_html__( 'Sent', 'wp-kwtsms-otp' ) : esc_html__( 'Failed', 'wp-kwtsms-otp' ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
