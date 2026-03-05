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
$credentials_verified = (bool) $settings->get( 'gateway.credentials_verified', false );
$has_credentials      = $credentials_verified
	&& ! empty( $settings->get( 'gateway.api_username', '' ) )
	&& ! empty( $settings->get( 'gateway.api_password', '' ) );
$has_sender      = $credentials_verified && ! empty( $settings->get( 'gateway.sender_id', '' ) );
$test_mode       = (bool) $settings->get( 'gateway.test_mode', 1 );
$debug_logging   = (bool) $settings->get( 'general.debug_logging', 0 );
// Relative content path (e.g. "wp-content") for display — avoids showing full server paths.
$content_dir     = ( defined( 'ABSPATH' ) && defined( 'WP_CONTENT_DIR' ) )
	? rtrim( str_replace( trailingslashit( ABSPATH ), '', WP_CONTENT_DIR ), '/' )
	: 'wp-content';
?>
<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'Help &amp; Support', 'wp-kwtsms' ); ?></h1>
	</div>

	<!-- ===== Plugin Status ===== -->
	<div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px 20px;margin-bottom:24px;max-width:800px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Current Status', 'wp-kwtsms' ); ?></h2>
		<table style="border-collapse:collapse;width:100%;font-size:13px;">
			<tr>
				<td style="padding:6px 0;width:200px;"><strong><?php esc_html_e( 'API Credentials', 'wp-kwtsms' ); ?></strong></td>
				<td>
					<?php if ( $has_credentials ) : ?>
					<span style="color:#46b450;">&#10003; <?php esc_html_e( 'Configured', 'wp-kwtsms' ); ?></span>
					<?php else : ?>
					<span style="color:#dc3232;">&#10007; <?php esc_html_e( 'Not configured', 'wp-kwtsms' ); ?></span>,
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway Settings ', 'wp-kwtsms' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Sender ID', 'wp-kwtsms' ); ?></strong></td>
				<td>
					<?php if ( $has_sender ) : ?>
					<span style="color:#46b450;">&#10003; <?php echo esc_html( $settings->get( 'gateway.sender_id', '' ) ); ?></span>
					<?php else : ?>
					<span style="color:#dc3232;">&#10007; <?php esc_html_e( 'Not selected', 'wp-kwtsms' ); ?></span>,
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway Settings ', 'wp-kwtsms' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Test Mode', 'wp-kwtsms' ); ?></strong></td>
				<td>
					<?php if ( $test_mode ) : ?>
					<span style="color:#FFA200;font-weight:600;"><?php esc_html_e( 'ON, no real SMS is sent', 'wp-kwtsms' ); ?></span>
					<?php else : ?>
					<span style="color:#46b450;"><?php esc_html_e( 'OFF, live SMS delivery', 'wp-kwtsms' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Debug Logging', 'wp-kwtsms' ); ?></strong></td>
				<td>
					<?php if ( $debug_logging ) : ?>
					<span style="color:#FFA200;font-weight:600;"><?php esc_html_e( 'ON', 'wp-kwtsms' ); ?></span>,
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-logs&tab=debug_log' ) ); ?>"><?php echo esc_html( $content_dir . '/kwtsms-debug.log' ); ?></a>
					<?php else : ?>
					<span style="color:#757575;"><?php esc_html_e( 'OFF', 'wp-kwtsms' ); ?></span>,
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp' ) ); ?>"><?php esc_html_e( 'Enable in General Settings ', 'wp-kwtsms' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Account Balance', 'wp-kwtsms' ); ?></strong></td>
				<td>
					<?php
					$bal_available = $settings->get( 'gateway.balance_available', null );
					$bal_updated   = (int) $settings->get( 'gateway.balance_updated_at', 0 );
					if ( $credentials_verified && null !== $bal_available ) :
					?>
					<span style="color:#46b450;font-weight:600;">
						<?php echo esc_html( number_format( (float) $bal_available, 2 ) ); ?>
					</span>
					<?php if ( $bal_updated > 0 ) : ?>
					<span style="color:#888;font-size:12px;">
						<?php printf(
							/* translators: %s: human-readable time difference */
							esc_html__( '(updated %s ago)', 'wp-kwtsms' ),
							esc_html( human_time_diff( $bal_updated, time() ) )
						); ?>
					</span>
					<?php endif; ?>
					, <a href="https://www.kwtsms.com/login/" target="_blank" rel="noopener" style="font-weight:600;"><?php esc_html_e( 'Recharge/Buy credits ', 'wp-kwtsms' ); ?></a>
					<?php else : ?>
					<span style="color:#888;"><?php esc_html_e( 'Not available, login on the Gateway page first.', 'wp-kwtsms' ); ?></span>,
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway Settings ', 'wp-kwtsms' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td style="padding:6px 0;"><strong><?php esc_html_e( 'Plugin Version', 'wp-kwtsms' ); ?></strong></td>
				<td><?php echo esc_html( defined( 'KWTSMS_OTP_VERSION' ) ? KWTSMS_OTP_VERSION : '—' ); ?></td>
			</tr>
		</table>
	</div>

		<!-- ===== Support ===== -->
		<h2><?php esc_html_e( 'Support &amp; Resources', 'wp-kwtsms' ); ?></h2>
		<ul style="font-size:14px;line-height:2;">
			<li>
				<strong><?php esc_html_e( 'kwtSMS FAQ:', 'wp-kwtsms' ); ?></strong>
				<a href="https://www.kwtsms.com/faq/" target="_blank" rel="noopener">kwtsms.com/faq/</a>,
				<?php esc_html_e( 'answers to common questions about credits, sender IDs, OTP, and delivery.', 'wp-kwtsms' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'kwtSMS Support:', 'wp-kwtsms' ); ?></strong>
				<a href="https://www.kwtsms.com/support.html" target="_blank" rel="noopener">kwtsms.com/support.html</a>,
				<?php esc_html_e( 'open a support ticket or browse help articles.', 'wp-kwtsms' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Contact kwtSMS:', 'wp-kwtsms' ); ?></strong>
				<a href="https://www.kwtsms.com/#contact" target="_blank" rel="noopener">kwtsms.com/#contact</a>,
				<?php esc_html_e( 'reach the kwtSMS team directly for Sender ID registration and account issues.', 'wp-kwtsms' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'kwtSMS API Documentation:', 'wp-kwtsms' ); ?></strong>
				<a href="https://www.kwtsms.com/doc/KwtSMS.com_API_Documentation_v41.pdf" target="_blank" rel="noopener">KwtSMS API v4.1 (PDF)</a>
			</li>
			<li>
				<strong><?php esc_html_e( 'kwtSMS Dashboard (balance, coverage, sender IDs):', 'wp-kwtsms' ); ?></strong>
				<a href="https://www.kwtsms.com/login/" target="_blank" rel="noopener">kwtsms.com/login</a>
			</li>
			<li>
				<strong><?php esc_html_e( 'kwtSMS Integrations:', 'wp-kwtsms' ); ?></strong>
				<a href="https://www.kwtsms.com/integrations.html" target="_blank" rel="noopener">kwtsms.com/integrations.html</a>,
				<?php esc_html_e( 'other platforms and integrations supported by kwtSMS.', 'wp-kwtsms' ); ?>
			</li>
		</ul>


	<!-- ===== Getting Started ===== -->
	<div style="max-width:800px;">
		<h2><?php esc_html_e( 'Getting Started', 'wp-kwtsms' ); ?></h2>
		<ol style="font-size:14px;line-height:1.8;">
			<li>
				<strong><?php esc_html_e( 'Create a kwtSMS account', 'wp-kwtsms' ); ?></strong><br>
				<?php esc_html_e( 'Go to kwtsms.com, sign up, log in, and request API access. Your API username and password will be provided in your account dashboard.', 'wp-kwtsms' ); ?>
				<a href="https://www.kwtsms.com/signup" target="_blank" rel="noopener"><?php esc_html_e( 'Sign up ', 'wp-kwtsms' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Register a Sender ID', 'wp-kwtsms' ); ?></strong><br>
				<?php esc_html_e( 'A Sender ID is the name or number your recipients see. Apply for one in your kwtSMS dashboard. This is required before sending.', 'wp-kwtsms' ); ?>
				<a href="https://www.kwtsms.com/sender-id-help.html" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more ', 'wp-kwtsms' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Configure Gateway Settings', 'wp-kwtsms' ); ?></strong><br>
				<?php esc_html_e( 'Go to kwtSMS  Gateway. Enter your API username and password, then click "Login" to verify your credentials and load your approved Sender IDs. Select a Sender ID and save.', 'wp-kwtsms' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-gateway' ) ); ?>"><?php esc_html_e( 'Go to Gateway ', 'wp-kwtsms' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Set up General Settings', 'wp-kwtsms' ); ?></strong><br>
				<?php esc_html_e( 'Choose your OTP mode (2FA, Passwordless, or Both), code length, expiry, and default country for the dial-code dropdown.', 'wp-kwtsms' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp' ) ); ?>"><?php esc_html_e( 'Go to General ', 'wp-kwtsms' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Add phone numbers to user profiles', 'wp-kwtsms' ); ?></strong><br>
				<?php esc_html_e( 'Each user must have a phone number in their profile (Users  Edit User  Phone Number).', 'wp-kwtsms' ); ?>
				<span style="color:#d63638;font-weight:600;"><?php esc_html_e( 'Without a phone number, 2FA is skipped for that user.', 'wp-kwtsms' ); ?></span>
			</li>
			<li>
				<strong><?php esc_html_e( 'Send a test SMS', 'wp-kwtsms' ); ?></strong><br>
				<?php esc_html_e( 'On the Gateway page, enter a test phone number and click "Send Test SMS". With Test Mode ON, the message is queued in your kwtSMS account but not delivered to the phone. Turn Test Mode OFF for real delivery.', 'wp-kwtsms' ); ?>
			</li>
		</ol>

		<!-- ===== Test Mode & Credits ===== -->
		<div style="background:#fff8e1;border-left:4px solid #FFA200;padding:14px 18px;border-radius:0 4px 4px 0;margin-bottom:24px;font-size:14px;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Test Mode and Credits: Important', 'wp-kwtsms' ); ?></h3>
			<p style="margin-top:0;">
				<?php esc_html_e( 'When Test Mode is ON, every message is sent to the kwtSMS API with a test=1 flag. The kwtSMS servers receive and queue the message, but it is never delivered to the recipient\'s phone.', 'wp-kwtsms' ); ?>
				<strong><?php esc_html_e( ' Credits are still deducted', 'wp-kwtsms' ); ?></strong>,
				<?php esc_html_e( 'kwtSMS charges for queued messages even in test mode.', 'wp-kwtsms' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'To recover credits from test messages, log in to your kwtSMS dashboard and delete the queued messages from the outbox queue.', 'wp-kwtsms' ); ?>
				<a href="https://www.kwtsms.com/login/" target="_blank" rel="noopener"><?php esc_html_e( 'kwtSMS Dashboard ', 'wp-kwtsms' ); ?></a>
			</p>
			<p style="margin-bottom:0;">
				<strong><?php esc_html_e( 'How to tell:', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'An orange "Test Mode" notice appears at the top of every kwtSMS admin page when active. To disable it, go to Gateway Settings and uncheck Test Mode.', 'wp-kwtsms' ); ?>
			</p>
		</div>

		<!-- ===== Features ===== -->
		<h2><?php esc_html_e( 'Features Overview', 'wp-kwtsms' ); ?></h2>

		<h3><?php esc_html_e( 'OTP Two-Factor Authentication (2FA)', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'After a user enters their password, an OTP code is sent to their registered phone. They must enter the code to complete login. Configure code length (4 or 6 digits), expiry (1–30 min), and max wrong attempts in General Settings.', 'wp-kwtsms' ); ?></p>

		<h3><?php esc_html_e( 'Passwordless Login', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Users can log in with just their phone number, no password needed. An OTP is sent to their phone and they are logged in on success. Enable via OTP Mode  Passwordless or Both. A country-code dropdown with GeoIP pre-selection is shown on the login form.', 'wp-kwtsms' ); ?></p>

		<h3><?php esc_html_e( 'Password Reset via SMS', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Replaces the default email reset link with an SMS OTP. Users receive a code by SMS to verify their identity, then are taken to the reset form. Enable via General Settings  Enable Password Reset OTP.', 'wp-kwtsms' ); ?></p>

		<h3><?php esc_html_e( 'Per-Role OTP Enforcement', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Choose which user roles require OTP (e.g. require it for Administrators but skip it for Subscribers). Excluded roles bypass OTP entirely. Configure under General Settings  Authentication.', 'wp-kwtsms' ); ?></p>

		<h3><?php esc_html_e( 'SMS Templates', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Customise the message text for each event (login, reset, welcome). Placeholders like {otp}, {site_name}, and {expiry_minutes} are replaced automatically. Separate English and Arabic templates are supported.', 'wp-kwtsms' ); ?></p>

		<h3><?php esc_html_e( 'WooCommerce Integration', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'When WooCommerce is active, the plugin can send SMS to customers when order status changes. Supported statuses: Processing, Shipped (On-Hold), Completed, Cancelled, Pending Payment, Refunded, and Failed. Each status has its own configurable template (English + Arabic). Additional features:', 'wp-kwtsms' ); ?></p>
		<ul style="margin-left:20px;font-size:14px;line-height:1.8;">
			<li><?php esc_html_e( 'Admin SMS notifications: send a copy to a store phone number on any status change.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Per-order custom SMS: send a custom message from the order edit screen.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Checkout OTP gate: require phone verification before an order is placed.', 'wp-kwtsms' ); ?></li>
		</ul>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-integrations' ) ); ?>"><?php esc_html_e( 'Integrations Settings ', 'wp-kwtsms' ); ?></a></p>

		<h3><?php esc_html_e( 'Form Integrations (Contact Form 7, WPForms, Elementor Pro, Gravity Forms, Ninja Forms)', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Each form plugin integration supports two modes:', 'wp-kwtsms' ); ?></p>
		<ul style="margin-left:20px;font-size:14px;line-height:1.8;">
			<li><strong><?php esc_html_e( 'Notification mode', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Send a confirmation SMS to the customer after a successful form submission.', 'wp-kwtsms' ); ?></li>
			<li><strong><?php esc_html_e( 'OTP Gate mode', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Block the form submission until the user verifies their phone number with an OTP. An overlay modal appears on submit, asking the user to enter and confirm their phone.', 'wp-kwtsms' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'Security', 'wp-kwtsms' ); ?></h3>
		<ul style="margin-left:20px;font-size:14px;line-height:1.8;">
			<li><strong><?php esc_html_e( 'Sliding-window rate limiting', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'OTP requests are limited per phone, per IP, and per account. The sliding-window algorithm prevents gaming at window boundaries.', 'wp-kwtsms' ); ?></li>
			<li><strong><?php esc_html_e( 'Phone blocking list', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Block specific numbers from ever receiving an OTP. Blocked numbers receive a silent success response (anti-enumeration).', 'wp-kwtsms' ); ?></li>
			<li><strong><?php esc_html_e( 'Bot protection', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Optional Google reCAPTCHA v3 or Cloudflare Turnstile on OTP forms.', 'wp-kwtsms' ); ?></li>
			<li><strong><?php esc_html_e( 'Emergency bypass', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Add define( \'KWTSMS_OTP_DISABLED\', true ) to wp-config.php to disable all OTP logic if you are locked out.', 'wp-kwtsms' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'Logs', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'The Logs page shows three tabs:', 'wp-kwtsms' ); ?></p>
		<ul style="margin-left:20px;font-size:14px;">
			<li><strong><?php esc_html_e( 'SMS History', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Full unredacted log of all SMS sends (phone, message, status, message ID, gateway result).', 'wp-kwtsms' ); ?></li>
			<li><strong><?php esc_html_e( 'OTP Attempts', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Every verification attempt with result, IP address, and user. Useful for detecting brute-force attacks.', 'wp-kwtsms' ); ?></li>
			<li><strong><?php esc_html_e( 'Debug Log', 'wp-kwtsms' ); ?></strong>: <?php esc_html_e( 'Full API request/response log (visible only when Debug Logging is enabled in General Settings).', 'wp-kwtsms' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'Allowed Countries', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'In General Settings you can restrict which countries are shown in the dial-code dropdown and accepted for OTP. Default is GCC countries. This prevents OTPs from being sent to unintended regions.', 'wp-kwtsms' ); ?></p>

		<!-- ===== Collecting Phone Numbers ===== -->
		<h2><?php esc_html_e( 'How to Collect Phone Numbers from Users', 'wp-kwtsms' ); ?></h2>
		<p>
			<?php esc_html_e( 'The plugin sends OTP codes and SMS notifications to the phone number stored in each user\'s profile. Without a phone number on file, 2FA is silently bypassed and no SMS is ever sent to that user.', 'wp-kwtsms' ); ?>
			<span style="color:#d63638;font-weight:600;"><?php esc_html_e( 'Make sure every user has a phone number before enabling 2FA.', 'wp-kwtsms' ); ?></span>
		</p>
		<p><?php esc_html_e( 'Choose the collection method that matches how your users join your site:', 'wp-kwtsms' ); ?></p>

		<h3><?php esc_html_e( 'Method 1: WooCommerce Registration (recommended for WooCommerce stores)', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'When WooCommerce is active, the plugin automatically adds a Phone Number field to the WooCommerce My Account registration form and to checkout. The number is saved to the user profile on account creation, no extra steps needed.', 'wp-kwtsms' ); ?></p>
		<ol>
			<li><?php esc_html_e( 'Enable the WooCommerce integration: Integrations  WooCommerce  Enable WooCommerce SMS Integration.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Phone collection is active automatically on the My Account registration form and checkout page.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Test by registering a new account on /my-account and verifying the phone appears under Users  Edit User  Phone Number.', 'wp-kwtsms' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'Method 2: Manual entry by the admin', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'You can add or update a phone number for any existing user directly in the WordPress admin panel.', 'wp-kwtsms' ); ?></p>
		<ol>
			<li><?php esc_html_e( 'Go to Users in the WordPress admin menu.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Click Edit under the user\'s name.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Scroll down to the Phone Number field (added by this plugin).', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Enter the number with country code, e.g. 96599123456 for Kuwait.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Click Update User to save.', 'wp-kwtsms' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'Method 3: Ask users to update their own profile', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Users can add their own phone number from the front-end WordPress profile page.', 'wp-kwtsms' ); ?></p>
		<ol>
			<li><?php esc_html_e( 'Direct users to their profile page: /wp-admin/profile.php (or the equivalent front-end profile page if your theme provides one).', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'They will see a Phone Number field. Ask them to enter their number with country code.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'They click Update Profile to save.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Once saved, 2FA and SMS notifications will work for that user on their next login.', 'wp-kwtsms' ); ?></li>
		</ol>

		<div style="background:#e7f5ff;border-left:4px solid #72aee6;padding:14px 18px;border-radius:0 4px 4px 0;margin-bottom:24px;font-size:14px;">
			<strong><?php esc_html_e( 'Tip: Check who is missing a phone number', 'wp-kwtsms' ); ?></strong><br>
			<?php esc_html_e( 'Go to Users in the admin, then look for the Phone Number column. Any user showing "—" or a blank value has no phone on file and will bypass 2FA until one is added.', 'wp-kwtsms' ); ?>
			<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'View all users ', 'wp-kwtsms' ); ?></a>
		</div>

		<!-- ===== Styling ===== -->
		<h2><?php esc_html_e( 'Styling &amp; Customisation', 'wp-kwtsms' ); ?></h2>
		<p>
			<?php esc_html_e( 'The OTP and passwordless login pages use the plugin\'s own stylesheet (assets/css/login.css). This stylesheet is intentionally minimal, it uses standard WordPress blue (#2271b1) for interactive elements and inherits the base WordPress login page layout. It does NOT override your theme colours or fonts.', 'wp-kwtsms' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'To customise the appearance, you do NOT need a customisation page. Simply add CSS overrides to your theme\'s Additional CSS (Appearance  Customise  Additional CSS) or your child theme\'s style.css. Key selectors:', 'wp-kwtsms' ); ?>
		</p>
		<ul style="margin-left:20px;font-size:13px;line-height:2;font-family:monospace;background:#f8f8f8;padding:10px 20px;border:1px solid #ddd;border-radius:4px;">
			<li>.kwtsms-otp-wrap: <?php esc_html_e( 'outer container of OTP entry form', 'wp-kwtsms' ); ?></li>
			<li>.kwtsms-form-card: <?php esc_html_e( 'white card around the form', 'wp-kwtsms' ); ?></li>
			<li>.kwtsms-otp-input: <?php esc_html_e( 'the OTP code input field', 'wp-kwtsms' ); ?></li>
			<li>.kwtsms-submit-btn: <?php esc_html_e( 'the Submit / Verify button', 'wp-kwtsms' ); ?></li>
			<li>.kwtsms-phone-group: <?php esc_html_e( 'country code + phone input wrapper', 'wp-kwtsms' ); ?></li>
			<li>.kwtsms-powered-by: <?php esc_html_e( '"SMS by kwtSMS.com" footer', 'wp-kwtsms' ); ?></li>
		</ul>
		<p>
			<?php esc_html_e( 'No customisation page is needed. Full CSS control is available through standard WordPress/theme overrides.', 'wp-kwtsms' ); ?>
		</p>

		<!-- ===== Troubleshooting ===== -->
		<h2><?php esc_html_e( 'Troubleshooting', 'wp-kwtsms' ); ?></h2>

		<h3><?php esc_html_e( 'Messages not being delivered: step by step', 'wp-kwtsms' ); ?></h3>
		<ol style="font-size:14px;line-height:1.9;">
			<li>
				<strong><?php esc_html_e( 'Check whether Test Mode is ON.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'Look for the orange "kwtSMS is in Test Mode" notice at the top of this page. If it appears, messages are being queued but not delivered. Go to Gateway Settings, uncheck Test Mode, and save. Credits are consumed even in test mode, delete queued messages from the kwtSMS dashboard to recover them.', 'wp-kwtsms' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Verify your API credentials.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'On the Gateway page, click Login. If it fails, your API username or password is incorrect. Log in to kwtsms.com  API Settings to get the correct credentials.', 'wp-kwtsms' ); ?>
				<a href="https://www.kwtsms.com/login/" target="_blank" rel="noopener"><?php esc_html_e( 'kwtSMS Dashboard ', 'wp-kwtsms' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Confirm a Sender ID is selected.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'The Sender ID dropdown on the Gateway page must have a selection. If it is empty, click Reload after verifying credentials. Without a Sender ID, no message can be sent.', 'wp-kwtsms' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Check your account balance.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'You need at least 1 credit per message. Your current balance is shown on the Gateway page and in the Current Status table above. Insufficient credits cause error ERR010 or ERR011.', 'wp-kwtsms' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Check the SMS History log.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'Go to Logs  SMS History. If the send attempt is listed with Status: Failed, the Result column shows the API error code. Match it to the error table below.', 'wp-kwtsms' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-logs&tab=sms_history' ) ); ?>"><?php esc_html_e( 'SMS History ', 'wp-kwtsms' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Enable Debug Logging for full API details.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'Go to General  Developer Tools and turn Debug Logging ON. Trigger a send again. Then view the full request and response (including the exact error from kwtSMS) in the Debug Log.', 'wp-kwtsms' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp-logs&tab=debug_log' ) ); ?>"><?php esc_html_e( 'Debug Log ', 'wp-kwtsms' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Check destination coverage.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'The SMS Coverage table on the Gateway page lists which countries are supported. If the destination country is not covered or shows as inactive, add coverage via your kwtSMS dashboard.', 'wp-kwtsms' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Run a Gateway Test SMS.', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'On the Gateway page, enter a phone number including country code and click "Send Test SMS". This isolates whether the issue is the API connection or a specific trigger in the plugin.', 'wp-kwtsms' ); ?>
			</li>
		</ol>

		<!-- KWT-SMS promotional sender ID warning -->
		<div style="background:#fef0f0;border-left:4px solid #d63638;padding:14px 18px;border-radius:0 4px 4px 0;margin:16px 0 24px;font-size:14px;">
			<h3 style="margin-top:0;color:#d63638;"><?php esc_html_e( '⛔ KWT-SMS Promotional Sender ID: For Testing Only. Do Not Use in Production', 'wp-kwtsms' ); ?></h3>
			<p style="margin-top:0;">
				<?php esc_html_e( 'The shared "KWT-SMS" sender ID is a public promotional channel. It is only suitable for initial testing while you are setting up the plugin. It must not be used in a live production site.', 'wp-kwtsms' ); ?>
			</p>
			<ul style="margin-left:20px;line-height:1.9;margin-bottom:12px;">
				<li>
					<strong><?php esc_html_e( 'Severe delivery delays:', 'wp-kwtsms' ); ?></strong>
					<?php esc_html_e( 'Promotional sender IDs are lower priority by design. Delivery can take 120 seconds or more, far too slow for OTP codes that users expect in seconds.', 'wp-kwtsms' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Virgin Mobile (Zain-MVNO) numbers never receive the message:', 'wp-kwtsms' ); ?></strong>
					<?php esc_html_e( 'Kuwait numbers starting with 4 (Virgin subscribers) do not receive messages from promotional sender IDs at all. Those users will never get an OTP.', 'wp-kwtsms' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Do Not Disturb (DND) filters:', 'wp-kwtsms' ); ?></strong>
					<?php esc_html_e( 'Promotional messages are blocked for users who have enabled DND on their number, causing lost credits and undelivered OTPs.', 'wp-kwtsms' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Brand damage:', 'wp-kwtsms' ); ?></strong>
					<?php esc_html_e( 'Recipients see "KWT-SMS" as the sender, not your business name. This reduces trust and makes messages look like spam.', 'wp-kwtsms' ); ?>
				</li>
			</ul>
			<p style="margin-bottom:4px;">
				<strong><?php esc_html_e( 'What to do:', 'wp-kwtsms' ); ?></strong>
				<?php esc_html_e( 'Register a private alphanumeric Sender ID in your kwtSMS account using your brand name (e.g. "MyShop"). Private sender IDs have fast delivery, reach all Kuwaiti operators including Virgin, bypass DND filters, and build customer trust.', 'wp-kwtsms' ); ?>
			</p>
			<p style="margin-bottom:0;">
				<a href="https://www.kwtsms.com/faq/must-have-senderid-for-otp.html" target="_blank" rel="noopener"><?php esc_html_e( 'Why you need a private Sender ID for OTP ', 'wp-kwtsms' ); ?></a>
				&nbsp;&middot;&nbsp;
				<a href="https://www.kwtsms.com/#contact" target="_blank" rel="noopener"><?php esc_html_e( 'Contact kwtSMS to register your Sender ID ', 'wp-kwtsms' ); ?></a>
			</p>
		</div>

		<!-- Kuwait DLR note -->
		<h3><?php esc_html_e( 'Message shows as Sent but was never delivered (Kuwait numbers)', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Delivery Reports (DLR) are not available for messages sent to Kuwait mobile numbers. The API returns "OK" (sent) as soon as the message is handed off to the operator, but there is no delivery confirmation for Kuwait. If a customer says they did not receive the SMS and the Logs page shows "Sent", check:', 'wp-kwtsms' ); ?></p>
		<ul style="margin-left:20px;font-size:14px;line-height:1.8;">
			<li><?php esc_html_e( 'Is the customer using Virgin (Zain-MVNO)? If so, switch from KWT-SMS to a private sender ID.', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Check the kwtSMS API error log in your kwtSMS account dashboard (API  Error Log).', 'wp-kwtsms' ); ?></li>
			<li><?php esc_html_e( 'Use the Debug Log (enable in General  Developer Tools) for the full API request and response.', 'wp-kwtsms' ); ?></li>
		</ul>

		<!-- International coverage -->
		<h3><?php esc_html_e( 'Messages not reaching international numbers', 'wp-kwtsms' ); ?></h3>
		<p>
			<?php esc_html_e( 'International SMS coverage is disabled by default on all new kwtSMS accounts. To enable coverage for countries outside Kuwait, contact kwtSMS support.', 'wp-kwtsms' ); ?>
			<a href="https://www.kwtsms.com/#contact" target="_blank" rel="noopener"><?php esc_html_e( 'kwtSMS Contact ', 'wp-kwtsms' ); ?></a>
		</p>
		<p><?php esc_html_e( 'The Gateway page shows your current coverage list. If a destination country is missing, it has not been enabled.', 'wp-kwtsms' ); ?></p>

		<!-- API rate limiting -->
		<h3><?php esc_html_e( 'API requests being blocked (rate limit)', 'wp-kwtsms' ); ?></h3>
		<p>
			<?php esc_html_e( 'The kwtSMS API allows a maximum of 5 requests per second from a single IP address. Exceeding this limit causes your server\'s IP to be temporarily blocked by kwtSMS. Under normal use this limit is never reached, each OTP send is one request. If you are running bulk sends or automated tests, introduce a delay between requests.', 'wp-kwtsms' ); ?>
		</p>

		<h3><?php esc_html_e( 'Users get "Session expired" error', 'wp-kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'The OTP session is stored as a 15-minute transient. This can be cleared by object cache flushes or plugin conflicts. Check that no caching plugin is clearing transients too aggressively.', 'wp-kwtsms' ); ?></p>

		<h3 style="color:#dc3232;"><?php esc_html_e( '⚠ Admin Lockout: Cannot Receive OTP', 'wp-kwtsms' ); ?></h3>
		<div style="background:#fff8e1;border-left:4px solid #FFA200;padding:12px 16px;border-radius:0 4px 4px 0;margin-bottom:16px;font-size:14px;">
			<p style="margin-top:0;">
				<?php esc_html_e( 'If your admin account has a phone number set and you cannot receive the OTP (lost phone, changed number, gateway issue), you will be locked out of WordPress. Use one of the emergency bypass methods below.', 'wp-kwtsms' ); ?>
			</p>

			<p><strong><?php esc_html_e( 'Option 1: Emergency bypass constant (fastest)', 'wp-kwtsms' ); ?></strong></p>
			<p><?php esc_html_e( 'Add this line to your wp-config.php (before the "stop editing" comment):', 'wp-kwtsms' ); ?></p>
			<pre style="background:#fff;border:1px solid #ddd;padding:8px 12px;font-size:13px;overflow-x:auto;">define( 'KWTSMS_OTP_DISABLED', true );</pre>
			<p><?php esc_html_e( 'This completely disables all OTP logic. Log in normally, fix your phone number or gateway, then remove the line.', 'wp-kwtsms' ); ?></p>

			<p><strong><?php esc_html_e( 'Option 2: Remove phone via WP-CLI', 'wp-kwtsms' ); ?></strong></p>
			<pre style="background:#fff;border:1px solid #ddd;padding:8px 12px;font-size:13px;overflow-x:auto;">wp user meta delete &lt;user_id&gt; kwtsms_phone</pre>
			<p><?php esc_html_e( 'Replace &lt;user_id&gt; with your user ID (usually 1 for the first admin). This removes the phone from your account so OTP is skipped on next login.', 'wp-kwtsms' ); ?></p>

			<p><strong><?php esc_html_e( 'Option 3: Disable the plugin via SFTP/FTP', 'wp-kwtsms' ); ?></strong></p>
			<p style="margin-bottom:0;">
				<?php esc_html_e( 'Connect via SFTP/FTP and rename the plugin folder from wp-kwtsms to _wp-kwtsms. WordPress will deactivate the plugin automatically, allowing normal login. Rename it back to re-enable.', 'wp-kwtsms' ); ?>
			</p>
		</div>

		<h3><?php esc_html_e( 'Where is the debug log?', 'wp-kwtsms' ); ?></h3>
		<p>
			<?php
			$debug_log_url = admin_url( 'admin.php?page=kwtsms-otp-logs&tab=debug_log' );
			printf(
				/* translators: 1: path to debug log file, 2: link to Logs page */
				esc_html__( 'When Debug Logging is enabled, all API activity is recorded in %1$s. You can view, scroll, and download it directly from %2$s.', 'wp-kwtsms' ),
				'<code>' . esc_html( $content_dir . '/kwtsms-debug.log' ) . '</code>',
				'<a href="' . esc_url( $debug_log_url ) . '">' . esc_html__( 'Logs  Debug Log', 'wp-kwtsms' ) . '</a>'
			);
			?>
		</p>

		<h3><?php esc_html_e( 'API error codes', 'wp-kwtsms' ); ?></h3>
		<p style="font-size:13px;"><?php esc_html_e( 'Error codes appear in the SMS History log (Result column) and in the Debug Log. Match them to the table below.', 'wp-kwtsms' ); ?></p>
		<table class="widefat striped" style="max-width:800px;font-size:13px;">
			<thead>
				<tr>
					<th style="width:90px;"><?php esc_html_e( 'Code', 'wp-kwtsms' ); ?></th>
					<th><?php esc_html_e( 'Meaning', 'wp-kwtsms' ); ?></th>
					<th><?php esc_html_e( 'Fix', 'wp-kwtsms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td>ERR001</td><td><?php esc_html_e( 'Service temporarily unavailable', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Retry after a short wait. Check kwtsms.com status if it persists.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR002</td><td><?php esc_html_e( 'Gateway configuration error', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Contact kwtSMS support, account configuration issue.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR003</td><td><?php esc_html_e( 'Authentication failed', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Wrong API username or password. Re-enter credentials on the Gateway page and click Login.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR004</td><td><?php esc_html_e( 'API not enabled on account', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Contact kwtSMS to enable the API on your account.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR005</td><td><?php esc_html_e( 'Account suspended', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Log in to kwtsms.com to check account status.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR006 / ERR025</td><td><?php esc_html_e( 'Invalid phone number', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'The number format is wrong. Ensure country code is included (e.g. 96598765432).', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR008</td><td><?php esc_html_e( 'Sender ID not allowed', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'The selected Sender ID is not approved on your account. Choose a different one on the Gateway page.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR009</td><td><?php esc_html_e( 'Message body is empty', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Check your SMS templates. The OTP or notification template for this event is empty.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR010 / ERR011</td><td><?php esc_html_e( 'Insufficient credits', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Top up your kwtSMS account balance.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR012</td><td><?php esc_html_e( 'Message too long', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Shorten your SMS template. Standard SMS is 160 characters; Arabic is 70 characters per page.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR013</td><td><?php esc_html_e( 'SMS queue is full', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Retry in a few minutes.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR024</td><td><?php esc_html_e( 'Request blocked by security policy', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Your server IP may be rate-limited (max 5 req/sec). Contact kwtSMS support.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR026 / ERR033</td><td><?php esc_html_e( 'No SMS coverage for destination', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Add coverage for this country in your kwtSMS account or contact kwtSMS to enable international coverage.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR027</td><td><?php esc_html_e( 'Unsupported characters in message', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'Remove emoji or special characters from the SMS template.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR028</td><td><?php esc_html_e( 'Resend too fast', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'The API requires at least 15 seconds between OTP sends to the same number.', 'wp-kwtsms' ); ?></td></tr>
				<tr><td>ERR031 / ERR032</td><td><?php esc_html_e( 'Message rejected (policy/spam)', 'wp-kwtsms' ); ?></td><td><?php esc_html_e( 'The message content was flagged. Review your template text and remove any spam-like content.', 'wp-kwtsms' ); ?></td></tr>
			</tbody>
		</table>

	</div>
</div>
