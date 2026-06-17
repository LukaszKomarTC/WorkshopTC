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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the settings group, section and fields (Settings API).
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'tcw_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize' )
		);

		add_settings_section( 'tcw_general', __( 'General', 'tossa-workshop' ), '__return_false', self::MENU_SLUG );

		$fields = array(
			'public_base_url' => array( __( 'Public base URL', 'tossa-workshop' ), 'url', __( 'Used to build scan / status links. Leave empty to use the site URL.', 'tossa-workshop' ) ),
			'shop_phone'      => array( __( 'Shop phone', 'tossa-workshop' ), 'text', __( 'Shown on printed labels (and later on client communications).', 'tossa-workshop' ) ),
			'shop_address'    => array( __( 'Shop address', 'tossa-workshop' ), 'text', '' ),
		);

		foreach ( $fields as $key => $def ) {
			add_settings_field(
				$key,
				$def[0],
				array( $this, 'render_field' ),
				self::MENU_SLUG,
				'tcw_general',
				array(
					'key'  => $key,
					'type' => $def[1],
					'help' => $def[2],
				)
			);
		}
	}

	/**
	 * Render a settings field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_field( $args ) {
		$settings = self::get_settings();
		$value    = isset( $settings[ $args['key'] ] ) ? $settings[ $args['key'] ] : '';
		printf(
			'<input type="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />',
			esc_attr( 'url' === $args['type'] ? 'url' : 'text' ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $args['key'] ),
			esc_attr( $value )
		);
		if ( ! empty( $args['help'] ) ) {
			echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
		}
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		// Only accept an absolute http(s) base URL; otherwise fall back to
		// home_url() (stored as empty) so QR codes never encode a relative URL.
		$base = isset( $input['public_base_url'] ) ? esc_url_raw( trim( $input['public_base_url'] ) ) : '';
		if ( $base && ! preg_match( '#^https?://#i', $base ) ) {
			add_settings_error( self::OPTION_KEY, 'tcw_bad_url', __( 'The public base URL must start with http:// or https://. It has been cleared.', 'tossa-workshop' ) );
			$base = '';
		}

		return array(
			'public_base_url' => $base,
			'shop_phone'      => isset( $input['shop_phone'] ) ? sanitize_text_field( $input['shop_phone'] ) : '',
			'shop_address'    => isset( $input['shop_address'] ) ? sanitize_text_field( $input['shop_address'] ) : '',
		);
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
			<form action="options.php" method="post">
				<?php
				settings_fields( 'tcw_settings_group' );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
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
			// "use home_url()".
			'public_base_url' => '',
			// Shop contact details, used on labels and (later) emails/client page.
			'shop_phone'      => '',
			'shop_address'    => '',
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
