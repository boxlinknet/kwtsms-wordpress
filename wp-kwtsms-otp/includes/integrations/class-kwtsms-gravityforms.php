<?php
/**
 * Gravity Forms Integration.
 *
 * Supports two modes, configurable per-site in the Integrations admin page:
 *
 *  - Notification mode (default): sends a confirmation SMS after a successful
 *    Gravity Forms submission. Looks for a field of type 'phone' or a field
 *    whose label contains the word "phone".
 *
 *  - OTP Gate mode: blocks the form submission (marks it invalid) until the
 *    visitor has verified their phone number via an OTP code. The JS layer
 *    (form-otp.js) adds a hidden input `kwtsms_form_verified_token`; this
 *    class verifies it server-side using the `gform_validation` filter.
 *
 * Auto-detected when `GFForms` class exists (Gravity Forms 2.x+).
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_GravityForms
 *
 * Hooks into Gravity Forms events to:
 *  - (Notification mode) send a confirmation SMS via `gform_after_submission`.
 *  - (Gate mode) block the submission via `gform_validation` when the OTP
 *    token is absent or unverified.
 *
 * Phone detection strategy:
 *  1. Any GF field whose type is 'phone'.
 *  2. Any GF field whose label contains the word 'phone' (case-insensitive).
 */
class KwtSMS_GravityForms {

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
	 *  - If gf_mode is 'gate', hooks `gform_validation` to block unverified
	 *    submissions.
	 *  - Otherwise (notification mode), hooks `gform_after_submission` to send
	 *    a confirmation SMS after a successful submission.
	 *
	 * If GForms is not installed or the integration is disabled in settings,
	 * no hook is registered and the class exits immediately.
	 *
	 * @param KwtSMS_Plugin   $plugin   The main plugin instance.
	 * @param KwtSMS_Settings $settings The settings instance (unused directly —
	 *                                  accessed via $plugin->settings for
	 *                                  consistency with other integrations, but
	 *                                  also accepted as a dependency for tests).
	 */
	public function __construct( KwtSMS_Plugin $plugin, KwtSMS_Settings $settings = null ) {
		$this->plugin = $plugin;

		// Gravity Forms must be active.
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		// Bail entirely if the GF integration is disabled.
		if ( ! $this->plugin->settings->get( 'integrations.gf_enabled', 1 ) ) {
			return;
		}

		$mode = $this->plugin->settings->get( 'integrations.gf_mode', 'notification' );

		if ( 'gate' === $mode ) {
			// Gate mode: gform_validation is a filter that receives a $validation_result
			// array. Setting ['is_valid'] = false blocks the submission.
			add_filter( 'gform_validation', array( $this, 'gate_validate' ) );
		} else {
			// Notification mode: gform_after_submission fires after the entry is saved.
			// Args: $entry (array), $form (array).
			add_action( 'gform_after_submission', array( $this, 'send_notification' ), 10, 2 );
		}
	}

	// =========================================================================
	// Gate mode
	// =========================================================================

