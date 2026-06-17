<?php
/**
 * Repair-job status read/write with audit trail.
 *
 * Centralizes status changes so both the admin status dropdown (M2) and the
 * client approve/decline flow (M4) append history and fire the same action.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Status transitions for repair jobs.
 */
class Job_Status {

	const HISTORY_META = 'status_history';

	/**
	 * Current status slug for a job.
	 *
	 * @param int $job_id Job post ID.
	 * @return string Status slug, or '' if none.
	 */
	public static function get( $job_id ) {
		$terms = wp_get_object_terms( $job_id, Job_Status_Taxonomy::TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return (string) $terms[0];
	}

	/**
	 * Set the status of a job, appending history and firing the action.
	 *
	 * Performs no capability checks — callers gate as appropriate.
	 *
	 * @param int      $job_id  Job post ID.
	 * @param string   $to      Target status slug.
	 * @param int|null $user_id Acting user (defaults to current user).
	 * @return bool True if the status changed.
	 */
	public static function set( $job_id, $to, $user_id = null ) {
		if ( ! in_array( $to, Job_Status_Taxonomy::status_slugs(), true ) ) {
			return false;
		}

		$from = self::get( $job_id );
		if ( $from === $to ) {
			return false;
		}

		$result = wp_set_object_terms( $job_id, $to, Job_Status_Taxonomy::TAXONOMY, false );
		if ( is_wp_error( $result ) ) {
			return false;
		}

		$history = get_post_meta( $job_id, self::HISTORY_META, true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$history[] = array(
			'timestamp' => current_time( 'mysql' ),
			'from'      => $from,
			'to'        => $to,
			'user_id'   => null === $user_id ? get_current_user_id() : (int) $user_id,
		);
		update_post_meta( $job_id, self::HISTORY_META, $history );

		/**
		 * Fires after a repair job's status changes.
		 *
		 * @param int    $job_id Job post ID.
		 * @param string $from   Previous status slug ('' if none).
		 * @param string $to     New status slug.
		 */
		do_action( 'tcw_job_status_changed', $job_id, $from, $to );

		return true;
	}

	/**
	 * Status slugs the current user is allowed to set.
	 *
	 * Mechanics (no advance/close caps) may move up to quality_check plus the
	 * side exits; front desk (advance, no close) up to delivered; managers
	 * (close) everything.
	 *
	 * @return string[]
	 */
	public static function allowed_for_current_user() {
		if ( ! current_user_can( 'tcw_change_status' ) ) {
			return array();
		}

		$linear = Job_Status_Taxonomy::linear_statuses();
		$qc_idx = array_search( 'quality_check', $linear, true );

		$allowed = array();
		foreach ( Job_Status_Taxonomy::status_slugs() as $slug ) {
			if ( in_array( $slug, array( 'declined', 'cancelled' ), true ) ) {
				$allowed[] = $slug; // Side exits, anyone with change_status.
				continue;
			}

			$idx = array_search( $slug, $linear, true );
			if ( false === $idx ) {
				continue;
			}

			if ( $idx <= $qc_idx ) {
				$allowed[] = $slug;
			} elseif ( 'closed' === $slug ) {
				if ( current_user_can( 'tcw_close_jobs' ) ) {
					$allowed[] = $slug;
				}
			} elseif ( current_user_can( 'tcw_advance_status_full' ) ) {
				$allowed[] = $slug;
			}
		}

		return $allowed;
	}
}
