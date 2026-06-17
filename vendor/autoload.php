<?php
/**
 * Minimal PSR-4 autoloader for the bundled chillerlan QR-code libraries.
 *
 * These libraries are vendored (not Composer-managed) so the plugin stays
 * self-contained. Only the two namespaces below are handled; everything else
 * is left to other autoloaders (e.g. the plugin's own in includes/).
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		$prefixes = array(
			'chillerlan\\QRCode\\'   => __DIR__ . '/chillerlan/php-qrcode/src/',
			'chillerlan\\Settings\\' => __DIR__ . '/chillerlan/php-settings-container/src/',
		);

		foreach ( $prefixes as $prefix => $base_dir ) {
			$len = strlen( $prefix );
			if ( 0 !== strncmp( $prefix, $class, $len ) ) {
				continue;
			}
			$relative = substr( $class, $len );
			$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
			return;
		}
	}
);
