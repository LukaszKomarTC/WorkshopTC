<?php
/**
 * Shared admin asset enqueueing.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for enqueuing assets used by more than one screen.
 */
class Assets {

	/**
	 * Enqueue the shared media-picker/gallery script with its strings.
	 * Safe to call more than once per request.
	 *
	 * @return void
	 */
	public static function enqueue_media_js() {
		if ( wp_script_is( 'tcw-admin-media', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script( 'tcw-admin-media', TCW_PLUGIN_URL . 'assets/js/admin-media.js', array( 'jquery' ), TCW_VERSION, true );
		wp_localize_script(
			'tcw-admin-media',
			'tcwMedia',
			array(
				'selectImage' => __( 'Select image', 'tossa-workshop' ),
				'remove'      => __( 'Remove', 'tossa-workshop' ),
				'label'       => __( 'Label', 'tossa-workshop' ),
				'addPhotos'   => __( 'Add photos', 'tossa-workshop' ),
			)
		);
	}
}
