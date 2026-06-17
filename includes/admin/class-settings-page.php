<?php
/**
 * Settings page skeleton.
 *
 * M0 ships the page shell, the menu entry and the option storage so later
 * milestones can hang their settings off it:
 *  - M1 adds the public base URL override (defaults to home_url()).
 *  - M3 adds per-status / per-language notification templates and toggles.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop\Admin;

use TossaWorkshop\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Workshop settings menu and renders the page.
 */
class Settings_Page {

	const MENU_SLUG  = 'tcw-settings';
	const OPTION_KEY = 'tcw_settings';
	const CAPABILITY = 'tcw_manage_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Settings_Page|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Settings_Page
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
	}

	/**
	 * Add the settings menu page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Workshop Settings', 'tossa-workshop' ),
			__( 'Workshop', 'tossa-workshop' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-admin-tools',
			28
		);
	}

	/**
	 * Render the settings page shell.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access workshop settings.', 'tossa-workshop' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tossa Workshop', 'tossa-workshop' ); ?></h1>
			<p>
				<?php esc_html_e( 'Workshop settings will appear here as features are added (public URLs, notification templates, status toggles).', 'tossa-workshop' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Retrieve the stored settings array with defaults applied.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			// Public base URL used to build scan / status links. Empty means
			// "use home_url()". Surfaced as an editable field in M1.
			'public_base_url' => '',
		);

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/**
		 * Filter the resolved workshop settings.
		 *
		 * @param array $settings Settings array.
		 */
		return apply_filters( 'tcw_settings', wp_parse_args( $stored, $defaults ) );
	}
}
