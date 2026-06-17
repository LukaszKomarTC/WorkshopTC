<?php
/**
 * Bike custom post type (tcw_bike).
 *
 * Holds both customer and fleet bikes, distinguished by the bike_type meta.
 * M0 registers the post type and its capabilities only; meta boxes, fields,
 * ID and QR generation arrive in M1.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the tcw_bike post type.
 */
class Bike_CPT {

	const POST_TYPE = 'tcw_bike';

	/**
	 * Singleton instance.
	 *
	 * @var Bike_CPT|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Bike_CPT
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
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Bikes', 'post type general name', 'tossa-workshop' ),
			'singular_name'         => _x( 'Bike', 'post type singular name', 'tossa-workshop' ),
			'menu_name'             => _x( 'Bikes', 'admin menu', 'tossa-workshop' ),
			'add_new'               => __( 'Add New', 'tossa-workshop' ),
			'add_new_item'          => __( 'Add New Bike', 'tossa-workshop' ),
			'edit_item'             => __( 'Edit Bike', 'tossa-workshop' ),
			'new_item'              => __( 'New Bike', 'tossa-workshop' ),
			'view_item'             => __( 'View Bike', 'tossa-workshop' ),
			'search_items'          => __( 'Search Bikes', 'tossa-workshop' ),
			'not_found'             => __( 'No bikes found', 'tossa-workshop' ),
			'not_found_in_trash'    => __( 'No bikes found in Trash', 'tossa-workshop' ),
			'all_items'             => __( 'All Bikes', 'tossa-workshop' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 26,
			'menu_icon'           => 'dashicons-bank', // Replaced with a bike-appropriate icon in M1.
			'capability_type'     => array( 'tcw_bike', 'tcw_bikes' ),
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
		);

		/**
		 * Filter the tcw_bike post type registration args.
		 *
		 * @param array $args Registration arguments.
		 */
		$args = apply_filters( 'tcw_bike_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );
	}
}
