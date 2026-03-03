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

/** @var KwtSMS_Admin $this */
$templates = $this->plugin->settings->get_all_templates();

$template_labels = array(
	'login_otp'   => __( 'Login OTP', 'wp-kwtsms' ),
	'reset_otp'   => __( 'Password Reset OTP', 'wp-kwtsms' ),
	'welcome_sms' => __( 'Welcome SMS', 'wp-kwtsms' ),
);

$template_descriptions = array(
	'login_otp'   => __( 'Sent when a user requests a login OTP code.', 'wp-kwtsms' ),
	'reset_otp'   => __( 'Sent when a user requests a password reset via OTP.', 'wp-kwtsms' ),
	'welcome_sms' => __( 'Sent after a new user account is created. (Optional)', 'wp-kwtsms' ),
);

$template_placeholders = array(
	'login_otp'   => array(
		'{otp}'            => __( 'The generated OTP code', 'wp-kwtsms' ),
		'{site_name}'      => __( 'Your WordPress site name', 'wp-kwtsms' ),
		'{expiry_minutes}' => __( 'OTP validity period in minutes', 'wp-kwtsms' ),
	),
	'reset_otp'   => array(
		'{otp}'            => __( 'The generated OTP code', 'wp-kwtsms' ),
		'{site_name}'      => __( 'Your WordPress site name', 'wp-kwtsms' ),
		'{expiry_minutes}' => __( 'OTP validity period in minutes', 'wp-kwtsms' ),
	),
	'welcome_sms' => array(
		'{name}'      => __( 'User display name', 'wp-kwtsms' ),
		'{site_name}' => __( 'Your WordPress site name', 'wp-kwtsms' ),
	),
);

$valid_tabs = array_keys( $template_labels );
$active_tab = isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $valid_tabs, true )
	? sanitize_key( $_GET['tab'] )
	: 'login_otp';

/**
 * Build a tab URL for the Templates page.
 *
 * @param string $tab Tab key.
 * @return string Admin URL with page + tab query args.
 */
function kwtsms_templates_tab_url( $tab ) {
	return add_query_arg(
		array( 'page' => 'kwtsms-otp-templates', 'tab' => $tab ),
		admin_url( 'admin.php' )
	);
}
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'SMS Templates', 'wp-kwtsms' ); ?></h1>
	</div>

	<!-- Tab navigation -->
	<nav class="nav-tab-wrapper">
		<?php foreach ( $template_labels as $key => $label ) : ?>
		<a href="<?php echo esc_url( kwtsms_templates_tab_url( $key ) ); ?>"
			class="nav-tab <?php echo $key === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $label ); ?>
		</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_templates_group' ); ?>

		<?php foreach ( $template_labels as $key => $label ) :
			$tpl       = $templates[ $key ] ?? array( 'enabled' => 0, 'en' => '', 'ar' => '' );
			$is_active = ( $key === $active_tab );
		?>
		<div class="kwtsms-tab-section"<?php echo $is_active ? '' : ' style="display:none;"'; ?>>

			<div class="kwtsms-placeholder-help">
				<strong><?php esc_html_e( 'Available placeholders:', 'wp-kwtsms' ); ?></strong>
				<ul style="margin:6px 0 0 16px;list-style:disc;">
					<?php foreach ( $template_placeholders[ $key ] as $placeholder => $desc ) : ?>
					<li>
						<span class="kwtsms-placeholder-tag"><?php echo esc_html( $placeholder ); ?></span>
						<span class="kwtsms-placeholder-desc"><?php echo esc_html( $desc ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="kwtsms-template-card">
				<div class="kwtsms-template-card-header">
					<h3><?php echo esc_html( $label ); ?></h3>
				</div>
				<p class="description"><?php echo esc_html( $template_descriptions[ $key ] ); ?></p>

				<div class="kwtsms-lang-tabs">
					<div class="kwtsms-tab-nav">
						<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
						<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
					</div>
					<div class="kwtsms-tab-pane" data-tab="en">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_templates[<?php echo esc_attr( $key ); ?>][en]"
								id="tpl_<?php echo esc_attr( $key ); ?>_en"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="ltr"
								data-lang="en"
							><?php echo esc_textarea( $tpl['en'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="tpl_<?php echo esc_attr( $key ); ?>_en">
								<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
								&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
							</div>
						</div>
					</div>
					<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
						<div class="kwtsms-textarea-wrap">
							<textarea
								name="kwtsms_otp_templates[<?php echo esc_attr( $key ); ?>][ar]"
								id="tpl_<?php echo esc_attr( $key ); ?>_ar"
								class="large-text kwtsms-sms-textarea"
								rows="3"
								dir="rtl"
								data-lang="ar"
							><?php echo esc_textarea( $tpl['ar'] ); ?></textarea>
							<div class="kwtsms-char-counter" data-target="tpl_<?php echo esc_attr( $key ); ?>_ar">
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
		</div>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save Templates', 'wp-kwtsms' ), 'primary kwtsms-save-btn' ); ?>
	</form>
</div>
