# kwtsms OTP — Feature Backlog & Roadmap

> Last updated: 2026-03-03
> Current version: 2.8.0
> Status: WordPress.org submission in progress

---

## Immediate — WordPress.org Submission

Before the plugin goes live on the directory:

- [ ] Run Plugin Check (PCP) — zero error-level items
- [ ] Validate readme.txt at wordpress.org/plugins/developers/readme-validator/
- [ ] Commit untracked `assets/logos/` and modified `readme.txt`
- [ ] Build production ZIP (exclude `tests/`, `docs/`, `vendor/`, `phpstan*`, `phpunit.xml`, `composer.*`)
- [ ] Submit at https://wordpress.org/plugins/developers/add/
- [ ] After approval: set up SVN repo and push trunk + tag `2.8.0`
- [ ] Upload marketplace assets to SVN `/assets/` directory

---

## Phase A — Security & Auth Hardening

### A1. Trusted Devices ("Remember This Device")
After passing 2FA, the user can mark the device as trusted and skip OTP for a configurable number of days (e.g. 30 days).

**Implementation notes:**
- Store a signed token in a long-lived cookie (`kwtsms_trusted_device`)
- Token maps to `{user_id}_{device_fingerprint}_{expiry}` stored in `wp_usermeta`
- Admin setting: enable/disable trusted devices, set expiry days
- User profile page: list and revoke trusted devices
- Security: rotate token on each login; invalidate all on password change

### A2. Duplicate OTP Guard
If a user submits the OTP form twice quickly (double-click, page refresh), two OTPs can be sent. Need idempotency.

**Implementation notes:**
- Before sending OTP, check if a valid (non-expired) OTP already exists for this phone+action
- If yes: reuse it and reset the expiry clock instead of generating a new code
- Add a short send-cooldown (e.g. 60 seconds) before allowing a new send

### A3. Sliding-Window Rate Limiting
Current implementation uses a fixed 10-minute window (easy to game by waiting for window reset). Sliding window is more robust.

**Implementation notes:**
- Replace transient-based counter with a list of timestamps stored in transient
- On each attempt: prune timestamps older than the window, count remaining, check limit
- Window and limit remain configurable (currently 3 OTPs / 10 min)

### A4. Registration OTP Gate
Currently, registration only sends a welcome SMS after the account is created. A full OTP gate would verify the phone number **before** creating the account — preventing fake registrations with invalid numbers.

**Implementation notes:**
- On registration form submit: intercept via `registration_errors` filter (priority 10)
- If phone present: generate + send OTP, store pending registration data in transient, redirect to OTP verification page
- On OTP verify: retrieve transient, complete `wp_create_user()`, then log user in
- Applies to standard WP registration and WooCommerce My Account registration
- Admin setting: enable/disable registration OTP gate (separate from login OTP)
- Graceful fallback: if no phone provided and gate is optional, allow through

### A5. Proxy / VPN / Bad IP Detection (IPHub)
Silently block or flag OTP requests from proxy, VPN, and datacenter IPs using the IPHub v2 API (`https://v2.api.iphub.info/ip/{ip}`).

**How it works:**
- On every OTP send request, look up the requester's IP
- Cache the result in a WP transient for 24 hours (one API call per IP per day)
- Apply the configured action based on the returned `block` level
- Fail open: if IPHub is unreachable or rate-limited (`HTTP 429`), allow the request and log a warning — never break OTP due to third-party downtime

**Block level behaviour (admin-configurable per level):**
| Block Level | Meaning | Default Action | Configurable To |
|-------------|---------|---------------|-----------------|
| `0` | Residential / business (safe) | Allow | — |
| `1` | Proxy / VPN / datacenter | Silent block | Allow / Log only / Throttle |
| `2` | Mixed — may flag innocent users | Log only | Silent block / Allow |

**Silent block:** return a fake success response to the attacker (anti-enumeration — they never know they were blocked).

**Admin settings:**
- IPHub API key (required)
- On/off toggle
- Action per block level (`0`, `1`, `2`): Allow / Silent block / Log only
- Cache TTL (default 24h, configurable)

