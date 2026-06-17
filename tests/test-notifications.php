<?php
/**
 * Standalone tests for TossaWorkshop\Notification_Templates.
 *
 * Run: php tests/test-notifications.php
 *
 * @package TossaWorkshop
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['__opts'] = array();
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['__opts'] ) ? $GLOBALS['__opts'][ $k ] : $d;
}
function __( $s, $d = null ) {
	return $s;
}

require_once dirname( __DIR__ ) . '/includes/class-job-status-taxonomy.php';
require_once dirname( __DIR__ ) . '/includes/class-notification-templates.php';

use TossaWorkshop\Notification_Templates;

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

echo "Notification_Templates\n";

// Placeholder rendering.
$out = Notification_Templates::render(
	'Hi {client_name}, your {bike} ref {job_id}: {status_url}',
	array(
		'client_name' => 'Ana',
		'bike'        => 'Orbea Orca (TCB-000001)',
		'job_id'      => 'TCW-2026-0001',
		'status_url'  => 'https://x/y',
	)
);
tcw_assert( 'placeholders substituted', 'Hi Ana, your Orbea Orca (TCB-000001) ref TCW-2026-0001: https://x/y', $out );

// Default enabled set.
$en = Notification_Templates::default_enabled();
tcw_assert( 'received enabled by default', true, $en['received'] );
tcw_assert( 'waiting_approval enabled by default', true, $en['waiting_approval'] );
tcw_assert( 'ready enabled by default', true, $en['ready'] );
tcw_assert( 'in_repair disabled by default', false, $en['in_repair'] );

// is_enabled honours stored overrides.
$GLOBALS['__opts'][ Notification_Templates::ENABLED_OPTION ] = array( 'received' => false, 'in_repair' => true );
tcw_assert( 'stored override turns received off', false, Notification_Templates::is_enabled( 'received' ) );
tcw_assert( 'stored override turns in_repair on', true, Notification_Templates::is_enabled( 'in_repair' ) );
tcw_assert( 'unset status falls back to default (ready)', true, Notification_Templates::is_enabled( 'ready' ) );
$GLOBALS['__opts'] = array();

// Language resolution + generic fallback.
$es = Notification_Templates::get( 'received', 'es' );
tcw_assert( 'es received subject', 'Hemos recibido tu bici — {job_id}', $es['subject'] );

$de = Notification_Templates::get( 'in_repair', 'de' ); // No bespoke -> generic.
tcw_assert( 'de in_repair uses generic', 'Update zu deiner Reparatur — {job_id}', $de['subject'] );

// Unknown language (ca) falls back to Spanish.
$ca = Notification_Templates::get( 'received', 'ca' );
tcw_assert( 'ca falls back to es', $es['subject'], $ca['subject'] );

// Stored override wins.
$GLOBALS['__opts'][ Notification_Templates::TEMPLATES_OPTION ] = array(
	'es' => array( 'received' => array( 'subject' => 'Custom subject {job_id}' ) ),
);
$ov = Notification_Templates::get( 'received', 'es' );
tcw_assert( 'override subject wins', 'Custom subject {job_id}', $ov['subject'] );
tcw_assert( 'override keeps default body', $es['body'], $ov['body'] );
$GLOBALS['__opts'] = array();

echo "\n{$tests} tests, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
