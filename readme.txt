=== kwtSMS: OTP & SMS Notifications ===
Contributors: kwtsms
Tags: sms, otp, authentication, woocommerce, login
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.0.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SMS OTP login, password reset, and WooCommerce order notifications via the kwtSMS gateway. Arabic RTL support included.

== Description ==

**kwtSMS** replaces or supplements WordPress passwords with SMS one-time codes, sends WooCommerce order updates automatically, and lets you verify phone numbers on any contact form, all powered by the [kwtSMS](https://www.kwtsms.com) SMS gateway.

Built for Arabic-speaking markets (Kuwait, Saudi Arabia, UAE, Bahrain, Qatar, Oman) with full RTL admin support and bilingual SMS templates in English and Arabic.

= Authentication =

* **2FA Mode:** users log in with username + password, then confirm with a 6-digit SMS code
* **Passwordless Mode:** users enter their phone number and receive an OTP to log in directly, no password needed
* **Both Modes:** let each user choose their preferred method
* **Password Reset via SMS:** replace the email link with an SMS OTP verification flow
* **Role-Based Enforcement:** configure which user roles must pass OTP (exclude administrators, apply only to customers, etc.)
* **Welcome SMS:** send a customisable welcome message when a new user registers
* **Country Code Dropdown:** restrict the dial-code selector on login forms to GCC countries or a custom list

= WooCommerce Integration =

* **7 order status notifications:** Processing, On-Hold (Shipped), Completed, Cancelled, Pending Payment, Refunded, Failed
* **Admin order notifications:** automatically notify a configurable admin phone number on any order status change
* **Checkout OTP Gate:** require phone verification before the customer can place an order
* **Per-status templates:** independent English + Arabic SMS template for every order status
* **Admin SMS panel:** send a custom free-text SMS to any order's customer from the order edit screen
* HPOS (High-Performance Order Storage) compatible

= Contact Form Integrations =

Each integration supports two modes: **Notification** (send a confirmation SMS on submit) and **OTP Gate** (block submission until the phone number is verified):

* Contact Form 7
* WPForms
* Elementor Pro Forms
* Gravity Forms
* Ninja Forms

= Security =

* Sliding-window rate limiting per phone number, per IP address, and per user account
* Phone blocking list: silently drop OTP requests from blocked numbers (anti-enumeration)
* Attempt lockout after configurable max failures
* Google reCAPTCHA v3 and Cloudflare Turnstile support
* All credentials stored server-side, never output to HTML
* Nonces on every form and AJAX action
* Anti-enumeration: password reset never reveals whether an account exists

= Developer API =

Hooks for custom workflows:

* `kwtsms_otp_before_send`: filter OTP data before sending
* `kwtsms_otp_message`: filter the SMS text
* `kwtsms_otp_phone_number`: filter the normalised phone number
* `kwtsms_otp_verified`: action fired on successful verification
* `kwtsms_otp_send_failed`: action fired on send failure

= External Services =

This plugin connects to the following external services:

**1. kwtSMS API** (required): sends SMS messages.

* Service: [https://www.kwtsms.com](https://www.kwtsms.com)
* API endpoint: `https://www.kwtsms.com/API/`
* Data sent: phone number, message text, API credentials
* When: every time an OTP or notification SMS is dispatched
* Terms of Service: [https://www.kwtsms.com/policy.html](https://www.kwtsms.com/policy.html)
* Privacy Policy: [https://www.kwtsms.com/privacy.html](https://www.kwtsms.com/privacy.html)

A kwtSMS account with SMS credits is required.

**2. ipapi.co** (optional): detects the visitor's country to pre-select the dial-code flag on the phone input.

* Service: [https://ipapi.co](https://ipapi.co)
* Data sent: visitor IP address (no other data)
* When: on the login page when Passwordless or OTP mode is active; result is cached for 24 hours per IP
* Terms of Service: [https://ipapi.co/terms/](https://ipapi.co/terms/)
* Privacy Policy: [https://ipapi.co/privacy/](https://ipapi.co/privacy/)

If ipapi.co is unavailable, the phone input falls back to the default country configured in General Settings. No personal data is stored by the plugin as a result of this call.

**3. Google reCAPTCHA v3** (optional): bot protection on OTP forms. Only active if you enter a reCAPTCHA Site Key in General Settings.

* Service: [https://www.google.com/recaptcha/](https://www.google.com/recaptcha/)
* Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

**4. Cloudflare Turnstile** (optional): alternative bot protection. Only active if you enter a Turnstile Site Key in General Settings.

* Service: [https://www.cloudflare.com/products/turnstile/](https://www.cloudflare.com/products/turnstile/)
* Privacy Policy: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)

= Admin =

* **Users Without Phone** sub-page under the WordPress Users menu: lists all registered users missing a phone number, with a dynamic count badge on the menu item

= Test Mode =

Enable **Test Mode** in the Gateway settings to develop and test without consuming SMS credits. In test mode the API call is made with the `test=1` flag. OTP codes are generated and stored normally, and the code is written to the WordPress debug log so you can complete flows during development.

= Languages =

Ships with English (default) and Arabic translations. The plugin admin UI and all user-facing strings are fully translatable.

== Installation ==

= Method 1: WordPress Plugin Directory (coming soon) =

The plugin has been submitted to the WordPress.org plugin directory and is pending review. Once approved, you will be able to install it directly from inside WordPress:

1. In your WordPress admin, go to **Plugins > Add New Plugin**.
2. Search for **kwtSMS**.
3. Click **Install Now** next to "kwtSMS: OTP & SMS Notifications".
4. Click **Activate**.
5. Proceed to the configuration steps below.

= Method 2: Upload via WordPress Admin =

1. Download the plugin zip file from the [GitHub releases page](https://github.com/boxlinknet/wp-kwtsms/releases).
2. In your WordPress admin, go to **Plugins > Add New Plugin**.
3. Click the **Upload Plugin** button at the top of the page.
4. Click **Choose File**, select the downloaded `wp-kwtsms-x.x.x.zip` file, and click **Install Now**.
5. Click **Activate Plugin**.
6. Proceed to the configuration steps below.

= Method 3: Manual FTP / SFTP Upload =

1. Download the plugin zip file from the [GitHub releases page](https://github.com/boxlinknet/wp-kwtsms/releases).
2. Unzip the file on your computer. You will get a folder named `wp-kwtsms`.
3. Upload the `wp-kwtsms` folder to `/wp-content/plugins/` on your server using an FTP or SFTP client (FileZilla, Cyberduck, etc.).
4. In your WordPress admin, go to **Plugins > Installed Plugins**.
5. Find **kwtSMS: OTP & SMS Notifications** and click **Activate**.
6. Proceed to the configuration steps below.

= Configuration (all methods) =

After activating the plugin:

1. Go to **kwtSMS > Gateway** in the WordPress admin menu.
2. Enter your **kwtSMS API Username** and **API Password** (find these in your kwtSMS account under Account > API Settings, not your login password).
3. Click **Save & Verify Credentials**. The plugin calls the kwtSMS API to validate your credentials and populate the Sender ID dropdown.
4. Select your **Sender ID** from the dropdown and click **Save Settings**.
5. Go to **kwtSMS > General** and select your OTP mode: **2FA** (password + SMS code), **Passwordless** (phone number + SMS code only), or **Both** (let each user choose).
6. Go to **kwtSMS > Templates** to customize your SMS messages in English and Arabic.

= WooCommerce Order Notifications =

Go to **kwtSMS > Integrations > WooCommerce** and enable the order status notifications you want. Each status has its own English and Arabic SMS template.

= Test Mode =

Before going live, enable **Test Mode** in Gateway Settings. In test mode the plugin sends the API request with `test=1` so no real SMS is delivered and no credits are consumed. The OTP code is written to `wp-content/debug.log` so you can complete full authentication flows during development. Disable Test Mode when you are ready to go live.

== Frequently Asked Questions ==

= Do I need a kwtSMS account? =

Yes. You need an active kwtSMS account with API access. Sign up at [kwtsms.com](https://www.kwtsms.com). API credentials (username and password) are entered in the Gateway settings page.

= What is the difference between Test Mode and Live Mode? =

In **Test Mode** (enabled in Gateway Settings), the plugin calls the kwtSMS API with the `test=1` flag. The SMS is queued on the kwtSMS server but never delivered to the handset, and no credits are consumed. The OTP code is written to `wp-content/debug.log` so you can complete authentication flows during development without a real phone. In **Live Mode** (test mode off), the SMS is delivered to the recipient's phone and credits are deducted. Always develop with Test Mode on, then disable it before going live.

= Does the plugin work without WooCommerce? =

Yes. WooCommerce is fully optional. All login, password reset, and contact form features work on any WordPress site.

= Which contact form plugins are supported? =

Contact Form 7, WPForms, Elementor Pro (Forms widget), Gravity Forms, and Ninja Forms. Each integration has its own settings page with independent enable/mode controls and SMS templates.

= What phone number format should users enter? =

International format with country code, no leading + or 00. For example, a Kuwaiti number would be `96598765432`. The plugin automatically strips leading `+` or `00`, removes spaces and dashes, and converts Arabic/Hindi numerals to Latin digits.

= Can I restrict OTP to specific user roles? =

Yes. In General > OTP Required Roles, select which roles must pass OTP. Administrators are excluded by default.

= What happens if a user does not have a phone number on their account? =

The admin sees a notice in the user profile. The user is prompted to add a phone number before SMS features activate for their account. Password reset falls back to the standard email flow.

= Is the plugin HTTPS-only? =

The plugin works over HTTP, but sends an admin notice recommending HTTPS for security. The kwtSMS API endpoint is always called over HTTPS regardless of your site configuration.

= How do I test without sending real SMS messages? =

Enable **Test Mode** in Gateway Settings. With test mode on, the API receives `test=1` and does not deliver the message. No SMS credits are consumed. The OTP code is written to the WordPress debug log (`wp-content/debug.log`) so you can complete the flow.

= What is the OTP Gate mode for contact forms? =

In OTP Gate mode, the form submission is blocked until the user verifies their phone number via SMS. The verification token is validated server-side before the form data is processed. It cannot be bypassed by manipulating the front end.

= Can I customize the SMS message? =

Yes. Go to **kwtSMS > Templates**. Each template has a separate English and Arabic textarea. Supported placeholders (like `{otp}`, `{site_name}`, `{expiry_minutes}`) are listed below each field. A live character counter shows how many SMS pages the message will use.

= Does the plugin support Arabic SMS? =

Yes. Arabic templates are stored separately. The plugin detects the WordPress site language (`get_locale()`) and sends the Arabic template when the locale starts with `ar_`. The admin template editor has a right-to-left textarea for Arabic.

= How does rate limiting work? =

The plugin tracks OTP requests per phone number and per IP address using WordPress transients. By default, a phone can request a maximum of 3 OTPs per 10-minute window. Failed verification attempts are counted separately and trigger a timed lockout after the configured maximum (default: 3 attempts).

= How do I unlock an admin who is locked out due to OTP? =

Add this line to your `wp-config.php`:

`define( 'KWTSMS_OTP_DISABLED', true );`

This bypasses all OTP checks and restores the standard WordPress login. Remove the line once you have regained access.

= Where is plugin data stored? =

All settings are in `wp_options`. Phone numbers are in `wp_usermeta`. OTP tokens and rate-limit counters use WordPress transients (stored in `wp_options` or object cache). The plugin has an `uninstall.php` that removes all data on deletion.

= My SMS status shows OK but the recipient did not receive the message. What happened? =

Check the Sending Queue at [kwtsms.com](https://www.kwtsms.com/login/). If your message is stuck there, it was accepted by the API but not dispatched. Common causes are emoji in the message, hidden characters from copy-pasting (from Word, PDF, or rich editors), or spam filter triggers. Delete the stuck message from the queue to recover your credits. Also verify that Test Mode is off in Gateway Settings: in test mode, messages are queued but never delivered to the handset.

= What is a Sender ID and why should I not use the shared KWT-SMS sender? =

A Sender ID is the name that appears as the sender on the recipient's phone (for example, MY-APP instead of a random number). `KWT-SMS` is a shared test sender. It causes delivery delays and is blocked on Virgin Kuwait. Register a private Sender ID through your kwtSMS account. For OTP and authentication messages, you need a **Transactional** Sender ID, which bypasses DND (Do Not Disturb) filtering on Zain and Ooredoo. Promotional Sender IDs are filtered, meaning OTP messages can silently fail while credits are still deducted.

= I am getting an authentication error when I save my credentials. What should I check? =

The plugin requires your **API username and API password**, not your account mobile number or your login password. Log in to [kwtsms.com](https://www.kwtsms.com/login/), go to Account > API settings, and verify your API credentials. Credentials are case-sensitive. Make sure there are no extra spaces when copying and pasting.

= Can I send SMS to numbers outside Kuwait? =

International sending is disabled by default on kwtSMS accounts. Log in to your kwtSMS account and activate coverage for the countries you need. Visit the kwtSMS dashboard to view and manage your active country coverage. Note that enabling international coverage increases exposure to automated abuse, so rate limiting is strongly recommended before enabling it.

== Help & Support ==

* **[kwtSMS FAQ](https://www.kwtsms.com/faq/)**: Answers to common questions about credits, sender IDs, OTP, and delivery.
* **[kwtSMS Support](https://www.kwtsms.com/support.html)**: Open a support ticket or browse help articles.
* **[Contact kwtSMS](https://www.kwtsms.com/#contact)**: Reach the kwtSMS team directly for Sender ID registration and account issues.
* **[API Documentation (PDF)](https://www.kwtsms.com/doc/KwtSMS.com_API_Documentation_v41.pdf)**: kwtSMS REST API v4.1 full reference.
* **[Best Practices](https://www.kwtsms.com/articles/sms-api-implementation-best-practices.html)**: SMS API implementation best practices.
* **[Integration Test Checklist](https://www.kwtsms.com/articles/sms-api-integration-test-checklist.html)**: Pre-launch testing checklist.
* **[Sender ID Help](https://www.kwtsms.com/sender-id-help.html)**: Sender ID registration and guidelines.
* **[kwtSMS Dashboard](https://www.kwtsms.com/login/)**: Recharge credits, buy Sender IDs, view message logs, and manage coverage.
* **[Other Integrations](https://www.kwtsms.com/integrations.html)**: Plugins and integrations for other platforms and languages.
* **[Plugin Issues](https://github.com/boxlinknet/wp-kwtsms/issues)**: Report bugs or request features for this WordPress plugin.

== Screenshots ==

1. Gateway settings: API credentials, live account balance, Sender ID dropdown, and Test Mode toggle.
2. SMS Templates in Arabic (RTL): bilingual template editor with live character counter and SMS page indicator.
3. Two-Step Verification screen: OTP entry step shown after successful password login (2FA mode).
4. Passwordless login: phone number entry with country code selector, no password required.
5. Password reset via OTP: SMS verification step that replaces the default email reset link.
6. WooCommerce integration: per-status SMS templates and checkout OTP gate configuration.
7. Integrations overview: WooCommerce, Contact Form 7, WPForms, Elementor Pro, Gravity Forms, and Ninja Forms.
8. SMS Logs: full send history with date, Sender ID, message preview, phone, type, status, and API response.

== Changelog ==

= 3.0.3 =
* Fix: password reset OTP SMS now sent correctly even when login OTP cooldown is active for the same user (cooldown is now scoped per action type).
* Fix: WooCommerce order total placeholder {total} no longer contains HTML entities in SMS messages.
* Fix: CF7 gate mode form auto-submit after OTP verification no longer throws TypeError when pendingForm is null.
* Fix: WPForms gate mode phone field detection now checks label text (WPForms uses non-standard input names).
* Fix: Settings get() method now correctly returns $fallback instead of undefined $default variable.
* Fix: Country code dropdown on SMS login page is now properly sized (constrained width, phone field takes remaining space).
* Fix: Admin notices and warnings from other plugins (e.g. Action Scheduler) now display above the kwtSMS logo header, not beside it.
* Fix: Admin sub-menu page hiding now uses CSS/JS and redirect instead of remove_submenu_page, preventing redirect loops.
* Fix: Users Without Phone page menu count badge now updates dynamically without a page reload.
* Enhancement: CF7 notification mode now sends SMS even when SMTP email delivery fails (hooks wpcf7_submit instead of wpcf7_mail_sent).
* Enhancement: Integrations page notes Elementor Pro requirement for form widgets.
* Enhancement: Integrations page notes Ninja Forms phone field configuration requirement.
* Enhancement: Gravity Forms shown on Integrations page as "Coming soon".

= 3.0.2 =
* Fix: remove tab navigation from form integration pages (CF7, WPForms, Elementor, Gravity Forms, Ninja Forms), both cards now always visible.
* Fix: move Enable Integration toggle into settings table, consistent across all form integrations.
* Fix: restore left padding on WPForms admin pages stripped by WPForms.
* Fix: suppress WPForms injected header, flyout, and footer on kwtSMS integration pages.
* Fix: hide page footer on all kwtSMS admin pages.
* Docs: remove em dashes and separator hyphens from prose in readme.txt and README.md.

= 3.0.1 =
* Fix: resolve all PHP_CodeSniffer WordPress Coding Standards violations.
* Fix: expand country codes data to full 250-country list.
* Fix: alignment and spacing in OTP and passwordless login views.

= 3.0.0 =
* Initial public release.

== Upgrade Notice ==

= 3.0.0 =
Initial release. No upgrade required.
