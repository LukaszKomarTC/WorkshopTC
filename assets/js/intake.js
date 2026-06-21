/* Workshop intake — bike search (vanilla JS, no jQuery). */
( function () {
	'use strict';

	var cfg = window.tcwIntake || {};
	var input = document.getElementById( 'tcw-q' );
	var list = document.getElementById( 'tcw-results' );
	if ( ! input || ! list || ! cfg.ajaxUrl ) {
		return;
	}

	var timer = null;

	function clear() {
		list.innerHTML = '';
	}

	function go( internalId ) {
		window.location.href = cfg.intakeUrl + '?bike=' + encodeURIComponent( internalId );
	}

	function render( items ) {
		clear();
		items.forEach( function ( item ) {
			var li = document.createElement( 'li' );
			li.textContent = item.label;
			li.addEventListener( 'click', function () {
				go( item.internal_id || '' );
			} );
			list.appendChild( li );
		} );
	}

	function search( term ) {
		var url = cfg.ajaxUrl + '?action=' + encodeURIComponent( cfg.action ) +
			'&nonce=' + encodeURIComponent( cfg.nonce ) +
			'&q=' + encodeURIComponent( term );

		fetch( url, { credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) { render( Array.isArray( data ) ? data : [] ); } )
			.catch( function () { clear(); } );
	}

	input.addEventListener( 'input', function () {
		var term = input.value.trim();
		if ( timer ) {
			clearTimeout( timer );
		}
		if ( term.length < 2 ) {
			clear();
			return;
		}
		timer = setTimeout( function () { search( term ); }, 250 );
	} );

	// Enter on an exact-looking internal ID jumps straight to it.
	input.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			var term = input.value.trim();
			if ( term ) {
				go( term );
			}
		}
	} );
} )();