**Integration with A6 IP Allowlist:** IPs on the admin allowlist bypass IPHub lookup entirely — ensures corporate VPN / office users are never blocked.

**Logging:** every blocked or flagged request logged to `kwtsms-debug.log` with IP, block level, phone attempted, and timestamp.

**Implementation notes:**
- New method `KwtSMS_OTP_Engine::check_ip_reputation( $ip )` — returns `allow`, `block`, or `log`
- API call via `wp_remote_get()` with `X-Key` header and 3-second timeout
- Transient key: `kwtsms_ip_{md5($ip)}`, TTL: configurable (default `DAY_IN_SECONDS`)
- Settings keys: `security.iphub_api_key`, `security.iphub_enabled`, `security.iphub_action_block1`, `security.iphub_action_block2`, `security.iphub_cache_ttl`

### A6. IP Allowlist / Blocklist
- **Allowlist:** Users connecting from trusted IPs (e.g. office network) skip 2FA entirely
- **Blocklist:** IPs blocked from making any OTP requests

**Implementation notes:**
- Admin setting: textarea of CIDR ranges / individual IPs
- Check `$_SERVER['REMOTE_ADDR']` (with proxy header support) on OTP request
- Allowlisted IPs bypass OTP gate; blocklisted IPs get rate-limit response

---

## Phase B — Additional Form Integrations

All planned integrations are complete: WooCommerce ✅, Contact Form 7 ✅, WPForms ✅, Elementor ✅, Gravity Forms ✅, Ninja Forms ✅

> Phase B has nothing remaining.

---

## Phase C — Admin Site Alerts

Send SMS notifications to the site admin(s) when key WordPress events occur. Entirely separate from user-facing OTP flows.

### C1. Admin Site Event Alerts

**Events to support (each individually toggleable):**

| Event | WordPress Hook |
|-------|---------------|
| New user registered | `user_register` |
| User logged in | `wp_login` |
| New post published | `transition_post_status` (pending → publish) |
| New comment posted | `comment_post` |
| WordPress core update available | `wp_version_check` / `upgrader_process_complete` |

**Settings:**
- Admin phone number(s) to notify (comma-separated) — separate from WooCommerce admin phone
- Per-event enable/disable toggles
- Configurable message template per event with relevant placeholders (e.g. `{username}`, `{post_title}`, `{site_name}`)

**Implementation notes:**
- New class `class-kwtsms-admin-alerts.php` in `includes/`
- Registered and loaded from main plugin bootstrap
- Settings section added to existing admin UI (e.g. new "Admin Alerts" tab or card on General page)

---

## Phase D — WooCommerce Advanced Suite

> Already implemented: basic order/status SMS ✅, edit-order metabox ✅, checkout OTP gate ✅, login 2FA ✅, password reset via SMS ✅, admin order notifications ✅, registration SMS ✅

### D1. Stock & Inventory Alerts
Admin and customer SMS notifications for stock events.

**Admin alerts (each toggleable):**
| Event | WooCommerce Hook |
|-------|-----------------|
| Low stock threshold reached | `woocommerce_low_stock` |
| Out of stock | `woocommerce_no_stock` |
| Product on backorder | `woocommerce_product_on_backorder` |

**Customer alert:**
- **Back in stock notifier** — customers can subscribe to a product while it's out of stock and receive an SMS the moment it becomes available again. Hook: `woocommerce_product_set_stock_status` when transitioning to `instock`. Store subscriber list in post meta (`kwtsms_back_in_stock_subscribers`), send SMS to each, then clear the list.

**Settings:** Admin phone(s) for admin alerts; per-event toggles; back-in-stock opt-in button on product page.

### D2. New Product Published
Notify subscribed customers (or admin) when a new product goes live.

**Hook:** `transition_post_status` — `product` post type, `publish` new status.

**Settings:** Toggle on/off, message template with `{product_name}`, `{product_url}`.

### D3. Cart Abandonment Recovery + Coupons
Send a recovery SMS to customers who add items to cart but don't complete checkout.

