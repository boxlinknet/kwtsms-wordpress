<?php
/**
 * Tests for KwtSMS_User_Meta — phone field save logic.
 *
 * Covers:
 *  - JS-combined field used when present.
 *  - Server-side fallback (dial code + local number) when combined field is empty.
 *  - Meta cleared when both combined and local fields are empty.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_KwtSMS_User_Meta
 */
class Test_KwtSMS_User_Meta extends TestCase {

	/** @var array<int,array<string,string>> Simulated user-meta storage. */
	private static $user_meta = array();

	// =========================================================================
	// setUp / tearDown
	// =========================================================================

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		self::$user_meta = array();

		// Core WP option helpers (used by settings / logging paths not exercised here).
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );

		// Sanitize helpers — mirror WP behaviour closely enough for unit tests.
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );

		// Security guards — always pass in unit-test context.
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// WP_Error type check.
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );

		// User-meta store backed by a static array.
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'update_user_meta' )->alias( function ( $uid, $key, $val ) {
			self::$user_meta[ $uid ][ $key ] = $val;
			return true;
		} );
		Functions\when( 'delete_user_meta' )->alias( function ( $uid, $key ) {
			unset( self::$user_meta[ $uid ][ $key ] );
			return true;
		} );

		// Constructor calls add_action four times — swallow them.
		Functions\when( 'add_action' )->justReturn( null );

		// Internationalisation helpers used inside normalize_phone error message.
		Functions\when( '__' )->alias( function ( $text ) { return $text; } );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );
	}

	protected function tearDown(): void {
		// Reset superglobal between tests.
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a minimal $_POST array for save_phone_field() and call the method.
	 *
	 * @param int    $user_id      Fake user ID.
	 * @param array  $post_fields  Fields to merge into $_POST.
	 */
	private function invoke_save( int $user_id, array $post_fields ): void {
		// The nonce field must be present to pass the early-return guard.
		$_POST = array_merge(
			array( 'kwtsms_phone_nonce' => 'valid_nonce' ),
			$post_fields
		);

		( new KwtSMS_User_Meta() )->save_phone_field( $user_id );
	}

	// =========================================================================
	// Tests
	// =========================================================================

	/**
	 * When the JS-combined field is populated, it is used directly.
	 *
	 * Simulates a browser that has JS enabled: the hidden `kwtsms_phone` field
	 * already contains the full international number built by the inline script.
	 */
	public function test_save_phone_field_uses_js_combined_field_when_present(): void {
		$this->invoke_save(
			1,
			array(
				'kwtsms_phone'       => '96598765432',
				'kwtsms_dial_code'   => '965',
				'kwtsms_local_phone' => '99220322',
			)
		);

		$this->assertArrayHasKey( 1, self::$user_meta );
		$this->assertSame( '96598765432', self::$user_meta[1]['kwtsms_phone'] );
	}

	/**
	 * When the JS-combined field is empty, the server builds the phone from
	 * dial code + local number — covering no-JS environments.
	 *
	 * Simulates a browser with JS disabled: `kwtsms_phone` is empty (its initial
	 * value was '' before JS ran), but `kwtsms_dial_code` and `kwtsms_local_phone`
	 * carry the user's actual input.
	 */
	public function test_save_phone_field_falls_back_to_dial_plus_local_when_combined_empty(): void {
		$this->invoke_save(
			2,
			array(
				'kwtsms_phone'       => '',
				'kwtsms_dial_code'   => '965',
				'kwtsms_local_phone' => '99220322',
			)
		);

		$this->assertArrayHasKey( 2, self::$user_meta );
		$this->assertSame( '96598765432', self::$user_meta[2]['kwtsms_phone'] );
	}

	/**
	 * When both `kwtsms_phone` and `kwtsms_local_phone` are empty, the meta is
	 * cleared — no partial or invalid value is stored.
	 */
	public function test_save_phone_field_clears_meta_when_both_combined_and_local_empty(): void {
		// Seed a pre-existing value so we can verify it gets deleted.
		self::$user_meta[3]['kwtsms_phone'] = '96598765432';

		$this->invoke_save(
			3,
			array(
				'kwtsms_phone'       => '',
				'kwtsms_dial_code'   => '965',
				'kwtsms_local_phone' => '',
			)
		);

		// Meta key should be absent after clearing.
		$this->assertArrayNotHasKey( 'kwtsms_phone', self::$user_meta[3] ?? array() );
	}
}

/**
 * Class Test_KwtSMS_User_Meta_Registration
 *
 * Tests for the registration form phone field hooks:
 *  - validate_registration_phone() — optional field, validates when non-empty.
 *  - save_registration_phone()     — saves normalized phone to user meta.
 */
