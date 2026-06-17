<?php
/**
 * Standalone tests for TossaWorkshop\ID_Generator.
 *
 * Run: php tests/test-id-generator.php
 *
 * @package TossaWorkshop
 */

require_once __DIR__ . '/bootstrap-stubs.php';

use TossaWorkshop\ID_Generator;

$failures = 0;
$tests    = 0;

/**
 * Tiny assertion helper.
 *
 * @param string $label    Test label.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function tcw_assert( $label, $expected, $actual ) {
	global $failures, $tests;
	$tests++;
	if ( $expected === $actual ) {
		echo "  PASS: {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL: {$label}\n";
	echo '        expected: ' . var_export( $expected, true ) . "\n";
	echo '        actual:   ' . var_export( $actual, true ) . "\n";
}

echo "ID_Generator\n";

// Customer bike sequence + format.
$GLOBALS['__tcw_options'] = array();
tcw_assert( 'first customer bike', 'TCB-000001', ID_Generator::generate_bike_id( 'customer' ) );
tcw_assert( 'second customer bike', 'TCB-000002', ID_Generator::generate_bike_id( 'customer' ) );

// Fleet bike per-category counter + uppercase category.
$GLOBALS['__tcw_options'] = array();
tcw_assert( 'first emtb fleet', 'TCF-EMTB-001', ID_Generator::generate_bike_id( 'fleet', 'emtb' ) );
tcw_assert( 'second emtb fleet', 'TCF-EMTB-002', ID_Generator::generate_bike_id( 'fleet', 'emtb' ) );
tcw_assert( 'first road fleet (separate counter)', 'TCF-ROAD-001', ID_Generator::generate_bike_id( 'fleet', 'road' ) );

// Job ID with explicit year + 4-digit padding + per-year reset.
$GLOBALS['__tcw_options'] = array();
tcw_assert( 'first 2026 job', 'TCW-2026-0001', ID_Generator::generate_job_id( 2026 ) );
tcw_assert( 'second 2026 job', 'TCW-2026-0002', ID_Generator::generate_job_id( 2026 ) );
tcw_assert( 'first 2027 job (year reset)', 'TCW-2027-0001', ID_Generator::generate_job_id( 2027 ) );

// Uniqueness: skip a value that already exists in meta.
$GLOBALS['__tcw_options'] = array();
$GLOBALS['wpdb']->existing = array( 'TCB-000001' );
tcw_assert( 'skips existing internal_id', 'TCB-000002', ID_Generator::generate_bike_id( 'customer' ) );
$GLOBALS['wpdb']->existing = array();

// Fleet with empty category falls back to GEN.
$GLOBALS['__tcw_options'] = array();
tcw_assert( 'empty fleet category -> GEN', 'TCF-GEN-001', ID_Generator::generate_bike_id( 'fleet', '' ) );

echo "\n{$tests} tests, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
