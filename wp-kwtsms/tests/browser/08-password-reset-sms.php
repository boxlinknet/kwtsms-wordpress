<?php
/**
 * Browser E2E Test 08: SMS-Based Password Reset Full Flow
 *
 * Tests the complete SMS password-reset journey: user clicks "Lost your
 * password?", enters their username, receives an OTP via SMS (captured from
 * the debug log in test mode), verifies the OTP, sets a new password, and
 * then logs in with the new password to confirm the reset succeeded.
 *
 * @test-name    08-password-reset-sms
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

$new_password = 'NewPass_' . substr( md5( (string) time() ), 0, 8 ) . '!';

return [
    'name'        => '08-password-reset-sms',
    'description' => 'Full SMS-based password reset: request → OTP verification → new password → login.',

    'preconditions' => [
        'SMS password reset enabled  (Admin → General → Password Reset = OTP)',
        'Test user exists: username = "testuser", with kwtsms_phone = 96598765432',
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
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 1, 'login-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'click',
            'description' => 'Click the "Lost your password?" link',
            'selector'    => 'a[href*="lostpassword"], #nav a, .login-forgot-password',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 2, 'lost-password-page' ),
        ],
        [
            'step'        => 3,
            'action'      => 'fill',
            'description' => 'Enter the username or email for the account to reset',
            'fields'      => [
                '#user_login' => 'testuser',
            ],
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 3, 'username-entered' ),
        ],
        [
            'step'        => 4,
            'action'      => 'click',
            'description' => 'Submit the lost-password form — plugin intercepts and sends OTP',
            'selector'    => '#wp-submit, [type="submit"]',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 4, 'otp-form' ),
        ],
        [
            'step'        => 5,
            'action'      => 'assert_text',
            'description' => 'Assert the OTP entry form is displayed',
            'contains'    => [ 'code', 'OTP', 'verify', 'Enter', 'sent', 'verification' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 5, 'otp-form-shown' ),
        ],
        [
            'step'        => 6,
            'action'      => 'check_log',
            'description' => 'Read the reset OTP from the kwtsms debug log',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -20',
            'extract'     => 'otp_code',
            'screenshot'  => null,
        ],
        [
            'step'        => 7,
            'action'      => 'fill',
            'description' => 'Enter the OTP code into the verification field',
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 7, 'otp-entered' ),
        ],
        [
            'step'        => 8,
            'action'      => 'click',
            'description' => 'Submit the OTP to proceed to the password reset form',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 8, 'reset-form' ),
        ],
        [
            'step'        => 9,
            'action'      => 'assert_text',
            'description' => 'Assert the WordPress "Enter new password" form is shown',
            'contains'    => [ 'new password', 'Enter', 'reset', 'password', 'rp_key' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 9, 'new-password-form' ),
        ],
        [
            'step'        => 10,
            'action'      => 'fill',
            'description' => 'Enter a new password in the reset form',
            'fields'      => [
                '#pass1, [name="pass1"]' => $new_password,
                '#pass2, [name="pass2"]' => $new_password,
            ],
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 10, 'new-password-entered' ),
        ],
        [
            'step'        => 11,
            'action'      => 'click',
            'description' => 'Submit the new password form',
            'selector'    => '#wp-submit, [type="submit"], .button.button-primary',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 11, 'reset-success' ),
        ],
        [
            'step'        => 12,
            'action'      => 'assert_text',
            'description' => 'Assert password reset was confirmed (success message or login redirect)',
            'contains'    => [ 'password has been reset', 'login', 'success', 'Your password' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 12, 'reset-confirmed' ),
        ],
        [
            'step'        => 13,
            'action'      => 'navigate',
            'description' => 'Navigate back to login page to verify the new password works',
            'url'         => kwtsms_url( '/wp-login.php' ),
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 13, 'login-page-again' ),
        ],
        [
            'step'        => 14,
            'action'      => 'fill',
            'description' => 'Login with testuser and the newly set password',
            'fields'      => [
                '#user_login' => 'testuser',
                '#user_pass'  => $new_password,
            ],
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 14, 'new-creds-entered' ),
        ],
        [
            'step'        => 15,
            'action'      => 'click',
            'description' => 'Submit the login form with the new password',
            'selector'    => '#wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 15, 'logged-in' ),
        ],
        [
            'step'        => 16,
            'action'      => 'assert_text',
            'description' => 'Assert successful login with the new password',
            'contains'    => [ 'Dashboard', 'Howdy', 'My account' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '08-password-reset-sms', 16, 'dashboard' ),
        ],
    ],

    'expected_outcome' => 'User completes the full SMS password reset flow: OTP received via debug log, new password set, and login with the new password succeeds.',
];
