<?php
/**
 * Admin View: Users Without Phone.
 *
 * Lists all users in OTP-required roles who have no phone number saved.
 * Each row has an inline phone input and Save button backed by AJAX.
 *
 * @package KwtSMS_OTP
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- @var KwtSMS_Admin $this, injected by admin controller.

$settings       = $this->plugin->settings;
$required_roles = (array) $settings->get( 'general.otp_required_roles', array() );

// Query users without a phone who are subject to OTP.
$query_args = array(
	'number'     => 200,
	'orderby'    => 'display_name',
	'order'      => 'ASC',
	'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'relation' => 'OR',
		array(
			'key'     => 'kwtsms_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => 'kwtsms_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'value'   => '',
			'compare' => '=',
		),
	),
);

if ( ! empty( $required_roles ) ) {
	$query_args['role__in'] = $required_roles;
}

$users      = get_users( $query_args );
$user_count = count( $users );
$nonce      = wp_create_nonce( 'kwtsms_admin_nonce' );

// Resolve default dial code for client-side auto-prefixing.
$default_iso2 = $settings->get( 'general.default_country_code', 'KW' );
$all_ccs      = include KWTSMS_OTP_DIR . 'includes/data/country-codes.php';
$default_dial = '965'; // Kuwait fallback.
foreach ( $all_ccs as $cc_row ) {
	if ( $cc_row['iso2'] === $default_iso2 ) {
		$default_dial = $cc_row['dial'];
		break;
	}
}

// Human-readable role labels.
$all_wp_roles = wp_roles()->get_names();

// Color scheme per role slug.
$role_colors = array(
	'administrator' => array(
		'bg' => '#1d2327',
		'fg' => '#ffffff',
	),
	'editor'        => array(
		'bg' => '#2271b1',
		'fg' => '#ffffff',
	),
	'author'        => array(
		'bg' => '#00a32a',
		'fg' => '#ffffff',
	),
	'contributor'   => array(
		'bg' => '#787c82',
		'fg' => '#ffffff',
	),
	'subscriber'    => array(
		'bg' => '#e8e8e8',
		'fg' => '#3c434a',
	),
	'customer'      => array(
		'bg' => '#8f5fb4',
		'fg' => '#ffffff',
	),
	'shop_manager'  => array(
		'bg' => '#7f54b3',
		'fg' => '#ffffff',
	),
);
?>

<div class="wrap kwtsms-admin-wrap">

	<?php $this->render_page_notices(); ?>

	<div class="kwtsms-admin-header">
		<img src="<?php echo esc_url( KWTSMS_OTP_URL . 'admin/images/kwtsms_logo_60.png' ); ?>" alt="kwtSMS" class="kwtsms-logo" />
		<h1><?php esc_html_e( 'Users Without Phone', 'kwtsms' ); ?></h1>
	</div>
	<hr class="wp-header-end">

	<?php // ── Summary bar ──────────────────────────────────────────────────── ?>
	<div class="kwtsms-unphone-summary <?php echo $user_count > 0 ? 'is-warning' : 'is-ok'; ?>">
		<span class="kwtsms-unphone-count" id="kwtsms-unphone-count"><?php echo (int) $user_count; ?></span>
		<?php if ( $user_count > 0 ) : ?>
			<div class="kwtsms-unphone-summary-text">
				<strong><?php esc_html_e( 'Users need a phone number', 'kwtsms' ); ?></strong>
				<span><?php esc_html_e( 'These users are required to verify via OTP but will bypass it until a phone is saved.', 'kwtsms' ); ?></span>
			</div>
		<?php else : ?>
			<div class="kwtsms-unphone-summary-text">
				<strong><?php esc_html_e( 'All users covered', 'kwtsms' ); ?></strong>
				<span><?php esc_html_e( 'Every user in an OTP-required role has a phone number. OTP is enforced for all of them.', 'kwtsms' ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<?php // ── Scope note ────────────────────────────────────────────────────── ?>
	<p class="kwtsms-unphone-scope">
		<?php if ( ! empty( $required_roles ) ) : ?>
			<?php
			$role_name_list = array();
			foreach ( $required_roles as $slug ) {
				$role_name_list[] = isset( $all_wp_roles[ $slug ] ) ? translate_user_role( $all_wp_roles[ $slug ] ) : ucfirst( $slug );
			}
			printf(
				/* translators: 1: comma-separated role names, 2: link to General Settings */
				esc_html__( 'Filtered to OTP-required roles: %1$s. Change roles in %2$s.', 'kwtsms' ),
				'<strong>' . esc_html( implode( ', ', $role_name_list ) ) . '</strong>',
				'<a href="' . esc_url( admin_url( 'admin.php?page=kwtsms-otp' ) ) . '">' . esc_html__( 'General Settings', 'kwtsms' ) . '</a>'
			);
			?>
		<?php else : ?>
			<?php
			printf(
				/* translators: %s: link to General Settings */
				esc_html__( 'No roles configured — showing all users without a phone. %s', 'kwtsms' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=kwtsms-otp' ) ) . '">' . esc_html__( 'Configure OTP roles.', 'kwtsms' ) . '</a>'
			);
			?>
		<?php endif; ?>
	</p>

	<?php if ( $user_count > 0 ) : ?>

	<table class="wp-list-table widefat fixed kwtsms-unphone-table" id="kwtsms-unphone-table">
		<thead>
			<tr>
				<th scope="col" style="width:34px;padding:10px 8px;"></th>
				<th scope="col" style="width:190px;"><?php esc_html_e( 'User', 'kwtsms' ); ?></th>
					<th scope="col" style="width:120px;"><?php esc_html_e( 'Role', 'kwtsms' ); ?></th>
				<th scope="col" style="width:260px;"><?php esc_html_e( 'Phone Number', 'kwtsms' ); ?></th>
				<th scope="col" style="width:80px;"></th>
			</tr>
		</thead>
		<tbody id="kwtsms-unphone-tbody">
			<?php foreach ( $users as $user ) : ?>
				<?php
				$roles        = (array) $user->roles;
				$primary_role = $roles[0] ?? '';
				$role_label   = isset( $all_wp_roles[ $primary_role ] )
					? translate_user_role( $all_wp_roles[ $primary_role ] )
					: ucfirst( $primary_role );
				$chip         = $role_colors[ $primary_role ] ?? array(
					'bg' => '#e8e8e8',
					'fg' => '#3c434a',
				);
				$chip_style   = 'background:' . $chip['bg'] . ';color:' . $chip['fg'];
				?>
				<tr id="kwtsms-urow-<?php echo (int) $user->ID; ?>" class="kwtsms-unphone-row">
					<td style="padding:10px 8px;vertical-align:middle;text-align:center;">
						<?php echo get_avatar( $user->ID, 28, '', '', array( 'class' => 'kwtsms-unphone-avatar' ) ); ?>
					</td>
					<td style="padding:10px 12px;vertical-align:middle;">
						<strong><?php echo esc_html( $user->display_name ); ?></strong><br>
						<span class="kwtsms-unphone-login">@<?php echo esc_html( $user->user_login ); ?></span>
					</td>
					<td style="padding:10px 12px;vertical-align:middle;">
						<span class="kwtsms-unphone-role-chip" style="<?php echo esc_attr( $chip_style ); ?>">
							<?php echo esc_html( $role_label ); ?>
						</span>
					</td>
					<td style="padding:8px 12px;vertical-align:middle;">
						<input
							type="tel"
							class="kwtsms-unphone-input"
							placeholder="<?php esc_attr_e( 'e.g. 96598765432', 'kwtsms' ); ?>"
							maxlength="15"
							data-user-id="<?php echo (int) $user->ID; ?>"
							autocomplete="off"
						/>
						<span class="kwtsms-unphone-msg" aria-live="polite"></span>
					</td>
					<td style="padding:8px 12px;vertical-align:middle;">
						<button type="button"
							class="button button-primary kwtsms-unphone-save-btn"
							data-user-id="<?php echo (int) $user->ID; ?>">
							<?php esc_html_e( 'Save', 'kwtsms' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php else : ?>

	<div class="kwtsms-unphone-empty" id="kwtsms-unphone-empty">
		<div class="kwtsms-unphone-empty-icon">&#10003;</div>
		<h3><?php esc_html_e( 'All users are covered', 'kwtsms' ); ?></h3>
		<p><?php esc_html_e( 'Every user in an OTP-required role has a phone number saved. OTP is fully enforced.', 'kwtsms' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kwtsms-otp' ) ); ?>" class="button">
			<?php esc_html_e( 'Back to General Settings', 'kwtsms' ); ?>
		</a>
	</div>

	<?php endif; ?>

</div><!-- /.kwtsms-admin-wrap -->

<?php
wp_localize_script(
	'kwtsms-admin',
	'kwtSmsUnphoneData',
	array(
		'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		'nonce'          => $nonce,
		'defaultDial'    => $default_dial,
		'generalPageUrl' => admin_url( 'admin.php?page=kwtsms-otp' ),
		'strings'        => array(
			'saving'       => __( "Saving\u2026", 'kwtsms' ),
			'couldNotSave' => __( 'Could not save phone.', 'kwtsms' ),
			'save'         => __( 'Save', 'kwtsms' ),
			'requestFail'  => __( 'Request failed. Try again.', 'kwtsms' ),
			'enterPhone'   => __( 'Please enter a phone number.', 'kwtsms' ),
			'tooShort'     => __( 'Number too short. Include country code, e.g. 96512345678.', 'kwtsms' ),
		),
	)
);

wp_add_inline_script(
	'kwtsms-admin',
	'(function($){' .
	'"use strict";' .
	'var d=kwtSmsUnphoneData;' .
	'function savePhone(userId,phone,$row){' .
		'var $btn=$row.find(".kwtsms-unphone-save-btn");' .
		'var $msg=$row.find(".kwtsms-unphone-msg");' .
		'var $inp=$row.find(".kwtsms-unphone-input");' .
		'$btn.prop("disabled",true).text(d.strings.saving);' .
		'$msg.text("").removeClass("is-error is-ok");' .
		'$inp.prop("disabled",true);' .
		'$.post(d.ajaxUrl,{' .
			'action:"kwtsms_save_user_phone",' .
			'nonce:d.nonce,' .
			'user_id:userId,' .
			'phone:phone' .
		'})' .
		'.done(function(res){' .
			'if(res.success){' .
				'$row.addClass("kwtsms-row-saved");' .
				'setTimeout(function(){' .
					'$row.addClass("kwtsms-row-removing");' .
					'setTimeout(function(){' .
						'$row.remove();' .
						'var remaining=$("#kwtsms-unphone-tbody tr").length;' .
						'$("#kwtsms-unphone-count").text(remaining);' .
						'if(remaining===0){window.location.href=d.generalPageUrl;}' .
					'},400);' .
				'},700);' .
			'}else{' .
				'var errMsg=(res.data&&res.data.message)?res.data.message:d.strings.couldNotSave;' .
				'$msg.text(errMsg).addClass("is-error");' .
				'$btn.prop("disabled",false).text(d.strings.save);' .
				'$inp.prop("disabled",false);' .
			'}' .
		'})' .
		'.fail(function(){' .
			'$msg.text(d.strings.requestFail).addClass("is-error");' .
			'$btn.prop("disabled",false).text(d.strings.save);' .
			'$inp.prop("disabled",false);' .
		'});' .
	'}' .
	'$(document).on("input",".kwtsms-unphone-input",function(){' .
		'var raw=$(this).val();' .
		'var cleaned=raw.replace(/\\D/g,"");' .
		'if(raw!==cleaned){$(this).val(cleaned);}' .
	'});' .
	'$(document).on("click",".kwtsms-unphone-save-btn",function(){' .
		'var userId=$(this).data("user-id");' .
		'var $row=$("#kwtsms-urow-"+userId);' .
		'var $inp=$row.find(".kwtsms-unphone-input");' .
		'var $msg=$row.find(".kwtsms-unphone-msg");' .
		'var digits=$inp.val().replace(/\\D/g,"");' .
		'$msg.text("").removeClass("is-error is-ok");' .
		'if(!digits){$msg.text(d.strings.enterPhone).addClass("is-error");return;}' .
		'if(digits.length<=8&&digits.length>=5){digits=d.defaultDial+digits;$inp.val(digits);}' .
		'if(digits.length<10){$msg.text(d.strings.tooShort).addClass("is-error");return;}' .
		'savePhone(userId,digits,$row);' .
	'});' .
	'$(document).on("keydown",".kwtsms-unphone-input",function(e){' .
		'if(13===e.which){' .
			'var userId=$(this).data("user-id");' .
			'$("#kwtsms-urow-"+userId).find(".kwtsms-unphone-save-btn").trigger("click");' .
		'}' .
	'});' .
	'})(jQuery);'
);
?>
