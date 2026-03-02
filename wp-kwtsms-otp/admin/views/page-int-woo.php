<?php
/**
 * Admin View: WooCommerce Integration Settings Sub-Page.
 *
 * Dedicated page for all WooCommerce SMS settings. Submitting this form
 * only overwrites the WooCommerce-related fields in the integrations option
 * (identified by the hidden _save_section = woo field), so other integration
 * settings are never erased.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */

$settings = $this->plugin->settings;

// Load saved integration settings with defaults merged in.
$int = array_merge(
	KwtSMS_Settings::DEFAULTS['integrations'],
	(array) $settings->get( 'integrations' )
);

// For each template key, merge saved values over the defaults.
$templates = $settings->get_all_integration_templates();

$woo_active = class_exists( 'WooCommerce' );
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'WooCommerce Settings', 'wp-kwtsms-otp' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-integrations' ) ); ?>" class="button" style="margin-left:16px;align-self:center;">
			&larr; <?php esc_html_e( 'All Integrations', 'wp-kwtsms-otp' ); ?>
		</a>
	</div>

	<?php if ( ! $woo_active ) : ?>
	<div class="notice notice-warning inline" style="margin:16px 0;">
		<p><?php esc_html_e( 'WooCommerce is not installed or activated. The settings below will be saved and applied once WooCommerce is active.', 'wp-kwtsms-otp' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_integrations_group' ); ?>
		<input type="hidden" name="kwtsms_otp_integrations[_save_section]" value="woo" />

		<!-- Enable toggle card -->
		<div class="kwtsms-template-card">
			<div class="kwtsms-template-card-header">
				<h3><?php esc_html_e( 'WooCommerce Integration', 'wp-kwtsms-otp' ); ?></h3>
				<label class="kwtsms-toggle">
					<input type="checkbox"
						name="kwtsms_otp_integrations[woo_enabled]"
						value="1"
						<?php checked( $int['woo_enabled'], 1 ); ?> />
					<span><?php esc_html_e( 'Enable WooCommerce SMS Integration', 'wp-kwtsms-otp' ); ?></span>
				</label>
			</div>
			<p class="description">
				<?php esc_html_e( 'Send SMS notifications for order status changes and registration events.', 'wp-kwtsms-otp' ); ?>
			</p>

			<!-- Checkout OTP Gate -->
			<table class="form-table" style="margin-top:12px;">
				<tr>
					<th scope="row"><?php esc_html_e( 'Checkout OTP Gate', 'wp-kwtsms-otp' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="kwtsms_otp_integrations[woo_checkout_otp]"
								value="1"
								<?php checked( $int['woo_checkout_otp'], 1 ); ?> />
							<?php esc_html_e( 'Require OTP verification before placing an order', 'wp-kwtsms-otp' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, customers must verify their billing phone with an OTP before checkout completes. Recommended for reducing fraudulent orders.', 'wp-kwtsms-otp' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Admin SMS notification settings -->
		<div class="kwtsms-template-card">
			<div class="kwtsms-template-card-header">
				<h3><?php esc_html_e( 'Admin SMS Notifications', 'wp-kwtsms-otp' ); ?></h3>
			</div>
			<p class="description">
				<?php esc_html_e( 'Send an SMS to a store admin phone number when selected order status changes occur.', 'wp-kwtsms-otp' ); ?>
			</p>
			<table class="form-table" style="margin-top:12px;">
				<tr>
					<th scope="row"><?php esc_html_e( 'Admin Phone Number(s)', 'wp-kwtsms-otp' ); ?></th>
					<td>
						<input type="text"
							name="kwtsms_otp_integrations[woo_admin_phone]"
							value="<?php echo esc_attr( $int['woo_admin_phone'] ?? '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. 96598765432, 96599220333', 'wp-kwtsms-otp' ); ?>" />
						<p class="description"><?php esc_html_e( 'Comma-separated list of phone numbers (with country code) to receive admin notifications.', 'wp-kwtsms-otp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notify admin for statuses', 'wp-kwtsms-otp' ); ?></th>
					<td>
						<?php
						$admin_notify_statuses = $int['woo_notify_admin_statuses'] ?? array();
						$status_options        = array(
							'processing' => __( 'Processing', 'wp-kwtsms-otp' ),
							'on-hold'    => __( 'On-Hold (Shipped)', 'wp-kwtsms-otp' ),
							'completed'  => __( 'Completed', 'wp-kwtsms-otp' ),
							'cancelled'  => __( 'Cancelled', 'wp-kwtsms-otp' ),
							'pending'    => __( 'Pending Payment', 'wp-kwtsms-otp' ),
							'refunded'   => __( 'Refunded', 'wp-kwtsms-otp' ),
							'failed'     => __( 'Payment Failed', 'wp-kwtsms-otp' ),
						);
						foreach ( $status_options as $slug => $label ) :
						?>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox"
								name="kwtsms_otp_integrations[woo_notify_admin_statuses][]"
								value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( in_array( $slug, (array) $admin_notify_statuses, true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Select which status changes trigger an admin SMS notification.', 'wp-kwtsms-otp' ); ?></p>
					</td>
				</tr>
			</table>
		</div><!-- /.admin-notification-card -->

		<!-- Order status template cards -->
		<?php
		$woo_template_defs = array(
			'woo_processing' => array(
				'label'        => __( 'Order Confirmed (Processing)', 'wp-kwtsms-otp' ),
				'description'  => __( 'Sent when an order transitions to Processing status.', 'wp-kwtsms-otp' ),
				'placeholders' => '{order_id}, {total}, {site_name}, {customer_name}',
			),
			'woo_shipped'    => array(
				'label'        => __( 'Order Shipped', 'wp-kwtsms-otp' ),
				'description'  => __( 'Sent when an order transitions to On-Hold / Shipped status.', 'wp-kwtsms-otp' ),
				'placeholders' => '{order_id}, {site_name}, {customer_name}',
			),
			'woo_completed'  => array(
				'label'        => __( 'Order Completed', 'wp-kwtsms-otp' ),
				'description'  => __( 'Sent when an order is marked Completed.', 'wp-kwtsms-otp' ),
				'placeholders' => '{order_id}, {site_name}, {customer_name}',
			),
			'woo_cancelled'  => array(
				'label'        => __( 'Order Cancelled', 'wp-kwtsms-otp' ),
				'description'  => __( 'Sent when an order is Cancelled.', 'wp-kwtsms-otp' ),
				'placeholders' => '{order_id}, {site_name}, {customer_name}',
			),
			'woo_pending'    => array(
				'label'        => __( 'Order Pending Payment', 'wp-kwtsms-otp' ),
				'description'  => __( 'Sent when an order is created with Pending Payment status (disabled by default).', 'wp-kwtsms-otp' ),
				'placeholders' => '{order_id}, {site_name}, {customer_name}',
			),
			'woo_refunded'   => array(
				'label'        => __( 'Order Refunded', 'wp-kwtsms-otp' ),
				'description'  => __( 'Sent when an order is Refunded (disabled by default).', 'wp-kwtsms-otp' ),
				'placeholders' => '{order_id}, {site_name}, {customer_name}',
			),
			'woo_failed'     => array(
				'label'        => __( 'Order Payment Failed', 'wp-kwtsms-otp' ),
				'description'  => __( 'Sent when an order payment Fails (disabled by default).', 'wp-kwtsms-otp' ),
				'placeholders' => '{order_id}, {site_name}, {customer_name}',
			),
		);

		foreach ( $woo_template_defs as $key => $def ) :
			$tpl = $templates[ $key ] ?? array( 'enabled' => 0, 'en' => '', 'ar' => '' );
		?>
		<div class="kwtsms-template-card">
			<div class="kwtsms-template-card-header">
				<h3><?php echo esc_html( $def['label'] ); ?></h3>
				<label class="kwtsms-toggle">
					<input type="checkbox"
						name="kwtsms_otp_integrations[<?php echo esc_attr( $key ); ?>][enabled]"
						value="1"
						<?php checked( $tpl['enabled'], 1 ); ?> />
					<span><?php esc_html_e( 'Enabled', 'wp-kwtsms-otp' ); ?></span>
				</label>
			</div>
			<p class="description"><?php echo esc_html( $def['description'] ); ?></p>
			<p class="description" style="margin-top:4px;">
				<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms-otp' ); ?></strong>
				<code><?php echo esc_html( $def['placeholders'] ); ?></code>
			</p>

			<div class="kwtsms-template-fields">
				<!-- English -->
				<div class="kwtsms-template-field">
					<label for="int_<?php echo esc_attr( $key ); ?>_en">
						<span class="kwtsms-lang-flag">&#x1F1EC;&#x1F1E7;</span>
						<?php esc_html_e( 'English (LTR)', 'wp-kwtsms-otp' ); ?>
					</label>
					<div class="kwtsms-textarea-wrap">
						<textarea
							name="kwtsms_otp_integrations[<?php echo esc_attr( $key ); ?>][en]"
							id="int_<?php echo esc_attr( $key ); ?>_en"
							class="large-text kwtsms-sms-textarea"
							rows="3"
							dir="ltr"
							data-lang="en"
						><?php echo esc_textarea( $tpl['en'] ); ?></textarea>
						<div class="kwtsms-char-counter" data-target="int_<?php echo esc_attr( $key ); ?>_en">
							<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
							&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
						</div>
					</div>
				</div>

				<!-- Arabic -->
				<div class="kwtsms-template-field">
					<label for="int_<?php echo esc_attr( $key ); ?>_ar">
						<span class="kwtsms-lang-flag">&#x1F1F0;&#x1F1FC;</span>
						<?php esc_html_e( 'Arabic (RTL)', 'wp-kwtsms-otp' ); ?>
					</label>
					<div class="kwtsms-textarea-wrap">
						<textarea
							name="kwtsms_otp_integrations[<?php echo esc_attr( $key ); ?>][ar]"
							id="int_<?php echo esc_attr( $key ); ?>_ar"
							class="large-text kwtsms-sms-textarea"
							rows="3"
							dir="rtl"
							data-lang="ar"
						><?php echo esc_textarea( $tpl['ar'] ); ?></textarea>
						<div class="kwtsms-char-counter" data-target="int_<?php echo esc_attr( $key ); ?>_ar">
							<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
							&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save WooCommerce Settings', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn' ); ?>

	</form>

</div><!-- /.kwtsms-admin-wrap -->
