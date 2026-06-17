/* global jQuery */
( function ( $ ) {
	'use strict';

	/**
	 * Show/hide fleet- and customer-only meta boxes based on bike type.
	 */
	function applyBikeType() {
		var $sel = $( '.tcw-bike-type' );
		// If the type selector is hidden (e.g. via Screen Options), leave all
		// sections visible rather than wrongly hiding fleet/customer fields.
		if ( ! $sel.length ) {
			return;
		}
		var isFleet = 'fleet' === ( $sel.val() || '' );

		$( '.tcw-only-fleet' ).toggle( isFleet );
		$( '.tcw-only-customer' ).toggle( ! isFleet );
	}

	$( function () {
		applyBikeType();
		$( document ).on( 'change', '.tcw-bike-type', applyBikeType );
	} );
} )( jQuery );
