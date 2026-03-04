<?php
/**
 * Browser E2E Test 07: Two-Factor Authentication Login (Subscriber Role)
 *
 * Verifies the 2FA flow for a subscriber-role user: the user enters their
 * regular username and password, is redirected to the OTP entry page instead
 * of the dashboard, enters the OTP from the debug log, and is then granted
 * access to the dashboard.
 *
 * @test-name    07-2fa-login
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '07-2fa-login',
    'description' => 'Subscriber logs in with password then completes 2FA OTP step before reaching the dashboard.',

    'preconditions' => [
        'Login mode = Two-Factor (2FA)  (Admin  General  Login Mode)',
        '2FA enabled for Subscriber role  (Admin  General  Roles requiring OTP)',
        'Test user exists: username = "testuser", password = "testpass", role = Subscriber',
        'testuser has kwtsms_phone = 96599220322 stored in usermeta',
        'test_mode     = 1  (Admin  Gateway  Test Mode ON)',
        'debug_logging = 1  (Admin  General  Developer Tools  Debug Logging)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Open the WordPress login page',
            'url'         => kwtsms_url( '/wp-login.php' ),
            'screenshot'  => kwtsms_screenshot_path( '07-2fa-login', 1, 'login-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'fill',
            'description' => 'Enter subscriber credentials (username and password)',
            'fields'      => [
                '#user_login' => 'testuser',
                '#user_pass'  => 'testpass',
            ],
            'screenshot'  => kwtsms_screenshot_path( '07-2fa-login', 2, 'creds-entered' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Submit the credential form',
            'selector'    => '#wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '07-2fa-login', 3, 'otp-redirect' ),
        ],
        [
            'step'        => 4,
            'action'      => 'assert_text',
            'description' => 'Assert the OTP entry page is shown — NOT the dashboard',
            'contains'    => [ 'code', 'OTP', 'verify', 'Enter', 'sent', 'verification' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '07-2fa-login', 4, 'otp-page' ),
        ],
        [
            'step'        => 5,
            'action'      => 'assert_not_text',
            'description' => 'Assert the WordPress dashboard is NOT shown yet (2FA not bypassed)',
            'not_contains' => [ 'Dashboard', 'Howdy', 'At a Glance' ],
            'screenshot'  => null,
        ],
        [
            'step'        => 6,
            'action'      => 'check_log',
            'description' => 'Read the OTP code from the kwtsms debug log',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -20',
            'extract'     => 'otp_code',
            'screenshot'  => null,
        ],
        [
            'step'        => 7,
            'action'      => 'fill',
            'description' => 'Enter the OTP code from the debug log',
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '07-2fa-login', 7, 'otp-entered' ),
        ],
        [
            'step'        => 8,
            'action'      => 'click',
            'description' => 'Submit the OTP to complete 2FA',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '07-2fa-login', 8, 'after-otp-submit' ),
        ],
        [
            'step'        => 9,
            'action'      => 'assert_text',
            'description' => 'Assert the user is now on the WordPress dashboard as the subscriber',
            'contains'    => [ 'Dashboard', 'Howdy', 'My account', 'Hello', 'testuser' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '07-2fa-login', 9, 'dashboard' ),
        ],
    ],

    'expected_outcome' => 'Subscriber-role user enters credentials, is redirected to OTP form (not dashboard), enters OTP from debug log, and successfully reaches the dashboard after 2FA.',
];
