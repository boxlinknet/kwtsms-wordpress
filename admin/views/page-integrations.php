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

$settings = $this->plugin->settings;

$integrations = array(
	'woo' => array(
		'label'       => __( 'WooCommerce', 'wp-kwtsms' ),
		'description' => __( 'Order status SMS notifications (7 statuses), checkout OTP gate, admin alerts, and per-order custom SMS from the order metabox.', 'wp-kwtsms' ),
		'active'      => class_exists( 'WooCommerce' ),
		'sms_enabled' => (bool) $settings->get( 'integrations.woo_enabled', 1 ),
		'slug'        => 'kwtsms-otp-int-woo',
		'wp_slug'     => 'woocommerce',
	),
	'cf7' => array(
		'label'       => __( 'Contact Form 7', 'wp-kwtsms' ),
		'description' => __( 'Send a confirmation SMS on form submission, or enable OTP gate to verify the phone before the form submits.', 'wp-kwtsms' ),
		'active'      => class_exists( 'WPCF7' ),
		'sms_enabled' => (bool) $settings->get( 'integrations.cf7_enabled', 1 ),
		'slug'        => 'kwtsms-otp-int-cf7',
		'wp_slug'     => 'contact-form-7',
	),
	'wpforms' => array(
		'label'       => __( 'WPForms', 'wp-kwtsms' ),
		'description' => __( 'Send a confirmation SMS on form submission, or enable OTP gate to verify the phone before the form submits.', 'wp-kwtsms' ),
		'active'      => function_exists( 'wpforms' ) || class_exists( 'WPForms\WPForms' ),
		'sms_enabled' => (bool) $settings->get( 'integrations.wpforms_enabled', 1 ),
		'slug'        => 'kwtsms-otp-int-wpforms',
		'wp_slug'     => 'wpforms-lite',
	),
	'elementor' => array(
		'label'       => __( 'Elementor', 'wp-kwtsms' ),
		'description' => __( 'Send a confirmation SMS after an Elementor Pro form submission, or gate the form behind phone OTP verification.', 'wp-kwtsms' ),
		'active'      => did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ),
		'sms_enabled' => (bool) $settings->get( 'integrations.elementor_enabled', 1 ),
		'slug'        => 'kwtsms-otp-int-elementor',
		'wp_slug'     => 'elementor',
	),
'nf' => array(
		'label'       => __( 'Ninja Forms', 'wp-kwtsms' ),
		'description' => __( 'Send a confirmation SMS on submission, or gate the form behind phone OTP verification.', 'wp-kwtsms' ),
		'active'      => class_exists( 'Ninja_Forms' ),
		'sms_enabled' => (bool) $settings->get( 'integrations.nf_enabled', 1 ),
		'slug'        => 'kwtsms-otp-int-nf',
		'wp_slug'     => 'ninja-forms',
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
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'Integrations', 'wp-kwtsms' ); ?></h1>
	</div>

	<p style="max-width:800px;font-size:14px;color:#555;">
		<?php esc_html_e( 'Configure SMS for each supported plugin. Settings pages appear for installed and active plugins only.', 'wp-kwtsms' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped" style="max-width:960px;margin-top:16px;border-collapse:collapse;">
		<thead>
			<tr>
				<th style="width:40px;padding:12px 8px;text-align:center;"></th>
				<th style="width:160px;padding:12px 16px;"><?php esc_html_e( 'Integration', 'wp-kwtsms' ); ?></th>
				<th style="padding:12px 16px;"><?php esc_html_e( 'What it does', 'wp-kwtsms' ); ?></th>
				<th style="width:130px;padding:12px 16px;"><?php esc_html_e( 'Plugin', 'wp-kwtsms' ); ?></th>
				<th style="width:110px;padding:12px 16px;"><?php esc_html_e( 'SMS', 'wp-kwtsms' ); ?></th>
				<th style="width:120px;padding:12px 16px;"></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $integrations as $key => $int ) : ?>
			<?php
			$install_url = ! empty( $int['wp_slug'] )
				? admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $int['wp_slug'] ) )
				: null;
			?>
			<tr>
				<td style="text-align:center;font-size:22px;padding:14px 8px;vertical-align:middle;">
					<?php echo $icons[ $key ]; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</td>
				<td style="padding:14px 16px;vertical-align:middle;">
					<strong style="font-size:14px;"><?php echo esc_html( $int['label'] ); ?></strong>
				</td>
				<td style="color:#555;font-size:13px;padding:14px 16px;vertical-align:middle;line-height:1.5;">
					<?php echo esc_html( $int['description'] ); ?>
				</td>
				<td style="padding:14px 16px;vertical-align:middle;">
					<?php if ( $int['active'] ) : ?>
						<span style="color:#00a32a;font-weight:600;">&#10003; <?php esc_html_e( 'Installed', 'wp-kwtsms' ); ?></span>
					<?php elseif ( $install_url ) : ?>
						<a href="<?php echo esc_url( $install_url ); ?>" style="color:#999;text-decoration:none;" title="<?php esc_attr_e( 'View on WordPress.org', 'wp-kwtsms' ); ?>">
							&#10007; <?php esc_html_e( 'Not installed', 'wp-kwtsms' ); ?>
						</a>
					<?php else : ?>
						<span style="color:#999;">&#10007; <?php esc_html_e( 'Not installed', 'wp-kwtsms' ); ?></span>
					<?php endif; ?>
				</td>
				<td style="padding:14px 16px;vertical-align:middle;">
					<?php if ( ! $int['active'] ) : ?>
						<span style="color:#bbb;font-size:12px;"><?php esc_html_e( 'N/A', 'wp-kwtsms' ); ?></span>
					<?php elseif ( $int['sms_enabled'] ) : ?>
						<span style="color:#00a32a;font-weight:600;">&#10003; <?php esc_html_e( 'On', 'wp-kwtsms' ); ?></span>
					<?php else : ?>
						<span style="color:#d63638;font-weight:600;">&#10007; <?php esc_html_e( 'Off', 'wp-kwtsms' ); ?></span>
					<?php endif; ?>
				</td>
				<td style="padding:14px 16px;vertical-align:middle;">
					<?php if ( $int['active'] ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $int['slug'] ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Configure', 'wp-kwtsms' ); ?> &rarr;
						</a>
					<?php elseif ( $install_url ) : ?>
						<a href="<?php echo esc_url( $install_url ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Install', 'wp-kwtsms' ); ?> &rarr;
						</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

</div><!-- /.kwtsms-admin-wrap -->
