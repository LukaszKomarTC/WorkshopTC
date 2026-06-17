<?php
/**
 * Plugin Name:       Tossa Workshop
 * Plugin URI:        https://tossacycling.com/
 * Description:       Workshop management for Tossa Cycling: bikes with permanent internal IDs + QR codes, structured repair jobs, status pipeline, client notifications and token-gated status pages.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Tossa Cycling
 * Author URI:        https://tossacycling.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tossa-workshop
 * Domain Path:       /languages
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/*
 * -----------------------------------------------------------------------------
 * Constants
 * -----------------------------------------------------------------------------
 */
define( 'TCW_VERSION', '0.1.0' );
define( 'TCW_PLUGIN_FILE', __FILE__ );
define( 'TCW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TCW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*
 * -----------------------------------------------------------------------------
 * Autoloader
 * -----------------------------------------------------------------------------
 */
require_once TCW_PLUGIN_DIR . 'includes/class-autoloader.php';
Autoloader::register();

/*
 * -----------------------------------------------------------------------------
 * Activation / deactivation
 * -----------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Deactivator', 'deactivate' ) );

/*
 * -----------------------------------------------------------------------------
 * Bootstrap
 * -----------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->run();
	}
);
