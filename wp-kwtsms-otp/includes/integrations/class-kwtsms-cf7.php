<?php
/**
 * Contact Form 7 Integration.
 *
 * Sends a confirmation SMS when a CF7 form is submitted with a phone number.
 * Use the [tel kwtsms_phone] tag in your CF7 form to capture the phone.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_CF7
 *
 * Hooks into the wpcf7_mail_sent action, which fires after Contact Form 7
 * has validated the submission and sent the configured mail(s). This ensures
 * we only send the confirmation SMS for genuinely successful submissions.
 *
 * To enable the SMS on any form, add a tel field named `kwtsms_phone`:
 *   [tel kwtsms_phone]          (optional phone)
 *   [tel* kwtsms_phone]         (required phone)
 */
class KwtSMS_CF7 {

	/**
	 * Reference to the main plugin instance.
	 *
	 * @var KwtSMS_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * Registers the wpcf7_mail_sent hook so we can send a confirmation SMS
	 * after every successful CF7 form submission that contains a kwtsms_phone
	 * field.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;
		// Fire after CF7 validation succeeds and mail is sent.
		add_action( 'wpcf7_mail_sent', array( $this, 'send_confirmation_sms' ) );
	}

	/**
	 * Send a confirmation SMS after a CF7 form is successfully submitted.
	 *
	 * Looks for a field named `kwtsms_phone` in the submitted data.
	 *
	 * @param WPCF7_ContactForm $cf7 The contact form instance.
	 */
	public function send_confirmation_sms( $cf7 ) {
		if ( ! is_a( $cf7, 'WPCF7_ContactForm' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		$phone  = isset( $posted['kwtsms_phone'] ) ? sanitize_text_field( $posted['kwtsms_phone'] ) : '';

		if ( empty( $phone ) ) {
			return;
		}

		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return;
		}

		$site_name  = get_bloginfo( 'name' );
		$form_title = sanitize_text_field( $cf7->title() );

		$message = sprintf(
			/* translators: 1: site name, 2: form name */
			__( '%1$s: Your form "%2$s" has been submitted successfully. Thank you!', 'wp-kwtsms-otp' ),
			$site_name,
			$form_title
		);

		$this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'cf7'
		);
	}
}
