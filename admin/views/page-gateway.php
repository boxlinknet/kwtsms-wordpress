<?php
/**
 * Admin View: Gateway Settings Page.
 *
 * API information, login verification, sender ID selection, SMS coverage,
 * test mode, and gateway test SMS.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- @var KwtSMS_Admin $this, injected by admin controller.
$settings             = $this->plugin->settings;
$gateway              = $settings->get( 'gateway' ) + KwtSMS_Settings::DEFAULTS['gateway'];
$sender_id            = $gateway['sender_id'] ?? '';
$test_mode            = ! empty( $gateway['test_mode'] );
$credentials_verified = ! empty( $gateway['credentials_verified'] );
$sender_ids           = $gateway['sender_ids'] ?? array();

// Build dial-code lookups for coverage pills.
$_cc_data_all  = include KWTSMS_OTP_DIR . 'includes/data/country-codes.php';
$_dial_by_name = array(); // Maps lowercase name to dial code.
$_dial_by_iso2 = array(); // Maps ISO2 to dial code.
$_name_by_dial = array(); // Maps dial code to country name.
$_iso2_by_dial = array(); // Maps dial code to ISO2.
foreach ( $_cc_data_all as $_cce ) {
	$_dial_by_name[ strtolower( $_cce['name'] ) ] = $_cce['dial'];
	$_dial_by_iso2[ $_cce['iso2'] ]               = $_cce['dial'];
	$_name_by_dial[ $_cce['dial'] ]               = $_cce['name'];
	$_iso2_by_dial[ $_cce['dial'] ]               = $_cce['iso2'];
}
unset( $_cc_data_all, $_cce );

// API status codes that must never be treated as country names.
$_api_codes = array( 'OK', 'ERROR', 'ERR', 'FAIL', 'FAILED', 'NULL', 'NONE', 'N/A', 'NA', 'TRUE', 'FALSE' );
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'Gateway Settings', 'wp-kwtsms' ); ?></h1>
	</div>
	<hr class="wp-header-end">

	<!-- Balance Display -->
	<?php
	$bal_available = $gateway['balance_available'] ?? null;
	$bal_purchased = $gateway['balance_purchased'] ?? null;
	?>
	<div class="kwtsms-balance-bar" id="kwtsms-balance-card"<?php echo $credentials_verified ? '' : ' style="display:none;"'; ?>>
		<?php esc_html_e( 'Available balance:', 'wp-kwtsms' ); ?>
		<strong id="kwtsms-balance"><?php echo null !== $bal_available ? esc_html( number_format( (float) $bal_available, 2 ) ) : '—'; ?></strong>
		&nbsp;&mdash;&nbsp;
		<?php esc_html_e( 'Total purchased:', 'wp-kwtsms' ); ?>
		<span id="kwtsms-balance-purchased"><?php echo ( null !== $bal_purchased && $bal_purchased > 0 ) ? esc_html( number_format( (float) $bal_purchased, 2 ) ) : '—'; ?></span>
		<a href="https://www.kwtsms.com/login/" target="_blank" rel="noopener" style="margin-left:auto;font-size:13px;font-weight:600;"><?php esc_html_e( 'Recharge/Buy credits ', 'wp-kwtsms' ); ?></a>
	</div>

	<form method="post" action="options.php" id="kwtsms-gateway-form">
		<?php settings_fields( 'kwtsms_otp_gateway_group' ); ?>

		<?php if ( ! $credentials_verified ) : ?>
		<div class="kwtsms-api-status is-info kwtsms-signup-note">
			<?php esc_html_e( "Don't have a kwtSMS account?", 'wp-kwtsms' ); ?>
			<a href="https://www.kwtsms.com/signup" target="_blank" rel="noopener" style="color:#46b450;font-weight:600;">
				<?php esc_html_e( 'Sign up for free ', 'wp-kwtsms' ); ?>
			</a>
		</div>
		<?php endif; ?>

		<!-- ===== API Information ===== -->
		<h2 class="title"><?php esc_html_e( 'API Information', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr id="kwtsms-row-username"<?php echo $credentials_verified ? ' style="display:none;"' : ''; ?>>
				<th scope="row"><label for="kwtsms_api_username"><?php esc_html_e( 'API Username', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="text" name="kwtsms_otp_gateway[api_username]" id="kwtsms_api_username"
						value="<?php echo esc_attr( $gateway['api_username'] ); ?>"
						class="regular-text" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Your kwtSMS API username, found in your kwtSMS account under API Settings, not your login username.', 'wp-kwtsms' ); ?></p>
					<p class="description" id="kwtsms-username-warning" style="color:#dc3232;display:none;"></p>
				</td>
			</tr>

			<tr id="kwtsms-row-password"<?php echo $credentials_verified ? ' style="display:none;"' : ''; ?>>
				<th scope="row"><label for="kwtsms_api_password"><?php esc_html_e( 'API Password', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="password" name="kwtsms_otp_gateway[api_password]" id="kwtsms_api_password"
						value=""
						placeholder="<?php echo $credentials_verified ? esc_attr__( '(leave blank to keep current password)', 'wp-kwtsms' ) : ''; ?>"
						class="regular-text" autocomplete="new-password" />
					<p class="description"><?php esc_html_e( 'Your kwtSMS API password, found in your kwtSMS account under API Settings, not your login password.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"></th>
				<td>
					<div style="display:flex;flex-direction:column;gap:8px;align-items:flex-start;">
						<?php if ( $credentials_verified ) : ?>
						<button type="button" id="kwtsms-login-btn" class="button button-primary" style="display:none;">
							<?php esc_html_e( 'Login', 'wp-kwtsms' ); ?>
						</button>
						<div style="display:flex;flex-direction:row;gap:10px;align-items:center;">
							<button type="button" id="kwtsms-reload-all" class="button">
								&#x21BB; <?php esc_html_e( 'Reload', 'wp-kwtsms' ); ?>
							</button>
							<button type="button" id="kwtsms-logout-btn" class="button">
								<?php esc_html_e( 'Logout', 'wp-kwtsms' ); ?>
							</button>
							<span id="kwtsms-login-status" style="font-size:13px;font-weight:600;" aria-live="polite">
								<span style="color:#46b450;">&#x2713; 
								<?php
								/* translators: %s: API username */
								printf( esc_html__( 'Connected as %s', 'wp-kwtsms' ), esc_html( $gateway['api_username'] ) );
								?>
								</span>
							</span>
						</div>
						<p class="description kwtsms-reload-hint"><?php esc_html_e( 'Fetches latest Sender IDs, coverage, and balance from your kwtSMS account.', 'wp-kwtsms' ); ?></p>
						<?php else : ?>
						<button type="button" id="kwtsms-login-btn" class="button button-primary">
							<?php esc_html_e( 'Login', 'wp-kwtsms' ); ?>
						</button>
						<div style="display:flex;flex-direction:row;gap:10px;align-items:center;">
							<button type="button" id="kwtsms-reload-all" class="button" style="display:none;">
								&#x21BB; <?php esc_html_e( 'Reload', 'wp-kwtsms' ); ?>
							</button>
							<button type="button" id="kwtsms-logout-btn" class="button" style="display:none;">
								<?php esc_html_e( 'Logout', 'wp-kwtsms' ); ?>
							</button>
							<span id="kwtsms-login-status" style="font-size:13px;font-weight:600;" aria-live="polite"></span>
						</div>
						<p class="description kwtsms-reload-hint" style="display:none;"><?php esc_html_e( 'Fetches latest Sender IDs, coverage, and balance from your kwtSMS account.', 'wp-kwtsms' ); ?></p>
						<?php endif; ?>
					</div>
					</td>
			</tr>

		</table>

		<!-- Dependent sections — hidden until credentials are verified -->
		<div id="kwtsms-verified-sections"<?php echo $credentials_verified ? '' : ' style="display:none;"'; ?>>

		<!-- ===== Test Mode ===== -->
		<h2 class="title"><?php esc_html_e( 'Test Mode', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_test_mode"><?php esc_html_e( 'Enable Test Mode', 'wp-kwtsms' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_gateway[test_mode]" id="kwtsms_test_mode"
							value="1" <?php checked( $test_mode ); ?> />
						<?php esc_html_e( 'Messages are queued but not delivered. Delete from kwtSMS queue to recover credits.', 'wp-kwtsms' ); ?>
					</label>
					<?php if ( $test_mode ) : ?>
					<p class="description" style="color:#d63638;font-weight:600;">
						<?php esc_html_e( '⚠ Test Mode is ON. The SMS will be queued but will NOT be delivered to your phone.', 'wp-kwtsms' ); ?>
					</p>
					<?php endif; ?>
				</td>
			</tr>

		</table>

		<!-- ===== Sender ID ===== -->
		<table class="form-table" role="presentation">

			<tr id="kwtsms-sender-row">
				<th scope="row"><label for="kwtsms_sender_id"><?php esc_html_e( 'Sender ID', 'wp-kwtsms' ); ?></label></th>
				<td>
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
								<option value=""><?php esc_html_e( '— Login to load —', 'wp-kwtsms' ); ?></option>
							<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'This is the name recipients see as the sender of your SMS messages. Choose from the sender IDs registered on your kwtSMS account.', 'wp-kwtsms' ); ?></p>
					<p class="description">
						<a href="https://www.kwtsms.com/sender-id-help.html" target="_blank" rel="noopener">
							<?php esc_html_e( 'Register or request a Sender ID at kwtSMS ', 'wp-kwtsms' ); ?>
						</a>
					</p>
				</td>
			</tr>

		</table>

		<div style="margin-top:20px;">
			<?php submit_button( __( 'Save Settings', 'wp-kwtsms' ), 'primary kwtsms-save-btn', 'submit', false ); ?>
		</div>
		<hr style="margin:20px 0;" />

		<!-- ===== SMS Coverage ===== -->
		<h2 class="title"><?php esc_html_e( 'SMS Coverage', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active Coverage', 'wp-kwtsms' ); ?></th>
				<td id="kwtsms-coverage-section" aria-live="polite">
					<div id="kwtsms-coverage-result" style="display:flex;flex-wrap:wrap;gap:6px;" aria-live="polite">
						<?php
						$saved_cov = $gateway['coverage'] ?? array();
						if ( ! empty( $saved_cov ) ) :
							foreach ( $saved_cov as $c ) :
								$_cname = '';
								$_cdial = '';
								if ( is_array( $c ) ) {
									$_cname = $c['name'] ?? $c['country'] ?? $c['countryName'] ?? $c['CountryName'] ?? $c['cc'] ?? '';
									if ( '' === $_cname ) {
										foreach ( $c as $v ) {
											if ( is_string( $v ) && '' !== $v ) {
												$_cname = $v;
												break; }
										}
									}
									if ( '' === $_cname ) {
										continue;
									}
									// Skip API status codes stored as country names.
									if ( in_array( strtoupper( $_cname ), $_api_codes, true ) ) {
										continue;
									}
									// Bare dial-code digit string stored as name? Resolve to country.
									if ( ctype_digit( $_cname ) ) {
										$_rname = $_name_by_dial[ $_cname ] ?? '';
										if ( '' === $_rname ) {
											continue;
										}
										$_cdial = $_cname;
										$_cname = $_rname;
									} else {
										$_cdial = $c['dial'] ?? $_dial_by_name[ strtolower( $_cname ) ] ?? '';
										if ( '' === $_cdial && ! empty( $c['cc'] ) ) {
											$_cdial = $_dial_by_iso2[ strtoupper( $c['cc'] ) ] ?? '';
										}
									}
								} else {
									$_cname = trim( (string) $c );
									if ( '' === $_cname ) {
										continue;
									}
									// Skip API status codes.
									if ( in_array( strtoupper( $_cname ), $_api_codes, true ) ) {
										continue;
									}
									// Bare dial-code digit string? Resolve to country name.
									if ( ctype_digit( $_cname ) ) {
										$_rname = $_name_by_dial[ $_cname ] ?? '';
										if ( '' === $_rname ) {
											continue;
										}
										$_cdial = $_cname;
										$_cname = $_rname;
									} else {
										$_cdial = $_dial_by_name[ strtolower( $_cname ) ] ?? '';
									}
								}
								$_clabel = '' !== $_cdial ? $_cname . ' (+' . $_cdial . ')' : $_cname;
								echo '<span class="kwtsms-tag-chip">' . esc_html( $_clabel ) . '</span>';
							endforeach;
						endif;
						?>
					</div>
					<p class="description" style="margin-top:8px;">
						<?php esc_html_e( 'These are the countries your account can currently send SMS to. To send to additional countries, request coverage from your kwtSMS account.', 'wp-kwtsms' ); ?>
						&nbsp;<a href="https://www.kwtsms.com/coverage/" target="_blank" rel="noopener"><?php esc_html_e( 'Request more coverage ', 'wp-kwtsms' ); ?></a>
					</p>
				</td>
			</tr>
		</table>

		<!-- ===== Gateway Test ===== -->
		<h2 class="title"><?php esc_html_e( 'Gateway Test', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_test_phone"><?php esc_html_e( 'Test Phone Number', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="tel" id="kwtsms_test_phone"
						value=""
						class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 96512345678', 'wp-kwtsms' ); ?>" />
					<p class="description"><?php esc_html_e( 'Enter the full number with country code. e.g. Kuwait: 965 + 8 digits = 96512345678 (11 digits total).', 'wp-kwtsms' ); ?></p>
					<?php if ( $test_mode ) : ?>
					<p class="description" style="color:#dc3232;font-weight:700;">
						<?php esc_html_e( '⚠ Test Mode is ON. The SMS will be queued but will NOT be delivered to your phone.', 'wp-kwtsms' ); ?>
					</p>
					<?php else : ?>
					<p class="description" style="color:#2a7a2f;font-weight:600;">
						<?php esc_html_e( 'Test Mode is currently OFF. The SMS will be delivered to the phone number above. SMS credits will be consumed.', 'wp-kwtsms' ); ?>
					</p>
					<?php endif; ?>
					<div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
						<button type="button" id="kwtsms-send-test-sms" class="button button-primary"<?php echo $credentials_verified ? '' : ' disabled'; ?>>
							<?php esc_html_e( 'Send Test SMS', 'wp-kwtsms' ); ?>
						</button>
						<span id="kwtsms-test-sms-result" style="font-size:13px;line-height:1.5;" aria-live="polite"></span>
					</div>
				</td>
			</tr>

		</table>

		</div><!-- #kwtsms-verified-sections -->

	</form>

</div>
