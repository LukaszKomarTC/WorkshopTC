<?php
/**
 * Repair Jobs admin list table: columns, filters, search, quick views (§10).
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop\Admin;

use TossaWorkshop\Repair_Job_CPT;
use TossaWorkshop\Bike_CPT;
use TossaWorkshop\Job_Status;
use TossaWorkshop\Job_Status_Taxonomy;
use TossaWorkshop\Repair_Job_Fields;
use TossaWorkshop\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Customises the repair-job list screen.
 */
class Jobs_List {

	/**
	 * Singleton instance.
	 *
	 * @var Jobs_List|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Jobs_List
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
		$pt = Repair_Job_CPT::POST_TYPE;
		add_filter( "manage_{$pt}_posts_columns", array( $this, 'columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		add_filter( "manage_edit-{$pt}_sortable_columns", array( $this, 'sortable' ) );
		add_filter( "views_edit-{$pt}", array( $this, 'quick_views' ) );
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
			'tcw_job_id'      => __( 'Job ID', 'tossa-workshop' ),
			'tcw_client'      => __( 'Client', 'tossa-workshop' ),
			'tcw_bike'        => __( 'Bike', 'tossa-workshop' ),
			'tcw_status'      => __( 'Status', 'tossa-workshop' ),
			'tcw_priority'    => __( 'Priority', 'tossa-workshop' ),
			'tcw_received'    => __( 'Received', 'tossa-workshop' ),
			'tcw_promised'    => __( 'Promised', 'tossa-workshop' ),
			'tcw_mechanic'    => __( 'Mechanic', 'tossa-workshop' ),
			'tcw_approval'    => __( 'Approval', 'tossa-workshop' ),
			'tcw_final_price' => __( 'Final price', 'tossa-workshop' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable( $columns ) {
		$columns['tcw_received']    = 'tcw_received';
		$columns['tcw_promised']    = 'tcw_promised';
		$columns['tcw_priority']    = 'tcw_priority';
		$columns['tcw_final_price'] = 'tcw_final_price';
		return $columns;
	}

	/**
	 * Render a custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Job ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'tcw_job_id':
				echo '<strong><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( get_post_meta( $post_id, 'job_id', true ) ) . '</a></strong>';
				break;

			case 'tcw_client':
				echo esc_html( get_post_meta( $post_id, 'client_name', true ) ?: '—' );
				break;

			case 'tcw_bike':
				$bike_id = (int) get_post_meta( $post_id, 'bike_id', true );
				if ( $bike_id ) {
					$internal = get_post_meta( $bike_id, 'internal_id', true );
					$bike     = trim( get_post_meta( $bike_id, 'brand', true ) . ' ' . get_post_meta( $bike_id, 'model', true ) );
					echo esc_html( $bike );
					if ( $internal ) {
						echo '<br><a href="' . esc_url( get_edit_post_link( $bike_id ) ) . '"><code>' . esc_html( $internal ) . '</code></a>';
					}
				} else {
					echo '—';
				}
				break;

			case 'tcw_status':
				$slug   = Job_Status::get( $post_id );
				$labels = Job_Status_Taxonomy::status_labels();
				echo esc_html( $slug ? ( $labels[ $slug ] ?? $slug ) : '—' );
				break;

			case 'tcw_priority':
				$opts = Repair_Job_Fields::option_sets()['priority'];
				$val  = get_post_meta( $post_id, 'priority', true );
				echo esc_html( $val && isset( $opts[ $val ] ) ? $opts[ $val ] : '—' );
				break;

			case 'tcw_received':
				echo esc_html( get_post_meta( $post_id, 'date_received', true ) ?: '—' );
				break;

			case 'tcw_promised':
				echo esc_html( get_post_meta( $post_id, 'promised_completion', true ) ?: '—' );
				break;

			case 'tcw_mechanic':
				$uid  = (int) get_post_meta( $post_id, 'assigned_mechanic', true );
				$user = $uid ? get_userdata( $uid ) : null;
				echo esc_html( $user ? $user->display_name : '—' );
				break;

			case 'tcw_approval':
				$map = array(
					'not_required' => __( 'Not required', 'tossa-workshop' ),
					'pending'      => __( 'Pending', 'tossa-workshop' ),
					'approved'     => __( 'Approved', 'tossa-workshop' ),
					'declined'     => __( 'Declined', 'tossa-workshop' ),
				);
				$val = get_post_meta( $post_id, 'approval_status', true );
				echo esc_html( $val && isset( $map[ $val ] ) ? $map[ $val ] : '—' );
				break;

			case 'tcw_final_price':
				if ( ! current_user_can( 'tcw_edit_prices' ) ) {
					echo '<span class="description">—</span>';
					break;
				}
				$price = get_post_meta( $post_id, 'final_price', true );
				echo esc_html( is_numeric( $price ) ? number_format( (float) $price, 2, ',', '.' ) . ' €' : '—' );
				break;
		}
	}

	/**
	 * Render the filter controls above the list.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function filters( $post_type ) {
		if ( Repair_Job_CPT::POST_TYPE !== $post_type ) {
			return;
		}

		// Status.
		$labels = Job_Status_Taxonomy::status_labels();
		$cur    = $this->req( 'tcw_status' );
		echo '<select name="tcw_status"><option value="">' . esc_html__( 'All statuses', 'tossa-workshop' ) . '</option>';
		foreach ( Job_Status_Taxonomy::status_slugs() as $slug ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $slug ), selected( $cur, $slug, false ), esc_html( $labels[ $slug ] ?? $slug ) );
		}
		echo '</select>';

		// Priority.
		$pcur = $this->req( 'tcw_priority' );
		echo '<select name="tcw_priority"><option value="">' . esc_html__( 'All priorities', 'tossa-workshop' ) . '</option>';
		foreach ( Repair_Job_Fields::option_sets()['priority'] as $val => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $val ), selected( $pcur, $val, false ), esc_html( $label ) );
		}
		echo '</select>';

		// Bike type.
		$bcur = $this->req( 'tcw_bike_type' );
		echo '<select name="tcw_bike_type"><option value="">' . esc_html__( 'All bike types', 'tossa-workshop' ) . '</option>';
		foreach ( array( 'customer' => __( 'Customer', 'tossa-workshop' ), 'fleet' => __( 'Fleet', 'tossa-workshop' ) ) as $val => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $val ), selected( $bcur, $val, false ), esc_html( $label ) );
		}
		echo '</select>';

		// Mechanic.
		wp_dropdown_users(
			array(
				'name'            => 'tcw_mechanic',
				'show_option_all' => __( 'All mechanics', 'tossa-workshop' ),
				'selected'        => (int) $this->req( 'tcw_mechanic' ),
				'role__in'        => array( 'administrator', Roles::ROLE_MANAGER, Roles::ROLE_MECHANIC, Roles::ROLE_FRONT_DESK ),
			)
		);

		// Approval pending.
		echo '<label style="margin:0 6px;"><input type="checkbox" name="tcw_approval" value="pending"' . checked( $this->req( 'tcw_approval' ), 'pending', false ) . ' /> ' . esc_html__( 'Approval pending', 'tossa-workshop' ) . '</label>';
	}

	/**
	 * Apply filters, search and default ordering to the list query.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function apply_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Repair_Job_CPT::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = array();
		$tax_query  = array();

		$status = $this->req( 'tcw_status' );
		if ( $status ) {
			$tax_query[] = array(
				'taxonomy' => Job_Status_Taxonomy::TAXONOMY,
				'field'    => 'slug',
				'terms'    => $status,
			);
		}

		$mechanic = (int) $this->req( 'tcw_mechanic' );
		if ( $mechanic ) {
			$meta_query[] = array(
				'key'   => 'assigned_mechanic',
				'value' => $mechanic,
			);
		}

		$priority = $this->req( 'tcw_priority' );
		if ( $priority ) {
			$meta_query[] = array(
				'key'   => 'priority',
				'value' => $priority,
			);
		}

		if ( 'pending' === $this->req( 'tcw_approval' ) ) {
			$meta_query[] = array(
				'key'   => 'approval_status',
				'value' => 'pending',
			);
		}

		$bike_type = $this->req( 'tcw_bike_type' );
		if ( 'customer' === $bike_type || 'fleet' === $bike_type ) {
			$bike_ids = $this->bikes_of_type( $bike_type );
			// Use an impossible ID when none match so the list is empty rather
			// than unfiltered.
			$meta_query[] = array(
				'key'     => 'bike_id',
				'value'   => $bike_ids ? $bike_ids : array( 0 ),
				'compare' => 'IN',
			);
		}

		// Sorting. Order via a named meta_query clause built with EXISTS/NOT
		// EXISTS so it is a LEFT JOIN — rows missing the meta sort last instead
		// of being excluded (which a bare meta_key INNER JOIN would do).
		$orderby = $query->get( 'orderby' );
		$map     = array(
			'tcw_received'    => 'date_received',
			'tcw_promised'    => 'promised_completion',
			'tcw_priority'    => 'priority',
			'tcw_final_price' => 'final_price',
		);
		$sort_key = '';
		$sort_dir = 'DESC';
		if ( isset( $map[ $orderby ] ) ) {
			$sort_key = $map[ $orderby ];
			$sort_dir = 'asc' === strtolower( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';
		} elseif ( ! $orderby ) {
			$sort_key = 'date_received'; // Default: newest received first.
		}
		if ( $sort_key ) {
			$meta_query['tcw_sort'] = array(
				'relation' => 'OR',
				array(
					'key'     => $sort_key,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => $sort_key,
					'compare' => 'NOT EXISTS',
				),
			);
			$query->set( 'orderby', array( 'tcw_sort' => $sort_dir, 'date' => 'DESC' ) );
		}

		if ( $meta_query ) {
			$existing = (array) $query->get( 'meta_query' );
			$query->set( 'meta_query', array_merge( $existing, $meta_query ) );
		}
		if ( $tax_query ) {
			$existing_tax = (array) $query->get( 'tax_query' );
			$query->set( 'tax_query', array_merge( $existing_tax, $tax_query ) );
		}
	}

	/**
	 * Quick-view links above the list.
	 *
	 * @param array $views Existing views.
	 * @return array
	 */
	public function quick_views( $views ) {
		$base  = admin_url( 'edit.php?post_type=' . Repair_Job_CPT::POST_TYPE );
		$links = array(
			'waiting_approval' => __( 'Waiting for approval', 'tossa-workshop' ),
			'waiting_parts'    => __( 'Waiting for parts', 'tossa-workshop' ),
			'ready'            => __( 'Ready for pickup', 'tossa-workshop' ),
		);
		foreach ( $links as $slug => $label ) {
			$url                    = add_query_arg( 'tcw_status', $slug, $base );
			$views[ 'tcw_' . $slug ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		return $views;
	}

	/**
	 * Bike IDs of a given type.
	 *
	 * @param string $type customer|fleet.
	 * @return int[]
	 */
	private function bikes_of_type( $type ) {
		$q = new \WP_Query(
			array(
				'post_type'      => Bike_CPT::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'bike_type',
						'value' => $type,
					),
				),
			)
		);
		return array_map( 'intval', $q->posts );
	}

	/**
	 * Read a sanitized request filter value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function req( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended — list-table filter, read-only.
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}
}
