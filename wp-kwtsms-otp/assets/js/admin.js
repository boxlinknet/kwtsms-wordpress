/**
 * kwtSMS OTP — Admin JavaScript
 *
 * Handles:
 *   - CAPTCHA provider field show/hide
 *   - Login button (verify credentials + persist state)
 *   - Logout button (clear verified state)
 *   - Save & Verify Credentials AJAX
 *   - Reload-All button (sender IDs + coverage in one click)
 *   - Send Test SMS AJAX
 *   - SMS template character counter + page count
 *   - API Username phone-number detection warning
 *   - Coverage rendering helper (renderCoverageChips)
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
		$( '#kwtsms-reload-all, #kwtsms-send-test-sms' ).prop( 'disabled', ! enabled );
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
					$balanceSub.text( ( s.ofPurchased || 'of %s purchased' ).replace( '%s', parseFloat( d.balance.purchased ).toFixed( 2 ) ) );
				}
			}

			// Update verified state + enable dependent features.
			credentialsVerified = true;
			setDependentFeatures( true );

			// Show verified sections and toggle login/logout/reload buttons.
			$( '#kwtsms-verified-sections' ).show();
			$( '#kwtsms-balance-card' ).show();
			$( '.kwtsms-signup-note' ).hide();
			$( '#kwtsms-row-username' ).hide();
			$( '#kwtsms-row-password' ).hide();
			$( '#kwtsms-sender-row' ).show();
			$( '#kwtsms-login-btn' ).hide();
			$( '#kwtsms-logout-btn' ).show();
			$( '#kwtsms-reload-all' ).show();
			$( '.kwtsms-reload-hint' ).show();

			// Render coverage chips if returned.
			if ( d.coverage ) {
				renderCoverageChips( d.coverage, $( '#kwtsms-coverage-result' ) );
			}

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
			$( '#kwtsms-reload-all' ).hide();

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

	$( '#kwtsms-verify-btn' ).on( 'click', function () {
		const $btn    = $( this );
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
			$btn.prop( 'disabled', false ).text( s.saveAndVerify || 'Save & Verify Credentials' );
		} );
	} );

	// =========================================================================
	// Reload-All button (sender IDs + coverage in one click)
	// =========================================================================

	$( '#kwtsms-reload-all' ).on( 'click', function () {
		const $btn     = $( this );
		const username = $( '#kwtsms_api_username' ).val().trim() || data.savedUsername || '';
		const password = $( '#kwtsms_api_password' ).val().trim() || data.savedPassword || '';

		$btn.prop( 'disabled', true ).text( '↻ ' + ( s.reloading || 'Reloading...' ) );

		$.post( ajaxUrl, {
			action:   'kwtsms_verify_credentials',
			nonce:    nonce,
			username: username,
			password: password,
		} )
		.done( function ( resp ) {
			handleVerifyResponse( resp, null, $( '#kwtsms-login-status' ) );
		} )
		.fail( function () {
			$( '#kwtsms-login-status' ).html( '<span style="color:#dc3232;">' + ( s.error || 'Network error.' ) + '</span>' );
		} )
		.always( function () {
			$btn.prop( 'disabled', false ).text( '↻ ' + ( s.reload || 'Reload' ) );
		} );
	} );

	// =========================================================================
	// Send Test SMS
	// =========================================================================

	$( '#kwtsms-send-test-sms' ).on( 'click', function () {
		const $btn    = $( this );
		const $result = $( '#kwtsms-test-sms-result' );
		const $field  = $( '#kwtsms_test_phone' );
		let   phone   = $field.val().trim();
		const username = $( '#kwtsms_api_username' ).val().trim();
		const password = $( '#kwtsms_api_password' ).val().trim();

		if ( ! username || ! password ) {
			$result.text( s.credentialsMissing || 'Please save your API credentials first (Gateway Settings → Save Settings).' ).css( 'color', '#dc3232' );
			return;
		}

		if ( ! phone ) {
			$result.text( s.testPhoneMissing || 'Please enter a test phone number first.' ).css( 'color', '#dc3232' );
			return;
		}

		// Auto-prefix short numbers (local format without country code).
		const digitsOnly = phone.replace( /^\+/, '' ).replace( /^00/, '' ).replace( /\D/g, '' );
		if ( digitsOnly.length <= 8 && digitsOnly.length >= 5 ) {
			const dial = data.defaultDialCode || '965';
			phone = dial + digitsOnly;
			$field.val( phone ); // update the field so user sees the full number
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
					? ( s.testModeResult || 'Test mode ON — message queued, not delivered.' )
					: ( s.testSmsResult || 'SMS sent to %phone%. Check your messages.' ).replace( '%phone%', d.phone || '' );
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
	// Coverage rendering helper (used by Reload-All and page load)
	// =========================================================================

	function renderCoverageChips( coverage, $result ) {
		var API_CODES = [ 'OK', 'ERROR', 'ERR', 'FAIL', 'FAILED', 'NULL', 'NONE', 'N/A', 'NA', 'TRUE', 'FALSE' ];
		var html = '';
		if ( Array.isArray( coverage ) ) {
			coverage.forEach( function ( row ) {
				var name = '', dial = '';
				if ( typeof row === 'object' && row !== null ) {
					name = row.name || row.country || row.countryName || row.CountryName || '';
					if ( ! name ) {
						name = Object.values( row ).find( function( v ) { return typeof v === 'string'; } ) || '';
					}
					dial = row.dial || '';
				} else {
					name = String( row );
				}
				if ( ! name ) return;
				// Skip API status code strings and bare dial-code digit strings.
				if ( API_CODES.indexOf( name.toUpperCase() ) !== -1 ) return;
				if ( /^\d+$/.test( name ) ) return;
				var label = dial ? name + ' (+' + dial + ')' : name;
				html += '<span class="kwtsms-tag-chip">' + $( '<span>' ).text( label ).html() + '</span>';
			} );
		} else if ( coverage && typeof coverage === 'object' ) {
			Object.keys( coverage ).forEach( function ( key ) {
				if ( API_CODES.indexOf( String( key ).toUpperCase() ) === -1 ) {
					html += '<span class="kwtsms-tag-chip">' + $( '<span>' ).text( key ).html() + '</span>';
				}
			} );
		}
		$result.html( html );
	}

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
		$( '#kwtsms-reload-all' ).hide();
		$( '.kwtsms-reload-hint' ).hide();

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
		const $result  = $( '#kwtsms-coverage-result' );
		// Only populate if PHP did not pre-render chips.
		if ( savedCov.length && ! $result.children().length && ! $result.text().trim() ) {
			renderCoverageChips( savedCov, $result );
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

	// =========================================================================
	// Unsaved changes — custom branded modal
	//
	// For in-page link navigation: intercepts the click and shows our own
	// dialog so we control the title, body, and button labels (and they are
	// fully translatable via kwtSmsAdminData.strings).
	//
	// For browser close / back / forward (where we cannot intercept the click):
	// a beforeunload listener is still registered as a safety net; browsers
	// always show their own native text there regardless of what we set.
	// =========================================================================

	( function () {
		var $forms = $( '.kwtsms-admin-wrap form[action="options.php"]' );
		if ( ! $forms.length ) {
			return;
		}

		var dirty       = false;
		var pendingHref = null;

		// ── Build the modal ────────────────────────────────────────────────
		var $overlay = $(
			'<div id="kwtsms-unsaved-overlay" role="dialog" aria-modal="true" aria-labelledby="kwtsms-unsaved-title">' +
				'<div id="kwtsms-unsaved-dialog">' +
					'<h2 id="kwtsms-unsaved-title"></h2>' +
					'<p  id="kwtsms-unsaved-body"></p>' +
					'<div id="kwtsms-unsaved-hint">' +
						'<span id="kwtsms-unsaved-hint-icon">&#128161;</span>' +
						'<span id="kwtsms-unsaved-hint-text"></span>' +
					'</div>' +
					'<div id="kwtsms-unsaved-actions">' +
						'<button type="button" id="kwtsms-unsaved-stay"  class="button kwtsms-save-btn"></button>' +
						'<button type="button" id="kwtsms-unsaved-leave" class="button"></button>' +
					'</div>' +
				'</div>' +
			'</div>'
		).appendTo( 'body' );

		$overlay.find( '#kwtsms-unsaved-title'     ).text( s.unsavedTitle || 'Unsaved Changes'                                                                              );
		$overlay.find( '#kwtsms-unsaved-body'      ).text( s.unsavedBody  || 'You have unsaved changes. Leaving this page will discard them.'                               );
		$overlay.find( '#kwtsms-unsaved-hint-text' ).text( s.unsavedHint  || 'To save: click "Stay on Page", then scroll to the bottom and click the Save Settings button.' );
		$overlay.find( '#kwtsms-unsaved-stay'      ).text( s.unsavedStay  || 'Stay on Page'                                                                                );
		$overlay.find( '#kwtsms-unsaved-leave'     ).text( s.unsavedLeave || 'Leave Page'                                                                                  );

		function showModal( href ) {
			pendingHref = href || null;
			$overlay.addClass( 'is-visible' );
			$( '#kwtsms-unsaved-stay' ).trigger( 'focus' );
		}

		function hideModal() {
			pendingHref = null;
			$overlay.removeClass( 'is-visible' );
		}

		// "Stay on Page" — dismiss modal, do nothing.
		$( '#kwtsms-unsaved-stay' ).on( 'click', hideModal );

		// Clicking the backdrop also stays (do not navigate).
		$overlay.on( 'click', function ( e ) {
			if ( e.target === this ) { hideModal(); }
		} );

		// Esc key — stay.
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && $overlay.hasClass( 'is-visible' ) ) { hideModal(); }
		} );

		// "Leave Page" — proceed with stored navigation target.
		$( '#kwtsms-unsaved-leave' ).on( 'click', function () {
			dirty = false;
			var href = pendingHref;
			hideModal();
			if ( href ) { window.location.href = href; }
		} );

		// ── Dirty tracking ─────────────────────────────────────────────────
		$forms.on( 'input change', 'input, select, textarea', function () {
			dirty = true;
		} );

		// Clear dirty on save (form submit).
		$forms.on( 'submit', function () {
			dirty = false;
		} );

		// ── Intercept link clicks for in-page navigation ───────────────────
		$( document ).on( 'click', 'a', function ( e ) {
			if ( ! dirty ) { return; }

			var rawHref = $( this ).attr( 'href' ) || '';
			// Skip: empty, same-page anchors, javascript: links, new-tab links.
			if (
				! rawHref ||
				rawHref === '#' ||
				rawHref.indexOf( 'javascript:' ) === 0 ||
				$( this ).attr( 'target' ) === '_blank'
			) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();
			showModal( this.href );
		} );

		// ── Fallback for browser close / back / forward ────────────────────
		// Cannot intercept these as link clicks; the browser will show its own
		// native dialog text (we cannot customise that text).
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );
	}() );

} )( jQuery );
