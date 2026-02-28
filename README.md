# kwtSMS OTP Authentication — WordPress Plugin

Secure SMS-based OTP login and password reset for WordPress, powered by the [kwtSMS](https://www.kwtsms.com) gateway.

## Features

- **2FA mode** — standard password login followed by a one-time SMS code
- **Passwordless login** — phone-number-only login (no password required)
- **Password reset via OTP** — replaces the default email reset flow with SMS
- **Google reCAPTCHA v3** and **Cloudflare Turnstile** bot protection
- **Country code dropdown** on login forms — restrict to GCC or custom country list
- **GeoIP pre-selection** — auto-detects the user's country on the login form
- **Fully bilingual** — English + Arabic (RTL) with WordPress i18n
- **Admin gateway dashboard** — login/logout toggle, live balance, sender ID list, SMS coverage map
- **Logs page** — full SMS history, OTP attempt log (with IP), and debug log viewer
- **Version:** 1.5.0 | **Requires:** WordPress 6.0+, PHP 7.4+

## Installation

1. Download or clone this repository into `wp-content/plugins/wp-kwtsms-otp/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Go to **Settings → kwtSMS OTP → Gateway** and enter your kwtSMS API credentials
4. Click **Login** to verify credentials — your sender IDs and balance will load automatically
5. Configure OTP behaviour under **Settings → kwtSMS OTP → General**

> Don't have a kwtSMS account? [Sign up at kwtsms.com →](https://www.kwtsms.com/signup)

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
- An active [kwtSMS](https://www.kwtsms.com) account with API access

## Configuration

### Gateway Settings
| Setting | Description |
|---------|-------------|
| API Username | Your kwtSMS account username |
| API Password | Your kwtSMS account password |
| Sender ID | Pre-approved sender name shown on SMS |
| Test Mode | Simulates sends without spending credits (OTP written to debug log) |

### General Settings
| Setting | Default | Description |
|---------|---------|-------------|
| OTP Mode | 2FA | `2fa` or `passwordless` |
| OTP Length | 6 digits | 4–8 |
| OTP Expiry | 5 minutes | How long the code is valid |
| Max Attempts | 3 | Failed attempts before lockout |
| Resend Cooldown | 120 seconds | Minimum time between resend requests |
| Allowed Countries | GCC (KW, SA, AE, BH, QA, OM) | Countries shown in the login form dropdown |
| CAPTCHA Provider | None | `none`, `recaptcha_v3`, or `turnstile` |
| Referral Link | On | Show "SMS service by kwtSMS.com" on the login footer |

## Plugin Structure

```
wp-kwtsms-otp/
├── wp-kwtsms-otp.php              # Bootstrap, activation hooks, autoloader
├── includes/
│   ├── class-kwtsms-plugin.php    # Main service locator (singleton)
│   ├── class-kwtsms-api.php       # kwtSMS HTTP API client
│   ├── class-kwtsms-settings.php  # Settings helper (wp_options wrapper)
│   ├── class-kwtsms-otp-engine.php # OTP generate / verify / rate-limit
│   ├── class-kwtsms-login-otp.php  # Login 2FA / passwordless hooks
│   ├── class-kwtsms-reset-otp.php  # Password reset OTP hooks
│   ├── class-kwtsms-user-meta.php  # Phone number field on user profile
│   ├── class-kwtsms-captcha.php    # reCAPTCHA v3 / Turnstile integration
│   ├── class-kwtsms-geoip.php      # Server-side GeoIP (ip-api.com, cached)
│   ├── views/
│   │   ├── page-otp.php            # OTP entry page (2FA)
│   │   └── page-passwordless.php   # Passwordless login page
│   └── data/
│       └── country-codes.php       # Dial code + ISO2 for ~240 countries
├── admin/
│   ├── class-kwtsms-admin.php      # Admin menu, settings pages, AJAX handlers
│   └── views/
│       ├── page-gateway.php        # Gateway settings (credentials, coverage)
│       ├── page-general.php        # General OTP settings
│       ├── page-logs.php           # SMS history, OTP attempts, debug log
│       └── page-help.php           # Quick-start guide, status panel
├── assets/
│   ├── css/admin.css
│   ├── css/login.css
│   ├── js/admin.js
│   └── js/login.js
├── languages/
│   ├── wp-kwtsms-otp.pot
│   ├── wp-kwtsms-otp-ar.po / .mo   # Arabic translation
│   └── wp-kwtsms-otp-en_US.po / .mo
└── uninstall.php                   # Removes all plugin data on deletion
```

## Testing Locally (WP Playground)

No Docker required — use [WordPress Playground](https://wordpress.github.io/wordpress-playground/):

```bash
cd wp-kwtsms-otp/
npx @wp-playground/cli@latest server --auto-mount
# Opens at http://localhost:9400
```

Enable **Test Mode** in Gateway settings to simulate SMS sends without spending credits. The OTP code is written to `wp-content/debug.log` (requires `WP_DEBUG_LOG=true`).

## Changelog

### 1.5.0
- Gateway login/logout toggle — credentials persist to DB on login
- Live account balance auto-updates after each SMS send
- Two-column SMS coverage display on Gateway page
- OTP Activity table with type column and link to full Logs
- Debug Log viewer tab in Logs page
- Referral link on standard `wp-login.php` footer
- Account balance shown on Help page

### 1.4.0
- Admin notices relocated above page title (WP flex header fix)
- Improved UX on Gateway settings page

### 1.0.0 – 1.3.0
- Initial release: 2FA, passwordless login, password reset via OTP
- Google reCAPTCHA v3 and Cloudflare Turnstile support
- Country code dropdown with GeoIP pre-selection
- Full Arabic (RTL) translation
- SMS History and OTP Attempts logs

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

---

Powered by [kwtSMS.com](https://www.kwtsms.com) — Kuwait's SMS gateway
