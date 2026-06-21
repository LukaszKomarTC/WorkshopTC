<?php
/**
 * Front-end employee intake page at /workshop-intake/ (Phase 1).
 *
 * Standalone, staff-only, app-like screen: search a bike, view its card with an
 * open-job warning and recent history, and create a repair job from it. New-bike
 * creation, the scan-redirect setting and photos arrive in later phases.
 *
 * Access: page/search/card require tcw_view_bikes; the create-job action
 * (Intake_Actions) requires tcw_edit_jobs.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

use TossaWorkshop\Admin\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the intake page.
 */
class Intake_Page {

	const QUERY_VAR = 'tcw_intake';

	/**
	 * Singleton instance.
	 *
	 * @var Intake_Page|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Intake_Page
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build an intake URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		$url = QR_Generator::base_url() . '/workshop-intake/';
		return $args ? $url . '?' . http_build_query( $args ) : $url;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle' ) );
	}

	/**
	 * Register the rewrite rule (also called from the activator before flush).
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^workshop-intake/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle an intake request.
	 *
	 * @return void
	 */
	public function maybe_handle() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		nocache_headers();

		if ( ! is_user_logged_in() ) {
			auth_redirect(); // Sends to login, returns here. Exits.
		}
		if ( ! current_user_can( 'tcw_view_bikes' ) ) {
			status_header( 403 );
			$this->render_refusal();
			exit;
		}

