<?php
/**
 * Admin View: General Settings Page.
 *
 * Renders the OTP behaviour and CAPTCHA configuration form.
 * Uses the WordPress Settings API — form data is saved to kwtsms_otp_general.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- @var KwtSMS_Admin $this, injected by admin controller.
$settings              = $this->plugin->settings;
$general               = $settings->get( 'general' ) + KwtSMS_Settings::DEFAULTS['general'];
$gateway               = $settings->get( 'gateway' ) + KwtSMS_Settings::DEFAULTS['gateway'];
$credentials_verified  = ! empty( $gateway['credentials_verified'] );
$bal_available         = $gateway['balance_available'] ?? null;
$bal_purchased         = $gateway['balance_purchased'] ?? null;
$captcha_provider      = $general['captcha_provider'] ?? 'none';
$referral_link         = ! empty( $general['referral_link'] );
$default_cc            = $general['default_country_code'] ?? 'KW';
$allowed_iso2          = $general['allowed_countries'] ?? array( 'KW', 'SA', 'AE', 'BH', 'QA', 'OM' );
$debug_logging         = ! empty( $general['debug_logging'] );
$balance_failure_mode  = $general['balance_failure_mode'] ?? 'block';
$blocked_phones        = $general['blocked_phones'] ?? '';
$ip_allowlist          = $general['ip_allowlist'] ?? '';
$ip_blocklist          = $general['ip_blocklist'] ?? '';
$otp_required_roles    = $general['otp_required_roles'] ?? array();
$registration_otp_gate = $general['registration_otp_gate'] ?? 'disabled';
$all_wp_roles          = wp_roles()->get_names();

// Count users in OTP-required roles who have no phone number saved.
$no_phone_count = 0;
if ( ! empty( $otp_required_roles ) ) {
	$no_phone_args  = array(
		'number'     => -1,
		'fields'     => 'ids',
		'role__in'   => (array) $otp_required_roles,
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => 'kwtsms_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'kwtsms_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'value'   => '',
				'compare' => '=',
			),
		),
	);
	$no_phone_count = count( get_users( $no_phone_args ) );
}

// Load all countries for dropdowns.
$all_countries = include KWTSMS_OTP_DIR . 'includes/data/country-codes.php';
// Index by ISO2 for quick lookup.
$cc_by_iso2 = array();
foreach ( $all_countries as $cc ) {
	$cc_by_iso2[ $cc['iso2'] ] = $cc;
}
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'General Settings', 'wp-kwtsms' ); ?></h1>
	</div>
	<hr class="wp-header-end">

	<div class="kwtsms-intro-box" style="background:#fff8ed;border:1px solid #FFA200;border-radius:4px;padding:16px 20px;margin-bottom:20px;">
		<p style="margin:0;font-size:14px;line-height:1.6;">
			<?php esc_html_e( 'kwtSMS is a Kuwaiti SMS gateway trusted by top businesses to deliver messages anywhere in the world, with private Sender ID, free API testing, non-expiring credits, and competitive flat-rate pricing. Secure, simple to integrate, built to last.', 'wp-kwtsms' ); ?>
		</p>
		<p style="margin:8px 0 0;font-size:14px;line-height:1.6;">
			<?php esc_html_e( 'Open a free account easily under 1 minute, no papers or payment required.', 'wp-kwtsms' ); ?>
			<a href="https://www.kwtsms.com/signup/" target="_blank" rel="noopener noreferrer" style="color:#FFA200;font-weight:600;"><?php esc_html_e( '🚀 Click here to get started ', 'wp-kwtsms' ); ?></a>
		</p>
	</div>

	<!-- Balance Display (mirrors Gateway page) -->
	<div class="kwtsms-balance-bar" id="kwtsms-balance-card"<?php echo $credentials_verified ? '' : ' style="display:none;"'; ?>>
		<?php esc_html_e( 'Available balance:', 'wp-kwtsms' ); ?>
		<strong id="kwtsms-balance"><?php echo null !== $bal_available ? esc_html( number_format( (float) $bal_available, 2 ) ) : '—'; ?></strong>
		&nbsp;|&nbsp;
		<?php esc_html_e( 'Total purchased:', 'wp-kwtsms' ); ?>
		<span id="kwtsms-balance-purchased"><?php echo ( null !== $bal_purchased && $bal_purchased > 0 ) ? esc_html( number_format( (float) $bal_purchased, 2 ) ) : '—'; ?></span>
		<a href="https://www.kwtsms.com/login/" target="_blank" rel="noopener" style="margin-left:auto;font-size:13px;font-weight:600;"><?php esc_html_e( 'Recharge/Buy credits ', 'wp-kwtsms' ); ?></a>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_general_group' ); ?>

		<!-- ===== Login Behaviour ===== -->
		<h2 class="title"><?php esc_html_e( 'Login Behaviour', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label><?php esc_html_e( 'Enable Login OTP', 'wp-kwtsms' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[login_otp]" value="1" <?php checked( $general['login_otp'], 1 ); ?> />
						<?php esc_html_e( 'Require OTP on login', 'wp-kwtsms' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><label><?php esc_html_e( 'Enable Password Reset OTP', 'wp-kwtsms' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[reset_otp]" value="1" <?php checked( $general['reset_otp'], 1 ); ?> />
						<?php esc_html_e( 'Use SMS OTP for password reset (instead of email link)', 'wp-kwtsms' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><label><?php esc_html_e( 'OTP Mode', 'wp-kwtsms' ); ?></label></th>
				<td>
					<?php
					$modes = array(
						'2fa'          => __( 'Two-Factor Authentication (2FA): password + OTP', 'wp-kwtsms' ),
						'passwordless' => __( 'Passwordless: phone + OTP only', 'wp-kwtsms' ),
						'both'         => __( 'Both: users can choose', 'wp-kwtsms' ),
					);
					foreach ( $modes as $value => $label ) :
						?>
						<label style="display:block;margin-bottom:6px;">
							<input type="radio" name="kwtsms_otp_general[otp_mode]"
								value="<?php echo esc_attr( $value ); ?>"
								<?php checked( $general['otp_mode'], $value ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Require OTP for', 'wp-kwtsms' ); ?></th>
				<td>
					<?php foreach ( $all_wp_roles as $role_slug => $role_label ) : ?>
					<label style="display:block;margin-bottom:4px;">
						<input type="checkbox"
							name="kwtsms_otp_general[otp_required_roles][]"
							value="<?php echo esc_attr( $role_slug ); ?>"
							<?php checked( in_array( $role_slug, (array) $otp_required_roles, true ) ); ?> />
						<?php echo esc_html( translate_user_role( $role_label ) ); ?>
					</label>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'Check roles that must complete OTP verification. Unchecked roles log in directly without OTP. Administrators are unchecked by default to prevent lockout. On multisite, super admins are treated as administrators.', 'wp-kwtsms' ); ?>
					</p>
					<?php if ( $no_phone_count > 0 ) : ?>
					<p class="description" style="margin-top:8px;background:#fff8ed;border-left:3px solid #FFA200;padding:8px 12px;border-radius:0 3px 3px 0;">
						<?php
						printf(
							/* translators: 1: number of users without phone, 2: opening link tag, 3: closing link tag */
							esc_html__( '%1$d user(s) in OTP-required roles have no phone number saved and will bypass OTP. %2$sAdd phone numbers now.%3$s', 'wp-kwtsms' ),
							(int) $no_phone_count,
							'<a href="' . esc_url( admin_url( 'admin.php?page=kwtsms-otp-users' ) ) . '">',
							'</a>'
						);
						?>
					</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_otp_length"><?php esc_html_e( 'OTP Code Length', 'wp-kwtsms' ); ?></label></th>
				<td>
					<select name="kwtsms_otp_general[otp_length]" id="kwtsms_otp_length">
						<option value="4" <?php selected( $general['otp_length'], 4 ); ?>><?php esc_html_e( '4 digits', 'wp-kwtsms' ); ?></option>
						<option value="6" <?php selected( $general['otp_length'], 6 ); ?>><?php esc_html_e( '6 digits (recommended)', 'wp-kwtsms' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_otp_expiry"><?php esc_html_e( 'Code Expiry', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="number" name="kwtsms_otp_general[otp_expiry]" id="kwtsms_otp_expiry"
						value="<?php echo (int) $general['otp_expiry']; ?>" min="1" max="30" class="small-text" />
					<?php esc_html_e( 'minutes', 'wp-kwtsms' ); ?>
					<p class="description"><?php esc_html_e( 'Recommended: 5 minutes. Shorter = more secure, longer = more user-friendly.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_max_attempts"><?php esc_html_e( 'Max Verification Attempts', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="number" name="kwtsms_otp_general[max_attempts]" id="kwtsms_max_attempts"
						value="<?php echo (int) $general['max_attempts']; ?>" min="1" max="10" class="small-text" />
					<p class="description"><?php esc_html_e( 'Number of wrong OTP attempts before the code is invalidated.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_resend_cooldown"><?php esc_html_e( 'Resend Cooldown', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="number" name="kwtsms_otp_general[resend_cooldown]" id="kwtsms_resend_cooldown"
						value="<?php echo (int) $general['resend_cooldown']; ?>" min="30" max="600" class="small-text" />
					<?php esc_html_e( 'seconds', 'wp-kwtsms' ); ?>
					<p class="description"><?php esc_html_e( 'Minimum time between resend requests per user. Recommended: 120 seconds.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

		</table>

		<!-- ===== Notifications ===== -->
		<h2 class="title"><?php esc_html_e( 'Notifications', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_welcome_sms_enabled"><?php esc_html_e( 'Send Welcome SMS', 'wp-kwtsms' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[welcome_sms_enabled]" id="kwtsms_welcome_sms_enabled"
							value="1" <?php checked( ! empty( $general['welcome_sms_enabled'] ) ); ?> />
						<?php esc_html_e( 'Send a welcome SMS to every new user when they register.', 'wp-kwtsms' ); ?>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to Templates page */
							esc_html__( 'Configure the message text on the %s.', 'wp-kwtsms' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=kwtsms-otp-templates' ) ) . '">' . esc_html__( 'Templates page', 'wp-kwtsms' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>

		<tr>
			<th scope="row"><label for="kwtsms_registration_otp_gate"><?php esc_html_e( 'Registration OTP Gate', 'wp-kwtsms' ); ?></label></th>
			<td>
				<select id="kwtsms_registration_otp_gate" name="kwtsms_otp_general[registration_otp_gate]">
					<option value="disabled" <?php selected( $registration_otp_gate, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'wp-kwtsms' ); ?></option>
					<option value="optional" <?php selected( $registration_otp_gate, 'optional' ); ?>><?php esc_html_e( 'Optional (verify phone if provided)', 'wp-kwtsms' ); ?></option>
					<option value="required" <?php selected( $registration_otp_gate, 'required' ); ?>><?php esc_html_e( 'Required (phone and OTP mandatory)', 'wp-kwtsms' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Require OTP phone verification before creating a new user account. Works for standard WordPress registration and WooCommerce My Account registration.', 'wp-kwtsms' ); ?></p>
			</td>
		</tr>

		</table>

		<!-- ===== Phone & Country Settings ===== -->
		<h2 class="title"><?php esc_html_e( 'Phone &amp; Country Settings', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="kwtsms_default_country_code"><?php esc_html_e( 'Default Country Code', 'wp-kwtsms' ); ?></label>
				</th>
				<td>
					<select name="kwtsms_otp_general[default_country_code]" id="kwtsms_default_country_code">
						<?php foreach ( $all_countries as $cc ) : ?>
						<option value="<?php echo esc_attr( $cc['iso2'] ); ?>"
							<?php selected( $default_cc, $cc['iso2'] ); ?>>
							<?php echo esc_html( $cc['name'] . ' (+' . $cc['dial'] . ')' ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'This is pre-filled as the default dial code on login forms. Users only need to enter their local phone number without the country code. GeoIP detection will override this automatically when available.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Allowed Countries', 'wp-kwtsms' ); ?></th>
				<td>
					<div id="kwtsms-allowed-countries-wrap">
						<div id="kwtsms-allowed-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
							<?php foreach ( $allowed_iso2 as $iso2 ) : ?>
								<?php $cc_data = $cc_by_iso2[ $iso2 ] ?? null; ?>
								<?php if ( $cc_data ) : ?>
							<span class="kwtsms-country-tag" style="display:inline-flex;align-items:center;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;padding:3px 8px;font-size:13px;">
									<?php echo esc_html( $cc_data['name'] . ' (+' . $cc_data['dial'] . ')' ); ?>
								<button type="button" class="kwtsms-remove-country" data-iso2="<?php echo esc_attr( $iso2 ); ?>"
									style="background:none;border:none;cursor:pointer;margin-left:6px;color:#dc3232;font-weight:bold;font-size:14px;line-height:1;" aria-label="<?php esc_attr_e( 'Remove', 'wp-kwtsms' ); ?>">
									×
								</button>
							</span>
							<?php endif; ?>
							<?php endforeach; ?>
						</div>

						<div style="display:flex;gap:8px;align-items:center;">
							<select id="kwtsms-add-country-select">
								<option value=""><?php esc_html_e( '— Add a country —', 'wp-kwtsms' ); ?></option>
								<?php foreach ( $all_countries as $cc ) : ?>
								<option value="<?php echo esc_attr( $cc['iso2'] ); ?>"
									data-dial="<?php echo esc_attr( $cc['dial'] ); ?>"
									data-name="<?php echo esc_attr( $cc['name'] ); ?>">
									<?php echo esc_html( $cc['name'] . ' (+' . $cc['dial'] . ')' ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="kwtsms-add-country-btn" class="button">
								<?php esc_html_e( 'Add', 'wp-kwtsms' ); ?>
							</button>
						</div>

						<!-- Hidden field stores JSON array of ISO2 codes -->
						<input type="hidden" name="kwtsms_otp_general[allowed_countries]"
							id="kwtsms-allowed-countries-input"
							value="<?php echo esc_attr( wp_json_encode( $allowed_iso2 ) ); ?>" />
					</div>
					<p class="description">
						<?php esc_html_e( 'Controls which countries appear in the OTP login dial-code dropdown and which phone numbers this plugin will accept.', 'wp-kwtsms' ); ?>
					</p>
					<p class="description" style="margin-top:6px;background:#fff8ed;border-left:3px solid #FFA200;padding:8px 12px;border-radius:0 3px 3px 0;">
						<strong><?php esc_html_e( 'Important:', 'wp-kwtsms' ); ?></strong>
						<?php
						printf(
							/* translators: %s: link to the Gateway page */
							esc_html__( 'Adding a country here does NOT enable SMS delivery to that country. Delivery depends on which countries are active in your kwtSMS account coverage. Check your %s to see which countries you can currently send to. If a country is listed here but not covered in your account, SMS will silently fail.', 'wp-kwtsms' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ) . '">' . esc_html__( 'Gateway page (SMS Coverage section)', 'wp-kwtsms' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== CAPTCHA ===== -->
		<h2 class="title"><?php esc_html_e( 'CAPTCHA Protection', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label><?php esc_html_e( 'CAPTCHA Provider', 'wp-kwtsms' ); ?></label></th>
				<td>
					<?php
					$providers = array(
						'none'      => __( 'None', 'wp-kwtsms' ),
						'recaptcha' => __( 'Google reCAPTCHA v3 (invisible)', 'wp-kwtsms' ),
						'turnstile' => __( 'Cloudflare Turnstile', 'wp-kwtsms' ),
					);
					foreach ( $providers as $value => $label ) :
						?>
						<label style="display:block;margin-bottom:6px;">
							<input type="radio" name="kwtsms_otp_general[captcha_provider]"
								value="<?php echo esc_attr( $value ); ?>"
								id="captcha_provider_<?php echo esc_attr( $value ); ?>"
								<?php checked( $captcha_provider, $value ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>

			<tr class="kwtsms-captcha-fields kwtsms-recaptcha-fields" style="<?php echo 'recaptcha' !== $captcha_provider ? 'display:none;' : ''; ?>">
				<th scope="row"><?php esc_html_e( 'reCAPTCHA v3 Keys', 'wp-kwtsms' ); ?></th>
				<td>
					<input type="text" name="kwtsms_otp_general[recaptcha_site_key]"
						value="<?php echo esc_attr( $general['recaptcha_site_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Site Key', 'wp-kwtsms' ); ?>" /><br /><br />
					<input type="password" name="kwtsms_otp_general[recaptcha_secret_key]"
						value="<?php echo esc_attr( $general['recaptcha_secret_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Secret Key', 'wp-kwtsms' ); ?>" />
					<p class="description">
						<a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get keys from Google reCAPTCHA Admin Console ', 'wp-kwtsms' ); ?>
						</a>
					</p>
				</td>
			</tr>

			<tr class="kwtsms-captcha-fields kwtsms-turnstile-fields" style="<?php echo 'turnstile' !== $captcha_provider ? 'display:none;' : ''; ?>">
				<th scope="row"><?php esc_html_e( 'Turnstile Keys', 'wp-kwtsms' ); ?></th>
				<td>
					<input type="text" name="kwtsms_otp_general[turnstile_site_key]"
						value="<?php echo esc_attr( $general['turnstile_site_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Site Key', 'wp-kwtsms' ); ?>" /><br /><br />
					<input type="password" name="kwtsms_otp_general[turnstile_secret_key]"
						value="<?php echo esc_attr( $general['turnstile_secret_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Secret Key', 'wp-kwtsms' ); ?>" />
					<p class="description">
						<a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get keys from Cloudflare Dashboard ', 'wp-kwtsms' ); ?>
						</a>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== Security ===== -->
		<h2 class="title"><?php esc_html_e( 'Security', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="kwtsms_blocked_phones"><?php esc_html_e( 'Blocked Phone Numbers', 'wp-kwtsms' ); ?></label>
				</th>
				<td>
					<textarea name="kwtsms_otp_general[blocked_phones]" id="kwtsms_blocked_phones"
						rows="6" class="large-text code"
						placeholder="96599000000&#10;96566000000"><?php echo esc_textarea( $blocked_phones ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One per line or comma-separated, with country code (digits only). These numbers will never receive OTP SMS. Blocked requests return a silent success to prevent enumeration.', 'wp-kwtsms' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== IP Rules ===== -->
		<h2 class="title"><?php esc_html_e( 'IP Rules', 'wp-kwtsms' ); ?></h2>
		<p style="margin-top:-8px;color:#555;font-size:13px;">
			<?php esc_html_e( 'One IPv4 or IPv6 address or CIDR per line (e.g. 192.168.1.0/24). Allowlisted IPs skip per-IP rate limiting. Blocklisted IPs receive a silent refusal.', 'wp-kwtsms' ); ?>
		</p>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="kwtsms_ip_allowlist"><?php esc_html_e( 'IP Allowlist', 'wp-kwtsms' ); ?></label>
				</th>
				<td>
					<textarea name="kwtsms_otp_general[ip_allowlist]" id="kwtsms_ip_allowlist"
						rows="5" class="large-text code"
						placeholder="192.168.1.0/24&#10;10.0.0.5"><?php echo esc_textarea( $ip_allowlist ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'These IPs skip the per-IP rate limit and proxy detection (when enabled). OTP is still required.', 'wp-kwtsms' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="kwtsms_ip_blocklist"><?php esc_html_e( 'IP Blocklist', 'wp-kwtsms' ); ?></label>
				</th>
				<td>
					<textarea name="kwtsms_otp_general[ip_blocklist]" id="kwtsms_ip_blocklist"
						rows="5" class="large-text code"
						placeholder="185.220.101.0/24&#10;45.33.32.156"><?php echo esc_textarea( $ip_blocklist ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'These IPs are silently refused. The response is identical to a rate-limit error to prevent enumeration of this list.', 'wp-kwtsms' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== Referral Link ===== -->
		<h2 class="title"><?php esc_html_e( 'Powered-by Footer', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_referral_link"><?php esc_html_e( 'Show Referral Link', 'wp-kwtsms' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[referral_link]" id="kwtsms_referral_link"
							value="1" <?php checked( $referral_link ); ?> />
						<?php esc_html_e( 'Display "SMS by kwtSMS.com" footer on login pages', 'wp-kwtsms' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'The link text is fixed and cannot be customized.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

		</table>

		<!-- ===== Developer Tools ===== -->
		<h2 class="title"><?php esc_html_e( 'Developer Tools', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_debug_logging"><?php esc_html_e( 'Debug Logging', 'wp-kwtsms' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[debug_logging]" id="kwtsms_debug_logging"
							value="1" <?php checked( $debug_logging ); ?> />
						<?php esc_html_e( 'Enable detailed logging for troubleshooting.', 'wp-kwtsms' ); ?>
					</label>
					<?php if ( $debug_logging ) : ?>
					<p class="description" style="color:#d63638;font-weight:600;">
						<?php esc_html_e( '⚠ Debug Logging is ON, disable this on production sites.', 'wp-kwtsms' ); ?>
					</p>
					<?php endif; ?>
					<p class="description">
						<?php
						// Show relative path (e.g. wp-content/kwtsms-debug.log) regardless of server layout.
						$log_path = ( defined( 'ABSPATH' ) && defined( 'WP_CONTENT_DIR' ) )
						? str_replace( trailingslashit( ABSPATH ), '', WP_CONTENT_DIR ) . '/kwtsms-debug.log'
						: 'wp-content/kwtsms-debug.log';
						printf(
							/* translators: %s: path to the debug log file */
							esc_html__( 'Log file: %s', 'wp-kwtsms' ),
							'<code>' . esc_html( $log_path ) . '</code>'
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-help' ) ); ?>">
							<?php esc_html_e( 'See Help page for troubleshooting guide ', 'wp-kwtsms' ); ?>
						</a>
					</p>
				</td>
			</tr>

		</table>

	<!-- ===== On Balance Failure ===== -->
	<h2 class="title"><?php esc_html_e( 'On Balance Failure', 'wp-kwtsms' ); ?></h2>
	<p style="margin-top:-8px;color:#555;font-size:13px;">
		<?php esc_html_e( 'Decides what happens when kwtSMS cannot send an OTP because your SMS credit balance is zero. An admin email is always sent when this condition is first detected.', 'wp-kwtsms' ); ?>
	</p>
	<table class="form-table" role="presentation">

		<tr>
			<th scope="row"><?php esc_html_e( 'When credits run out', 'wp-kwtsms' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;margin-bottom:10px;">
						<input type="radio" name="kwtsms_otp_general[balance_failure_mode]" value="block"
							<?php checked( $balance_failure_mode, 'block' ); ?> />
						<strong><?php esc_html_e( 'Block logins (Recommended)', 'wp-kwtsms' ); ?></strong>
						<p class="description" style="margin-left:24px;">
							<?php esc_html_e( 'Users who require OTP cannot log in until the account is recharged. This keeps OTP enforcement intact and makes the outage visible.', 'wp-kwtsms' ); ?>
						</p>
					</label>
					<label style="display:block;">
						<input type="radio" name="kwtsms_otp_general[balance_failure_mode]" value="allow"
							<?php checked( $balance_failure_mode, 'allow' ); ?> />
						<?php esc_html_e( 'Allow login without OTP (password only)', 'wp-kwtsms' ); ?>
						<p class="description" style="margin-left:24px;color:#d63638;">
							<?php esc_html_e( 'Users bypass OTP and log in with password alone until credits are restored. Choose this only if uninterrupted access is more important than 2FA enforcement.', 'wp-kwtsms' ); ?>
						</p>
					</label>
				</fieldset>
			</td>
		</tr>

	</table>

		<?php submit_button( __( 'Save Settings', 'wp-kwtsms' ), 'primary kwtsms-save-btn' ); ?>
	</form>

	<?php
	// Load security settings for the IPHub form.
	$security            = $settings->get( 'security' ) + KwtSMS_Settings::DEFAULTS['security'];
	$iphub_api_key       = $security['iphub_api_key'] ?? '';
	$iphub_enabled       = ! empty( $security['iphub_enabled'] );
	$iphub_action_block1 = $security['iphub_action_block1'] ?? 'block';
	$iphub_action_block2 = $security['iphub_action_block2'] ?? 'log';
	$iphub_cache_ttl     = (int) ( $security['iphub_cache_ttl'] ?? 86400 );
	?>

	<!-- ===== IPHub Proxy/VPN Detection ===== -->
	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_security_group' ); ?>

		<h2 class="title"><?php esc_html_e( 'Proxy and VPN Detection (IPHub)', 'wp-kwtsms' ); ?></h2>
		<p style="margin-top:-8px;color:#555;font-size:13px;">
			<?php
			printf(
				/* translators: %s: IPHub website link */
				esc_html__( 'Uses the %s API to identify and act on OTP requests from known proxy or VPN IP addresses. Allowlisted IPs (above) always bypass this check.', 'wp-kwtsms' ),
				'<a href="https://iphub.info/" target="_blank" rel="noopener noreferrer">IPHub</a>'
			);
			?>
		</p>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_iphub_enabled"><?php esc_html_e( 'Enable IPHub Detection', 'wp-kwtsms' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_security[iphub_enabled]" id="kwtsms_iphub_enabled"
							value="1" <?php checked( $iphub_enabled ); ?> />
						<?php esc_html_e( 'Check each OTP request IP against the IPHub reputation database.', 'wp-kwtsms' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_iphub_api_key"><?php esc_html_e( 'IPHub API Key', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="password" name="kwtsms_otp_security[iphub_api_key]" id="kwtsms_iphub_api_key"
						value="<?php echo esc_attr( $iphub_api_key ); ?>"
						class="regular-text" autocomplete="new-password"
						placeholder="<?php esc_attr_e( 'Paste your IPHub API key here', 'wp-kwtsms' ); ?>" />
					<p class="description">
						<a href="https://iphub.info/register" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get a free API key from IPHub.info (up to 1,000 lookups/day on the free plan) ', 'wp-kwtsms' ); ?>
						</a>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_iphub_action_block1"><?php esc_html_e( 'Action for Level 1 (Proxy/VPN)', 'wp-kwtsms' ); ?></label></th>
				<td>
					<select name="kwtsms_otp_security[iphub_action_block1]" id="kwtsms_iphub_action_block1">
						<option value="block" <?php selected( $iphub_action_block1, 'block' ); ?>><?php esc_html_e( 'Block silently (recommended)', 'wp-kwtsms' ); ?></option>
						<option value="log"   <?php selected( $iphub_action_block1, 'log' ); ?>><?php esc_html_e( 'Log only, allow through', 'wp-kwtsms' ); ?></option>
						<option value="allow" <?php selected( $iphub_action_block1, 'allow' ); ?>><?php esc_html_e( 'Allow (no action)', 'wp-kwtsms' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'IP identified as a confirmed proxy or VPN exit node.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_iphub_action_block2"><?php esc_html_e( 'Action for Level 2 (Mixed)', 'wp-kwtsms' ); ?></label></th>
				<td>
					<select name="kwtsms_otp_security[iphub_action_block2]" id="kwtsms_iphub_action_block2">
						<option value="log"   <?php selected( $iphub_action_block2, 'log' ); ?>><?php esc_html_e( 'Log only, allow through (recommended)', 'wp-kwtsms' ); ?></option>
						<option value="block" <?php selected( $iphub_action_block2, 'block' ); ?>><?php esc_html_e( 'Block silently', 'wp-kwtsms' ); ?></option>
						<option value="allow" <?php selected( $iphub_action_block2, 'allow' ); ?>><?php esc_html_e( 'Allow (no action)', 'wp-kwtsms' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'IP from a range that contains a mix of residential and proxy traffic.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_iphub_cache_ttl"><?php esc_html_e( 'Cache TTL', 'wp-kwtsms' ); ?></label></th>
				<td>
					<input type="number" name="kwtsms_otp_security[iphub_cache_ttl]" id="kwtsms_iphub_cache_ttl"
						value="<?php echo (int) $iphub_cache_ttl; ?>"
						min="3600" max="604800" class="small-text" />
					<?php esc_html_e( 'seconds', 'wp-kwtsms' ); ?>
					<p class="description">
						<?php esc_html_e( 'How long to cache each IP reputation result (min: 1 hour = 3600 s, max: 7 days = 604800 s, default: 86400 s = 1 day). Cached results avoid repeated API calls for the same IP.', 'wp-kwtsms' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Security Settings', 'wp-kwtsms' ), 'primary kwtsms-save-btn' ); ?>
	</form>

</div>

<script>
// Allowed countries tag manager
(function() {
	'use strict';
	const tagsDiv   = document.getElementById('kwtsms-allowed-tags');
	const input     = document.getElementById('kwtsms-allowed-countries-input');
	const addSelect = document.getElementById('kwtsms-add-country-select');
	const addBtn    = document.getElementById('kwtsms-add-country-btn');
	if ( ! tagsDiv || ! input || ! addSelect || ! addBtn ) return;

	function getIso2List() {
		try { return JSON.parse(input.value) || []; } catch(e) { return []; }
	}

	function setIso2List(list) {
		input.value = JSON.stringify(list);
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}

	const removeLabel = '<?php echo esc_js( __( 'Remove', 'wp-kwtsms' ) ); ?>';

	function renderTag(iso2, dial, name) {
		const span = document.createElement('span');
		span.className = 'kwtsms-country-tag';
		span.style.cssText = 'display:inline-flex;align-items:center;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;padding:3px 8px;font-size:13px;';
		span.dataset.iso2  = iso2;
		span.textContent   = name + ' (+' + dial + ')';
		const btn = document.createElement('button');
		btn.type      = 'button';
		btn.className = 'kwtsms-remove-country';
		btn.dataset.iso2 = iso2;
		btn.style.cssText = 'background:none;border:none;cursor:pointer;margin-left:6px;color:#dc3232;font-weight:bold;font-size:14px;line-height:1;';
		btn.textContent = '×';
		btn.setAttribute( 'aria-label', removeLabel );
		btn.addEventListener('click', function() {
			const list = getIso2List().filter(function(c) { return c !== iso2; });
			setIso2List(list);
			span.remove();
		});
		span.appendChild(btn);
		return span;
	}

	// Bind remove buttons on existing tags.
	tagsDiv.querySelectorAll('.kwtsms-remove-country').forEach(function(btn) {
		btn.addEventListener('click', function() {
			const iso2 = btn.dataset.iso2;
			const list = getIso2List().filter(function(c) { return c !== iso2; });
			setIso2List(list);
			btn.closest('.kwtsms-country-tag').remove();
		});
	});

	// Add button.
	addBtn.addEventListener('click', function() {
		const opt  = addSelect.options[addSelect.selectedIndex];
		const iso2 = opt.value;
		const dial = opt.dataset.dial;
		const name = opt.dataset.name;
		if (!iso2) return;
		const list = getIso2List();
		if (list.indexOf(iso2) !== -1) return; // already added
		list.push(iso2);
		setIso2List(list);
		tagsDiv.appendChild(renderTag(iso2, dial, name));
		addSelect.selectedIndex = 0;
	});
})();
</script>
