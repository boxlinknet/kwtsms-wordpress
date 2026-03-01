/**
 * kwtSMS OTP — Admin JavaScript
 *
 * Handles:
 *   - CAPTCHA provider field show/hide
 *   - Login button (verify credentials + persist state)
 *   - Logout button (clear verified state)
 *   - Save & Verify Credentials AJAX
 *   - Sender ID reload
 *   - Send Test SMS AJAX
 *   - SMS template character counter + page count
 *   - API Username phone-number detection warning
 *   - Coverage AJAX load (renders as tag chips)
 */

/* global kwtSmsAdminData, jQuery */

( function ( $ ) {
	'use strict';

	const data    = window.kwtSmsAdminData || {};
	const ajaxUrl = data.ajaxUrl || '';
	const nonce   = data.nonce   || '';
	const s       = data.strings || {};

	// Track whether credentials have been verified.
	// Initialized from PHP-persisted state so page reload remembers login.
	let credentialsVerified = !! data.credentialsVerified;

	// =========================================================================
	// Enable/disable dependent features based on verified state
	// =========================================================================

	function setDependentFeatures( enabled ) {
		$( '#kwtsms-reload-senders, #kwtsms-load-coverage, #kwtsms-send-test-sms' )
			.prop( 'disabled', ! enabled );
	}

	// Apply initial state from PHP.
	setDependentFeatures( credentialsVerified );

	// =========================================================================
	// API Username — warn if it looks like a phone number
	// =========================================================================

	$( '#kwtsms_api_username' ).on( 'blur', function () {
		const $field   = $( this );
		const val      = $field.val().trim();
		const $warning = $field.closest( 'td' ).find( '.kwtsms-username-warning' );

		if ( /^\+?[\d\s()\-]{8,}$/.test( val ) ) {
			if ( ! $warning.length ) {
				$field.after(
					'<p class="kwtsms-username-warning" style="color:#dc3232;margin-top:4px;">' +
					( s.usernameIsPhone || 'This looks like a phone number. API Username should be your kwtSMS account username.' ) +
					'</p>'
				);
			}
		} else {
			$warning.remove();
		}
	} );

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
	// Login button — verify credentials + persist state
	// =========================================================================

	function handleVerifyResponse( resp, $status, $loginStatus ) {
		if ( resp.success ) {
			const d = resp.data;

			// Populate sender ID dropdown.
			const $senderSelect = $( '#kwtsms_sender_id' );
			if ( d.sender_ids && d.sender_ids.length ) {
				const savedSender = $senderSelect.val();
				$senderSelect.empty();
				d.sender_ids.forEach( function ( id ) {
					const selected = id === savedSender ? ' selected' : '';
					$senderSelect.append( $( '<option' + selected + '>' ).val( id ).text( id ) );
				} );
			}

			// Show balance.
			const $balance    = $( '#kwtsms-balance' );
			const $balanceSub = $( '#kwtsms-balance-purchased' );
			if ( d.balance ) {
				$balance.text( parseFloat( d.balance.available ).toFixed( 2 ) );
				if ( d.balance.purchased ) {
					$balanceSub.text( 'of ' + parseFloat( d.balance.purchased ).toFixed( 2 ) + ' purchased' );
				}
			}

			// Update verified state + enable dependent features.
			credentialsVerified = true;
			setDependentFeatures( true );

			// Show verified sections and toggle login/logout buttons.
			$( '#kwtsms-verified-sections' ).show();
			$( '#kwtsms-balance-card' ).show();
			$( '.kwtsms-signup-note' ).hide();
			$( '#kwtsms-row-username' ).hide();
			$( '#kwtsms-row-password' ).hide();
			$( '#kwtsms-sender-row' ).show();
			$( '#kwtsms-login-btn' ).hide();
			$( '#kwtsms-logout-btn' ).show();

			// Update login status span.
			const username = $( '#kwtsms_api_username' ).val().trim();
			const statusMsg = '✓ ' + ( s.connectedAs
				? s.connectedAs.replace( '%s', username )
				: 'Connected as ' + username );
			if ( $loginStatus && $loginStatus.length ) {
				$loginStatus.html( '<span style="color:#46b450;">' + $( '<span>' ).text( statusMsg ).html() + '</span>' );
			}
			if ( $status ) {
				$status.addClass( 'is-success' ).text( s.verified || 'Credentials verified! ✓' ).show();
			}
		} else {
			credentialsVerified = false;
			setDependentFeatures( false );

			// Hide verified sections.
			$( '#kwtsms-verified-sections' ).hide();
			$( '#kwtsms-balance-card' ).hide();
			$( '.kwtsms-signup-note' ).show();
			$( '#kwtsms-row-username' ).show();
			$( '#kwtsms-row-password' ).show();
			$( '#kwtsms-sender-row' ).hide();
			$( '#kwtsms-login-btn' ).show();
			$( '#kwtsms-logout-btn' ).hide();

			const msg = resp.data && resp.data.message ? resp.data.message : ( s.error || 'Verification failed.' );
			if ( $loginStatus && $loginStatus.length ) {
				$loginStatus.html( '<span style="color:#dc3232;">' + $( '<span>' ).text( msg ).html() + '</span>' );
			}
			if ( $status ) {
				$status.addClass( 'is-error' ).text( msg ).show();
			}
		}
	}

	$( '#kwtsms-login-btn' ).on( 'click', function () {
		const $btn         = $( this );
		const $loginStatus = $( '#kwtsms-login-status' );
		const username     = $( '#kwtsms_api_username' ).val().trim();
		const password     = $( '#kwtsms_api_password' ).val().trim();

		if ( ! username || ! password ) {
			$loginStatus.html(
				'<span style="color:#dc3232;">' +
				( s.credentialsMissing || 'Please enter your API username and password first.' ) +
				'</span>'
			);
			return;
		}

		$btn.prop( 'disabled', true ).text( s.verifying || 'Verifying...' );
		$loginStatus.html( '' );

		$.post( ajaxUrl, {
			action:   'kwtsms_verify_credentials',
			nonce:    nonce,
			username: username,
			password: password,
		} )
		.done( function ( resp ) {
			handleVerifyResponse( resp, null, $loginStatus );
		} )
		.fail( function () {
			$loginStatus.html( '<span style="color:#dc3232;">' + ( s.error || 'Network error.' ) + '</span>' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text( s.login || 'Login' );
		} );
	} );

	// =========================================================================
	// Save & Verify Credentials (bottom button)
	// =========================================================================

	$( '#kwtsms-verify-btn, #kwtsms-reload-senders' ).on( 'click', function () {
		const $btn    = $( this );
		const isReload = $btn.attr( 'id' ) === 'kwtsms-reload-senders';
		const $status = $( '#kwtsms-api-status' );
		const username = $( '#kwtsms_api_username' ).val().trim();
		const password = $( '#kwtsms_api_password' ).val().trim();

		if ( ! username || ! password ) {
			$status.removeClass( 'is-success' ).addClass( 'is-error' )
				.text( s.credentialsMissing || 'Please enter your API username and password first.' )
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
			handleVerifyResponse( resp, $status, $( '#kwtsms-login-status' ) );
		} )
		.fail( function () {
			$status.addClass( 'is-error' ).text( s.error || 'Network error. Please try again.' ).show();
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text(
				isReload ? '↻ Reload' : ( s.saveAndVerify || 'Save & Verify Credentials' )
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
		const username = $( '#kwtsms_api_username' ).val().trim();
		const password = $( '#kwtsms_api_password' ).val().trim();

		if ( ! username || ! password ) {
			$result.text( s.credentialsMissing || 'Please save your API credentials first (Gateway Settings → Save Settings).' ).css( 'color', '#dc3232' );
			return;
		}

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
				const d   = resp.data || {};
				const msg = d.test_mode
					? 'Test mode ON — message queued, NOT delivered to your phone. OTP code: ' + ( d.code || '' ) + ' (check wp-content/debug.log)'
					: 'SMS delivered to ' + ( d.phone || '' ) + '. Check your messages.';
				$result.text( msg ).css( 'color', '#46b450' );
			} else {
				const msg = resp.data && resp.data.message ? resp.data.message : ( s.error || 'Send failed.' );
				$result.text( msg ).css( 'color', '#dc3232' );
			}
		} )
		.fail( function () {
			$result.text( s.error || 'Network error.' ).css( 'color', '#dc3232' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text( s.sendTestSms || 'Send Gateway Test SMS' );
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

	// =========================================================================
	// Coverage load — renders as tag chips (same style as Allowed Countries)
	// =========================================================================

	$( '#kwtsms-load-coverage' ).on( 'click', function () {
		const $btn    = $( this );
		const $result = $( '#kwtsms-coverage-result' );

		$btn.prop( 'disabled', true ).text( s.loadingCoverage || 'Loading coverage...' );
		$result.html( '' );

		$.post( ajaxUrl, {
			action: 'kwtsms_get_coverage',
			nonce:  nonce,
		} )
		.done( function ( resp ) {
			if ( resp.success && resp.data && resp.data.coverage ) {
				const coverage = resp.data.coverage;
				let html = '';

				if ( Array.isArray( coverage ) ) {
					coverage.forEach( function ( row ) {
						const name = row.country || row.name || JSON.stringify( row );
						html += '<span class="kwtsms-tag-chip">' + $( '<span>' ).text( name ).html() + '</span>';
					} );
				} else {
					// Object format: {KW: 'active', SA: 'active', ...}
					Object.keys( coverage ).forEach( function ( key ) {
						html += '<span class="kwtsms-tag-chip">' + $( '<span>' ).text( key ).html() + '</span>';
					} );
				}

				$result.html( html );
			} else {
				const msg = resp.data && resp.data.message ? resp.data.message : ( s.coverageError || 'Could not load coverage data.' );
				$result.html( '<p style="color:#dc3232;">' + $( '<span>' ).text( msg ).html() + '</p>' );
			}
		} )
		.fail( function () {
			$result.html( '<p style="color:#dc3232;">' + ( s.coverageError || 'Network error.' ) + '</p>' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text( '↻ ' + ( s.refreshCoverage || 'Refresh Coverage' ) );
		} );
	} );

	// =========================================================================
	// Logout button
	// =========================================================================

	$( '#kwtsms-logout-btn' ).on( 'click', function () {
		credentialsVerified = false;

		// Toggle buttons.
		$( '#kwtsms-login-btn' ).show();
		$( '#kwtsms-logout-btn' ).hide();

		// Hide verified sections.
		$( '#kwtsms-verified-sections' ).hide();

		// Clear login status.
		$( '#kwtsms-login-status' ).html( '' );

		// Hide balance bar and show signup note.
		$( '#kwtsms-balance-card' ).hide();
		$( '#kwtsms-balance' ).text( '—' );
		$( '#kwtsms-balance-purchased' ).text( '' );
		$( '.kwtsms-signup-note' ).show();
		$( '#kwtsms-row-username' ).show();
		$( '#kwtsms-row-password' ).show();
		$( '#kwtsms-sender-row' ).hide();

		// Server-side: clear credentials_verified flag.
		$.post( ajaxUrl, {
			action: 'kwtsms_logout_gateway',
			nonce:  nonce,
		} );
	} );

	// =========================================================================
	// On page load: render saved coverage chips
	// =========================================================================

	( function () {
		const savedCov = data.savedCoverage || [];
		if ( savedCov.length ) {
			const $result = $( '#kwtsms-coverage-result' );
			// Only populate if the result div is currently empty (PHP may have pre-rendered).
			if ( ! $result.children().length && ! $result.text().trim() ) {
				let html = '';
				savedCov.forEach( function ( c ) {
					const name = ( typeof c === 'object' ) ? ( c.name || c.country || '' ) : String( c );
					html += '<span class="kwtsms-tag-chip">' + $( '<span>' ).text( name ).html() + '</span>';
				} );
				$result.html( html );
			}
		}
	}() );

	// Balance and login/logout button state on page load are handled server-side
	// by page-gateway.php. setDependentFeatures( credentialsVerified ) above
	// handles button enable/disable state on init, so no JS restore is needed.

	// =========================================================================
	// Integrations page — tab switching
	// All tab content is always in the DOM (single form); we show/hide via JS.
	// =========================================================================

	( function () {
		var tabLinks    = document.querySelectorAll( '.kwtsms-int-tab-link' );
		var tabContents = document.querySelectorAll( '.kwtsms-int-tab-content' );

		if ( ! tabLinks.length ) {
			return;
		}

		tabLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var target = this.getAttribute( 'href' );

				// Deactivate all tabs.
				tabLinks.forEach( function ( l ) {
					l.classList.remove( 'nav-tab-active' );
				} );

				// Hide all tab panels.
				tabContents.forEach( function ( c ) {
					c.style.display = 'none';
				} );

				// Activate the clicked tab and show its panel.
				this.classList.add( 'nav-tab-active' );

				var panel = document.querySelector( target );
				if ( panel ) {
					panel.style.display = 'block';
				}
			} );
		} );
	}() );

} )( jQuery );
