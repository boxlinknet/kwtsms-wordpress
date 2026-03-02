<?php
/**
 * Browser E2E Test 04: Passwordless Login — User Has No Phone Number
 *
 * Verifies that when a user without a stored phone number attempts passwordless
 * login, the system shows an appropriate error rather than a generic success
 * message, providing clear user feedback without leaking account existence.
 *
 * @test-name    04-passwordless-no-phone
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '04-passwordless-no-phone',
    'description' => 'Passwordless login attempt for a user with no phone shows a clear error.',

    'preconditions' => [
        'Passwordless login mode enabled (Admin → General → Login Mode = Passwordless)',
        'A test user exists WITHOUT a phone number stored in usermeta (kwtsms_phone)',
        'Suggested: create user "nophone_user" via WP admin with no phone number',
        'test_mode = 1  (Admin → Gateway → Test Mode ON)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Open the standard WordPress login page',
            'url'         => kwtsms_url( '/wp-login.php' ),
            'screenshot'  => kwtsms_screenshot_path( '04-passwordless-no-phone', 1, 'login-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'click',
            'description' => 'Click the "Sign in with OTP" or passwordless login link',
            'selector'    => 'a[href*="kwtsms-passwordless"], a[href*="passwordless"], .kwtsms-passwordless-link, #kwtsms-passwordless-link',
            'screenshot'  => kwtsms_screenshot_path( '04-passwordless-no-phone', 2, 'passwordless-page' ),
        ],
        [
            'step'        => 3,
            'action'      => 'fill',
            'description' => 'Enter the email or username of a user who has no phone number',
            'fields'      => [
                '[name="kwtsms_identifier"], [name="log"], #user_login, [name="email"]' => 'nophone_user',
            ],
            'screenshot'  => kwtsms_screenshot_path( '04-passwordless-no-phone', 3, 'email-entered' ),
        ],
        [
            'step'        => 4,
            'action'      => 'click',
            'description' => 'Submit the passwordless login form',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '04-passwordless-no-phone', 4, 'no-phone-error' ),
        ],
        [
            'step'        => 5,
            'action'      => 'assert_text',
            'description' => 'Assert an error message is displayed (not a silent success)',
            'contains'    => [ 'phone', 'no phone', 'not found', 'unable', 'error', 'cannot', 'missing' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '04-passwordless-no-phone', 5, 'error-state' ),
        ],
        [
            'step'        => 6,
            'action'      => 'assert_not_text',
            'description' => 'Assert the user was NOT logged in (dashboard not shown)',
            'not_contains' => [ 'Dashboard', 'Howdy', 'wp-admin' ],
            'screenshot'  => null,
        ],
    ],

    'expected_outcome' => 'The passwordless login form displays a clear error message when the user has no phone number, and the user remains on the login/error page rather than being authenticated.',
];
