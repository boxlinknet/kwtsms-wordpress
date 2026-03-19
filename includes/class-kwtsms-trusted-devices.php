<?php
/**
 * Trusted Devices manager for kwtSMS 2FA.
 *
 * Stores sha256(token) in wp_usermeta. Raw token lives only in the
 * browser cookie. Max 10 devices per user; oldest evicted on overflow.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Trusted_Devices
 *
 * Manages "Trust this device" tokens for 2FA skip. All token logic is
 * encapsulated here, kept intentionally separate from the login flow so
 * each concern is independently testable and auditable.
 *
 * Security properties:
 * - Raw token is 32 random bytes (256-bit entropy) encoded as 64 hex chars.
 * - Only sha256(token) is persisted; raw token never leaves the cookie.
 * - All comparisons use hash_equals() to prevent timing attacks.
 * - Cookie flags: httponly, SameSite=Strict; secure when site is HTTPS.
 * - Device list is capped at 10; oldest device is evicted on overflow.
 * - All trusted devices are revoked on password reset.
 * - TTL is 30 days, checked on both cookie expiry and server-side last_seen.
 */
class KwtSMS_Trusted_Devices {

	/**
	 * User meta key for the trusted devices array.
	 *
	 * @var string
	 */
	const META_KEY = 'kwtsms_trusted_devices';

	/**
	 * Cookie name prefix. Full name is this prefix + user_id.
	 *
	 * @var string
	 */
	const COOKIE_PREFIX = 'kwtsms_trusted_device_';

	/**
	 * Maximum number of trusted devices per user.
	 *
	 * @var int
	 */
	const MAX_DEVICES = 10;

	/**
	 * Trust period in days.
	 *
	 * @var int
	 */
	const TTL_DAYS = 30;

	/**
	 * Number of random bytes for the token (32 bytes = 64 hex chars).
	 *
	 * @var int
	 */
	const TOKEN_BYTES = 32;

	// =========================================================================
	// Token lifecycle
	// =========================================================================

	/**
	 * Issue a new trusted device token for a user.
	 *
	 * Generates a cryptographically secure 32-byte token, stores sha256(token)
	 * in usermeta, and returns the raw token (for the browser cookie).
	 *
	 * If MAX_DEVICES devices are already stored for the user, the oldest device
	 * (by `created` timestamp) is removed before the new one is added.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Raw token (64-char hex string).
	 */
	public function issue_token( int $user_id ): string {
		$token      = bin2hex( random_bytes( self::TOKEN_BYTES ) );
		$token_hash = hash( 'sha256', $token );
		$ua         = substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 120 );

		$devices = $this->get_devices( $user_id );

		// Enforce max device limit: remove oldest by created time.
		if ( count( $devices ) >= self::MAX_DEVICES ) {
			usort( $devices, fn( $a, $b ) => $a['created'] <=> $b['created'] );
			$devices = array_slice( $devices, 1 );
		}

		$devices[] = array(
			'token_hash' => $token_hash,
			'created'    => time(),
			'last_seen'  => time(),
			'ua'         => $ua,
		);

		update_user_meta( $user_id, self::META_KEY, $devices );

