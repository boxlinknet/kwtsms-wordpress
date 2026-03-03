# Browser E2E Tests

Each PHP script in this directory is a **test definition** executed by Claude
using the `agent-browser` skill.

## How to run

Tell Claude: "Run browser test 01-register-wp-user" (or all of them in sequence).
Claude will:
1. Read the test definition file
2. Open `http://localhost:8080` (Docker) or WP Playground
3. Execute each step using agent-browser
4. Save a screenshot after every step to `docs/screenshots/{test-name}/`
5. Report pass/fail with screenshot paths

## Environment

Default: Docker `wp_site` container at http://localhost:8080
WP admin: http://localhost:8080/wp-admin (admin / admin)
WP Playground: set `KWTSMS_TEST_ENV=playground` before asking Claude to run

## Prerequisites

- Docker Desktop running (`docker ps` shows `wp_site` and `wp_db`)
- Plugin activated in wp_site container
- API credentials configured (Gateway page in wp-admin)
- Test mode enabled in Gateway settings
- Debug logging enabled in General settings

## Test files

| File | Scenario |
|------|----------|
| 01-register-wp-user.php | Register new WP user with phone + OTP + welcome SMS |
| 02-register-woo-user.php | WooCommerce account registration |
| 03-checkout-guest.php | WooCommerce guest checkout with OTP |
| 04-passwordless-no-phone.php | Passwordless — user has no phone |
| 05-passwordless-with-phone.php | Passwordless — happy path |
| 06-passwordless-dot-format.php | Passwordless — +965.99220322 format |
| 07-2fa-login.php | 2FA login (subscriber role) |
| 08-password-reset-sms.php | Full SMS password reset flow |
| 09-rate-limit-ui.php | Rate limit error in UI |
| 10-otp-expiry.php | Expired OTP rejected |
| 11-blocked-phone.php | Blocked phone silent success |
| 12-referral-link.php | Footer referral link on all OTP pages |
| 13-welcome-sms.php | Welcome SMS on registration |
| 14-template-variables.php | Template variable substitution |
