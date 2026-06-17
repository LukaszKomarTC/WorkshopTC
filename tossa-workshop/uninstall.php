<?php
/**
 * Uninstall routine.
 *
 * Runs when the plugin is deleted from the WordPress admin. Removes the
 * plugin's roles, capabilities and option rows. CPT content (bikes, jobs,
 * media) is intentionally preserved unless the site owner removes it, so an
 * accidental delete does not destroy workshop history.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-roles.php';

Roles::remove_roles();

// Remove settings + version flag.
delete_option( 'tcw_settings' );
delete_option( 'tcw_version' );

// Remove all counter options (tcw_counter_*) and any leftover locks.
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tcw_counter_%'"
);
