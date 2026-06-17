<?php
/**
 * Notifications settings page: per-status on/off toggles and per-language
 * subject/body editors (spec §8).
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop\Admin;

use TossaWorkshop\Job_Status_Taxonomy;
use TossaWorkshop\Notification_Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Editor for client notification templates and toggles.
 */
class Notifications_Page {

	const MENU_SLUG  = 'tcw-notifications';
	const CAPABILITY = 'tcw_manage_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Notifications_Page|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Notifications_Page
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_tcw_save_notifications', array( $this, 'handle_save' ) );
	}

	/**
	 * Add the Notifications submenu under the Workshop menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			Settings_Page::MENU_SLUG,
			__( 'Notifications', 'tossa-workshop' ),
			__( 'Notifications', 'tossa-workshop' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * The language currently being edited (from the query string).
	 *
	 * @return string
	 */
	private function current_lang() {
		$lang = isset( $_GET['tcw_lang'] ) ? sanitize_key( wp_unslash( $_GET['tcw_lang'] ) ) : 'es'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only tab selection.
		return in_array( $lang, Notification_Templates::SHIPPED_LANGS, true ) ? $lang : 'es';
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tossa-workshop' ) );
		}

		$lang     = $this->current_lang();
		$labels   = Job_Status_Taxonomy::status_labels();
		$statuses = Job_Status_Taxonomy::status_slugs();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Workshop Notifications', 'tossa-workshop' ) . '</h1>';

		if ( isset( $_GET['tcw_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Notification settings saved.', 'tossa-workshop' ) . '</p></div>';
		}

		// Language tabs.
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( Notification_Templates::SHIPPED_LANGS as $code ) {
			$url = add_query_arg(
				array(
					'page'     => self::MENU_SLUG,
					'tcw_lang' => $code,
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( $url ),
				$code === $lang ? ' nav-tab-active' : '',
				esc_html( strtoupper( $code ) )
			);
		}
		echo '</h2>';

		echo '<p class="description">' . esc_html__( 'Placeholders:', 'tossa-workshop' ) . ' <code>' . esc_html( implode( '</code> <code>', Notification_Templates::PLACEHOLDERS ) ) . '</code></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — tokens are static literals.

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="tcw_save_notifications" />';
		echo '<input type="hidden" name="tcw_lang" value="' . esc_attr( $lang ) . '" />';
		wp_nonce_field( 'tcw_save_notifications', 'tcw_notify_nonce' );

		foreach ( $statuses as $status ) {
			$tpl     = Notification_Templates::get( $status, $lang );
			$enabled = Notification_Templates::is_enabled( $status );
			$label   = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

			echo '<h3 style="margin-bottom:4px;">' . esc_html( $label ) . ' <code style="font-weight:normal;">' . esc_html( $status ) . '</code></h3>';
			echo '<table class="form-table" role="presentation"><tbody>';

			echo '<tr><th scope="row">' . esc_html__( 'Send email', 'tossa-workshop' ) . '</th><td>';
			printf(
				'<label><input type="checkbox" name="enabled[%1$s]" value="1"%2$s /> %3$s</label>',
				esc_attr( $status ),
				checked( $enabled, true, false ),
				esc_html__( 'Notify the client when a job enters this status', 'tossa-workshop' )
			);
			echo '</td></tr>';

			echo '<tr><th scope="row"><label>' . esc_html__( 'Subject', 'tossa-workshop' ) . '</label></th><td>';
			printf(
				'<input type="text" class="large-text" name="tpl[%1$s][subject]" value="%2$s" />',
				esc_attr( $status ),
				esc_attr( $tpl['subject'] )
			);
			echo '</td></tr>';

			echo '<tr><th scope="row"><label>' . esc_html__( 'Body', 'tossa-workshop' ) . '</label></th><td>';
			printf(
				'<textarea class="large-text" rows="7" name="tpl[%1$s][body]">%2$s</textarea>',
				esc_attr( $status ),
				esc_textarea( $tpl['body'] )
			);
			echo '</td></tr>';

			echo '</tbody></table>';
		}

		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle the form submission.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tossa-workshop' ) );
		}
		if ( ! isset( $_POST['tcw_notify_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tcw_notify_nonce'] ) ), 'tcw_save_notifications' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'tossa-workshop' ) );
		}

		$lang = isset( $_POST['tcw_lang'] ) ? sanitize_key( wp_unslash( $_POST['tcw_lang'] ) ) : 'es';
		$lang = in_array( $lang, Notification_Templates::SHIPPED_LANGS, true ) ? $lang : 'es';

		$statuses = Job_Status_Taxonomy::status_slugs();

		// Enabled toggles (apply to all languages; unchecked = off).
		$posted_enabled = isset( $_POST['enabled'] ) && is_array( $_POST['enabled'] ) ? wp_unslash( $_POST['enabled'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitized
		$enabled        = array();
		foreach ( $statuses as $status ) {
			$enabled[ $status ] = ! empty( $posted_enabled[ $status ] );
		}
		update_option( Notification_Templates::ENABLED_OPTION, $enabled );

		// Templates for the edited language; store only values that differ from
		// the shipped default so future default improvements still apply.
		$defaults  = Notification_Templates::defaults();
		$overrides = get_option( Notification_Templates::TEMPLATES_OPTION, array() );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitized — sanitized per field below.
		$posted_tpl = isset( $_POST['tpl'] ) && is_array( $_POST['tpl'] ) ? wp_unslash( $_POST['tpl'] ) : array();

		foreach ( $statuses as $status ) {
			$subject = isset( $posted_tpl[ $status ]['subject'] ) ? sanitize_text_field( $posted_tpl[ $status ]['subject'] ) : '';
			$body    = isset( $posted_tpl[ $status ]['body'] ) ? sanitize_textarea_field( $posted_tpl[ $status ]['body'] ) : '';

			$def = isset( $defaults[ $lang ][ $status ] ) ? $defaults[ $lang ][ $status ] : $defaults[ $lang ]['_generic'];

			$store = array();
			if ( '' !== $subject && $subject !== $def['subject'] ) {
				$store['subject'] = $subject;
			}
			if ( '' !== $body && $body !== $def['body'] ) {
				$store['body'] = $body;
			}

			if ( $store ) {
				$overrides[ $lang ][ $status ] = $store;
			} elseif ( isset( $overrides[ $lang ][ $status ] ) ) {
				unset( $overrides[ $lang ][ $status ] );
			}
		}
		update_option( Notification_Templates::TEMPLATES_OPTION, $overrides );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::MENU_SLUG,
					'tcw_lang'  => $lang,
					'tcw_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
