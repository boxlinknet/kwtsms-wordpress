<?php
/**
 * Admin View: Admin Alerts Settings Page.
 *
 * Renders per-event toggles, admin phone numbers, and message templates
 * (EN + AR). Uses the WordPress Settings API, saved to kwtsms_otp_alerts.
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
<div class="wrap">
	<h1><?php esc_html_e( 'Admin Alerts', 'wp-kwtsms' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Send SMS notifications to admin phone numbers when key site events occur.', 'wp-kwtsms' ); ?></p>

	<form method="post" action="options.php">
		<?php settings_fields( 'kwtsms_otp_alerts_group' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="kwtsms-admin-phones"><?php esc_html_e( 'Admin Phone Numbers', 'wp-kwtsms' ); ?></label>
				</th>
				<td>
					<input type="text" id="kwtsms-admin-phones" name="kwtsms_otp_alerts[admin_phones]"
						value="<?php echo esc_attr( $alerts['admin_phones'] ); ?>"
						class="regular-text"
						placeholder="96598765432, 96512345678">
					<p class="description"><?php esc_html_e( 'Comma-separated phone numbers with country code. These receive all enabled alert types.', 'wp-kwtsms' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Event Alerts', 'wp-kwtsms' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Enable or disable each event alert and customise the message templates.', 'wp-kwtsms' ); ?></p>

		<table class="form-table" role="presentation">
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
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $event_label ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="kwtsms_otp_alerts[<?php echo esc_attr( $event_key ); ?>]"
								value="1" <?php checked( ! empty( $alerts[ $event_key ] ) ); ?>>
							<?php esc_html_e( 'Enable this alert', 'wp-kwtsms' ); ?>
						</label>

						<div style="margin-top: 8px;">
							<label>
								<strong><?php esc_html_e( 'Message (EN)', 'wp-kwtsms' ); ?></strong><br>
								<textarea name="kwtsms_otp_alerts[<?php echo esc_attr( $tpl_key ); ?>_en]"
									rows="2" style="width:100%;max-width:500px;"><?php echo esc_textarea( $tpl['en'] ? $tpl['en'] : $default_tpl['en'] ); ?></textarea>
							</label>
						</div>

						<div style="margin-top: 4px;">
							<label>
								<strong><?php esc_html_e( 'Message (AR)', 'wp-kwtsms' ); ?></strong><br>
								<textarea name="kwtsms_otp_alerts[<?php echo esc_attr( $tpl_key ); ?>_ar]"
									rows="2" style="width:100%;max-width:500px;" dir="rtl"><?php echo esc_textarea( $tpl['ar'] ? $tpl['ar'] : $default_tpl['ar'] ); ?></textarea>
							</label>
						</div>

						<p class="description">
							<?php
							printf(
								/* translators: %s: comma-separated list of placeholder names */
								esc_html__( 'Available placeholders: %s', 'wp-kwtsms' ),
								'<code>' . esc_html( $tpl_placeholders[ $tpl_key ] ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Save Alert Settings', 'wp-kwtsms' ) ); ?>
	</form>
</div>
