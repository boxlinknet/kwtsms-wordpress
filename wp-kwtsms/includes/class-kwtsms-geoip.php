<?php
/**
 * GeoIP Detection — detect the visitor's country from their IP address.
 *
 * Uses the ip-api.com free JSON endpoint (no API key required for non-commercial
 * use under 45 requests/minute). Results are cached in a transient for 24 hours
 * to minimise external API calls.
 *
 * Only the countryCode field is requested to keep the payload minimal.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_GeoIP
 */
class KwtSMS_GeoIP {

	/**
	 * Detect the ISO2 country code for the current visitor's IP.
	 *
	 * Returns null on failure (API error, timeout, private IP, cache miss).
	 * The result is cached for 24 hours per IP using a transient.
	 *
	 * @return string|null ISO2 country code (e.g. 'KW'), or null if unknown.
	 */
	public static function detect_iso2() {
		$ip = self::get_client_ip();

		if ( empty( $ip ) ) {
			return null;
		}

		// Cache key per IP.
		$cache_key = 'kwtsms_geoip_' . md5( $ip );
		$cached    = get_transient( $cache_key );

		// Transient returns false only on miss; empty string means cached failure.
		if ( false !== $cached ) {
			return '' !== $cached ? $cached : null;
		}

		// Call ip-api.com — only request the countryCode field.
		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode',
			array( 'timeout' => 3 )
		);

		if ( is_wp_error( $response ) ) {
			// Cache failure for 1 hour to avoid hammering the API on errors.
			set_transient( $cache_key, '', HOUR_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['countryCode'] ) ) {
			$iso2 = strtoupper( sanitize_text_field( $body['countryCode'] ) );
			// Cache the result for 24 hours.
			set_transient( $cache_key, $iso2, DAY_IN_SECONDS );
			return $iso2;
		}

		// Cache failure for 1 hour.
		set_transient( $cache_key, '', HOUR_IN_SECONDS );
		return null;
	}

	/**
	 * Get the real client IP, handling common proxy headers.
	 *
	 * NOTE: X-Forwarded-For and X-Real-IP headers can be spoofed. This is used
	 * only for GeoIP pre-selection (cosmetic UX) — not for security decisions.
	 *
	 * @return string Validated IP address, or empty string if none found.
	 */
	private static function get_client_ip() {
		// REMOTE_ADDR is always set and cannot be spoofed at the TCP level.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Try proxy headers only if REMOTE_ADDR is a known private/loopback range,
		// which typically means we're behind a trusted reverse proxy.
		$private_ranges = array( '127.', '10.', '172.', '192.168.' );
		$is_private     = false;
		foreach ( $private_ranges as $range ) {
			if ( 0 === strpos( $ip, $range ) ) {
				$is_private = true;
				break;
			}
		}

		if ( $is_private ) {
			foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					// X-Forwarded-For may be a comma-separated list; take the first.
					$forwarded = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
					if ( filter_var( $forwarded, FILTER_VALIDATE_IP ) ) {
						$ip = $forwarded;
						break;
					}
				}
			}
		}

		// Final validation.
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
