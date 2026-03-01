<?php
/**
 * Admin View: Integrations Page.
 *
 * Tabbed settings hub for all third-party plugin integrations.
 * Each tab has an enable toggle and editable SMS template cards.
 *
 * IMPORTANT: All tab content is rendered in a single <form> so that
 * submitting one tab never erases data from another tab. All inputs are
 * always present in the DOM (tabs are shown/hidden via CSS/JS only).
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */

$woo_active       = class_exists( 'WooCommerce' );
$cf7_active       = class_exists( 'WPCF7' );
$wpforms_active   = function_exists( 'wpforms' ) || class_exists( 'WPForms\WPForms' );
$elementor_active = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );

$settings = $this->plugin->settings;

// Load saved integration settings with defaults merged in.
$int = array_merge(
	KwtSMS_Settings::DEFAULTS['integrations'],
	(array) $settings->get( 'integrations' )
);

// For each template key, merge saved values over the defaults.
$templates = $settings->get_all_integration_templates();
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtSMS — Integrations', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<p style="max-width:800px;font-size:14px;">
		<?php esc_html_e( 'Configure SMS notifications for each supported third-party plugin. Settings are saved together — switching tabs does not lose unsaved changes.', 'wp-kwtsms-otp' ); ?>
	</p>

	<!-- ===== Tab Navigation ===== -->
	<h2 class="nav-tab-wrapper kwtsms-int-tabs">
		<a href="#kwtsms-tab-woo" class="nav-tab nav-tab-active kwtsms-int-tab-link">
			<?php esc_html_e( 'WooCommerce', 'wp-kwtsms-otp' ); ?>
			<?php if ( ! $woo_active ) : ?>
				<span class="kwtsms-badge-inactive"><?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
			<?php endif; ?>
		</a>
		<a href="#kwtsms-tab-cf7" class="nav-tab kwtsms-int-tab-link">
			<?php esc_html_e( 'Contact Form 7', 'wp-kwtsms-otp' ); ?>
			<?php if ( ! $cf7_active ) : ?>
				<span class="kwtsms-badge-inactive"><?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
			<?php endif; ?>
		</a>
		<a href="#kwtsms-tab-wpforms" class="nav-tab kwtsms-int-tab-link">
			<?php esc_html_e( 'WPForms', 'wp-kwtsms-otp' ); ?>
			<?php if ( ! $wpforms_active ) : ?>
				<span class="kwtsms-badge-inactive"><?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
			<?php endif; ?>
		</a>
		<a href="#kwtsms-tab-elementor" class="nav-tab kwtsms-int-tab-link">
			<?php esc_html_e( 'Elementor', 'wp-kwtsms-otp' ); ?>
			<?php if ( ! $elementor_active ) : ?>
				<span class="kwtsms-badge-inactive"><?php esc_html_e( 'Not installed', 'wp-kwtsms-otp' ); ?></span>
			<?php endif; ?>
		</a>
	</h2>

	<!-- ===== Single form wrapping ALL tab content ===== -->
	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_integrations_group' ); ?>

		<!-- ================================================================
		     Tab: WooCommerce
		     ================================================================ -->
		<div id="kwtsms-tab-woo" class="kwtsms-int-tab-content">

			<?php if ( ! $woo_active ) : ?>
			<div class="notice notice-warning inline" style="margin:16px 0;">
				<p><?php esc_html_e( 'WooCommerce is not installed or activated. The settings below will be saved and applied once WooCommerce is active.', 'wp-kwtsms-otp' ); ?></p>
			</div>
			<?php endif; ?>

			<!-- Enable toggle -->
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
							<span class="kwtsms-lang-flag">🇬🇧</span>
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
							<span class="kwtsms-lang-flag">🇰🇼</span>
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

		</div><!-- /#kwtsms-tab-woo -->

		<!-- ================================================================
		     Tab: Contact Form 7
		     ================================================================ -->
		<div id="kwtsms-tab-cf7" class="kwtsms-int-tab-content" style="display:none;">

			<?php if ( ! $cf7_active ) : ?>
			<div class="notice notice-warning inline" style="margin:16px 0;">
				<p><?php esc_html_e( 'Contact Form 7 is not installed or activated. The settings below will be saved and applied once CF7 is active.', 'wp-kwtsms-otp' ); ?></p>
			</div>
			<?php endif; ?>

			<!-- Enable toggle -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Contact Form 7 Integration', 'wp-kwtsms-otp' ); ?></h3>
					<label class="kwtsms-toggle">
						<input type="checkbox"
							name="kwtsms_otp_integrations[cf7_enabled]"
							value="1"
							<?php checked( $int['cf7_enabled'], 1 ); ?> />
						<span><?php esc_html_e( 'Enable CF7 SMS Integration', 'wp-kwtsms-otp' ); ?></span>
					</label>
				</div>
				<p class="description">
					<?php esc_html_e( 'Send a confirmation SMS after a CF7 form is submitted successfully.', 'wp-kwtsms-otp' ); ?>
				</p>

				<div class="notice notice-info inline" style="margin:12px 0 0;">
					<p>
						<?php esc_html_e( 'Setup tip: add a tel field named kwtsms_phone to your CF7 form:', 'wp-kwtsms-otp' ); ?>
						<code>[tel kwtsms_phone placeholder "e.g. 96598765432"]</code>
					</p>
				</div>
			</div>

			<!-- CF7 confirmation template card -->
			<?php
			$cf7_tpl = $templates['cf7_confirmation'] ?? array( 'enabled' => 0, 'en' => '', 'ar' => '' );
			?>
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Form Submission Confirmation', 'wp-kwtsms-otp' ); ?></h3>
					<label class="kwtsms-toggle">
						<input type="checkbox"
							name="kwtsms_otp_integrations[cf7_confirmation][enabled]"
							value="1"
							<?php checked( $cf7_tpl['enabled'], 1 ); ?> />
						<span><?php esc_html_e( 'Enabled', 'wp-kwtsms-otp' ); ?></span>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Sent to the submitter after a successful form submission.', 'wp-kwtsms-otp' ); ?></p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms-otp' ); ?></strong>
					<code>{site_name}, {form_name}</code>
				</p>

				<div class="kwtsms-template-fields">
					<!-- English -->
					<div class="kwtsms-template-field">
						<label for="int_cf7_confirmation_en">
							<span class="kwtsms-lang-flag">🇬🇧</span>
							<?php esc_html_e( 'English (LTR)', 'wp-kwtsms-otp' ); ?>
						</label>
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[cf7_confirmation][en]"
								id="int_cf7_confirmation_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $cf7_tpl['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_cf7_confirmation_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
							</div>
						</div>
					</div>

					<!-- Arabic -->
					<div class="kwtsms-template-field">
						<label for="int_cf7_confirmation_ar">
							<span class="kwtsms-lang-flag">🇰🇼</span>
							<?php esc_html_e( 'Arabic (RTL)', 'wp-kwtsms-otp' ); ?>
						</label>
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[cf7_confirmation][ar]"
								id="int_cf7_confirmation_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $cf7_tpl['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_cf7_confirmation_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div><!-- /#kwtsms-tab-cf7 -->

		<!-- ================================================================
		     Tab: WPForms
		     ================================================================ -->
		<div id="kwtsms-tab-wpforms" class="kwtsms-int-tab-content" style="display:none;">

			<?php if ( ! $wpforms_active ) : ?>
			<div class="notice notice-warning inline" style="margin:16px 0;">
				<p><?php esc_html_e( 'WPForms is not installed or activated. The settings below will be saved and applied once WPForms is active.', 'wp-kwtsms-otp' ); ?></p>
			</div>
			<?php endif; ?>

			<!-- Enable toggle -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'WPForms Integration', 'wp-kwtsms-otp' ); ?></h3>
					<label class="kwtsms-toggle">
						<input type="checkbox"
							name="kwtsms_otp_integrations[wpforms_enabled]"
							value="1"
							<?php checked( $int['wpforms_enabled'], 1 ); ?> />
						<span><?php esc_html_e( 'Enable WPForms SMS Integration', 'wp-kwtsms-otp' ); ?></span>
					</label>
				</div>
				<p class="description">
					<?php esc_html_e( 'Send a confirmation SMS after a WPForms form is submitted successfully.', 'wp-kwtsms-otp' ); ?>
				</p>
				<div class="notice notice-info inline" style="margin:12px 0 0;">
					<p><?php esc_html_e( 'WPForms automatically detects Phone fields. Add a Phone field to your form to enable SMS delivery.', 'wp-kwtsms-otp' ); ?></p>
				</div>
			</div>

			<!-- WPForms confirmation template card -->
			<?php
			$wpf_tpl = $templates['wpforms_confirmation'] ?? array( 'enabled' => 0, 'en' => '', 'ar' => '' );
			?>
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Form Submission Confirmation', 'wp-kwtsms-otp' ); ?></h3>
					<label class="kwtsms-toggle">
						<input type="checkbox"
							name="kwtsms_otp_integrations[wpforms_confirmation][enabled]"
							value="1"
							<?php checked( $wpf_tpl['enabled'], 1 ); ?> />
						<span><?php esc_html_e( 'Enabled', 'wp-kwtsms-otp' ); ?></span>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Sent to the submitter after a successful form submission.', 'wp-kwtsms-otp' ); ?></p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms-otp' ); ?></strong>
					<code>{site_name}, {form_name}</code>
				</p>

				<div class="kwtsms-template-fields">
					<!-- English -->
					<div class="kwtsms-template-field">
						<label for="int_wpforms_confirmation_en">
							<span class="kwtsms-lang-flag">🇬🇧</span>
							<?php esc_html_e( 'English (LTR)', 'wp-kwtsms-otp' ); ?>
						</label>
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[wpforms_confirmation][en]"
								id="int_wpforms_confirmation_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $wpf_tpl['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_wpforms_confirmation_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
							</div>
						</div>
					</div>

					<!-- Arabic -->
					<div class="kwtsms-template-field">
						<label for="int_wpforms_confirmation_ar">
							<span class="kwtsms-lang-flag">🇰🇼</span>
							<?php esc_html_e( 'Arabic (RTL)', 'wp-kwtsms-otp' ); ?>
						</label>
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[wpforms_confirmation][ar]"
								id="int_wpforms_confirmation_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $wpf_tpl['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_wpforms_confirmation_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div><!-- /#kwtsms-tab-wpforms -->

		<!-- ================================================================
		     Tab: Elementor
		     ================================================================ -->
		<div id="kwtsms-tab-elementor" class="kwtsms-int-tab-content" style="display:none;">

			<?php if ( ! $elementor_active ) : ?>
			<div class="notice notice-warning inline" style="margin:16px 0;">
				<p><?php esc_html_e( 'Elementor is not installed or activated. The settings below will be saved and applied once Elementor is active.', 'wp-kwtsms-otp' ); ?></p>
			</div>
			<?php endif; ?>

			<!-- Enable toggle -->
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Elementor Integration', 'wp-kwtsms-otp' ); ?></h3>
					<label class="kwtsms-toggle">
						<input type="checkbox"
							name="kwtsms_otp_integrations[elementor_enabled]"
							value="1"
							<?php checked( $int['elementor_enabled'], 1 ); ?> />
						<span><?php esc_html_e( 'Enable Elementor SMS Integration', 'wp-kwtsms-otp' ); ?></span>
					</label>
				</div>
				<p class="description">
					<?php esc_html_e( 'Send a confirmation SMS after an Elementor Pro form is submitted successfully.', 'wp-kwtsms-otp' ); ?>
				</p>
				<div class="notice notice-info inline" style="margin:12px 0 0;">
					<p><?php esc_html_e( 'Requires Elementor Pro. Add a Tel or Phone field to your form — the plugin will use it as the destination number.', 'wp-kwtsms-otp' ); ?></p>
				</div>
			</div>

			<!-- Elementor confirmation template card -->
			<?php
			$elm_tpl = $templates['elementor_confirmation'] ?? array( 'enabled' => 0, 'en' => '', 'ar' => '' );
			?>
			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php esc_html_e( 'Form Submission Confirmation', 'wp-kwtsms-otp' ); ?></h3>
					<label class="kwtsms-toggle">
						<input type="checkbox"
							name="kwtsms_otp_integrations[elementor_confirmation][enabled]"
							value="1"
							<?php checked( $elm_tpl['enabled'], 1 ); ?> />
						<span><?php esc_html_e( 'Enabled', 'wp-kwtsms-otp' ); ?></span>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Sent to the submitter after a successful Elementor form submission.', 'wp-kwtsms-otp' ); ?></p>
				<p class="description" style="margin-top:4px;">
					<strong><?php esc_html_e( 'Placeholders:', 'wp-kwtsms-otp' ); ?></strong>
					<code>{site_name}, {form_name}</code>
				</p>

				<div class="kwtsms-template-fields">
					<!-- English -->
					<div class="kwtsms-template-field">
						<label for="int_elementor_confirmation_en">
							<span class="kwtsms-lang-flag">🇬🇧</span>
							<?php esc_html_e( 'English (LTR)', 'wp-kwtsms-otp' ); ?>
						</label>
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[elementor_confirmation][en]"
								id="int_elementor_confirmation_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $elm_tpl['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_elementor_confirmation_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
							</div>
						</div>
					</div>

					<!-- Arabic -->
					<div class="kwtsms-template-field">
						<label for="int_elementor_confirmation_ar">
							<span class="kwtsms-lang-flag">🇰🇼</span>
							<?php esc_html_e( 'Arabic (RTL)', 'wp-kwtsms-otp' ); ?>
						</label>
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_integrations[elementor_confirmation][ar]"
								id="int_elementor_confirmation_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $elm_tpl['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="int_elementor_confirmation_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div><!-- /#kwtsms-tab-elementor -->

		<?php submit_button( __( 'Save Integration Settings', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn' ); ?>

	</form>

</div><!-- /.kwtsms-admin-wrap -->
