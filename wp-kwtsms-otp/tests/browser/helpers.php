<?php
/**
 * Helper functions for browser E2E test definitions.
 *
 * @package KwtSMS_OTP\Tests\Browser
 */

require_once __DIR__ . '/config.php';

/**
 * Build the full URL for a path on the test site.
 *
 * @param string $path e.g. '/wp-login.php'
 * @return string
 */
function kwtsms_url( string $path ): string {
    return rtrim( KWTSMS_TEST_BASE_URL, '/' ) . '/' . ltrim( $path, '/' );
}

/**
 * Return the directory where screenshots for a test should be saved.
 * Creates the directory if it does not exist.
 *
 * @param string $test_name e.g. '01-register-wp-user'
 * @return string Absolute directory path.
 */
function kwtsms_screenshot_dir( string $test_name ): string {
    $dir = KWTSMS_SCREENSHOT_ROOT . '/' . $test_name;
    if ( ! is_dir( $dir ) ) {
        mkdir( $dir, 0755, true );
    }
    return $dir;
}

/**
 * Return the full path for a screenshot file.
 *
 * @param string $test_name  e.g. '01-register-wp-user'
 * @param int    $step       Step number.
 * @param string $description e.g. 'otp-form'
 * @return string
 */
function kwtsms_screenshot_path( string $test_name, int $step, string $description ): string {
    $dir = kwtsms_screenshot_dir( $test_name );
    return $dir . '/step-' . str_pad( $step, 2, '0', STR_PAD_LEFT ) . '-' . $description . '.png';
}
