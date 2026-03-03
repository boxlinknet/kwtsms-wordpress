<?php
/**
 * WPForms Integration.
 *
 * Supports two modes, configurable per-site in the Integrations admin page:
 *
 *  - Notification mode (default): sends a confirmation SMS when a WPForms
 *    form is submitted successfully. Looks for a Phone field in the form data.
 *
 *  - OTP Gate mode: blocks form processing until the visitor has verified their
 *    phone number via an OTP code. The JS layer (form-otp.js) adds a hidden
 *    input `kwtsms_form_verified_token`; this class verifies it server-side
 *    via the `wpforms_process_initial_errors` filter.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_WPForms
 *
 * Hooks into WPForms events to:
 *  - (Notification mode) send a confirmation SMS via `wpforms_process_complete`.
 *  - (Gate mode) inject a validation error via `wpforms_process_initial_errors`
 *    when the OTP token is absent or unverified.
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
	 * Registers hooks based on the configured mode:
	 *  - If wpforms_mode is 'gate', hooks `wpforms_process_initial_errors` to
	 *    block unverified submissions early in the validation pipeline.
	 *  - Otherwise (notification mode), hooks `wpforms_process_complete` to
	 *    send a confirmation SMS after a successful submission.
	 *
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

		$mode = $this->plugin->settings->get( 'integrations.wpforms_mode', 'notification' );

		if ( 'gate' === $mode ) {
			// Gate mode: inject a form-level error early in WPForms processing.
			add_filter( 'wpforms_process_initial_errors', array( $this, 'gate_add_error' ), 10, 2 );
		} else {
			// Notification mode: wpforms_process_complete fires after successful submission.
			add_action( 'wpforms_process_complete', array( $this, 'send_confirmation_sms' ), 10, 4 );
		}
	}

	// =========================================================================
	// Gate mode
	// =========================================================================

	/**
	 * Gate mode — add a validation error if the OTP token is absent or unverified.
	 *
	 * Hooked to `wpforms_process_initial_errors`. The filter receives and must
	 * return the errors array (keyed by field ID, value is error string). We
	 * use the special key 'header' to show a form-level (non-field) error.
	 *
	 * @param array $errors    Existing errors array (field_id => message).
	 * @param array $form_data WPForms form configuration array.
	 *
	 * @return array Possibly augmented errors array.
	 */
	public function gate_add_error( $errors, $form_data ) {
		$token = sanitize_text_field( wp_unslash( $_POST['kwtsms_form_verified_token'] ?? '' ) );

		if ( empty( $token ) || ! $this->plugin->verify_form_token( $token ) ) {
			$form_id                      = absint( $form_data['id'] ?? 0 );
			$errors[ $form_id ]['header'] = __( 'Please verify your phone number before submitting this form.', 'wp-kwtsms' );
			return $errors;
		}

		// Token is verified — consume it immediately (single-use) to prevent replay.
		// Note: form_id in the transient is stored for audit purposes.
		// Token is single-use (consumed on success) so cross-form replay is prevented.
		delete_transient( 'kwtsms_form_otp_' . $token );

		return $errors;
	}

	// =========================================================================
	// Notification mode
	// =========================================================================

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

		$phone      = KwtSMS_API::prepend_country_code_if_local( $phone, KwtSMS_API::get_default_dial_code() );
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

	// =========================================================================
	// Helpers
	// =========================================================================

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