		status_header( 200 );
		$this->render();
		exit;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	private function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended — read-only navigation params.
		$internal = isset( $_GET['bike'] ) ? sanitize_text_field( wp_unslash( $_GET['bike'] ) ) : '';
		$created  = isset( $_GET['created'] ) ? absint( wp_unslash( $_GET['created'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->head( __( 'Workshop Intake', 'tossa-workshop' ) );

		echo '<div class="tcw-wrap">';
		echo '<h1>' . esc_html__( 'Workshop Intake', 'tossa-workshop' ) . ' <span class="tcw-shop">' . esc_html( get_bloginfo( 'name' ) ) . '</span></h1>';

		if ( $created ) {
			$this->render_confirmation( $created );
		}

		$this->render_search_box( $internal );

		if ( $internal ) {
			$bike_id = $this->resolve_bike( $internal );
			if ( $bike_id ) {
				$this->render_bike_card( $bike_id );
			} else {
				$this->render_not_found( $internal );
			}
		}

		echo '</div>';
		$this->foot();
	}

	/**
	 * Render the search box.
	 *
	 * @param string $value Current bike term.
	 * @return void
	 */
	private function render_search_box( $value ) {
		echo '<div class="tcw-search">';
		echo '<label for="tcw-q">' . esc_html__( 'Scan a label or search a bike', 'tossa-workshop' ) . '</label>';
		echo '<input type="text" id="tcw-q" autocomplete="off" placeholder="' . esc_attr__( 'Internal ID, serial, owner name / email / phone', 'tossa-workshop' ) . '" />';
		echo '<ul id="tcw-results" class="tcw-results"></ul>';
		echo '</div>';
	}

	/**
	 * Render the bike card with open-job warning + recent jobs + create form.
	 *
	 * @param int $bike_id Bike ID.
	 * @return void
	 */
	private function render_bike_card( $bike_id ) {
		$internal = get_post_meta( $bike_id, 'internal_id', true );
		$brand    = get_post_meta( $bike_id, 'brand', true );
		$model    = get_post_meta( $bike_id, 'model', true );
		$type     = get_post_meta( $bike_id, 'bike_type', true );
		$cat_opts = Bike_Fields::option_sets()['category'];
		$category = get_post_meta( $bike_id, 'category', true );

		echo '<div class="tcw-card">';
		echo '<div class="tcw-card-head"><span class="tcw-id">' . esc_html( $internal ) . '</span>';
		echo '<span class="tcw-badge">' . esc_html( 'fleet' === $type ? __( 'Fleet', 'tossa-workshop' ) : __( 'Customer', 'tossa-workshop' ) ) . '</span></div>';
		echo '<p class="tcw-bike-name">' . esc_html( trim( $brand . ' ' . $model ) );
		if ( $category && isset( $cat_opts[ $category ] ) ) {
			echo ' · ' . esc_html( $cat_opts[ $category ] );
		}
		$size = get_post_meta( $bike_id, 'frame_size', true );
		if ( $size ) {
			echo ' · ' . esc_html( $size );
		}
		echo '</p>';

		// Owner (staff-only screen).
		$owner = get_post_meta( $bike_id, 'owner_name', true );
		$phone = get_post_meta( $bike_id, 'owner_phone', true );
		$email = get_post_meta( $bike_id, 'owner_email', true );
		if ( $owner || $phone || $email ) {
			echo '<dl class="tcw-owner">';
			if ( $owner ) {
				echo '<dt>' . esc_html__( 'Owner', 'tossa-workshop' ) . '</dt><dd>' . esc_html( $owner ) . '</dd>';
			}
			if ( $phone ) {
				echo '<dt>' . esc_html__( 'Phone', 'tossa-workshop' ) . '</dt><dd>' . esc_html( $phone ) . '</dd>';
			}
			if ( $email ) {
				echo '<dt>' . esc_html__( 'Email', 'tossa-workshop' ) . '</dt><dd>' . esc_html( $email ) . '</dd>';
			}
			echo '</dl>';
		}

		// Open-job warning.
		$open = $this->jobs_for_bike( $bike_id, 20, true );
		if ( $open ) {
			$j      = $open[0];
			$labels = Job_Status_Taxonomy::status_labels();
			$slug   = Job_Status::get( $j );
			echo '<div class="tcw-warn">';
			echo '<strong>' . esc_html__( 'Open job already exists:', 'tossa-workshop' ) . '</strong> ';
			echo esc_html( get_post_meta( $j, 'job_id', true ) . ' — ' . ( $labels[ $slug ] ?? $slug ) );
			echo '<div class="tcw-warn-actions"><a class="tcw-btn" href="' . esc_url( get_edit_post_link( $j ) ) . '">' . esc_html__( 'Open existing job', 'tossa-workshop' ) . '</a></div>';
			echo '</div>';
		}

		// Actions.
		echo '<div class="tcw-actions">';
		echo '<a class="tcw-btn" href="' . esc_url( get_edit_post_link( $bike_id ) ) . '">' . esc_html__( 'Edit in admin', 'tossa-workshop' ) . '</a>';
		$print = wp_nonce_url( admin_url( 'admin.php?action=tcw_print_label&post=' . $bike_id ), 'tcw_print_label_' . $bike_id );
		echo '<a class="tcw-btn" href="' . esc_url( $print ) . '" target="_blank" rel="noopener">' . esc_html__( 'Print label', 'tossa-workshop' ) . '</a>';
		echo '</div>';

		// Recent history (last 3).
		$recent = $this->jobs_for_bike( $bike_id, 3, false );
		if ( $recent ) {
			$labels = Job_Status_Taxonomy::status_labels();
			echo '<h3>' . esc_html__( 'Recent jobs', 'tossa-workshop' ) . '</h3><ul class="tcw-history">';
			foreach ( $recent as $jid ) {
				$slug = Job_Status::get( $jid );
				echo '<li><a href="' . esc_url( get_edit_post_link( $jid ) ) . '">' . esc_html( get_post_meta( $jid, 'job_id', true ) ) . '</a> — ' . esc_html( ( $labels[ $slug ] ?? $slug ) . ' · ' . get_post_meta( $jid, 'date_received', true ) ) . '</li>';
			}
			echo '</ul>';
			$all = admin_url( 'edit.php?post_type=' . Repair_Job_CPT::POST_TYPE );
			echo '<p><a href="' . esc_url( $all ) . '">' . esc_html__( 'View full history', 'tossa-workshop' ) . '</a></p>';
		}

		// Create-job form.
		if ( current_user_can( 'tcw_edit_jobs' ) ) {
			$this->render_create_job_form( $bike_id );
		}

		echo '</div>'; // .tcw-card
	}

	/**
	 * Render the compact create-job form.
	 *
	 * @param int $bike_id Bike ID.
	 * @return void
	 */
	private function render_create_job_form( $bike_id ) {
		$prio = Repair_Job_Fields::option_sets()['priority'];

		echo '<h3>' . esc_html__( 'Create repair job', 'tossa-workshop' ) . '</h3>';
		echo '<form class="tcw-job-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="tcw_intake_create_job" />';
		echo '<input type="hidden" name="bike_id" value="' . esc_attr( (string) $bike_id ) . '" />';
		wp_nonce_field( 'tcw_intake_job_' . $bike_id, 'tcw_intake_nonce' );

		echo '<label>' . esc_html__( 'Problem description', 'tossa-workshop' ) . '<textarea name="problem_description" rows="3"></textarea></label>';
		echo '<label>' . esc_html__( 'Bike condition on arrival', 'tossa-workshop' ) . '<textarea name="bike_condition_on_arrival" rows="2"></textarea></label>';

		echo '<label>' . esc_html__( 'Priority', 'tossa-workshop' ) . '<select name="priority">';
		foreach ( $prio as $val => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $val ), selected( 'normal', $val, false ), esc_html( $label ) );
		}
		echo '</select></label>';

		echo '<label>' . esc_html__( 'Promised completion', 'tossa-workshop' ) . '<input type="date" name="promised_completion" /></label>';

		echo '<label>' . esc_html__( 'Assigned mechanic', 'tossa-workshop' ) . '</label>';
		wp_dropdown_users(
			array(
				'name'              => 'assigned_mechanic',
				'show_option_none'  => __( '— None —', 'tossa-workshop' ),
				'option_none_value' => 0,
				'role__in'          => array( 'administrator', Roles::ROLE_MANAGER, Roles::ROLE_MECHANIC, Roles::ROLE_FRONT_DESK ),
			)
		);

		echo '<label class="tcw-check"><input type="checkbox" name="send_email" value="1" checked /> ' . esc_html__( 'Send "received" email to the client', 'tossa-workshop' ) . '</label>';

		echo '<button type="submit" class="tcw-btn tcw-btn-primary tcw-sticky">' . esc_html__( 'Create repair job', 'tossa-workshop' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Render the post-create confirmation panel.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function render_confirmation( $job_id ) {
		if ( Repair_Job_CPT::POST_TYPE !== get_post_type( $job_id ) ) {
			return;
		}
		// Only show the panel (which exposes the client token/contact) for a job
		// this user can edit — same bar as the admin job screen. Prevents
		// harvesting tokens by enumerating arbitrary ?created= IDs.
		if ( ! current_user_can( 'edit_post', $job_id ) ) {
			return;
		}
		$job_number = get_post_meta( $job_id, 'job_id', true );
		$bike_id    = (int) get_post_meta( $job_id, 'bike_id', true );
		$internal   = $bike_id ? get_post_meta( $bike_id, 'internal_id', true ) : '';

		echo '<div class="tcw-confirm">';
		echo '<p class="tcw-confirm-title">' . esc_html__( 'Repair job created', 'tossa-workshop' ) . '</p>';
		echo '<p><strong>' . esc_html( $job_number ) . '</strong>';
		if ( $internal ) {
			echo ' · ' . esc_html( $internal );
		}
		echo '</p>';
		echo '<div class="tcw-actions">';
		echo '<a class="tcw-btn tcw-btn-primary" href="' . esc_url( get_edit_post_link( $job_id ) ) . '">' . esc_html__( 'Open job', 'tossa-workshop' ) . '</a>';
		echo '<a class="tcw-btn" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Back to intake', 'tossa-workshop' ) . '</a>';

		// WhatsApp the client their status link, if we have a phone.
		$phone = preg_replace( '/\D+/', '', (string) get_post_meta( $job_id, 'client_phone', true ) );
		$token = get_post_meta( $job_id, 'client_token', true );
		if ( $phone && $token && $job_number ) {
			$text = rawurlencode( get_bloginfo( 'name' ) . ' — ' . $job_number . ' — ' . Client_Status_Page::url( $job_number, $token ) );
			echo '<a class="tcw-btn tcw-btn-wa" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . $phone . '?text=' . $text ) . '">' . esc_html__( 'WhatsApp client', 'tossa-workshop' ) . '</a>';
		}
		echo '</div></div>';
	}

	/**
	 * Render the "bike not found" panel (Phase 1: point to admin create).
	 *
	 * @param string $internal Searched ID.
	 * @return void
	 */
	private function render_not_found( $internal ) {
		echo '<div class="tcw-card">';
		echo '<p>' . esc_html( sprintf( /* translators: %s: searched ID */ __( 'No bike found for "%s".', 'tossa-workshop' ), $internal ) ) . '</p>';
		if ( current_user_can( 'tcw_edit_bikes' ) ) {
			echo '<a class="tcw-btn tcw-btn-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . Bike_CPT::POST_TYPE ) ) . '">' . esc_html__( 'Create a new bike (admin)', 'tossa-workshop' ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Refusal page for authenticated users without permission.
	 *
	 * @return void
	 */
	private function render_refusal() {
		$this->head( __( 'Workshop Intake', 'tossa-workshop' ) );
		echo '<div class="tcw-wrap"><div class="tcw-card"><p>' . esc_html__( 'You do not have permission to access the workshop intake.', 'tossa-workshop' ) . '</p></div></div>';
		$this->foot();
	}

	/**
	 * Resolve a bike post ID from its internal ID.
	 *
	 * @param string $internal Internal ID.
	 * @return int
	 */
	private function resolve_bike( $internal ) {
		$q = new \WP_Query(
			array(
				'post_type'      => Bike_CPT::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'internal_id',
						'value' => $internal,
					),
				),
			)
		);
		return $q->have_posts() ? (int) $q->posts[0] : 0;
	}

	/**
	 * Job IDs linked to a bike, newest first. Optionally only active jobs.
	 *
	 * @param int  $bike_id     Bike ID.
	 * @param int  $limit       Max results.
	 * @param bool $active_only Exclude terminal statuses.
	 * @return int[]
	 */
	private function jobs_for_bike( $bike_id, $limit, $active_only ) {
		$args = array(
			'post_type'      => Repair_Job_CPT::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'bike_id',
					'value' => $bike_id,
				),
			),
		);