		return $token;
	}

	/**
	 * Rotate a trusted device token: revoke the old one, issue a new one.
	 *
	 * Called after every successful OTP so tokens are not reused indefinitely.
	 * The old token is revoked first; if that fails silently, a new token is
	 * still issued (the old one will simply expire via cookie TTL).
	 *
	 * @param int    $user_id   WordPress user ID.
	 * @param string $old_token Raw old token (from cookie).
	 * @return string New raw token.
	 */
	public function rotate_token( int $user_id, string $old_token ): string {
		$this->revoke( $user_id, $old_token );
		return $this->issue_token( $user_id );
	}

	// =========================================================================
	// Verification
	// =========================================================================

	/**
	 * Check if the current request comes from a trusted device for a given user.
	 *
	 * Reads the cookie, looks up the sha256 hash in usermeta, and enforces the
	 * 30-day TTL based on `last_seen`. If an expired device is found, it is
	 * automatically revoked.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if the device is trusted and within TTL.
	 */
	public function is_trusted( int $user_id ): bool {
		$token = $this->get_cookie_token( $user_id );
		if ( '' === $token ) {
			return false;
		}

		$device = $this->find_device( $user_id, $token );
		if ( null === $device ) {
			return false;
		}

		// Enforce server-side TTL (cookie may outlive any usermeta cleanup).
		$cutoff = time() - ( self::TTL_DAYS * DAY_IN_SECONDS );
		if ( $device['last_seen'] < $cutoff ) {
			$this->revoke( $user_id, $token );
			return false;
		}

		return true;
	}

	// =========================================================================
	// Mutation
	// =========================================================================

	/**
	 * Update the last_seen timestamp for a specific device.
	 *
	 * Called after a trusted-device bypass to keep the session alive.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   Raw token (from cookie).
	 */
	public function update_last_seen( int $user_id, string $token ): void {
		$devices = $this->get_devices( $user_id );
		$hash    = hash( 'sha256', $token );
		foreach ( $devices as &$device ) {
			if ( hash_equals( $device['token_hash'], $hash ) ) {
				$device['last_seen'] = time();
				break;
			}
		}
		unset( $device );
		update_user_meta( $user_id, self::META_KEY, $devices );
	}

	/**
	 * Revoke a single trusted device by raw token.
	 *
	 * Removes the device whose stored hash matches sha256($token).
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   Raw token (from cookie).
	 */
	public function revoke( int $user_id, string $token ): void {
		$hash    = hash( 'sha256', $token );
		$devices = $this->get_devices( $user_id );
		$devices = array_values(
			array_filter(
				$devices,
				fn( $d ) => ! hash_equals( $d['token_hash'], $hash )
			)
		);
		update_user_meta( $user_id, self::META_KEY, $devices );
	}

	/**
	 * Revoke a single trusted device by stored token hash.
	 *
	 * Used in the admin profile UI where the raw token is not available.
	 * Compares hashes using hash_equals() to avoid timing side-channels.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $token_hash sha256 hash of the token (as stored in meta).
	 */
	public function revoke_by_hash( int $user_id, string $token_hash ): void {
		$devices = $this->get_devices( $user_id );
		$devices = array_values(
			array_filter(
				$devices,
				fn( $d ) => ! hash_equals( $d['token_hash'], $token_hash )
			)
		);
		update_user_meta( $user_id, self::META_KEY, $devices );
	}

	/**
	 * Revoke all trusted devices for a user.
	 *
	 * Called on password reset and when the user or admin clicks "Revoke all".
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public function revoke_all( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	// =========================================================================
	// Cookie helpers
	// =========================================================================

	/**
	 * Set the trusted device cookie for a user.
	 *
	 * Cookie flags: httponly=true, secure=(site is SSL), SameSite=Strict,
	 * expiry = now + 30 days, path = COOKIEPATH, domain = COOKIE_DOMAIN.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   Raw token to store in the cookie.
	 */
	public function set_cookie( int $user_id, string $token ): void {
		$name    = self::COOKIE_PREFIX . $user_id;
		$expiry  = time() + ( self::TTL_DAYS * DAY_IN_SECONDS );
		$options = array(
			'expires'  => $expiry,
			'path'     => COOKIEPATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		);
		setcookie( $name, $token, $options );
		// Make cookie available in the current request so is_trusted() can read it
		// if called again in the same page load.
		$_COOKIE[ $name ] = $token;
	}

	/**
	 * Clear the trusted device cookie for a user.
	 *
	 * Overwrites with an expired cookie to instruct the browser to delete it.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public function clear_cookie( int $user_id ): void {
		$name = self::COOKIE_PREFIX . $user_id;
		setcookie(
			$name,
			'',
			array(
				'expires'  => time() - YEAR_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
		unset( $_COOKIE[ $name ] );
	}

	// =========================================================================
	// Data accessors
	// =========================================================================

	/**
	 * Get all trusted devices for a user.
	 *
	 * Returns an array of device entries. Each entry has keys:
	 * - token_hash (string): sha256 hash of the raw token.
	 * - created (int): Unix timestamp when this device was trusted.
	 * - last_seen (int): Unix timestamp of last successful bypass.
	 * - ua (string): Partial user-agent string (first 120 chars).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array{token_hash: string, created: int, last_seen: int, ua: string}>
	 */
	public function get_devices( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Get the raw token value from the trusted device cookie for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Raw token or empty string if cookie is not set.
	 */
	public function get_cookie_token( int $user_id ): string {
		$name = self::COOKIE_PREFIX . $user_id;
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return '';
		}
		$token = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) {
			return '';
		}
		return $token;
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Find a device entry by raw token.
	 *
	 * Iterates over stored devices and compares sha256(token) using hash_equals()
	 * to prevent timing side-channel attacks.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   Raw token.
	 * @return array|null Device array if found, null otherwise.
	 */
	private function find_device( int $user_id, string $token ): ?array {
		$hash    = hash( 'sha256', $token );
		$devices = $this->get_devices( $user_id );
		foreach ( $devices as $device ) {
			if ( hash_equals( $device['token_hash'], $hash ) ) {
				return $device;
			}
		}
		return null;
	}
}