**Implementation notes:**
- Detect abandonment via WooCommerce session + scheduled cron (e.g. after 1 hour of inactivity)
- Send recovery SMS with a personalised coupon code auto-generated via `WC_Coupon`
- Coupon: single-use, configurable discount %, configurable expiry
- Admin settings: enable/disable, delay (minutes), discount amount, message template with `{coupon_code}`, `{cart_total}`, `{first_name}`
- Only fires for customers with a known phone number (logged-in or entered at checkout)
- **Track abandoned cart performance** — admin dashboard card showing: total abandoned carts detected, SMSes sent, recovered (order placed after SMS), recovery rate %
- **Block-based checkout compatible** — WooCommerce's FSE block checkout (`woocommerce/checkout` block) uses a different flow than classic shortcode checkout; ensure cart capture and recovery hooks work with both

### D4. Multivendor Store Support
SMS notifications for multivendor WooCommerce stores (Dokan, WCFM, WC Vendors).

**Sub-features:**
- **SMS to customer when order is placed** — fires immediately on order creation via `woocommerce_checkout_order_processed` or `woocommerce_new_order`, not just on status change. Sends an instant order confirmation SMS regardless of initial payment status.
- **SMS to vendor/admin on new order** — when a product belonging to a specific vendor is ordered, that vendor receives an SMS alert with order details and their sub-order total.

**Implementation notes:**
- Detect active multivendor plugin at runtime (Dokan: `function_exists('dokan')`, WCFM: `class_exists('WCFM')`, WC Vendors: `class_exists('WCV_Vendors')`)
- For Dokan: hook into `dokan_new_order_notification` and retrieve vendor via `dokan_get_seller_id_by_order()`
- For WCFM: hook into `wcfm_order_item_processed` and get vendor via `$wcfm_marketplace->vendor_id`
- For WC Vendors: hook into `wcvendors_notify_vendor_new_order`
- Vendor phone: read from vendor profile meta (varies per plugin)
- Admin "instant new order" SMS: hook `woocommerce_checkout_order_processed` — fires once per new order regardless of payment method
- Settings: per-vendor-plugin toggles, vendor self-service option to manage their own notification phone

### D5. COD-Only OTP Gate
Require OTP verification only when the customer selects Cash on Delivery as the payment method. All other payment methods bypass the OTP gate.

**Implementation notes:**
- Extend the existing checkout OTP gate in `class-kwtsms-woo.php`
- New admin setting: `woo_checkout_otp_payment_methods` — multi-select (All orders / COD only / specific methods)
- In `process_checkout_otp()`: check `$_POST['payment_method']` before enforcing OTP
- Useful for stores that trust card payments (verified by gateway) but want phone verification for COD

### D6. HPOS Compatibility Declaration
WooCommerce 8.5+ shows an admin notice without this.

**Fix:** Add to main plugin file on `before_woocommerce_init`:
```php
\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
```

---

## Phase F — Messaging Utilities

### F0. Short URLs with Bit.ly
Automatically shorten any URL placeholder in an SMS template before sending. Keeps messages concise, saves characters, and looks cleaner for the recipient.

**How it works:**
- Scan the outgoing message for URLs (regex match `https?://[^\s]+`)
- For each URL found: call Bit.ly API to get a shortened link, replace inline
- Shortened link is used only for that send — original template is never modified
- Fail gracefully: if Bit.ly API is down or rate-limited, send the original long URL

**Bit.ly API:**
- Endpoint: `POST https://api-ssl.bitly.com/v4/shorten`
- Auth: `Authorization: Bearer {access_token}` header
- Request body: `{ "long_url": "https://..." }`
- Response: `{ "link": "https://bit.ly/xxxx" }`

**Admin settings:**
- Enable/disable URL shortening toggle
- Bit.ly access token field (Generic Access Token from Bit.ly account)
- Optional: Bit.ly Group GUID for branded domains (e.g. `mystore.co/xxxx`)

**Applies to all outgoing SMS:** order notifications, cart recovery, event reminders, booking confirmations, OTP messages (if they contain a link), admin alerts — any template with a `{url}` or inline link.

