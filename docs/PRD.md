# PRD — kwtsms OTP Login & Password Reset for WordPress
**Version:** 1.0
**Date:** 2026-02-28
**Status:** Approved for implementation

---

## 1. Overview

### 1.1 Product Name
`wp-kwtsms-otp` — WordPress OTP Authentication Plugin powered by kwtsms SMS Gateway

### 1.2 Purpose
Replace or augment WordPress's default login and password reset flows with SMS-based One-Time Password (OTP) verification, delivered via the kwtsms JSON REST API. The plugin must be secure, accessible to Arabic-speaking users, extensible for the WordPress ecosystem (WooCommerce, third-party plugins), and compliant with kwtsms API best practices.

### 1.3 Target Audience
- WordPress site administrators who need secure SMS-based authentication
- Kuwait-based businesses requiring Arabic SMS support
- WooCommerce store owners (future phase)
- Multi-language WordPress sites (en_US + Arabic minimum)

---

## 2. Goals & Success Criteria

| Goal | Measurable Criterion |
|------|---------------------|
| Secure login via SMS OTP | Users can log in using OTP; brute force is rate-limited |
| Secure password reset via SMS | Email reset link replaced/supplemented by SMS OTP |
| Zero failed OTP sends due to phone format errors | Phone numbers normalized before API call |
| Meaningful error messages | Every API error code maps to a user-readable message |
| Arabic support | SMS templates work in Arabic; admin UI supports RTL |
| Admin configures everything | All settings in 3 admin pages, no code changes needed |
| Bot protection | CAPTCHA blocks automated OTP requests |
| Account visibility | Admin can see balance and verify gateway health |
| Test-safe | Test mode (`test=1`) prevents accidental SMS during dev |

---

## 3. Scope

### 3.1 In Scope (Phase 1)
- OTP Login: 2FA mode and Passwordless mode (admin-selectable)
- OTP Password Reset: Replace email link with SMS OTP verification
- Admin Pages: General Settings, Gateway Settings, SMS Templates
- Google reCAPTCHA v3 integration on OTP request forms
- Cloudflare Turnstile integration on OTP request forms
- Phone number storage in WordPress user meta
- Account balance display in Gateway Settings
- Test mode with configurable test phone number
- English + Arabic SMS templates with RTL admin editor
- Rate limiting and OTP attempt lockout
- Multilingual admin UI (en_US + Arabic)

### 3.2 Out of Scope (Phase 2+)
- WooCommerce checkout phone verification
- SMS notification for new orders, shipping, etc.
- Multi-gateway support (other SMS providers)
- Native mobile app support
- Delivery report tracking (DLR)

---

## 4. Admin Pages Specification

### 4.1 Page 1: General Settings
**Menu:** Settings → kwtsms OTP → General

| Field | Type | Default | Validation |
|-------|------|---------|-----------|
| Enable Login OTP | Toggle | On | — |
| OTP Mode | Radio | 2FA | — |
| Enable Password Reset OTP | Toggle | On | — |
| OTP Code Length | Select (4/6) | 6 | — |
| OTP Expiry (minutes) | Number | 3 | 1–10 |
| Max Verification Attempts | Number | 3 | 1–10 |
| Resend Cooldown (seconds) | Number | 60 | 30–300 |
| CAPTCHA Provider | Radio | None | — |
| reCAPTCHA Site Key | Text | — | Required if reCAPTCHA selected |
| reCAPTCHA Secret Key | Text | — | Required if reCAPTCHA selected |
| Turnstile Site Key | Text | — | Required if Turnstile selected |
| Turnstile Secret Key | Text | — | Required if Turnstile selected |

### 4.2 Page 2: Gateway Settings
**Menu:** Settings → kwtsms OTP → Gateway

| Field | Type | Notes |
|-------|------|-------|
| API Username | Text | Stored in wp_options, never exposed in HTML |
| API Password | Password | Stored in wp_options |
| Sender ID | Dropdown | Populated via AJAX from `/senderid/` endpoint on credential save |
| Test Mode | Toggle | Sends with `test=1`; real SMS not delivered |
| Test Phone Number | Text | Default: 96599220322; normalized on save |
| [Save & Verify Credentials] | Button | Calls `/senderid/`; populates dropdown; shows balance |
| Account Balance | Display | Current / Purchased credits from `/balance/` endpoint |
| API Status | Badge | Green (connected) / Red (error) with error description |

