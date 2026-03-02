<?php
/**
 * Browser E2E Test 06: Passwordless Login — Dot-Format Phone Normalization
 *
 * Verifies that phone numbers entered with dots and a leading plus sign
 * (e.g., +965.99220322) are correctly normalised to the stored format
 * (96599220322) and resolve to the correct user, allowing successful login.
 *
 * @test-name    06-passwordless-dot-format
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '06-passwordless-dot-format',
    'description' => 'Phone in +965.99220322 dot format is normalised and resolves the correct user for passwordless login.',

    'preconditions' => [
        'Passwordless login mode enabled (Admin → General → Login Mode = Passwordless OTP)',
        'Test user exists with usermeta kwtsms_phone = 96599220322 (plain digits, no plus/dots)',
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
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 1, 'login-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'click',
            'description' => 'Click the passwordless / Sign in with OTP link',
            'selector'    => 'a[href*="kwtsms-passwordless"], a[href*="passwordless"], .kwtsms-passwordless-link, #kwtsms-passwordless-link',
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 2, 'passwordless-page' ),
        ],
        [
            'step'        => 3,
            'action'      => 'fill',
            'description' => 'Enter phone in dot format: +965.99220322',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => '+965.99220322',
            ],
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 3, 'phone-entered' ),
        ],
        [
            'step'        => 4,
            'action'      => 'click',
            'description' => 'Submit to request OTP — system should normalise the phone and find the user',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 4, 'otp-form' ),
        ],
        [
            'step'        => 5,
            'action'      => 'assert_text',
            'description' => 'Assert the OTP form is shown (not an error — user was found)',
            'contains'    => [ 'code', 'OTP', 'verify', 'Enter', 'sent' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 5, 'otp-form-shown' ),
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
            'description' => 'Enter the OTP code retrieved from the debug log',
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 7, 'otp-entered' ),
        ],
        [
            'step'        => 8,
            'action'      => 'click',
            'description' => 'Submit the OTP to complete authentication',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 8, 'after-otp-submit' ),
        ],
        [
            'step'        => 9,
            'action'      => 'assert_text',
            'description' => 'Assert the user is now logged in on the WordPress dashboard',
            'contains'    => [ 'Dashboard', 'Howdy', 'My account', 'Hello' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '06-passwordless-dot-format', 9, 'dashboard' ),
        ],
    ],

    'expected_outcome' => 'Phone entered as +965.99220322 is normalised to 96599220322, the correct user is found, OTP is sent and verified, and the user lands on the dashboard.',
];
