<?php
/**
 * Browser E2E Test 01: WordPress User Registration with OTP
 *
 * Registers a new WordPress user with a phone number, verifies the OTP
 * gate fires (if registration OTP is enabled), and confirms a welcome SMS
 * appears in the debug log after successful registration.
 *
 * @test-name    01-register-wp-user
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

$rand = substr( md5( (string) time() ), 0, 6 );

return [
    'name'        => '01-register-wp-user',
    'description' => 'Register a new WP user with phone number and verify welcome SMS in debug log.',

    'preconditions' => [
        'welcome_sms_enabled = 1   (Admin  General  Welcome SMS)',
        'test_mode            = 1   (Admin  Gateway  Test Mode ON)',
        'debug_logging        = 1   (Admin  General  Developer Tools  Debug Logging)',
        'API credentials configured (Admin  Gateway)',
        'User registration enabled in WordPress (Settings  General  Anyone can register)',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Open the WordPress registration page',
            'url'         => kwtsms_url( '/wp-login.php?action=register' ),
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 1, 'register-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'fill',
            'description' => 'Fill username and email fields with unique values',
            'fields'      => [
                '#user_login' => 'testuser_' . $rand,
                '#user_email' => 'test_' . $rand . '@example.com',
            ],
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 2, 'form-filled' ),
        ],
        [
            'step'        => 3,
            'action'      => 'fill',
            'description' => 'Fill the plugin phone number field with the test phone',
            'fields'      => [
                '[name="kwtsms_phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 3, 'phone-filled' ),
        ],
        [
            'step'        => 4,
            'action'      => 'click',
            'description' => 'Submit the registration form',
            'selector'    => '#wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 4, 'after-submit' ),
        ],
        [
            'step'        => 5,
            'action'      => 'assert_text',
            'description' => 'Assert the OTP verification prompt is shown',
            'contains'    => [ 'verification', 'OTP', 'code', 'verify' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 5, 'otp-prompt' ),
        ],
        [
            'step'        => 6,
            'action'      => 'check_log',
            'description' => 'Read OTP code from the kwtsms debug log',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -20',
            'extract'     => 'otp_code',  // Claude stores the 6-digit code for use in step 7
            'screenshot'  => null,
        ],
        [
            'step'        => 7,
            'action'      => 'fill',
            'description' => 'Enter the OTP code extracted from the debug log',
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 7, 'otp-entered' ),
        ],
        [
            'step'        => 8,
            'action'      => 'click',
            'description' => 'Submit the OTP verification form',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 8, 'after-otp-submit' ),
        ],
        [
            'step'        => 9,
            'action'      => 'assert_text',
            'description' => 'Assert user is logged in or registration succeeded',
            'contains'    => [ 'Dashboard', 'Welcome', 'My account', 'registration complete', 'logged in' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 9, 'success' ),
        ],
        [
            'step'        => 10,
            'action'      => 'check_log',
            'description' => 'Verify the welcome SMS entry appears in the debug log',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -30',
            'assert'      => 'welcome',   // assert the word "welcome" (case-insensitive) is in the output
            'screenshot'  => kwtsms_screenshot_path( '01-register-wp-user', 10, 'debug-log' ),
        ],
    ],

    'expected_outcome' => 'New user is registered, OTP gate fires if enabled, user is logged in, and a welcome SMS entry is visible in kwtsms-debug.log.',
];
