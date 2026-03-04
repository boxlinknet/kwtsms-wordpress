# Napkin Runbook

## Curation Rules
- Re-prioritize on every read.
- Keep recurring, high-value notes only.
- Max 10 items per category.
- Each item includes date + "Do instead".

## Execution & Validation (Highest Priority)
1. **[2026-03-04] Always verify before claiming completion**
   Do instead: run PHPUnit + PHPCS, screenshot result, then state done.

2. **[2026-03-04] Never commit without passing tests**
   Do instead: run `vendor/bin/phpunit` and `vendor/bin/phpcs` inside container first.

3. **[2026-03-04] Always use test=1 during development**
   Do instead: pass `test=1` to all kwtsms API calls; OTP lands in `wp-content/kwtsms-debug.log`.

## WordPress Plugin Guardrails
1. **[2026-03-04] Plugin slug is `wp-kwtsms`, text domain is `wp-kwtsms`**
   Do instead: use `wp-kwtsms` everywhere — slugs, option names, hooks, text domain.

2. **[2026-03-04] KwtSMS_API constructor takes 4 args**
   Do instead: `new KwtSMS_API($username, $password, $test_mode, $debug_mode)`.

3. **[2026-03-04] Screenshots must go to `docs/screenshots/{VERSION}/` at repo root**
   Do instead: never save screenshots inside `wp-kwtsms/`.

4. **[2026-03-04] WP Playground restarts clear DB — testuser must be re-created**
   Do instead: keep blueprint.json simple (login + activatePlugin + runPHP for testuser); avoid complex `update_option` in blueprint (causes 502).

5. **[2026-03-04] WP 6.9 password reset interception**
   Do instead: use `login_init` at priority 1, NOT `lostpassword_post` — `login_form_lostpassword` fires before and breaks redirect.

## User Directives
1. **[2026-03-04] Always auto-merge feature branches (Option 1) — never ask**
   Do instead: merge directly to main without prompting.

2. **[2026-03-04] Always use Subagent-Driven execution**
   Do instead: never ask which execution mode to use.

3. **[2026-03-04] After each phase: tests → screenshot → version bump → commit → tag → push**
   Do instead: follow this exact sequence, no shortcuts.

4. **[2026-03-04] Use WP Playground for local testing — no Docker**
   Do instead: `npx @wp-playground/cli@latest server --auto-mount` from plugin dir.

## Shell & Command Reliability
1. **[2026-03-04] Debug log path is `wp-content/kwtsms-debug.log`**
   Do instead: check this file (not `debug.log`) for OTP and API call traces.

2. **[2026-03-04] Test credentials: username=YOUR_API_USERNAME, phone=96598765432**
   Do instead: always use these for local/staging API tests with test=1.
