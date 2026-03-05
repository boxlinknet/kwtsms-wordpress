=== kwtSMS: OTP & SMS Notifications ===
Contributors: kwtsms
Tags: sms, otp, authentication, woocommerce, login
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.0.0
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

= WooCommerce Integration =

* **7 order status notifications:** Processing, On-Hold (Shipped), Completed, Cancelled, Pending Payment, Refunded, Failed
* **Checkout OTP Gate:** require phone verification before the customer can place an order
* **Per-status templates:** independent English + Arabic SMS template for every order status
* **Admin SMS panel:** send a custom SMS to any order's phone number directly from the WooCommerce order screen

= Contact Form Integrations =

Each integration supports two modes: **Notification** (send a confirmation SMS on submit) and **OTP Gate** (block submission until the phone number is verified):

* Contact Form 7
* WPForms
* Elementor Pro Forms
* Gravity Forms
* Ninja Forms

= Security =

* Per-phone and per-IP rate limiting to prevent OTP flooding
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

**1. kwtSMS API** (required) — sends SMS messages.

* Service: [https://www.kwtsms.com](https://www.kwtsms.com)
* API endpoint: `https://www.kwtsms.com/API/`
* Data sent: phone number, message text, API credentials
* When: every time an OTP or notification SMS is dispatched
* Terms of Service: [https://www.kwtsms.com/policy.html](https://www.kwtsms.com/policy.html)
* Privacy Policy: [https://www.kwtsms.com/privacy.html](https://www.kwtsms.com/privacy.html)

A kwtSMS account with SMS credits is required.

**2. ipapi.co** (optional) — detects the visitor's country to pre-select the dial-code flag on the phone input.

* Service: [https://ipapi.co](https://ipapi.co)
* Data sent: visitor IP address (no other data)
* When: on the login page when Passwordless or OTP mode is active; result is cached for 24 hours per IP
* Terms of Service: [https://ipapi.co/terms/](https://ipapi.co/terms/)
* Privacy Policy: [https://ipapi.co/privacy/](https://ipapi.co/privacy/)

If ipapi.co is unavailable, the phone input falls back to the default country configured in General Settings. No personal data is stored by the plugin as a result of this call.

**3. Google reCAPTCHA v3** (optional) — bot protection on OTP forms. Only active if you enter a reCAPTCHA Site Key in General Settings.

* Service: [https://www.google.com/recaptcha/](https://www.google.com/recaptcha/)
* Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

**4. Cloudflare Turnstile** (optional) — alternative bot protection. Only active if you enter a Turnstile Site Key in General Settings.

* Service: [https://www.cloudflare.com/products/turnstile/](https://www.cloudflare.com/products/turnstile/)
* Privacy Policy: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)

= Test Mode =

Enable **Test Mode** in the Gateway settings to develop and test without consuming SMS credits. In test mode the API call is made with the `test=1` flag. OTP codes are generated and stored normally, and the code is written to the WordPress debug log so you can complete flows during development.

= Languages =

Ships with English (default) and Arabic translations. The plugin admin UI and all user-facing strings are fully translatable.

== Installation ==

1. Upload the `wp-kwtsms` folder to `/wp-content/plugins/`, or install via **Plugins > Add New Plugin** in your WordPress dashboard.
2. Activate the plugin through the **Plugins** screen.
3. Go to **kwtSMS > Gateway** and enter your kwtSMS API username and password.
4. Click **Save & Verify Credentials**. The Sender ID dropdown will populate automatically.
5. Select your Sender ID and save.
6. Go to **kwtSMS > General** and choose your OTP mode (2FA, Passwordless, or Both).
7. Customize your SMS templates under **kwtSMS > Templates**.

For WooCommerce notifications, visit **kwtSMS > Integrations > WooCommerce** and enable the order statuses you want.

== Frequently Asked Questions ==

= Do I need a kwtSMS account? =

Yes. You need an active kwtSMS account with API access. Sign up at [kwtsms.com](https://www.kwtsms.com). API credentials (username and password) are entered in the Gateway settings page.

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

== Screenshots ==

1. Gateway settings page: enter API credentials, select Sender ID, and view account balance.
2. General settings: choose OTP mode, configure code length, expiry, rate limits, and CAPTCHA.
3. SMS Templates: English and Arabic templates with live character counter and page indicator.
4. OTP verification screen shown to users during login.
5. Passwordless login: users enter their phone number to receive an OTP.
6. WooCommerce integration: per-status SMS templates and checkout OTP gate settings.
7. Integrations overview page: enable and configure CF7, WPForms, Elementor, Gravity Forms, and Ninja Forms.
8. SMS Logs: full send history with phone number, message, and status.

== Changelog ==

= 3.0.0 =
* Initial public release.

== Upgrade Notice ==

= 3.0.0 =
Initial release. No upgrade required.
