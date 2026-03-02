<?php
/**
 * Tests for KwtSMS_API — phone normalizer, error mapping, request layer.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_API
 */
class Test_KwtSMS_API extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WP options / sanitize functions used by append_send_log / append_attempt_log.
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_textarea_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// normalize_phone
	// =========================================================================

	public function test_normalize_phone_strips_plus() {
		$result = KwtSMS_API::normalize_phone( '+96598765432' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_strips_double_zero() {
		$result = KwtSMS_API::normalize_phone( '0096598765432' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_removes_spaces() {
		$result = KwtSMS_API::normalize_phone( '965 9922 0322' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_removes_dashes() {
		$result = KwtSMS_API::normalize_phone( '965-9922-0322' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_converts_arabic_numerals() {
		// Arabic-Indic numerals: ٩٦٥٩٩٢٢٠٣٢٢
		$result = KwtSMS_API::normalize_phone( '٩٦٥٩٩٢٢٠٣٢٢' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_converts_eastern_arabic_numerals() {
		// Extended Arabic-Indic numerals (Persian/Urdu): ۹۶۵۹۹۲۲۰۳۲۲
		$result = KwtSMS_API::normalize_phone( '۹۶۵۹۹۲۲۰۳۲۲' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_removes_parentheses_and_dots() {
		$result = KwtSMS_API::normalize_phone( '(965) 9922.0322' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_strips_plus_and_spaces_combined() {
		$result = KwtSMS_API::normalize_phone( '+965 9922 0322' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_valid_without_changes() {
		$result = KwtSMS_API::normalize_phone( '96598765432' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_returns_wp_error_for_letters() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$result = KwtSMS_API::normalize_phone( 'not-a-phone' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_phone', $result->get_error_code() );
	}

	public function test_normalize_phone_returns_wp_error_for_too_short() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$result = KwtSMS_API::normalize_phone( '1234567' ); // 7 digits — too short.
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_normalize_phone_returns_wp_error_for_too_long() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$result = KwtSMS_API::normalize_phone( '1234567890123456' ); // 16 digits — too long.
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_normalize_phone_accepts_8_digit_min() {
		$result = KwtSMS_API::normalize_phone( '12345678' ); // 8 digits — minimum.
		$this->assertSame( '12345678', $result );
	}

	public function test_normalize_phone_accepts_15_digit_max() {
		$result = KwtSMS_API::normalize_phone( '123456789012345' ); // 15 digits — maximum.
		$this->assertSame( '123456789012345', $result );
	}

	// =========================================================================
	// map_error_code
	// =========================================================================

	public function test_map_error_code_returns_string_for_known_code() {
		Functions\when( '__' )->returnArg( 1 );
		$msg = KwtSMS_API::map_error_code( 'ERR003' );
		$this->assertIsString( $msg );
		$this->assertNotEmpty( $msg );
	}

	public function test_map_error_code_returns_fallback_for_unknown_code() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$msg = KwtSMS_API::map_error_code( 'ERR999' );
		$this->assertIsString( $msg );
		$this->assertStringContainsString( 'ERR999', $msg );
	}

	public function test_map_error_code_covers_all_known_codes() {
		Functions\when( '__' )->returnArg( 1 );
		$codes = array(
			'ERR001', 'ERR002', 'ERR003', 'ERR004', 'ERR005',
			'ERR006', 'ERR007', 'ERR008', 'ERR009', 'ERR010',
			'ERR011', 'ERR012', 'ERR013', 'ERR019', 'ERR020',
			'ERR021', 'ERR022', 'ERR023', 'ERR024', 'ERR025',
			'ERR026', 'ERR027', 'ERR028', 'ERR029', 'ERR030',
			'ERR031', 'ERR032', 'ERR033',
		);
		foreach ( $codes as $code ) {
			$msg = KwtSMS_API::map_error_code( $code );
			$this->assertIsString( $msg, "Expected string for $code" );
			$this->assertNotEmpty( $msg, "Expected non-empty message for $code" );
		}
	}

	// =========================================================================
	// send_sms — unit test with mocked wp_remote_post
	// =========================================================================

	public function test_send_sms_returns_error_when_credentials_missing() {
		Functions\when( '__' )->returnArg( 1 );
		$api    = new KwtSMS_API( '', '', false );
		$result = $api->send_sms( '96598765432', 'KWTSMS', 'Test message' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'kwtsms_no_credentials', $result->get_error_code() );
	}

	public function test_send_sms_test_mode_writes_to_error_log() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"result":"SUCCESS","msg-id":"12345","balance-after":"90.00"}',
			)
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"result":"SUCCESS","msg-id":"12345","balance-after":"90.00"}' );
		Functions\when( 'is_wp_error' )->alias( function( $v ) { return $v instanceof WP_Error; } );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );

		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			define( 'WP_DEBUG_LOG', true );
		}

		$api    = new KwtSMS_API( 'testuser', 'testpass', true );
		$result = $api->send_sms( '96598765432', 'KWTSMS', 'Your code is: 123456' );

		// Test mode + WP_DEBUG_LOG — just verify no exception and result is an array.
		$this->assertIsArray( $result );
	}

	// =========================================================================
	// normalize_phone — edge cases
	// =========================================================================

	public function test_normalize_phone_with_dot_separator() {
		// +965.98765432 — dots used as separators (common Kuwaiti format).
		$result = KwtSMS_API::normalize_phone( '+965.98765432' );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_sql_injection_string() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$result = KwtSMS_API::normalize_phone( "'; DROP TABLE users; --" );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_normalize_phone_html_tags() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$result = KwtSMS_API::normalize_phone( '<script>alert(1)</script>' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_normalize_phone_newline_and_tab_chars() {
		$result = KwtSMS_API::normalize_phone( "96598765432\n\t" );
		$this->assertSame( '96598765432', $result );
	}

	public function test_normalize_phone_arabic_alpha_string() {
		// Arabic letters (not numerals) — must reject.
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$result = KwtSMS_API::normalize_phone( 'مرحبا' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_normalize_phone_unicode_control_chars() {
		// Null byte and zero-width space injected around a valid number.
		// After stripping non-digits, should be valid.
		$result = KwtSMS_API::normalize_phone( "\x0096598765432\xe2\x80\x8b" );
		$this->assertSame( '96598765432', $result );
	}

	// =========================================================================
	// Debug log rotation
	// =========================================================================

	public function test_debug_log_max_bytes_constant_is_one_mib() {
		$this->assertSame( 1048576, KwtSMS_API::DEBUG_LOG_MAX_BYTES );
	}

	public function test_debug_log_rotation_code_exists_in_source() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-kwtsms-api.php' );
		$this->assertStringContainsString( 'DEBUG_LOG_MAX_BYTES', $source );
		$this->assertStringContainsString( 'rename(', $source );
	}
}

/**
 * Minimal WP_Error stub for unit tests.
 *
 * Supports multiple errors via add() so that registration-form validation
 * tests can call $errors->add() and $errors->has_errors() exactly as the
 * real WP_Error class does.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var array<int,array{code:string,message:string,data:mixed}> */
		private $errors = array();

		public function __construct( $code = '', $message = '', $data = array() ) {
			if ( '' !== $code ) {
				$this->errors[] = array(
					'code'    => $code,
					'message' => $message,
					'data'    => $data,
				);
			}
		}

		/**
		 * Add an error — mirrors WP_Error::add().
		 *
		 * @param string $code    Error code.
		 * @param string $message Human-readable message.
		 * @param mixed  $data    Optional data.
		 */
		public function add( $code, $message, $data = array() ) {
			$this->errors[] = array(
				'code'    => $code,
				'message' => $message,
				'data'    => $data,
			);
		}

		/** @return bool True if any errors have been added. */
		public function has_errors() {
			return ! empty( $this->errors );
		}

		/** @return string First error code, or empty string when no errors. */
		public function get_error_code() {
			return isset( $this->errors[0] ) ? $this->errors[0]['code'] : '';
		}

		/**
		 * Return message for a specific error code, or first message when no
		 * code is given.
		 *
		 * @param string $code Optional error code to filter by.
		 * @return string
		 */
		public function get_error_message( $code = '' ) {
			if ( '' === $code ) {
				return isset( $this->errors[0] ) ? $this->errors[0]['message'] : '';
			}
			foreach ( $this->errors as $err ) {
				if ( $err['code'] === $code ) {
					return $err['message'];
				}
			}
			return '';
		}

		/** @return mixed First error data, or empty array. */
		public function get_error_data() {
			return isset( $this->errors[0] ) ? $this->errors[0]['data'] : array();
		}
	}
}
