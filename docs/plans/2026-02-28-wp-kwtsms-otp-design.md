# Implementation Plan — wp-kwtsms-otp WordPress Plugin
**Version:** 1.0
**Date:** 2026-02-28
**References:**
- [`docs/PRD.md`](./PRD.md)
- [`docs/KwtSMS.com_API_Documentation_v41.pdf`](./KwtSMS.com_API_Documentation_v41.pdf)
- [kwtsms Best Practices](https://www.kwtsms.com/articles/sms-api-implementation-best-practices.html)
- [kwtsms Test Checklist](https://www.kwtsms.com/articles/sms-api-integration-test-checklist.html)

---

## Workflow Rules (Must Follow)

1. **Research first.** Read relevant WP/kwtsms docs before writing any code.
2. **Implement one task at a time.** Do not skip ahead.
3. **Test locally (Docker) before committing.**
4. **Commit to git after every successful local test.**
5. **STOP after each task.** Present a complete walkthrough and test instructions.
6. **Wait for user review and approval before starting the next task.**
7. After approval: test on live platform, commit again.

---

## Branding (use throughout admin UI and login page styles)

| Token | Value |
|-------|-------|
| Primary color | `#FFA200` (orange) |
| Accent color | `#79CCF2` (light blue) |
| Background | `#FFFFFF` |
| Text primary | `#434345` |
| Link color | `#79CCF2` |
| Body font | Lato, Helvetica, Arial, sans-serif |
| Heading font | Montserrat, sans-serif |
| Border radius | `4px` (inputs: `0px`) |
| Logo | `https://www.kwtsms.com/images/kwtsms_logo_60.png` |

---

## Development Environment

### Local Setup (Docker)
```bash
# From plugin root (wordpress/)
docker-compose up -d
# WordPress: http://localhost:8080
# WP Admin:  http://localhost:8080/wp-admin  (admin / admin)
# phpMyAdmin: http://localhost:8081

# Activate plugin via WP-CLI
docker exec <wp-container> wp plugin activate wp-kwtsms-otp --allow-root

# Run PHPUnit tests
docker exec <wp-container> composer test --allow-root

# Update translations POT
docker exec <wp-container> wp i18n make-pot wp-content/plugins/wp-kwtsms-otp languages/wp-kwtsms-otp.pot --allow-root
```

### Test Credentials
- **Add in:** wp-admin → Settings → kwtsms OTP → Gateway
- **Test phone:** `96598765432`
- **Test mode:** Enable in Gateway Settings (uses `test=1`, no real SMS)
- **Debug log:** OTP code written to `wp-content/debug.log` when `WP_DEBUG_LOG=true` AND test mode is ON

---

## Phase 1: Foundation

---

### Task 1.1 — Scaffold Plugin & Docker Environment

**Goal:** Create a runnable WordPress environment with the plugin skeleton installed.

**Steps:**
1. Create plugin directory: `wp-kwtsms-otp/`
2. Create main plugin file `wp-kwtsms-otp.php` with WordPress plugin headers
3. Create `composer.json` (WPCS, PHPStan, PHPUnit)
4. Create `docker-compose.yml` (WordPress 6.x + MySQL + phpMyAdmin)
5. Create `uninstall.php` (stub — removes all options on uninstall)
6. Create `.gitignore` (vendor/, node_modules/, *.mo, docker volumes)
7. Verify plugin appears in WordPress admin plugin list
8. Verify plugin activates without PHP errors

**Files created:**
```
wp-kwtsms-otp/
├── wp-kwtsms-otp.php
├── uninstall.php
├── composer.json
├── .gitignore
docker-compose.yml
```

**Test:**
```bash
docker-compose up -d
# Visit http://localhost:8080/wp-admin/plugins.php
# Confirm "kwtsms OTP" plugin listed and activatable
```

**Commit message:** `feat: scaffold plugin and Docker dev environment`

---

### Task 1.2 — kwtsms API Client Class

**Goal:** Implement a reusable, well-documented PHP class that wraps all kwtsms JSON REST API calls.

**Steps:**
1. Create `includes/class-kwtsms-api.php`
2. Implement constructor: accept `$username`, `$password`, `$test_mode`
3. Implement private `request( $endpoint, $payload )` method:
   - Always uses `wp_remote_post()` (never GET)
   - Always sets `Content-Type: application/json` and `Accept: application/json`
   - Always uses HTTPS
   - Never logs credentials
4. Implement public methods:
   - `get_balance()` → `['available' => float, 'purchased' => float]` or `WP_Error`
   - `get_sender_ids()` → `string[]` or `WP_Error`
   - `send_sms( $phone, $sender_id, $message )` → `['msg_id' => string, 'balance_after' => float]` or `WP_Error`
   - `validate_number( $phone )` → `'OK'|'ER'|'NR'` or `WP_Error`
5. Implement `map_error_code( $code )` → returns user-facing translated error message (all ERR001–ERR033 codes mapped per PRD §7.1)
6. Implement phone normalizer: `normalize_phone( $phone )`:
   - Strip leading `+` or `00`
   - Convert Arabic/Hindi numerals to English
   - Remove spaces, dashes, dots, parentheses
   - Validate result is digits only, 8–15 chars
7. Write PHPUnit tests:
   - `test_normalize_phone_strips_plus()`
   - `test_normalize_phone_strips_double_zero()`
   - `test_normalize_phone_converts_hindi_numerals()`
   - `test_normalize_phone_removes_spaces_and_dashes()`
   - `test_map_error_code_returns_translated_string()`
   - `test_send_sms_test_mode_does_not_call_real_api()` (mock `wp_remote_post`)

**Files created:**
```
includes/class-kwtsms-api.php
tests/test-api.php
```

**Test:**
```bash
# Unit tests
docker exec <container> composer test

# Manual test (will not send real SMS — test mode on):
# Go to Settings → kwtsms OTP → Gateway
# Enter API credentials → click Save & Verify
# Confirm: SenderID dropdown populates, balance shows
```

**Commit message:** `feat: implement kwtsms API client with phone normalizer and error mapping`

---

### Task 1.3 — OTP Engine

**Goal:** Implement OTP generation, storage, verification, and rate limiting using WordPress Transients API.

**Steps:**
1. Create `includes/class-kwtsms-otp-engine.php`
2. Implement `generate( $user_id_or_phone, $action )`:
   - Generates cryptographically random 4–6 digit code (`wp_rand` or `random_int`)
   - Key: `kwtsms_otp_{md5($identifier)}`
   - Value: `['code' => string, 'attempts' => 0, 'action' => string, 'created' => time()]`
   - TTL: from settings (default 180 seconds)
   - Overwrites any existing OTP for same identifier
3. Implement `verify( $identifier, $code )`:
   - Retrieves transient
   - Checks expiry manually (transient may still exist at boundary)
   - Compares `hash_equals()` (timing-safe comparison)
   - Increments attempt counter
   - Returns: `'valid'`, `'invalid'`, `'expired'`, `'max_attempts'`
   - On `'valid'`: deletes transient immediately
   - On `'max_attempts'`: sets lockout transient
4. Implement `is_rate_limited( $phone )`:
   - Transient key: `kwtsms_otp_rate_{md5($phone)}`
   - Max 3 requests per 10 minutes per phone
   - Returns `bool`
5. Implement `increment_rate( $phone )`: increments counter transient
6. Implement `get_remaining_attempts( $identifier )`: returns int
7. Implement `get_resend_available_in( $identifier )`: returns seconds until resend allowed
8. Write PHPUnit tests for all methods

**Files created:**
```
includes/class-kwtsms-otp-engine.php
tests/test-otp-engine.php
```

**Test:**
```bash
docker exec <container> composer test
```

**Commit message:** `feat: implement OTP engine (generate/verify/rate-limit)`

---

### Task 1.4 — Plugin Settings Storage

**Goal:** Register all plugin settings in WordPress Options API with proper sanitization.

**Steps:**
1. Create `includes/class-kwtsms-settings.php`
2. Register option groups and settings via `register_setting()` with sanitize callbacks:
   - `kwtsms_otp_general` (group): otp_mode, otp_length, otp_expiry, max_attempts, resend_cooldown, login_otp_enabled, reset_otp_enabled, captcha_provider, recaptcha_site_key, recaptcha_secret_key, turnstile_site_key, turnstile_secret_key
   - `kwtsms_otp_gateway` (group): api_username, api_password, sender_id, test_mode, test_phone
   - `kwtsms_otp_templates` (group): templates array per template ID (en, ar, enabled)
3. Implement `get( $key, $default = null )` helper
4. Implement `set( $key, $value )` helper
5. Implement `get_all_templates()` → returns templates array with defaults if not set
6. Define default values as class constants
7. Sanitize callbacks:
   - Phone numbers: use `KwtSMS_API::normalize_phone()`
   - Text fields: `sanitize_text_field()`
   - HTML textarea (templates): `wp_kses_post()`
   - Integers: `absint()`
   - Booleans: `(bool) $value`
   - API password: encrypt with `wp_hash( $value )` for storage, expose only via server-side variable (never in HTML output)

**Files created:**
```
includes/class-kwtsms-settings.php
tests/test-settings.php
```

**Commit message:** `feat: register plugin settings with sanitization`

---

## Phase 2: Admin UI

---

### Task 2.1 — Admin Menu & Page Router

**Goal:** Create the three admin pages under a unified menu.

**Steps:**
1. Create `admin/class-kwtsms-admin.php`
2. Hook `admin_menu` → register top-level menu:
   - Icon: kwtsms logo (`https://www.kwtsms.com/images/kwtsms_logo_60.png`) as base64 SVG fallback
   - Menu title: "kwtsms OTP"
   - Subpages: General, Gateway, Templates
3. Hook `admin_enqueue_scripts` → load `admin.css` and `admin.js` only on plugin pages
4. Load `admin/views/page-general.php`, `page-gateway.php`, `page-templates.php` per page
5. Add admin notices:
   - Site not HTTPS: warning banner
   - Credentials not set: prompt to configure gateway
   - Low balance (< 10 credits): warning
   - Test mode active: info banner

**Files created:**
```
admin/class-kwtsms-admin.php
admin/views/page-general.php    (stub)
admin/views/page-gateway.php    (stub)
admin/views/page-templates.php  (stub)
assets/css/admin.css            (stub)
assets/js/admin.js              (stub)
```

**Branding applied:**
- Menu accent: `#FFA200`
- Admin page headings: Montserrat, `#434345`
- Save buttons: `background: #FFA200; color: #fff; border-radius: 4px`
- Links: `#79CCF2`

**Test:**
```
wp-admin → verify "kwtsms OTP" menu appears with 3 subpages
```

**Commit message:** `feat: register admin menu with 3 subpages and admin notices`

---

### Task 2.2 — Gateway Settings Page

**Goal:** Build the Gateway Settings page with live credential verification and balance display.

**Steps:**
1. Build `admin/views/page-gateway.php`:
   - Settings form: API Username, API Password (type="password"), Test Mode toggle, Test Phone
   - Sender ID: `<select>` populated from saved option; "Verify & Reload" button triggers AJAX fetch
   - Account Balance: `<div id="kwtsms-balance">` updated via AJAX
   - API Status badge: green ✓ / red ✗ with error message
2. Build AJAX handler (`wp_ajax_kwtsms_verify_credentials`):
   - Verify nonce
   - Check `current_user_can('manage_options')`
   - Instantiate `KwtSMS_API` with submitted credentials (not yet saved)
   - Call `get_sender_ids()` and `get_balance()`
   - Return JSON: `{success, senderids[], balance, error_message}`
3. Build `admin.js`:
   - On "Save & Verify" button click: submit form via AJAX
   - On success: populate `<select>` with sender IDs, show balance
   - On error: show user-friendly error message (map from PRD §7.1)
   - Show/hide reCAPTCHA/Turnstile key fields based on CAPTCHA provider radio selection
4. Show kwtsms logo in page header

**Test:**
```
1. Enter valid API credentials → Save & Verify
   Expected: Sender ID dropdown populates, balance shows
2. Enter invalid credentials → Save & Verify
   Expected: Error message "Gateway configuration error. Contact admin."
3. Enter correct credentials, wrong SenderID → Save
   Expected: Admin notice warning about SenderID selection
```

**Commit message:** `feat: gateway settings page with live credential verification and balance`

---

### Task 2.3 — General Settings Page

**Goal:** Build the General Settings page with CAPTCHA provider selection and OTP behavior options.

**Steps:**
1. Build `admin/views/page-general.php` using WordPress Settings API
2. Sections:
   - OTP Behavior: mode (2FA/Passwordless/Both), length, expiry, max attempts, resend cooldown
   - Login OTP: enable/disable toggle
   - Password Reset OTP: enable/disable toggle
   - CAPTCHA: radio (None/reCAPTCHA v3/Cloudflare Turnstile); conditional key fields
3. JS show/hide CAPTCHA key fields based on radio selection
4. Validate: if CAPTCHA provider selected, both keys required

**Commit message:** `feat: general settings page with CAPTCHA provider configuration`

---

### Task 2.4 — SMS Templates Page

**Goal:** Build the template editor with multilingual support, character counter, and placeholder hints.

**Steps:**
1. Build `admin/views/page-templates.php`
2. For each template (login_otp, reset_otp, welcome_sms):
   - Card with: enable toggle, English textarea, Arabic textarea (`dir="rtl"`)
   - Placeholder reference list below each textarea
   - Live character counter (JS): English 160/SMS, Arabic 70/SMS
   - Page count indicator: `Math.ceil(chars / chars_per_sms)` displayed as "1 SMS / 2 SMS pages"
3. JS character counter hook on `input` events for both textareas
4. Sanitize template content on save (strip HTML tags, emojis, disallowed characters)
5. Show default templates if not yet customized

**Test:**
```
1. Edit login_otp English template → save → verify saved text appears on reload
2. Edit Arabic template → verify RTL display in textarea
3. Type > 160 chars in English template → counter turns red, shows "2 SMS pages"
4. Type emoji in template → emoji stripped on save
```

**Commit message:** `feat: SMS template editor with character counter and RTL Arabic support`

---

## Phase 3: Core Authentication Flows

---

### Task 3.1 — User Phone Number Field (User Meta)

**Goal:** Add a phone number field to user profiles for OTP delivery (used by 2FA and password reset).

**Steps:**
1. Create `includes/class-kwtsms-user-meta.php`
2. Hook `show_user_profile` and `edit_user_profile` → render phone number field:
   - Label: "Phone Number (for SMS OTP)"
   - Input: type="tel", placeholder="e.g. 96598765432"
   - Arabic label if `is_rtl()`
3. Hook `personal_options_update` and `edit_user_profile_update` → save:
   - Verify nonce, sanitize, normalize via `KwtSMS_API::normalize_phone()`
   - Validate via `KwtSMS_API::validate_number()` (optional, admin-configurable)
   - Save to `user_meta` key: `kwtsms_phone`
4. Admin user list: add "Phone" column (optional, admin-toggled)
5. Sanitize and normalize on save; show error notice if format invalid

**Test:**
```
1. Go to wp-admin → Users → Edit user
   Expected: "Phone Number (for SMS OTP)" field appears
2. Enter +96598765432 → save
   Expected: Saved as 96598765432 (normalized)
3. Enter ٩٦٥٩٩٢٢٠٣٢٢ (Hindi numerals) → save
   Expected: Converted to 96598765432
4. Enter invalid number (abc) → save
   Expected: Error notice, not saved
```

**Commit message:** `feat: user phone number field with normalization and validation`

---

### Task 3.2 — OTP Login (2FA Mode)

**Goal:** Intercept WordPress login after successful password validation; require OTP before issuing auth cookies.

**Steps:**
1. Create `includes/class-kwtsms-login-otp.php`
2. Hook `authenticate` filter (priority 30, after WordPress default auth at 20):
   - If result is `WP_User` (credentials valid) AND 2FA is enabled:
     - Get phone from user_meta
     - If no phone: show error "No phone number on account" OR bypass OTP (admin-configurable)
     - Store partial auth: `set_transient('kwtsms_partial_auth_{session_token}', $user_id, 900)`
     - Store session token in cookie (httponly, secure, SameSite=Strict)
     - Return `WP_Error` to prevent immediate login
     - Redirect to OTP entry page
3. Register custom action `wp-login.php?action=kwtsms_otp`:
   - Hook `login_form_{action}` or use `wp-login.php` `$action` parsing
   - Render OTP entry form: code input, resend button with countdown
   - Show CAPTCHA if configured
   - Apply kwtsms branding: `#FFA200` primary, Lato body font
4. Handle OTP form submission (`POST`):
   - Verify nonce
   - Retrieve partial auth session
   - Normalize and validate OTP input (digits only, correct length)
   - Call `KwtSMS_OTP_Engine::verify()`
   - On `'valid'`: delete partial auth transient, issue auth cookies, redirect
   - On `'invalid'`: show "Incorrect code. X attempts remaining."
   - On `'expired'`: show "Code expired. Request a new one." with resend button
   - On `'max_attempts'`: show "Too many attempts. Wait X minutes."
5. Handle resend AJAX (`wp_ajax_nopriv_kwtsms_resend_otp`):
   - Verify nonce, check rate limit
   - Generate new OTP, send SMS
   - Return countdown timer value
6. Enqueue `assets/js/login.js` and `assets/css/login.css` on login page only
7. Login JS: countdown timer for resend button; prevent double-submit

**Files created:**
```
includes/class-kwtsms-login-otp.php
assets/js/login.js
assets/css/login.css
assets/css/login-rtl.css
tests/test-login-otp.php
```

**Test sequence:**
```
Test 3.2.1 — Happy path (2FA):
1. Enable 2FA mode in General Settings
2. Add phone number to test user profile (96598765432)
3. Enable test mode, set test phone to 96598765432
4. Log in with valid username + password
5. Expected: Redirect to OTP page
6. Check wp-content/debug.log for OTP code
7. Enter correct code → Expected: Logged in, redirected to dashboard

Test 3.2.2 — Wrong OTP:
1. Log in → OTP page
2. Enter wrong code (999999)
3. Expected: "Incorrect code. 2 attempts remaining."

Test 3.2.3 — Expired OTP:
1. Log in → OTP page
2. Wait for OTP expiry + 10s (configurable, default 3 min)
3. Enter correct code
4. Expected: "Your code has expired. Request a new one."

Test 3.2.4 — Resend:
1. Log in → OTP page
2. Click Resend → Expected: Button disabled with countdown timer
3. After countdown → click Resend again
4. Expected: New OTP sent (check debug.log), old OTP invalid

Test 3.2.5 — Rate limiting:
1. Request OTP 4 times rapidly for same phone
2. Expected: "Too many requests. Please wait before trying again."

Test 3.2.6 — Hindi numeral phone:
1. Set user phone to ٩٦٥٩٩٢٢٠٣٢٢ in profile
2. Log in with 2FA
3. Expected: Phone normalized to 96598765432, OTP sent successfully
```

**Commit message:** `feat: OTP 2FA login flow with partial auth, resend, and rate limiting`

---

### Task 3.3 — OTP Login (Passwordless Mode)

**Goal:** Add a "Login with SMS" tab on the login page for passwordless OTP login.

**Steps:**
1. Hook `login_form` → inject "Login with SMS" tab/link below login form
2. Register action `wp-login.php?action=kwtsms_passwordless`:
   - Form: phone number input + CAPTCHA + submit
   - On submit:
     - Normalize phone → validate format
     - Check rate limit
     - Verify CAPTCHA
     - Query user by `kwtsms_phone` meta: `get_users(['meta_key' => 'kwtsms_phone', 'meta_value' => $phone])`
     - If user found: generate OTP, send SMS, redirect to OTP verification page
     - If not found: show same generic message (anti-enumeration)
   - OTP verification form: same as 2FA step (reuse from Task 3.2)
   - On valid OTP: issue auth cookies, redirect

**Test sequence:**
```
Test 3.3.1 — Happy path (Passwordless):
1. Enable Passwordless mode in General Settings
2. Ensure test user has phone 96598765432
3. Visit wp-login.php → Click "Login with SMS"
4. Enter 96598765432 → submit
5. Check debug.log for OTP
6. Enter OTP → Expected: Logged in

Test 3.3.2 — Unknown phone:
1. Enter unknown phone number
2. Expected: "If an account exists for this phone, an OTP will be sent." (same message)
3. No SMS sent, no error in debug.log

Test 3.3.3 — Phone with +/spaces:
1. Enter "+965 9922 0322" → submit
2. Expected: Normalized to 96598765432, OTP sent
```

**Commit message:** `feat: passwordless SMS login flow`

---

### Task 3.4 — Password Reset via OTP

**Goal:** Replace the default WordPress email password reset with SMS OTP verification.

**Steps:**
1. Create `includes/class-kwtsms-reset-otp.php`
2. Hook `lostpassword_post` to intercept and handle:
   - If reset OTP enabled: suppress default email reset
   - Accept username OR phone number in the form
   - Resolve user: try `get_user_by('login')`, then `get_user_by('email')`, then by phone meta
   - If user found and has phone: generate OTP, send SMS
   - Redirect to OTP verification step
3. Inject phone number field into lost password form via `login_form_lostpassword`
4. Register OTP verification step for reset flow:
   - After OTP verified: redirect to password reset form
   - Hook `password_reset` to mark OTP as consumed
5. After successful password change: auto-login user
6. If user has no phone and email reset is enabled: fall back to email reset with notice "No phone on file, reset email sent"

**Test sequence:**
```
Test 3.4.1 — Happy path (Reset via OTP):
1. Enable Reset OTP in General Settings
2. Ensure user has phone 96598765432
3. Click "Lost your password?" → enter username
4. Check debug.log for OTP
5. Enter OTP → shown password reset form
6. Enter new password → save
7. Expected: Auto-logged in, redirected to dashboard

Test 3.4.2 — No phone on file:
1. User with no phone meta
2. Click "Lost your password?" → enter username
3. Expected: "No phone number on this account. A password reset email has been sent."

Test 3.4.3 — Wrong OTP during reset:
1. Request reset OTP
2. Enter wrong code
3. Expected: "Incorrect code. 2 attempts remaining."
```

**Commit message:** `feat: password reset via SMS OTP with email fallback`

---

## Phase 4: Security Layer (CAPTCHA)

---

### Task 4.1 — Google reCAPTCHA v3

**Goal:** Add invisible reCAPTCHA v3 to the OTP request form (login and reset).

**Steps:**
1. Create `includes/class-kwtsms-captcha.php`
2. Implement `enqueue_recaptcha_v3()`: loads Google reCAPTCHA script with site key
3. Implement `render_recaptcha_field()`: hidden input for reCAPTCHA token
4. Implement `verify_recaptcha_v3( $token )`:
   - POST to `https://www.google.com/recaptcha/api/siteverify`
   - Check `success` and `score` (threshold: 0.5)
   - Returns `true` / `WP_Error` with user message
5. Hook: add to OTP request form submission handlers
6. Enqueue script only if provider = reCAPTCHA and on login/reset pages

**Test:**
```
1. Enable reCAPTCHA v3 in General Settings with valid keys
2. Attempt to request OTP from login page
3. Expected: reCAPTCHA badge visible in corner
4. Expected: OTP sent only if score >= 0.5
5. Simulate low score via test key → Expected: OTP blocked
```

**Commit message:** `feat: Google reCAPTCHA v3 on OTP request forms`

---

### Task 4.2 — Cloudflare Turnstile

**Goal:** Add Cloudflare Turnstile widget to OTP request forms as an alternative to reCAPTCHA.

**Steps:**
1. Add to `class-kwtsms-captcha.php`:
2. Implement `enqueue_turnstile()`: loads Turnstile script
3. Implement `render_turnstile_widget()`: `<div class="cf-turnstile" data-sitekey="...">` with kwtsms branding colors
4. Implement `verify_turnstile( $token )`:
   - POST to `https://challenges.cloudflare.com/turnstile/v0/siteverify`
   - Returns `true` / `WP_Error`
5. Conditional loading based on settings

**Test:**
```
1. Enable Turnstile in General Settings with valid keys
2. Visit OTP request page → Turnstile widget appears
3. Complete Turnstile → OTP sent
4. Inspect page source → site key not exposed in JS variables alongside secret key
```

**Commit message:** `feat: Cloudflare Turnstile CAPTCHA on OTP request forms`

---

## Phase 5: Internationalization

---

### Task 5.1 — i18n Setup & POT File

**Goal:** Extract all translatable strings and generate .pot, .po, .mo files for English and Arabic.

**Steps:**
1. Audit all PHP files: wrap every user-facing string in `__()`, `_e()`, `esc_html__()`
2. Audit all JS files: use `kwtsmsOtpData.strings.*` localized via `wp_localize_script()`
3. Ensure Arabic numerals note in phone field is translatable
4. Generate POT:
   ```bash
   wp i18n make-pot . languages/wp-kwtsms-otp.pot --domain=wp-kwtsms-otp
   ```
5. Create `wp-kwtsms-otp-ar.po` with Arabic translations for all strings
6. Compile to `.mo`:
   ```bash
   wp i18n make-mo languages/
   ```
7. Verify `load_plugin_textdomain()` called on `plugins_loaded`
8. Test RTL: `is_rtl()` loads `login-rtl.css` and `admin-rtl.css`

**Arabic translations to include (minimum):**
- All OTP verification form strings
- All admin page labels and descriptions
- All error messages from PRD §7.1
- All input validation messages from PRD §7.2
- SMS default templates (in template settings)

**Test:**
```
1. Set WordPress language to Arabic (ar) in Settings → General
2. Visit login page → OTP step in Arabic, RTL layout
3. Visit wp-admin → kwtsms OTP settings in Arabic, RTL
```

**Commit message:** `feat: i18n setup with English and Arabic translations (RTL support)`

---

## Phase 6: Testing & Quality

---

### Task 6.1 — PHPUnit Test Suite

**Goal:** Full unit test coverage for all core classes.

**Test files:**
```
tests/
├── bootstrap.php
├── test-api.php           # API client + phone normalizer + error mapper
├── test-otp-engine.php    # OTP generate/verify/rate-limit
├── test-settings.php      # Option registration + sanitize callbacks
├── test-login-otp.php     # 2FA and passwordless flows (WP_Mock)
└── test-reset-otp.php     # Password reset flow
```

**Run tests:**
```bash
docker exec <container> composer test
```

**Coverage targets:**
- `KwtSMS_API`: 90%+ (phone normalizer, error mapping, API request mocking)
- `KwtSMS_OTP_Engine`: 95%+ (generate, verify, rate limit, edge cases)
- `KwtSMS_Settings`: 80%+ (sanitize callbacks)

**Commit message:** `test: complete PHPUnit suite for API, OTP engine, settings`

---

### Task 6.2 — Integration Test Checklist (kwtsms Test Checklist Compliance)

Following the [kwtsms SMS API Integration Test Checklist](https://www.kwtsms.com/articles/sms-api-integration-test-checklist.html):

**Must test before any live deployment:**

| Test | Expected Result |
|------|----------------|
| English SMS sent | Arrives within 60s, readable, includes site name |
| Arabic SMS sent | Arabic displays correctly, not reversed/garbled |
| Phone: `+96598765432` | Normalized and sent successfully |
| Phone: `0096598765432` | Normalized and sent successfully |
| Phone: `965 9922 0322` | Spaces stripped, sent successfully |
| Phone: `965-9922-0322` | Dashes stripped, sent successfully |
| Phone: `٩٦٥٩٩٢٢٠٣٢٢` | Hindi numerals converted, sent |
| Phone: `96522334455` (landline) | Error: "must use a mobile number" |
| Phone: `123456` (invalid) | Error: "Please enter a valid phone number" |
| Phone: `my-email@gmail.com` | Error: "Please enter a valid phone number" |
| Wrong OTP entered | "Incorrect code. X attempts remaining." |
| Expired OTP entered | "Your code has expired. Request a new one." |
| Resend button timing | Button disabled until cooldown expires |
| Resend button | New code generated, old code invalid |
| Spam: 5+ rapid OTPs | Rate limit error after 3 attempts |
| No CAPTCHA bypass | Cannot get OTP without CAPTCHA (if enabled) |
| Balance tracking | Balance decreases by correct amount per SMS |
| Emoji in template | Emoji stripped on save |

**Commit message:** `test: manual integration test checklist verified`

---

### Task 6.3 — Live Platform Testing

**Goal:** Test the plugin on a live WordPress installation (not local Docker).

**Steps:**
1. Install plugin on live WP site
2. Configure real API credentials in Gateway Settings
3. Disable test mode
4. Test all OTP flows end-to-end with real SMS to 96598765432
5. Test Arabic language mode end-to-end
6. Verify balance deducted correctly per send
7. Verify account balance display in Gateway Settings is accurate
8. Test WooCommerce checkout flow: confirm no conflicts with WooCommerce login
9. Document any live-only issues found

**Stop and present results to user before final commit.**

**Commit message:** `test: live platform integration verified`

---

## Phase 7: Polish & Documentation

---

### Task 7.1 — Admin UX Improvements

- Admin "Send Test SMS" button on Gateway Settings page: sends to test phone, shows result inline
- Admin OTP log: last 20 OTP sends with timestamp, phone (masked), status (sent/failed)
- Admin notice for: test mode active, missing phone numbers on users
- Dashboard widget: today's OTP sends count + balance

**Commit message:** `feat: admin test SMS button, OTP send log, dashboard widget`

---

### Task 7.2 — Code Documentation Pass

- Add PHPDoc to every public class and method
- Add inline comments for non-obvious logic (phone normalization, transient keys)
- Update `CLAUDE.md` with any new patterns discovered
- Write `README.md` with: installation, configuration, credentials setup, test mode guide

**Commit message:** `docs: complete PHPDoc pass and README`

---

### Task 7.3 — Uninstall Cleanup

Implement `uninstall.php` to remove:
- All `kwtsms_otp_*` options from `wp_options`
- All `kwtsms_phone` user meta from `wp_usermeta`
- All `kwtsms_otp_*` transients

**Commit message:** `feat: uninstall cleanup removes all plugin data`

---

## Summary: Task Order & Commits

| # | Task | Commit |
|---|------|--------|
| 1.1 | Scaffold + Docker | `feat: scaffold plugin and Docker dev environment` |
| 1.2 | kwtsms API Client | `feat: kwtsms API client with phone normalizer` |
| 1.3 | OTP Engine | `feat: OTP engine (generate/verify/rate-limit)` |
| 1.4 | Settings Storage | `feat: register plugin settings with sanitization` |
| 2.1 | Admin Menu | `feat: admin menu with 3 subpages and notices` |
| 2.2 | Gateway Settings Page | `feat: gateway settings with credential verification` |
| 2.3 | General Settings Page | `feat: general settings with CAPTCHA configuration` |
| 2.4 | Templates Page | `feat: SMS template editor with character counter` |
| 3.1 | User Phone Meta | `feat: user phone number field` |
| 3.2 | 2FA Login | `feat: OTP 2FA login flow` |
| 3.3 | Passwordless Login | `feat: passwordless SMS login` |
| 3.4 | Password Reset OTP | `feat: password reset via OTP` |
| 4.1 | reCAPTCHA v3 | `feat: Google reCAPTCHA v3` |
| 4.2 | Turnstile | `feat: Cloudflare Turnstile` |
| 5.1 | i18n + Arabic | `feat: i18n with English and Arabic (RTL)` |
| 6.1 | PHPUnit Suite | `test: complete PHPUnit suite` |
| 6.2 | Integration Tests | `test: integration test checklist` |
| 6.3 | Live Tests | `test: live platform verified` |
| 7.1 | Admin UX | `feat: admin test button and OTP log` |
| 7.2 | Documentation | `docs: PHPDoc pass and README` |
| 7.3 | Uninstall | `feat: uninstall cleanup` |

---

## Adding API Credentials for Testing

1. Start Docker: `docker-compose up -d`
2. Visit: `http://localhost:8080/wp-admin`
3. Go to: **Settings → kwtsms OTP → Gateway**
4. Enter:
   - **API Username:** *(your kwtsms API username)*
   - **API Password:** *(your kwtsms API password)*
5. Click **Save & Verify Credentials**
   - Sender ID dropdown will populate from API
   - Balance will display
6. Enable **Test Mode** (all SMS go to test phone, `test=1`, credits not consumed)
7. Set **Test Phone:** `96598765432`
8. Enable `WP_DEBUG_LOG` in `wp-config.php` → OTP code written to `wp-content/debug.log`

> **Credentials are stored server-side in `wp_options` only. They are never output to browser HTML or JavaScript.**
