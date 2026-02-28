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
$settings        = $this->plugin->settings;
$general         = $settings->get( 'general' ) + KwtSMS_Settings::DEFAULTS['general'];
$captcha_provider = $general['captcha_provider'] ?? 'none';
?>
<div class="wrap kwtsms-admin-wrap">
	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtsms" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtsms OTP — General Settings', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_general_group' ); ?>

		<!-- ===== OTP Behaviour ===== -->
		<h2 class="title"><?php esc_html_e( 'OTP Behaviour', 'wp-kwtsms-otp' ); ?></h2>
		<table class="form-table" role="presentation">

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
					<p class="description"><?php esc_html_e( 'Recommended: 3 minutes. Shorter = more secure, longer = more user-friendly.', 'wp-kwtsms-otp' ); ?></p>
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
						value="<?php echo (int) $general['resend_cooldown']; ?>" min="30" max="300" class="small-text" />
					<?php esc_html_e( 'seconds', 'wp-kwtsms-otp' ); ?>
					<p class="description"><?php esc_html_e( 'Minimum time between resend requests per user. Recommended: 60 seconds.', 'wp-kwtsms-otp' ); ?></p>
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

		<?php submit_button( __( 'Save Settings', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn' ); ?>
	</form>
</div>
