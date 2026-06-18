<?php
/**
 * Standalone tests for TossaWorkshop\Client_Status_Copy.
 *
 * Run: php tests/test-client-copy.php
 *
 * @package TossaWorkshop
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/class-client-status-copy.php';

use TossaWorkshop\Client_Status_Copy;

$failures = 0;
$tests    = 0;

function tcw_assert( $label, $expected, $actual ) {
	global $failures, $tests;
	$tests++;
	if ( $expected === $actual ) {
		echo "  PASS: {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL: {$label}\n    expected: " . var_export( $expected, true ) . "\n    actual:   " . var_export( $actual, true ) . "\n";
}

echo "Client_Status_Copy\n";

tcw_assert( 'shipped lang kept', 'de', Client_Status_Copy::lang( 'de' ) );
tcw_assert( 'unknown lang -> es', 'es', Client_Status_Copy::lang( 'ca' ) );
tcw_assert( 'empty lang -> es', 'es', Client_Status_Copy::lang( '' ) );

$en = Client_Status_Copy::status( 'ready', 'en' );
tcw_assert( 'en ready label', 'Ready for pickup', $en['label'] );

$de = Client_Status_Copy::status( 'received', 'de' );
tcw_assert( 'de received label', 'Erhalten', $de['label'] );

// ca falls back to es content.
$ca = Client_Status_Copy::status( 'ready', 'ca' );
$es = Client_Status_Copy::status( 'ready', 'es' );
tcw_assert( 'ca status falls back to es', $es['label'], $ca['label'] );

// Unknown status -> slug, empty desc.
$unk = Client_Status_Copy::status( 'bogus', 'en' );
tcw_assert( 'unknown status label = slug', 'bogus', $unk['label'] );
tcw_assert( 'unknown status desc empty', '', $unk['desc'] );

// Labels.
tcw_assert( 'en approve label', 'Approve', Client_Status_Copy::t( 'approve', 'en' ) );
tcw_assert( 'es approve label', 'Aprobar', Client_Status_Copy::t( 'approve', 'es' ) );
tcw_assert( 'unknown label key returns key', 'nope', Client_Status_Copy::t( 'nope', 'en' ) );

echo "\n{$tests} tests, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
