# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.4] - 2026-03-09

### Added
- GitHub Actions CI workflow: PHPCS, PHPStan, and PHPUnit run automatically on push and pull requests across PHP 8.1, 8.2, and 8.3.
- Automated plugin zip release: pushing a version tag (`vX.Y.Z`) now triggers a GitHub Actions workflow that builds and publishes the release zip.
- Branch protection rules and PR template for the repository.

### Fixed
- PHPStan false positives in admin view files: suppressed `$this might not be defined`, defensive null-coalescing, offset-always-exists, and left-side-always-true errors that arise because view templates are included inside class methods.
- PHPUnit test assertions updated to reflect `wpcf7_submit` hook (changed from `wpcf7_mail_sent` in 3.0.3).
- PHPUnit test for Gravity Forms updated to match "Coming soon" display (no tab ID).
- Test placeholders: replaced real API username with `wp_username` throughout test files for client identification.

## [3.0.3] - 2026-03-08

### Fixed
- Password reset OTP SMS now sent correctly even when login OTP cooldown is active for the same user (cooldown is now scoped per action type).
- WooCommerce order total placeholder `{total}` no longer contains HTML entities in SMS messages.
- CF7 gate mode form auto-submit after OTP verification no longer throws TypeError when pendingForm is null.
- WPForms gate mode phone field detection now checks label text (WPForms uses non-standard input names).
- Settings `get()` method now correctly returns `$fallback` instead of undefined `$default` variable.
- Country code dropdown on SMS login page is now properly sized (constrained width, phone field takes remaining space).
- Admin notices and warnings from other plugins (e.g. Action Scheduler) now display above the kwtSMS logo header, not beside it.
- Admin sub-menu page hiding now uses CSS/JS and redirect instead of `remove_submenu_page`, preventing redirect loops.
- Users Without Phone page: menu count badge updates dynamically without page reload.

### Changed
- CF7 notification mode now sends SMS even when SMTP email delivery fails (hooks `wpcf7_submit` instead of `wpcf7_mail_sent`).
- Integrations page notes Elementor Pro requirement for form widgets.
- Integrations page notes Ninja Forms phone field configuration requirement.
- Gravity Forms shown on Integrations page as "Coming soon".

## [3.0.2] - 2026-03-07

### Fixed
- Removed tab navigation from form integration pages (CF7, WPForms, Elementor, Gravity Forms, Ninja Forms); both settings cards are now always visible.
- Enable Integration toggle moved into settings table, consistent across all form integrations.
- Restored left padding on WPForms admin pages stripped by WPForms.
- Suppressed WPForms injected header, flyout, and footer on kwtSMS integration pages.
- Hidden page footer on all kwtSMS admin pages.

## [3.0.1] - 2026-03-06

### Fixed
- Resolved all PHP_CodeSniffer WordPress Coding Standards violations.
- Expanded country codes data to full 250-country list.
- Alignment and spacing in OTP and passwordless login views.

## [3.0.0] - 2026-03-05

### Added
- Plugin bootstrap with activation, deactivation, and uninstall hooks.
- kwtSMS API client (`KwtSMS_API`) with `send()`, `verify()`, `balance()`, `sender_ids()`, `coverage()`.
- Settings storage (`KwtSMS_Settings`) backed by `wp_options`, with `DEFAULTS` and typed getters.
- Gateway settings page: API credentials input with live verification and Sender ID dropdown auto-population.
- General settings page: OTP mode (2FA, Passwordless, Both), code length, expiry, rate limits, CAPTCHA.
- Templates page: bilingual English and Arabic SMS templates with live character counter and page indicator.
- 2FA login: password login followed by a 6-digit SMS OTP step.
- Passwordless login: phone number entry followed by OTP, no password required.
- Password reset via OTP: replaces the email reset link with an SMS verification flow.
- Per-role OTP enforcement: choose which user roles require OTP, with administrator exclusion by default.
- Sliding-window rate limiting per phone number, per IP address, and per user account.
- Phone blocking list: silently drop OTP requests from blocked numbers.
- Google reCAPTCHA v3 integration for bot protection on OTP forms.
- Cloudflare Turnstile integration as alternative bot protection.
- Country code dropdown on login forms with GCC and custom country list support.
- Emergency bypass constant `KWTSMS_OTP_DISABLED` in `wp-config.php` for lockout recovery.
- WooCommerce integration: 7 order status SMS notifications (Processing, On-Hold, Completed, Cancelled, Pending Payment, Refunded, Failed).
- WooCommerce checkout OTP gate: require phone verification before order placement.
- Per-order admin SMS metabox: send a custom SMS to the customer from the order edit screen.
- Admin SMS notification: notify a configurable phone on any order status change.
- HPOS (High-Performance Order Storage) compatibility.
- Contact Form 7 integration: Notification and OTP Gate modes.
- WPForms integration: Notification and OTP Gate modes.
- Elementor Pro integration: Notification and OTP Gate modes.
- Gravity Forms integration: Notification and OTP Gate modes.
- Ninja Forms integration: Notification and OTP Gate modes.
- Users Without Phone admin sub-page with dynamic menu count badge.
- OTP send log (last 100 entries) on Logs page.
- Dashboard widget with today's send count.
- Help page with balance display, FAQ links, and support resources.
- Developer action/filter hooks: `kwtsms_otp_before_send`, `kwtsms_otp_message`, `kwtsms_otp_phone_number`, `kwtsms_otp_verified`, `kwtsms_otp_send_failed`.
- English (en_US) and Arabic (ar) translations with RTL admin support.
- PHPUnit 9 + Brain\Monkey test suite (191 tests).
- CodeQL security scanning and Dependabot dependency updates.
- `uninstall.php` that removes all plugin data on deletion.

[Unreleased]: https://github.com/boxlinknet/kwtsms-wordpress/compare/v3.0.4...HEAD
[3.0.4]: https://github.com/boxlinknet/kwtsms-wordpress/compare/v3.0.3...v3.0.4
[3.0.3]: https://github.com/boxlinknet/kwtsms-wordpress/compare/v3.0.2...v3.0.3
[3.0.2]: https://github.com/boxlinknet/kwtsms-wordpress/compare/v3.0.1...v3.0.2
[3.0.1]: https://github.com/boxlinknet/kwtsms-wordpress/compare/v3.0.0...v3.0.1
[3.0.0]: https://github.com/boxlinknet/kwtsms-wordpress/releases/tag/v3.0.0
