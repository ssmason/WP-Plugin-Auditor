/* global plaAuditor, wp */
/* Plugin Auditor — Modal, AJAX, Progress, Print */

( function ( $ ) {
	'use strict';

	const modal        = document.getElementById( 'pla-modal' );
	const progressEl   = modal.querySelector( '.pla-modal__progress' );
	const progressFill = modal.querySelector( '.pla-progress-bar__fill' );
	const progressText = modal.querySelector( '.pla-modal__progress-text' );
	const progressHint = modal.querySelector( '.pla-modal__progress-hint' );
	const errorEl      = modal.querySelector( '.pla-modal__error' );
	const errorText    = modal.querySelector( '.pla-modal__error-text' );
	const reportEl     = modal.querySelector( '.pla-modal__report' );
	const footerEl     = modal.querySelector( '.pla-modal__footer' );
	const closeBtn     = modal.querySelector( '.pla-modal__close' );
	const retryBtn     = modal.querySelector( '.pla-modal__retry' );
	const printBtn     = modal.querySelector( '.pla-modal__print' );

	let triggerElement = null;
	let currentPlugin  = null;
	let currentNonce   = null;
	let activeRequest  = null;

	// -------------------------------------------------------------------------
	// Modal open / close
	// -------------------------------------------------------------------------

	function openModal() {
		modal.removeAttribute( 'hidden' );
		closeBtn.focus();
		trapFocus( modal );
	}

	function closeModal() {
		modal.setAttribute( 'hidden', '' );
		removeFocusTrap();
		if ( triggerElement ) {
			triggerElement.focus();
			triggerElement = null;
		}
		if ( activeRequest ) {
			activeRequest.abort();
			activeRequest = null;
		}
	}

	function resetModal() {
		progressEl.hidden         = true;
		errorEl.hidden            = true;
		reportEl.hidden           = true;
		footerEl.hidden           = true;
		reportEl.textContent      = '';
		errorText.textContent     = '';
		// Reset progress bar so animation restarts cleanly next time.
		progressFill.style.animation = 'none';
		progressFill.style.width     = '0%';
		void progressFill.offsetWidth; // eslint-disable-line no-void
	}

	function showProgress() {
		resetModal();
		progressFill.style.animation = '';
		progressEl.hidden = false;
	}

	function completeProgress( callback ) {
		progressFill.style.animation = 'none';
		progressFill.style.width     = '100%';
		progressText.textContent     = plaAuditor.i18n.complete;
		progressHint.textContent     = '';
		setTimeout( callback, 600 );
	}

	function showError( message ) {
		progressEl.hidden         = true;
		reportEl.hidden           = true;
		footerEl.hidden           = true;
		errorText.textContent     = message;
		errorEl.hidden            = false;
	}

	function showReport( html ) {
		completeProgress( function () {
			progressEl.hidden  = true;
			errorEl.hidden     = true;
			reportEl.innerHTML = html;
			reportEl.hidden    = false;
			footerEl.hidden    = false;
		} );
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	function runAudit( pluginFile, nonce ) {
		currentPlugin = pluginFile;
		currentNonce  = nonce;

		showProgress();
		openModal();

		if ( activeRequest ) {
			activeRequest.abort();
		}

		activeRequest = $.ajax( {
			url:     plaAuditor.ajaxUrl,
			method:  'POST',
			timeout: plaAuditor.ajaxTimeout,
			data:    {
				action:      'pla_run_audit',
				plugin_file: pluginFile,
				nonce:       nonce,
			},
			success: function ( response ) {
				activeRequest = null;
				if ( response.success ) {
					showReport( response.data.html );
				} else {
					const msg = ( response.data && response.data.message )
						? response.data.message
						: plaAuditor.i18n.error;
					showError( msg );
				}
			},
			error: function ( jqXHR, textStatus ) {
				activeRequest = null;
				if ( 'abort' === textStatus ) {
					return;
				}
				const msg = ( 'timeout' === textStatus )
					? plaAuditor.i18n.timeout
					: plaAuditor.i18n.error;
				showError( msg );
			},
		} );
	}

	function loadReport( reportId, nonce ) {
		showProgress();
		openModal();

		if ( activeRequest ) {
			activeRequest.abort();
		}

		activeRequest = $.ajax( {
			url:     plaAuditor.ajaxUrl,
			method:  'POST',
			timeout: plaAuditor.ajaxTimeout,
			data:    {
				action:    'pla_get_report',
				report_id: reportId,
				nonce:     nonce,
			},
			success: function ( response ) {
				activeRequest = null;
				if ( response.success ) {
					showReport( response.data.html );
				} else {
					const msg = ( response.data && response.data.message )
						? response.data.message
						: plaAuditor.i18n.error;
					showError( msg );
				}
			},
			error: function ( jqXHR, textStatus ) {
				activeRequest = null;
				if ( 'abort' === textStatus ) {
					return;
				}
				showError( plaAuditor.i18n.error );
			},
		} );
	}

	// -------------------------------------------------------------------------
	// Focus trap
	// -------------------------------------------------------------------------

	const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function trapFocus( container ) {
		container.addEventListener( 'keydown', handleFocusTrap );
	}

	function removeFocusTrap() {
		modal.removeEventListener( 'keydown', handleFocusTrap );
	}

	function handleFocusTrap( e ) {
		if ( 'Escape' === e.key ) {
			closeModal();
			return;
		}

		if ( 'Tab' !== e.key ) {
			return;
		}

		const focusable = Array.from( modal.querySelectorAll( FOCUSABLE ) ).filter(
			( el ) => ! el.closest( '[hidden]' )
		);
		if ( 0 === focusable.length ) {
			e.preventDefault();
			return;
		}

		const first = focusable[ 0 ];
		const last  = focusable[ focusable.length - 1 ];

		if ( e.shiftKey ) {
			if ( document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			}
		} else {
			if ( document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	}

	// -------------------------------------------------------------------------
	// Event listeners
	// -------------------------------------------------------------------------

	closeBtn.addEventListener( 'click', closeModal );
	modal.querySelector( '.pla-modal__backdrop' ).addEventListener( 'click', closeModal );

	retryBtn.addEventListener( 'click', function () {
		if ( currentPlugin && currentNonce ) {
			runAudit( currentPlugin, currentNonce );
		}
	} );

	printBtn.addEventListener( 'click', function () {
		document.body.classList.add( 'pla-is-printing' );
		window.print();
		window.addEventListener( 'afterprint', function restoreAfterPrint() {
			document.body.classList.remove( 'pla-is-printing' );
			window.removeEventListener( 'afterprint', restoreAfterPrint );
		} );
	} );

	// Audit trigger links in plugin rows.
	document.addEventListener( 'click', function ( e ) {
		const trigger = e.target.closest( '.pla-audit-trigger' );
		if ( ! trigger ) {
			return;
		}
		e.preventDefault();
		triggerElement = trigger;
		const pluginFile = trigger.dataset.plugin;
		const nonce      = trigger.dataset.nonce;
		if ( pluginFile && nonce ) {
			runAudit( pluginFile, nonce );
		}
	} );

	// View previous report links.
	document.addEventListener( 'click', function ( e ) {
		const trigger = e.target.closest( '.pla-view-report' );
		if ( ! trigger ) {
			return;
		}
		e.preventDefault();
		triggerElement   = trigger;
		const reportId   = trigger.dataset.reportId;
		const nonce      = trigger.dataset.nonce;
		if ( reportId && nonce ) {
			loadReport( reportId, nonce );
		}
	} );

}( window.jQuery ) );
