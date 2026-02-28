<?php
/**
 * Elementor Pro Forms Integration.
 *
 * Sends a confirmation SMS when an Elementor Pro form with a phone field
 * is submitted successfully.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Elementor
 *
 * Hooks into the elementor_pro/forms/new_record action, which fires after
 * Elementor Pro has validated and processed a form submission. We scan the
 * submitted fields for a telephone / phone field and send a confirmation SMS.
 *
 * Phone detection strategy:
 *  1. Any field whose Elementor type is 'tel'.
 *  2. Any field whose Elementor type is 'phone'.
 *  3. Any field whose title (label) contains the word 'phone'.
 */
class KwtSMS_Elementor {

	/**
	 * Reference to the main plugin instance.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * Registers the elementor_pro/forms/new_record hook so we can send a
	 * confirmation SMS after every successful Elementor Pro form submission
	 * that contains a phone / tel field.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;
		// elementor_pro/forms/new_record fires after a successful Elementor form submission.
		add_action( 'elementor_pro/forms/new_record', array( $this, 'send_confirmation_sms' ), 10, 2 );
	}

	/**
	 * Send a confirmation SMS after an Elementor Pro form submission.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record  Submitted form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler The form AJAX handler.
	 */
	public function send_confirmation_sms( $record, $handler ) {
		$fields = $record->get( 'fields' );
		$phone  = $this->extract_phone( $fields );

		if ( empty( $phone ) ) {
			return;
		}

		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$form_name = sanitize_text_field( $record->get_form_settings( 'form_name' ) ?? '' );

		$message = sprintf(
			/* translators: 1: site name, 2: form name */
			__( '%1$s: Your form "%2$s" has been received. Thank you!', 'wp-kwtsms-otp' ),
			$site_name,
			$form_name ?: 'Contact Form'
		);

		$this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'elementor'
		);
	}

	/**
	 * Extract a phone number from Elementor form fields.
	 *
	 * Scans the fields array (keyed by field ID) for a field whose type is
	 * 'tel' or 'phone', or whose title contains the word 'phone'.
	 *
	 * @param array $fields Fields array from $record->get('fields').
	 * @return string Phone number string, or empty string if not found.
	 */
	private function extract_phone( array $fields ) {
		foreach ( $fields as $id => $field ) {
			$type  = strtolower( $field['type'] ?? '' );
			$title = strtolower( $field['title'] ?? '' );
			if ( 'tel' === $type || 'phone' === $type || false !== strpos( $title, 'phone' ) ) {
				return sanitize_text_field( $field['value'] ?? '' );
			}
		}
		return '';
	}
}