		if ( $active_only ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'taxonomy' => Job_Status_Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( 'delivered', 'closed', 'cancelled', 'declined' ),
					'operator' => 'NOT IN',
				),
			);
		}

		$q = new \WP_Query( $args );
		return array_map( 'intval', $q->posts );
	}

	/**
	 * Output the page head + styles + config.
	 *
	 * @param string $title Title.
	 * @return void
	 */
	private function head( $title ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		$config = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'action'   => Bike_Search::ACTION,
			'nonce'    => wp_create_nonce( Bike_Search::ACTION ),
			'intakeUrl' => self::url(),
		);
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		:root{--tcw:#1f6f3c;}
		*{box-sizing:border-box;}
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f4f5f4;color:#1d2327;margin:0;padding:0 0 90px;}
		.tcw-wrap{max-width:560px;margin:0 auto;padding:16px;}
		h1{font-size:1.3rem;color:var(--tcw);margin:12px 0 16px;}
		h1 .tcw-shop{color:#646970;font-weight:400;font-size:.8em;}
		h3{font-size:1rem;margin:18px 0 6px;}
		.tcw-search label{display:block;font-weight:600;margin-bottom:6px;}
		#tcw-q{width:100%;font-size:1.1rem;padding:14px;border:1px solid #c3c4c7;border-radius:10px;}
		.tcw-results{list-style:none;margin:6px 0 0;padding:0;}
		.tcw-results li{padding:12px 14px;border:1px solid #e2e4e2;border-top:0;background:#fff;cursor:pointer;}
		.tcw-results li:first-child{border-top:1px solid #e2e4e2;border-radius:8px 8px 0 0;}
		.tcw-results li:last-child{border-radius:0 0 8px 8px;}
		.tcw-results li:hover{background:#eef6f0;}
		.tcw-card{background:#fff;border:1px solid #e2e4e2;border-radius:12px;padding:18px;margin-top:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
		.tcw-card-head{display:flex;align-items:center;gap:10px;}
		.tcw-id{font-size:1.25rem;font-weight:700;font-family:monospace;}
		.tcw-badge{background:var(--tcw);color:#fff;border-radius:999px;padding:2px 12px;font-size:.8rem;}
		.tcw-bike-name{margin:6px 0 12px;font-size:1.05rem;}
		.tcw-owner{display:grid;grid-template-columns:auto 1fr;gap:2px 14px;margin:0 0 12px;}
		.tcw-owner dt{font-weight:600;color:#50575e;}
		.tcw-owner dd{margin:0;}
		.tcw-warn{background:#fcf3e6;border:1px solid #f0d9b5;border-radius:10px;padding:12px;margin:12px 0;}
		.tcw-warn-actions{margin-top:8px;}
		.tcw-history{list-style:none;margin:0;padding:0;}
		.tcw-history li{padding:6px 0;border-bottom:1px solid #f0f0f1;}
		.tcw-actions{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;}
		.tcw-btn{display:inline-block;padding:11px 16px;border-radius:8px;border:1px solid #c3c4c7;background:#f6f7f7;color:#1d2327;text-decoration:none;font-weight:600;cursor:pointer;font-size:1rem;}
		.tcw-btn-primary{background:var(--tcw);color:#fff;border-color:var(--tcw);}
		.tcw-btn-wa{background:#25d366;color:#fff;border-color:#25d366;}
		.tcw-job-form label{display:block;font-weight:600;margin:10px 0 4px;}
		.tcw-job-form textarea,.tcw-job-form select,.tcw-job-form input[type=date]{width:100%;padding:10px;border:1px solid #c3c4c7;border-radius:8px;font-size:1rem;}
		.tcw-job-form .tcw-check{font-weight:400;display:flex;align-items:center;gap:8px;margin-top:12px;}
		.tcw-sticky{width:100%;margin-top:16px;padding:15px;font-size:1.05rem;}
		.tcw-confirm{background:#e6f4ea;border:1px solid #b7e0c2;border-radius:12px;padding:16px;margin-bottom:16px;}
		.tcw-confirm-title{font-weight:700;margin:0 0 6px;}
	</style>
	<script>window.tcwIntake = <?php echo wp_json_encode( $config ); ?>;</script>
</head>
<body>
		<?php
	}

	/**
	 * Output the page foot + script.
	 *
	 * @return void
	 */
	private function foot() {
		echo '<script src="' . esc_url( TCW_PLUGIN_URL . 'assets/js/intake.js?ver=' . TCW_VERSION ) . '"></script>';
		echo '</body></html>';
	}
}
