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
				'kwtsms_local_phone' => '98765432',
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
				'kwtsms_local_phone' => '98765432',
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
