<?php
/**
 * Passwordless Login Page — phone number entry form.
 *
 * Available variables:
 * @var string $error_message   Error to display.
 * @var string $success_message Info / success message to display.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

$error_message   = $error_message ?? '';
$success_message = $success_message ?? '';
$site_name       = get_bloginfo( 'name' );
$login_url       = wp_login_url();

/* $plugin_settings is passed as a local variable from KwtSMS_Login_OTP::render_passwordless_page() */
$captcha = new KwtSMS_Captcha( $plugin_settings ?? new KwtSMS_Settings() );

// Build site logo or site name for the header.
$custom_logo_id = get_theme_mod( 'custom_logo' );
if ( $custom_logo_id ) {
	$logo_html = wp_get_attachment_image(
		$custom_logo_id,
		'medium',
		false,
		array( 'style' => 'max-height:80px;width:auto;margin-bottom:12px;' )
	);
} else {
	$logo_html = '<span style="font-size:1.4em;font-weight:700;">' . esc_html( $site_name ) . '</span>';
}

// Referral link settings.
$referral_link_enabled = isset( $plugin_settings ) ? (bool) $plugin_settings->get( 'general.referral_link', 1 ) : true;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( __( 'Login with SMS — ', 'wp-kwtsms-otp' ) . $site_name ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( KWTSMS_OTP_URL . 'assets/css/login.css?v=' . KWTSMS_OTP_VERSION ); ?>" />
	<?php if ( is_rtl() ) : ?>
	<link rel="stylesheet" href="<?php echo esc_url( KWTSMS_OTP_URL . 'assets/css/login-rtl.css?v=' . KWTSMS_OTP_VERSION ); ?>" />
	<?php endif; ?>
</head>
<body class="login wp-core-ui">
<div id="login">

	<h1>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( $site_name ); ?>" tabindex="-1">
			<?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	</h1>

	<div class="kwtsms-otp-box">
		<h2 class="kwtsms-otp-title"><?php esc_html_e( 'Login with SMS', 'wp-kwtsms-otp' ); ?></h2>
		<p class="kwtsms-otp-desc"><?php esc_html_e( 'Enter your registered phone number to receive a one-time login code.', 'wp-kwtsms-otp' ); ?></p>

		<?php if ( ! empty( $error_message ) ) : ?>
		<div class="kwtsms-otp-error" role="alert"><?php echo esc_html( $error_message ); ?></div>
		<?php endif; ?>

		<?php if ( ! empty( $success_message ) ) : ?>
		<div class="kwtsms-otp-success" role="status"><?php echo esc_html( $success_message ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( add_query_arg( 'action', 'kwtsms_passwordless', $login_url ) ); ?>" id="kwtsms-passwordless-form">
			<?php wp_nonce_field( 'kwtsms_passwordless_submit', 'kwtsms_passwordless_nonce' ); ?>

			<label class="screen-reader-text">
				<?php esc_html_e( 'Phone number', 'wp-kwtsms-otp' ); ?>
			</label>

			<?php
			// $allowed_countries and $detected_iso2 are passed from render_passwordless_page().
			// Provide safe fallbacks if viewed directly.
			$allowed_countries = $allowed_countries ?? array();
			$detected_iso2     = $detected_iso2 ?? 'KW';
			?>

			<div class="kwtsms-phone-group" style="display:flex;gap:0;margin-bottom:10px;">
				<select
					name="kwtsms_dial_code"
					id="kwtsms_dial_code"
					class="kwtsms-dial-select input"
					style="width:auto;flex:0 0 auto;border-right:0;border-radius:0;"
				>
					<?php foreach ( $allowed_countries as $c ) : ?>
					<option value="<?php echo esc_attr( $c['dial'] ); ?>"
						<?php selected( $c['iso2'], $detected_iso2 ); ?>>
						<?php echo esc_html( $c['name'] . ' (+' . $c['dial'] . ')' ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<input
					type="tel"
					name="kwtsms_local_phone"
					id="kwtsms_local_phone"
					class="input"
					placeholder="<?php esc_attr_e( 'Local number', 'wp-kwtsms-otp' ); ?>"
					autocomplete="tel-national"
					style="flex:1;border-radius:0;"
					required
				/>
				<!-- Hidden combined field: dial + local, submitted as kwtsms_phone -->
				<input type="hidden" name="kwtsms_phone" id="kwtsms_phone_combined" />
			</div>

			<?php echo $captcha->render_widget(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<input type="submit" class="button button-primary button-large kwtsms-btn" value="<?php esc_attr_e( 'Send OTP Code', 'wp-kwtsms-otp' ); ?>" />
		</form>

		<p class="kwtsms-back-link">
			<a href="<?php echo esc_url( $login_url ); ?>">← <?php esc_html_e( 'Back to login', 'wp-kwtsms-otp' ); ?></a>
		</p>
	</div>
</div>

<?php if ( $referral_link_enabled ) :
	$ref_url = add_query_arg( 'ref', wp_parse_url( home_url(), PHP_URL_HOST ), 'https://www.kwtsms.com/' );
?>
<p class="kwtsms-powered-by" style="text-align:center;font-size:11px;color:#888;margin-top:16px;">
	<a href="<?php echo esc_url( $ref_url ); ?>" target="_blank" rel="noopener">
		<?php esc_html_e( 'SMS service by kwtSMS.com', 'wp-kwtsms-otp' ); ?>
	</a>
</p>
<?php endif; ?>
</body>
</html>
