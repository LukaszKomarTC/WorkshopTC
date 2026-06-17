<?php
/**
 * PSR-4-ish autoloader mapping the TossaWorkshop namespace to includes/.
 *
 * Class `TossaWorkshop\Repair_Job_CPT` -> includes/class-repair-job-cpt.php
 * Class `TossaWorkshop\Admin\Settings_Page` -> includes/admin/class-settings-page.php
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Simple namespace-aware autoloader following WordPress file naming.
 */
class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 *
	 * @var string
	 */
	private const PREFIX = 'TossaWorkshop\\';

	/**
	 * Register the autoloader with the SPL stack.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Resolve and load a class file.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$segments = explode( '\\', $relative );
		$class    = array_pop( $segments );

		// Sub-namespaces map to lowercase sub-directories.
		$dir = TCW_PLUGIN_DIR . 'includes/';
		foreach ( $segments as $segment ) {
			$dir .= strtolower( str_replace( '_', '-', $segment ) ) . '/';
		}

		$file = $dir . 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
