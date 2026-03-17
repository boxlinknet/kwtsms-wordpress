<?php
/**
 * Admin View: SMS Templates Page.
 *
 * Three URL-driven tabs — one per template — matching the Logs page nav style.
 * The form wraps all tab sections; hidden sections (display:none) still submit,
 * so Save Templates always persists all three templates at once.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- @var KwtSMS_Admin $this, injected by admin controller.
$kwtsms_templates = $this->plugin->settings->get_all_templates();

$kwtsms_template_labels = array(
	'login_otp'   => __( 'Login OTP', 'kwtsms' ),
	'reset_otp'   => __( 'Password Reset OTP', 'kwtsms' ),
	'welcome_sms' => __( 'Welcome SMS', 'kwtsms' ),
);

$kwtsms_template_descriptions = array(
	'login_otp'   => __( 'Sent when a user requests a login OTP code.', 'kwtsms' ),
	'reset_otp'   => __( 'Sent when a user requests a password reset via OTP.', 'kwtsms' ),
	'welcome_sms' => __( 'Sent after a new user account is created. (Optional)', 'kwtsms' ),
);

$kwtsms_template_placeholders = array(
	'login_otp'   => array(
		'{otp}'            => __( 'The generated OTP code', 'kwtsms' ),
		'{site_name}'      => __( 'Your WordPress site name', 'kwtsms' ),
		'{expiry_minutes}' => __( 'OTP validity period in minutes', 'kwtsms' ),
	),
	'reset_otp'   => array(
		'{otp}'            => __( 'The generated OTP code', 'kwtsms' ),
		'{site_name}'      => __( 'Your WordPress site name', 'kwtsms' ),
		'{expiry_minutes}' => __( 'OTP validity period in minutes', 'kwtsms' ),
	),
	'welcome_sms' => array(
		'{name}'      => __( 'User display name', 'kwtsms' ),
		'{site_name}' => __( 'Your WordPress site name', 'kwtsms' ),
	),
);

$kwtsms_valid_tabs = array_keys( $kwtsms_template_labels );
$kwtsms_active_tab = isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $kwtsms_valid_tabs, true ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	? sanitize_key( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	: 'login_otp';

/**
 * Build a tab URL for the Templates page.
 *
 * @param string $tab Tab key.
 * @return string Admin URL with page + tab query args.
 */
function kwtsms_templates_tab_url( $tab ) {
	return add_query_arg(
		array(
			'page' => 'kwtsms-otp-templates',
			'tab'  => $tab,
		),
		admin_url( 'admin.php' )
	);
}
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'SMS Templates', 'kwtsms' ); ?></h1>
	</div>
	<hr class="wp-header-end">

	<!-- Tab navigation -->
	<nav class="nav-tab-wrapper">
		<?php foreach ( $kwtsms_template_labels as $kwtsms_key => $kwtsms_label ) : ?>
		<a href="<?php echo esc_url( kwtsms_templates_tab_url( $kwtsms_key ) ); ?>"
			class="nav-tab <?php echo $kwtsms_key === $kwtsms_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $kwtsms_label ); ?>
		</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_templates_group' ); ?>

		<?php
		foreach ( $kwtsms_template_labels as $kwtsms_key => $kwtsms_label ) :
			$kwtsms_tpl       = $kwtsms_templates[ $kwtsms_key ] ?? array(
				'enabled' => 0,
				'en'      => '',
				'ar'      => '',
			);
			$kwtsms_is_active = ( $kwtsms_key === $kwtsms_active_tab );
			?>
		<div class="kwtsms-tab-section"<?php echo $kwtsms_is_active ? ' style="margin-top:16px;"' : ' style="display:none;"'; ?>>

			<div class="kwtsms-placeholder-help">
				<strong><?php esc_html_e( 'Available placeholders:', 'kwtsms' ); ?></strong>
				<ul style="margin:6px 0 0 16px;list-style:disc;">
					<?php foreach ( $kwtsms_template_placeholders[ $kwtsms_key ] as $kwtsms_placeholder => $kwtsms_desc ) : ?>
					<li>
						<span class="kwtsms-placeholder-tag"><?php echo esc_html( $kwtsms_placeholder ); ?></span>
						<span class="kwtsms-placeholder-desc"><?php echo esc_html( $kwtsms_desc ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php echo esc_html( $kwtsms_label ); ?></h3>
				</div>
				<p class="description"><?php echo esc_html( $kwtsms_template_descriptions[ $kwtsms_key ] ); ?></p>

				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_templates[<?php echo esc_attr( $kwtsms_key ); ?>][en]"
								id="tpl_<?php echo esc_attr( $kwtsms_key ); ?>_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $kwtsms_tpl['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="tpl_<?php echo esc_attr( $kwtsms_key ); ?>_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_templates[<?php echo esc_attr( $kwtsms_key ); ?>][ar]"
								id="tpl_<?php echo esc_attr( $kwtsms_key ); ?>_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $kwtsms_tpl['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="tpl_<?php echo esc_attr( $kwtsms_key ); ?>_ar">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'kwtsms' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="kwtsms-reset-wrap" style="margin-top:8px;">
					<button type="button" class="button kwtsms-reset-template"
						data-key="<?php echo esc_attr( $kwtsms_key ); ?>">
						&#8635; <?php esc_html_e( 'Reset to Default', 'kwtsms' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save Templates', 'kwtsms' ), 'primary kwtsms-save-btn' ); ?>
	</form>
</div>
