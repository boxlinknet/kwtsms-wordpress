<?php
/**
 * Browser E2E Test 03: WooCommerce Guest Checkout with Optional OTP Gate
 *
 * A guest customer browses the shop, adds a product to cart, fills in billing
 * details (including phone), and completes checkout.  If the checkout OTP gate
 * is enabled the test also handles the OTP verification step.
 *
 * @test-name    03-checkout-guest
 * @environment  docker (http://localhost:8080)
 */

require_once __DIR__ . '/helpers.php';

return [
    'name'        => '03-checkout-guest',
    'description' => 'Guest checkout via WooCommerce — includes OTP gate step if enabled.',

    'preconditions' => [
        'WooCommerce plugin active',
        'At least one published product in the shop',
        'Guest checkout allowed (WooCommerce → Settings → Accounts & Privacy)',
        'test_mode     = 1  (Admin → Gateway → Test Mode ON)',
        'debug_logging = 1  (Admin → General → Developer Tools)',
        'API credentials configured',
        'Checkout OTP gate: optional — test handles both enabled and disabled',
    ],

    'steps' => [
        [
            'step'        => 1,
            'action'      => 'navigate',
            'description' => 'Open the WooCommerce shop page',
            'url'         => kwtsms_url( '/shop/' ),
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 1, 'shop-page' ),
        ],
        [
            'step'        => 2,
            'action'      => 'click',
            'description' => 'Click "Add to cart" on the first available product',
            'selector'    => '.add_to_cart_button, a.button.add_to_cart_button',
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 2, 'cart' ),
        ],
        [
            'step'        => 3,
            'action'      => 'navigate',
            'description' => 'Navigate directly to the checkout page',
            'url'         => kwtsms_url( '/checkout/' ),
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 3, 'checkout-page' ),
        ],
        [
            'step'        => 4,
            'action'      => 'fill',
            'description' => 'Fill guest billing details including phone number',
            'fields'      => [
                '#billing_first_name' => 'Test',
                '#billing_last_name'  => 'Guest',
                '#billing_email'      => 'guest_checkout@example.com',
                '#billing_phone'      => KWTSMS_TEST_PHONE,
                '#billing_address_1'  => '123 Test Street',
                '#billing_city'       => 'Kuwait City',
                '#billing_country'    => 'KW',
            ],
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 4, 'billing-filled' ),
        ],
        [
            'step'        => 5,
            'action'      => 'click',
            'description' => 'Place the order',
            'selector'    => '#place_order',
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 5, 'after-place-order' ),
        ],
        [
            'step'        => 6,
            'action'      => 'check_log',
            'description' => 'Read OTP from debug log if checkout OTP gate intercepted the order',
            'command'     => 'docker exec wp_site cat /var/www/html/wp-content/kwtsms-debug.log | tail -20',
            'extract'     => 'otp_code',
            'screenshot'  => null,
        ],
        [
            'step'        => 7,
            'action'      => 'fill',
            'description' => 'Enter OTP code if the verification form appeared after order submission',
            'conditional' => 'otp_form_visible',
            'fields'      => [
                '[name="kwtsms_otp"], #kwtsms_otp, [name="otp_code"]' => '{otp_code}',
            ],
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 7, 'otp-entered' ),
        ],
        [
            'step'        => 8,
            'action'      => 'click',
            'description' => 'Submit OTP form if it was present',
            'conditional' => 'otp_form_visible',
            'selector'    => '[type="submit"]',
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 8, 'after-otp-submit' ),
        ],
        [
            'step'        => 9,
            'action'      => 'assert_text',
            'description' => 'Assert the order confirmation / thank-you page is displayed',
            'contains'    => [ 'Order received', 'Thank you', 'order number', 'Your order' ],
            'match'       => 'any',
            'screenshot'  => kwtsms_screenshot_path( '03-checkout-guest', 9, 'order-confirmation' ),
        ],
    ],

    'expected_outcome' => 'Guest checkout completes successfully. If checkout OTP gate is enabled, OTP verification happens between order submission and the thank-you page.',
];
