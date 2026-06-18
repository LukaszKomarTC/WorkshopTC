<?php
/**
 * Bikes admin list table: columns, multi-field search, type filter (§10).
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop\Admin;

use TossaWorkshop\Bike_CPT;
use TossaWorkshop\Repair_Job_CPT;
use TossaWorkshop\Bike_Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Customises the bike list screen.
 */
class Bikes_List {

	/** Meta keys the admin search box matches against. */
	const SEARCH_KEYS = array( 'internal_id', 'serial_number', 'owner_name', 'owner_email', 'owner_phone' );

	/**
	 * Singleton instance.
	 *
	 * @var Bikes_List|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Bikes_List
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
		$pt = Bike_CPT::POST_TYPE;
		add_filter( "manage_{$pt}_posts_columns", array( $this, 'columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_query' ) );
	}

	/**
	 * Define columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$cb = isset( $columns['cb'] ) ? array( 'cb' => $columns['cb'] ) : array();
		return $cb + array(
			'tcw_internal_id' => __( 'Internal ID', 'tossa-workshop' ),
			'tcw_owner'       => __( 'Owner', 'tossa-workshop' ),
			'tcw_brand_model' => __( 'Brand / Model', 'tossa-workshop' ),
			'tcw_type'        => __( 'Type', 'tossa-workshop' ),
			'tcw_category'    => __( 'Category', 'tossa-workshop' ),
			'tcw_size'        => __( 'Size', 'tossa-workshop' ),
			'tcw_serial'      => __( 'Serial', 'tossa-workshop' ),
			'tcw_last_svc'    => __( 'Last service', 'tossa-workshop' ),
			'tcw_repairs'     => __( '# Repairs', 'tossa-workshop' ),
		);
	}

	/**
	 * Render a custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Bike ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'tcw_internal_id':
				echo '<strong><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '"><code>' . esc_html( get_post_meta( $post_id, 'internal_id', true ) ) . '</code></a></strong>';
				break;

			case 'tcw_owner':
				$owner = get_post_meta( $post_id, 'owner_name', true );
				if ( ! $owner ) {
					$uid   = (int) get_post_meta( $post_id, 'owner_user_id', true );
					$user  = $uid ? get_userdata( $uid ) : null;
					$owner = $user ? $user->display_name : '';
				}
				echo esc_html( $owner ?: '—' );
				break;

			case 'tcw_brand_model':
				echo esc_html( trim( get_post_meta( $post_id, 'brand', true ) . ' ' . get_post_meta( $post_id, 'model', true ) ) ?: '—' );
				break;

			case 'tcw_type':
				$type = get_post_meta( $post_id, 'bike_type', true );
				echo esc_html( 'fleet' === $type ? __( 'Fleet', 'tossa-workshop' ) : ( 'customer' === $type ? __( 'Customer', 'tossa-workshop' ) : '—' ) );
				break;

			case 'tcw_category':
				$opts = Bike_Fields::option_sets()['category'];
				$val  = get_post_meta( $post_id, 'category', true );
				echo esc_html( $val && isset( $opts[ $val ] ) ? $opts[ $val ] : '—' );
				break;

			case 'tcw_size':
				echo esc_html( get_post_meta( $post_id, 'frame_size', true ) ?: '—' );
				break;

			case 'tcw_serial':
				echo esc_html( get_post_meta( $post_id, 'serial_number', true ) ?: '—' );
				break;

			case 'tcw_last_svc':
				echo esc_html( get_post_meta( $post_id, 'last_service_date', true ) ?: '—' );
				break;

			case 'tcw_repairs':
				echo (int) $this->repair_count( $post_id );
				break;
		}
	}

	/**
	 * Render the bike-type filter.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function filters( $post_type ) {
		if ( Bike_CPT::POST_TYPE !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only filter.
		$cur = isset( $_GET['tcw_bike_type'] ) ? sanitize_text_field( wp_unslash( $_GET['tcw_bike_type'] ) ) : '';
		echo '<select name="tcw_bike_type"><option value="">' . esc_html__( 'All types', 'tossa-workshop' ) . '</option>';
		foreach ( array( 'customer' => __( 'Customer', 'tossa-workshop' ), 'fleet' => __( 'Fleet', 'tossa-workshop' ) ) as $val => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $val ), selected( $cur, $val, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Apply the multi-field search and type filter.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function apply_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Bike_CPT::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = array();

		// Multi-field search: match internal_id / serial / owner fields. Replace
		// the default title/content search so owner email & phone are covered.
		$search = trim( (string) $query->get( 's' ) );
		if ( '' !== $search ) {
			$query->set( 's', '' );
			$or = array( 'relation' => 'OR' );
			foreach ( self::SEARCH_KEYS as $key ) {
				$or[] = array(
					'key'     => $key,
					'value'   => $search,
					'compare' => 'LIKE',
				);
			}
			$meta_query[] = $or;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only filter.
		$type = isset( $_GET['tcw_bike_type'] ) ? sanitize_text_field( wp_unslash( $_GET['tcw_bike_type'] ) ) : '';
		if ( 'customer' === $type || 'fleet' === $type ) {
			$meta_query[] = array(
				'key'   => 'bike_type',
				'value' => $type,
			);
		}

		if ( $meta_query ) {
			$existing = (array) $query->get( 'meta_query' );
			$query->set( 'meta_query', array_merge( $existing, $meta_query ) );
		}
	}

	/**
	 * Count repair jobs linked to a bike.
	 *
	 * @param int $bike_id Bike ID.
	 * @return int
	 */
	private function repair_count( $bike_id ) {
		$q = new \WP_Query(
			array(
				'post_type'      => Repair_Job_CPT::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'bike_id',
						'value' => $bike_id,
					),
				),
			)
		);
		return (int) $q->found_posts;
	}
}
