<?php
/**
 * Admin View: Gateway Settings Page.
 *
 * API credentials, sender ID selection, SMS coverage (visible after verify),
 * test mode, and gateway test SMS.
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
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtSMS OTP — Gateway Settings', 'wp-kwtsms-otp' ); ?></h1>
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

		<p class="kwtsms-signup-note">
			<?php esc_html_e( "Don't have a kwtSMS account?", 'wp-kwtsms-otp' ); ?>
			<a href="https://www.kwtsms.com/register/" target="_blank" rel="noopener">
				<?php esc_html_e( 'Sign up for free →', 'wp-kwtsms-otp' ); ?>
			</a>
		</p>

		<!-- ===== API Credentials ===== -->
		<h2 class="title"><?php esc_html_e( 'API Credentials', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_api_username"><?php esc_html_e( 'API Username', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="text" name="kwtsms_otp_gateway[api_username]" id="kwtsms_api_username"
						value="<?php echo esc_attr( $gateway['api_username'] ); ?>"
						class="regular-text" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Your kwtsms account username.', 'wp-kwtsms-otp' ); ?></p>
					<p class="description" id="kwtsms-username-warning" style="color:#dc3232;display:none;"></p>
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
					<p class="description"><?php esc_html_e( 'Select the sender ID approved for your kwtSMS account.', 'wp-kwtsms-otp' ); ?></p>
					<p class="description">
						<a href="https://www.kwtsms.com/sender-id-help.html" target="_blank" rel="noopener">
							<?php esc_html_e( 'Register or request a Sender ID at kwtSMS →', 'wp-kwtsms-otp' ); ?>
						</a>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== Coverage Section (hidden until credentials verified) ===== -->
		<div id="kwtsms-coverage-section" style="display:none;" aria-live="polite">
			<h2><?php esc_html_e( 'SMS Coverage', 'wp-kwtsms-otp' ); ?></h2>
			<p>
				<button type="button" id="kwtsms-load-coverage" class="button">
					<?php esc_html_e( 'Load Active Coverage', 'wp-kwtsms-otp' ); ?>
				</button>
			</p>
			<div id="kwtsms-coverage-result" style="margin-top:10px;"></div>
			<p>
				<a href="https://www.kwtsms.com/coverage/" target="_blank" rel="noopener">
					<?php esc_html_e( 'Add more countries to your coverage →', 'wp-kwtsms-otp' ); ?>
				</a>
			</p>
		</div>

		<!-- ===== Test Mode ===== -->
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
					<?php if ( $test_mode ) : ?>
					<p class="description" style="color:#d63638;font-weight:600;">
						<?php esc_html_e( '⚠ Test Mode is ON — no SMS will reach any phone.', 'wp-kwtsms-otp' ); ?>
					</p>
					<?php endif; ?>
				</td>
			</tr>

		</table>

		<!-- ===== Gateway Test ===== -->
		<h2 class="title"><?php esc_html_e( 'Gateway Test', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_test_phone"><?php esc_html_e( 'Test Phone Number', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="tel" name="kwtsms_otp_gateway[test_phone]" id="kwtsms_test_phone"
						value="<?php echo esc_attr( $test_phone ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 96512345678', 'wp-kwtsms-otp' ); ?>" />
					<?php if ( $test_mode ) : ?>
					<p class="description">
						<?php esc_html_e( 'Test Mode is currently ON. The SMS will be queued but will NOT be delivered to your phone. The OTP code will appear in wp-content/debug.log.', 'wp-kwtsms-otp' ); ?>
					</p>
					<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Test Mode is currently OFF. The SMS will be delivered to the phone number above. SMS credits will be consumed.', 'wp-kwtsms-otp' ); ?>
					</p>
					<?php endif; ?>
					<div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
						<button type="button" id="kwtsms-send-test-sms" class="button button-primary">
							<?php esc_html_e( 'Send Gateway Test SMS', 'wp-kwtsms-otp' ); ?>
						</button>
						<span id="kwtsms-test-sms-result" style="font-size:13px;line-height:1.5;" aria-live="polite"></span>
					</div>
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
