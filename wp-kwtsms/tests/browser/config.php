<?php
/**
 * Shared configuration for browser E2E tests.
 *
 * These tests are executed by Claude using the agent-browser skill.
 * Each test script is a PHP definition file — Claude reads it and executes
 * every step using agent-browser, saving screenshots after each step.
 *
 * Environment:
 *   KWTSMS_TEST_ENV=playground  — fresh WP Playground instance
 *   KWTSMS_TEST_ENV=docker      (default) — uses http://localhost:8080 (wp_site container)
 *
 * @package KwtSMS_OTP\Tests\Browser
 */

define( 'KWTSMS_TEST_ENV', getenv( 'KWTSMS_TEST_ENV' ) ?: 'docker' );

// Base URL per environment.
define( 'KWTSMS_TEST_BASE_URL', 'docker' === KWTSMS_TEST_ENV
    ? 'http://localhost:8080'
    : 'http://localhost:9400'   // WP Playground default port
);

define( 'KWTSMS_TEST_ADMIN_USER', 'admin' );
define( 'KWTSMS_TEST_ADMIN_PASS', 'admin' );
define( 'KWTSMS_TEST_PHONE',      '96599220322' );

// Screenshots root — at REPO ROOT docs/screenshots/, NOT inside plugin dir.
// __DIR__ = tests/browser/ → depth 4 walks up to repo root.
define( 'KWTSMS_SCREENSHOT_ROOT',
    dirname( __DIR__, 4 ) . '/docs/screenshots'
);
