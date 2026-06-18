<?php
/**
 * Fleet post-rental quick-check (spec §11, optional/isolated).
 *
 * A standalone admin page records a condition check on a fleet bike (overall
 * OK, component ratings, damage notes, photos) and can optionally spin up a
 * linked repair job in `received` and mark the bike no longer rentable. A
 * launcher meta box appears on fleet bikes (also reachable after a scan, which
 * lands staff on the bike edit screen).
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop\Admin;

use TossaWorkshop\Bike_CPT;
use TossaWorkshop\Field_Kit;
use TossaWorkshop\Job_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Fleet quick-check page + launcher.
 */
class Fleet_Check {

	const PAGE       = 'tcw-fleet-check';
	const CAPABILITY = 'tcw_edit_bikes';
	const META       = 'fleet_checks';

	/**
	 * Singleton instance.
	 *
	 * @var Fleet_Check|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Fleet_Check
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Rating options for component checks.
	 *
	 * @return array<string,string>
	 */
	public static function ratings() {
		return array(
			'ok'        => __( 'OK', 'tossa-workshop' ),
			'attention' => __( 'Needs attention', 'tossa-workshop' ),
			'replace'   => __( 'Replace', 'tossa-workshop' ),
		);
	}

	/**
	 * Components rated in a check.
	 *
	 * @return array<string,string>
	 */
	public static function components() {
		return array(
			'tyres'         => __( 'Tyres', 'tossa-workshop' ),
			'brakes'        => __( 'Brakes', 'tossa-workshop' ),
			'drivetrain'    => __( 'Drivetrain', 'tossa-workshop' ),
			'battery_motor' => __( 'Battery / Motor', 'tossa-workshop' ),
		);
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'add_meta_boxes_' . Bike_CPT::POST_TYPE, array( $this, 'add_meta_box' ) );
		// Handled via admin-post so the post-submit redirect runs before output.
		add_action( 'admin_post_tcw_fleet_check', array( $this, 'handle_submit' ) );
	}

	/**
	 * Register the (hidden) quick-check page under the Bikes menu.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . Bike_CPT::POST_TYPE,
			__( 'Fleet quick-check', 'tossa-workshop' ),
			__( 'Fleet quick-check', 'tossa-workshop' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * URL of the quick-check page for a bike.
	 *
	 * @param int $bike_id Bike ID.
	 * @return string
	 */
	public static function page_url( $bike_id ) {
		return add_query_arg(
			array(
				'post_type' => Bike_CPT::POST_TYPE,
				'page'      => self::PAGE,
				'bike'      => $bike_id,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Add the launcher meta box on fleet bikes only.
	 *
	 * @param \WP_Post $post Bike.
	 * @return void
	 */
	public function add_meta_box( $post ) {
		if ( 'fleet' !== get_post_meta( $post->ID, 'bike_type', true ) ) {
			return;
		}
		add_meta_box(
			'tcw_fleet_check',
			__( 'Fleet quick-check', 'tossa-workshop' ),
			array( $this, 'render_meta_box' ),
			Bike_CPT::POST_TYPE,
			'side',
			'low'
		);
	}

	/**
	 * Render the launcher meta box.
	 *
	 * @param \WP_Post $post Bike.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$checks = get_post_meta( $post->ID, self::META, true );
		$last   = is_array( $checks ) && $checks ? end( $checks ) : null;

		if ( $last ) {
			$ratings = self::ratings();
			echo '<p class="description">' . esc_html__( 'Last check:', 'tossa-workshop' ) . ' ' . esc_html( $last['timestamp'] ) . '<br>';
			echo esc_html__( 'Condition:', 'tossa-workshop' ) . ' ' . ( ! empty( $last['condition_ok'] ) ? esc_html__( 'OK', 'tossa-workshop' ) : esc_html__( 'Issues', 'tossa-workshop' ) ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'No post-rental checks recorded yet.', 'tossa-workshop' ) . '</p>';
		}

		echo '<a class="button button-primary" href="' . esc_url( self::page_url( $post->ID ) ) . '">' . esc_html__( 'Record post-rental check', 'tossa-workshop' ) . '</a>';
	}

	/**
	 * Render the quick-check page (and handle submissions).
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tossa-workshop' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only GET render; the form POST verifies its own nonce.
		$bike_id = isset( $_GET['bike'] ) ? absint( wp_unslash( $_GET['bike'] ) ) : 0;
		if ( ! $bike_id || Bike_CPT::POST_TYPE !== get_post_type( $bike_id ) || 'fleet' !== get_post_meta( $bike_id, 'bike_type', true ) ) {
			wp_die( esc_html__( 'This page is only available for fleet bikes.', 'tossa-workshop' ) );
		}

		wp_enqueue_media();
		\TossaWorkshop\Assets::enqueue_media_js();
		wp_enqueue_style( 'tcw-admin-bike', TCW_PLUGIN_URL . 'assets/css/admin-bike.css', array(), TCW_VERSION );

		$internal = get_post_meta( $bike_id, 'internal_id', true );
		$ratings  = self::ratings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Fleet quick-check', 'tossa-workshop' ); ?> — <code><?php echo esc_html( $internal ); ?></code></h1>

			<?php if ( isset( $_GET['tcw_done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Check saved.', 'tossa-workshop' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tcw_fleet_check" />
				<input type="hidden" name="bike" value="<?php echo esc_attr( (string) $bike_id ); ?>" />
				<?php wp_nonce_field( 'tcw_fleet_check_' . $bike_id, 'tcw_fc_nonce' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Overall condition OK', 'tossa-workshop' ); ?></th>
						<td><label><input type="checkbox" name="condition_ok" value="1" checked /> <?php esc_html_e( 'Yes', 'tossa-workshop' ); ?></label></td>
					</tr>
					<?php foreach ( self::components() as $key => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<select name="ratings[<?php echo esc_attr( $key ); ?>]">
									<?php foreach ( $ratings as $rv => $rl ) : ?>
										<option value="<?php echo esc_attr( $rv ); ?>"><?php echo esc_html( $rl ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Damage notes', 'tossa-workshop' ); ?></th>
						<td><textarea name="damage_notes" rows="4" class="large-text"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Photos', 'tossa-workshop' ); ?></th>
						<td><?php Field_Kit::render_gallery( 'tcw_fc_photos', array() ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Follow-up', 'tossa-workshop' ); ?></th>
						<td>
							<label><input type="checkbox" name="create_job" value="1" /> <?php esc_html_e( 'Create a linked repair job (status: received)', 'tossa-workshop' ); ?></label><br>
							<label><input type="checkbox" name="set_rental_inactive" value="1" /> <?php esc_html_e( 'Mark the bike as not currently rentable', 'tossa-workshop' ); ?></label>
						</td>
					</tr>
				</tbody></table>
				<?php submit_button( __( 'Save check', 'tossa-workshop' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle a quick-check submission (admin-post, before output).
	 *
	 * @return void
	 */
	public function handle_submit() {
		$bike_id = isset( $_POST['bike'] ) ? absint( wp_unslash( $_POST['bike'] ) ) : 0;

		if ( ! $bike_id || ! isset( $_POST['tcw_fc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tcw_fc_nonce'] ) ), 'tcw_fleet_check_' . $bike_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'tossa-workshop' ) );
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tossa-workshop' ) );
		}
		if ( Bike_CPT::POST_TYPE !== get_post_type( $bike_id ) || 'fleet' !== get_post_meta( $bike_id, 'bike_type', true ) ) {
			wp_die( esc_html__( 'This page is only available for fleet bikes.', 'tossa-workshop' ) );
		}

		$allowed_ratings = array_keys( self::ratings() );
		$ratings         = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitized — sanitized per component below.
		$posted_ratings = isset( $_POST['ratings'] ) && is_array( $_POST['ratings'] ) ? wp_unslash( $_POST['ratings'] ) : array();
		foreach ( self::components() as $key => $label ) {
			$val             = isset( $posted_ratings[ $key ] ) ? sanitize_key( $posted_ratings[ $key ] ) : '';
			$ratings[ $key ] = in_array( $val, $allowed_ratings, true ) ? $val : 'ok';
		}

		$damage = isset( $_POST['damage_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['damage_notes'] ) ) : '';
		$photos = $this->parse_photos();

		$check = array(
			'timestamp'    => current_time( 'mysql' ),
			'user_id'      => get_current_user_id(),
			'condition_ok' => ! empty( $_POST['condition_ok'] ),
			'ratings'      => $ratings,
			'damage_notes' => $damage,
			'photos'       => $photos,
		);

		$checks = get_post_meta( $bike_id, self::META, true );
		if ( ! is_array( $checks ) ) {
			$checks = array();
		}
		$checks[] = $check;
		update_post_meta( $bike_id, self::META, $checks );

		if ( ! empty( $_POST['set_rental_inactive'] ) ) {
			update_post_meta( $bike_id, 'rental_active', '' );
		}

		$redirect = add_query_arg( 'tcw_done', '1', self::page_url( $bike_id ) );

		if ( ! empty( $_POST['create_job'] ) ) {
			$problem = trim( __( 'Post-rental check follow-up.', 'tossa-workshop' ) . ( $damage ? "\n" . $damage : '' ) );
			$job_id  = Job_Factory::create_for_bike( $bike_id, $problem, 'received' );
			if ( $job_id ) {
				$redirect = get_edit_post_link( $job_id, 'raw' );
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Parse the posted photo gallery into a list of {id,label}.
	 *
	 * @return array<int,array{id:int,label:string}>
	 */
	private function parse_photos() {
		$out = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitized — sanitized below.
		$raw = isset( $_POST['tcw_fc_photos'] ) && is_array( $_POST['tcw_fc_photos'] ) ? wp_unslash( $_POST['tcw_fc_photos'] ) : array();
		foreach ( $raw as $item ) {
			$id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			if ( ! $id ) {
				continue;
			}
			$out[] = array(
				'id'    => $id,
				'label' => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
			);
		}
		return $out;
	}
}
