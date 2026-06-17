<?php
/**
 * Minimal WordPress function/$wpdb stubs so pure-logic classes can be
 * exercised without a WordPress runtime. Used by the standalone test scripts
 * in this directory (run with `php tests/test-*.php`).
 *
 * @package TossaWorkshop
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['__tcw_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['__tcw_options'] ) ? $GLOBALS['__tcw_options'][ $key ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['__tcw_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	// Mirrors WP: returns false when the option already exists (atomic INSERT).
	function add_option( $key, $value = '', $deprecated = '', $autoload = 'yes' ) {
		if ( array_key_exists( $key, $GLOBALS['__tcw_options'] ) ) {
			return false;
		}
		$GLOBALS['__tcw_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['__tcw_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return gmdate( $type );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

/**
 * Stub of $wpdb covering the single query ID_Generator runs.
 */
class TCW_WPDB_Stub {
	public $postmeta = 'wp_postmeta';
	public $options  = 'wp_options';

	/** @var string[] Meta values considered already present. */
	public $existing = array();

	private $last_value = null;

	public function prepare( $query, ...$args ) {
		// ID_Generator passes ( meta_key, meta_value ).
		$this->last_value = isset( $args[1] ) ? $args[1] : null;
		return $query;
	}

	public function get_var( $query ) {
		return in_array( $this->last_value, $this->existing, true ) ? '1' : null;
	}
}

$GLOBALS['wpdb'] = new TCW_WPDB_Stub();

require_once dirname( __DIR__ ) . '/includes/class-id-generator.php';
