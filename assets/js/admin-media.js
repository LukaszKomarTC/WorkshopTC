/* global jQuery, wp, tcwMedia */
( function ( $ ) {
	'use strict';

	var strings = window.tcwMedia || { selectImage: 'Select image', remove: 'Remove', label: 'Label', addPhotos: 'Add photos' };

	/**
	 * Single-image attachment picker.
	 */
	function initAttachmentPickers() {
		$( document ).on( 'click', '.tcw-attachment-select', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.tcw-attachment' );
			var frame = wp.media( {
				title: strings.selectImage,
				multiple: false,
				library: { type: 'image' }
			} );

			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var src = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
				$wrap.find( 'input[type=hidden]' ).val( att.id );
				$wrap.find( '.tcw-attachment-preview' ).html(
					$( '<img>' ).attr( 'src', src ).css( { maxWidth: '120px', height: 'auto', border: '1px solid #ddd' } )
				);
				$wrap.find( '.tcw-attachment-remove' ).show();
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.tcw-attachment-remove', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.tcw-attachment' );
			$wrap.find( 'input[type=hidden]' ).val( '' );
			$wrap.find( '.tcw-attachment-preview' ).empty();
			$( this ).hide();
		} );
	}

	/**
	 * Repeatable photo gallery.
	 */
	function initGallery() {
		$( document ).on( 'click', '.tcw-gallery-add', function ( e ) {
			e.preventDefault();
			var $btn = $( this );
			var name = $btn.data( 'name' );
			var $list = $btn.siblings( '.tcw-gallery-items' );

			var frame = wp.media( {
				title: strings.addPhotos,
				multiple: true,
				library: { type: 'image' }
			} );

			frame.on( 'select', function () {
				frame.state().get( 'selection' ).each( function ( attachment ) {
					var att = attachment.toJSON();
					var src = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
					// Monotonic index that never reuses a removed slot, so POST
					// keys can't collide and silently drop a photo.
					var i = parseInt( $list.attr( 'data-next-index' ), 10 );
					if ( isNaN( i ) ) {
						i = $list.children().length;
					}
					$list.attr( 'data-next-index', i + 1 );
					var $li = $( '<li class="tcw-gallery-item"></li>' );
					$li.append( $( '<input type="hidden">' ).attr( 'name', name + '[' + i + '][id]' ).val( att.id ) );
					$li.append( $( '<img>' ).attr( 'src', src ).css( { maxWidth: '90px', height: 'auto', border: '1px solid #ddd' } ) );
					$li.append( $( '<input type="text">' ).attr( 'name', name + '[' + i + '][label]' ).attr( 'placeholder', strings.label ) );
					$li.append( $( '<button type="button" class="button-link tcw-gallery-remove"></button>' ).text( strings.remove ) );
					$list.append( $li );
				} );
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.tcw-gallery-remove', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.tcw-gallery-item' ).remove();
		} );
	}

	$( function () {
		initAttachmentPickers();
		initGallery();
	} );
} )( jQuery );
