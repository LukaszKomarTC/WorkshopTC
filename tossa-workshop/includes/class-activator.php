<?php
/**
 * Activation routine.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once on plugin activation.
 */
class Activator {

	const VERSION_OPTION = 'tcw_version';

	/**
	 * Activate the plugin: roles, post types, taxonomy + terms, defaults,
	 * then flush rewrite rules.
	 *
	 * @return void
	 */
	public static function activate() {
		// Capabilities first so the current admin keeps access to the new CPTs.
		Roles::add_roles();

		// Register data model now — the init hook has not fired in the
		// activation request, but terms and rewrites need the registrations.
		Bike_CPT::instance()->register();
		Repair_Job_CPT::instance()->register();
		Job_Status_Taxonomy::instance()->register();

		// Seed the fixed status terms (idempotent).
		Job_Status_Taxonomy::insert_terms();

		// Ensure the settings option exists.
		if ( false === get_option( Admin\Settings_Page::OPTION_KEY, false ) ) {
			add_option( Admin\Settings_Page::OPTION_KEY, array(), '', 'no' );
		}

		update_option( self::VERSION_OPTION, TCW_VERSION );

		/**
		 * Fires after activation tasks complete, before rewrite flush.
		 */
		do_action( 'tcw_activated' );

		flush_rewrite_rules();
	}
}
