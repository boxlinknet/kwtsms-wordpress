<?php
/**
 * Admin View: WooCommerce Integration Settings Sub-Page.
 *
 * URL-driven tabs matching the Logs page nav style:
 *   Settings tab  — integration enable, checkout OTP, admin notifications.
 *   One tab per order-status template (7 templates).
 *
 * The form wraps all tab sections; hidden tabs (display:none) still submit,
 * so Save always persists all WooCommerce settings at once.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */

$settings  = $this->plugin->settings;
$int       = array_merge(
	KwtSMS_Settings::DEFAULTS['integrations'],
	(array) $settings->get( 'integrations' )
);
$templates = $settings->get_all_integration_templates();
$woo_active = class_exists( 'WooCommerce' );

// WooCommerce order-status template definitions.
$woo_template_defs = array(
	'woo_processing' => array(
		'tab_label'    => __( 'Processing', 'wp-kwtsms-otp' ),
		'label'        => __( 'New Order / Order Confirmed', 'wp-kwtsms-otp' ),
		'description'  => __( "Sent immediately when a paid order is placed (credit card, PayPal, COD). This is the main 'new order' notification.", 'wp-kwtsms-otp' ),
		'placeholders' => '{order_id}, {total}, {site_name}, {customer_name}',
	),
	'woo_shipped'    => array(
		'tab_label'    => __( 'Shipped', 'wp-kwtsms-otp' ),
		'label'        => __( 'Order Shipped', 'wp-kwtsms-otp' ),
		'description'  => __( 'Sent when the order status is set to On-Hold, typically used to indicate the order has been shipped.', 'wp-kwtsms-otp' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_completed'  => array(
		'tab_label'    => __( 'Completed', 'wp-kwtsms-otp' ),
		'label'        => __( 'Order Completed', 'wp-kwtsms-otp' ),
		'description'  => __( 'Sent when the order is marked as fully delivered and complete.', 'wp-kwtsms-otp' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_cancelled'  => array(
		'tab_label'    => __( 'Cancelled', 'wp-kwtsms-otp' ),
		'label'        => __( 'Order Cancelled', 'wp-kwtsms-otp' ),
		'description'  => __( 'Sent when the order is cancelled by the customer or admin.', 'wp-kwtsms-otp' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_pending'    => array(
		'tab_label'    => __( 'Pending', 'wp-kwtsms-otp' ),
		'label'        => __( 'New Order — Awaiting Payment', 'wp-kwtsms-otp' ),
		'description'  => __( 'Sent when an order is placed but payment not yet received (e.g. bank transfer). Disabled by default.', 'wp-kwtsms-otp' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_refunded'   => array(
		'tab_label'    => __( 'Refunded', 'wp-kwtsms-otp' ),
		'label'        => __( 'Order Refunded', 'wp-kwtsms-otp' ),
		'description'  => __( 'Sent when a refund is issued for the order. Disabled by default.', 'wp-kwtsms-otp' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_failed'     => array(
		'tab_label'    => __( 'Failed', 'wp-kwtsms-otp' ),
		'label'        => __( 'Payment Failed — Order Not Confirmed', 'wp-kwtsms-otp' ),
		'description'  => __( 'Sent when the payment attempt fails and the order is not confirmed. Disabled by default.', 'wp-kwtsms-otp' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
);

$valid_tabs = array_merge( array( 'settings' ), array_keys( $woo_template_defs ) );
$active_tab = isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $valid_tabs, true )
	? sanitize_key( $_GET['tab'] )
	: 'settings';

/**
 * Build a tab URL for the WooCommerce integration page.
 *
 * @param string $tab Tab key.
 * @return string Admin URL with page + tab query args.
 */
function kwtsms_woo_tab_url( $tab ) {
	return add_query_arg(
		array( 'page' => 'kwtsms-otp-int-woo', 'tab' => $tab ),
		admin_url( 'admin.php' )
	);
}

// Status enable/disable checklist used in the Settings tab.
$customer_status_labels = array(
	'woo_processing' => array(
		'label' => __( 'New Order / Order Confirmed', 'wp-kwtsms-otp' ),
		'hint'  => __( "Fires immediately when a paid order is placed (credit card, PayPal, COD). This is the main 'new order' notification.", 'wp-kwtsms-otp' ),
	),
	'woo_shipped'    => array(
		'label' => __( 'Order Shipped', 'wp-kwtsms-otp' ),
		'hint'  => __( 'Fires when the order status is set to On-Hold, typically used to indicate the order has been shipped.', 'wp-kwtsms-otp' ),
	),
	'woo_completed'  => array(
		'label' => __( 'Order Completed', 'wp-kwtsms-otp' ),
		'hint'  => __( 'Fires when the order is marked as fully delivered and complete.', 'wp-kwtsms-otp' ),
	),
	'woo_cancelled'  => array(
		'label' => __( 'Order Cancelled', 'wp-kwtsms-otp' ),
		'hint'  => __( 'Fires when the order is cancelled by the customer or admin.', 'wp-kwtsms-otp' ),
	),
	'woo_pending'    => array(
		'label' => __( 'New Order — Awaiting Payment', 'wp-kwtsms-otp' ),
		'hint'  => __( 'Fires when an order is placed but payment has not been received yet (e.g. bank transfer). Disabled by default.', 'wp-kwtsms-otp' ),
	),
	'woo_refunded'   => array(
		'label' => __( 'Order Refunded', 'wp-kwtsms-otp' ),
		'hint'  => __( 'Fires when a refund is issued for the order. Disabled by default.', 'wp-kwtsms-otp' ),
	),
	'woo_failed'     => array(
		'label' => __( 'Payment Failed — Order Not Confirmed', 'wp-kwtsms-otp' ),
		'hint'  => __( 'Fires when the payment attempt fails and the order is not confirmed. Disabled by default.', 'wp-kwtsms-otp' ),
	),
);
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

	<!-- Tab navigation -->
	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( kwtsms_woo_tab_url( 'settings' ) ); ?>"
			class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'wp-kwtsms-otp' ); ?>
		</a>
		<?php foreach ( $woo_template_defs as $key => $def ) : ?>
		<a href="<?php echo esc_url( kwtsms_woo_tab_url( $key ) ); ?>"
			class="nav-tab <?php echo $key === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $def['tab_label'] ); ?>
		</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_integrations_group' ); ?>
		<input type="hidden" name="kwtsms_otp_integrations[_save_section]" value="woo" />

		<!-- ===== Settings Tab ===== -->
		<div class="kwtsms-tab-section"<?php echo 'settings' === $active_tab ? '' : ' style="display:none;"'; ?>>

			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'WooCommerce Integration', 'wp-kwtsms-otp' ); ?></h3>
				</div>
				<p class="description" style="margin-top:0;">
					<?php esc_html_e( 'Send SMS notifications for order status changes and registration events.', 'wp-kwtsms-otp' ); ?>
				</p>

				<table class="form-table" style="margin-top:12px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Integration', 'wp-kwtsms-otp' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_enabled]"
									value="1"
									<?php checked( $int['woo_enabled'], 1 ); ?> />
								<?php esc_html_e( 'Enable WooCommerce SMS Integration', 'wp-kwtsms-otp' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Checkout OTP', 'wp-kwtsms-otp' ); ?></th>
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

				<hr style="margin:16px 0;border:none;border-top:1px solid #e0e0e0;">

				<h4 style="margin:0 0 4px;"><?php esc_html_e( 'Customer SMS per Order Status', 'wp-kwtsms-otp' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Select which order status changes trigger a customer SMS notification.', 'wp-kwtsms-otp' ); ?>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled statuses', 'wp-kwtsms-otp' ); ?></th>
						<td>
							<?php foreach ( $customer_status_labels as $csl_key => $csl_def ) :
								$csl_tpl = $templates[ $csl_key ] ?? array( 'enabled' => 0 );
							?>
							<div style="margin-bottom:10px;">
								<label style="display:block;">
									<input type="checkbox"
										name="kwtsms_otp_integrations[<?php echo esc_attr( $csl_key ); ?>][enabled]"
										value="1"
										<?php checked( $csl_tpl['enabled'], 1 ); ?> />
									<strong><?php echo esc_html( $csl_def['label'] ); ?></strong>
								</label>
								<p class="description" style="margin:2px 0 0 20px;"><?php echo esc_html( $csl_def['hint'] ); ?></p>
							</div>
							<?php endforeach; ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to a template tab */
									esc_html__( 'Edit message text on each status tab above.', 'wp-kwtsms-otp' )
								);
								?>
							</p>
						</td>
					</tr>
				</table>
			</div><!-- /.woo-integration-card -->

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
								'processing' => __( 'New Order / Order Confirmed (Processing)', 'wp-kwtsms-otp' ),
								'on-hold'    => __( 'Order Shipped (On-Hold)', 'wp-kwtsms-otp' ),
								'completed'  => __( 'Order Completed', 'wp-kwtsms-otp' ),
								'cancelled'  => __( 'Order Cancelled', 'wp-kwtsms-otp' ),
								'pending'    => __( 'New Order — Awaiting Payment (Pending)', 'wp-kwtsms-otp' ),
								'refunded'   => __( 'Order Refunded', 'wp-kwtsms-otp' ),
								'failed'     => __( 'Payment Failed — Order Not Confirmed', 'wp-kwtsms-otp' ),
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

		</div><!-- /.kwtsms-tab-section[settings] -->

		<!-- ===== Order Status Template Tabs ===== -->
		<?php foreach ( $woo_template_defs as $key => $def ) :
			$tpl       = $templates[ $key ] ?? array( 'enabled' => 0, 'en' => '', 'ar' => '' );
			$is_active = ( $key === $active_tab );
		?>
		<div class="kwtsms-tab-section"<?php echo $is_active ? '' : ' style="display:none;"'; ?>>

			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php echo esc_html( $def['label'] ); ?></h3>
				</div>
				<p class="description"><?php echo esc_html( $def['description'] ); ?></p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms-otp' ); ?></strong>
					<code><?php echo esc_html( $def['placeholders'] ); ?></code>
				</p>

				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms-otp' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms-otp' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
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
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
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
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="<?php echo esc_attr( $key ); ?>">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms-otp' ); ?>
					</button>
				</div>
			</div>

		</div><!-- /.kwtsms-tab-section[<?php echo esc_attr( $key ); ?>] -->
		<?php endforeach; ?>

		<?php submit_button( __( 'Save WooCommerce Settings', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn' ); ?>

	</form>

</div><!-- /.kwtsms-admin-wrap -->
