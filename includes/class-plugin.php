<?php
/**
 * Main plugin orchestrator.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Wires together the plugin modules. One instance per request.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether run() has already executed.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton).
	 */
	private function __construct() {}

	/**
	 * Boot the plugin: register all runtime hooks.
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Data model: CPTs + taxonomy + terms. Registered on every load.
		Bike_CPT::instance()->register_hooks();
		Repair_Job_CPT::instance()->register_hooks();
		Job_Status_Taxonomy::instance()->register_hooks();

		// Front-end routers.
		Scan_Router::instance()->register_hooks();
		Client_Status_Page::instance()->register_hooks();

		// Notifications listener (must run on front-end too, e.g. client
		// approval in M4 fires a status change).
		Notifier::instance()->register_hooks();

		// GDPR export/erase integration (runs during privacy requests).
		Privacy::instance()->register_hooks();

		// Admin-only modules.
		if ( is_admin() ) {
			Admin\Settings_Page::instance()->register_hooks();
			Admin\Notifications_Page::instance()->register_hooks();
			Bike_Meta_Boxes::instance()->register_hooks();
			Print_Label::instance()->register_hooks();
			Job_Meta_Boxes::instance()->register_hooks();
			Bike_Search::instance()->register_hooks();
			Admin\Jobs_List::instance()->register_hooks();
			Admin\Bikes_List::instance()->register_hooks();
			Admin\Fleet_Check::instance()->register_hooks();
			Admin\Help_Page::instance()->register_hooks();
		}
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'tossa-workshop',
			false,
			dirname( TCW_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
