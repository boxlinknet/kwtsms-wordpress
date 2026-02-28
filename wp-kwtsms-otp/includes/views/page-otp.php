<?php
/**
 * OTP Entry Page — rendered via login_init hook on wp-login.php?action=kwtsms_otp
 *
 * Available variables (set by the calling render method):
 *
 * @var string  $error_message  Error message to display (empty if none).
 * @var string  $token          Partial auth session token.
 * @var int     $otp_length     Expected OTP code length (4 or 6).
 * @var int     $cooldown       Resend cooldown in seconds.
 * @var string  $redirect_to    Post-login redirect URL.
 * @var string  $nonce_resend   Nonce for AJAX resend request.
 * @var bool    $is_reset       True when rendering for password reset context.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

$is_reset   = $is_reset ?? false;
$site_name  = get_bloginfo( 'name' );
$logo_url   = 'https://www.kwtsms.com/images/kwtsms_logo_60.png';
$login_url  = wp_login_url();
$page_title = $is_reset
	? __( 'Verify Your Identity — Password Reset', 'wp-kwtsms-otp' )
	: __( 'Enter Your Verification Code', 'wp-kwtsms-otp' );

$masked_phone = '';
if ( ! empty( $token ) ) {
	$partial = get_transient( 'kwtsms_partial_auth_' . $token );
	if ( $partial && ! empty( $partial['phone'] ) ) {
		$p            = $partial['phone'];
		$len          = strlen( $p );
		$masked_phone = substr( $p, 0, max( 0, $len - 4 ) ) . '****';
	}
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $page_title . ' — ' . $site_name ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( admin_url( '../wp-login.php' ) ); ?>" />
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
		<h2 class="kwtsms-otp-title">
			<?php echo esc_html( $is_reset ? __( 'Password Reset', 'wp-kwtsms-otp' ) : __( 'Two-Step Verification', 'wp-kwtsms-otp' ) ); ?>
		</h2>

		<?php if ( ! empty( $masked_phone ) ) : ?>
		<p class="kwtsms-otp-desc">
			<?php
			printf(
				/* translators: %s: partially masked phone number */
				esc_html__( 'We sent a %d-digit code to %s', 'wp-kwtsms-otp' ),
				(int) $otp_length,
				'<strong>' . esc_html( $masked_phone ) . '</strong>'
			);
			?>
		</p>
		<?php else : ?>
		<p class="kwtsms-otp-desc">
			<?php
			printf(
				/* translators: %d: number of digits */
				esc_html__( 'Enter the %d-digit code sent to your phone.', 'wp-kwtsms-otp' ),
				(int) $otp_length
			);
			?>
		</p>
		<?php endif; ?>

		<?php if ( ! empty( $error_message ) ) : ?>
		<div class="kwtsms-otp-error" role="alert">
			<?php echo esc_html( $error_message ); ?>
		</div>
		<?php endif; ?>

		<?php
		$form_action = ! empty( $is_reset )
			? add_query_arg( 'action', 'kwtsms_reset_otp', $login_url )
			: add_query_arg( 'action', 'kwtsms_otp', $login_url );
		$nonce_action = ! empty( $is_reset ) ? 'kwtsms_reset_otp_submit' : 'kwtsms_otp_submit';
		$nonce_field  = ! empty( $is_reset ) ? 'kwtsms_reset_nonce' : 'kwtsms_otp_nonce';
		?>
		<form method="post" action="<?php echo esc_url( $form_action ); ?>" id="kwtsms-otp-form">
			<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>

			<?php if ( $is_reset ) : ?>
				<input type="hidden" name="kwtsms_context" value="reset" />
			<?php endif; ?>

			<?php if ( ! empty( $redirect_to ) ) : ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
			<?php endif; ?>

			<div class="kwtsms-code-group">
				<label for="kwtsms_code" class="screen-reader-text">
					<?php esc_html_e( 'Verification code', 'wp-kwtsms-otp' ); ?>
				</label>
				<input
					type="text"
					inputmode="numeric"
					pattern="[0-9]*"
					autocomplete="one-time-code"
					name="kwtsms_code"
					id="kwtsms_code"
					class="input kwtsms-code-input"
					placeholder="<?php echo esc_attr( str_repeat( '•', $otp_length ) ); ?>"
					maxlength="<?php echo (int) $otp_length; ?>"
					autofocus
					required
				/>
			</div>

			<input type="submit" name="kwtsms_verify" class="button button-primary button-large kwtsms-btn" value="<?php esc_attr_e( 'Verify Code', 'wp-kwtsms-otp' ); ?>" />
		</form>

		<div class="kwtsms-resend-wrap">
			<button
				type="button"
				id="kwtsms-resend-btn"
				class="kwtsms-resend-btn"
				data-nonce="<?php echo esc_attr( $nonce_resend ); ?>"
				data-cooldown="<?php echo (int) $cooldown; ?>"
				data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				data-token="<?php echo esc_attr( $token ?? '' ); ?>"
				disabled
			>
				<?php
				printf(
					/* translators: %d: seconds until resend is allowed */
					esc_html__( 'Resend code (%d)', 'wp-kwtsms-otp' ),
					(int) $cooldown
				);
				?>
			</button>
			<span id="kwtsms-resend-msg" class="kwtsms-resend-msg" aria-live="polite"></span>
		</div>

		<p class="kwtsms-back-link">
			<a href="<?php echo esc_url( $login_url ); ?>">
				← <?php esc_html_e( 'Back to login', 'wp-kwtsms-otp' ); ?>
			</a>
		</p>
	</div>
</div>

<script src="<?php echo esc_url( KWTSMS_OTP_URL . 'assets/js/login.js?v=' . KWTSMS_OTP_VERSION ); ?>"></script>
</body>
</html>
