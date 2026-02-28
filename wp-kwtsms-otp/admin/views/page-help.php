<?php
/**
 * Admin View: Help & Support Page.
 *
 * Getting started guide, feature overview, troubleshooting, and support links.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/** @var KwtSMS_Admin $this */
$settings        = $this->plugin->settings;
$has_credentials = ! empty( $settings->get( 'gateway.api_username', '' ) ) && ! empty( $settings->get( 'gateway.api_password', '' ) );
$has_sender      = ! empty( $settings->get( 'gateway.sender_id', '' ) );
$test_mode       = (bool) $settings->get( 'gateway.test_mode', 1 );
$debug_logging   = (bool) $settings->get( 'general.debug_logging', 0 );
$content_dir     = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '';
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="https://www.kwtsms.com/images/kwtsms_logo_60.png" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'kwtSMS OTP — Help &amp; Support', 'wp-kwtsms-otp' ); ?></h1>
	</div>

	<!-- ===== Plugin Status ===== -->
	<div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px 20px;margin-bottom:24px;max-width:800px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Current Status', 'wp-kwtsms-otp' ); ?></h2>
		<table style="border-collapse:collapse;width:100%;font-size:13px;">
			<tr>
				<td style="padding:6px 0;width:200px;"><strong><?php esc_html_e( 'API Credentials', 'wp-kwtsms-otp' ); ?></strong></td>
				<td>
					<?php if ( $has_credentials ) : ?>
					<span style="color:#46b450;">&#10003; <?php esc_html_e( 'Configured', 'wp-kwtsms-otp' ); ?></span>
					<?php else : ?>
					<span style="color:#dc3232;">&#10007; <?php esc_html_e( 'Not configured', 'wp-kwtsms-otp' ); ?></span>
					&mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway Settings →', 'wp-kwtsms-otp' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Sender ID', 'wp-kwtsms-otp' ); ?></strong></td>
				<td>
					<?php if ( $has_sender ) : ?>
					<span style="color:#46b450;">&#10003; <?php echo esc_html( $settings->get( 'gateway.sender_id', '' ) ); ?></span>
					<?php else : ?>
					<span style="color:#dc3232;">&#10007; <?php esc_html_e( 'Not selected', 'wp-kwtsms-otp' ); ?></span>
					&mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway Settings →', 'wp-kwtsms-otp' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Test Mode', 'wp-kwtsms-otp' ); ?></strong></td>
				<td>
					<?php if ( $test_mode ) : ?>
					<span style="color:#FFA200;font-weight:600;"><?php esc_html_e( 'ON — no real SMS is sent', 'wp-kwtsms-otp' ); ?></span>
					<?php else : ?>
					<span style="color:#46b450;"><?php esc_html_e( 'OFF — live SMS delivery', 'wp-kwtsms-otp' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Debug Logging', 'wp-kwtsms-otp' ); ?></strong></td>
				<td>
					<?php if ( $debug_logging ) : ?>
					<span style="color:#FFA200;font-weight:600;"><?php esc_html_e( 'ON', 'wp-kwtsms-otp' ); ?></span>
					&mdash; <?php echo esc_html( $content_dir . '/kwtsms-debug.log' ); ?>
					<?php else : ?>
					<span style="color:#757575;"><?php esc_html_e( 'OFF', 'wp-kwtsms-otp' ); ?></span>
					&mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp' ) ); ?>"><?php esc_html_e( 'Enable in General Settings →', 'wp-kwtsms-otp' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Account Balance', 'wp-kwtsms-otp' ); ?></strong></td>
				<td>
					<?php
					$bal_available = $settings->get( 'gateway.balance_available', null );
					$bal_updated   = (int) $settings->get( 'gateway.balance_updated_at', 0 );
					if ( null !== $bal_available ) :
					?>
					<span style="color:#46b450;font-weight:600;">
						<?php echo esc_html( number_format( (float) $bal_available, 2 ) ); ?>
					</span>
					<?php if ( $bal_updated > 0 ) : ?>
					<span style="color:#888;font-size:12px;">
						<?php printf(
							/* translators: %s: human-readable time difference */
							esc_html__( '(updated %s ago)', 'wp-kwtsms-otp' ),
							esc_html( human_time_diff( $bal_updated, time() ) )
						); ?>
					</span>
					<?php endif; ?>
					&mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Gateway Settings →', 'wp-kwtsms-otp' ); ?></a>
					<?php else : ?>
					<span style="color:#888;"><?php esc_html_e( 'Not available — Login on the Gateway page first.', 'wp-kwtsms-otp' ); ?></span>
					&mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway Settings →', 'wp-kwtsms-otp' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<!-- ===== Getting Started ===== -->
	<div style="max-width:800px;">
		<h2><?php esc_html_e( 'Getting Started', 'wp-kwtsms-otp' ); ?></h2>
		<ol style="font-size:14px;line-height:1.8;">
			<li>
				<strong><?php esc_html_e( 'Create a kwtSMS account', 'wp-kwtsms-otp' ); ?></strong><br>
				<?php esc_html_e( 'Go to kwtsms.com and register for a free account. You will receive your API username and password.', 'wp-kwtsms-otp' ); ?>
				<a href="https://www.kwtsms.com/signup" target="_blank" rel="noopener"><?php esc_html_e( 'Sign up →', 'wp-kwtsms-otp' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Register a Sender ID', 'wp-kwtsms-otp' ); ?></strong><br>
				<?php esc_html_e( 'A Sender ID is the name or number your recipients see. Apply for one in your kwtSMS dashboard. This is required before sending.', 'wp-kwtsms-otp' ); ?>
				<a href="https://www.kwtsms.com/sender-id-help.html" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more →', 'wp-kwtsms-otp' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Configure Gateway Settings', 'wp-kwtsms-otp' ); ?></strong><br>
				<?php esc_html_e( 'Go to kwtSMS OTP → Gateway. Enter your API username and password, then click "Save & Verify Credentials" to load your approved Sender IDs. Select a Sender ID and save.', 'wp-kwtsms-otp' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway →', 'wp-kwtsms-otp' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Set up General Settings', 'wp-kwtsms-otp' ); ?></strong><br>
				<?php esc_html_e( 'Choose your OTP mode (2FA, Passwordless, or Both), code length, expiry, and default country for the dial-code dropdown.', 'wp-kwtsms-otp' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp' ) ); ?>"><?php esc_html_e( 'Go to General →', 'wp-kwtsms-otp' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Add phone numbers to user profiles', 'wp-kwtsms-otp' ); ?></strong><br>
				<?php esc_html_e( 'Each user must have a phone number in their profile (Users → Edit User → Phone Number). Without a phone number, 2FA is skipped for that user.', 'wp-kwtsms-otp' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Send a test SMS', 'wp-kwtsms-otp' ); ?></strong><br>
				<?php esc_html_e( 'On the Gateway page, enter a test phone number and click "Send Gateway Test SMS". With Test Mode ON, the code will not be delivered but will appear in debug.log.', 'wp-kwtsms-otp' ); ?>
			</li>
		</ol>

		<!-- ===== Features ===== -->
		<h2><?php esc_html_e( 'Features Overview', 'wp-kwtsms-otp' ); ?></h2>

		<h3><?php esc_html_e( 'OTP Two-Factor Authentication (2FA)', 'wp-kwtsms-otp' ); ?></h3>
		<p><?php esc_html_e( 'After a user enters their password, an OTP code is sent to their registered phone. They must enter the code to complete login. Configure code length (4 or 6 digits), expiry (1–30 min), and max wrong attempts in General Settings.', 'wp-kwtsms-otp' ); ?></p>

		<h3><?php esc_html_e( 'Passwordless Login', 'wp-kwtsms-otp' ); ?></h3>
		<p><?php esc_html_e( 'Users can log in with just their phone number — no password needed. An OTP is sent to their phone and they are logged in on success. Enable via OTP Mode → Passwordless or Both. A country-code dropdown with GeoIP pre-selection is shown on the login form.', 'wp-kwtsms-otp' ); ?></p>

		<h3><?php esc_html_e( 'Password Reset via SMS', 'wp-kwtsms-otp' ); ?></h3>
		<p><?php esc_html_e( 'Replaces the default email reset link with an SMS OTP. Users receive a code by SMS to verify their identity, then are taken to the reset form. Enable via General Settings → Enable Password Reset OTP.', 'wp-kwtsms-otp' ); ?></p>

		<h3><?php esc_html_e( 'SMS Templates', 'wp-kwtsms-otp' ); ?></h3>
		<p><?php esc_html_e( 'Customise the message text for each event (login, reset, welcome). Placeholders like {otp}, {site_name}, and {expiry_minutes} are replaced automatically. Separate English and Arabic templates are supported.', 'wp-kwtsms-otp' ); ?></p>

		<h3><?php esc_html_e( 'Logs', 'wp-kwtsms-otp' ); ?></h3>
		<p>
			<?php esc_html_e( 'The Logs page shows two tabs:', 'wp-kwtsms-otp' ); ?>
		</p>
		<ul style="margin-left:20px;font-size:14px;">
			<li><strong><?php esc_html_e( 'SMS History', 'wp-kwtsms-otp' ); ?></strong> — <?php esc_html_e( 'Full unredacted log of all SMS sends (phone, message, status, message ID).', 'wp-kwtsms-otp' ); ?></li>
			<li><strong><?php esc_html_e( 'OTP Attempts', 'wp-kwtsms-otp' ); ?></strong> — <?php esc_html_e( 'Every verification attempt with result, IP address, and user. Useful for detecting brute-force attacks.', 'wp-kwtsms-otp' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'Allowed Countries', 'wp-kwtsms-otp' ); ?></h3>
		<p><?php esc_html_e( 'In General Settings you can restrict which countries are shown in the dial-code dropdown and accepted for OTP. Default is GCC countries. This prevents OTPs from being sent to unintended regions.', 'wp-kwtsms-otp' ); ?></p>

		<!-- ===== Styling ===== -->
		<h2><?php esc_html_e( 'Styling &amp; Customisation', 'wp-kwtsms-otp' ); ?></h2>
		<p>
			<?php esc_html_e( 'The OTP and passwordless login pages use the plugin\'s own stylesheet (assets/css/login.css). This stylesheet is intentionally minimal — it uses standard WordPress blue (#2271b1) for interactive elements and inherits the base WordPress login page layout. It does NOT override your theme colours or fonts.', 'wp-kwtsms-otp' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'To customise the appearance, you do NOT need a customisation page. Simply add CSS overrides to your theme\'s Additional CSS (Appearance → Customise → Additional CSS) or your child theme\'s style.css. Key selectors:', 'wp-kwtsms-otp' ); ?>
		</p>
		<ul style="margin-left:20px;font-size:13px;line-height:2;font-family:monospace;background:#f8f8f8;padding:10px 20px;border:1px solid #ddd;border-radius:4px;">
			<li>.kwtsms-otp-wrap — <?php esc_html_e( 'outer container of OTP entry form', 'wp-kwtsms-otp' ); ?></li>
			<li>.kwtsms-form-card — <?php esc_html_e( 'white card around the form', 'wp-kwtsms-otp' ); ?></li>
			<li>.kwtsms-otp-input — <?php esc_html_e( 'the OTP code input field', 'wp-kwtsms-otp' ); ?></li>
			<li>.kwtsms-submit-btn — <?php esc_html_e( 'the Submit / Verify button', 'wp-kwtsms-otp' ); ?></li>
			<li>.kwtsms-phone-group — <?php esc_html_e( 'country code + phone input wrapper', 'wp-kwtsms-otp' ); ?></li>
			<li>.kwtsms-powered-by — <?php esc_html_e( '"SMS service by kwtSMS.com" footer', 'wp-kwtsms-otp' ); ?></li>
		</ul>
		<p>
			<?php esc_html_e( 'No customisation page is needed. Full CSS control is available through standard WordPress/theme overrides.', 'wp-kwtsms-otp' ); ?>
		</p>

		<!-- ===== Troubleshooting ===== -->
		<h2><?php esc_html_e( 'Troubleshooting', 'wp-kwtsms-otp' ); ?></h2>

		<h3><?php esc_html_e( 'SMS is not being sent', 'wp-kwtsms-otp' ); ?></h3>
		<ol style="font-size:14px;line-height:1.8;">
			<li><?php esc_html_e( 'Go to Gateway Settings and click "Save & Verify Credentials". If it fails, your username or password is wrong.', 'wp-kwtsms-otp' ); ?></li>
			<li><?php esc_html_e( 'Make sure a Sender ID is selected. If the dropdown is empty, click "Reload" after verifying credentials.', 'wp-kwtsms-otp' ); ?></li>
			<li><?php esc_html_e( 'Check that Test Mode is OFF if you expect real delivery. With Test Mode ON, messages are queued but not sent.', 'wp-kwtsms-otp' ); ?></li>
			<li><?php esc_html_e( 'Enable Debug Logging (General Settings → Developer Tools), attempt a send, and check wp-content/kwtsms-debug.log for the full API response.', 'wp-kwtsms-otp' ); ?></li>
			<li><?php esc_html_e( 'Check your kwtSMS account balance at kwtsms.com — insufficient credits will cause ERR010 or ERR011.', 'wp-kwtsms-otp' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'Users get "Session expired" error', 'wp-kwtsms-otp' ); ?></h3>
		<p><?php esc_html_e( 'The OTP session is stored as a 15-minute transient. This can be cleared by object cache flushes or plugin conflicts. Check that no caching plugin is clearing transients too aggressively.', 'wp-kwtsms-otp' ); ?></p>

		<h3><?php esc_html_e( 'Where is the debug log?', 'wp-kwtsms-otp' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: %s: path to debug log file */
				esc_html__( 'When Debug Logging is enabled: %s', 'wp-kwtsms-otp' ),
				'<code>' . esc_html( $content_dir . '/kwtsms-debug.log' ) . '</code>'
			);
			?>
			<br>
			<?php esc_html_e( 'In Test Mode, OTP codes are also written to: wp-content/plugins/wp-kwtsms-otp/test-otp.log', 'wp-kwtsms-otp' ); ?>
		</p>

		<h3><?php esc_html_e( 'Common API error codes', 'wp-kwtsms-otp' ); ?></h3>
		<table class="widefat striped" style="max-width:700px;font-size:13px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Code', 'wp-kwtsms-otp' ); ?></th>
					<th><?php esc_html_e( 'Meaning', 'wp-kwtsms-otp' ); ?></th>
					<th><?php esc_html_e( 'Fix', 'wp-kwtsms-otp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td>ERR003</td><td><?php esc_html_e( 'Authentication failed', 'wp-kwtsms-otp' ); ?></td><td><?php esc_html_e( 'Wrong username or password. Verify at kwtsms.com.', 'wp-kwtsms-otp' ); ?></td></tr>
				<tr><td>ERR008</td><td><?php esc_html_e( 'Sender ID not allowed', 'wp-kwtsms-otp' ); ?></td><td><?php esc_html_e( 'Selected Sender ID is not approved. Choose a different one.', 'wp-kwtsms-otp' ); ?></td></tr>
				<tr><td>ERR010/011</td><td><?php esc_html_e( 'Insufficient credits', 'wp-kwtsms-otp' ); ?></td><td><?php esc_html_e( 'Top up your kwtSMS account balance.', 'wp-kwtsms-otp' ); ?></td></tr>
				<tr><td>ERR033</td><td><?php esc_html_e( 'No SMS coverage', 'wp-kwtsms-otp' ); ?></td><td><?php esc_html_e( 'Add coverage for the destination country in your kwtSMS account.', 'wp-kwtsms-otp' ); ?></td></tr>
				<tr><td>ERR006/025</td><td><?php esc_html_e( 'Invalid phone number', 'wp-kwtsms-otp' ); ?></td><td><?php esc_html_e( 'The phone number format is wrong. Ensure country code is included.', 'wp-kwtsms-otp' ); ?></td></tr>
			</tbody>
		</table>

		<!-- ===== Support ===== -->
		<h2><?php esc_html_e( 'Support', 'wp-kwtsms-otp' ); ?></h2>
		<ul style="font-size:14px;line-height:2;">
			<li>
				<strong><?php esc_html_e( 'kwtSMS API Documentation:', 'wp-kwtsms-otp' ); ?></strong>
				<a href="https://www.kwtsms.com/api-documentation/" target="_blank" rel="noopener">kwtsms.com/api-documentation</a>
			</li>
			<li>
				<strong><?php esc_html_e( 'kwtSMS Support:', 'wp-kwtsms-otp' ); ?></strong>
				<a href="https://www.kwtsms.com/contact/" target="_blank" rel="noopener">kwtsms.com/contact</a>
			</li>
			<li>
				<strong><?php esc_html_e( 'kwtSMS Dashboard (balance, coverage, sender IDs):', 'wp-kwtsms-otp' ); ?></strong>
				<a href="https://www.kwtsms.com/login/" target="_blank" rel="noopener">kwtsms.com/login</a>
			</li>
		</ul>

		<p style="color:#757575;font-size:12px;margin-top:24px;">
			<?php
			printf(
				/* translators: %s: plugin version */
				esc_html__( 'Plugin version: %s', 'wp-kwtsms-otp' ),
				esc_html( defined( 'KWTSMS_OTP_VERSION' ) ? KWTSMS_OTP_VERSION : '—' )
			);
			?>
		</p>
	</div>
</div>