class Test_KwtSMS_User_Meta_Registration extends TestCase {

	/** @var array<int,array<string,string>> Simulated user-meta storage. */
	private static $user_meta = array();

	// =========================================================================
	// setUp / tearDown
	// =========================================================================

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		self::$user_meta = array();

		// Core WP option helpers.
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );

		// Sanitize helpers — mirror WP behaviour closely enough for unit tests.
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias( function ( $v ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
		} );
		Functions\when( 'wp_unslash' )->alias( function ( $v ) { return $v; } );

		// Security guards — always pass in unit-test context.
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// WP_Error type check.
		Functions\when( 'is_wp_error' )->alias( function ( $v ) {
			return $v instanceof WP_Error;
		} );

		// User-meta store backed by a static array.
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'update_user_meta' )->alias( function ( $uid, $key, $val ) {
			self::$user_meta[ $uid ][ $key ] = $val;
			return true;
		} );
		Functions\when( 'delete_user_meta' )->alias( function ( $uid, $key ) {
			unset( self::$user_meta[ $uid ][ $key ] );
			return true;
		} );

		// Constructor calls add_action / add_filter — swallow them.
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( null );

		// Internationalisation helpers used inside normalize_phone error message.
		Functions\when( '__' )->alias( function ( $text ) { return $text; } );
		Functions\when( 'esc_html' )->alias( 'htmlspecialchars' );
	}

	protected function tearDown(): void {
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Tests — validate_registration_phone()
	// =========================================================================

	/**
	 * Empty phone passes validation (field is optional).
	 *
	 * When no phone is submitted the filter must return the errors object
	 * unchanged — no kwtsms_invalid_phone error is added.
	 */
	public function test_validate_registration_phone_passes_when_empty(): void {
		$_POST = array( 'kwtsms_phone_reg' => '' );

		$errors = new WP_Error();
		$result = ( new KwtSMS_User_Meta() )->validate_registration_phone( $errors, 'testuser', 'test@example.com' );

		$this->assertFalse( $result->has_errors(), 'No errors expected for empty phone.' );
	}

	/**
	 * A valid international phone passes validation.
	 *
	 * 96598765432 is a well-formed Kuwaiti number; normalize_phone() should
	 * return it as-is, so no error is added.
	 */
	public function test_validate_registration_phone_passes_with_valid_phone(): void {
		$_POST = array( 'kwtsms_phone_reg' => '96598765432' );

		$errors = new WP_Error();
		$result = ( new KwtSMS_User_Meta() )->validate_registration_phone( $errors, 'testuser', 'test@example.com' );

		$this->assertFalse( $result->has_errors(), 'No errors expected for a valid phone number.' );
	}

	/**
	 * An invalid phone (non-numeric string) triggers a validation error.
	 *
	 * 'abc' cannot be normalised; normalize_phone() returns a WP_Error, which
	 * must be propagated into the $errors bag as kwtsms_invalid_phone.
	 */
	public function test_validate_registration_phone_adds_error_for_invalid_phone(): void {
		$_POST = array( 'kwtsms_phone_reg' => 'abc' );

		$errors = new WP_Error();
		$result = ( new KwtSMS_User_Meta() )->validate_registration_phone( $errors, 'testuser', 'test@example.com' );

		$this->assertTrue( $result->has_errors(), 'An error should be added for an invalid phone.' );
		$this->assertNotEmpty( $result->get_error_message( 'kwtsms_invalid_phone' ) );
	}

	// =========================================================================
	// Tests — save_registration_phone()
	// =========================================================================

	/**
	 * A valid phone is normalised and saved to kwtsms_phone user meta.
	 *
	 * After save_registration_phone() runs with a valid phone, the meta store
	 * must contain the normalised number for the given user ID.
	 */
	public function test_save_registration_phone_saves_normalized_phone(): void {
		$_POST = array( 'kwtsms_phone_reg' => '96598765432' );

		( new KwtSMS_User_Meta() )->save_registration_phone( 42 );

		$this->assertArrayHasKey( 42, self::$user_meta );
		$this->assertSame( '96598765432', self::$user_meta[42]['kwtsms_phone'] );
	}

	/**
	 * An empty phone skips the save — update_user_meta is never called.
	 *
	 * When kwtsms_phone_reg is empty the method must return early without
	 * writing anything to user meta.
	 */
	public function test_save_registration_phone_skips_when_empty(): void {
		$_POST = array( 'kwtsms_phone_reg' => '' );

		( new KwtSMS_User_Meta() )->save_registration_phone( 99 );

		$this->assertArrayNotHasKey( 99, self::$user_meta, 'update_user_meta must not be called for an empty phone.' );
	}
}
