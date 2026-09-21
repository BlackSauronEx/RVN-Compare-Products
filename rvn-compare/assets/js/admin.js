/**
 * RVN Compare — скрипты админки (ванильный JS).
 */
( function () {
	'use strict';

	function init() {
		// Color picker на полях с классом .rvn-compare-color.
		var fields = document.querySelectorAll( '.rvn-compare-color' );
		if ( fields.length && window.wp && window.wp.colorPicker ) {
			for ( var i = 0; i < fields.length; i++ ) {
				window.wp.colorPicker( fields[ i ] );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
