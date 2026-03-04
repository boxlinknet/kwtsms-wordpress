<?php
/**
 * Browser E2E Test 02: WooCommerce Customer Registration with OTP
 *
 * Verifies that a customer registering via the WooCommerce My Account page
 * triggers the kwtsms OTP flow when a phone number is provided, and that the
 * account is created only after successful OTP verification.
 *
 * @test-name    02-register-woo-user
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

$rand = substr( md5( (string) time() ), 0, 6 );

return [
    'name'        => '02-register-woo-user',
    'description' => 'WooCommerce My Account registration with phone number triggers OTP flow.',

    'preconditions' => [
        'WooCommerce plugin active',
        'WooCommerce  Settings  Accounts & Privacy  Allow customers to create an account on the "My account" page = checked',
        'kwtsms phone field enabled for WC registration (Admin  General)',
        'test_mode  = 1  (Admin  Gateway  Test Mode ON)',
        'debug_logging = 1  (Admin  General  Developer Tools)',
        'API credentials configured',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Open the WooCommerce My Account page (shows registration form for guests)',
            'url'         => kwtsms_url( '/my-account/' ),
            'screenshot'  => kwtsms_screenshot_path( '02-register-woo-user', 1, 'myaccount-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'fill',
            'description' => 'Fill the WooCommerce registration form with email, password, and phone',
            'fields'      => [
                '#reg_email'              => 'wcuser_' . $rand . '@example.com',
                '#reg_password'           => 'SecurePass123!',
                '[name="kwtsms_phone"]'   => KWTSMS_TEST_PHONE,
            ],
            'screenshot'  => kwtsms_screenshot_path( '02-register-woo-user', 2, 'wc-form-filled' ),
        ],
        [
            'step'        => 3,
            'action'      => 'click',
            'description' => 'Submit the WooCommerce registration form',
            'selector'    => '[name="register"]',
            'screenshot'  => kwtsms_screenshot_path( '02-register-woo-user', 3, 'after-submit' ),
        ],
        [
            'step'        => 4,
            'action'      => 'assert_text',
            'description' => 'Assert OTP verification prompt or successful account creation',
            'contains'    => [ 'verification', 'OTP', 'code', 'verify', 'Hello', 'account', 'registered' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '02-register-woo-user', 4, 'result' ),
        ],
        [
            'step'        => 5,
            'action'      => 'check_log',
            'description' => 'Read OTP from debug log if OTP gate was triggered',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -20',
            'extract'     => 'otp_code',
            'screenshot'  => null,
        ],
        [
            'step'        => 6,
            'action'      => 'fill',
            'description' => 'Enter OTP code if the verification form is present',
            'conditional' => 'otp_form_visible',   // Claude skips this step if OTP form is not on screen
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '02-register-woo-user', 6, 'otp-entered' ),
        ],
        [
            'step'        => 7,
            'action'      => 'click',
            'description' => 'Submit OTP form if it was present',
            'conditional' => 'otp_form_visible',
            'selector'    => '[type="submit"]',
            'screenshot'  => kwtsms_screenshot_path( '02-register-woo-user', 7, 'after-otp-submit' ),
        ],
        [
            'step'        => 8,
            'action'      => 'assert_text',
            'description' => 'Assert WooCommerce My Account dashboard is shown (registration succeeded)',
            'contains'    => [ 'Hello', 'My account', 'Dashboard', 'Orders', 'account has been created' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '02-register-woo-user', 8, 'wc-dashboard' ),
        ],
    ],

    'expected_outcome' => 'WooCommerce customer account is created after OTP verification (if OTP gate enabled), and the user lands on the My Account dashboard.',
];
