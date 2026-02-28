<?php
/**
 * Tests for the plugin bootstrap file.
 *
 * Verifies that critical safety guards are present in wp-kwtsms-otp.php.
 *
 * @package KwtSMS_OTP
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_Bootstrap
 */
class Test_KwtSMS_Bootstrap extends TestCase {

	public function test_bootstrap_file_contains_disabled_constant_guard() {
		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/wp-kwtsms-otp.php' );
		$this->assertStringContainsString( 'KWTSMS_OTP_DISABLED', $bootstrap );
	}
}