### 4.3 Page 3: SMS Templates
**Menu:** Settings → kwtsms OTP → Templates

Each template card contains:
- Enable/Disable toggle
- English message textarea (placeholder hints shown below)
- Arabic message textarea (`dir="rtl"`, `lang="ar"`)
- Live character counter (Arabic: 70/SMS, English: 160/SMS)
- Page count indicator (max 6 pages)
- Available `{placeholders}` listed below each field

**Templates:**

| Template ID | Trigger | Placeholders |
|-------------|---------|-------------|
| `login_otp` | OTP Login (2FA + Passwordless) | `{otp}`, `{site_name}`, `{expiry_minutes}` |
| `reset_otp` | Password Reset OTP | `{otp}`, `{site_name}`, `{expiry_minutes}` |
| `welcome_sms` | New User Registration (optional) | `{name}`, `{site_name}` |

**Default English template (login_otp):**
```
Your {site_name} login code is: {otp}
Valid for {expiry_minutes} minutes. Do not share this code.
```

**Default Arabic template (login_otp):**
```
رمز تسجيل الدخول إلى {site_name} هو: {otp}
صالح لمدة {expiry_minutes} دقائق. لا تشارك هذا الرمز.
```

---

## 5. OTP Flow Specification

### 5.1 2FA Login Flow
```
1. User submits username + password → WordPress validates credentials
2. If credentials valid → plugin intercepts via `authenticate` filter
3. Plugin stores partial auth token in transient (15-minute TTL)
4. User redirected to OTP entry page (wp-login.php?action=kwtsms_otp)
5. OTP generated (4–6 random digits), stored in transient (3-min TTL)
6. SMS sent via kwtsms JSON API to phone from user_meta
7. User enters OTP code → plugin verifies
8. OTP valid → delete transient → set auth cookies → redirect to admin/homepage
9. OTP invalid → increment attempt counter → show error
10. After max attempts → lockout for resend cooldown period
```

### 5.2 Passwordless Login Flow
```
1. User navigates to custom login page tab "Login with SMS"
2. User enters phone number
3. Plugin normalizes phone: strip +/00/spaces, convert Hindi→English digits
4. Plugin validates format (must be digits, country code required)
5. CAPTCHA verified (if enabled)
6. Plugin looks up user by phone in user_meta
7. If user found → send OTP → redirect to OTP entry page
8. If user not found → show generic message (no enumeration)
9. User enters OTP → verify → log in
```

### 5.3 Password Reset via OTP
```
1. User clicks "Lost Password?" on wp-login.php
2. Custom form: enter username or phone number
3. User found → OTP sent to phone in user_meta
4. User enters OTP → verified
5. OTP valid → show new password + confirm password form
6. Password saved → user logged in automatically
```

### 5.4 OTP Storage Schema
```
Transient key:   kwtsms_otp_{md5(user_id)}
Transient value: {
    "code":     "123456",
    "attempts": 0,
    "phone":    "96599220322",
    "action":   "login" | "reset" | "passwordless"
}
TTL: configured OTP expiry in seconds (default: 180s)

Rate limit key:  kwtsms_otp_rate_{md5(phone)}
Rate limit value: request_count
TTL: 10 minutes
```

---

## 6. Phone Number Normalization (Critical)

Per kwtsms best practices and API validation rules:

```
Input Examples → Normalized Output:
+96599220322    → 96599220322
0096599220322   → 96599220322
965 9922 0322   → 96599220322
965-9922-0322   → 96599220322
٩٦٥٩٩٢٢٠٣٢٢    → 96599220322  (Hindi/Arabic numerals → English)
9922 0322       → 9922 0322    (no country code → prompt user)
96522334455     → ERROR: landline not supported for OTP
123456          → ERROR: invalid format

Rules:
1. Strip leading + or 00
2. Convert Arabic/Hindi numerals (٠١٢٣٤٥٦٧٨٩) to (0123456789)
3. Remove all non-digit characters (spaces, dashes, dots, parentheses)
4. Validate minimum 8 digits, maximum 15 digits
5. Warn if number does not start with country code (no leading 0)
6. Use /validate/ API endpoint for final validation before sending
```

