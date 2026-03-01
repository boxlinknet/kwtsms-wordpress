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
	// The exact block from class-kwtsms-login-otp.php (with super-admin guard):
	//
	//   $required_roles = $this->plugin->settings->get( 'general.otp_required_roles', array() );
	//   if ( ! empty( $required_roles ) ) {
	//       $user_roles = $user->roles ?? array();
	//       // Multisite: super admin may have an empty roles array — treat as administrator.
	//       if ( empty( $user_roles ) && function_exists( 'is_super_admin' ) && is_super_admin( $user->ID ) ) {
	//           $user_roles = [ 'administrator' ];
	//       }
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
	 * Mirrors the production branching including the multisite super-admin guard
	 * that was added in Task 5. Returns true when OTP should be required (the
	 * flow continues to the OTP challenge) and false when OTP is bypassed (the
	 * user object is returned directly).
	 *
	 * @param string[] $user_roles     Roles from the user object ($user->roles).
	 * @param string[] $required_roles Configured required roles (from settings).
	 * @param int      $user_id        User ID — passed to is_super_admin() guard.
	 * @param bool     $is_super_admin Whether is_super_admin() returns true for the user.
	 *
	 * @return bool True = OTP required, False = OTP bypassed.
	 */
	private function apply_role_check( $user_roles, array $required_roles, $user_id = 0, $is_super_admin = false ): bool {
		if ( ! empty( $required_roles ) ) {
			if ( empty( $user_roles ) && $is_super_admin ) {
				$user_roles = array( 'administrator' );
			}
			$intersect = array_intersect( $user_roles, $required_roles );
			if ( empty( $intersect ) ) {
				return false; // bypass OTP
			}
		}
		return true; // require OTP
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

		$otp_required = $this->apply_role_check( $user->roles, $required_roles );

		$this->assertFalse(
			$otp_required,
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

		$otp_required = $this->apply_role_check( $user->roles, $required_roles );

		$this->assertTrue(
			$otp_required,
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
			$user         = new WP_User( 1, $user_roles );
			$otp_required = $this->apply_role_check( $user->roles, $required_roles );

			$this->assertTrue(
				$otp_required,
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

		$otp_required = $this->apply_role_check( $user->roles, $required_roles );

		$this->assertTrue(
			$otp_required,
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

		$otp_required = $this->apply_role_check( $user->roles, $required_roles );

		$this->assertFalse(
			$otp_required,
			'A user with roles [subscriber, contributor] must bypass OTP when only "administrator" is required.'
		);
	}

	// =========================================================================
	// Task 5 quality-fix coverage (super-admin guard + passwordless bypass path)
	// =========================================================================

	/**
	 * Multisite super admin with an empty roles array must be treated as
	 * 'administrator' by the super-admin guard and therefore be required to
	 * pass OTP when 'administrator' is in otp_required_roles.
	 *
	 * Covers the guard block added in Task 5:
	 *   if ( empty( $user_roles ) && is_super_admin( $user->ID ) ) {
	 *       $user_roles = [ 'administrator' ];
	 *   }
	 */
	public function test_super_admin_with_empty_roles_treated_as_administrator() {
		$required_roles = array( 'administrator' );

		// Multisite super admins may have roles = [] on sub-sites.
		$user_id    = 42;
		$user_roles = array(); // empty — as seen on multisite sub-sites

		// is_super_admin() returns true for this user (simulated via $is_super_admin arg).
		$otp_required = $this->apply_role_check( $user_roles, $required_roles, $user_id, true );

		$this->assertTrue(
			$otp_required,
			'A super admin with empty roles must be treated as "administrator" and required to pass OTP ' .
			'when "administrator" is in otp_required_roles.'
		);
	}

	/**
	 * Passwordless flow: when a user's role is not in otp_required_roles the
	 * intersection is empty and the bypass path is taken (no OTP challenge).
	 *
	 * This test verifies the boolean logic that gates issue_auth_and_redirect()
	 * without calling that function (which calls wp_safe_redirect + exit).
	 *
	 * Covers the passwordless role check added in Task 5:
	 *   $intersect = array_intersect( $user_roles, $required_roles );
	 *   if ( empty( $intersect ) ) { issue_auth_and_redirect(); }
	 */
	public function test_passwordless_bypass_when_role_not_in_required_list() {
		$required_roles = array( 'administrator' );
		$user_roles     = array( 'subscriber' );

		// The bypass condition: intersection is empty → bypass OTP.
		$intersect = array_intersect( $user_roles, $required_roles );
		$this->assertEmpty(
			$intersect,
			'array_intersect(["subscriber"], ["administrator"]) must be empty — confirming bypass path is taken.'
		);

		// Confirm apply_role_check() returns false (OTP bypassed) for this combination.
		$otp_required = $this->apply_role_check( $user_roles, $required_roles );

		$this->assertFalse(
			$otp_required,
			'A subscriber must bypass OTP in the passwordless flow when only "administrator" is in otp_required_roles.'
		);
	}
}