**Implementation notes:**
- New method `KwtSMS_API::maybe_shorten_urls( $message )` — called in `send_sms()` before dispatch
- API call via `wp_remote_post()` with 3-second timeout
- Cache shortened URLs in transient (`kwtsms_short_{md5($url)}`, TTL 24h) to avoid re-shortening the same URL repeatedly
- Settings keys: `general.bitly_enabled`, `general.bitly_access_token`, `general.bitly_group_guid`

---

## Phase G — Plugin Integrations

> Already integrated: CF7 ✅, WPForms ✅, Elementor Forms ✅, Gravity Forms ✅, Ninja Forms ✅, WooCommerce ✅
> Dokan is covered under D4 (Multivendor Suite).

Each integration is a single self-contained class in `includes/integrations/`, loaded at runtime only when the target plugin is active.

---

### G1 — Form Plugins (OTP Phone Verification Field)

Same pattern as existing CF7/WPForms/Gravity Forms integrations: add an OTP phone verification field/widget that sends a code and requires verification before form submission.

| Plugin | Detection | Integration Hook |
|--------|-----------|-----------------|
| **Forminator** | `class_exists('Forminator_API')` | `forminator_custom_form_submit_before_set_fields` |
| **Formidable Forms** | `class_exists('FrmForm')` | `frm_validate_field_entry` |
| **Quform** | `class_exists('Quform_Form_Manager')` | `quform_pre_process` |
| **Fluent Forms** | `function_exists('wpFluentForm')` | `fluentform_before_insert_submission` |

---

### G2 — Membership & Community Plugins

OTP gate at registration and/or login; phone field on member profile; welcome/confirmation SMS.

#### G2a. BuddyPress
- OTP gate on BP registration form (`bp_signup_validate`)
- Phone field on BP extended profile (custom xprofile field)
- Welcome SMS on `bp_core_activated_user`
- Login OTP: uses existing WP auth OTP (works automatically)

#### G2b. Ultimate Member
- OTP gate on UM registration forms (`um_submit_form_errors_hook__registration`)
- Phone field on UM profile (`um_profile_field_filter`)
- Welcome SMS on `um_registration_complete`
- Passwordless login via UM login form

#### G2c. Simple Membership
- OTP gate on registration (`swpm_registration_form_validation`)
- Welcome SMS on `swpm_member_registration_complete`
- Phone field on member edit form

#### G2d. Paid Memberships Pro
- OTP gate at checkout (`pmpro_registration_checks`)
- Membership confirmation SMS on `pmpro_after_checkout`
- Phone field on PMPro checkout form
- Expiry reminder SMS (X days before membership expires)

---

### G3 — eCommerce

#### G3a. Easy Digital Downloads
- Purchase confirmation SMS to customer on `edd_complete_purchase`
- Download link SMS (send download URL via SMS on purchase)
- Admin SMS on new purchase
- Refund/failed payment SMS
- Message templates with `{order_id}`, `{total}`, `{download_name}`, `{download_link}`

---

### G4 — Support Plugins

#### G4a. Awesome Support
- SMS to customer when ticket is created (confirmation) — `wpas_open_ticket`
- SMS to assigned agent on new ticket — `wpas_open_ticket`
- SMS to customer when agent replies — `wpas_post_new_reply`
- SMS to customer when ticket is closed — `wpas_close_ticket`

#### G4b. Fluent Support
- SMS on ticket created, reply received, ticket closed
- Hook: `fluent_support/ticket_created`, `fluent_support/response_added_by_agent`
- Agent notification SMS on ticket assignment

#### G4c. Fluent CRM
- SMS action inside FluentCRM automation workflows (send SMS to contact)
- Custom action block: "Send SMS via kwtSMS" in the automation builder
- Hook: `fluentcrm_automation_funnel_start`
- Contact phone synced from `kwtsms_phone` user meta

---

### G5 — Booking Plugins

All booking integrations follow the same pattern:
- **Booking confirmation SMS** to customer (immediately on booking)
- **Reminder SMS** to customer (X hours/days before appointment — via WP cron)
- **New booking alert SMS** to admin
- **Cancellation SMS** to customer
- Message templates with `{booking_date}`, `{booking_time}`, `{service_name}`, `{name}`

