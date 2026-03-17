<?php
/**
 * Uninstall routine for kwtSMS: OTP & SMS Notifications.
 *
 * WordPress only runs this file when the plugin is deleted (not just deactivated).
 * The WP_UNINSTALL_PLUGIN check is required — it prevents direct execution.
 *
 * Data removed:
 * - All kwtsms_otp_* options from wp_options
 * - All kwtsms_phone user meta from wp_usermeta
 * - All kwtsms_otp_* and kwtsms_partial_auth_* transients
 *
 * @package KwtSMS_OTP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// -------------------------------------------------------------------------
// 1. Remove all plugin options.
// -------------------------------------------------------------------------
$kwtsms_options = array(
	'kwtsms_otp_general',
	'kwtsms_otp_gateway',
	'kwtsms_otp_templates',
	'kwtsms_otp_version',
	'kwtsms_otp_send_log',
	'kwtsms_otp_sms_history',
	'kwtsms_otp_attempt_log',
);

foreach ( $kwtsms_options as $kwtsms_option ) {
	delete_option( $kwtsms_option );
	// Also remove from multisite if applicable.
	delete_site_option( $kwtsms_option );
}

// -------------------------------------------------------------------------
// 2. Remove user phone meta.
// -------------------------------------------------------------------------
delete_metadata( 'user', 0, 'kwtsms_phone', '', true );
delete_metadata( 'user', 0, 'kwtsms_dismissed_version', '', true );

// -------------------------------------------------------------------------
// 3. Remove transients (OTPs, partial auths, rate limiters).
// Transients are stored as _transient_* and _transient_timeout_* in options.
// -------------------------------------------------------------------------
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_kwtsms\_%'
	    OR option_name LIKE '\_transient\_timeout\_kwtsms\_%'"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery
