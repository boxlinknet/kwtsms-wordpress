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
$logo_url        = 'https://www.kwtsms.com/images/kwtsms_logo_60.png';
$login_url       = wp_login_url();

/* $plugin_settings is passed as a local variable from KwtSMS_Login_OTP::render_passwordless_page() */
$captcha = new KwtSMS_Captcha( $plugin_settings ?? new KwtSMS_Settings() );
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
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="kwtsms" style="height:60px;margin-bottom:12px;" />
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

		<form method="post" action="<?php echo esc_url( add_query_arg( 'action', 'kwtsms_passwordless', $login_url ) ); ?>">
			<?php wp_nonce_field( 'kwtsms_passwordless_submit', 'kwtsms_passwordless_nonce' ); ?>

			<label for="kwtsms_phone" class="screen-reader-text">
				<?php esc_html_e( 'Phone number', 'wp-kwtsms-otp' ); ?>
			</label>
			<input
				type="tel"
				name="kwtsms_phone"
				id="kwtsms_phone"
				class="input"
				placeholder="<?php esc_attr_e( 'Phone number (e.g. 96599220322)', 'wp-kwtsms-otp' ); ?>"
				autocomplete="tel"
				required
			/>

			<?php echo $captcha->render_widget(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<input type="submit" class="button button-primary button-large kwtsms-btn" value="<?php esc_attr_e( 'Send OTP Code', 'wp-kwtsms-otp' ); ?>" />
		</form>

		<p class="kwtsms-back-link">
			<a href="<?php echo esc_url( $login_url ); ?>">← <?php esc_html_e( 'Back to login', 'wp-kwtsms-otp' ); ?></a>
		</p>
	</div>
</div>
</body>
</html>