---

## 7. Error Handling & User Messages

### 7.1 API Error Code → User Message Mapping

| API Code | Internal Meaning | User-Facing Message (EN) | User-Facing Message (AR) |
|----------|-----------------|--------------------------|--------------------------|
| ERR001 | API disabled | Service temporarily unavailable | الخدمة غير متوفرة مؤقتاً |
| ERR003 | Wrong credentials | Gateway configuration error. Contact admin. | خطأ في إعداد البوابة. تواصل مع المشرف. |
| ERR004 | No API access | Gateway not enabled. Contact admin. | البوابة غير مفعلة. تواصل مع المشرف. |
| ERR005 | Account blocked | Gateway account suspended. Contact admin. | الحساب موقوف. تواصل مع المشرف. |
| ERR009 | Empty message | Template is empty. Contact admin. | قالب الرسالة فارغ. تواصل مع المشرف. |
| ERR010 | Zero balance | Insufficient SMS credits. Contact admin. | رصيد غير كافٍ. تواصل مع المشرف. |
| ERR011 | Not enough balance | Insufficient SMS credits. Contact admin. | رصيد غير كافٍ. تواصل مع المشرف. |
| ERR024 | IP lockdown | Request blocked. Contact admin. | الطلب محجوب. تواصل مع المشرف. |
| ERR025 | Invalid number | Phone number is not valid. | رقم الهاتف غير صحيح. |
| ERR028 | Resend too fast | Please wait 15 seconds before requesting another code. | انتظر 15 ثانية قبل طلب رمز جديد. |
| ERR031/032 | Spam/bad content | Message rejected. Contact admin. | الرسالة مرفوضة. تواصل مع المشرف. |
| — | OTP expired | Your code has expired. Request a new one. | انتهت صلاحية الرمز. اطلب رمزاً جديداً. |
| — | OTP invalid | Incorrect code. X attempts remaining. | رمز غير صحيح. X محاولات متبقية. |
| — | Max attempts | Too many failed attempts. Please wait X minutes. | محاولات كثيرة. انتظر X دقائق. |
| — | Rate limited | Too many requests. Please wait before trying again. | طلبات كثيرة. انتظر قبل المحاولة مجدداً. |
| — | Phone not found | If an account exists for this phone, an OTP will be sent. | إذا كان هناك حساب بهذا الرقم، سيُرسَل رمز. |
| — | No phone on file | No phone number on this account. Use email reset. | لا يوجد رقم هاتف. استخدم إعادة التعيين بالبريد. |

> **Note:** Always log full API errors server-side. Never expose raw API error codes to end users.

### 7.2 Input Validation Messages

| Condition | User Message (EN) |
|-----------|------------------|
| Empty phone field | Phone number is required. |
| Invalid format | Please enter a valid phone number with country code (e.g. 96599220322). |
| Landline detected | This number cannot receive SMS. Please use a mobile number. |
| Arabic numerals detected | Auto-converted to: {normalized}. Is this correct? |
| OTP field empty | Please enter the verification code. |
| OTP not 4/6 digits | Please enter a {length}-digit code. |

---

## 8. Security Requirements

| Requirement | Implementation |
|-------------|---------------|
| HTTPS only | Plugin displays admin notice if site is not HTTPS |
| Credentials never in HTML | API credentials read server-side only, never output to browser |
| Nonce on all forms | `wp_create_nonce` / `wp_verify_nonce` on every form submission |
| Capability checks | `current_user_can('manage_options')` on all admin actions |
| Input sanitization | `sanitize_text_field()` on all inputs; phone via custom normalizer |
| Output escaping | `esc_html()`, `esc_attr()`, `esc_url()` on all output |
| OTP single-use | Transient deleted immediately on successful verification |
| Rate limiting | Max 3 OTP requests per phone per 10 minutes (transient counter) |
| Attempt lockout | Max 3 failed verifications before timed lockout |
| Anti-enumeration | Password reset shows same message regardless of user existence |
| CAPTCHA | reCAPTCHA v3 or Cloudflare Turnstile on OTP request form |
| Test mode | `test=1` parameter prevents real SMS delivery during development |
| Partial auth tokens | Stored in transients, not sessions; expire in 15 minutes |

