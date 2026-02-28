/**
 * kwtsms OTP — Login Page JavaScript
 *
 * Handles the resend countdown timer and OTP resend AJAX call.
 * No jQuery dependency — uses vanilla JS only (lighter for login page).
 */

( function () {
	'use strict';

	// =========================================================================
	// Passwordless phone form — combine dial code + local number on submit.
	// =========================================================================
	const passwordlessForm = document.getElementById( 'kwtsms-passwordless-form' );
	if ( passwordlessForm ) {
		passwordlessForm.addEventListener( 'submit', function () {
			const dialSelect   = document.getElementById( 'kwtsms_dial_code' );
			const localInput   = document.getElementById( 'kwtsms_local_phone' );
			const combinedInput = document.getElementById( 'kwtsms_phone_combined' );

			if ( dialSelect && localInput && combinedInput ) {
				const dial  = dialSelect.value.replace( /\D/g, '' );
				const local = localInput.value.replace( /^0+/, '' ).replace( /\D/g, '' );
				combinedInput.value = dial + local;
			}
		} );
	}

	// =========================================================================
	// OTP page — resend countdown timer and submit handler.
	// =========================================================================

	const btn     = document.getElementById( 'kwtsms-resend-btn' );
	const msgSpan = document.getElementById( 'kwtsms-resend-msg' );
	const form    = document.getElementById( 'kwtsms-otp-form' );

	if ( ! btn ) { return; }

	const cooldown = parseInt( btn.dataset.cooldown, 10 ) || 60;
	let timer      = cooldown;
	let interval   = null;

	/**
	 * Start the resend cooldown countdown.
	 */
	function startCountdown() {
		btn.disabled = true;
		timer = cooldown;
		updateBtnText();

		interval = setInterval( function () {
			timer--;
			if ( timer <= 0 ) {
				clearInterval( interval );
				btn.disabled = false;
				btn.textContent = getLabel( 'Resend code' );
			} else {
				updateBtnText();
			}
		}, 1000 );
	}

	function updateBtnText() {
		btn.textContent = getLabel( 'Resend code' ) + ' (' + timer + ')';
	}

	function getLabel( base ) {
		return base;
	}

	// Start countdown immediately on page load.
	startCountdown();

	/**
	 * Prevent double-submission of the OTP form.
	 */
	if ( form ) {
		form.addEventListener( 'submit', function () {
			const submitBtn = form.querySelector( '[type="submit"]' );
			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.value = 'Verifying…';
			}
		} );
	}

	/**
	 * Auto-focus: when 6 (or 4) digits entered, optionally auto-submit.
	 */
	const codeInput = document.getElementById( 'kwtsms_code' );
	if ( codeInput ) {
		const maxLen = parseInt( codeInput.getAttribute( 'maxlength' ), 10 ) || 6;

		codeInput.addEventListener( 'input', function () {
			// Only allow digits.
			this.value = this.value.replace( /\D/g, '' );

			// Auto-submit when correct length is reached.
			if ( this.value.length === maxLen && form ) {
				form.submit();
			}
		} );
	}

	/**
	 * Resend button click — AJAX request.
	 */
	btn.addEventListener( 'click', function () {
		const ajaxUrl = btn.dataset.ajax;
		const nonce   = btn.dataset.nonce;
		const token   = btn.dataset.token;

		if ( ! ajaxUrl ) { return; }

		btn.disabled = true;
		if ( msgSpan ) { msgSpan.textContent = ''; }

		const body = new URLSearchParams( {
			action: 'kwtsms_resend_otp',
			nonce:  nonce,
			token:  token,
		} );

		fetch( ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( resp ) {
			if ( resp.success ) {
				if ( msgSpan ) {
					msgSpan.textContent = resp.data && resp.data.message
						? resp.data.message
						: 'A new code has been sent.';
					msgSpan.style.color = '#46b450';
				}
				startCountdown();
			} else {
				const msg = resp.data && resp.data.message ? resp.data.message : 'Could not resend. Please try again.';
				if ( msgSpan ) {
					msgSpan.textContent = msg;
					msgSpan.style.color = '#dc3232';
				}
				btn.disabled = false;
				btn.textContent = getLabel( 'Resend code' );
			}
		} )
		.catch( function () {
			if ( msgSpan ) {
				msgSpan.textContent = 'Network error. Please try again.';
				msgSpan.style.color = '#dc3232';
			}
			btn.disabled = false;
			btn.textContent = getLabel( 'Resend code' );
		} );
	} );

} )();
