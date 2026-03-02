<?php
/**
 * Browser E2E Test 12: "SMS by kwtSMS.com" Referral Link on OTP Pages
 *
 * Verifies that when the referral link setting is enabled in General settings,
 * a footer attribution link to kwtSMS.com appears on every OTP-related page:
 * the standard 2FA / OTP entry page, the passwordless login page, and the
 * lost-password (reset) page.
 *
 * @test-name    12-referral-link
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '12-referral-link',
    'description' => '"SMS by kwtSMS.com" footer link is visible on all OTP-related pages when referral_link = 1.',

    'preconditions' => [
        'Referral link setting enabled  (Admin → General → Show referral link = ON)',
        'Passwordless login or 2FA enabled (so OTP pages are accessible)',
        'Admin credentials: admin / admin',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Log in as admin and open the kwtsms General settings',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=general' ),
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 1, 'admin-general-settings' ),
        ],
        [
            'step'        => 2,
            'action'      => 'click',
            'description' => 'Enable the "Show referral link" toggle/checkbox if not already enabled',
            'selector'    => '[name="kwtsms_referral_link"], #kwtsms_referral_link',
            'conditional' => 'not_checked',
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 2, 'admin-referral-enabled' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Save General settings',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 3, 'admin-saved' ),
        ],
        [
            'step'        => 4,
            'action'      => 'navigate',
            'description' => 'Navigate to the kwtsms OTP login/verification page',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-otp' ),
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 4, 'login-otp-footer' ),
        ],
        [
            'step'        => 5,
            'action'      => 'assert_text',
            'description' => 'Assert the kwtSMS referral link is visible on the OTP login page',
            'contains'    => [ 'kwtsms', 'kwtSMS', 'kwtsms.com', 'SMS by' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 5, 'login-otp-link' ),
        ],
        [
            'step'        => 6,
            'action'      => 'navigate',
            'description' => 'Navigate to the passwordless login page',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-passwordless' ),
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 6, 'passwordless-footer' ),
        ],
        [
            'step'        => 7,
            'action'      => 'assert_text',
            'description' => 'Assert the kwtSMS referral link is visible on the passwordless login page',
            'contains'    => [ 'kwtsms', 'kwtSMS', 'kwtsms.com', 'SMS by' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 7, 'passwordless-link' ),
        ],
        [
            'step'        => 8,
            'action'      => 'navigate',
            'description' => 'Navigate to the WordPress lost-password / reset page',
            'url'         => kwtsms_url( '/wp-login.php?action=lostpassword' ),
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 8, 'reset-footer' ),
        ],
        [
            'step'        => 9,
            'action'      => 'assert_text',
            'description' => 'Assert the kwtSMS referral link is visible on the lost-password page',
            'contains'    => [ 'kwtsms', 'kwtSMS', 'kwtsms.com', 'SMS by' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '12-referral-link', 9, 'reset-link' ),
        ],
    ],

    'expected_outcome' => 'The "SMS by kwtSMS.com" attribution link appears in the footer of all three OTP-related pages when the referral link setting is enabled.',
];
