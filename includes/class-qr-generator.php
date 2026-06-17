<?php
/**
 * QR code generation and storage.
 *
 * Wraps the bundled chillerlan QR library so the rest of the plugin depends on
 * this class, not the library. Builds the scan URL, renders a PNG and stores
 * it in the media library attached to the bike.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use TossaWorkshop\Admin\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Generates QR PNGs and stores them as attachments.
 */
class QR_Generator {

	/**
	 * Build the public base URL used for scan / status links.
	 *
	 * Defaults to home_url(); overridable via the public_base_url setting so
	 * QR codes survive a domain change without reprinting.
	 *
	 * @return string Base URL without trailing slash.
	 */
	public static function base_url() {
		$settings = Settings_Page::get_settings();
		$base     = isset( $settings['public_base_url'] ) ? trim( (string) $settings['public_base_url'] ) : '';
		if ( '' === $base ) {
			$base = home_url();
		}
		return untrailingslashit( $base );
	}

	/**
	 * The scan URL for a bike's internal ID.
	 *
	 * @param string $internal_id Bike internal ID.
	 * @return string
	 */
	public static function scan_url( $internal_id ) {
		return self::base_url() . '/workshop-scan/?id=' . rawurlencode( $internal_id );
	}

	/**
	 * Render QR PNG bytes for arbitrary text.
	 *
	 * @param string $text  Payload.
	 * @param int    $scale Module scale.
	 * @return string|null PNG bytes, or null on failure.
	 */
	public static function png_bytes( $text, $scale = 8 ) {
		try {
			$options = new QROptions(
				array(
					'version'      => QRCode::VERSION_AUTO,
					'outputType'   => QROutputInterface::GDIMAGE_PNG,
					'eccLevel'     => EccLevel::M,
					'scale'        => (int) $scale,
					'outputBase64' => false,
					'imageBase64'  => false,
					'quietzoneSize' => 2,
				)
			);
			$png = ( new QRCode( $options ) )->render( $text );
			return is_string( $png ) ? $png : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Generate and store the QR image for a bike, returning the attachment ID.
	 *
	 * @param int    $bike_id     Bike post ID.
	 * @param string $internal_id Bike internal ID.
	 * @return int|\WP_Error Attachment ID on success.
	 */
	public static function generate_for_bike( $bike_id, $internal_id ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$png = self::png_bytes( self::scan_url( $internal_id ) );
		if ( null === $png ) {
			return new \WP_Error( 'tcw_qr_render_failed', __( 'Could not render the QR image.', 'tossa-workshop' ) );
		}

		$filename = 'qr-' . sanitize_file_name( $internal_id ) . '.png';
		$upload   = wp_upload_bits( $filename, null, $png );
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'tcw_qr_upload_failed', $upload['error'] );
		}

		$attachment = array(
			'post_mime_type' => 'image/png',
			'post_title'     => sprintf(
				/* translators: %s: bike internal ID */
				__( 'QR code %s', 'tossa-workshop' ),
				$internal_id
			),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $bike_id );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			// Avoid leaving an orphaned file in the uploads directory.
			wp_delete_file( $upload['file'] );
			return new \WP_Error( 'tcw_qr_attach_failed', __( 'Could not store the QR attachment.', 'tossa-workshop' ) );
		}

		$meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $meta );

		return (int) $attach_id;
	}
}
