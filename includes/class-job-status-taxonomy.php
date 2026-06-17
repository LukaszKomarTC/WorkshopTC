<?php
/**
 * Job status taxonomy (tcw_job_status).
 *
 * Non-hierarchical taxonomy attached to tcw_repair_job. Current status is the
 * single assigned term; the full audit trail lives in the status_history meta
 * (added in M2). Term slugs are fixed by the spec.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the tcw_job_status taxonomy and seeds its fixed terms.
 */
class Job_Status_Taxonomy {

	const TAXONOMY = 'tcw_job_status';

	/**
	 * Singleton instance.
	 *
	 * @var Job_Status_Taxonomy|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Job_Status_Taxonomy
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Ordered status term slugs. Order reflects the linear pipeline followed
	 * by the two side-exit statuses.
	 *
	 * @return string[]
	 */
	public static function status_slugs() {
		return array(
			'received',
			'inspected',
			'waiting_approval',
			'approved',
			'waiting_parts',
			'parts_arrived',
			'in_repair',
			'quality_check',
			'ready',
			'delivered',
			'closed',
			'declined',
			'cancelled',
		);
	}

	/**
	 * The linear pipeline statuses in order (excludes the side-exit statuses
	 * declined/cancelled). Used for status-transition gating.
	 *
	 * @return string[]
	 */
	public static function linear_statuses() {
		return array(
			'received',
			'inspected',
			'waiting_approval',
			'approved',
			'waiting_parts',
			'parts_arrived',
			'in_repair',
			'quality_check',
			'ready',
			'delivered',
			'closed',
		);
	}

	/**
	 * Default human-readable label per status slug.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels() {
		return array(
			'received'         => __( 'Received', 'tossa-workshop' ),
			'inspected'        => __( 'Inspected', 'tossa-workshop' ),
			'waiting_approval' => __( 'Waiting for Approval', 'tossa-workshop' ),
			'approved'         => __( 'Approved', 'tossa-workshop' ),
			'waiting_parts'    => __( 'Waiting for Parts', 'tossa-workshop' ),
			'parts_arrived'    => __( 'Parts Arrived', 'tossa-workshop' ),
			'in_repair'        => __( 'In Repair', 'tossa-workshop' ),
			'quality_check'    => __( 'Quality Check', 'tossa-workshop' ),
			'ready'            => __( 'Ready for Pickup', 'tossa-workshop' ),
			'delivered'        => __( 'Delivered', 'tossa-workshop' ),
			'closed'           => __( 'Closed', 'tossa-workshop' ),
			'declined'         => __( 'Declined', 'tossa-workshop' ),
			'cancelled'        => __( 'Cancelled', 'tossa-workshop' ),
		);
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
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register() {
		$labels = array(
			'name'          => _x( 'Job Statuses', 'taxonomy general name', 'tossa-workshop' ),
			'singular_name' => _x( 'Job Status', 'taxonomy singular name', 'tossa-workshop' ),
			'menu_name'     => __( 'Statuses', 'tossa-workshop' ),
			'all_items'     => __( 'All Statuses', 'tossa-workshop' ),
			'edit_item'     => __( 'Edit Status', 'tossa-workshop' ),
			'update_item'   => __( 'Update Status', 'tossa-workshop' ),
			'add_new_item'  => __( 'Add New Status', 'tossa-workshop' ),
			'search_items'  => __( 'Search Statuses', 'tossa-workshop' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_admin_column'  => false, // Custom column added in M5.
			'hierarchical'       => false,
			'show_in_rest'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			// Managed by code, not by hand-editing terms.
			'capabilities'       => array(
				'manage_terms' => 'tcw_change_status',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'tcw_change_status',
			),
		);

		/**
		 * Filter the tcw_job_status taxonomy registration args.
		 *
		 * @param array $args Registration arguments.
		 */
		$args = apply_filters( 'tcw_job_status_taxonomy_args', $args );

		register_taxonomy( self::TAXONOMY, array( Repair_Job_CPT::POST_TYPE ), $args );
	}

	/**
	 * Ensure all fixed status terms exist. Idempotent; safe to call on every
	 * activation. The taxonomy must already be registered before calling.
	 *
	 * @return void
	 */
	public static function insert_terms() {
		$labels = self::status_labels();
		foreach ( self::status_slugs() as $slug ) {
			if ( term_exists( $slug, self::TAXONOMY ) ) {
				continue;
			}
			$label = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
			wp_insert_term( $label, self::TAXONOMY, array( 'slug' => $slug ) );
		}
	}
}