| Plugin | Detection | Key Hooks |
|--------|-----------|-----------|
| **BookingPress** | `class_exists('Bookingpress_Core')` | `bookingpress_after_booking_added`, `bookingpress_booking_status_changed` |
| **WooCommerce Bookings** | `class_exists('WC_Bookings')` | `woocommerce_booking_confirmed`, `woocommerce_booking_cancelled` |
| **WP Booking Calendar** | `class_exists('WpBcCustomer')` | `wpbc_booking_confirmed`, `wpbc_booking_request_approved` |
| **Calendarista** | `class_exists('Calendarista')` | `calendarista_booking_placed`, `calendarista_booking_approved` |

---

### G6 — Security & Infrastructure Plugins

Discovered from wordpress.org/plugins/ **Popular** top 20 scan. These are high-install security and infrastructure plugins whose users strongly benefit from SMS alerts.

Already integrated plugins found in the scan: Elementor ✅, CF7 ✅, WPForms ✅, WooCommerce ✅. Filtered below to SMS-relevant only.

> **Source:** wordpress.org/plugins top-100 scan across Featured, Popular, and Block-Enabled categories (300 plugins total). Filtered to SMS-relevant only. Already-integrated plugins excluded. Ranked by install base × SMS use-case value.

---

### Top 10 SMS-Relevant Findings (not yet in plan)

