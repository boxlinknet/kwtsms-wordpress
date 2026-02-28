/**
 * kwtsms OTP — Admin JavaScript
 *
 * Handles:
 *   - CAPTCHA provider field show/hide
 *   - Save & Verify Credentials AJAX
 *   - Sender ID reload
 *   - Send Test SMS AJAX
 *   - SMS template character counter + page count
 */

/* global kwtSmsAdminData, jQuery */

( function ( $ ) {
	'use strict';

	const data = window.kwtSmsAdminData || {};
	const ajaxUrl = data.ajaxUrl || '';
	const nonce   = data.nonce   || '';
	const s       = data.strings || {};

	// =========================================================================
	// CAPTCHA field show/hide
	// =========================================================================

	function updateCaptchaFields() {
		const val = $( 'input[name="kwtsms_otp_general[captcha_provider]"]:checked' ).val();
		$( '.kwtsms-recaptcha-fields' ).toggle( val === 'recaptcha' );
		$( '.kwtsms-turnstile-fields' ).toggle( val === 'turnstile' );
	}

	$( 'input[name="kwtsms_otp_general[captcha_provider]"]' ).on( 'change', updateCaptchaFields );
	updateCaptchaFields(); // Initial state.

	// =========================================================================
	// Save & Verify Credentials
	// =========================================================================

	$( '#kwtsms-verify-btn, #kwtsms-reload-senders' ).on( 'click', function () {
		const $btn    = $( this );
		const $status = $( '#kwtsms-api-status' );
		const $balance = $( '#kwtsms-balance' );
		const $balanceSub = $( '#kwtsms-balance-purchased' );
		const $senderSelect = $( '#kwtsms_sender_id' );
		const username = $( '#kwtsms_api_username' ).val();
		const password = $( '#kwtsms_api_password' ).val();

		if ( ! username || ! password ) {
			$status.removeClass( 'is-success' ).addClass( 'is-error' )
				.text( 'Please enter your API username and password.' )
				.show();
			return;
		}

		$btn.prop( 'disabled', true ).text( s.verifying || 'Verifying...' );
		$status.hide().removeClass( 'is-success is-error' );

		$.post( ajaxUrl, {
			action:   'kwtsms_verify_credentials',
			nonce:    nonce,
			username: username,
			password: password,
		} )
		.done( function ( resp ) {
			if ( resp.success ) {
				const d = resp.data;

				// Populate sender ID dropdown.
				if ( d.sender_ids && d.sender_ids.length ) {
					const savedSender = $senderSelect.val();
					$senderSelect.empty();
					d.sender_ids.forEach( function ( id ) {
						const selected = id === savedSender ? ' selected' : '';
						$senderSelect.append( $( '<option' + selected + '>' ).val( id ).text( id ) );
					} );
				}

				// Show balance.
				if ( d.balance ) {
					$balance.text( parseFloat( d.balance.available ).toFixed( 2 ) + ' credits' );
					if ( d.balance.purchased ) {
						$balanceSub.text( 'Purchased: ' + parseFloat( d.balance.purchased ).toFixed( 2 ) );
					}
				}

				$status.addClass( 'is-success' ).text( s.verified || 'Credentials verified! ✓' ).show();
			} else {
				const msg = resp.data && resp.data.message ? resp.data.message : ( s.error || 'Verification failed.' );
				$status.addClass( 'is-error' ).text( msg ).show();
			}
		} )
		.fail( function () {
			$status.addClass( 'is-error' ).text( s.error || 'Network error. Please try again.' ).show();
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text(
				$btn.attr( 'id' ) === 'kwtsms-reload-senders' ? '↻ Reload' : 'Save & Verify Credentials'
			);
		} );
	} );

	// =========================================================================
	// Send Test SMS
	// =========================================================================

	$( '#kwtsms-send-test-sms' ).on( 'click', function () {
		const $btn    = $( this );
		const $result = $( '#kwtsms-test-sms-result' );
		const phone   = $( '#kwtsms_test_phone' ).val();

		if ( ! phone ) {
			$result.text( 'Please enter a test phone number first.' ).css( 'color', '#dc3232' );
			return;
		}

		$btn.prop( 'disabled', true ).text( s.sending || 'Sending...' );
		$result.text( '' );

		$.post( ajaxUrl, {
			action:  'kwtsms_send_test_sms',
			nonce:   nonce,
			phone:   phone,
		} )
		.done( function ( resp ) {
			if ( resp.success ) {
				$result.text( s.sent || 'Test SMS sent! Check your phone.' ).css( 'color', '#46b450' );
			} else {
				const msg = resp.data && resp.data.message ? resp.data.message : 'Send failed.';
				$result.text( msg ).css( 'color', '#dc3232' );
			}
		} )
		.fail( function () {
			$result.text( 'Network error.' ).css( 'color', '#dc3232' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text( 'Send Test SMS Now' );
		} );
	} );

	// =========================================================================
	// SMS character counter + page count
	// =========================================================================

	function updateCharCounter( $textarea ) {
		const text = $textarea.val();
		const lang = $textarea.data( 'lang' ) || 'en';
		const len  = text.length;

		// GSM-7 encoding limits: 160 single-page, 153 per page multi.
		// Arabic (Unicode): 70 single-page, 67 per page multi.
		const singleLimit = ( lang === 'ar' ) ? 70  : 160;
		const multiLimit  = ( lang === 'ar' ) ? 67  : 153;
		const pages       = len <= singleLimit ? 1 : Math.ceil( len / multiLimit );

		const $counter = $textarea.closest( '.kwtsms-textarea-wrap' ).find( '.kwtsms-char-counter' );
		$counter.find( '.kwtsms-char-count' ).text( len );
		$counter.find( '.kwtsms-page-count' ).text( pages );
		$counter.toggleClass( 'is-warning', pages > 1 );
	}

	$( '.kwtsms-sms-textarea' ).each( function () {
		updateCharCounter( $( this ) );
	} );

	$( document ).on( 'input', '.kwtsms-sms-textarea', function () {
		updateCharCounter( $( this ) );
	} );

} )( jQuery );
