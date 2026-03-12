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

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- @var KwtSMS_Admin $this, injected by admin controller.

$settings   = $this->plugin->settings;
$int        = array_merge(
	KwtSMS_Settings::DEFAULTS['integrations'],
	(array) $settings->get( 'integrations' )
);
$templates  = $settings->get_all_integration_templates();
$woo_active = class_exists( 'WooCommerce' );

// WooCommerce order-status template definitions.
$woo_template_defs = array(
	'woo_processing' => array(
		'tab_label'    => __( 'Processing', 'wp-kwtsms' ),
		'label'        => __( 'New Order / Order Confirmed (Processing)', 'wp-kwtsms' ),
		'description'  => __( "Sent immediately when a paid order is placed (credit card, PayPal, COD). This is the main 'new order' notification.", 'wp-kwtsms' ),
		'placeholders' => '{order_id}, {total}, {site_name}, {customer_name}',
	),
	'woo_shipped'    => array(
		'tab_label'    => __( 'Shipped', 'wp-kwtsms' ),
		'label'        => __( 'Order Shipped (On-Hold)', 'wp-kwtsms' ),
		'description'  => __( 'Sent when the order status is set to On-Hold, typically used to indicate the order has been shipped.', 'wp-kwtsms' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_completed'  => array(
		'tab_label'    => __( 'Completed', 'wp-kwtsms' ),
		'label'        => __( 'Order Completed', 'wp-kwtsms' ),
		'description'  => __( 'Sent when the order is marked as fully delivered and complete.', 'wp-kwtsms' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_cancelled'  => array(
		'tab_label'    => __( 'Cancelled', 'wp-kwtsms' ),
		'label'        => __( 'Order Cancelled', 'wp-kwtsms' ),
		'description'  => __( 'Sent when the order is cancelled by the customer or admin.', 'wp-kwtsms' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_pending'    => array(
		'tab_label'    => __( 'Pending', 'wp-kwtsms' ),
		'label'        => __( 'New Order — Awaiting Payment (Pending)', 'wp-kwtsms' ),
		'description'  => __( 'Sent when an order is placed but payment not yet received (e.g. bank transfer). Disabled by default.', 'wp-kwtsms' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_refunded'   => array(
		'tab_label'    => __( 'Refunded', 'wp-kwtsms' ),
		'label'        => __( 'Order Refunded', 'wp-kwtsms' ),
		'description'  => __( 'Sent when a refund is issued for the order. Disabled by default.', 'wp-kwtsms' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
	'woo_failed'     => array(
		'tab_label'    => __( 'Failed', 'wp-kwtsms' ),
		'label'        => __( 'Payment Failed — Order Not Confirmed', 'wp-kwtsms' ),
		'description'  => __( 'Sent when the payment attempt fails and the order is not confirmed. Disabled by default.', 'wp-kwtsms' ),
		'placeholders' => '{order_id}, {site_name}, {customer_name}',
	),
);

$valid_tabs = array_merge( array( 'stock_alerts', 'multivendor', 'cart_abandonment' ), array_keys( $woo_template_defs ) );
$active_tab = isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $valid_tabs, true ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	? sanitize_key( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	: 'woo_processing';

/**
 * Build a tab URL for the WooCommerce integration page.
 *
 * @param string $tab Tab key.
 * @return string Admin URL with page + tab query args.
 */
function kwtsms_woo_tab_url( $tab ) {
	return add_query_arg(
		array(
			'page' => 'kwtsms-otp-int-woo',
			'tab'  => $tab,
		),
		admin_url( 'admin.php' )
	);
}

// Status enable/disable checklist used in the Settings tab.
$customer_status_labels = array(
	'woo_processing' => array(
		'label' => __( 'New Order / Order Confirmed (Processing)', 'wp-kwtsms' ),
		'hint'  => __( "Fires immediately when a paid order is placed (credit card, PayPal, COD). This is the main 'new order' notification.", 'wp-kwtsms' ),
	),
	'woo_pending'    => array(
		'label' => __( 'New Order — Awaiting Payment (Pending)', 'wp-kwtsms' ),
		'hint'  => __( 'Fires when an order is placed but payment has not been received yet (e.g. bank transfer). Disabled by default.', 'wp-kwtsms' ),
	),
	'woo_failed'     => array(
		'label' => __( 'Payment Failed — Order Not Confirmed', 'wp-kwtsms' ),
		'hint'  => __( 'Fires when the payment attempt fails and the order is not confirmed. Disabled by default.', 'wp-kwtsms' ),
	),
	'woo_shipped'    => array(
		'label' => __( 'Order Shipped (On-Hold)', 'wp-kwtsms' ),
		'hint'  => __( 'Fires when the order status is set to On-Hold, typically used to indicate the order has been shipped.', 'wp-kwtsms' ),
	),
	'woo_completed'  => array(
		'label' => __( 'Order Completed', 'wp-kwtsms' ),
		'hint'  => __( 'Fires when the order is marked as fully delivered and complete.', 'wp-kwtsms' ),
	),
	'woo_cancelled'  => array(
		'label' => __( 'Order Cancelled', 'wp-kwtsms' ),
		'hint'  => __( 'Fires when the order is cancelled by the customer or admin.', 'wp-kwtsms' ),
	),
	'woo_refunded'   => array(
		'label' => __( 'Order Refunded', 'wp-kwtsms' ),
		'hint'  => __( 'Fires when a refund is issued for the order. Disabled by default.', 'wp-kwtsms' ),
	),
);
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'WooCommerce Settings', 'wp-kwtsms' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-integrations' ) ); ?>" class="button" style="margin-left:16px;align-self:center;">
			&larr; <?php esc_html_e( 'All Integrations', 'wp-kwtsms' ); ?>
		</a>
	</div>
	<hr class="wp-header-end">

	<?php if ( ! $woo_active ) : ?>
	<div class="notice notice-warning inline" style="margin:16px 0;">
		<p><?php esc_html_e( 'WooCommerce is not installed or activated. The settings below will be saved and applied once WooCommerce is active.', 'wp-kwtsms' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_integrations_group' ); ?>
		<input type="hidden" name="kwtsms_otp_integrations[_save_section]" value="woo" />

		<!-- ===== Settings (always visible) ===== -->
		<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'WooCommerce Integration', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description" style="margin-top:0;">
					<?php esc_html_e( 'Send SMS notifications for order status changes and registration events.', 'wp-kwtsms' ); ?>
				</p>

				<table class="form-table" style="margin-top:12px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Integration', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_enabled]"
									value="1"
									<?php checked( $int['woo_enabled'], 1 ); ?> />
								<?php esc_html_e( 'Enable WooCommerce SMS Integration', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Checkout OTP', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_checkout_otp]"
									id="kwtsms-checkout-otp-toggle"
									value="1"
									<?php checked( $int['woo_checkout_otp'], 1 ); ?> />
								<?php esc_html_e( 'Require OTP verification before placing an order', 'wp-kwtsms' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, customers must verify their billing phone with an OTP before checkout completes. Recommended for reducing fraudulent orders.', 'wp-kwtsms' ); ?>
							</p>
						</td>
					</tr>
					<tr id="kwtsms-cod-only-row"<?php echo empty( $int['woo_checkout_otp'] ) ? ' style="display:none;"' : ''; ?>>
						<th scope="row"><?php esc_html_e( 'COD Only', 'wp-kwtsms' ); ?></th>
						<td>
							<label class="kwtsms-toggle">
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_checkout_otp_cod_only]"
									value="1"
									<?php checked( ! empty( $int['woo_checkout_otp_cod_only'] ) ); ?> />
								<span><?php esc_html_e( 'Require OTP only for Cash on Delivery orders', 'wp-kwtsms' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'When enabled, card and other payment methods bypass the OTP gate. Only COD customers must verify.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
				</table>
				<script>
				(function() {
					var toggle = document.getElementById('kwtsms-checkout-otp-toggle');
					var row    = document.getElementById('kwtsms-cod-only-row');
					if (!toggle || !row) return;
					toggle.addEventListener('change', function() {
						row.style.display = this.checked ? '' : 'none';
					});
				})();
				</script>

				<hr style="margin:16px 0;border:none;border-top:1px solid #e0e0e0;">

				<h4 style="margin:0 0 4px;"><?php esc_html_e( 'Customer SMS per Order Status', 'wp-kwtsms' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Select which order status changes trigger a customer SMS notification.', 'wp-kwtsms' ); ?>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled statuses', 'wp-kwtsms' ); ?></th>
						<td>
							<?php
							foreach ( $customer_status_labels as $csl_key => $csl_def ) :
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
								<?php esc_html_e( 'Edit message text on each status tab below.', 'wp-kwtsms' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div><!-- /.woo-integration-card -->

			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Admin SMS Notifications', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Send an SMS to a store admin phone number when selected order status changes occur.', 'wp-kwtsms' ); ?>
				</p>
				<table class="form-table" style="margin-top:12px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Phone Number(s)', 'wp-kwtsms' ); ?></th>
						<td>
							<input type="text"
								name="kwtsms_otp_integrations[woo_admin_phone]"
								value="<?php echo esc_attr( $int['woo_admin_phone'] ?? '' ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. 96598765432, 96599220333', 'wp-kwtsms' ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated list of phone numbers (with country code) to receive admin notifications.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Notify admin for statuses', 'wp-kwtsms' ); ?></th>
						<td>
							<?php
							$admin_notify_statuses = $int['woo_notify_admin_statuses'] ?? array();
							$status_options        = array(
								'processing' => __( 'New Order / Order Confirmed (Processing)', 'wp-kwtsms' ),
								'pending'    => __( 'New Order — Awaiting Payment (Pending)', 'wp-kwtsms' ),
								'failed'     => __( 'Payment Failed — Order Not Confirmed', 'wp-kwtsms' ),
								'on-hold'    => __( 'Order Shipped (On-Hold)', 'wp-kwtsms' ),
								'completed'  => __( 'Order Completed', 'wp-kwtsms' ),
								'cancelled'  => __( 'Order Cancelled', 'wp-kwtsms' ),
								'refunded'   => __( 'Order Refunded', 'wp-kwtsms' ),
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
							<p class="description"><?php esc_html_e( 'Select which status changes trigger an admin SMS notification.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
				</table>
			</div><!-- /.admin-notification-card -->

		<!-- ===== Order Status Template Tabs ===== -->
		<nav class="nav-tab-wrapper" style="margin-top:24px;">
			<a href="<?php echo esc_url( kwtsms_woo_tab_url( 'stock_alerts' ) ); ?>"
				class="nav-tab <?php echo 'stock_alerts' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Stock Alerts', 'wp-kwtsms' ); ?>
			</a>
			<a href="<?php echo esc_url( kwtsms_woo_tab_url( 'multivendor' ) ); ?>"
				class="nav-tab <?php echo 'multivendor' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Multivendor', 'wp-kwtsms' ); ?>
			</a>
			<a href="<?php echo esc_url( kwtsms_woo_tab_url( 'cart_abandonment' ) ); ?>"
				class="nav-tab <?php echo 'cart_abandonment' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Cart Abandonment', 'wp-kwtsms' ); ?>
			</a>
			<?php foreach ( $woo_template_defs as $key => $def ) : ?>
			<a href="<?php echo esc_url( kwtsms_woo_tab_url( $key ) ); ?>"
				class="nav-tab <?php echo $key === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $def['tab_label'] ); ?>
			</a>
			<?php endforeach; ?>
		</nav>
		<?php if ( 'stock_alerts' === $active_tab ) : ?>
		<div class="kwtsms-tab-section">

			<!-- Stock Admin Phone -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Stock Alert Admin Phone', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Phone number(s) to receive stock alert SMS messages. Separate multiple numbers with commas.', 'wp-kwtsms' ); ?>
				</p>
				<table class="form-table" style="margin-top:12px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Phone Number(s)', 'wp-kwtsms' ); ?></th>
						<td>
							<input type="text"
								name="kwtsms_otp_integrations[woo_stock_admin_phone]"
								value="<?php echo esc_attr( $int['woo_stock_admin_phone'] ?? '' ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. 96598765432, 96599220333', 'wp-kwtsms' ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated list of phone numbers (with country code) to receive stock notifications.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Low Stock Alert -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Low Stock Alert', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Sent to the stock admin phone when a product reaches its low stock threshold.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {product_name}, {quantity}</code>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_low_stock_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_low_stock_enabled'] ) ); ?> />
								<?php esc_html_e( 'Send SMS on low stock', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php
				$tpl_ls = $templates['woo_tpl_low_stock'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_low_stock][en]"
								id="int_woo_tpl_low_stock_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_ls['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_low_stock_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_low_stock][ar]"
								id="int_woo_tpl_low_stock_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_ls['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_low_stock_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_low_stock">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

			<!-- Out of Stock Alert -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Out of Stock Alert', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Sent to the stock admin phone when a product goes out of stock.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {product_name}</code>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_no_stock_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_no_stock_enabled'] ) ); ?> />
								<?php esc_html_e( 'Send SMS on out of stock', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php
				$tpl_ns = $templates['woo_tpl_no_stock'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_no_stock][en]"
								id="int_woo_tpl_no_stock_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_ns['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_no_stock_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_no_stock][ar]"
								id="int_woo_tpl_no_stock_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_ns['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_no_stock_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_no_stock">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

			<!-- Backorder Alert -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Backorder Alert', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Sent to the stock admin phone when a product is placed on backorder.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {product_name}</code>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_backorder_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_backorder_enabled'] ) ); ?> />
								<?php esc_html_e( 'Send SMS on backorder', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php
				$tpl_bo = $templates['woo_tpl_backorder'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_backorder][en]"
								id="int_woo_tpl_backorder_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_bo['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_backorder_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_backorder][ar]"
								id="int_woo_tpl_backorder_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_bo['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_backorder_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_backorder">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

			<!-- New Product SMS -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'New Product SMS', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Sent to the stock admin phone when a new product is first published. Disabled by default.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {product_name}</code>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_new_product_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_new_product_enabled'] ) ); ?> />
								<?php esc_html_e( 'Send SMS when a new product is published', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php
				$tpl_np = $templates['woo_tpl_new_product'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_new_product][en]"
								id="int_woo_tpl_new_product_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_np['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_new_product_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_new_product][ar]"
								id="int_woo_tpl_new_product_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_np['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_new_product_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_new_product">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

			<!-- Back-in-Stock Notifications -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Back-in-Stock Notifications', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'When enabled, out-of-stock product pages show a subscribe form. Subscribers receive an SMS when the product comes back in stock. Disabled by default.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {product_name}</code>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_back_in_stock_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_back_in_stock_enabled'] ) ); ?> />
								<?php esc_html_e( 'Enable back-in-stock subscriber notifications', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php
				$tpl_bis = $templates['woo_tpl_back_in_stock'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_back_in_stock][en]"
								id="int_woo_tpl_back_in_stock_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_bis['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_back_in_stock_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_back_in_stock][ar]"
								id="int_woo_tpl_back_in_stock_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_bis['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_back_in_stock_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_back_in_stock">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

		</div><!-- /.kwtsms-tab-section[stock_alerts] -->
		<?php endif; ?>
		<?php if ( 'multivendor' === $active_tab ) : ?>
		<div class="kwtsms-tab-section">

			<!-- Instant Order Alert -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Instant New Order Alert', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Send an SMS to the admin immediately when any new order is placed, before any status changes.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {order_id}, {customer_name}, {total}</code>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_instant_order_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_instant_order_enabled'] ) ); ?> />
								<?php esc_html_e( 'Send SMS on new order', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Phone Number(s)', 'wp-kwtsms' ); ?></th>
						<td>
							<input type="text"
								name="kwtsms_otp_integrations[woo_instant_order_phone]"
								value="<?php echo esc_attr( $int['woo_instant_order_phone'] ?? '' ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. 96598765432, 96599220333', 'wp-kwtsms' ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated list of phone numbers (with country code) to receive instant order alerts.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
				</table>
				<?php
				$tpl_io = $templates['woo_tpl_instant_order'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_instant_order][en]"
								id="int_woo_tpl_instant_order_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_io['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_instant_order_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_instant_order][ar]"
								id="int_woo_tpl_instant_order_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_io['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_instant_order_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_instant_order">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

			<!-- Vendor SMS -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Vendor SMS', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Requires Dokan, WCFM, or WC Vendors. Sends an SMS to each vendor whose product appears in a new order.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<?php esc_html_e( 'Vendors must save their phone number in their WordPress user profile under "Phone Number".', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {order_id}, {product_name}, {total}</code>
				</p>
				<table class="form-table" style="margin-top:8px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_vendor_sms_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_vendor_sms_enabled'] ) ); ?> />
								<?php esc_html_e( 'Send SMS to vendors on new order', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php
				$tpl_vno = $templates['woo_tpl_vendor_new_order'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_vendor_new_order][en]"
								id="int_woo_tpl_vendor_new_order_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_vno['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_vendor_new_order_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_vendor_new_order][ar]"
								id="int_woo_tpl_vendor_new_order_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_vno['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_vendor_new_order_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_vendor_new_order">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

		</div><!-- /.kwtsms-tab-section[multivendor] -->
		<?php endif; ?>
		<?php if ( 'cart_abandonment' === $active_tab ) : ?>
		<div class="kwtsms-tab-section">

			<!-- Cart Abandonment Settings -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Cart Abandonment Recovery', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Automatically send a recovery SMS with an optional coupon code to customers who add items to their cart but do not complete checkout.', 'wp-kwtsms' ); ?>
				</p>
				<table class="form-table" style="margin-top:12px;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-kwtsms' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="kwtsms_otp_integrations[woo_cart_abandon_enabled]"
									value="1"
									<?php checked( ! empty( $int['woo_cart_abandon_enabled'] ) ); ?> />
								<?php esc_html_e( 'Enable cart abandonment recovery SMS', 'wp-kwtsms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Send delay (minutes)', 'wp-kwtsms' ); ?></th>
						<td>
							<input type="number"
								name="kwtsms_otp_integrations[woo_cart_abandon_delay]"
								value="<?php echo absint( $int['woo_cart_abandon_delay'] ?? 60 ); ?>"
								min="1"
								class="small-text" />
							<p class="description"><?php esc_html_e( 'Minutes of inactivity before the recovery SMS is sent.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Coupon discount (%)', 'wp-kwtsms' ); ?></th>
						<td>
							<input type="number"
								name="kwtsms_otp_integrations[woo_cart_abandon_coupon]"
								value="<?php echo absint( $int['woo_cart_abandon_coupon'] ?? 10 ); ?>"
								min="0"
								max="100"
								class="small-text" />
							<p class="description"><?php esc_html_e( 'Set to 0 to disable coupon generation. Otherwise, a single-use percentage coupon will be created and included in the message.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Coupon expiry (hours)', 'wp-kwtsms' ); ?></th>
						<td>
							<input type="number"
								name="kwtsms_otp_integrations[woo_cart_abandon_expiry]"
								value="<?php echo absint( $int['woo_cart_abandon_expiry'] ?? 48 ); ?>"
								min="1"
								class="small-text" />
							<p class="description"><?php esc_html_e( 'Hours until the generated coupon expires.', 'wp-kwtsms' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Cart Abandonment Template -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Recovery SMS Template', 'wp-kwtsms' ); ?></h3>
				</div>
				<p class="description">
					<?php esc_html_e( 'Message sent to the customer when their cart is considered abandoned.', 'wp-kwtsms' ); ?>
				</p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code>{site_name}, {first_name}, {cart_total}, {coupon_code}, {discount}, {cart_url}</code>
				</p>
				<?php
				$tpl_ca = $templates['woo_tpl_cart_abandon'] ?? array(
					'en' => '',
					'ar' => '',
				);
				?>
				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_cart_abandon][en]"
								id="int_woo_tpl_cart_abandon_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl_ca['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_cart_abandon_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[woo_tpl_cart_abandon][ar]"
								id="int_woo_tpl_cart_abandon_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl_ca['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_woo_tpl_cart_abandon_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="woo_tpl_cart_abandon">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

		</div><!-- /.kwtsms-tab-section[cart_abandonment] -->
		<?php endif; ?>

		<?php
		foreach ( $woo_template_defs as $key => $def ) :
			$tpl       = $templates[ $key ] ?? array(
				'enabled' => 0,
				'en'      => '',
				'ar'      => '',
			);
			$is_active = ( $key === $active_tab );
			?>
		<div class="kwtsms-tab-section"<?php echo $is_active ? '' : ' style="display:none;"'; ?>>

			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php echo esc_html( $def['label'] ); ?></h3>
				</div>
				<p class="description"><?php echo esc_html( $def['description'] ); ?></p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms' ); ?></strong>
					<code><?php echo esc_html( $def['placeholders'] ); ?></code>
				</p>

				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
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
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
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
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="<?php echo esc_attr( $key ); ?>">
						&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
					</button>
				</div>
			</div>

		</div><!-- /.kwtsms-tab-section[<?php echo esc_attr( $key ); ?>] -->
		<?php endforeach; ?>

		<?php submit_button( __( 'Save WooCommerce Settings', 'wp-kwtsms' ), 'primary kwtsms-save-btn' ); ?>

	</form>

</div><!-- /.kwtsms-admin-wrap -->
