<?php
/**
 * Tests for Per-Role OTP Enforcement (Task 5 — v2.5.0).
 *
 * Verifies that the role-check logic inserted into intercept_login() correctly:
 *   1. Bypasses OTP for a user whose role is NOT in the required list.
 *   2. Requires OTP for a user whose role IS in the required list.
 *   3. Requires OTP for all users when the required list is empty (default).
 *
 * The helper apply_role_check() mirrors the exact branching logic from
 * KwtSMS_Login_OTP::intercept_login() — the same approach used in
 * test-plugin-resend.php — so the business rules can be tested without
 * instantiating the full KwtSMS_Login_OTP class or wiring WordPress hooks.
 *
 * @package KwtSMS_OTP
 */

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Minimal WP_User stub for unit tests.
 *
 * Provides the $roles property and ID field used by intercept_login() role check.
 */
if ( ! class_exists( 'WP_User' ) ) {
	// phpcs:ignore
	class WP_User {
		/** @var int */
		public $ID = 0;

		/** @var string[] */
		public $roles = array();

		/**
		 * @param int      $id    User ID.
		 * @param string[] $roles User roles array.
		 */
		public function __construct( $id, $roles = array() ) {
			$this->ID    = $id;
			$this->roles = $roles;
		}
	}
}

/**
 * Class Test_Role_OTP_Enforcement
 *
 * Covers the per-role OTP bypass/enforcement logic from intercept_login().
 */
class Test_Role_OTP_Enforcement extends TestCase {

	// =========================================================================
	// Lifecycle
	// =========================================================================

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// =========================================================================
	// Helper: mirrors the role-check logic from intercept_login().
	//
	// The exact block from class-kwtsms-login-otp.php:
	//
	//   $required_roles = $this->plugin->settings->get( 'general.otp_required_roles', array() );
	//   if ( ! empty( $required_roles ) ) {
	//       $user_roles = $user->roles ?? array();
	//       $intersect  = array_intersect( $user_roles, (array) $required_roles );
	//       if ( empty( $intersect ) ) {
	//           return $user;   // bypass OTP
	//       }
	//   }
	//   // ... proceed to OTP challenge ...
	// =========================================================================

	/**
	 * Apply the role-check logic from intercept_login().
	 *
	 * Returns true when OTP should be skipped (user returned directly),
	 * or false when the flow continues to the OTP challenge.
	 *
	 * @param string[] $required_roles Configured required roles (from settings).
	 * @param WP_User  $user           Authenticated user object.
	 *
	 * @return bool True = OTP bypassed, False = OTP required.
	 */
	private function apply_role_check( array $required_roles, WP_User $user ) {
		if ( ! empty( $required_roles ) ) {
			$user_roles = $user->roles ?? array();
			$intersect  = array_intersect( $user_roles, (array) $required_roles );
			if ( empty( $intersect ) ) {
				// User's role is not in the required list — bypass OTP.
				return true;
			}
		}
		// User must pass OTP challenge.
		return false;
	}

	// =========================================================================
	// Tests
	// =========================================================================

	/**
	 * When otp_required_roles is ['administrator'] and the user is a 'subscriber',
	 * the role check must bypass OTP and return the user directly.
	 */
	public function test_role_not_in_required_list_bypasses_otp() {
		$required_roles = array( 'administrator' );
		$user           = new WP_User( 5, array( 'subscriber' ) );

		$bypassed = $this->apply_role_check( $required_roles, $user );

		$this->assertTrue(
			$bypassed,
			'A subscriber must bypass OTP when only "administrator" is in otp_required_roles.'
		);
	}

	/**
	 * When otp_required_roles is ['subscriber'] and the user is a 'subscriber',
	 * the role check must NOT bypass OTP — the user must go through the challenge.
	 */
	public function test_role_in_required_list_requires_otp() {
		$required_roles = array( 'subscriber' );
		$user           = new WP_User( 7, array( 'subscriber' ) );

		$bypassed = $this->apply_role_check( $required_roles, $user );

		$this->assertFalse(
			$bypassed,
			'A subscriber must NOT bypass OTP when "subscriber" is in otp_required_roles.'
		);
	}

	/**
	 * When otp_required_roles is empty (the default), the role check must never
	 * bypass OTP — all users must go through the challenge regardless of role.
	 */
	public function test_empty_required_roles_applies_to_all() {
		$required_roles = array();

		// Test with different roles to confirm none are bypassed.
		$roles_to_test = array(
			array( 'subscriber' ),
			array( 'editor' ),
			array( 'administrator' ),
			array( 'author' ),
			array( 'contributor' ),
		);

		foreach ( $roles_to_test as $user_roles ) {
			$user     = new WP_User( 1, $user_roles );
			$bypassed = $this->apply_role_check( $required_roles, $user );

			$this->assertFalse(
				$bypassed,
				sprintf(
					'Empty otp_required_roles must require OTP for all users — "%s" was incorrectly bypassed.',
					implode( ', ', $user_roles )
				)
			);
		}
	}

	// =========================================================================
	// Additional edge-case coverage
	// =========================================================================

	/**
	 * A user with multiple roles must be required to pass OTP if ANY of their
	 * roles appears in otp_required_roles.
	 */
	public function test_user_with_multiple_roles_requires_otp_if_any_role_matches() {
		$required_roles = array( 'editor' );
		$user           = new WP_User( 10, array( 'subscriber', 'editor' ) );

		$bypassed = $this->apply_role_check( $required_roles, $user );

		$this->assertFalse(
			$bypassed,
			'A user with roles [subscriber, editor] must NOT bypass OTP when "editor" is required.'
		);
	}

	/**
	 * A user with multiple roles must bypass OTP when none of their roles
	 * appear in otp_required_roles.
	 */
	public function test_user_with_multiple_roles_bypasses_otp_when_no_role_matches() {
		$required_roles = array( 'administrator' );
		$user           = new WP_User( 11, array( 'subscriber', 'contributor' ) );

		$bypassed = $this->apply_role_check( $required_roles, $user );

		$this->assertTrue(
			$bypassed,
			'A user with roles [subscriber, contributor] must bypass OTP when only "administrator" is required.'
		);
	}
}
