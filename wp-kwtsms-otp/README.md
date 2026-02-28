# kwtsms OTP Authentication

A WordPress plugin for SMS-based one-time password (OTP) authentication powered by the [kwtsms](https://www.kwtsms.com/) SMS gateway.

## Features

- **Two-Factor Authentication (2FA)** — password + OTP after successful login
- **Passwordless Login** — phone number + OTP only (no password required)
- **Password Reset via OTP** — SMS OTP replaces the default email reset link
- **Anti-enumeration** — generic responses for unknown phone numbers
- **Rate limiting** — maximum 3 OTP requests per phone per 10 minutes
- **CAPTCHA support** — Google reCAPTCHA v3 and Cloudflare Turnstile
- **SMS templates** — customisable English and Arabic (RTL) templates
- **Test mode** — OTP codes written to `wp-content/debug.log`, no real SMS sent
- **Dashboard widget** — today's OTP send count and recent activity log
- **Arabic (RTL) support** — full translation included

## Requirements

- WordPress 6.0 or later
- PHP 8.0 or later
- A [kwtsms](https://www.kwtsms.com/) account with API access

## Installation

1. Upload the `wp-kwtsms-otp` directory to `/wp-content/plugins/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Navigate to **kwtsms OTP → Gateway** and enter your API credentials
4. Click **Save & Verify Credentials** to confirm the connection
5. Configure OTP behavior under **kwtsms OTP → General**

## Configuration

### Gateway Settings

| Field | Description |
|-------|-------------|
| API Username | Your kwtsms account username |
| API Password | Your kwtsms account password (stored server-side only) |
| Sender ID | Select from your approved kwtsms sender IDs |
| Enable Test Mode | Queue SMS without delivery; OTP codes written to `debug.log` |
| Test Phone | Phone number used for test SMS (include country code) |

### General Settings

| Field | Default | Description |
|-------|---------|-------------|
| OTP Mode | 2FA | `2FA`, `Passwordless`, or `Both` |
| Require OTP on Login | On | Enable/disable 2FA login interception |
| Use SMS OTP for Password Reset | On | Replace email reset link with SMS OTP |
| OTP Code Length | 6 digits | `4` or `6` digits |
| Code Expiry | 180 seconds | How long a code remains valid |
| Max Attempts | 3 | Failed attempts before lockout |
| Resend Cooldown | 60 seconds | Wait between resend requests |
| CAPTCHA Provider | None | `None`, `Google reCAPTCHA v3`, or `Cloudflare Turnstile` |

### User Phone Numbers

Each WordPress user must have a phone number registered to receive OTPs.

1. Go to **Users → Edit User** (or the user's profile)
2. Find the **Phone Number (for SMS OTP)** field
3. Enter the phone number with country code (e.g. `96599220322` for Kuwait)
4. Click **Update Profile**

Phone numbers are automatically normalised:
- `+96599220322` → `96599220322`
- `0096599220322` → `96599220322`
- `965 9922 0322` → `96599220322`
- Eastern Arabic numerals → Western digits

## Test Mode

Enable test mode for development and testing:

1. Go to **kwtsms OTP → Gateway**
2. Check **Enable Test Mode**
3. Optionally set **Test Phone** (for "Send Test SMS Now" button)
4. Ensure `WP_DEBUG_LOG` is `true` in `wp-config.php`

When test mode is active:
- No real SMS messages are sent; credits are not consumed
- OTP codes are written to `wp-content/debug.log` in the format:
  ```
  2026-02-28 12:00:00 [kwtsms-otp TEST] SMS to 96599220322: Your login code is: 123456. Valid for 3 minutes.
  ```
- A prominent admin notice reminds you that test mode is active

## SMS Templates

Customise the SMS message text under **kwtsms OTP → Templates**.

### Available Templates

| Template | When Sent |
|----------|-----------|
| Login OTP | When a user requests a login verification code |
| Password Reset OTP | When a user requests a password reset via OTP |
| Welcome SMS | After a new user account is created (optional) |

### Placeholders

| Placeholder | Value |
|-------------|-------|
| `{code}` | The generated OTP code |
| `{site}` | Your WordPress site name |
| `{name}` | The user's display name (Welcome SMS only) |

### Character Limits

- English: 160 characters per SMS, 153 per multi-part message
- Arabic: 70 characters per SMS, 67 per multi-part message

## OTP Login Flows

### 2FA Mode

1. User enters username and password on `/wp-login.php`
2. If credentials are valid and the user has a registered phone, an OTP is sent via SMS
3. User is redirected to the OTP entry page
4. On correct OTP entry, the user is logged in

### Passwordless Mode

1. User clicks **Login with SMS OTP** on the login page
2. User enters their registered phone number and clicks **Send OTP Code**
3. An OTP is sent via SMS (same response shown whether phone is registered or not)
4. User enters the code and is logged in

### Password Reset via OTP

1. User clicks **Lost your password?**
2. User enters their username, email, or phone number
3. If a registered phone is found, an OTP is sent via SMS
4. User enters the code and is redirected to the standard WP "Reset Password" form
5. User sets a new password; login completes

## Security

- OTP codes use `random_int()` for cryptographically secure generation
- Verification uses `hash_equals()` (timing-safe comparison)
- All cookies are `httponly`, `secure` (when HTTPS), and `SameSite=Strict`
- Session tokens are 40-character random strings (via `wp_generate_password`)
- Rate limiting: 3 OTP requests per phone per 10 minutes
- Max verification attempts: 3 before session lockout
- Anti-enumeration on passwordless form (identical response for unknown phones)
- API credentials stored server-side only, never output to browser or JS

## Internationalisation

The plugin is fully translated into Arabic (`ar`).

To change the WordPress admin language to Arabic:
1. Go to **Settings → General**
2. Set **Site Language** to **Arabic**
3. Click **Save Changes**

RTL CSS is automatically loaded for Arabic and other right-to-left languages.

## Error Codes

| Code | Meaning | Action |
|------|---------|--------|
| ERR001 | Authentication failed | Check API username/password |
| ERR002 | Insufficient credits | Top up kwtsms account |
| ERR003 | Invalid sender ID | Check sender ID in Gateway Settings |
| ERR004 | Message too long | Shorten SMS template |
| ERR005 | Invalid phone number | User must enter a valid mobile number |
| ERR006 | Spam rejection | Contact kwtsms support |
| ERR007 | Queue error | Retry; contact kwtsms if persistent |

## Uninstall

Deleting the plugin via **Plugins → Installed Plugins → Delete** removes:
- All `kwtsms_otp_*` options from `wp_options`
- All `kwtsms_phone` user meta from `wp_usermeta`
- All `kwtsms_otp_*` transients (active OTPs, sessions, rate limits)

## Changelog

### 1.0.0

- Initial release
- 2FA login via SMS OTP
- Passwordless login via SMS OTP
- Password reset via SMS OTP (replaces email link)
- Admin settings (General, Gateway, Templates)
- Google reCAPTCHA v3 and Cloudflare Turnstile support
- Arabic (RTL) translation
- OTP send activity log
- Dashboard widget

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
