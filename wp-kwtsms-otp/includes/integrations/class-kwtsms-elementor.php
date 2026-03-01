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
	 * that contains a phone / tel field. If the Elementor integration is
	 * disabled in settings, no hook is registered and the class exits immediately.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		// Bail entirely if the Elementor integration is disabled.
		if ( ! $this->plugin->settings->get( 'integrations.elementor_enabled', 1 ) ) {
			return;
		}

		// elementor_pro/forms/new_record fires after a successful Elementor form submission.
		add_action( 'elementor_pro/forms/new_record', array( $this, 'send_confirmation_sms' ), 10, 2 );
	}

	/**
	 * Send a confirmation SMS after an Elementor Pro form submission.
	 *
	 * The message text is loaded from the saved `integrations.elementor_confirmation`
	 * template and supports both English and Arabic (selected via is_rtl()). If
	 * the template is disabled by the admin, no SMS is sent.
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

		$form_name = sanitize_text_field( $record->get_form_settings( 'form_name' ) ?? '' );

		$message = $this->render_confirmation_template( $form_name ?: 'Contact Form' );
		if ( empty( $message ) ) {
			return; // Template disabled or missing.
		}

		$this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'elementor'
		);
	}

	/**
	 * Render the Elementor confirmation SMS from the saved template.
	 *
	 * Loads the `elementor_confirmation` template from integrations settings, checks
	 * the `enabled` sub-key, selects the correct language string (ar for RTL
	 * sites, en otherwise), and replaces {site_name} and {form_name} placeholders.
	 *
	 * @param string $form_name The Elementor form name.
	 *
	 * @return string Rendered SMS message, or empty string if disabled / missing.
	 */
	private function render_confirmation_template( $form_name ) {
		$templates = $this->plugin->settings->get_all_integration_templates();
		$template  = $templates['elementor_confirmation'] ?? array();

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
			array( get_bloginfo( 'name' ), $form_name ),
			$message
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
