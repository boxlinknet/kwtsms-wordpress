<?php
/**
 * Browser E2E Test 10: Expired OTP Rejected with Clear Error Message
 *
 * Sets the OTP expiry to 1 minute in admin settings, triggers an OTP, waits
 * 90 seconds for it to expire, then submits the stale code and verifies that
 * the system rejects it with an appropriate expiry error message.  Restores
 * the original expiry at the end.
 *
 * @test-name    10-otp-expiry
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '10-otp-expiry',
    'description' => 'An OTP submitted after its TTL expires is rejected with a clear expiry error.',

    'preconditions' => [
        'Admin access to kwtsms OTP settings',
        'Passwordless login mode enabled  (so OTP can be triggered easily)',
        'Test user exists with kwtsms_phone = 96598765432',
        'test_mode     = 1  (Admin → Gateway → Test Mode ON)',
        'debug_logging = 1  (Admin → General → Developer Tools → Debug Logging)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Log in as admin and open the kwtsms General settings page',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=general' ),
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 1, 'admin-general-settings' ),
        ],
        [
            'step'        => 2,
            'action'      => 'fill',
            'description' => 'Set OTP expiry to 1 minute (60 seconds)',
            'fields'      => [
                '[name="kwtsms_otp_expiry"], #kwtsms_otp_expiry' => '60',
            ],
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 2, 'expiry-set' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Save General settings',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 3, 'expiry-saved' ),
        ],
        [
            'step'        => 4,
            'action'      => 'navigate',
            'description' => 'Navigate to the passwordless login page to trigger an OTP',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-passwordless' ),
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 4, 'passwordless-page' ),
        ],
        [
            'step'        => 5,
            'action'      => 'fill',
            'description' => 'Enter the test phone number to request an OTP',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => null,
        ],
        [
            'step'        => 6,
            'action'      => 'click',
            'description' => 'Submit to trigger OTP generation',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 6, 'otp-received' ),
        ],
        [
            'step'        => 7,
            'action'      => 'check_log',
            'description' => 'Read the OTP from the debug log before it expires',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -20',
            'extract'     => 'otp_code',
            'screenshot'  => null,
        ],
        [
            'step'        => 8,
            'action'      => 'wait',
            'description' => 'Wait 90 seconds for the 60-second OTP to expire',
            'seconds'     => 90,
            'screenshot'  => null,
        ],
        [
            'step'        => 9,
            'action'      => 'fill',
            'description' => 'Enter the now-expired OTP code',
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 9, 'submit-expired' ),
        ],
        [
            'step'        => 10,
            'action'      => 'click',
            'description' => 'Submit the expired OTP',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 10, 'after-expired-submit' ),
        ],
        [
            'step'        => 11,
            'action'      => 'assert_text',
            'description' => 'Assert an expiry error message is shown',
            'contains'    => [ 'expired', 'invalid', 'no longer valid', 'try again', 'timeout', 'expired code' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 11, 'expiry-error' ),
        ],
        [
            'step'        => 12,
            'action'      => 'navigate',
            'description' => 'Navigate to General settings to restore default OTP expiry',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=general' ),
            'screenshot'  => null,
        ],
        [
            'step'        => 13,
            'action'      => 'fill',
            'description' => 'Restore default OTP expiry (300 seconds = 5 minutes)',
            'fields'      => [
                '[name="kwtsms_otp_expiry"], #kwtsms_otp_expiry' => '300',
            ],
            'screenshot'  => null,
        ],
        [
            'step'        => 14,
            'action'      => 'click',
            'description' => 'Save restored General settings',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '10-otp-expiry', 14, 'expiry-restored' ),
        ],
    ],

    'expected_outcome' => 'Submitting an OTP after its TTL has elapsed results in a clear expiry error message. The default OTP TTL is restored at the end of the test.',
];
