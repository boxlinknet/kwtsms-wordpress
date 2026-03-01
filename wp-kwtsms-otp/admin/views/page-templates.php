<?php
/**
 * Admin View: SMS Templates Page.
 *
 * Allows editing of EN + AR SMS templates for each event.
 * Includes live character counter with SMS page count.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */
$templates = $this->plugin->settings->get_all_templates();

$template_labels = array(
	'login_otp'   => __( 'Login OTP', 'wp-kwtsms-otp' ),
	'reset_otp'   => __( 'Password Reset OTP', 'wp-kwtsms-otp' ),
	'welcome_sms' => __( 'Welcome SMS', 'wp-kwtsms-otp' ),
);

$template_descriptions = array(
	'login_otp'   => __( 'Sent when a user requests a login OTP code.', 'wp-kwtsms-otp' ),
	'reset_otp'   => __( 'Sent when a user requests a password reset via OTP.', 'wp-kwtsms-otp' ),
	'welcome_sms' => __( 'Sent after a new user account is created. (Optional)', 'wp-kwtsms-otp' ),
);

$placeholders_info = array(
	'{otp}'             => __( 'The generated OTP code', 'wp-kwtsms-otp' ),
	'{site_name}'       => __( 'Your WordPress site name', 'wp-kwtsms-otp' ),
	'{expiry_minutes}'  => __( 'OTP validity period in minutes', 'wp-kwtsms-otp' ),
	'{name}'            => __( 'User display name (welcome SMS only)', 'wp-kwtsms-otp' ),
);
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtSMS — SMS Templates', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<!-- Placeholders reference -->
	<div class="kwtsms-placeholder-help">
		<strong><?php esc_html_e( 'Available placeholders:', 'wp-kwtsms-otp' ); ?></strong>
		<?php foreach ( $placeholders_info as $placeholder => $desc ) : ?>
			<span class="kwtsms-placeholder-tag"><?php echo esc_html( $placeholder ); ?></span>
			<span class="kwtsms-placeholder-desc"><?php echo esc_html( $desc ); ?></span>
			&nbsp;
		<?php endforeach; ?>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_templates_group' ); ?>

		<?php foreach ( $template_labels as $key => $label ) :
			$tpl = $templates[ $key ] ?? array( 'enabled' => 0, 'en' => '', 'ar' => '' );
		?>
		<div class="kwtsms-template-card">
			<div class="kwtsms-template-card-header">
				<h3><?php echo esc_html( $label ); ?></h3>
				<label class="kwtsms-toggle">
					<input type="checkbox" name="kwtsms_otp_templates[<?php echo esc_attr( $key ); ?>][enabled]"
						value="1" <?php checked( $tpl['enabled'], 1 ); ?> />
					<span><?php esc_html_e( 'Enabled', 'wp-kwtsms-otp' ); ?></span>
				</label>
			</div>
			<p class="description"><?php echo esc_html( $template_descriptions[ $key ] ); ?></p>

			<div class="kwtsms-template-fields">
				<!-- English -->
				<div class="kwtsms-template-field">
					<label for="tpl_<?php echo esc_attr( $key ); ?>_en">
						<span class="kwtsms-lang-flag">🇬🇧</span>
						<?php esc_html_e( 'English (LTR)', 'wp-kwtsms-otp' ); ?>
					</label>
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
							<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
							· <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
						</div>
					</div>
				</div>

				<!-- Arabic -->
				<div class="kwtsms-template-field">
					<label for="tpl_<?php echo esc_attr( $key ); ?>_ar">
						<span class="kwtsms-lang-flag">🇰🇼</span>
						<?php esc_html_e( 'Arabic (RTL)', 'wp-kwtsms-otp' ); ?>
					</label>
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
							<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms-otp' ); ?>
							· <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms-otp' ); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save Templates', 'wp-kwtsms-otp' ), 'primary kwtsms-save-btn' ); ?>
	</form>
</div>
