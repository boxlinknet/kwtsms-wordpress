<?php
/**
 * Browser E2E Test 14: Custom Template Variable Substitution in Sent SMS
 *
 * Configures a custom OTP message template containing {otp} and {site_name}
 * placeholders in the SMS Templates admin page, triggers an OTP, then reads
 * the debug log and verifies that the placeholders have been substituted with
 * the actual OTP value and site name — no literal "{otp}" or "{site_name}"
 * strings should remain in the logged message.  Restores the default template
 * at the end of the test.
 *
 * @test-name    14-template-variables
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

/**
 * A custom template that uses both available placeholders.
 * The log should show the resolved values, not the literal tokens.
 */
$custom_template = 'Your code is {otp} for {site_name}. Valid for 5 mins.';

return [
    'name'        => '14-template-variables',
    'description' => 'Custom OTP template with {otp} and {site_name} placeholders is substituted correctly before sending.',

    'preconditions' => [
        'Admin access to kwtsms OTP → SMS Templates page',
        'Passwordless login or 2FA mode enabled (so an OTP can be triggered)',
        'Test user exists with kwtsms_phone = 96599220322',
        'test_mode     = 1  (Admin → Gateway → Test Mode ON)',
        'debug_logging = 1  (Admin → General → Developer Tools → Debug Logging)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Log in as admin and open the SMS Templates settings page',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=templates' ),
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 1, 'templates-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'fill',
            'description' => 'Set the login_otp (or OTP) message template to include {otp} and {site_name}',
            'fields'      => [
                '[name="kwtsms_tpl_login_otp"], [name*="otp_message"], #kwtsms_tpl_login_otp, textarea[name*="login_otp"]' => $custom_template,
            ],
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 2, 'template-set' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Save the SMS Templates settings',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 3, 'template-saved' ),
        ],
        [
            'step'        => 4,
            'action'      => 'navigate',
            'description' => 'Navigate to the passwordless login page to trigger an OTP',
            'url'         => kwtsms_url( '/wp-login.php?action=kwtsms-passwordless' ),
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 4, 'passwordless-page' ),
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
            'description' => 'Submit to trigger OTP generation and SMS dispatch',
            'selector'    => '[type="submit"], #wp-submit',
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 6, 'otp-triggered' ),
        ],
        [
            'step'        => 7,
            'action'      => 'check_log',
            'description' => 'Read the full debug log entry for the sent SMS message',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -30',
            'extract'     => 'log_output',
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 7, 'debug-log' ),
        ],
        [
            'step'        => 8,
            'action'      => 'assert_not_log',
            'description' => 'Assert the log does NOT contain the literal placeholder token {otp}',
            'log_command' => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -30',
            'not_contains' => [ '{otp}', '{site_name}' ],
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 8, 'substitution-confirmed' ),
        ],
        [
            'step'        => 9,
            'action'      => 'assert_log_contains',
            'description' => 'Assert the log contains "Your code is" (confirming template prefix was preserved)',
            'log_command' => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -30',
            'contains'    => 'Your code is',
            'screenshot'  => null,
        ],
        [
            'step'        => 10,
            'action'      => 'navigate',
            'description' => 'Return to the SMS Templates page to restore the default template',
            'url'         => kwtsms_url( '/wp-admin/options-general.php?page=kwtsms-otp&tab=templates' ),
            'screenshot'  => null,
        ],
        [
            'step'        => 11,
            'action'      => 'click',
            'description' => 'Click the reset / restore default button for the login_otp template',
            'selector'    => '[data-reset="kwtsms_tpl_login_otp"], .kwtsms-reset-template, [name="kwtsms_reset_login_otp"]',
            'screenshot'  => null,
        ],
        [
            'step'        => 12,
            'action'      => 'click',
            'description' => 'Save the restored default template',
            'selector'    => '[type="submit"], #submit',
            'screenshot'  => kwtsms_screenshot_path( '14-template-variables', 12, 'template-restored' ),
        ],
    ],

    'expected_outcome' => 'The custom template "Your code is {otp} for {site_name}" is substituted before sending: the debug log shows the actual OTP digits and site name instead of the literal placeholder tokens.',
];
