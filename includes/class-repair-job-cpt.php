<?php
/**
 * Repair Job custom post type (tcw_repair_job).
 *
 * M0 registers the post type, its capabilities and attaches the status
 * taxonomy. Meta boxes, bike picker, intake/inspection/work fields, status
 * history and the status-changed action arrive in M2.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the tcw_repair_job post type.
 */
class Repair_Job_CPT {

	const POST_TYPE = 'tcw_repair_job';

	/**
	 * Singleton instance.
	 *
	 * @var Repair_Job_CPT|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Repair_Job_CPT
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
			'name'               => _x( 'Repair Jobs', 'post type general name', 'tossa-workshop' ),
			'singular_name'      => _x( 'Repair Job', 'post type singular name', 'tossa-workshop' ),
			'menu_name'          => _x( 'Repair Jobs', 'admin menu', 'tossa-workshop' ),
			'add_new'            => __( 'Add New', 'tossa-workshop' ),
			'add_new_item'       => __( 'Add New Repair Job', 'tossa-workshop' ),
			'edit_item'          => __( 'Edit Repair Job', 'tossa-workshop' ),
			'new_item'           => __( 'New Repair Job', 'tossa-workshop' ),
			'view_item'          => __( 'View Repair Job', 'tossa-workshop' ),
			'search_items'       => __( 'Search Repair Jobs', 'tossa-workshop' ),
			'not_found'          => __( 'No repair jobs found', 'tossa-workshop' ),
			'not_found_in_trash' => __( 'No repair jobs found in Trash', 'tossa-workshop' ),
			'all_items'          => __( 'All Repair Jobs', 'tossa-workshop' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 27,
			'menu_icon'           => 'dashicons-hammer',
			'capability_type'     => array( 'tcw_repair_job', 'tcw_repair_jobs' ),
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'taxonomies'          => array( Job_Status_Taxonomy::TAXONOMY ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
		);

		/**
		 * Filter the tcw_repair_job post type registration args.
		 *
		 * @param array $args Registration arguments.
		 */
		$args = apply_filters( 'tcw_repair_job_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );
	}
}
