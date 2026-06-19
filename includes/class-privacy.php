<?php
/**
 * GDPR personal-data export + erase integration (spec §13).
 *
 * Registers WordPress's native privacy exporter/eraser so a client's workshop
 * PII — bike owner contact and the client contact copied onto jobs — is
 * included in Tools → Export/Erase Personal Data. Repair history itself is
 * preserved on erase; only the personal contact fields are cleared.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks workshop data into WordPress's privacy tools.
 */
class Privacy {

	/** Records processed per page of an export/erase request. */
	const BATCH = 50;

	/**
	 * Singleton instance.
	 *
	 * @var Privacy|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Privacy
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
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['tossa-workshop'] = array(
			'exporter_friendly_name' => __( 'Tossa Workshop', 'tossa-workshop' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['tossa-workshop'] = array(
			'eraser_friendly_name' => __( 'Tossa Workshop', 'tossa-workshop' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export a person's workshop data, paginated.
	 *
	 * @param string $email Email address being exported.
	 * @param int    $page  1-based page number.
	 * @return array{data:array,done:bool}
	 */
	public function export( $email, $page = 1 ) {
		$page  = max( 1, (int) $page );
		$items = $this->matching_records( $email );
		$slice = array_slice( $items, ( $page - 1 ) * self::BATCH, self::BATCH );

		$export_items = array();
		foreach ( $slice as $rec ) {
			if ( 'bike' === $rec['type'] ) {
				$export_items[] = $this->export_bike( $rec['id'] );
			} else {
				$export_items[] = $this->export_job( $rec['id'] );
			}
		}

		return array(
			'data' => $export_items,
			'done' => ( $page * self::BATCH ) >= count( $items ),
		);
	}

	/**
	 * Erase a person's workshop PII, paginated. Keeps the records, clears the
	 * personal contact fields.
	 *
	 * @param string $email Email address being erased.
	 * @param int    $page  1-based page number.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public function erase( $email, $page = 1 ) {
		$page    = max( 1, (int) $page );
		$items   = $this->matching_records( $email );
		$slice   = array_slice( $items, ( $page - 1 ) * self::BATCH, self::BATCH );
		$removed = false;

		foreach ( $slice as $rec ) {
			if ( 'bike' === $rec['type'] ) {
				foreach ( array( 'owner_name', 'owner_email', 'owner_phone' ) as $key ) {
					if ( '' !== (string) get_post_meta( $rec['id'], $key, true ) ) {
						delete_post_meta( $rec['id'], $key );
						$removed = true;
					}
				}
			} else {
				foreach ( array( 'client_name', 'client_email', 'client_phone', 'client_notes', 'client_comments' ) as $key ) {
					if ( '' !== (string) get_post_meta( $rec['id'], $key, true ) ) {
						delete_post_meta( $rec['id'], $key );
						$removed = true;
					}
				}
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => ( $page * self::BATCH ) >= count( $items ),
		);
	}

	/**
	 * All bike + job records (as type/id rows) whose PII matches the email.
	 *
	 * @param string $email Email address.
	 * @return array<int,array{type:string,id:int}>
	 */
	private function matching_records( $email ) {
		$records = array();

		foreach ( $this->bike_ids( $email ) as $id ) {
			$records[] = array(
				'type' => 'bike',
				'id'   => (int) $id,
			);
		}
		foreach ( $this->job_ids( $email ) as $id ) {
			$records[] = array(
				'type' => 'job',
				'id'   => (int) $id,
			);
		}
		return $records;
	}

	/**
	 * Bike IDs matching the email (by owner_email or linked user's email).
	 *
	 * @param string $email Email address.
	 * @return int[]
	 */
	private function bike_ids( $email ) {
		$meta = array(
			'relation' => 'OR',
			array(
				'key'   => 'owner_email',
				'value' => $email,
			),
		);

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$meta[] = array(
				'key'   => 'owner_user_id',
				'value' => $user->ID,
			);
		}

		$q = new \WP_Query(
			array(
				'post_type'      => Bike_CPT::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return array_map( 'intval', $q->posts );
	}

	/**
	 * Job IDs matching the email (by stored client_email).
	 *
	 * @param string $email Email address.
	 * @return int[]
	 */
	private function job_ids( $email ) {
		$q = new \WP_Query(
			array(
				'post_type'      => Repair_Job_CPT::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'client_email',
						'value' => $email,
					),
				),
			)
		);
		return array_map( 'intval', $q->posts );
	}

	/**
	 * Build the export item for a bike.
	 *
	 * @param int $bike_id Bike ID.
	 * @return array
	 */
	private function export_bike( $bike_id ) {
		$fields = array(
			array( __( 'Internal ID', 'tossa-workshop' ), get_post_meta( $bike_id, 'internal_id', true ) ),
			array( __( 'Brand / Model', 'tossa-workshop' ), trim( get_post_meta( $bike_id, 'brand', true ) . ' ' . get_post_meta( $bike_id, 'model', true ) ) ),
			array( __( 'Owner name', 'tossa-workshop' ), get_post_meta( $bike_id, 'owner_name', true ) ),
			array( __( 'Owner email', 'tossa-workshop' ), get_post_meta( $bike_id, 'owner_email', true ) ),
			array( __( 'Owner phone', 'tossa-workshop' ), get_post_meta( $bike_id, 'owner_phone', true ) ),
			array( __( 'GDPR consent given', 'tossa-workshop' ), get_post_meta( $bike_id, 'gdpr_consent', true ) ? 'yes' : 'no' ),
		);

		return array(
			'group_id'    => 'tcw_bikes',
			'group_label' => __( 'Workshop bikes', 'tossa-workshop' ),
			'item_id'     => 'tcw-bike-' . $bike_id,
			'data'        => $this->to_data( $fields ),
		);
	}

	/**
	 * Build the export item for a job.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	private function export_job( $job_id ) {
		$fields = array(
			array( __( 'Job ID', 'tossa-workshop' ), get_post_meta( $job_id, 'job_id', true ) ),
			array( __( 'Client name', 'tossa-workshop' ), get_post_meta( $job_id, 'client_name', true ) ),
			array( __( 'Client email', 'tossa-workshop' ), get_post_meta( $job_id, 'client_email', true ) ),
			array( __( 'Client phone', 'tossa-workshop' ), get_post_meta( $job_id, 'client_phone', true ) ),
			array( __( 'Date received', 'tossa-workshop' ), get_post_meta( $job_id, 'date_received', true ) ),
			array( __( 'Problem description', 'tossa-workshop' ), get_post_meta( $job_id, 'problem_description', true ) ),
		);

		return array(
			'group_id'    => 'tcw_jobs',
			'group_label' => __( 'Workshop repair jobs', 'tossa-workshop' ),
			'item_id'     => 'tcw-job-' . $job_id,
			'data'        => $this->to_data( $fields ),
		);
	}

	/**
	 * Convert [label, value] pairs into the privacy export data shape, dropping
	 * empty values.
	 *
	 * @param array $fields List of [label, value].
	 * @return array
	 */
	private function to_data( array $fields ) {
		$data = array();
		foreach ( $fields as $field ) {
			if ( '' !== (string) $field[1] ) {
				$data[] = array(
					'name'  => $field[0],
					'value' => $field[1],
				);
			}
		}
		return $data;
	}
}