	/**
	 * Gate mode — verify the OTP token before Gravity Forms processes the entry.
	 *
	 * Hooked to `gform_validation` (filter). GF passes a `$validation_result`
	 * array that contains 'is_valid' (bool), 'form' (array), and optionally
	 * 'failed_validation_page'. Setting `['is_valid'] = false` blocks the
	 * submission and GF re-renders the form with the field error(s) attached.
	 *
	 * To surface a field-level error we find the phone field in the form
	 * definition and attach the error message via GFFormsModel or by
	 * annotating the field objects. Because GF's field validation API requires
	 * mutating field objects inside the form array, we annotate the first phone
	 * field with `failed_validation` and `validation_message`.
	 *
	 * @param array $validation_result {
	 *     @type bool  $is_valid Whether the form passed validation.
	 *     @type array $form     The form object (array representation).
	 * }
	 *
	 * @return array The (possibly mutated) validation result array.
	 */
	public function gate_validate( $validation_result ) {
		$token = sanitize_text_field( wp_unslash( $_POST['kwtsms_form_verified_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $token ) || ! $this->plugin->verify_form_token( $token ) ) {
			$validation_result['is_valid'] = false;

			// Mark the first phone field in the form with a validation error so
			// GF displays a user-facing message next to the field.
			$form = $validation_result['form'] ?? array();
			if ( ! empty( $form['fields'] ) ) {
				foreach ( $form['fields'] as &$field ) {
					if ( $this->is_phone_field( $field ) ) {
						$field->failed_validation  = true;
						$field->validation_message = __( 'Please verify your phone number before submitting this form.', 'wp-kwtsms-otp' );
						break;
					}
				}
				unset( $field );
				$validation_result['form'] = $form;
			}

			return $validation_result;
		}

		// Token is verified — consume it immediately (single-use) to prevent replay.
		delete_transient( 'kwtsms_form_otp_' . $token );

		return $validation_result;
	}

	// =========================================================================
	// Notification mode
	// =========================================================================

	/**
	 * Send a confirmation SMS after a Gravity Forms entry is saved.
	 *
	 * Looks for the first phone field in the submitted entry. The message text
	 * is loaded from the saved `integrations.gf_confirmation` template and
	 * supports both English and Arabic (selected via is_rtl()). If the template
	 * is disabled by the admin, no SMS is sent.
	 *
	 * @param array $entry The GF entry array (field IDs as keys, submitted values as values).
	 * @param array $form  The GF form configuration array.
	 */
	public function send_notification( $entry, $form ) {
		$phone = $this->extract_phone_from_entry( $entry, $form );
		if ( empty( $phone ) ) {
			return;
		}

		$phone      = KwtSMS_API::prepend_country_code_if_local( $phone, KwtSMS_API::get_default_dial_code() );
		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return;
		}

		$form_title = sanitize_text_field( $form['title'] ?? '' );

		$message = $this->render_confirmation_template( $form_title, $phone );
		if ( empty( $message ) ) {
			return; // Template disabled or missing.
		}

		$this->plugin->api->send_sms(
			$normalized,
			$this->plugin->settings->get( 'gateway.sender_id', '' ),
			$message,
			'gravityforms'
		);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Extract the first phone number value from a GF entry.
	 *
	 * GF entries store field values with the field ID as the array key.
	 * We iterate the form fields to identify phone-type or phone-labelled fields
	 * and return the corresponding value from the entry array.
	 *
	 * @param array $entry The GF entry array.
	 * @param array $form  The GF form definition array.
	 *
	 * @return string Raw phone string, or empty string if not found.
	 */
	private function extract_phone_from_entry( array $entry, array $form ) {
		if ( empty( $form['fields'] ) ) {
			return '';
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! $this->is_phone_field( $field ) ) {
				continue;
			}
			$field_id = (int) ( is_object( $field ) ? $field->id : ( $field['id'] ?? 0 ) );
			if ( $field_id && isset( $entry[ $field_id ] ) ) {
				return sanitize_text_field( $entry[ $field_id ] );
			}
		}

		return '';
	}

	/**
	 * Determine whether a GF field is a phone-type or phone-labelled field.
	 *
	 * Accepts both object and array representations of a GF field.
	 *
	 * @param object|array $field GF field object or array.
	 *
	 * @return bool
	 */
	private function is_phone_field( $field ) {
		$type  = strtolower( is_object( $field ) ? ( $field->type ?? '' ) : ( $field['type'] ?? '' ) );
		$label = strtolower( is_object( $field ) ? ( $field->label ?? '' ) : ( $field['label'] ?? '' ) );

		return 'phone' === $type || false !== strpos( $label, 'phone' );
	}

	/**
	 * Render the Gravity Forms confirmation SMS from the saved template.
	 *
	 * Loads the `gf_confirmation` template from integrations settings, checks
	 * the `enabled` sub-key, selects the correct language string (ar for RTL
	 * sites, en otherwise), and replaces {form_name} and {phone} placeholders.
	 *
	 * @param string $form_title  The GF form title.
	 * @param string $phone_value The raw phone value from the entry.
	 *
	 * @return string Rendered SMS message, or empty string if disabled / missing.
	 */
	private function render_confirmation_template( $form_title, $phone_value ) {
		$templates = $this->plugin->settings->get_all_integration_templates();
		$template  = $templates['gf_confirmation'] ?? array();

		if ( empty( $template['enabled'] ) ) {
			return '';
		}

		$lang    = ( function_exists( 'is_rtl' ) && is_rtl() ) ? 'ar' : 'en';
		$message = $template[ $lang ] ?? $template['en'] ?? '';

		if ( '' === $message ) {
			return '';
		}

		return str_replace(
			array( '{site_name}', '{form_name}', '{phone}' ),
			array( get_bloginfo( 'name' ), $form_title, $phone_value ),
			$message
		);
	}
}
