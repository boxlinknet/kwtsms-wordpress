<?php
/**
 * Tests for view template structure.
 *
 * Verifies critical markup is present in login page templates.
 *
 * @package KwtSMS_OTP
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_View_Templates
 */
class Test_View_Templates extends TestCase {

	public function test_passwordless_page_includes_wp_login_css() {
		$content = file_get_contents( KWTSMS_OTP_DIR . 'includes/views/page-passwordless.php' );
		$this->assertStringContainsString( 'wp-login.php', $content );
	}

	public function test_otp_page_includes_wp_login_css() {
		$content = file_get_contents( KWTSMS_OTP_DIR . 'includes/views/page-otp.php' );
		$this->assertStringContainsString( 'wp-login.php', $content );
	}

	public function test_login_css_has_brand_primary_color() {
		$css = file_get_contents( KWTSMS_OTP_DIR . 'assets/css/login.css' );
		$this->assertStringContainsString( '#FFA200', $css );
	}
}
