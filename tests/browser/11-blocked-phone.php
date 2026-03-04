<?php
/**
 * Browser E2E Test 11: Blocked Phone — Anti-Enumeration Silent Behaviour
 *
 * Adds the test phone number to the Blocked Phones list in admin settings,
 * then verifies that the OTP request page does NOT reveal the phone is blocked
 * (anti-enumeration: it should silently "succeed" or show a neutral message,
 * not a specific "phone blocked" error).  Cleans up the blocked list after
 * the test.
 *
 * @test-name    11-blocked-phone
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '11-blocked-phone',
    'description' => 'Blocked phone receives a silent/neutral response — no "phone blocked" error shown (anti-enumeration).',

    'preconditions' => [
        'Passwordless login mode enabled (so OTP form is accessible)',
        'Admin access to kwtsms General settings (Blocked Phones list)',
        'test_mode     = 1  (Admin  Gateway  Test Mode ON)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Log in as admin and open the kwtsms General settings page',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=general' ),
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 1, 'admin-general-settings' ),
        ],
        [
            'step'        => 2,
            'action'      => 'fill',
            'description' => 'Add the test phone number to the Blocked Phones textarea',
            'fields'      => [
                '[name="kwtsms_blocked_phones"], #kwtsms_blocked_phones, textarea[name*="blocked"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 2, 'admin-blocked-list' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Save General settings with the blocked phone added',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 3, 'admin-saved' ),
        ],
        [
            'step'        => 4,
            'action'      => 'navigate',
            'description' => 'Navigate to the passwordless login page as a guest',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-passwordless' ),
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 4, 'passwordless-page' ),
        ],
        [
            'step'        => 5,
            'action'      => 'fill',
            'description' => 'Enter the blocked phone number',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 5, 'phone-entered' ),
        ],
        [
            'step'        => 6,
            'action'      => 'click',
            'description' => 'Submit the OTP request with the blocked phone',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 6, 'result' ),
        ],
        [
            'step'        => 7,
            'action'      => 'assert_not_text',
            'description' => 'Assert the response does NOT mention the phone is blocked (anti-enumeration)',
            'not_contains' => [ 'blocked', 'not allowed', 'banned', 'blacklist', 'denied' ],
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 7, 'no-error-shown' ),
        ],
        [
            'step'        => 8,
            'action'      => 'navigate',
            'description' => 'Return to admin General settings to remove the blocked phone',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=general' ),
            'screenshot'  => null,
        ],
        [
            'step'        => 9,
            'action'      => 'fill',
            'description' => 'Clear the Blocked Phones textarea to unblock the test phone',
            'fields'      => [
                '[name="kwtsms_blocked_phones"], #kwtsms_blocked_phones, textarea[name*="blocked"]' => '',
            ],
            'screenshot'  => null,
        ],
        [
            'step'        => 10,
            'action'      => 'click',
            'description' => 'Save General settings with the blocked list cleared',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '11-blocked-phone', 10, 'admin-unblocked' ),
        ],
    ],

    'expected_outcome' => 'A blocked phone number receives a neutral / silent response on the OTP request form — no specific "blocked" message is shown, preventing phone enumeration. The phone is removed from the block list after the test.',
];
