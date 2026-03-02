<?php
/**
 * Browser E2E Test 13: Welcome SMS Sent to Newly Registered User
 *
 * Enables welcome SMS in admin settings, registers a new user with a phone
 * number, and verifies that a welcome SMS entry appears in the kwtsms debug
 * log, confirming the welcome hook fired and the SMS was dispatched.
 *
 * @test-name    13-welcome-sms
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

$rand = substr( md5( (string) time() ), 0, 6 );

return [
    'name'        => '13-welcome-sms',
    'description' => 'Welcome SMS is sent to a new user after registration and the entry appears in the debug log.',

    'preconditions' => [
        'User registration enabled in WordPress  (Settings → General → Anyone can register)',
        'test_mode     = 1  (Admin → Gateway → Test Mode ON)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Log in as admin and open the kwtsms General settings',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=general' ),
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 1, 'admin-general-settings' ),
        ],
        [
            'step'        => 2,
            'action'      => 'click',
            'description' => 'Enable "Send Welcome SMS" toggle/checkbox',
            'selector'    => '[name="kwtsms_welcome_sms_enabled"], #kwtsms_welcome_sms_enabled',
            'conditional' => 'not_checked',
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 2, 'admin-welcome-enabled' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Enable "Debug Logging" toggle/checkbox',
            'selector'    => '[name="kwtsms_debug_logging"], #kwtsms_debug_logging',
            'conditional' => 'not_checked',
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 3, 'admin-debug-enabled' ),
        ],
        [
            'step'        => 4,
            'action'      => 'click',
            'description' => 'Save General settings',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 4, 'admin-settings-saved' ),
        ],
        [
            'step'        => 5,
            'action'      => 'navigate',
            'description' => 'Log out from admin to register as a new user',
            'url'         => kwtsms_url( '/wp-login.php?action=logout' ),
            'screenshot'  => null,
        ],
        [
            'step'        => 6,
            'action'      => 'navigate',
            'description' => 'Open the WordPress registration page',
            'url'         => kwtsms_url( '/wp-login.php?action=register' ),
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 6, 'register-page' ),
        ],
        [
            'step'        => 7,
            'action'      => 'fill',
            'description' => 'Fill the registration form with unique username, email, and test phone',
            'fields'      => [
                '#user_login'           => 'welcome_' . $rand,
                '#user_email'           => 'welcome_' . $rand . '@example.com',
                '[name="kwtsms_phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 7, 'register-form-filled' ),
        ],
        [
            'step'        => 8,
            'action'      => 'click',
            'description' => 'Submit the registration form',
            'selector'    => '#wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 8, 'after-register' ),
        ],
        [
            'step'        => 9,
            'action'      => 'wait',
            'description' => 'Wait briefly for the welcome SMS hook to fire and log entry to be written',
            'seconds'     => 3,
            'screenshot'  => null,
        ],
        [
            'step'        => 10,
            'action'      => 'check_log',
            'description' => 'Check the kwtsms debug log for a welcome SMS entry',
            'command'     => 'docker exec wp_site grep -i "welcome" /var/www/html/wp-content/kwtsms-debug.log | tail -10',
            'assert'      => 'welcome',
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 10, 'debug-log' ),
        ],
        [
            'step'        => 11,
            'action'      => 'assert_log_contains',
            'description' => 'Assert the welcome SMS log entry references the test phone number',
            'log_command' => 'docker exec wp_site grep -i "welcome" /var/www/html/wp-content/kwtsms-debug.log | tail -10',
            'contains'    => KWTSMS_TEST_PHONE,
            'screenshot'  => kwtsms_screenshot_path( '13-welcome-sms', 11, 'welcome-confirmed' ),
        ],
    ],

    'expected_outcome' => 'After new user registration with a phone number, a welcome SMS entry is present in kwtsms-debug.log, confirming the welcome_sms hook fired and SMS was dispatched to the registered phone.',
];
