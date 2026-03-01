<?php
/**
 * Admin View: Gateway Settings Page.
 *
 * API credentials, login verification, sender ID selection, SMS coverage,
 * test mode, and gateway test SMS.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */
$settings              = $this->plugin->settings;
$gateway               = $settings->get( 'gateway' ) + KwtSMS_Settings::DEFAULTS['gateway'];
$sender_id             = $gateway['sender_id'] ?? '';
$test_mode             = ! empty( $gateway['test_mode'] );
$test_phone            = $gateway['test_phone'] ?? '';
$credentials_verified  = ! empty( $gateway['credentials_verified'] );
$sender_ids            = $gateway['sender_ids'] ?? array();
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtSMS — Gateway Settings', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<!-- Balance Display -->
	<?php
	$bal_available = $gateway['balance_available'] ?? null;
	$bal_purchased = $gateway['balance_purchased'] ?? null;
	?>
	<div class="kwtsms-balance-bar" id="kwtsms-balance-card"<?php echo $credentials_verified ? '' : ' style="display:none;"'; ?>>
		<div class="kwtsms-balance-bar-main">
			💳 <strong id="kwtsms-balance"><?php echo null !== $bal_available ? esc_html( number_format( (float) $bal_available, 2 ) ) : '—'; ?></strong>
			<?php esc_html_e( 'credits available', 'wp-kwtsms-otp' ); ?>
		</div>
		<div class="kwtsms-balance-bar-sub" id="kwtsms-balance-purchased">
			<?php if ( null !== $bal_purchased && $bal_purchased > 0 ) : ?>
				<?php printf( esc_html__( 'of %s purchased', 'wp-kwtsms-otp' ), esc_html( number_format( (float) $bal_purchased, 2 ) ) ); ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- API Login Status -->
	<div id="kwtsms-api-status" class="kwtsms-api-status<?php echo $credentials_verified ? ' is-success' : ''; ?>"
		<?php echo $credentials_verified ? '' : 'style="display:none;"'; ?> aria-live="polite">
		<?php if ( $credentials_verified ) : ?>
			<?php
			printf(
				/* translators: %s: API username */
				esc_html__( 'Connected as %s', 'wp-kwtsms-otp' ),
				'<strong>' . esc_html( $gateway['api_username'] ) . '</strong>'
			);
			?>
		<?php endif; ?>
	</div>

	<form method="post" action="options.php" id="kwtsms-gateway-form">
		<?php settings_fields( 'kwtsms_otp_gateway_group' ); ?>

		<?php if ( ! $credentials_verified ) : ?>
		<div class="kwtsms-api-status is-info kwtsms-signup-note">
			<?php esc_html_e( "Don't have a kwtSMS account?", 'wp-kwtsms-otp' ); ?>
			<a href="https://www.kwtsms.com/signup" target="_blank" rel="noopener" style="color:#46b450;font-weight:600;">
				<?php esc_html_e( 'Sign up for free →', 'wp-kwtsms-otp' ); ?>
			</a>
		</div>
		<?php endif; ?>

		<!-- ===== API Credentials ===== -->
		<h2 class="title"><?php esc_html_e( 'API Credentials', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_api_username"><?php esc_html_e( 'API Username', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="text" name="kwtsms_otp_gateway[api_username]" id="kwtsms_api_username"
						value="<?php echo esc_attr( $gateway['api_username'] ); ?>"
						class="regular-text" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Your kwtSMS API username, found in your kwtSMS account under API Settings, not your login username.', 'wp-kwtsms-otp' ); ?></p>
					<p class="description" id="kwtsms-username-warning" style="color:#dc3232;display:none;"></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_api_password"><?php esc_html_e( 'API Password', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="password" name="kwtsms_otp_gateway[api_password]" id="kwtsms_api_password"
						value="<?php echo esc_attr( $gateway['api_password'] ); ?>"
						class="regular-text" autocomplete="new-password" />
					<p class="description"><?php esc_html_e( 'Your kwtSMS API password, found in your kwtSMS account under API Settings, not your login password.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"></th>
				<td>
					<div style="display:flex;align-items:center;gap:12px;">
						<?php if ( $credentials_verified ) : ?>
						<button type="button" id="kwtsms-login-btn" class="button button-primary" style="display:none;">
							<?php esc_html_e( 'Login', 'wp-kwtsms-otp' ); ?>
						</button>
						<button type="button" id="kwtsms-logout-btn" class="button">
							<?php esc_html_e( 'Logout', 'wp-kwtsms-otp' ); ?>
						</button>
						<?php else : ?>
						<button type="button" id="kwtsms-login-btn" class="button button-primary">
							<?php esc_html_e( 'Login', 'wp-kwtsms-otp' ); ?>
						</button>
						<button type="button" id="kwtsms-logout-btn" class="button" style="display:none;">
							<?php esc_html_e( 'Logout', 'wp-kwtsms-otp' ); ?>
						</button>
						<?php endif; ?>
						<span id="kwtsms-login-status" style="font-size:13px;font-weight:600;" aria-live="polite">
							<?php if ( $credentials_verified ) : ?>
							<span style="color:#46b450;">
								&#x2713; <?php
								printf(
									/* translators: %s: API username */
									esc_html__( 'Connected as %s', 'wp-kwtsms-otp' ),
									esc_html( $gateway['api_username'] )
								);
								?>
							</span>
							<?php endif; ?>
						</span>
					</div>
					</td>
			</tr>

			<tr style="display:none;">
				<th scope="row"><label for="kwtsms_sender_id"><?php esc_html_e( 'Sender ID', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<div style="display:flex;align-items:center;gap:10px;">
						<select name="kwtsms_otp_gateway[sender_id]" id="kwtsms_sender_id">
							<?php if ( ! empty( $sender_ids ) ) : ?>
								<?php foreach ( $sender_ids as $sid ) : ?>
								<option value="<?php echo esc_attr( $sid ); ?>"
									<?php selected( $sender_id, $sid ); ?>>
									<?php echo esc_html( $sid ); ?>
								</option>
								<?php endforeach; ?>
							<?php elseif ( ! empty( $sender_id ) ) : ?>
								<option value="<?php echo esc_attr( $sender_id ); ?>" selected>
									<?php echo esc_html( $sender_id ); ?>
								</option>
							<?php else : ?>
								<option value=""><?php esc_html_e( '— Login to load —', 'wp-kwtsms-otp' ); ?></option>
							<?php endif; ?>
						</select>
						<button type="button" id="kwtsms-reload-senders" class="button"<?php echo $credentials_verified ? '' : ' disabled'; ?>>
							&#x21BB; <?php esc_html_e( 'Reload', 'wp-kwtsms-otp' ); ?>
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

		<!-- Dependent sections — hidden until credentials are verified -->
		<div id="kwtsms-verified-sections"<?php echo $credentials_verified ? '' : ' style="display:none;"'; ?>>

		<!-- ===== SMS Coverage ===== -->
		<h2 class="title"><?php esc_html_e( 'SMS Coverage', 'wp-kwtsms-otp' ); ?></h2>
		<div id="kwtsms-coverage-section" class="kwtsms-coverage-row" aria-live="polite">
			<div class="kwtsms-coverage-col-left">
				<p style="margin:0 0 8px;">
					<?php esc_html_e( 'Countries your kwtSMS account is approved to send SMS to.', 'wp-kwtsms-otp' ); ?>
				</p>
				<p style="margin:0;">
					<a href="https://www.kwtsms.com/coverage/" target="_blank" rel="noopener">
						<?php esc_html_e( 'Add more countries →', 'wp-kwtsms-otp' ); ?>
					</a>
				</p>
			</div>
			<div class="kwtsms-coverage-col-right">
				<button type="button" id="kwtsms-load-coverage" class="button">
					&#x21BB; <?php esc_html_e( 'Refresh Coverage', 'wp-kwtsms-otp' ); ?>
				</button>
				<div id="kwtsms-coverage-result" style="margin-top:10px;" aria-live="polite">
					<?php
					$saved_cov = $gateway['coverage'] ?? array();
					if ( ! empty( $saved_cov ) ) :
						foreach ( $saved_cov as $c ) :
							$name = is_array( $c ) ? ( $c['name'] ?? $c['country'] ?? (string) $c ) : (string) $c;
							echo '<span class="kwtsms-tag-chip">' . esc_html( $name ) . '</span>';
						endforeach;
					endif;
					?>
				</div>
			</div>
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
					<p class="description"><?php esc_html_e( 'Include country code, e.g. 96599220322', 'wp-kwtsms-otp' ); ?></p>
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
						<button type="button" id="kwtsms-send-test-sms" class="button button-primary"<?php echo $credentials_verified ? '' : ' disabled'; ?>>
							<?php esc_html_e( 'Send Gateway Test SMS', 'wp-kwtsms-otp' ); ?>
						</button>
						<span id="kwtsms-test-sms-result" style="font-size:13px;line-height:1.5;" aria-live="polite"></span>
					</div>
				</td>
			</tr>

		</table>

		<div style="margin-top:20px;">
			<?php submit_button( __( 'Save Settings', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn', 'submit', false ); ?>
		</div>

		</div><!-- #kwtsms-verified-sections -->

	</form>

	<?php
	// OTP Send Log — last 20 entries.
	$send_log = get_option( 'kwtsms_otp_send_log', array() );
	if ( ! empty( $send_log ) ) :
	?>
	<hr style="margin:30px 0;" />
	<h2><?php esc_html_e( 'Recent OTP Activity', 'wp-kwtsms-otp' ); ?></h2>
	<table class="widefat striped kwtsms-otp-log" style="max-width:800px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date / Time', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Type', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-kwtsms-otp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $send_log as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ?? 0 ) ); ?></td>
				<td><?php echo esc_html( $entry['phone'] ?? '' ); ?></td>
				<td>
					<?php
					$entry_type = $entry['type'] ?? '';
					if ( 'test' === $entry_type ) {
						echo '<span style="color:#888;">' . esc_html__( 'Test', 'wp-kwtsms-otp' ) . '</span>';
					} elseif ( $entry_type ) {
						echo '<span>' . esc_html( ucfirst( $entry_type ) ) . '</span>';
					} else {
						echo '<span style="color:#888;">—</span>';
					}
					?>
				</td>
				<td style="color:<?php echo 'sent' === ( $entry['status'] ?? '' ) ? '#46b450' : '#dc3232'; ?>;">
					<?php echo 'sent' === ( $entry['status'] ?? '' ) ? esc_html__( 'Sent', 'wp-kwtsms-otp' ) : esc_html__( 'Failed', 'wp-kwtsms-otp' ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p style="margin-top:8px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-logs&tab=sms_history' ) ); ?>">
			<?php esc_html_e( 'View full SMS history in Logs →', 'wp-kwtsms-otp' ); ?>
		</a>
	</p>
	<?php endif; ?>
</div>
