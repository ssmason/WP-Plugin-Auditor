/* Plugin Auditor — admin tools page (accordion + reset defaults). */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Accordion toggles.
		document.querySelectorAll( '.pla-accordion-toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var expanded = 'true' === btn.getAttribute( 'aria-expanded' );
				btn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				var body = btn.nextElementSibling;
				if ( body ) {
					body.hidden = expanded;
				}
			} );
		} );

		// Reset to defaults — tick only the default-ON checkboxes.
		var resetBtn = document.querySelector( '.pla-reset-defaults' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				document.querySelectorAll( '.pla-checks-form input[type="checkbox"]' ).forEach( function ( cb ) {
					var row = cb.closest( '.pla-check-row' );
					var badge = row ? row.querySelector( '.pla-badge' ) : null;
					cb.checked = badge ? badge.classList.contains( 'pla-badge--on' ) : false;
				} );
			} );
		}
	} );
}() );
