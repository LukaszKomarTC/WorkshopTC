<?php
/**
 * Standalone tests for TossaWorkshop\Job_Status transitions.
 *
 * Run: php tests/test-job-status.php
 *
 * @package TossaWorkshop
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['__terms']  = array();
$GLOBALS['__meta']   = array();
$GLOBALS['__fired']  = array();

function wp_get_object_terms( $id, $tax, $args = array() ) {
	return isset( $GLOBALS['__terms'][ $id ] ) ? $GLOBALS['__terms'][ $id ] : array();
}
function wp_set_object_terms( $id, $slug, $tax, $append = false ) {
	$GLOBALS['__terms'][ $id ] = (array) $slug;
	return array( 1 );
}
function is_wp_error( $thing ) {
	return $thing instanceof \WP_Error;
}
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['__meta'][ $id ][ $key ] ) ? $GLOBALS['__meta'][ $id ][ $key ] : '';
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['__meta'][ $id ][ $key ] = $value;
	return true;
}
function current_time( $type ) {
	return gmdate( 'Y-m-d H:i:s' );
}
function get_current_user_id() {
	return 7;
}
function do_action( $tag, ...$args ) {
	$GLOBALS['__fired'][] = array( $tag, $args );
}
function __( $s, $d = null ) {
	return $s;
}

require_once dirname( __DIR__ ) . '/includes/class-job-status-taxonomy.php';
require_once dirname( __DIR__ ) . '/includes/class-job-status.php';

use TossaWorkshop\Job_Status;

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

echo "Job_Status\n";

$job = 100;

// First transition: '' -> received.
$changed = Job_Status::set( $job, 'received' );
tcw_assert( 'first set returns changed', true, $changed );
tcw_assert( 'current is received', 'received', Job_Status::get( $job ) );

$history = $GLOBALS['__meta'][ $job ]['status_history'];
tcw_assert( 'history has one entry', 1, count( $history ) );
tcw_assert( 'history from empty', '', $history[0]['from'] );
tcw_assert( 'history to received', 'received', $history[0]['to'] );
tcw_assert( 'history records user', 7, $history[0]['user_id'] );

// Action fired with (job, from, to).
$last = end( $GLOBALS['__fired'] );
tcw_assert( 'action name', 'tcw_job_status_changed', $last[0] );
tcw_assert( 'action args', array( $job, '', 'received' ), $last[1] );

// Second transition appends history.
Job_Status::set( $job, 'inspected' );
tcw_assert( 'history has two entries', 2, count( $GLOBALS['__meta'][ $job ]['status_history'] ) );
tcw_assert( 'second from received', 'received', $GLOBALS['__meta'][ $job ]['status_history'][1]['from'] );

// No-op when status unchanged: no new history, no new action.
$before = count( $GLOBALS['__fired'] );
$noop   = Job_Status::set( $job, 'inspected' );
tcw_assert( 'no-op returns false', false, $noop );
tcw_assert( 'no extra action fired', $before, count( $GLOBALS['__fired'] ) );

// Invalid status rejected.
tcw_assert( 'invalid status rejected', false, Job_Status::set( $job, 'bogus_status' ) );

echo "\n{$tests} tests, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
