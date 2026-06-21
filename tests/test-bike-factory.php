<?php
/**
 * Standalone tests for TossaWorkshop\Bike_Factory pure logic.
 *
 * Covers prepare_meta() sanitization/defaults and the invalid-type guard in
 * create(). The full create() happy path (ID + QR + media) is integration and
 * is verified on staging.
 *
 * Run: php tests/test-bike-factory.php
 *
 * @package TossaWorkshop
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function __( $s, $d = null ) { return $s; }
function _x( $s, $c, $d = null ) { return $s; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : ''; }
function sanitize_textarea_field( $s ) { return is_string( $s ) ? trim( $s ) : ''; }
function sanitize_email( $s ) { return is_string( $s ) ? trim( $s ) : ''; }
function esc_url_raw( $s ) { return is_string( $s ) ? trim( $s ) : ''; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $tag, $value ) { return $value; }
function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }

$GLOBALS['__inserts'] = 0;
function wp_insert_post( $args, $wp_error = false ) {
	$GLOBALS['__inserts']++;
	return 999;
}

require_once dirname( __DIR__ ) . '/includes/class-bike-fields.php';
require_once dirname( __DIR__ ) . '/includes/class-field-kit.php';
require_once dirname( __DIR__ ) . '/includes/class-bike-factory.php';

use TossaWorkshop\Bike_Factory;

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

echo "Bike_Factory\n";

// prepare_meta: valid values pass through, invalid select dropped, lang default.
$meta = Bike_Factory::prepare_meta(
	array(
		'bike_type'      => 'customer',
		'brand'          => '  Scott ',
		'model'          => 'Addict',
		'category'       => 'road',
		'owner_email'    => 'a@b.com',
		'owner_language' => 'de',
		'frame_size'     => 'M',
		'unknown_key'    => 'ignored',
	)
);
tcw_assert( 'bike_type kept', 'customer', $meta['bike_type'] );
tcw_assert( 'brand trimmed', 'Scott', $meta['brand'] );
tcw_assert( 'valid category kept', 'road', $meta['category'] );
tcw_assert( 'email kept', 'a@b.com', $meta['owner_email'] );
tcw_assert( 'language kept', 'de', $meta['owner_language'] );
tcw_assert( 'unknown key ignored', false, array_key_exists( 'unknown_key', $meta ) );

// Invalid category dropped to '' (Field_Kit select validation).
$meta2 = Bike_Factory::prepare_meta(
	array(
		'bike_type' => 'fleet',
		'category'  => 'spaceship',
	)
);
tcw_assert( 'invalid category -> empty', '', $meta2['category'] );

// Language defaults to es when absent.
$meta3 = Bike_Factory::prepare_meta( array( 'bike_type' => 'customer' ) );
tcw_assert( 'language defaults to es', 'es', $meta3['owner_language'] );

// create() rejects an invalid bike type without inserting a post.
$GLOBALS['__inserts'] = 0;
tcw_assert( 'invalid type returns 0', 0, Bike_Factory::create( array( 'brand' => 'X' ) ) );
tcw_assert( 'no post inserted for invalid type', 0, $GLOBALS['__inserts'] );

echo "\n{$tests} tests, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
