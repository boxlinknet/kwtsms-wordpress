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

	public function test_passwordless_page_includes_wp_head() {
		$content = file_get_contents( KWTSMS_OTP_DIR . 'includes/views/page-passwordless.php' );
		// Styles are enqueued in the render method and output via wp_head().
		$this->assertStringContainsString( 'wp_head()', $content );
	}

	public function test_otp_page_includes_wp_head() {
		$content = file_get_contents( KWTSMS_OTP_DIR . 'includes/views/page-otp.php' );
		// Styles are enqueued in the render method and output via wp_head().
		$this->assertStringContainsString( 'wp_head()', $content );
	}

	public function test_login_css_has_brand_primary_color() {
		$css = file_get_contents( KWTSMS_OTP_DIR . 'assets/css/login.css' );
		$this->assertStringContainsString( '#FFA200', $css );
	}

	public function test_help_page_contains_lockout_section() {
		$help = file_get_contents(
			dirname( __DIR__ ) . '/admin/views/page-help.php'
		);
		$this->assertStringContainsString( 'KWTSMS_OTP_DISABLED', $help );
		$this->assertStringContainsString( 'kwtsms_phone', $help );
	}

	public function test_integrations_page_contains_all_four_plugins() {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin/views/page-integrations.php' );
		$this->assertStringContainsString( 'WooCommerce', $source );
		$this->assertStringContainsString( 'Contact Form 7', $source );
		$this->assertStringContainsString( 'WPForms', $source );
		$this->assertStringContainsString( 'Elementor', $source );
	}
}
