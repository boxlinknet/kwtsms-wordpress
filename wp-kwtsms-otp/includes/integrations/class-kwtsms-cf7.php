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
	 * field. If the CF7 integration is disabled in settings, no hook is
	 * registered and the class exits immediately.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		// Bail entirely if the CF7 integration is disabled.
		if ( ! $this->plugin->settings->get( 'integrations.cf7_enabled', 1 ) ) {
			return;
		}

		// Fire after CF7 validation succeeds and mail is sent.
		add_action( 'wpcf7_mail_sent', array( $this, 'send_confirmation_sms' ) );
	}

	/**
	 * Send a confirmation SMS after a CF7 form is successfully submitted.
	 *
	 * Looks for a field named `kwtsms_phone` in the submitted data. The message
	 * text is loaded from the saved `integrations.cf7_confirmation` template and
	 * supports both English and Arabic (selected via is_rtl()). If the template
	 * is disabled by the admin, no SMS is sent.
	 *
	 * @param WPCF7_ContactForm $cf7 The contact form instance.
	 */
	public function send_confirmation_sms( $cf7 ) {
		if ( ! is_a( $cf7, 'WPCF7_ContactForm' ) ) {
			return;
		}

		$phone = $this->get_submission_phone();
		if ( empty( $phone ) ) {
			return;
		}

		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return;
		}

		$message = $this->render_confirmation_template( sanitize_text_field( $cf7->title() ) );
		if ( empty( $message ) ) {
			return; // Template disabled or missing.
		}

		$this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'cf7'
		);
	}

	/**
	 * Retrieve the `kwtsms_phone` value from the current CF7 submission.
	 *
	 * Isolated into a protected method so that tests can override it without
	 * needing to stub the static WPCF7_Submission::get_instance() call, which
	 * cannot be intercepted by Brain\Monkey.
	 *
	 * @return string Raw phone string from posted data, or empty string if absent.
	 */
	protected function get_submission_phone() {
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return '';
		}
		$posted = $submission->get_posted_data();
		return isset( $posted['kwtsms_phone'] ) ? sanitize_text_field( $posted['kwtsms_phone'] ) : '';
	}

	/**
	 * Render the CF7 confirmation SMS from the saved template.
	 *
	 * Loads the `cf7_confirmation` template from integrations settings, checks
	 * the `enabled` sub-key, selects the correct language string (ar for RTL
	 * sites, en otherwise), and replaces {site_name} and {form_name} placeholders.
	 *
	 * @param string $form_title The CF7 form title.
	 *
	 * @return string Rendered SMS message, or empty string if disabled / missing.
	 */
	private function render_confirmation_template( $form_title ) {
		$templates = $this->plugin->settings->get_all_integration_templates();
		$template  = $templates['cf7_confirmation'] ?? array();

		if ( empty( $template['enabled'] ) ) {
			return '';
		}

		$lang    = ( function_exists( 'is_rtl' ) && is_rtl() ) ? 'ar' : 'en';
		$message = $template[ $lang ] ?? $template['en'] ?? '';

		if ( '' === $message ) {
			return '';
		}

		return str_replace(
			array( '{site_name}', '{form_name}' ),
			array( get_bloginfo( 'name' ), $form_title ),
			$message
		);
	}
}
