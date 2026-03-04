<?php
/**
 * User Phone Number Meta Field.
 *
 * Adds a phone number field to WordPress user profiles for OTP delivery.
 * Phone numbers are normalised to international format (digits only) on save.
 * Nonce + capability checks guard every save operation.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KwtSMS_User_Meta
 */
class KwtSMS_User_Meta {

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'render_phone_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_phone_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_phone_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_phone_field' ) );

		add_action( 'register_form',       array( $this, 'render_registration_phone_field' ) );
		add_filter( 'registration_errors', array( $this, 'validate_registration_phone' ), 10, 3 );
		add_action( 'user_register',       array( $this, 'save_registration_phone' ) );
	}

	/**
	 * Render the phone number field on the user profile page.
	 *
	 * Displays a country-code dropdown (from allowed countries) + local number input.
	 * The combined value (e.g. 96599220322) is stored in kwtsms_phone user meta.
	 *
	 * @param WP_User $user The user being edited.
	 */
	public function render_phone_field( WP_User $user ) {
		$phone = get_user_meta( $user->ID, 'kwtsms_phone', true );

		// Load country data.
		$settings       = new KwtSMS_Settings();
		$allowed_iso2   = (array) $settings->get( 'general.allowed_countries', array( 'KW', 'SA', 'AE', 'BH', 'QA', 'OM' ) );
		$default_iso2   = (string) $settings->get( 'general.default_country_code', 'KW' );
		$all_countries  = include KWTSMS_OTP_DIR . 'includes/data/country-codes.php';

		$cc_by_iso2 = array();
		foreach ( $all_countries as $cc ) {
			$cc_by_iso2[ $cc['iso2'] ] = $cc;
		}
		$allowed_countries = array();
		foreach ( $allowed_iso2 as $iso2 ) {
			if ( isset( $cc_by_iso2[ $iso2 ] ) ) {
				$allowed_countries[] = $cc_by_iso2[ $iso2 ];
			}
		}
		if ( empty( $allowed_countries ) ) {
			$allowed_countries = $all_countries;
		}

		// Determine pre-selected dial code from saved phone.
		$selected_dial = '';
		$local_number  = $phone;
		if ( ! empty( $phone ) ) {
			// Try to match the saved phone against allowed country dial codes.
			foreach ( $allowed_countries as $cc ) {
				if ( 0 === strpos( $phone, $cc['dial'] ) ) {
					$selected_dial = $cc['dial'];
					$local_number  = substr( $phone, strlen( $cc['dial'] ) );
					break;
				}
			}
		}

		// Fallback: use default country dial code.
		if ( empty( $selected_dial ) && isset( $cc_by_iso2[ $default_iso2 ] ) ) {
			$selected_dial = $cc_by_iso2[ $default_iso2 ]['dial'];
		}
		?>
		<h3><?php esc_html_e( 'SMS OTP Authentication', 'wp-kwtsms' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="kwtsms_local_phone"><?php esc_html_e( 'Phone Number (for SMS OTP)', 'wp-kwtsms' ); ?></label>
				</th>
				<td>
					<?php wp_nonce_field( 'kwtsms_save_phone_' . $user->ID, 'kwtsms_phone_nonce' ); ?>

					<div style="display:flex;gap:0;max-width:400px;">
						<select name="kwtsms_dial_code" id="kwtsms_dial_code" style="flex:0 0 auto;">
							<?php foreach ( $allowed_countries as $cc ) : ?>
							<option value="<?php echo esc_attr( $cc['dial'] ); ?>"
								<?php selected( $selected_dial, $cc['dial'] ); ?>>
								<?php echo esc_html( $cc['name'] . ' (+' . $cc['dial'] . ')' ); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<input
							type="tel"
							name="kwtsms_local_phone"
							id="kwtsms_local_phone"
							value="<?php echo esc_attr( $local_number ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Local number', 'wp-kwtsms' ); ?>"
							style="flex:1;"
						/>
					</div>
					<!-- Hidden combined field submitted as kwtsms_phone -->
					<input type="hidden" name="kwtsms_phone" id="kwtsms_phone_combined" value="<?php echo esc_attr( $phone ); ?>" />

					<p class="description">
						<?php esc_html_e( 'Enter your phone number with country code. Used to receive OTP codes for login and password reset.', 'wp-kwtsms' ); ?>
					</p>
					<?php if ( ! empty( $phone ) ) : ?>
						<p class="description">
							<strong><?php esc_html_e( 'Saved:', 'wp-kwtsms' ); ?></strong>
							<code><?php echo esc_html( $phone ); ?></code>
						</p>
					<?php endif; ?>

					<script>
					(function() {
						var dialSelect = document.getElementById('kwtsms_dial_code');
						var localInput = document.getElementById('kwtsms_local_phone');
						var combined   = document.getElementById('kwtsms_phone_combined');
						function update() {
							var dial  = dialSelect.value.replace(/\D/g, '');
							var local = localInput.value.replace(/^0+/, '').replace(/\D/g, '');
							// Strip leading dial code if user typed the full international number.
							if ( dial && local.indexOf( dial ) === 0 ) {
								local = local.slice( dial.length );
							}
							combined.value = local ? (dial + local) : '';
						}
						if (dialSelect && localInput && combined) {
							dialSelect.addEventListener('change', update);
							localInput.addEventListener('input', update);
							update(); // initial sync
						}
					})();
					</script>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the phone number field from the user profile form.
	 *
	 * Validates nonce and capability, normalises the number, and saves to user meta.
	 *
	 * @param int $user_id The ID of the user being updated.
	 */
	public function save_phone_field( $user_id ) {
		if ( ! isset( $_POST['kwtsms_phone_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_key( wp_unslash( $_POST['kwtsms_phone_nonce'] ) ),
			'kwtsms_save_phone_' . $user_id
		) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$raw_phone = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone'] ?? '' ) );

		// Server-side fallback for no-JS environments: combine dial code + local number.
		if ( '' === $raw_phone ) {
			$dial  = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['kwtsms_dial_code'] ?? '' ) ) );
			$local = preg_replace( '/^0+/', '', preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['kwtsms_local_phone'] ?? '' ) ) ) );
			if ( '' !== $dial && '' !== $local ) {
				$raw_phone = $dial . $local;
			}
		}

		// Empty value — clear the meta.
		if ( '' === $raw_phone ) {
			delete_user_meta( $user_id, 'kwtsms_phone' );
			return;
		}

		$raw_phone  = KwtSMS_API::prepend_country_code_if_local( $raw_phone, KwtSMS_API::get_default_dial_code() );
		$normalized = KwtSMS_API::normalize_phone( $raw_phone );

		if ( is_wp_error( $normalized ) ) {
			// Attach error to the profile update errors bag.
			add_action(
				'user_profile_update_errors',
				static function( WP_Error $errors ) use ( $normalized ) {
					$errors->add( 'kwtsms_invalid_phone', $normalized->get_error_message() );
				}
			);
			return;
		}

		update_user_meta( $user_id, 'kwtsms_phone', $normalized );
	}

	/**
	 * Render the phone number field on the default WordPress registration form.
	 *
	 * The field is optional. If provided, it must be a valid international number.
	 * Stored as kwtsms_phone user meta after successful registration.
	 */
	public function render_registration_phone_field() {
		$phone = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone_reg'] ?? '' ) );
		?>
		<p>
			<label for="kwtsms_phone_reg">
				<?php esc_html_e( 'Phone Number (optional)', 'wp-kwtsms' ); ?><br />
				<input
					type="tel"
					name="kwtsms_phone_reg"
					id="kwtsms_phone_reg"
					class="input"
					value="<?php echo esc_attr( $phone ); ?>"
					size="25"
					autocomplete="tel"
					placeholder="e.g. 96598765432"
				/>
			</label>
			<span class="description">
				<?php esc_html_e( 'Enter your phone with country code. Used for SMS verification.', 'wp-kwtsms' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Validate the phone field on registration.
	 *
	 * Called on the `registration_errors` filter.
	 * Only validates if a non-empty phone was provided (field is optional).
	 *
	 * @param WP_Error $errors               Existing registration errors.
	 * @param string   $sanitized_user_login Username.
	 * @param string   $user_email           Email address.
	 *
	 * @return WP_Error
	 */
	public function validate_registration_phone( $errors, $sanitized_user_login, $user_email ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone_reg'] ?? '' ) );

		if ( '' === $phone ) {
			return $errors; // Optional field — no validation needed.
		}

		$phone      = KwtSMS_API::prepend_country_code_if_local( $phone, KwtSMS_API::get_default_dial_code() );
		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			$errors->add( 'kwtsms_invalid_phone', $normalized->get_error_message() );
		}

		return $errors;
	}

	/**
	 * Save the phone number from the registration form.
	 *
	 * Called on the `user_register` action after successful registration.
	 * Only saves if a valid phone was submitted (it already passed validation).
	 *
	 * @param int $user_id Newly created user ID.
	 */
	public function save_registration_phone( $user_id ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['kwtsms_phone_reg'] ?? '' ) );

		if ( '' === $phone ) {
			return;
		}

		$phone      = KwtSMS_API::prepend_country_code_if_local( $phone, KwtSMS_API::get_default_dial_code() );
		$normalized = KwtSMS_API::normalize_phone( $phone );
		if ( is_wp_error( $normalized ) ) {
			return; // Should not happen if validate_registration_phone() ran, but defensive.
		}

		update_user_meta( $user_id, 'kwtsms_phone', $normalized );
	}
}
