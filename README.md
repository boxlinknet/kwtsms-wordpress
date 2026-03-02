# kwtSMS OTP Login and SMS Notifications — WordPress Plugin

Secure SMS-based OTP login, password reset, and WooCommerce / form notifications for WordPress — powered by the [kwtSMS](https://www.kwtsms.com) gateway.

**Version:** 2.8.0 | **Requires:** WordPress 6.0+, PHP 7.4+

> Don't have a kwtSMS account? [Sign up at kwtsms.com →](https://www.kwtsms.com/signup)

---

## Features

### Authentication
- **2FA mode** — standard password login followed by a one-time SMS code
- **Passwordless login** — phone number + OTP only; no password needed
- **Password reset via OTP** — replaces the default email reset flow with SMS
- **Per-role enforcement** — choose which user roles require OTP (e.g. skip OTP for subscribers)
- **Google reCAPTCHA v3** and **Cloudflare Turnstile** bot protection
- **Country code dropdown** on login forms — restrict to GCC or custom country list

### Security
- Cryptographically secure OTP generation (`random_int()`)
- **Sliding-window rate limiting** — per-phone, per-IP, per-account (no fixed-window gaming)
- **Phone blocking list** — silently drop OTP requests from blocked numbers (anti-enumeration)
- `hash_equals()` timing-safe OTP verification
- All cookies `httponly`, `secure`, `SameSite=Strict`
- Emergency bypass constant `KWTSMS_OTP_DISABLED` for lockout recovery

### WooCommerce
- **7 order status SMS**: Processing, Shipped, Completed, Cancelled, Pending, Refunded, Failed
- **Admin SMS notifications** — notify a configurable phone number on any order status change
- **Per-order custom SMS** — send a free-text SMS to the customer from the order edit screen
- OTP gate on WooCommerce checkout (verify phone before placing order)
- HPOS (High-Performance Order Storage) compatible

### Form Integrations — Notification or OTP Gate
Each integration supports two modes: **Notification** (send confirmation SMS on submit) or **OTP Gate** (block submission until phone is verified via OTP).

| Plugin | Auto-detected | Notification | OTP Gate |
|--------|:---:|:---:|:---:|
| Contact Form 7 | ✓ | ✓ | ✓ |
| WPForms | ✓ | ✓ | ✓ |
| Elementor Pro | ✓ | ✓ | ✓ |
| Gravity Forms | ✓ | ✓ | ✓ |
| Ninja Forms | ✓ | ✓ | ✓ |

### Balance & Gateway
- Account balance displayed on Gateway and Help pages without re-verifying credentials
- Pre-send balance check — warns before sending if credits are zero
- Test phone country code validation with hint text
- Test Mode — simulates sends without spending credits (OTP written to debug log)

### Admin
- 6 admin pages under the **kwtSMS** menu: General, Gateway, Templates, Integrations, Logs, Help
- Live credential verification with Sender ID auto-population
- OTP send log (last 100 entries)
- Dashboard widget with today's send count
- Full Arabic (RTL) translation included

---

## Screenshots

| | |
|---|---|
| ![Login page](docs/screenshots/2.x/v2.x-login-page.png) | ![OTP entry](docs/screenshots/2.x/v2.x-otp-entry-page.png) |
| Login page | OTP entry page |
| ![Admin menu](docs/screenshots/2.x/v2.1-admin-menu.png) | ![Gateway](docs/screenshots/2.x/v2.2-gateway-page.png) |
| Admin menu (kwtSMS) | Gateway settings |
| ![WooCommerce](docs/screenshots/2.x/v2.3-woo-integrations.png) | ![CF7 gate](docs/screenshots/2.x/v2.7-cf7-gate-mode.png) |
| WooCommerce order SMS | CF7 OTP gate mode toggle |
| ![Gravity Forms](docs/screenshots/2.x/v2.8-gravityforms-tab.png) | ![Ninja Forms](docs/screenshots/2.x/v2.8-ninjaforms-tab.png) |
| Gravity Forms integration | Ninja Forms integration |

---

## Requirements

| | Version |
|---|---|
| WordPress | 6.0 or later |
| PHP | 7.4 or later (8.x recommended) |
| kwtSMS account | [Sign up free](https://www.kwtsms.com/signup) |
| WooCommerce | Optional |
| Contact Form 7 / WPForms / Elementor Pro / Gravity Forms / Ninja Forms | Optional |

---

## Installation

1. Clone or download this repository
2. Upload the `wp-kwtsms-otp/` directory to `/wp-content/plugins/`
3. Activate from **Plugins → Installed Plugins**
4. Go to **kwtSMS → Gateway** and enter your API credentials
5. Click **Login** to verify credentials and load your Sender IDs
6. Configure OTP behaviour under **kwtSMS → General**

---

## Plugin Structure

```
wp-kwtsms-otp/
├── wp-kwtsms-otp.php
├── includes/
│   ├── class-kwtsms-plugin.php       # Main service locator (singleton)
│   ├── class-kwtsms-api.php          # kwtSMS HTTP API client
│   ├── class-kwtsms-settings.php     # Settings helper (wp_options wrapper)
│   ├── class-kwtsms-otp-engine.php   # OTP generate/verify, sliding-window rate limiting
│   ├── class-kwtsms-login-otp.php    # Login 2FA / passwordless hooks
│   ├── class-kwtsms-reset-otp.php    # Password reset OTP hooks
│   ├── class-kwtsms-user-meta.php    # Phone number field on user profile
│   ├── class-kwtsms-captcha.php      # reCAPTCHA v3 / Turnstile
│   ├── class-kwtsms-integrations.php # Integration loader
│   └── integrations/
│       ├── class-kwtsms-woo.php         # WooCommerce order SMS
│       ├── class-kwtsms-woo-metabox.php # Per-order custom SMS metabox
│       ├── class-kwtsms-cf7.php         # Contact Form 7
│       ├── class-kwtsms-wpforms.php     # WPForms
│       ├── class-kwtsms-elementor.php   # Elementor Pro
│       ├── class-kwtsms-gravityforms.php # Gravity Forms
│       └── class-kwtsms-ninjaforms.php  # Ninja Forms
├── admin/
│   ├── class-kwtsms-admin.php
│   └── views/
│       ├── page-general.php
│       ├── page-gateway.php
│       ├── page-templates.php
│       ├── page-integrations.php
│       ├── page-logs.php
│       └── page-help.php
├── assets/
│   ├── css/admin.css
│   ├── css/login.css
│   ├── js/admin.js
│   ├── js/login.js
│   └── js/form-otp.js   # OTP gate modal for form integrations
├── languages/
│   ├── wp-kwtsms-otp.pot
│   ├── wp-kwtsms-otp-ar.po / .mo
│   └── wp-kwtsms-otp-en_US.po / .mo
├── tests/                # PHPUnit 9 + Brain\Monkey (191 tests)
└── uninstall.php
```

---

## Testing Locally (WP Playground)

No Docker required:

```bash
cd wp-kwtsms-otp/
npx @wp-playground/cli@latest server --auto-mount
# Opens at http://localhost:9400
```

Enable **Test Mode** in Gateway settings — the OTP code is written to `wp-content/debug.log`.

### Running the Test Suite

```bash
cd wp-kwtsms-otp/
composer install
./vendor/bin/phpunit --no-coverage
```

---

## Important API Notes

| Topic | Detail |
|---|---|
| **Promotional sender "KWT-SMS"** | Intentionally slow (100+ second delivery). Not suitable for OTP. Virgin (Zain-MVNO) Kuwait subscribers do not receive it. Use a private Sender ID for OTP. |
| **Kuwait delivery reports** | DLR is not available for messages to Kuwait numbers. The API returns "OK" once the message is handed off to the operator, but there is no confirmation of receipt. |
| **International coverage** | Disabled by default on new accounts. Contact kwtSMS support to enable. |
| **API rate limit** | Max 5 requests/second per IP. Exceeding this temporarily blocks your server IP. |
| **Test mode credits** | `test=1` still deducts credits. Delete queued messages from your kwtSMS outbox to recover them. |
| **API error log** | Your kwtSMS account dashboard (API → Error Log) shows all send attempts with error details. |
| **Server timezone** | The kwtSMS API server operates on Asia/Kuwait (GMT+3). |

---

## Changelog

### 2.8.0
- Gravity Forms integration: notification SMS on submission + OTP gate mode
- Ninja Forms integration: notification SMS on submission + OTP gate mode

### 2.7.0
- **OTP Gate mode** for Contact Form 7, WPForms, and Elementor Pro — block form submission until phone is verified via OTP
- New frontend `form-otp.js` modal with phone input, code entry, and countdown
- AJAX endpoints: `kwtsms_form_send_otp` and `kwtsms_form_verify_otp` with attempt limiting and CSPRNG tokens

### 2.6.0
- Replaced fixed-window rate limiting with **sliding-window** algorithm — prevents gaming at window boundaries
- Per-phone, per-IP, and per-account limits all updated

### 2.5.0
- **Per-role OTP enforcement** — configure which user roles require OTP; excluded roles bypass OTP silently
- Super admin (multisite) automatically treated as Administrator role

### 2.4.0
- **Phone number blocking list** — textarea in General settings; blocked phones receive a silent success response (anti-enumeration)

### 2.3.0
- WooCommerce: 3 new order statuses — **Pending**, **Refunded**, **Failed** (disabled by default)
- **Admin SMS notifications** — configurable phone number notified on any order status change
- **Per-order custom SMS metabox** — send free-text SMS to customer from order edit screen
- HPOS-compatible metabox registration

### 2.2.0
- Balance persisted and shown on Gateway and Help pages without re-verifying
- Pre-send balance check in `send_sms()` — warns when credits are zero
- Test phone field now validates country code before sending

### 2.1.0
- Plugin renamed to **kwtSMS OTP Login and SMS Notifications**
- Admin menu updated from "kwtSMS OTP" to "kwtSMS"
- WooCommerce HPOS compatibility declaration

### 2.0.0
- WooCommerce order SMS (Processing, Shipped, Completed, Cancelled)
- WooCommerce checkout OTP gate
- Contact Form 7, WPForms, Elementor Pro integrations (notification mode)
- Per-IP and per-account rate limiting
- Emergency bypass constant `KWTSMS_OTP_DISABLED`

### 1.0.0 – 1.5.0
- 2FA and passwordless login, password reset via OTP
- Google reCAPTCHA v3 and Cloudflare Turnstile
- Country code dropdown with GeoIP pre-selection
- Full Arabic (RTL) translation
- SMS send log, OTP attempt log, debug log viewer
- Gateway login/logout toggle with live balance

---

## License

GPL-2.0-or-later — see [GNU GPL v2.0](https://www.gnu.org/licenses/gpl-2.0.html)

---

Powered by [kwtSMS.com](https://www.kwtsms.com) — Kuwait's SMS gateway
