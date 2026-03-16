<?php
/**
 * Elementor Pro Forms Integration.
 *
 * Supports two modes, configurable per-site in the Integrations admin page:
 *
 *  - Notification mode (default): sends a confirmation SMS when an Elementor
 *    Pro form with a phone/tel field is submitted successfully.
 *
 *  - OTP Gate mode: blocks form processing until the visitor has verified their
 *    phone number via an OTP code. The JS layer (form-otp.js) adds a hidden
 *    input `kwtsms_form_verified_token`; this class verifies it server-side
 *    via the `elementor_pro/forms/validation` action.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_Elementor
 *
 * Hooks into Elementor Pro Forms events to:
 *  - (Notification mode) send a confirmation SMS via
 *    `elementor_pro/forms/new_record`.
 *  - (Gate mode) add a field-level validation error via
 *    `elementor_pro/forms/validation` when the OTP token is absent or unverified.
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
	 * Registers hooks based on the configured mode:
	 *  - If elementor_mode is 'gate', hooks `elementor_pro/forms/validation`
	 *    to block unverified submissions.
	 *  - Otherwise (notification mode), hooks `elementor_pro/forms/new_record`
	 *    to send a confirmation SMS after a successful submission.
	 *
	 * If the Elementor integration is disabled in settings, no hook is registered
	 * and the class exits immediately.
	 *
	 * @param KwtSMS_Plugin $plugin The main plugin instance.
	 */
	public function __construct( KwtSMS_Plugin $plugin ) {
		$this->plugin = $plugin;

		// Bail entirely if the Elementor integration is disabled.
		if ( ! $this->plugin->settings->get( 'integrations.elementor_enabled', 1 ) ) {
			return;
		}

		$mode = $this->plugin->settings->get( 'integrations.elementor_mode', 'notification' );

		if ( 'gate' === $mode ) {
			// Gate mode: validate token during Elementor's validation pass.
			add_action( 'elementor_pro/forms/validation', array( $this, 'gate_add_error' ), 10, 2 );
		} else {
			// Notification mode: fires after a successful Elementor form submission.
			add_action( 'elementor_pro/forms/new_record', array( $this, 'send_confirmation_sms' ), 10, 2 );
		}
	}

	// =========================================================================
	// Gate mode
	// =========================================================================

	/**
	 * Gate mode — add a validation error to the phone field if unverified.
	 *
	 * Hooked to `elementor_pro/forms/validation`. Elementor passes the record
	 * and the AJAX handler by reference; errors are added via
	 * `$ajax_handler->add_error( $field_id, $message )`.
	 *
	 * We target the first phone-like field in the form, or fall back to a
	 * generic form-level error when no phone field is present.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record  Submitted form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler The form AJAX handler.
	 */
	public function gate_add_error( $record, $handler ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Elementor handles nonce; we only read our own token.
		$token = sanitize_text_field( wp_unslash( $_POST['kwtsms_form_verified_token'] ?? '' ) );

		if ( ! empty( $token ) && $this->plugin->verify_form_token( $token ) ) {
			// Token is verified — consume it immediately (single-use) to prevent replay.
			// Note: form_id in the transient is stored for audit purposes.
			// Token is single-use (consumed on success) so cross-form replay is prevented.
			delete_transient( 'kwtsms_form_otp_' . $token );
			return;
		}

		// Find the first phone-like field to attach the error to.
		$fields         = $record->get( 'fields' );
		$phone_field_id = null;

		foreach ( $fields as $id => $field ) {
			$type  = strtolower( $field['type'] ?? '' );
			$title = strtolower( $field['title'] ?? '' );
			if ( 'tel' === $type || 'phone' === $type || false !== strpos( $title, 'phone' ) ) {
				$phone_field_id = $id;
				break;
			}
		}

		$error_msg = __( 'Please verify your phone number before submitting this form.', 'kwtsms' );

		if ( null !== $phone_field_id ) {
			$handler->add_error( $phone_field_id, $error_msg );
		} else {
			// No phone field found — add a generic form-level error.
			$handler->add_error( 'form', $error_msg );
		}
	}

	// =========================================================================
	// Notification mode
	// =========================================================================

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
	public function send_confirmation_sms( $record, $handler ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$fields = $record->get( 'fields' );
		$phone  = $this->extract_phone( $fields );

		if ( empty( $phone ) ) {
			return;
		}

		$phone      = KwtSMS_API::prepend_country_code_if_local( $phone, KwtSMS_API::get_default_dial_code() );
		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return;
		}

		$form_name = sanitize_text_field( $record->get_form_settings( 'form_name' ) ?? '' );

		$message = $this->render_confirmation_template( $form_name ? $form_name : __( 'Contact Form', 'kwtsms' ) );
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

	// =========================================================================
	// Helpers
	// =========================================================================

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
