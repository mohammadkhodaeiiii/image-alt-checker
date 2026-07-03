/**
 * Image Alt Checker - general admin behaviour.
 *
 * Vanilla JavaScript, no dependencies. Adds a confirmation prompt to
 * destructive actions such as resetting the settings.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var confirmables = document.querySelectorAll( '[data-iac-confirm]' );

		confirmables.forEach( function ( element ) {
			element.addEventListener( 'click', function ( event ) {
				var message = element.getAttribute( 'data-iac-confirm' );

				if ( message && ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
}() );
