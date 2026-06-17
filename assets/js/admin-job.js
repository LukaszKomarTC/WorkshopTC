/* global jQuery, tcwJob */
( function ( $ ) {
	'use strict';

	$( function () {
		var $search = $( '#tcw_bike_search' );
		var $id = $( '#tcw_bike_id' );
		var $clear = $( '.tcw-bike-clear' );
		var $selected = $( '.tcw-bike-selected' );

		if ( ! $search.length || ! $.fn.autocomplete ) {
			return;
		}

		$search.autocomplete( {
			minLength: 2,
			source: function ( request, response ) {
				$.getJSON( tcwJob.ajaxUrl, {
					action: tcwJob.action,
					nonce: tcwJob.searchNonce,
					q: request.term
				} ).done( function ( data ) {
					response( $.isArray( data ) ? data : [] );
				} ).fail( function () {
					response( [] );
				} );
			},
			select: function ( event, ui ) {
				$id.val( ui.item.id );
				$search.val( ui.item.label );
				$selected.text( tcwJob.selected + ' ' + ui.item.label );
				$clear.show();
				return false;
			}
		} );

		$clear.on( 'click', function ( e ) {
			e.preventDefault();
			$id.val( '' );
			$search.val( '' );
			$selected.text( '' );
			$( this ).hide();
		} );
	} );
} )( jQuery );
