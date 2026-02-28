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
	}

	/**
	 * Render the phone number field on the user profile page.
	 *
	 * @param WP_User $user The user being edited.
	 */
	public function render_phone_field( WP_User $user ) {
		$phone = get_user_meta( $user->ID, 'kwtsms_phone', true );
		?>
		<h3><?php esc_html_e( 'SMS OTP Authentication', 'wp-kwtsms-otp' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="kwtsms_phone"><?php esc_html_e( 'Phone Number (for SMS OTP)', 'wp-kwtsms-otp' ); ?></label>
				</th>
				<td>
					<?php wp_nonce_field( 'kwtsms_save_phone_' . $user->ID, 'kwtsms_phone_nonce' ); ?>
					<input
						type="tel"
						name="kwtsms_phone"
						id="kwtsms_phone"
						value="<?php echo esc_attr( $phone ); ?>"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g. 96598765432', 'wp-kwtsms-otp' ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Enter phone number with country code (e.g. 96598765432 for Kuwait). Arabic/Eastern Arabic numerals are accepted. Used to receive OTP codes for login and password reset.', 'wp-kwtsms-otp' ); ?>
					</p>
					<?php if ( ! empty( $phone ) ) : ?>
						<p class="description">
							<strong><?php esc_html_e( 'Saved:', 'wp-kwtsms-otp' ); ?></strong>
							<?php echo esc_html( $phone ); ?>
						</p>
					<?php endif; ?>
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

		// Empty value — clear the meta.
		if ( '' === $raw_phone ) {
			delete_user_meta( $user_id, 'kwtsms_phone' );
			return;
		}

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
}
