<?php
/**
 * Intake POST handlers (admin-post, logged-in staff).
 *
 * Phase 1: create a repair job from an existing bike. New-bike creation arrives
 * in Phase 2. Handlers run before output so the PRG redirect works.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Processes intake form submissions.
 */
class Intake_Actions {

	/**
	 * Singleton instance.
	 *
	 * @var Intake_Actions|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Intake_Actions
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
		add_action( 'admin_post_tcw_intake_create_job', array( $this, 'create_job' ) );
	}

	/**
	 * Create a repair job from an existing bike, then redirect to confirmation.
	 *
	 * @return void
	 */
	public function create_job() {
		$bike_id = isset( $_POST['bike_id'] ) ? absint( wp_unslash( $_POST['bike_id'] ) ) : 0;

		if ( ! $bike_id || ! isset( $_POST['tcw_intake_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tcw_intake_nonce'] ) ), 'tcw_intake_job_' . $bike_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'tossa-workshop' ) );
		}
		if ( ! current_user_can( 'tcw_edit_jobs' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tossa-workshop' ) );
		}
		if ( Bike_CPT::POST_TYPE !== get_post_type( $bike_id ) ) {
			wp_die( esc_html__( 'Bike not found.', 'tossa-workshop' ) );
		}

		$problem    = isset( $_POST['problem_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['problem_description'] ) ) : '';
		$send_email = ! empty( $_POST['send_email'] );

		$job_id = Job_Factory::create_for_bike( $bike_id, $problem, 'received', $send_email );
		if ( ! $job_id ) {
			wp_die( esc_html__( 'Could not create the repair job.', 'tossa-workshop' ) );
		}

		// Persist the extra intake fields via the same sanitization as admin.
		$defs = Repair_Job_Fields::all_fields();
		foreach ( array( 'bike_condition_on_arrival', 'priority', 'promised_completion', 'assigned_mechanic' ) as $key ) {
			if ( isset( $defs[ $key ], $_POST[ $key ] ) ) {
				update_post_meta( $job_id, $key, Field_Kit::sanitize( $defs[ $key ], wp_unslash( $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitized — sanitized by Field_Kit.
			}
		}

		$internal = get_post_meta( $bike_id, 'internal_id', true );
		wp_safe_redirect(
			Intake_Page::url(
				array(
					'bike'    => $internal,
					'created' => $job_id,
				)
			)
		);
		exit;
	}
}
