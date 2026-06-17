<?php
/**
 * Bike admin meta boxes: render, save, ID generation and QR storage.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the bike edit screen and persists its data.
 */
class Bike_Meta_Boxes {

	const NONCE_ACTION = 'tcw_save_bike';
	const NONCE_NAME   = 'tcw_bike_nonce';

	/**
	 * Singleton instance.
	 *
	 * @var Bike_Meta_Boxes|null
	 */
	private static $instance = null;

	/**
	 * Guards against recursion when updating the post title on save.
	 *
	 * @var bool
	 */
	private $saving = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Bike_Meta_Boxes
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
		add_action( 'add_meta_boxes_' . Bike_CPT::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Bike_CPT::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'edit_form_top', array( $this, 'render_nonce' ) );
	}

	/**
	 * Register all meta boxes for the bike screen.
	 *
	 * @param \WP_Post $post Current bike.
	 * @return void
	 */
	public function add_meta_boxes( $post ) {
		add_meta_box(
			'tcw_bike_identity',
			__( 'Workshop ID & QR', 'tossa-workshop' ),
			array( $this, 'render_identity_box' ),
			Bike_CPT::POST_TYPE,
			'side',
			'high'
		);

		foreach ( Bike_Fields::sections() as $section ) {
			$box_id = 'tcw_bike_' . $section['id'];

			add_meta_box(
				$box_id,
				$section['title'],
				array( $this, 'render_section_box' ),
				Bike_CPT::POST_TYPE,
				'normal',
				'default',
				array( 'section' => $section )
			);

			// Collapse optional sections by default.
			if ( ! empty( $section['collapsed'] ) ) {
				add_filter(
					'postbox_classes_' . Bike_CPT::POST_TYPE . '_' . $box_id,
					array( $this, 'closed_class' )
				);
			}

			// Tag conditional sections so JS can show/hide them.
			if ( ! empty( $section['only'] ) ) {
				$only = $section['only'];
				add_filter(
					'postbox_classes_' . Bike_CPT::POST_TYPE . '_' . $box_id,
					static function ( $classes ) use ( $only ) {
						$classes[] = 'tcw-only-' . $only;
						return $classes;
					}
				);
			}
		}
	}

	/**
	 * Output the save nonce at the top of the bike edit form (always present,
	 * even if the identity box is hidden via Screen Options).
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_nonce( $post ) {
		if ( $post instanceof \WP_Post && Bike_CPT::POST_TYPE === $post->post_type ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		}
	}

	/**
	 * Add the 'closed' class to a postbox.
	 *
	 * @param string[] $classes Existing classes.
	 * @return string[]
	 */
	public function closed_class( $classes ) {
		$classes[] = 'closed';
		return $classes;
	}

	/**
	 * Render the identity / QR side box.
	 *
	 * @param \WP_Post $post Bike.
	 * @return void
	 */
	public function render_identity_box( $post ) {
		$internal_id = get_post_meta( $post->ID, 'internal_id', true );
		$qr_id       = (int) get_post_meta( $post->ID, 'qr_attachment_id', true );

		echo '<div class="tcw-identity">';

		if ( $internal_id ) {
			echo '<p><strong>' . esc_html__( 'Internal ID', 'tossa-workshop' ) . ':</strong><br>';
			echo '<code style="font-size:1.2em;">' . esc_html( $internal_id ) . '</code></p>';

			if ( $qr_id ) {
				$src = wp_get_attachment_image_url( $qr_id, 'medium' );
				if ( $src ) {
					echo '<p><img src="' . esc_url( $src ) . '" alt="" style="max-width:100%;height:auto;border:1px solid #ddd;" /></p>';
				}
			}

			echo '<p><a href="' . esc_url( QR_Generator::scan_url( $internal_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open scan URL', 'tossa-workshop' ) . '</a></p>';

			$print = wp_nonce_url(
				admin_url( 'admin.php?action=tcw_print_label&post=' . $post->ID ),
				'tcw_print_label_' . $post->ID
			);
			echo '<p><a class="button button-secondary" href="' . esc_url( $print ) . '" target="_blank" rel="noopener">' . esc_html__( 'Print label', 'tossa-workshop' ) . '</a></p>';

			$regen = wp_nonce_url(
				admin_url( 'admin.php?action=tcw_regenerate_qr&post=' . $post->ID ),
				'tcw_regenerate_qr_' . $post->ID
			);
			echo '<p><a class="button" href="' . esc_url( $regen ) . '">' . esc_html__( 'Regenerate QR', 'tossa-workshop' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'The internal ID and QR code are generated automatically when you first save this bike.', 'tossa-workshop' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render a section meta box.
	 *
	 * @param \WP_Post $post Bike.
	 * @param array    $metabox Meta box args (carries the section).
	 * @return void
	 */
	public function render_section_box( $post, $metabox ) {
		$section = $metabox['args']['section'];

		if ( ! empty( $section['help_intro'] ) ) {
			echo '<p class="description">' . esc_html( $section['help_intro'] ) . '</p>';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $section['fields'] as $field ) {
			Field_Kit::render_row( $post->ID, $field );
		}
		echo '</tbody></table>';
	}

	/**
	 * Persist bike fields, then generate the ID and QR on first save.
	 *
	 * @param int      $post_id Bike ID.
	 * @param \WP_Post $post    Bike.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( $this->saving ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitized — each value sanitized by Field_Kit.
		$posted = isset( $_POST['tcw_field'] ) && is_array( $_POST['tcw_field'] ) ? wp_unslash( $_POST['tcw_field'] ) : array();

		foreach ( Bike_Fields::all_fields() as $key => $field ) {
			$value = Field_Kit::persist_field( $post_id, $field, $posted );

			if ( 'gdpr_consent' === $key && $value && ! get_post_meta( $post_id, 'gdpr_consent_timestamp', true ) ) {
				update_post_meta( $post_id, 'gdpr_consent_timestamp', current_time( 'mysql' ) );
			}
		}

		// --- ID generation (first save only) -----------------------------
		// Only mint an ID once the bike type is explicitly valid, so a tampered
		// or empty type never silently assigns a customer ID to a fleet bike.
		$internal_id = get_post_meta( $post_id, 'internal_id', true );
		$type        = get_post_meta( $post_id, 'bike_type', true );
		if ( empty( $internal_id ) && in_array( $type, array( 'customer', 'fleet' ), true ) ) {
			$rentcat     = ( 'fleet' === $type ) ? get_post_meta( $post_id, 'rental_category', true ) : '';
			$internal_id = ID_Generator::generate_bike_id( $type, $rentcat );
			update_post_meta( $post_id, 'internal_id', $internal_id );
		}

		// --- QR generation (when missing) ---------------------------------
		$qr_id = (int) get_post_meta( $post_id, 'qr_attachment_id', true );
		if ( ! $qr_id && $internal_id ) {
			$new_qr = QR_Generator::generate_for_bike( $post_id, $internal_id );
			if ( ! is_wp_error( $new_qr ) ) {
				update_post_meta( $post_id, 'qr_attachment_id', $new_qr );
			}
		}

		// --- Auto title for readable admin lists --------------------------
		$brand = get_post_meta( $post_id, 'brand', true );
		$model = get_post_meta( $post_id, 'model', true );
		$name  = trim( $brand . ' ' . $model );
		if ( $internal_id && $name ) {
			$title = $name . ' (' . $internal_id . ')';
		} elseif ( $internal_id ) {
			$title = $internal_id;
		} else {
			$title = $name;
		}
		if ( '' !== $title && $title !== $post->post_title ) {
			$this->saving = true;
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $title,
				)
			);
			$this->saving = false;
		}
	}

	/**
	 * Enqueue media + the bike admin script/style on the bike edit screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Bike_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'tcw-admin-bike', TCW_PLUGIN_URL . 'assets/css/admin-bike.css', array(), TCW_VERSION );
		Assets::enqueue_media_js();
		wp_enqueue_script( 'tcw-admin-bike', TCW_PLUGIN_URL . 'assets/js/admin-bike.js', array( 'jquery' ), TCW_VERSION, true );
	}
}
