<?php
/**
 * Programmatic bike creation with ID + QR (mirror of Job_Factory).
 *
 * Bikes created through the admin screen get their internal_id and QR via the
 * save_post path. Code paths that create a bike programmatically (the intake
 * module) must go through here so the bike is complete: sanitized meta (same
 * rules as the admin form, via Field_Kit + Bike_Fields), a generated internal
 * ID, a stored QR image and a readable title.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Creates bikes outside the admin form.
 */
class Bike_Factory {

	/**
	 * Meta keys accepted from $args (each sanitized via its Bike_Fields def).
	 *
	 * @var string[]
	 */
	const ACCEPTED = array(
		'bike_type',
		'brand',
		'model',
		'serial_number',
		'secondary_frame_number',
		'owner_name',
		'owner_email',
		'owner_phone',
		'owner_language',
		'category',
		'frame_size',
		'color',
		'rental_category',
		'fleet_number',
	);

	/**
	 * Create a bike. Returns the post ID, or 0 on failure.
	 *
	 * @param array $args Field values (see ACCEPTED) plus optional gdpr_consent.
	 * @return int
	 */
	public static function create( array $args ) {
		$type = isset( $args['bike_type'] ) ? $args['bike_type'] : '';
		if ( ! in_array( $type, array( 'customer', 'fleet' ), true ) ) {
			// Never mint a bike with an invalid/missing type (avoids wrong ID).
			return 0;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Bike_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => '',
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$meta = self::prepare_meta( $args );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// GDPR consent + timestamp when given.
		if ( ! empty( $args['gdpr_consent'] ) ) {
			update_post_meta( $post_id, 'gdpr_consent', '1' );
			update_post_meta( $post_id, 'gdpr_consent_timestamp', current_time( 'mysql' ) );
		}

		// Internal ID (immutable thereafter).
		$rental_cat  = isset( $meta['rental_category'] ) ? $meta['rental_category'] : '';
		$internal_id = ID_Generator::generate_bike_id( $type, $rental_cat );
		update_post_meta( $post_id, 'internal_id', $internal_id );

		// QR image.
		$qr = QR_Generator::generate_for_bike( $post_id, $internal_id );
		if ( ! is_wp_error( $qr ) ) {
			update_post_meta( $post_id, 'qr_attachment_id', $qr );
		}

		// Readable title.
		$name  = trim( ( isset( $meta['brand'] ) ? $meta['brand'] : '' ) . ' ' . ( isset( $meta['model'] ) ? $meta['model'] : '' ) );
		$title = '' !== $name ? $name . ' (' . $internal_id . ')' : $internal_id;
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);

		return (int) $post_id;
	}

	/**
	 * Sanitize the accepted args into a bike meta map, using the same rules as
	 * the admin form (Field_Kit + Bike_Fields). Pure: no DB writes.
	 *
	 * @param array $args Raw field values.
	 * @return array<string,mixed>
	 */
	public static function prepare_meta( array $args ) {
		$defs = Bike_Fields::all_fields();
		$out  = array();

		foreach ( self::ACCEPTED as $key ) {
			if ( ! array_key_exists( $key, $args ) || ! isset( $defs[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = Field_Kit::sanitize( $defs[ $key ], $args[ $key ] );
		}

		// Owner language defaults to Spanish when absent/empty.
		if ( ! isset( $out['owner_language'] ) || '' === $out['owner_language'] ) {
			$out['owner_language'] = 'es';
		}

		return $out;
	}
}