| Rank | Plugin | Installs | Category | SMS Use Case |
|------|--------|----------|----------|-------------|
| 1 | The Events Calendar | 5M+ | Popular + Block | Ticket purchase, event reminders, organizer alerts |
| 2 | Event Tickets & Registration | 2M+ | Block | Ticket SMS, pre-event reminder (pairs with #1) |
| 3 | GiveWP | 1M+ | Block | Donation receipt, fundraising milestone alerts |
| 4 | Solid Security (iThemes) | 2M+ | Popular | Security breach, brute force, 2FA alerts |
| 5 | Loginizer | 1M+ | Popular | Brute force lockout, suspicious login SMS |
| 6 | Sucuri Security | 1M+ | Popular | Malware detected, integrity check failed |
| 7 | Booking Calendar (wpdevelop) | 60K+ | Block | Booking confirmation, reminder, cancellation |
| 8 | User Profile Builder | 600K+ | Block | Registration OTP gate, profile phone field |
| 9 | UsersWP | 200K+ | Block | Frontend registration/login OTP |
| 10 | Site Reviews | 40K+ | Block | New review submitted — admin alert |

> **Skipped (zero SMS value):** SEO plugins (Yoast, Rank Math, AIOSEO), caching plugins (LiteSpeed, WP Super Cache, W3TC), image optimizers (Smush, Imagify, EWWW), migration tools (UpdraftPlus\*, All-in-One WP Migration), editor tools (Classic Editor, Gutenberg blocks, Kadence, Spectra), analytics (Site Kit, Burst), cookie consent (CookieYes, Complianz), social feeds, sliders, galleries, multilingual tools.
> *UpdraftPlus already added in G6b.

---

### G6a. Wordfence Security *(5M+ installs)*
The most popular WordPress security plugin. Site owners need instant SMS alerts for critical security events — email is too slow when a site is under attack.

**Shared SMS use cases across G6a–G6d:** brute force threshold, admin login alert, malware/threat detected, file integrity failure — each plugin exposes its own action hooks.

**SMS notifications:**
- Brute force / login attack threshold reached
- Malware scan: infected file detected
- Firewall: blocked a critical attack
- Admin user logged in (for high-security sites)
- WordPress core/plugin file modified unexpectedly

**Integration:** Hook into Wordfence's own alert system via `wordfence_alert` action or intercept `wfLog` events. Alternatively monitor via `wp_login_failed` (repeated failures = brute force threshold).

#### G6b. UpdraftPlus – WP Backup *(5M+ installs)*
Most widely used backup plugin. SMS alerts close the gap when email delivery fails during a server crisis — exactly when you need the notification most.

**SMS notifications:**
- Backup completed successfully (to admin)
- Backup failed (critical alert — immediate SMS)
- Remote storage upload failed

**Integration:** Hook into `updraftplus_backup_complete` and `updraftplus_backup_failed` actions.

#### G6c. Jetpack *(4M+ installs)*
All-in-one plugin with downtime monitoring, security scanning, and stats. SMS is the fastest channel for downtime alerts.

**SMS notifications:**
- Site downtime detected (via Jetpack Monitor)
- Site back online
- Security scan: vulnerability or threat found

**Integration:** Hook into `jetpack_module_configuration_load_monitor` or `jetpack_site_down` / `jetpack_site_up` actions if available; fallback to cron-based uptime ping check.

#### G6d. Solid Security – iThemes Security *(2M+ installs)*
**SMS notifications:** brute force lockout, admin login from new IP, file change detected, vulnerability scan result.
**Integration:** `itsec_login_interstitial_show`, `itsec_lockout_user`, `itsec_new_admin_login`.

#### G6e. Loginizer *(1M+ installs)*
Focused purely on brute force protection. Complements Wordfence for sites that prefer a lighter security stack.
**SMS notifications:** IP blocked after failed login threshold, admin lockout warning.
**Integration:** `loginizer_pro_2fa_send` action or monitor `loginizer_log` option changes.

#### G6f. Sucuri Security *(1M+ installs)*
**SMS notifications:** malware scan found infected files, core file integrity check failed, blacklist status changed.
**Integration:** `sucuri_event_trigger` action or `SucuriScanEvent::notifyEvent()` hooks.

---

### G7 — Events & Ticketing

#### G7a. The Events Calendar *(5M+ installs)*
Most popular event management plugin for WordPress. Enormous install base with clear SMS needs for both organizers and attendees.

**SMS notifications:**
- Ticket purchase confirmation to buyer
- Event reminder to attendees (X hours/days before — via WP cron)
- Event cancelled / rescheduled alert to all ticket holders
- New RSVP or ticket sale alert to organizer

**Integration:** `tec_tickets_ticket_email_sent`, `tec_tickets_rsvp_after_attendee_creation`, `tribe_events_after_cancel_ticket`. Attendee phone: collected via custom ticket form field or from user meta.
**Detection:** `class_exists('Tribe__Events__Main')`

#### G7b. Event Tickets & Registration *(2M+ installs)*
The official companion plugin to The Events Calendar — handles ticket sales and RSVPs. Integrated separately since it can run standalone.

**SMS notifications:**
- Ticket purchased (confirmation to buyer)
- Pre-event reminder SMS (scheduled cron, X hours before)
- RSVP confirmation

**Integration:** `tec_tickets_ticket_email_sent`, `event_tickets_rsvp_attendee_created`.
**Detection:** `class_exists('Tribe__Tickets__Main')`

---

### G8 — Fundraising & Donations

#### G8a. GiveWP *(1M+ installs)*
The leading WordPress donation and fundraising plugin. Donors expect immediate receipt confirmation; admins want milestone alerts.

**SMS notifications:**
- Donation received — confirmation to donor (with amount, campaign name)
- Fundraising goal milestone reached — alert to admin (e.g. 25%, 50%, 100%)
- Recurring donation processed
- Donation failed / payment declined

**Integration:** `give_complete_donation`, `give_donation_failed`, `give_goal_progress_updated`.
**Detection:** `function_exists('Give')`
**Message template placeholders:** `{donor_name}`, `{amount}`, `{campaign}`, `{site_name}`

---

### G9 — User Registration & Profiles

#### G9a. User Profile Builder *(600K+ installs)*
Frontend user registration, login, and profile forms with role-based content restriction. Perfect candidate for registration OTP gate.

**SMS features:**
- Registration OTP gate (phone verified before account created — same as A4 but for UPB forms)
- Login OTP via UPB custom login form
- Phone field on UPB profile form synced to `kwtsms_phone` meta

**Integration:** `wppb_register_validate_field`, `wppb_before_user_update`, `wppb_login_validation`.
**Detection:** `function_exists('profile_builder_initialize')`

#### G9b. UsersWP *(200K+ installs)*
Lightweight frontend profile and registration plugin.

**SMS features:**
- Registration OTP gate
- Login OTP via UsersWP login form
- Phone field on UsersWP profile synced to `kwtsms_phone`

**Integration:** `uwp_registration_validate`, `uwp_login_validate`, `uwp_profile_update`.
**Detection:** `class_exists('UsersWP')`

---

### G10 — Reviews & Reputation

#### G10a. Site Reviews *(40K+ installs)*
WP's most complete review management plugin (similar to Tripadvisor/Yelp for WordPress).

**SMS notifications:**
- New review submitted — instant alert to admin
- Review approved — confirmation SMS to reviewer

**Integration:** `site-reviews/review/created`, `site-reviews/review/approved` action hooks.
**Detection:** `class_exists('GeminiLabs\SiteReviews\Plugin')`

---

### G11 — Booking (Additional)

#### G11a. Booking Calendar *(wpdevelop, 60K+ installs)*
Different plugin from "WP Booking Calendar" already in G5. Full-day and time-slot booking with admin approval flow.

**SMS notifications:** booking request received (admin), booking approved/declined (customer), booking reminder.
**Integration:** `wpbc_booking_confirmed`, `wpbc_booking_approved`, `wpbc_booking_cancelled`.
**Detection:** `class_exists('WpBcCustomer')` (note: same class as WP Booking Calendar — verify at implementation time)

## Phase E — Advanced / Longer-Term

### E1. TOTP / Authenticator App Support
Let users pair a TOTP authenticator (Google Authenticator, Authy) as an alternative to SMS OTP.

**Complexity:** High — requires QR code generation, TOTP validation library, per-user secret storage, backup code generation.

### E2. Backup Codes
Emergency one-time codes for when a user has no phone access. Generated on 2FA setup, stored hashed in `wp_usermeta`.

---

## Already Implemented (Verified in Code)

For reference — items that appeared in planning docs but are confirmed done:

| Feature | Version | File |
|---------|---------|------|
| Per-phone rate limiting | v1.x | `class-kwtsms-otp-engine.php` |
| Per-IP rate limiting | v2.x | `class-kwtsms-otp-engine.php` |
| Per-account rate limiting | v2.x | `class-kwtsms-otp-engine.php` |
| Phone field on WP registration | v2.x | `class-kwtsms-plugin.php` |
| Phone blocklist | v2.x | `class-kwtsms-otp-engine.php` |
| Admin SMS notifications (WooCommerce) | v2.x | `class-kwtsms-woo.php` L147-171 |
| Per-order custom SMS metabox | v2.x | `class-kwtsms-woo-metabox.php` |
| WooCommerce registration SMS | v2.x | via generic `user_register` hook |
| WooCommerce checkout OTP gate | v2.x | `class-kwtsms-woo.php` L350-488 |
| WooCommerce login 2FA / passwordless | v2.x | `class-kwtsms-login-otp.php` |
| WooCommerce password reset via SMS | v2.x | `class-kwtsms-reset-otp.php` |
| CF7 / WPForms / Elementor / Gravity Forms / Ninja Forms OTP gates | v2.x | `includes/integrations/` |
| Per-role OTP enforcement | v2.x | `class-kwtsms-login-otp.php` L88-100 |
| OTP resend rate limiting (per-phone, per-IP, per-account) | v2.x | `class-kwtsms-otp-engine.php` L220-295 |
| Country-based OTP restriction | v2.x | `class-kwtsms-api.php` L185-200 |
| Login with OTP (2FA + passwordless) | v2.x | `class-kwtsms-login-otp.php` |
| Reset password with OTP | v2.x | `class-kwtsms-reset-otp.php` |
| Checkout OTP gate (pre-order, all payment methods) | v2.x | `class-kwtsms-woo.php` L350-488 |
| Welcome SMS | v1.x | `class-kwtsms-plugin.php` |
| Debug log system | v1.3 | `class-kwtsms-debug.php` |
| Full test suite (unit + integration + E2E) | v2.x | `tests/` |
| PHPStan level-5 clean | v2.8 | CI |
