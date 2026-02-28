# kwtsms OTP Authentication for WordPress

Secure, convenient SMS-based authentication for WordPress — powered by the [kwtsms](https://www.kwtsms.com/) gateway. Replace passwords with one-time codes, protect WooCommerce checkouts, send order updates by SMS, and give your customers the fastest login experience possible.

> **Need a kwtsms account?** [Sign up for free →](https://www.kwtsms.com/signup)

---

## Why SMS Authentication?

- **Security** — OTP codes expire in minutes and can't be reused; no stored passwords to steal
- **Convenience** — customers log in with a phone number and a code; no password to remember
- **Trust** — verifying a phone number at registration confirms real users, not bots
- **Order updates** — send WooCommerce order status SMS automatically (processing, shipped, completed, cancelled)
- **GCC-first** — Arabic RTL support, Gulf phone number formats, kwtsms gateway built for the region

---

## Features at a Glance

### Authentication
- **2FA mode** — password login + SMS OTP as a second factor
- **Passwordless mode** — phone number + OTP only; no password needed
- **Both modes together** — users can choose which method to use
- **Password reset via OTP** — SMS code replaces the default email reset link
- **All logins protected** — applies to regular users and admins alike

### Registration & Phone Verification
- Phone number field on the WordPress registration form
- Phone number field on WooCommerce checkout / registration
- Server-side phone normalization on every save
- Optional Welcome SMS on new account creation

### WooCommerce
- Automatic order status SMS to customers: Processing, Shipped, Completed, Cancelled
- Optional OTP gate on WooCommerce checkout (verify phone before placing order)
- WooCommerce registration form phone field with normalization

### Third-Party Integrations
- **Contact Form 7** — send a confirmation SMS when a form is submitted
- **WPForms** — send a confirmation SMS on form submission
- **Elementor Pro** — send SMS on form submission via Elementor Pro Forms
- All integrations are auto-detected and only loaded when the plugin is active

### Security
- `random_int()` for cryptographically secure OTP generation
- `hash_equals()` for timing-safe verification
- Per-phone rate limiting (max 10 requests per 10 minutes)
- Per-IP rate limiting (max 10 requests per 10 minutes)
- Per-account rate limiting (max 5 requests per 10 minutes)
- Maximum 3 failed attempts before session lockout
- Anti-enumeration: identical response for unknown phones
- All cookies `httponly`, `secure`, `SameSite=Strict`
- API credentials never output to browser or JavaScript
- HTTPS-only enforcement with admin notice
- Emergency bypass constant for admin lockout recovery

### CAPTCHA
- Google reCAPTCHA v3 (invisible, score-based)
- Cloudflare Turnstile (privacy-preserving widget)
- CAPTCHA applied on OTP request forms (login + reset)

### Admin
- 6 admin pages: General, Gateway, Templates, Integrations, Logs, Help
- Live credential verification with Sender ID auto-population
- Account balance display
- "Send Test SMS" button with inline result
- OTP send log (last 100 entries with phone, status, timestamp)
- Dashboard widget with today's send count
- Admin notices: test mode active, missing credentials, low balance

### Internationalization
- Full Arabic translation included (`.po` / `.mo`)
- RTL CSS auto-loaded for Arabic and other RTL locales
- All user-facing strings wrapped in WordPress i18n functions
- SMS template editor with Arabic RTL textarea

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0 or later |
| PHP | 7.4 or later (8.x recommended) |
| kwtsms account | [Sign up free](https://www.kwtsms.com/signup) |
| WooCommerce | Optional — enables order SMS and checkout OTP |
| Contact Form 7 | Optional |
| WPForms | Optional (Lite or Pro) |
| Elementor Pro | Optional |

---

## Installation

1. Download or clone this repository
2. Upload the `wp-kwtsms-otp/` directory to `/wp-content/plugins/`
3. Activate from **Plugins → Installed Plugins**
4. Go to **kwtsms OTP → Gateway** and enter your API credentials
5. Click **Save & Verify Credentials**
6. Configure OTP behavior under **kwtsms OTP → General**

---

## Getting a kwtsms Account

1. Visit [kwtsms.com/signup](https://www.kwtsms.com/signup) and create a free account
2. Log in to the [kwtsms dashboard](https://www.kwtsms.com/login/) to find your API credentials
3. Register a Sender ID (e.g. your business name) — this appears as the SMS sender
4. Enter the username and password in **kwtsms OTP → Gateway → Save & Verify Credentials**

> Credentials are stored server-side in `wp_options` only. They are never output to HTML or JavaScript.

---

## Admin Pages

### General Settings (`kwtsms OTP → General`)

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Login OTP | On | Enable/disable OTP for login |
| OTP Mode | 2FA | `2FA`, `Passwordless`, or `Both` |
| Enable Password Reset OTP | On | Replace email reset with SMS OTP |
| OTP Code Length | 6 | `4` or `6` digits |
| OTP Expiry | 5 minutes | How long a code stays valid |
| Max Verification Attempts | 3 | Failed attempts before lockout |
| Resend Cooldown | 120 seconds | Wait between resend requests |
| CAPTCHA Provider | None | `None`, `reCAPTCHA v3`, or `Turnstile` |
| Default Country | KW | Pre-selected dial code on phone inputs |
| Allowed Countries | GCC | Countries shown in the dial code picker |

### Gateway Settings (`kwtsms OTP → Gateway`)

| Setting | Description |
|---------|-------------|
| API Username | Your kwtsms username |
| API Password | Your kwtsms password |
| Sender ID | Dropdown populated from your approved sender IDs |
| Test Mode | Send with `test=1` — no real SMS, credits not consumed |
| Test Phone | Phone used for the "Send Test SMS Now" button |
| Account Balance | Current / purchased credits (updated on credential verify) |
| API Status | Green / Red badge showing gateway connectivity |

### SMS Templates (`kwtsms OTP → Templates`)

Three editable templates, each with:
- Enable/disable toggle
- English message textarea
- Arabic message textarea (RTL)
- Live character counter (160 per SMS in English, 70 in Arabic)
- SMS page count indicator
- Available placeholder list

| Template | Sent When | Placeholders |
|----------|-----------|--------------|
| Login OTP | User requests login code | `{otp}`, `{site_name}`, `{expiry_minutes}` |
| Password Reset OTP | User requests password reset code | `{otp}`, `{site_name}`, `{expiry_minutes}` |
| Welcome SMS | New user account created (optional) | `{name}`, `{site_name}` |

**Default Login OTP (English):**
```
Your {site_name} login code is: {otp}. Valid for {expiry_minutes} minutes. Do not share this code.
```

**Default Login OTP (Arabic):**
```
رمز تسجيل الدخول إلى {site_name} هو: {otp}. صالح لمدة {expiry_minutes} دقائق. لا تشارك هذا الرمز.
```

### Integrations (`kwtsms OTP → Integrations`)

Shows the status of all supported third-party plugins (Active / Not installed).

- **WooCommerce** — enable/disable WooCommerce Checkout OTP gate
- **Contact Form 7** — instructions for adding `kwtsms_phone` field to forms
- **WPForms** — auto-detected via field type
- **Elementor Pro** — auto-detected via field type `tel`

### Logs (`kwtsms OTP → Logs`)

Last 100 OTP send events with:
- Timestamp
- Phone number (masked)
- Action (login / reset / passwordless / woo-checkout)
- Status (sent / failed)
- Error code if failed

### Help (`kwtsms OTP → Help`)

- Quick-start checklist
- Common error codes and fixes
- Admin lockout recovery instructions
- Links to API documentation and kwtsms support

---

## OTP Authentication Flows

### 2FA Login

```
1. User submits username + password → WordPress validates credentials
2. Plugin intercepts via authenticate filter (priority 30)
3. OTP generated and sent by SMS to the user's registered phone
4. Partial auth session stored in transient (15-minute TTL)
5. User redirected to OTP entry page
6. User enters code → verified → auth cookies issued → redirect to dashboard
```

### Passwordless Login

```
1. User clicks "Login with SMS OTP" on wp-login.php
2. User enters their phone number (with country code)
3. Plugin looks up user by kwtsms_phone meta
4. Same generic message shown whether phone is found or not (anti-enumeration)
5. If found: OTP sent → user enters code → logged in
```

### Password Reset via OTP

```
1. User clicks "Lost your password?"
2. Custom form: enter username, email, or phone number
3. If user found and has a phone: OTP sent via SMS
4. User enters OTP → redirected to WP password reset form
5. User sets new password → automatically logged in
6. If no phone on file: fallback to email reset with notice
```

### WooCommerce Checkout OTP (optional)

```
1. Customer enters phone at checkout
2. On first "Place Order" click: OTP sent to phone
3. OTP entry field appears on checkout page
4. On second submission: OTP verified → order placed
```

---

## Phone Number Registration

### Profile Page (all users)

1. Go to **Users → Edit User** (or **Your Profile**)
2. Find **Phone Number (for SMS OTP)**
3. Enter the number with country code — e.g. `96598765432`
4. Click **Update Profile**

### Registration Form

When user registration is open (`Settings → General → Membership`), the phone number field is automatically added to the WordPress registration form. Validation runs server-side before the account is created.

### WooCommerce Registration / Checkout

The phone field is added to WooCommerce's registration form and checkout. Phone numbers are stored in `kwtsms_phone` user meta on account creation.

### Phone Number Normalization

Numbers are automatically cleaned before save and before every API call:

| Input | Normalized |
|-------|-----------|
| `+96598765432` | `96598765432` |
| `0096598765432` | `96598765432` |
| `965 9922 0322` | `96598765432` |
| `965-9922-0322` | `96598765432` |
| `٩٦٥٩٩٢٢٠٣٢٢` (Arabic numerals) | `96598765432` |
| `۹۶۵۹۹۲۲۰۳۲۲` (Persian numerals) | `96598765432` |
| `(965) 9922.0322` | `96598765432` |

Invalid inputs (letters, too short, too long) are rejected with a user-friendly error.

---

## WooCommerce Order SMS

Order status changes automatically send SMS to the customer's phone:

| Status Change | Message Sent |
|---------------|-------------|
| → Processing | Order confirmed + total |
| → On Hold (Shipped) | Order shipped notification |
| → Completed | Order completion notification |
| → Cancelled | Cancellation notice |

The customer's phone is read from `kwtsms_phone` user meta, falling back to the WooCommerce billing phone if not set.

---

## Third-Party Form Integrations

### Contact Form 7

Add a phone field named `kwtsms_phone` to any form:

```
[tel kwtsms_phone placeholder "e.g. 96598765432"]
```

When the form is submitted successfully, a confirmation SMS is sent to that number using your Login OTP template.

### WPForms

Any form with a field of type `Phone` will automatically trigger a confirmation SMS on successful submission. No configuration needed.

### Elementor Pro Forms

Any form with a field of type `Tel` or with "phone" in the field title will trigger a confirmation SMS on submission.

---

## Test Mode

Enable test mode for development and QA:

1. Go to **kwtsms OTP → Gateway**
2. Enable **Test Mode**
3. Set **Test Phone** to your number (e.g. `96598765432`)
4. Enable `WP_DEBUG_LOG` in `wp-config.php`

When active:
- All SMS calls use `test=1` — messages are queued but not delivered; no credits consumed
- OTP codes are written to `wp-content/debug.log`:
  ```
  [kwtsms-otp TEST] SMS to 96598765432: Your login code is: 123456. Valid for 5 minutes.
  ```
- A prominent orange admin notice reminds you that test mode is on

---

## Admin Account Protection

All WordPress logins go through the same OTP flow, including administrator accounts. There is no bypass for admins by default.

### Emergency Bypass (Lockout Recovery)

If you are locked out of your admin account, you have three options:

**Option 1 — wp-config.php constant (easiest)**
```php
define( 'KWTSMS_OTP_DISABLED', true );
```
Add this to `wp-config.php`. The entire OTP system will be skipped until you remove the line.

**Option 2 — WP-CLI**
```bash
wp user update admin --user_pass="NewSecurePassword!" --allow-root
```
This bypasses the login form entirely.

**Option 3 — SFTP / cPanel**
Rename `wp-kwtsms-otp/wp-kwtsms-otp.php` to `wp-kwtsms-otp.php.disabled`. WordPress will deactivate the plugin automatically on the next request.

---

## Security

| Feature | Implementation |
|---------|---------------|
| OTP generation | `random_int()` — cryptographically secure |
| OTP comparison | `hash_equals()` — timing-safe |
| Session tokens | 40-char `wp_generate_password()` strings |
| Cookies | `httponly`, `secure` (HTTPS), `SameSite=Strict` |
| Rate limiting | Per-phone, per-IP, per-account transient counters |
| Max attempts | Lockout after 3 failed verifications |
| Anti-enumeration | Identical response for unknown/known phones |
| Credentials | Server-side only; never in HTML or JS |
| Input sanitization | `sanitize_text_field()` + custom phone normalizer |
| Output escaping | `esc_html()`, `esc_attr()`, `esc_url()` everywhere |
| Nonces | On every form and AJAX request |
| Capability checks | `manage_options` on all admin actions |
| HTTPS | Admin notice if site is not HTTPS |
| Debug log | Rotated at 1 MB; keeps one backup (`.log.1`) |

---

## Internationalization

- Text domain: `wp-kwtsms-otp`
- Translations: English (en_US), Arabic (ar)
- RTL CSS loaded automatically via `is_rtl()`
- Arabic textarea in template editor has `dir="rtl"` and Arabic font stack

To switch to Arabic:
1. **Settings → General → Site Language → Arabic**
2. Save — admin UI and login pages switch to Arabic RTL automatically

---

## Error Reference

| Code | Meaning | Fix |
|------|---------|-----|
| ERR003 | Wrong credentials | Verify username/password at kwtsms.com |
| ERR008 | Sender ID not allowed | Choose an approved Sender ID |
| ERR010/011 | Insufficient credits | Top up your kwtsms balance |
| ERR026 | No SMS coverage | Add coverage for this country in your kwtsms account |
| ERR006/025 | Invalid phone number | Ensure country code is included |
| ERR028 | Resend too fast | Wait 15 seconds between resend requests |
| ERR031/032 | Content rejected | Check template for spam-flagged content |

Full error code reference: [kwtsms API Documentation (PDF)](https://www.kwtsms.com/doc/KwtSMS.com_API_Documentation_v41.pdf)

---

## Uninstall

Deleting the plugin from **Plugins → Delete** removes all plugin data:

- All `kwtsms_otp_*` options from `wp_options`
- All `kwtsms_phone` user meta from `wp_usermeta`
- All `kwtsms_otp_*` and `kwtsms_partial_auth_*` transients
- The plugin does **not** remove WooCommerce order data

---

## Changelog

### 2.0.0
- WooCommerce integration: order status SMS, checkout OTP gate, registration phone field
- Contact Form 7 integration: confirmation SMS on form submission
- WPForms integration: confirmation SMS on form submission
- Elementor Pro integration: confirmation SMS on form submission
- Integrations admin page with status table and WC checkout OTP setting
- Phone field on WordPress registration form
- Per-IP and per-account OTP send rate limiting
- Debug log rotation at 1 MB (keeps `.log.1` backup)
- Emergency bypass constant `KWTSMS_OTP_DISABLED`
- Admin lockout documentation on Help page

### 1.6.0
- Blueprint for WordPress Playground (instant test environment with all integrations)
- Updated API docs and support links on Help page

### 1.5.0
- Reset OTP resend fix (resend button now correctly regenerates reset codes)
- Passwordless page CSS fix (duplicate selector merged)
- "0 attempts remaining" now shows expiry message instead of misleading count
- Admin lockout warning added to Help page

### 1.4.0
- OTP send log (last 100 entries)
- Logs admin page

### 1.3.0
- Sender ID sanity check on credential save
- Structured debug logging system
- Unredacted phone numbers in logs
- Credential pre-checks for all admin actions
- Suspicious OTP input logging

### 1.0.0
- 2FA login via SMS OTP
- Passwordless login via SMS OTP
- Password reset via SMS OTP (replaces email link)
- Admin settings: General, Gateway, Templates
- Google reCAPTCHA v3 and Cloudflare Turnstile
- Arabic (RTL) translation
- OTP send activity log
- Dashboard widget

---

## Support

- **API Documentation:** [KwtSMS API v4.1 (PDF)](https://www.kwtsms.com/doc/KwtSMS.com_API_Documentation_v41.pdf)
- **kwtsms Support:** [kwtsms.com/#contact](https://www.kwtsms.com/#contact)
- **kwtsms Dashboard:** [kwtsms.com/login](https://www.kwtsms.com/login/)
- **Sign up:** [kwtsms.com/signup](https://www.kwtsms.com/signup)

---

## License

GPLv2 or later. See [GNU GPL v2.0](https://www.gnu.org/licenses/gpl-2.0.html).
