<?php
/**
 * Admin View: Integrations Overview Page.
 *
 * Shows all supported integrations with install status.
 * Installed integrations link to their own dedicated settings sub-page.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */

$integrations = array(
	'woo' => array(
		'label'       => __( 'WooCommerce', 'wp-kwtsms-otp' ),
		'description' => __( 'Order status SMS notifications (7 statuses), checkout OTP gate, admin alerts, and per-order custom SMS from the order metabox.', 'wp-kwtsms-otp' ),
		'active'      => class_exists( 'WooCommerce' ),
		'slug'        => 'kwtsms-otp-int-woo',
	),
	'cf7' => array(
		'label'       => __( 'Contact Form 7', 'wp-kwtsms-otp' ),
		'description' => __( 'Send a confirmation SMS on form submission, or enable OTP gate to verify the phone before the form submits.', 'wp-kwtsms-otp' ),
		'active'      => class_exists( 'WPCF7' ),
		'slug'        => 'kwtsms-otp-int-cf7',
	),
	'wpforms' => array(
		'label'       => __( 'WPForms', 'wp-kwtsms-otp' ),
		'description' => __( 'Send a confirmation SMS on form submission, or enable OTP gate to verify the phone before the form submits.', 'wp-kwtsms-otp' ),
		'active'      => function_exists( 'wpforms' ) || class_exists( 'WPForms\WPForms' ),
		'slug'        => 'kwtsms-otp-int-wpforms',
	),
	'elementor' => array(
		'label'       => __( 'Elementor', 'wp-kwtsms-otp' ),
		'description' => __( 'Send a confirmation SMS after an Elementor Pro form submission, or gate the form behind phone OTP verification.', 'wp-kwtsms-otp' ),
		'active'      => did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ),
		'slug'        => 'kwtsms-otp-int-elementor',
	),
	'gf' => array(
		'label'       => __( 'Gravity Forms', 'wp-kwtsms-otp' ),
		'description' => __( 'Send a confirmation SMS on submission, or gate the form behind phone OTP verification.', 'wp-kwtsms-otp' ),
		'active'      => class_exists( 'GFForms' ),
		'slug'        => 'kwtsms-otp-int-gf',
	),
	'nf' => array(
		'label'       => __( 'Ninja Forms', 'wp-kwtsms-otp' ),
		'description' => __( 'Send a confirmation SMS on submission, or gate the form behind phone OTP verification.', 'wp-kwtsms-otp' ),
		'active'      => class_exists( 'Ninja_Forms' ),
		'slug'        => 'kwtsms-otp-int-nf',
	),
);

$icons = array(
	'woo'       => '&#x1F6D2;',
	'cf7'       => '&#x1F4CB;',
	'wpforms'   => '&#x1F4DD;',
	'elementor' => '&#x1F3A8;',
	'gf'        => '&#x1F4CA;',
	'nf'        => '&#x1F977;',
);
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtSMS — Integrations', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<p style="max-width:800px;font-size:14px;color:#555;">
		<?php esc_html_e( 'Configure SMS for each supported plugin. Settings pages appear for installed and active plugins only.', 'wp-kwtsms-otp' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped" style="max-width:900px;margin-top:16px;">
		<thead>
			<tr>
				<th style="width:30px;"></th>
				<th><?php esc_html_e( 'Integration', 'wp-kwtsms-otp' ); ?></th>
				<th><?php esc_html_e( 'What it does', 'wp-kwtsms-otp' ); ?></th>
				<th style="width:120px;"><?php esc_html_e( 'Status', 'wp-kwtsms-otp' ); ?></th>
				<th style="width:120px;"></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $integrations as $key => $int ) : ?>
			<tr>
				<td style="text-align:center;font-size:20px;">
					<?php echo $icons[ $key ] ?? '&#x1F50C;'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</td>
				<td><strong><?php echo esc_html( $int['label'] ); ?></strong></td>
				<td style="color:#555;font-size:13px;"><?php echo esc_html( $int['description'] ); ?></td>
				<td>
					<?php if ( $int['active'] ) : ?>
						<span style="color:#00a32a;font-weight:600;">&#10003; <?php esc_html_e( 'Active', 'wp-kwtsms-otp' ); ?></span>
					<?php else : ?>
						<span style="color:#999;">&#8212; <?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $int['active'] ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $int['slug'] ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Configure', 'wp-kwtsms-otp' ); ?> &rarr;
						</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

</div><!-- /.kwtsms-admin-wrap -->
