<?php
/**
 * Activation smoke test: stubs the WordPress functions the plugin touches at
 * load + activation time, then boots the plugin and runs the activator to
 * surface fatals that `php -l` cannot (undefined classes, bad references, etc).
 *
 * Run: php tests/activation-smoke.php
 *
 * @package TossaWorkshop
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$plugin_main = dirname( __DIR__ ) . '/tossa-workshop.php';

// --- WP shims -------------------------------------------------------------
$GLOBALS['__opts']    = array();
$GLOBALS['__actions'] = array();

function plugin_dir_path( $f ) { return rtrim( dirname( $f ), '/' ) . '/'; }
function plugin_dir_url( $f ) { return 'http://example.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function register_activation_hook( $f, $cb ) { $GLOBALS['__activate'] = $cb; }
function register_deactivation_hook( $f, $cb ) { $GLOBALS['__deactivate'] = $cb; }
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__actions'][ $tag ][] = $cb; }
function do_action( $tag ) {}
function add_filter( $tag, $cb ) {}
function apply_filters( $tag, $value ) { return $value; }
function is_admin() { return true; }
function load_plugin_textdomain() { return true; }

function __( $s, $d = null ) { return $s; }
function _e( $s, $d = null ) { echo $s; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_html_e( $s, $d = null ) { echo $s; }
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }

function get_option( $k, $def = false ) { return array_key_exists( $k, $GLOBALS['__opts'] ) ? $GLOBALS['__opts'][ $k ] : $def; }
function add_option( $k, $v = '', $dep = '', $auto = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['__opts'] ) ) { return false; } $GLOBALS['__opts'][ $k ] = $v; return true; }
function update_option( $k, $v, $auto = null ) { $GLOBALS['__opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__opts'][ $k ] ); return true; }
function current_time( $t ) { return gmdate( $t ); }
function flush_rewrite_rules() {}

function register_post_type( $t, $args ) { return (object) array( 'name' => $t ); }
function register_taxonomy( $t, $obj, $args ) { return true; }
function term_exists( $t, $tax ) { return null; }
function wp_insert_term( $term, $tax, $args = array() ) { return array( 'term_id' => 1 ); }

class TCW_Role_Stub {
	public $caps = array();
	public function add_cap( $c ) { $this->caps[ $c ] = true; }
	public function remove_cap( $c ) { unset( $this->caps[ $c ] ); }
}
$GLOBALS['__roles'] = array( 'administrator' => new TCW_Role_Stub() );
function get_role( $slug ) { return $GLOBALS['__roles'][ $slug ] ?? null; }
function add_role( $slug, $name, $caps ) { $r = new TCW_Role_Stub(); $r->caps = $caps; $GLOBALS['__roles'][ $slug ] = $r; return $r; }
function remove_role( $slug ) { unset( $GLOBALS['__roles'][ $slug ] ); }
function add_menu_page() {}
function current_user_can() { return true; }
function wp_die( $m ) { throw new \RuntimeException( $m ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }

// --- Boot -----------------------------------------------------------------
echo "Loading main plugin file...\n";
require $plugin_main;
echo "  OK (no parse/load fatal)\n";

echo "Running activation hook...\n";
call_user_func( $GLOBALS['__activate'] );
echo "  OK (activator ran)\n";

echo "Booting plugin (plugins_loaded)...\n";
TossaWorkshop\Plugin::instance()->run();
echo "  OK (run)\n";

echo "Firing init actions...\n";
foreach ( $GLOBALS['__actions']['init'] ?? array() as $cb ) {
	call_user_func( $cb );
}
echo "  OK (init)\n";

echo "\nRoles registered: " . implode( ', ', array_keys( $GLOBALS['__roles'] ) ) . "\n";
echo 'Manager cap count: ' . count( $GLOBALS['__roles']['tcw_workshop_manager']->caps ?? array() ) . "\n";
echo "\nSMOKE TEST PASSED\n";
