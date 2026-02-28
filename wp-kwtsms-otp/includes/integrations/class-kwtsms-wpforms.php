<?php
/**
 * WPForms Integration.
 *
 * Sends a confirmation SMS when a WPForms form is submitted.
 * Looks for a Phone field in the form data.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_WPForms
 *
 * Hooks into the wpforms_process_complete action, which fires after WPForms
 * has validated and saved the submission. This guarantees we only send an SMS
 * for genuinely successful entries.
 *
 * Phone detection strategy:
 *  1. Any field whose WPForms type is 'phone'.
 *  2. Any field whose label (name) contains the word 'phone'.
 */
class KwtSMS_WPForms {

	/**
	 * Reference to the main plugin instance.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * Registers the wpforms_process_complete hook so we can send a confirmation
	 * SMS after every successful WPForms submission that contains a phone field.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;
		// wpforms_process_complete fires after successful submission.
		add_action( 'wpforms_process_complete', array( $this, 'send_confirmation_sms' ), 10, 4 );
	}

	/**
	 * Send confirmation SMS after a WPForms submission.
	 *
	 * @param array $fields    Processed form fields (keyed by field ID).
	 * @param array $entry     Raw entry data as submitted.
	 * @param array $form_data Form settings array from WPForms.
	 * @param int   $entry_id  Saved entry ID (0 if entry storage is disabled).
	 */
	public function send_confirmation_sms( $fields, $entry, $form_data, $entry_id ) {
		$phone = $this->extract_phone_from_fields( $fields );
		if ( empty( $phone ) ) {
			return;
		}

		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return;
		}

		$site_name  = get_bloginfo( 'name' );
		$form_title = sanitize_text_field( $form_data['settings']['form_title'] ?? '' );

		$message = sprintf(
			/* translators: 1: site name, 2: form name */
			__( '%1$s: Your form "%2$s" was received. Thank you!', 'wp-kwtsms-otp' ),
			$site_name,
			$form_title
		);

		$this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'wpforms'
		);
	}

	/**
	 * Extract a phone number from WPForms processed fields.
	 *
	 * Iterates the processed fields array and returns the value of the first
	 * field whose type is 'phone' or whose label contains the word 'phone'.
	 *
	 * @param array $fields Processed fields from wpforms_process_complete.
	 * @return string Phone number string, or empty string if not found.
	 */
	private function extract_phone_from_fields( array $fields ) {
		foreach ( $fields as $field ) {
			$type  = strtolower( $field['type'] ?? '' );
			$label = strtolower( $field['name'] ?? '' );
			if ( 'phone' === $type || false !== strpos( $label, 'phone' ) ) {
				return sanitize_text_field( $field['value'] ?? '' );
			}
		}
		return '';
	}
}
