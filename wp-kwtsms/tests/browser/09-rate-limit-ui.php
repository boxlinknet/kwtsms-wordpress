<?php
/**
 * Browser E2E Test 09: Rate Limit Enforcement Visible in UI
 *
 * Verifies that the per-phone rate limiter blocks OTP requests after the
 * configured maximum (RATE_LIMIT_MAX = 3) is reached.  The 4th OTP request
 * within a 10-minute window should display a rate-limit error message and
 * must NOT send another OTP.
 *
 * @test-name    09-rate-limit-ui
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '09-rate-limit-ui',
    'description' => 'The 4th OTP request for the same phone within 10 minutes is blocked with a UI error.',

    'preconditions' => [
        'Passwordless login or 2FA mode enabled  (so the OTP request button is accessible)',
        'Rate limiting enabled — RATE_LIMIT_MAX = 3, window = 600 s  (default plugin settings)',
        'Test user exists with kwtsms_phone = 96599220322',
        'test_mode     = 1  (Admin  Gateway  Test Mode ON)',
        'debug_logging = 1  (Admin  General  Developer Tools  Debug Logging)',
        'API credentials configured',
        'NOTE: Clear rate-limit transients before running (Admin  General  Clear Rate Limits, or restart the container)',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Open the passwordless login page for the first OTP request',
            'url'         => kwtsms_url( '/wp-login.php' ),
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 1, 'passwordless-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'fill',
            'description' => 'OTP request attempt 1 — enter phone and submit',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 2, 'attempt-1-filled' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Submit attempt 1 (should succeed)',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 3, 'attempt-1' ),
        ],
        [
            'step'        => 4,
            'action'      => 'navigate',
            'description' => 'Go back to request OTP again (attempt 2)',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-passwordless' ),
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 4, 'back-for-attempt-2' ),
        ],
        [
            'step'        => 5,
            'action'      => 'fill',
            'description' => 'OTP request attempt 2 — enter phone and submit',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => null,
        ],
        [
            'step'        => 6,
            'action'      => 'click',
            'description' => 'Submit attempt 2 (should succeed — within limit)',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 6, 'attempt-2' ),
        ],
        [
            'step'        => 7,
            'action'      => 'navigate',
            'description' => 'Go back to request OTP again (attempt 3)',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-passwordless' ),
            'screenshot'  => null,
        ],
        [
            'step'        => 8,
            'action'      => 'fill',
            'description' => 'OTP request attempt 3 — enter phone and submit',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => null,
        ],
        [
            'step'        => 9,
            'action'      => 'click',
            'description' => 'Submit attempt 3 (should succeed — at the limit boundary)',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 9, 'attempt-3' ),
        ],
        [
            'step'        => 10,
            'action'      => 'navigate',
            'description' => 'Go back to make the 4th OTP request (should be blocked)',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-passwordless' ),
            'screenshot'  => null,
        ],
        [
            'step'        => 11,
            'action'      => 'fill',
            'description' => 'OTP request attempt 4 — enter phone and submit (expecting rate-limit block)',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="kwtsms_phone"], [name="log"], #user_login, [name="phone"]' => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => null,
        ],
        [
            'step'        => 12,
            'action'      => 'click',
            'description' => 'Submit attempt 4 — must be blocked by rate limiter',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 12, 'attempt-4-blocked' ),
        ],
        [
            'step'        => 13,
            'action'      => 'assert_text',
            'description' => 'Assert a rate-limit error message is shown on the page',
            'contains'    => [ 'too many', 'rate limit', 'limit', 'try again', 'wait', 'exceeded' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '09-rate-limit-ui', 13, 'rate-limit-error' ),
        ],
    ],

    'expected_outcome' => 'After 3 successful OTP requests, the 4th request within the same 10-minute window is blocked and the UI displays a clear rate-limit error message.',
];
