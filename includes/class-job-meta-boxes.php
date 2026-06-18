<?php
/**
 * Repair-job admin meta boxes: render, save, status pipeline, bike link,
 * client token + inherited contact (spec §3.2, §3.4, §4).
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the repair-job edit screen and persists its data.
 */
class Job_Meta_Boxes {

	const NONCE_ACTION = 'tcw_save_job';
	const NONCE_NAME   = 'tcw_job_nonce';

	/**
	 * Singleton instance.
	 *
	 * @var Job_Meta_Boxes|null
	 */
	private static $instance = null;

	/**
	 * Recursion guard for the auto-title update.
	 *
	 * @var bool
	 */
	private $saving = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Job_Meta_Boxes
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
		add_action( 'add_meta_boxes_' . Repair_Job_CPT::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Repair_Job_CPT::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'edit_form_top', array( $this, 'render_nonce' ) );
	}

	/**
	 * Output the save nonce at the top of the job edit form.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_nonce( $post ) {
		if ( $post instanceof \WP_Post && Repair_Job_CPT::POST_TYPE === $post->post_type ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		}
	}

	/**
	 * Register meta boxes; replace the default status tags box with our own.
	 *
	 * @param \WP_Post $post Current job.
	 * @return void
	 */
	public function add_meta_boxes( $post ) {
		remove_meta_box( 'tagsdiv-' . Job_Status_Taxonomy::TAXONOMY, Repair_Job_CPT::POST_TYPE, 'side' );

		add_meta_box(
			'tcw_job_status',
			__( 'Status & Client', 'tossa-workshop' ),
			array( $this, 'render_status_box' ),
			Repair_Job_CPT::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'tcw_job_bike',
			__( 'Bike', 'tossa-workshop' ),
			array( $this, 'render_bike_box' ),
			Repair_Job_CPT::POST_TYPE,
			'normal',
			'high'
		);

		foreach ( Repair_Job_Fields::sections() as $section ) {
			$box_id = 'tcw_job_' . $section['id'];
			add_meta_box(
				$box_id,
				$section['title'],
				array( $this, 'render_section_box' ),
				Repair_Job_CPT::POST_TYPE,
				'normal',
				'default',
				array( 'section' => $section )
			);
			if ( ! empty( $section['collapsed'] ) ) {
				add_filter(
					'postbox_classes_' . Repair_Job_CPT::POST_TYPE . '_' . $box_id,
					static function ( $classes ) {
						$classes[] = 'closed';
						return $classes;
					}
				);
			}
		}
	}

	/**
	 * Render the status + client side box.
	 *
	 * @param \WP_Post $post Job.
	 * @return void
	 */
	public function render_status_box( $post ) {
		$job_id  = $post->ID;
		$current = Job_Status::get( $job_id );
		$allowed = Job_Status::allowed_for_current_user();
		$labels  = Job_Status_Taxonomy::status_labels();
		$selected = $current ? $current : 'received';

		$job_number = get_post_meta( $job_id, 'job_id', true );

		echo '<div class="tcw-job-status">';

		if ( $job_number ) {
			echo '<p><strong>' . esc_html__( 'Job ID', 'tossa-workshop' ) . ':</strong><br><code style="font-size:1.2em;">' . esc_html( $job_number ) . '</code></p>';
		} else {
			echo '<p>' . esc_html__( 'The Job ID and client link are generated automatically on first save.', 'tossa-workshop' ) . '</p>';
		}

		echo '<p><label for="tcw_status"><strong>' . esc_html__( 'Status', 'tossa-workshop' ) . '</strong></label><br>';
		echo '<select id="tcw_status" name="tcw_status" style="width:100%;">';
		foreach ( Job_Status_Taxonomy::status_slugs() as $slug ) {
			$can = in_array( $slug, $allowed, true ) || $slug === $current;
			printf(
				'<option value="%1$s"%2$s%3$s>%4$s</option>',
				esc_attr( $slug ),
				selected( $selected, $slug, false ),
				$can ? '' : ' disabled',
				esc_html( isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug )
			);
		}
		echo '</select></p>';

		// Approval status (managed by the client page / inspection).
		$approval = get_post_meta( $job_id, 'approval_status', true );
		if ( $approval ) {
			echo '<p><strong>' . esc_html__( 'Approval', 'tossa-workshop' ) . ':</strong> ' . esc_html( $this->approval_label( $approval ) ) . '</p>';
		}

		// Linked bike shortcut.
		$bike_id = (int) get_post_meta( $job_id, 'bike_id', true );
		if ( $bike_id ) {
			$internal = get_post_meta( $bike_id, 'internal_id', true );
			echo '<p><strong>' . esc_html__( 'Bike', 'tossa-workshop' ) . ':</strong> <a href="' . esc_url( get_edit_post_link( $bike_id ) ) . '">' . esc_html( $internal ? $internal : ( '#' . $bike_id ) ) . '</a></p>';
		}

		// Client status link (page lands in M4; the token already exists).
		$token = get_post_meta( $job_id, 'client_token', true );
		if ( $token && $job_number ) {
			$url = Client_Status_Page::url( $job_number, $token );
			echo '<p><strong>' . esc_html__( 'Client link', 'tossa-workshop' ) . ':</strong><br><input type="text" readonly class="widefat" value="' . esc_attr( $url ) . '" onclick="this.select();" /></p>';
		}

		// Notifications: client email state + last send result, so it is clear
		// whether an email was attempted and whether wp_mail accepted it.
		$client_email = get_post_meta( $job_id, 'client_email', true );
		if ( $job_number ) {
			echo '<hr />';
			if ( ! $client_email ) {
				echo '<p class="description" style="color:#b32d2e;">' . esc_html__( 'No client email on file — notifications cannot be sent. Add an email to the bike owner.', 'tossa-workshop' ) . '</p>';
			} else {
				echo '<p class="description">' . esc_html__( 'Client email:', 'tossa-workshop' ) . ' ' . esc_html( $client_email ) . '</p>';

				$last = get_post_meta( $job_id, Notifier::LAST_META, true );
				if ( is_array( $last ) && ! empty( $last['timestamp'] ) ) {
					$mark = ! empty( $last['sent'] ) ? '✓' : '✗';
					echo '<p class="description">' . esc_html__( 'Last notification:', 'tossa-workshop' ) . ' ' . esc_html( $mark . ' ' . $last['status'] . ' → ' . $last['to'] . ' (' . $last['timestamp'] . ')' );
					if ( empty( $last['sent'] ) ) {
						echo '<br><span style="color:#b32d2e;">' . esc_html__( 'wp_mail reported failure — check the site email / SMTP setup.', 'tossa-workshop' ) . '</span>';
						if ( ! empty( $last['error'] ) ) {
							echo '<br><span style="color:#b32d2e;">' . esc_html__( 'Reason:', 'tossa-workshop' ) . ' ' . esc_html( $last['error'] ) . '</span>';
						}
					}
					echo '</p>';
				} else {
					echo '<p class="description">' . esc_html__( 'No notification sent yet for this job.', 'tossa-workshop' ) . '</p>';
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only flash.
				if ( isset( $_GET['tcw_resent'] ) ) {
					$ok = '1' === $_GET['tcw_resent']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					echo '<p class="description">' . ( $ok ? esc_html__( 'Notification resent.', 'tossa-workshop' ) : esc_html__( 'Resend failed — check the email/SMTP setup.', 'tossa-workshop' ) ) . '</p>';
				}

				$resend = wp_nonce_url(
					admin_url( 'admin.php?action=tcw_resend_notification&post=' . $job_id ),
					'tcw_resend_' . $job_id
				);
				echo '<p><a class="button" href="' . esc_url( $resend ) . '">' . esc_html__( 'Resend last notification', 'tossa-workshop' ) . '</a></p>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render the bike picker box.
	 *
	 * @param \WP_Post $post Job.
	 * @return void
	 */
	public function render_bike_box( $post ) {
		$bike_id  = (int) get_post_meta( $post->ID, 'bike_id', true );
		$label    = '';
		if ( $bike_id ) {
			$internal = get_post_meta( $bike_id, 'internal_id', true );
			$brand    = get_post_meta( $bike_id, 'brand', true );
			$model    = get_post_meta( $bike_id, 'model', true );
			$label    = trim( $internal . ' — ' . trim( $brand . ' ' . $model ) );
		}

		echo '<div class="tcw-bike-picker">';
		echo '<p class="description">' . esc_html__( 'Search by internal ID, serial number, or owner name / email / phone.', 'tossa-workshop' ) . '</p>';
		echo '<input type="hidden" id="tcw_bike_id" name="tcw_bike_id" value="' . esc_attr( (string) $bike_id ) . '" />';
		echo '<input type="text" id="tcw_bike_search" class="regular-text" autocomplete="off" placeholder="' . esc_attr__( 'Search bikes…', 'tossa-workshop' ) . '" value="' . esc_attr( $label ) . '" />';
		echo ' <button type="button" class="button tcw-bike-clear"' . ( $bike_id ? '' : ' style="display:none;"' ) . '>' . esc_html__( 'Clear', 'tossa-workshop' ) . '</button>';
		echo '<p class="tcw-bike-selected description">' . ( $label ? esc_html__( 'Selected:', 'tossa-workshop' ) . ' ' . esc_html( $label ) : '' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render a section meta box, respecting per-field capability gating.
	 *
	 * @param \WP_Post $post    Job.
	 * @param array    $metabox Meta box args.
	 * @return void
	 */
	public function render_section_box( $post, $metabox ) {
		$section = $metabox['args']['section'];

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $section['fields'] as $field ) {
			if ( ! empty( $field['cap'] ) && ! current_user_can( $field['cap'] ) ) {
				$this->render_readonly_row( $post->ID, $field );
				continue;
			}
			Field_Kit::render_row( $post->ID, $field );
		}
		echo '</tbody></table>';
	}

	/**
	 * Render a capability-gated field as read-only (visible, not editable).
	 *
	 * @param int   $post_id Job ID.
	 * @param array $field   Field definition.
	 * @return void
	 */
	private function render_readonly_row( $post_id, array $field ) {
		$value   = get_post_meta( $post_id, $field['key'], true );
		$display = ( is_scalar( $value ) && '' !== $value ) ? esc_html( (string) $value ) : '<span class="description">—</span>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html( $field['label'] ) . '</th>';
		echo '<td>' . $display . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped above.
		echo '</tr>';
	}

	/**
	 * Persist job fields, status, bike link, ID, token and inherited contact.
	 *
	 * @param int      $post_id Job ID.
	 * @param \WP_Post $post    Job.
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

		foreach ( Repair_Job_Fields::all_fields() as $field ) {
			// Skip price fields the user cannot edit (rendered read-only).
			if ( ! empty( $field['cap'] ) && ! current_user_can( $field['cap'] ) ) {
				continue;
			}
			Field_Kit::persist_field( $post_id, $field, $posted );
		}

		// --- Bike link ----------------------------------------------------
		if ( isset( $_POST['tcw_bike_id'] ) ) {
			$bike_id = absint( wp_unslash( $_POST['tcw_bike_id'] ) );
			if ( $bike_id && Bike_CPT::POST_TYPE === get_post_type( $bike_id ) ) {
				update_post_meta( $post_id, 'bike_id', $bike_id );
			} elseif ( 0 === $bike_id ) {
				delete_post_meta( $post_id, 'bike_id' );
			}
		}

		// --- ID + client token (first save) -------------------------------
		$job_number = get_post_meta( $post_id, 'job_id', true );
		if ( empty( $job_number ) ) {
			$job_number = ID_Generator::generate_job_id();
			update_post_meta( $post_id, 'job_id', $job_number );
		}
		if ( ! get_post_meta( $post_id, 'client_token', true ) ) {
			update_post_meta( $post_id, 'client_token', wp_generate_password( 32, false ) );
		}

		// --- Inherit client contact from the bike (once) ------------------
		$this->maybe_inherit_contact( $post_id );

		// --- Approval status follows the approval_required flag -----------
		$this->sync_approval_status( $post_id );

		// --- Status change (gated) ----------------------------------------
		if ( isset( $_POST['tcw_status'] ) ) {
			$new = sanitize_key( wp_unslash( $_POST['tcw_status'] ) );
			if ( in_array( $new, Job_Status::allowed_for_current_user(), true ) ) {
				Job_Status::set( $post_id, $new );
			}
		}

		// --- Auto title ---------------------------------------------------
		$this->update_title( $post_id, $post );
	}

	/**
	 * Copy client contact from the linked bike onto the job once, so historical
	 * notifications stay correct even if the bike owner later changes.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function maybe_inherit_contact( $job_id ) {
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
	 * Keep approval_status consistent with the approval_required flag without
	 * overriding a decision already made by the client.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function sync_approval_status( $job_id ) {
		$required = (bool) get_post_meta( $job_id, 'approval_required', true );
		$status   = get_post_meta( $job_id, 'approval_status', true );

		if ( $required ) {
			if ( ! in_array( $status, array( 'pending', 'approved', 'declined' ), true ) ) {
				update_post_meta( $job_id, 'approval_status', 'pending' );
			}
		} elseif ( ! in_array( $status, array( 'approved', 'declined' ), true ) ) {
			update_post_meta( $job_id, 'approval_status', 'not_required' );
		}
	}

	/**
	 * Set a readable post title from the job ID and linked bike.
	 *
	 * @param int      $job_id Job ID.
	 * @param \WP_Post $post   Job.
	 * @return void
	 */
	private function update_title( $job_id, $post ) {
		$job_number = get_post_meta( $job_id, 'job_id', true );
		$bike_id    = (int) get_post_meta( $job_id, 'bike_id', true );
		$internal   = $bike_id ? get_post_meta( $bike_id, 'internal_id', true ) : '';

		$title = $job_number;
		if ( $internal ) {
			$title .= ' — ' . $internal;
		}

		if ( '' !== $title && $title !== $post->post_title ) {
			$this->saving = true;
			wp_update_post(
				array(
					'ID'         => $job_id,
					'post_title' => $title,
				)
			);
			$this->saving = false;
		}
	}

	/**
	 * Human label for an approval status value.
	 *
	 * @param string $status Approval status.
	 * @return string
	 */
	private function approval_label( $status ) {
		$map = array(
			'not_required' => __( 'Not required', 'tossa-workshop' ),
			'pending'      => __( 'Pending', 'tossa-workshop' ),
			'approved'     => __( 'Approved', 'tossa-workshop' ),
			'declined'     => __( 'Declined', 'tossa-workshop' ),
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	/**
	 * Enqueue media + the job admin script/style on the job edit screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Repair_Job_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'tcw-admin-bike', TCW_PLUGIN_URL . 'assets/css/admin-bike.css', array(), TCW_VERSION );
		Assets::enqueue_media_js();
		wp_enqueue_script( 'tcw-admin-job', TCW_PLUGIN_URL . 'assets/js/admin-job.js', array( 'jquery', 'jquery-ui-autocomplete' ), TCW_VERSION, true );
		wp_localize_script(
			'tcw-admin-job',
			'tcwJob',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'searchNonce' => wp_create_nonce( Bike_Search::ACTION ),
				'action'      => Bike_Search::ACTION,
				'selected'    => __( 'Selected:', 'tossa-workshop' ),
			)
		);
	}
}
