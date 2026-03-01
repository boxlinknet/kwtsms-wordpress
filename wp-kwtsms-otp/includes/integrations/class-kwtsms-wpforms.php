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
	 * If the WPForms integration is disabled in settings, no hook is registered
	 * and the class exits immediately.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		// Bail entirely if the WPForms integration is disabled.
		if ( ! $this->plugin->settings->get( 'integrations.wpforms_enabled', 1 ) ) {
			return;
		}

		// wpforms_process_complete fires after successful submission.
		add_action( 'wpforms_process_complete', array( $this, 'send_confirmation_sms' ), 10, 4 );
	}

	/**
	 * Send confirmation SMS after a WPForms submission.
	 *
	 * The message text is loaded from the saved `integrations.wpforms_confirmation`
	 * template and supports both English and Arabic (selected via is_rtl()). If
	 * the template is disabled by the admin, no SMS is sent.
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

		$form_title = sanitize_text_field( $form_data['settings']['form_title'] ?? '' );

		$message = $this->render_confirmation_template( $form_title );
		if ( empty( $message ) ) {
			return; // Template disabled or missing.
		}

		$this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'wpforms'
		);
	}

	/**
	 * Render the WPForms confirmation SMS from the saved template.
	 *
	 * Loads the `wpforms_confirmation` template from integrations settings, checks
	 * the `enabled` sub-key, selects the correct language string (ar for RTL
	 * sites, en otherwise), and replaces {site_name} and {form_name} placeholders.
	 *
	 * @param string $form_title The WPForms form title.
	 *
	 * @return string Rendered SMS message, or empty string if disabled / missing.
	 */
	private function render_confirmation_template( $form_title ) {
		$templates = $this->plugin->settings->get_all_integration_templates();
		$template  = $templates['wpforms_confirmation'] ?? array();

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
