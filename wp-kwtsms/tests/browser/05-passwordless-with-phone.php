<?php
/**
 * Browser E2E Test 05: Passwordless Login — Happy Path
 *
 * Full happy-path test for passwordless login: a user who has a phone number
 * stored in their profile requests an OTP, retrieves it from the debug log,
 * enters it in the form, and is successfully authenticated.
 *
 * @test-name    05-passwordless-with-phone
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '05-passwordless-with-phone',
    'description' => 'Passwordless login happy path — user with phone receives OTP and logs in.',

    'preconditions' => [
        'Passwordless login mode enabled (Admin → General → Login Mode = Passwordless OTP)',
        'Test user exists with usermeta kwtsms_phone = 96598765432',
        'Suggested username: testuser  (created via WP admin or blueprint)',
        'test_mode     = 1  (Admin → Gateway → Test Mode ON)',
        'debug_logging = 1  (Admin → General → Developer Tools → Debug Logging)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Open the WordPress login page',
            'url'         => kwtsms_url( '/wp-login.php' ),
            'screenshot'  => kwtsms_screenshot_path( '05-passwordless-with-phone', 1, 'login-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'click',
            'description' => 'Click the passwordless / Sign in with OTP link',
            'selector'    => 'a[href*="kwtsms-passwordless"], a[href*="passwordless"], .kwtsms-passwordless-link, #kwtsms-passwordless-link',
            'screenshot'  => kwtsms_screenshot_path( '05-passwordless-with-phone', 2, 'passwordless-page' ),
        ],
        [
            'step'        => 3,
            'action'      => 'fill',
            'description' => 'Enter the test phone number to identify the user',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => kwtsms_screenshot_path( '05-passwordless-with-phone', 3, 'phone-entered' ),
        ],
        [
            'step'        => 4,
            'action'      => 'click',
            'description' => 'Submit to request OTP',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '05-passwordless-with-phone', 4, 'otp-form' ),
        ],
        [
            'step'        => 5,
            'action'      => 'check_log',
            'description' => 'Read the OTP code from the kwtsms debug log',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -20',
            'extract'     => 'otp_code',
            'screenshot'  => null,
        ],
        [
            'step'        => 6,
            'action'      => 'fill',
            'description' => 'Enter the OTP code into the verification field',
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '05-passwordless-with-phone', 6, 'otp-entered' ),
        ],
        [
            'step'        => 7,
            'action'      => 'click',
            'description' => 'Submit the OTP form to complete authentication',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '05-passwordless-with-phone', 7, 'after-otp-submit' ),
        ],
        [
            'step'        => 8,
            'action'      => 'assert_text',
            'description' => 'Assert the user is now on the WordPress dashboard',
            'contains'    => [ 'Dashboard', 'Howdy', 'My account', 'Hello' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '05-passwordless-with-phone', 8, 'dashboard' ),
        ],
    ],

    'expected_outcome' => 'User with a stored phone number completes passwordless login by entering the OTP from the debug log, and lands on the WordPress dashboard.',
];
