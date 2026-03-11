<?php
/**
 * Admin View: Admin Alerts Settings Page.
 *
 * Renders per-event toggles, admin phone numbers, and bilingual message
 * templates (EN / AR via language tabs). Uses the WordPress Settings API,
 * saved to kwtsms_otp_alerts.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- @var KwtSMS_Admin $this, injected by admin controller.
$settings = $this->plugin->settings;
// array_replace_recursive merges nested template arrays (en/ar) correctly.
// The + operator only works for flat arrays; alerts has nested tpl_* sub-arrays.
$alerts = array_replace_recursive( KwtSMS_Settings::DEFAULTS['alerts'], (array) $settings->get( 'alerts' ) );

$events = array(
	'user_register'  => __( 'New User Registered', 'wp-kwtsms' ),
	'wp_login'       => __( 'User Login', 'wp-kwtsms' ),
	'post_published' => __( 'Post Published', 'wp-kwtsms' ),
	'comment_posted' => __( 'Comment Posted', 'wp-kwtsms' ),
	'core_update'    => __( 'WordPress Core Updated', 'wp-kwtsms' ),
);

$tpl_placeholders = array(
	'tpl_user_register'  => '{site_name}, {username}, {email}',
	'tpl_wp_login'       => '{site_name}, {username}',
	'tpl_post_published' => '{site_name}, {post_title}',
	'tpl_comment_posted' => '{site_name}, {post_title}, {author}',
	'tpl_core_update'    => '{site_name}, {version}',
);
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'Admin Alerts', 'wp-kwtsms' ); ?></h1>
	</div>
	<hr class="wp-header-end">

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_alerts_group' ); ?>

		<!-- ===== Admin Phones ===== -->
		<h2 class="title"><?php esc_html_e( 'Recipient Phone Numbers', 'wp-kwtsms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="kwtsms-admin-phones"><?php esc_html_e( 'Admin Phone Numbers', 'wp-kwtsms' ); ?></label>
				</th>
				<td>
					<input type="text" id="kwtsms-admin-phones"
						name="kwtsms_otp_alerts[admin_phones]"
						value="<?php echo esc_attr( $alerts['admin_phones'] ); ?>"
						class="regular-text"
						placeholder="96598765432, 96512345678">
					<p class="description"><?php esc_html_e( 'Comma-separated phone numbers with country code. All enabled alert types are sent to every number listed here.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>
		</table>

		<!-- ===== Event Alerts ===== -->
		<h2 class="title"><?php esc_html_e( 'Event Alerts', 'wp-kwtsms' ); ?></h2>
		<p style="margin-top:-8px;margin-bottom:16px;color:#555;font-size:13px;">
			<?php esc_html_e( 'Enable or disable each event alert. Configure the message text in the Templates section below.', 'wp-kwtsms' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php foreach ( $events as $event_key => $event_label ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $event_label ); ?></th>
				<td>
					<label class="kwtsms-toggle">
						<input type="checkbox"
							name="kwtsms_otp_alerts[<?php echo esc_attr( $event_key ); ?>]"
							value="1"
							<?php checked( ! empty( $alerts[ $event_key ] ) ); ?>>
						<?php esc_html_e( 'Enabled', 'wp-kwtsms' ); ?>
					</label>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<!-- ===== Alert Templates ===== -->
		<h2 class="title"><?php esc_html_e( 'Alert Templates', 'wp-kwtsms' ); ?></h2>
		<p style="margin-top:-8px;margin-bottom:20px;color:#555;font-size:13px;">
			<?php esc_html_e( 'Customise the SMS message for each event in English and Arabic.', 'wp-kwtsms' ); ?>
		</p>

		<?php
		foreach ( $events as $event_key => $event_label ) :
			$tpl_key     = 'tpl_' . $event_key;
			$tpl         = is_array( $alerts[ $tpl_key ] ) ? $alerts[ $tpl_key ] : array(
				'en' => '',
				'ar' => '',
			);
			$default_tpl = KwtSMS_Settings::DEFAULTS['alerts'][ $tpl_key ] ?? array(
				'en' => '',
				'ar' => '',
			);
			$en_id       = 'alerts_' . $event_key . '_en';
			$ar_id       = 'alerts_' . $event_key . '_ar';
			?>
		<div class="kwtsms-template-card">
			<div class="kwtsms-template-card-header">
				<h3><?php echo esc_html( $event_label ); ?></h3>
			</div>

			<p class="description" style="margin:0 0 12px;">
				<?php
				printf(
					/* translators: %s: comma-separated list of placeholder names */
					esc_html__( 'Available placeholders: %s', 'wp-kwtsms' ),
					'<code>' . esc_html( $tpl_placeholders[ $tpl_key ] ) . '</code>'
				);
				?>
			</p>

			<div class="kwtsms-lang-tabs">
				<div class="kwtsms-tab-nav">
					<button type="button" class="kwtsms-tab-btn is-active" data-tab="en"><?php esc_html_e( 'English', 'wp-kwtsms' ); ?></button>
					<button type="button" class="kwtsms-tab-btn" data-tab="ar"><?php esc_html_e( 'Arabic', 'wp-kwtsms' ); ?></button>
				</div>
				<div class="kwtsms-tab-pane" data-tab="en">
					<div class="kwtsms-textarea-wrap">
						<textarea
							name="kwtsms_otp_alerts[<?php echo esc_attr( $tpl_key ); ?>_en]"
							id="<?php echo esc_attr( $en_id ); ?>"
							class="large-text kwtsms-sms-textarea"
							rows="3"
							dir="ltr"
							data-lang="en"
						><?php echo esc_textarea( $tpl['en'] ? $tpl['en'] : $default_tpl['en'] ); ?></textarea>
						<div class="kwtsms-char-counter" data-target="<?php echo esc_attr( $en_id ); ?>">
							<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
							&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
						</div>
					</div>
				</div>
				<div class="kwtsms-tab-pane" data-tab="ar" style="display:none;">
					<div class="kwtsms-textarea-wrap">
						<textarea
							name="kwtsms_otp_alerts[<?php echo esc_attr( $tpl_key ); ?>_ar]"
							id="<?php echo esc_attr( $ar_id ); ?>"
							class="large-text kwtsms-sms-textarea"
							rows="3"
							dir="rtl"
							data-lang="ar"
						><?php echo esc_textarea( $tpl['ar'] ? $tpl['ar'] : $default_tpl['ar'] ); ?></textarea>
						<div class="kwtsms-char-counter" data-target="<?php echo esc_attr( $ar_id ); ?>">
							<span class="kwtsms-char-count">0</span> <?php esc_html_e( 'characters', 'wp-kwtsms' ); ?>
							&middot; <span class="kwtsms-page-count">1</span> <?php esc_html_e( 'SMS page(s)', 'wp-kwtsms' ); ?>
						</div>
					</div>
				</div>
			</div>

			<div class="kwtsms-reset-wrap" style="margin-top:8px;">
				<button type="button" class="button kwtsms-reset-template"
					data-key="<?php echo esc_attr( $tpl_key ); ?>">
					&#8635; <?php esc_html_e( 'Reset to Default', 'wp-kwtsms' ); ?>
				</button>
			</div>
		</div>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save Alert Settings', 'wp-kwtsms' ), 'primary kwtsms-save-btn' ); ?>
	</form>

</div><!-- /.kwtsms-admin-wrap -->
