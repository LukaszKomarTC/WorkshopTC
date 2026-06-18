<?php
/**
 * Programmatic repair-job creation + client-contact inheritance.
 *
 * Shared by the job meta box (inherit on save) and the fleet quick-check
 * (spin up a linked job). Generates the job ID + client token, copies client
 * contact from the bike, and sets the initial status.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for creating jobs and inheriting client contact.
 */
class Job_Factory {

	/**
	 * Copy client contact from a job's linked bike onto the job once, so
	 * historical notifications stay correct even if the bike owner changes.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	public static function inherit_contact( $job_id ) {
		if ( get_post_meta( $job_id, 'client_contact_inherited', true ) ) {
			return;
		}
		$bike_id = (int) get_post_meta( $job_id, 'bike_id', true );
		if ( ! $bike_id ) {
			return;
		}

		$email = get_post_meta( $bike_id, 'owner_email', true );
		$phone = get_post_meta( $bike_id, 'owner_phone', true );
		$lang  = get_post_meta( $bike_id, 'owner_language', true );
		$name  = get_post_meta( $bike_id, 'owner_name', true );

		if ( ! $name ) {
			$user_id = (int) get_post_meta( $bike_id, 'owner_user_id', true );
			if ( $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user ) {
					$name  = $user->display_name;
					$email = $email ? $email : $user->user_email;
				}
			}
		}

		update_post_meta( $job_id, 'client_name', sanitize_text_field( $name ) );
		update_post_meta( $job_id, 'client_email', sanitize_email( $email ) );
		update_post_meta( $job_id, 'client_phone', sanitize_text_field( $phone ) );
		update_post_meta( $job_id, 'client_language', $lang ? $lang : 'es' );
		update_post_meta( $job_id, 'client_contact_inherited', '1' );
	}

	/**
	 * Create a repair job linked to a bike, in the given status.
	 *
	 * @param int    $bike_id Bike ID.
	 * @param string $problem Problem description.
	 * @param string $status  Initial status slug.
	 * @return int Job ID, or 0 on failure.
	 */
	public static function create_for_bike( $bike_id, $problem = '', $status = 'received' ) {
		$job_id = wp_insert_post(
			array(
				'post_type'   => Repair_Job_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => '',
			),
			true
		);
		if ( is_wp_error( $job_id ) || ! $job_id ) {
			return 0;
		}

		update_post_meta( $job_id, 'bike_id', (int) $bike_id );
		update_post_meta( $job_id, 'job_id', ID_Generator::generate_job_id() );
		update_post_meta( $job_id, 'client_token', wp_generate_password( 32, false ) );
		update_post_meta( $job_id, 'date_received', current_time( 'Y-m-d' ) );
		update_post_meta( $job_id, 'priority', 'normal' );
		update_post_meta( $job_id, 'approval_status', 'not_required' );
		if ( '' !== trim( (string) $problem ) ) {
			update_post_meta( $job_id, 'problem_description', sanitize_textarea_field( $problem ) );
		}

		self::inherit_contact( $job_id );

		$job_number = get_post_meta( $job_id, 'job_id', true );
		$internal   = get_post_meta( $bike_id, 'internal_id', true );
		wp_update_post(
			array(
				'ID'         => $job_id,
				'post_title' => $job_number . ( $internal ? ' — ' . $internal : '' ),
			)
		);

		Job_Status::set( $job_id, $status, get_current_user_id() );

		return (int) $job_id;
	}
}
