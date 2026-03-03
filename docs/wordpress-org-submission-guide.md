# WordPress.org Plugin Submission Guide

> Reference document for submitting `wp-kwtsms` to the WordPress.org plugin directory.
> Created: 2026-03-03

---

## Overview

WordPress.org uses **SVN** (not Git) as its distribution system. You submit a ZIP for human review, receive an SVN repo on approval, push your code there, and the plugin appears in the marketplace automatically.

---

## Step-by-Step Process

### Step 1 — Prepare Your WordPress.org Account
- Register at [wordpress.org](https://wordpress.org)
- **Enable 2FA** — mandatory since October 2024. The submission portal blocks you without it
- Set a separate **SVN password** in your profile (different from your login password)
- Whitelist `plugins@wordpress.org` so review emails don't go to spam

### Step 2 — Run Plugin Check Locally
Install the **Plugin Check (PCP)** plugin on a local WordPress site:
```bash
wp plugin check wp-kwtsms
```
All **error-level** items must be zero. The submission portal auto-runs this on upload and blocks if there are errors.

### Step 3 — Prepare the Production ZIP
Must be **under 10MB** and must **exclude**:
- `vendor/` (dev-only; keep runtime deps if any)
- `tests/`, `docs/`, `.git/`, `.github/`
- `node_modules/`, build configs
- `phpstan.neon`, `phpstan-bootstrap.php`, `phpunit.xml`, `composer.json`, `composer.lock`
- Any `var_dump()`, `print_r()`, or debug output

### Step 4 — readme.txt
Already created at `wp-kwtsms/readme.txt`. Validate it before submitting:
[https://wordpress.org/plugins/developers/readme-validator/](https://wordpress.org/plugins/developers/readme-validator/)

### Step 5 — Submit
URL: **[https://wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/)**

Upload the ZIP. The system auto-scans it. If it passes, it enters the human review queue.

### Step 6 — Wait for Review
- **Target: 14 business days**
- If issues found: detailed email with specific problems; ~3 months to fix and respond
- If no response after 14 days: email `plugins@wordpress.org`

### Step 7 — After Approval: SVN Setup
You receive an email with your SVN repo URL, then:

```bash
# Check out your empty SVN repo
svn co https://plugins.svn.wordpress.org/wp-kwtsms my-plugin-svn
cd my-plugin-svn

# Copy plugin files into trunk/ (production files only, no dev files)
cp -r /path/to/wp-kwtsms/* trunk/

# Stage all files
svn add trunk/*

# Commit to SVN
svn ci -m "Initial release 2.8.0"
# Enter your WordPress.org username + SVN password when prompted

# Tag the release (this is what WordPress uses for the downloadable ZIP)
svn cp trunk tags/2.8.0
svn ci -m "Tagging version 2.8.0"
```

The plugin appears in the directory within minutes.

### Step 8 — Upload Marketplace Assets
Place assets in `/assets/` directory in SVN (NOT inside `trunk/`):

| File | Size | Purpose |
|------|------|---------|
| `icon-128x128.png` | 128×128 px | Plugin icon |
| `icon-256x256.png` | 256×256 px | Retina icon |
| `banner-772x250.png` | 772×250 px | Page header banner |
| `banner-1544x500.png` | 1544×500 px | Retina banner |
| `screenshot-1.png` | Any | Matches Screenshot 1 in readme.txt |
| `screenshot-2.png` | Any | Matches Screenshot 2 in readme.txt |
| ... | | Up to screenshot-8.png |

```bash
svn add assets/
svn propset svn:mime-type image/png assets/*.png
svn ci -m "Add plugin assets"
```

---

## Requirements Summary

| Category | Requirement |
|---|---|
| License | GPLv2 or later (all code, images, assets) |
| Prefixing | All functions, classes, options, hooks use `kwtsms_` / `KwtSMS_` |
| Sanitization | All `$_POST`/`$_GET` data sanitized at input |
| Escaping | All dynamic output escaped at point of echo |
| Nonces | All forms + AJAX handlers verify a nonce |
| Capabilities | `current_user_can()` on all admin actions |
| SQL | `$wpdb->prepare()` for all dynamic queries |
| HTTP calls | `wp_remote_get/post()`, not `curl` directly |
| External API | Documented in readme.txt with service URL + privacy policy |
| No bundled core libs | Cannot include jQuery or anything WordPress already ships |
| No obfuscation | All code must be human-readable |
| No trialware | No features that expire or degrade |
| No phone-home | No silent external requests without user opt-in |
| PHP support | PHP 7.4+ |
| Tags | Max 5 tags, no competitor names, no keyword stuffing |
| readme.txt | Stable tag must match SVN tag folder name exactly |

---

## What Reviewers Check (Most Common Rejection Reasons)

1. **Missing output escaping** — every echoed variable must use `esc_html()`, `esc_attr()`, `esc_url()`, etc.
2. **Missing input sanitization** — all `$_POST`/`$_GET` data must be sanitized
3. **Missing nonce verification** — all forms and AJAX must verify nonces
4. **Missing capability checks** — all admin actions must check `current_user_can()`
5. **Unprefixed functions/globals** — everything must use the `kwtsms_`/`KwtSMS_` prefix
6. **SQL injection** — raw queries without `$wpdb->prepare()`
7. **Bundled WordPress core libraries** — jQuery, jQuery UI, etc.
8. **Using `curl` directly** — must use WP HTTP API
9. **Phone home without opt-in** — any undisclosed external request
10. **Dev files in ZIP** — `.git/`, `node_modules/`, `tests/`
11. **Version mismatch** — plugin header, readme.txt Stable tag, and SVN tag must all match
12. **Trademark violations** — plugin name implies official affiliation

---

## Releasing Updates After Listed

```bash
# Pull latest
svn up

# Edit files in trunk/ — bump version in:
#   - wp-kwtsms.php (Version: X.Y.Z)
#   - readme.txt (Stable tag: X.Y.Z)
#   - any internal VERSION constant

# Commit trunk
svn ci -m "Updating to version X.Y.Z"

# Tag the new release
svn cp trunk tags/X.Y.Z
svn ci -m "Tagging version X.Y.Z"
```

---

## Updating readme.txt Only (no code change)

No version bump needed:
```bash
svn ci -m "Update tested up to 6.7"
```

---

## Pre-Submission Checklist for wp-kwtsms

- [ ] 2FA enabled on WordPress.org account
- [ ] Plugin Check (PCP) runs clean — zero error-level items
- [ ] readme.txt validated at the readme validator
- [ ] Production ZIP excludes: `tests/`, `docs/`, `.git/`, `phpstan*`, `phpunit.xml`, `composer.json`
- [ ] External API (kwtSMS) documented in readme.txt with service URL and privacy policy link ✅
- [ ] Version consistent across plugin header, readme.txt Stable tag, and SVN tag
- [ ] License declared as GPL-2.0-or-later in plugin header and readme.txt ✅
- [ ] Text domain `wp-kwtsms` used in all i18n functions ✅
- [ ] All outputs escaped ✅
- [ ] All inputs sanitized ✅
- [ ] All forms have nonce verification ✅
- [ ] All admin actions have capability checks ✅
- [ ] No `var_dump()`, `print_r()`, or debug code in production files ✅
- [ ] No bundled copies of jQuery or other WordPress core libraries ✅
- [ ] Banner and icon assets prepared for SVN `/assets/` directory
- [ ] Screenshots prepared (8 screenshots documented in readme.txt)

---

## Useful Links

- Submission portal: https://wordpress.org/plugins/developers/add/
- Plugin guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- readme.txt validator: https://wordpress.org/plugins/developers/readme-validator/
- Plugin Check (PCP): https://wordpress.org/plugins/plugin-check/
- SVN guide: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- Assets guide: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- Review team contact: plugins@wordpress.org
