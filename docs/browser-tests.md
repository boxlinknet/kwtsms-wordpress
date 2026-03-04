# kwtSMS Browser Tests

Manual end-to-end tests run via agent-browser against WP Playground (`http://127.0.0.1:9401`).

Screenshots saved to `docs/screenshots/{VERSION}/`.

---

## v2.8.0 — 2026-03-04

### Environment

- WP Playground via `npx @wp-playground/cli@latest server --auto-mount` from `wp-kwtsms/`
- Plugin: wp-kwtsms v2.8.0
- Blueprint: `wp-kwtsms/blueprint.json`
- Test phone: `96599220322` (Kuwait)
- Admin: `admin` / `password`
- Test user: `testuser` / `Test@12345` (subscriber, phone `96599220322`)

---

### 1. Gateway — credential scenarios

**Setup:** Admin → kwtSMS → Gateway

| Scenario | Credentials | Expected | Result |
|---|---|---|---|
| Empty | (none) | Browser validation blocks submit | Pass |
| Wrong | `wronguser` / `wrongpass` | "Gateway authentication failed" | Pass |
| Valid | `instabox` / `LhVmTF3D^S4xpd` | "✓ Connected as instabox", Sender ID populated | Pass |

Screenshots: `07-gateway-wrong-creds.png`, `08-gateway-valid-creds.png`, `09-gateway-saved.png`

---

### 2. Debug logging

**Setup:** Admin → kwtSMS → General → Developer Tools → Debug Logging ON → Save

- Debug Logging checkbox enabled and saved
- Warning notice "⚠ Debug Logging is ON, disable this on production sites." shown
- Log file: `wp-content/kwtsms-debug.log`

Screenshot: `05-debug-enabled.png`

---

### 3. User registration + Welcome SMS

**Setup:** Register new user `otp_testuser` at `/wp-login.php?action=register` with phone `96599220322`

- Phone field present on registration form
- Registration succeeded → "Check your email" page
- Welcome SMS fired immediately on `user_register` hook
- API call (test mode): `POST /API/send/` → `result: OK`, `msg-id: 193dab38...`, balance: 70 → 69

**Debug log entry:**
```
[send_sms()] type=welcome phone=96599220322 sender=KWT-SMS
[request(send/)] SUCCESS result={"result":"OK","msg-id":"193dab38727dfc6cc7e5816c88ead1b9",...}
```

Screenshots: `12-register-page.png`, `13-register-form-filled.png`, `14-register-result.png`

---

### 4. 2FA OTP login

**Setup:** OTP mode = 2FA, testuser (subscriber) has phone `96599220322`, Subscriber role requires OTP

1. Login form → enter `testuser` / `Test@12345` → Submit
2. Redirected to `/wp-login.php?action=kwtsms_otp` — OTP screen shown
3. "We sent a 6-digit code to ****9322" displayed
4. OTP sent via API (test mode): code `476755` logged to `kwtsms-debug.log`
5. Entered code → "Verify Code" → redirected to `wp-admin/`
6. "Howdy, testuser" — login complete

**SMS log:** `login / 96599220322 / Sent / OK`
**OTP Attempts log:** `testuser (#3) / login / Success`

Screenshots: `20-otp-verification-screen.png`, `21-otp-code-entered.png`, `22-otp-login-success.png`

---

### 5. Passwordless login

**Setup:** Passwordless login page at `/wp-login.php?action=kwtsms_passwordless`

1. Country dropdown shows 🇰🇼 +965 (auto-detected)
2. Enter local number `99220322` → "Send OTP Code"
3. Redirected to `/wp-login.php?action=kwtsms_otp&context=passwordless`
4. OTP sent (type=passwordless): code `784150` logged to `kwtsms-debug.log`
5. Entered code → "Verify Code" → redirected to `wp-admin/`
6. "Howdy, otp_testuser" — login complete (matched by phone number)

**SMS log:** `passwordless / 96599220322 / Sent / OK`
**OTP Attempts log:** `otp_testuser (#2) / passwordless / Success`

Screenshots: `23-passwordless-page.png`, `24-passwordless-phone-entered.png`, `25-passwordless-otp-screen.png`, `27-passwordless-login-success.png`

---

### 6. Logs

**SMS History:** All 6 send attempts visible with phone, type, status, and API result
**OTP Attempts:** 2FA success and passwordless success both recorded
**Debug Log:** Full API request/response chain logged for each attempt

Screenshots: `28-sms-logs.png`, `29-otp-attempts-log.png`, `30-debug-log-final.png`

---

### Known issues found

| Issue | Detail | Status |
|---|---|---|
| Double country code on profile save | Admin entering `96599220322` in the "Local number" field on user-edit.php causes JS to prepend `965` again → stored as `96596599220322` | Fixed in v2.8.0 |

---

## WooCommerce tests

_To be added._