---

## 9. Multilingual Requirements

- Text domain: `wp-kwtsms-otp`
- All user-facing strings wrapped in `__()` / `_e()` / `esc_html__()`
- All JS strings localized via `wp_localize_script()`
- Languages delivered:
  - `wp-kwtsms-otp-en_US.po/.mo`
  - `wp-kwtsms-otp-ar.po/.mo`
- RTL CSS loaded when `is_rtl()` returns true
- Arabic SMS templates stored separately; plugin detects `get_locale()` to choose template
- Admin SMS template editor: Arabic textarea has `dir="rtl"` and Arabic font stack

---

## 10. Extensibility (Developer API)

Third-party plugins and themes can integrate via these hooks:

```php
// Filter: modify OTP before it's sent
apply_filters( 'kwtsms_otp_before_send', $otp_data, $user, $action );

// Filter: modify SMS message before sending
apply_filters( 'kwtsms_otp_message', $message, $user, $action, $locale );

// Filter: modify phone number after normalization
apply_filters( 'kwtsms_otp_phone_number', $phone, $user );

// Action: fires after OTP is successfully verified
do_action( 'kwtsms_otp_verified', $user, $action );

// Action: fires after OTP send fails
do_action( 'kwtsms_otp_send_failed', $user, $action, $error_code );

// Filter: customize the OTP page template
apply_filters( 'kwtsms_otp_login_template', $template_path );
```

---

## 11. Testing Credentials & Test Mode

### Development Testing
- **Test phone:** `96599220322` (configured in Gateway Settings)
- **Test mode:** Enable toggle in Gateway Settings → sends with `test=1` (no real SMS, credits saved)
- **Test flow:** OTP is generated and stored; in test mode, OTP is also written to the WordPress debug log for developer verification
- **API credentials:** Add in Gateway Settings page; credentials stored encrypted in `wp_options`

### Where to Add Credentials
Go to **wp-admin → Settings → kwtsms OTP → Gateway**:
1. Enter your kwtsms API Username
2. Enter your kwtsms API Password
3. Click **Save & Verify Credentials**
4. The Sender ID dropdown will auto-populate from the API
5. Select your Sender ID
6. Enable **Test Mode** for development
7. Confirm Test Phone is set to `96599220322`

---

## 12. kwtsms API Best Practices Compliance

This plugin is built in full compliance with:
- [kwtsms SMS API Implementation Best Practices](https://www.kwtsms.com/articles/sms-api-implementation-best-practices.html)
- [kwtsms SMS API Integration Test Checklist](https://www.kwtsms.com/articles/sms-api-integration-test-checklist.html)
- kwtsms API Documentation v4.1

Key compliance points:
1. Always use HTTPS + POST for all API requests
2. Credentials stored in wp_options, never hardcoded
3. Phone numbers normalized and validated before API call
4. Site name included in every OTP message (telecom compliance)
5. 3-minute OTP expiry; 60-second resend cooldown minimum
6. CAPTCHA on OTP request form
7. Rate limiting: 3–5 OTP requests per phone per 10 minutes
8. Hindi/Arabic numerals converted to English before sending
9. SMS content sanitized (no emoji, no unsupported HTML)
10. API error codes mapped to meaningful user messages
11. Balance checked from send response, not separate calls
12. Transactional SenderID recommended in admin notices

---

## 13. Non-Functional Requirements

| Category | Requirement |
|----------|-------------|
| PHP Compatibility | PHP 7.4+ |
| WordPress Compatibility | WordPress 6.0+ |
| WooCommerce | Optional dependency; graceful fallback |
| Coding Standards | WordPress Coding Standards (WPCS) |
| Documentation | PHPDoc on all public methods and classes |
| Plugin Size | < 500 KB (no bundled frameworks) |
| External Requests | Only to `https://www.kwtsms.com/API/` |
| Data Stored | `wp_options` (settings), `wp_usermeta` (phone), `wp_options` (transients) |
| Uninstall | `uninstall.php` removes all plugin data from DB |
