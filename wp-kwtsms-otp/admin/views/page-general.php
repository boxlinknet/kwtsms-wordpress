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

/** @var KwtSMS_Plugin $this — plugin manager, injected by KwtSMS_Admin via include */
$settings             = $this->plugin->settings;
$general              = $settings->get( 'general' ) + KwtSMS_Settings::DEFAULTS['general'];
$gateway              = $settings->get( 'gateway' ) + KwtSMS_Settings::DEFAULTS['gateway'];
$credentials_verified = ! empty( $gateway['credentials_verified'] );
$bal_available        = $gateway['balance_available'] ?? null;
$bal_purchased        = $gateway['balance_purchased'] ?? null;
$captcha_provider = $general['captcha_provider'] ?? 'none';
$referral_link    = ! empty( $general['referral_link'] );
$default_cc       = $general['default_country_code'] ?? 'KW';
$allowed_iso2     = $general['allowed_countries'] ?? array( 'KW', 'SA', 'AE', 'BH', 'QA', 'OM' );
$debug_logging        = ! empty( $general['debug_logging'] );
$blocked_phones       = $general['blocked_phones'] ?? '';
$otp_required_roles   = $general['otp_required_roles'] ?? array();
$all_wp_roles         = wp_roles()->get_names();

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
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'General Settings', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<div class="kwtsms-intro-box" style="background:#fff8ed;border:1px solid #FFA200;border-radius:4px;padding:16px 20px;margin-bottom:20px;">
		<p style="margin:0 0 8px;font-size:14px;line-height:1.6;">
			<?php esc_html_e( 'kwtSMS is a Kuwaiti SMS gateway with global coverage, trusted by businesses across the GCC. Key features include: global SMS delivery to 200+ countries, credits that never expire, private Sender ID registration, free API testing, competitive rates, and a simple REST API. Create a free account to get started. Enter your kwtSMS API credentials in the', 'wp-kwtsms-otp' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>" style="color:#FFA200;font-weight:600;"><?php esc_html_e( 'Gateway Settings', 'wp-kwtsms-otp' ); ?></a>.
		</p>
		<p style="margin:0;">
			<a href="https://www.kwtsms.com/signup" target="_blank" rel="noopener noreferrer" style="color:#FFA200;font-weight:600;">
				🚀 <?php esc_html_e( 'Sign up & test for free in under a minute', 'wp-kwtsms-otp' ); ?>
			</a>
		</p>
	</div>

	<!-- Balance Display (mirrors Gateway page) -->
	<div class="kwtsms-balance-bar" id="kwtsms-balance-card"<?php echo $credentials_verified ? '' : ' style="display:none;"'; ?>>
		<?php esc_html_e( 'Available balance:', 'wp-kwtsms-otp' ); ?>
		<strong id="kwtsms-balance"><?php echo null !== $bal_available ? esc_html( number_format( (float) $bal_available, 2 ) ) : '—'; ?></strong>
		&nbsp;&mdash;&nbsp;
		<?php esc_html_e( 'Total purchased:', 'wp-kwtsms-otp' ); ?>
		<span id="kwtsms-balance-purchased"><?php echo ( null !== $bal_purchased && $bal_purchased > 0 ) ? esc_html( number_format( (float) $bal_purchased, 2 ) ) : '—'; ?></span>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_general_group' ); ?>

		<!-- ===== Login Behaviour ===== -->
		<h2 class="title"><?php esc_html_e( 'Login Behaviour', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label><?php esc_html_e( 'Enable Login OTP', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[login_otp]" value="1" <?php checked( $general['login_otp'], 1 ); ?> />
						<?php esc_html_e( 'Require OTP on login', 'wp-kwtsms-otp' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><label><?php esc_html_e( 'Enable Password Reset OTP', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[reset_otp]" value="1" <?php checked( $general['reset_otp'], 1 ); ?> />
						<?php esc_html_e( 'Use SMS OTP for password reset (instead of email link)', 'wp-kwtsms-otp' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><label><?php esc_html_e( 'OTP Mode', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<?php
					$modes = array(
						'2fa'          => __( 'Two-Factor Authentication (2FA) — password + OTP', 'wp-kwtsms-otp' ),
						'passwordless' => __( 'Passwordless — phone + OTP only', 'wp-kwtsms-otp' ),
						'both'         => __( 'Both — users can choose', 'wp-kwtsms-otp' ),
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
				<th scope="row"><?php esc_html_e( 'Require OTP for', 'wp-kwtsms-otp' ); ?></th>
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
						<?php esc_html_e( 'Check roles that must complete OTP verification. Unchecked roles log in directly without OTP. Administrators are unchecked by default to prevent lockout. On multisite, super admins are treated as administrators.', 'wp-kwtsms-otp' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_otp_length"><?php esc_html_e( 'OTP Code Length', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<select name="kwtsms_otp_general[otp_length]" id="kwtsms_otp_length">
						<option value="4" <?php selected( $general['otp_length'], 4 ); ?>><?php esc_html_e( '4 digits', 'wp-kwtsms-otp' ); ?></option>
						<option value="6" <?php selected( $general['otp_length'], 6 ); ?>><?php esc_html_e( '6 digits (recommended)', 'wp-kwtsms-otp' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_otp_expiry"><?php esc_html_e( 'Code Expiry', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="number" name="kwtsms_otp_general[otp_expiry]" id="kwtsms_otp_expiry"
						value="<?php echo (int) $general['otp_expiry']; ?>" min="1" max="30" class="small-text" />
					<?php esc_html_e( 'minutes', 'wp-kwtsms-otp' ); ?>
					<p class="description"><?php esc_html_e( 'Recommended: 5 minutes. Shorter = more secure, longer = more user-friendly.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_max_attempts"><?php esc_html_e( 'Max Verification Attempts', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="number" name="kwtsms_otp_general[max_attempts]" id="kwtsms_max_attempts"
						value="<?php echo (int) $general['max_attempts']; ?>" min="1" max="10" class="small-text" />
					<p class="description"><?php esc_html_e( 'Number of wrong OTP attempts before the code is invalidated.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="kwtsms_resend_cooldown"><?php esc_html_e( 'Resend Cooldown', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<input type="number" name="kwtsms_otp_general[resend_cooldown]" id="kwtsms_resend_cooldown"
						value="<?php echo (int) $general['resend_cooldown']; ?>" min="30" max="600" class="small-text" />
					<?php esc_html_e( 'seconds', 'wp-kwtsms-otp' ); ?>
					<p class="description"><?php esc_html_e( 'Minimum time between resend requests per user. Recommended: 120 seconds.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

		</table>

		<!-- ===== Phone & Country Settings ===== -->
		<h2 class="title"><?php esc_html_e( 'Phone &amp; Country Settings', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="kwtsms_default_country_code"><?php esc_html_e( 'Default Country Code', 'wp-kwtsms-otp' ); ?></label>
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
					<p class="description"><?php esc_html_e( 'This is pre-filled as the default dial code on login forms. Users only need to enter their local phone number without the country code. GeoIP detection will override this automatically when available.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Allowed Countries', 'wp-kwtsms-otp' ); ?></th>
				<td>
					<div id="kwtsms-allowed-countries-wrap">
						<div id="kwtsms-allowed-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
							<?php foreach ( $allowed_iso2 as $iso2 ) : ?>
							<?php $cc_data = $cc_by_iso2[ $iso2 ] ?? null; ?>
							<?php if ( $cc_data ) : ?>
							<span class="kwtsms-country-tag" style="display:inline-flex;align-items:center;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;padding:3px 8px;font-size:13px;">
								<?php echo esc_html( $cc_data['name'] . ' (+' . $cc_data['dial'] . ')' ); ?>
								<button type="button" class="kwtsms-remove-country" data-iso2="<?php echo esc_attr( $iso2 ); ?>"
									style="background:none;border:none;cursor:pointer;margin-left:6px;color:#dc3232;font-weight:bold;font-size:14px;line-height:1;" aria-label="<?php esc_attr_e( 'Remove', 'wp-kwtsms-otp' ); ?>">
									×
								</button>
							</span>
							<?php endif; ?>
							<?php endforeach; ?>
						</div>

						<div style="display:flex;gap:8px;align-items:center;">
							<select id="kwtsms-add-country-select">
								<option value=""><?php esc_html_e( '— Add a country —', 'wp-kwtsms-otp' ); ?></option>
								<?php foreach ( $all_countries as $cc ) : ?>
								<option value="<?php echo esc_attr( $cc['iso2'] ); ?>"
									data-dial="<?php echo esc_attr( $cc['dial'] ); ?>"
									data-name="<?php echo esc_attr( $cc['name'] ); ?>">
									<?php echo esc_html( $cc['name'] . ' (+' . $cc['dial'] . ')' ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="kwtsms-add-country-btn" class="button">
								<?php esc_html_e( 'Add', 'wp-kwtsms-otp' ); ?>
							</button>
						</div>

						<!-- Hidden field stores JSON array of ISO2 codes -->
						<input type="hidden" name="kwtsms_otp_general[allowed_countries]"
							id="kwtsms-allowed-countries-input"
							value="<?php echo esc_attr( wp_json_encode( $allowed_iso2 ) ); ?>" />
					</div>
					<p class="description">
						<?php esc_html_e( 'Only phone numbers from these countries will be accepted for OTP. GCC countries are the default.', 'wp-kwtsms-otp' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== CAPTCHA ===== -->
		<h2 class="title"><?php esc_html_e( 'CAPTCHA Protection', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label><?php esc_html_e( 'CAPTCHA Provider', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<?php
					$providers = array(
						'none'      => __( 'None', 'wp-kwtsms-otp' ),
						'recaptcha' => __( 'Google reCAPTCHA v3 (invisible)', 'wp-kwtsms-otp' ),
						'turnstile' => __( 'Cloudflare Turnstile', 'wp-kwtsms-otp' ),
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
				<th scope="row"><?php esc_html_e( 'reCAPTCHA v3 Keys', 'wp-kwtsms-otp' ); ?></th>
				<td>
					<input type="text" name="kwtsms_otp_general[recaptcha_site_key]"
						value="<?php echo esc_attr( $general['recaptcha_site_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Site Key', 'wp-kwtsms-otp' ); ?>" /><br /><br />
					<input type="password" name="kwtsms_otp_general[recaptcha_secret_key]"
						value="<?php echo esc_attr( $general['recaptcha_secret_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Secret Key', 'wp-kwtsms-otp' ); ?>" />
					<p class="description">
						<a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get keys from Google reCAPTCHA Admin Console →', 'wp-kwtsms-otp' ); ?>
						</a>
					</p>
				</td>
			</tr>

			<tr class="kwtsms-captcha-fields kwtsms-turnstile-fields" style="<?php echo 'turnstile' !== $captcha_provider ? 'display:none;' : ''; ?>">
				<th scope="row"><?php esc_html_e( 'Turnstile Keys', 'wp-kwtsms-otp' ); ?></th>
				<td>
					<input type="text" name="kwtsms_otp_general[turnstile_site_key]"
						value="<?php echo esc_attr( $general['turnstile_site_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Site Key', 'wp-kwtsms-otp' ); ?>" /><br /><br />
					<input type="password" name="kwtsms_otp_general[turnstile_secret_key]"
						value="<?php echo esc_attr( $general['turnstile_secret_key'] ?? '' ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Secret Key', 'wp-kwtsms-otp' ); ?>" />
					<p class="description">
						<a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get keys from Cloudflare Dashboard →', 'wp-kwtsms-otp' ); ?>
						</a>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== Security ===== -->
		<h2 class="title"><?php esc_html_e( 'Security', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="kwtsms_blocked_phones"><?php esc_html_e( 'Blocked Phone Numbers', 'wp-kwtsms-otp' ); ?></label>
				</th>
				<td>
					<textarea name="kwtsms_otp_general[blocked_phones]" id="kwtsms_blocked_phones"
						rows="6" class="large-text code"
						placeholder="96599000000&#10;96566000000"><?php echo esc_textarea( $blocked_phones ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One per line or comma-separated, with country code (digits only). These numbers will never receive OTP SMS. Blocked requests return a silent success to prevent enumeration.', 'wp-kwtsms-otp' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<!-- ===== Referral Link ===== -->
		<h2 class="title"><?php esc_html_e( 'Powered-by Footer', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_referral_link"><?php esc_html_e( 'Show Referral Link', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[referral_link]" id="kwtsms_referral_link"
							value="1" <?php checked( $referral_link ); ?> />
						<?php esc_html_e( 'Display "SMS by kwtSMS.com" footer on login pages', 'wp-kwtsms-otp' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'The link text is fixed and cannot be customized.', 'wp-kwtsms-otp' ); ?></p>
				</td>
			</tr>

		</table>

		<!-- ===== Developer Tools ===== -->
		<h2 class="title"><?php esc_html_e( 'Developer Tools', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="kwtsms_debug_logging"><?php esc_html_e( 'Debug Logging', 'wp-kwtsms-otp' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="kwtsms_otp_general[debug_logging]" id="kwtsms_debug_logging"
							value="1" <?php checked( $debug_logging ); ?> />
						<?php esc_html_e( 'Enable detailed logging for troubleshooting.', 'wp-kwtsms-otp' ); ?>
					</label>
					<?php if ( $debug_logging ) : ?>
					<p class="description" style="color:#d63638;font-weight:600;">
						<?php esc_html_e( '⚠ Debug Logging is ON — disable this on production sites. Log includes request/response data (passwords are not logged).', 'wp-kwtsms-otp' ); ?>
					</p>
					<?php endif; ?>
					<p class="description">
						<?php
						$log_path = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/kwtsms-debug.log' : 'wp-content/kwtsms-debug.log';
						printf(
							/* translators: %s: path to the debug log file */
							esc_html__( 'Log file: %s', 'wp-kwtsms-otp' ),
							'<code>' . esc_html( $log_path ) . '</code>'
						);
						?>
						&mdash;
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-help' ) ); ?>">
							<?php esc_html_e( 'See Help page for troubleshooting guide →', 'wp-kwtsms-otp' ); ?>
						</a>
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Settings', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn' ); ?>
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
	}

	const removeLabel = '<?php echo esc_js( __( 'Remove', 'wp-kwtsms-otp' ) ); ?>';

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
