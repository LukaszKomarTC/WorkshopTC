<?php
/**
 * AJAX bike search for the repair-job bike picker.
 *
 * Searches by internal_id, serial number and owner name/email/phone so staff
 * can find a bike without knowing its internal ID. Lookup is keyed on meta,
 * never on the manufacturer serial alone.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Provides wp_ajax_tcw_bike_search.
 */
class Bike_Search {

	const ACTION = 'tcw_bike_search';

	/**
	 * Singleton instance.
	 *
	 * @var Bike_Search|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Bike_Search
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'search' ) );
	}

	/**
	 * Handle the AJAX search request.
	 *
	 * @return void
	 */
	public function search() {
		check_ajax_referer( self::ACTION, 'nonce' );

		if ( ! current_user_can( 'tcw_view_bikes' ) ) {
			wp_send_json_error( array(), 403 );
		}

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json( array() );
		}

		$meta_query = array( 'relation' => 'OR' );
		foreach ( array( 'internal_id', 'serial_number', 'owner_name', 'owner_email', 'owner_phone' ) as $key ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $term,
				'compare' => 'LIKE',
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Bike_CPT::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 20,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		$results = array();
		foreach ( $query->posts as $bike ) {
			$internal_id = get_post_meta( $bike->ID, 'internal_id', true );
			$brand       = get_post_meta( $bike->ID, 'brand', true );
			$model       = get_post_meta( $bike->ID, 'model', true );
			$owner       = get_post_meta( $bike->ID, 'owner_name', true );

			$label = trim( $internal_id . ' — ' . trim( $brand . ' ' . $model ) );
			if ( $owner ) {
				$label .= ' (' . $owner . ')';
			}

			$results[] = array(
				'id'          => $bike->ID,
				'internal_id' => $internal_id,
				'label'       => $label,
				// jQuery UI autocomplete uses `value` for the input on focus/select.
				'value'       => $label,
			);
		}

		wp_send_json( $results );
	}
}
