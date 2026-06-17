<?php
/**
 * Deactivation routine.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once on plugin deactivation.
 *
 * Deactivation is deliberately non-destructive: roles, CPT data, terms and
 * counters are preserved. Only transient/rewrite state is cleared. Permanent
 * cleanup belongs in uninstall.php.
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		/**
		 * Fires at the start of deactivation.
		 */
		do_action( 'tcw_deactivated' );

		// Clear rewrite rules registered by the plugin's endpoints.
		flush_rewrite_rules();
	}
}
