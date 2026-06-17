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

		// Admin settings skeleton.
		if ( is_admin() ) {
			Admin\Settings_Page::instance()->register_hooks();
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
