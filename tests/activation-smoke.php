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
function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {}
function register_setting( $group, $name, $args = array() ) {}
function add_settings_section( $id, $title, $cb, $page ) {}
function add_settings_field( $id, $title, $cb, $page, $section = 'default', $args = array() ) {}
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function __return_false() { return false; }

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

// Field catalog integrity (guards the array_merge fix).
$all = TossaWorkshop\Bike_Fields::all_fields();
echo 'Bike fields defined: ' . count( $all ) . "\n";
$required = array( 'bike_type', 'brand', 'model', 'tubeless', 'stem', 'odometer', 'shock_travel', 'rental_category' );
foreach ( $required as $k ) {
	if ( ! isset( $all[ $k ] ) ) {
		echo "FAIL: missing field '{$k}' (array_merge regression?)\n";
		exit( 1 );
	}
}
echo "Field catalog: all spot-checked keys present\n";

// Scan URL building.
$url = TossaWorkshop\QR_Generator::scan_url( 'TCB-000001' );
echo 'Scan URL: ' . $url . "\n";
if ( false === strpos( $url, '/workshop-scan/?id=TCB-000001' ) ) {
	echo "FAIL: scan URL malformed\n";
	exit( 1 );
}

// Repair-job field catalog.
$jobfields = TossaWorkshop\Repair_Job_Fields::all_fields();
echo 'Repair-job fields defined: ' . count( $jobfields ) . "\n";
foreach ( array( 'priority', 'date_received', 'accessories_received', 'quality_check', 'final_price', 'estimate_amount' ) as $k ) {
	if ( ! isset( $jobfields[ $k ] ) ) {
		echo "FAIL: missing job field '{$k}'\n";
		exit( 1 );
	}
}
// bike_id and status are handled specially, NOT generic fields.
if ( isset( $jobfields['bike_id'] ) || isset( $jobfields['status'] ) ) {
	echo "FAIL: bike_id/status must not be generic fields\n";
	exit( 1 );
}
echo "Job catalog: spot-checked keys present, bike_id/status excluded\n";

// Linear pipeline length.
$linear = TossaWorkshop\Job_Status_Taxonomy::linear_statuses();
echo 'Linear pipeline statuses: ' . count( $linear ) . "\n";
if ( 11 !== count( $linear ) || 'quality_check' !== $linear[7] ) {
	echo "FAIL: linear pipeline wrong\n";
	exit( 1 );
}

// New gating cap granted to front desk, withheld from mechanic.
$fd  = $GLOBALS['__roles']['tcw_front_desk']->caps ?? array();
$mec = $GLOBALS['__roles']['tcw_mechanic']->caps ?? array();
if ( empty( $fd['tcw_advance_status_full'] ) ) {
	echo "FAIL: front desk missing tcw_advance_status_full\n";
	exit( 1 );
}
if ( ! empty( $mec['tcw_advance_status_full'] ) ) {
	echo "FAIL: mechanic should not have tcw_advance_status_full\n";
	exit( 1 );
}
echo "Status gating cap: front desk yes, mechanic no\n";

echo "\nSMOKE TEST PASSED\n";
