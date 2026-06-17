<?php
/**
 * Extract translatable strings (text domain tossa-workshop) into a .pot file.
 *
 * Deliberately small: the plugin uses a consistent calling style
 * ( __( 'text', 'tossa-workshop' ) and friends ) with single-quoted literals,
 * so a focused regex extractor is sufficient and dependency-free.
 *
 * Run: php tools/i18n-extract.php
 *
 * @package TossaWorkshop
 */

$root = dirname( __DIR__ );

$dirs  = array( $root . '/includes', $root . '/templates' );
$files = array( $root . '/tossa-workshop.php' );

foreach ( $dirs as $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( 'php' === strtolower( $f->getExtension() ) ) {
			$files[] = $f->getPathname();
		}
	}
}

// Functions whose FIRST string arg is the msgid.
$simple = '__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_attr_x';
// _x and _ex: ( 'text', 'context', 'domain' ).
$ctx = '_x|_ex';

$strings = array(); // msgid => array of "file:line" references.

foreach ( array_unique( $files ) as $file ) {
	$code = file_get_contents( $file );
	$rel  = str_replace( $root . '/', '', $file );

	// Simple: fn( 'msgid' , 'tossa-workshop' )
	if ( preg_match_all(
		'/(?:' . $simple . ')\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'tossa-workshop\'/',
		$code,
		$m,
		PREG_OFFSET_CAPTURE
	) ) {
		foreach ( $m[1] as $hit ) {
			$line = substr_count( substr( $code, 0, $hit[1] ), "\n" ) + 1;
			$strings[ $hit[0] ][] = $rel . ':' . $line;
		}
	}

	// Context: _x( 'msgid', 'context', 'tossa-workshop' )
	if ( preg_match_all(
		'/(?:' . $ctx . ')\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'tossa-workshop\'/',
		$code,
		$m,
		PREG_OFFSET_CAPTURE
	) ) {
		foreach ( $m[1] as $idx => $hit ) {
			$key  = $m[2][ $idx ][0] . "\x04" . $hit[0]; // context\x04msgid
			$line = substr_count( substr( $code, 0, $hit[1] ), "\n" ) + 1;
			$strings[ $key ][] = $rel . ':' . $line;
		}
	}
}

ksort( $strings );

$out  = "# Tossa Workshop - translation template\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"Project-Id-Version: Tossa Workshop\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"Language: \\n\"\n\n";

foreach ( $strings as $key => $refs ) {
	$out .= '#: ' . implode( ' ', array_unique( $refs ) ) . "\n";
	if ( false !== strpos( $key, "\x04" ) ) {
		list( $context, $msgid ) = explode( "\x04", $key, 2 );
		$out .= 'msgctxt "' . $context . "\"\n";
	} else {
		$msgid = $key;
	}
	$out .= 'msgid "' . $msgid . "\"\n";
	$out .= "msgstr \"\"\n\n";
}

@mkdir( $root . '/languages', 0755, true );
file_put_contents( $root . '/languages/tossa-workshop.pot', $out );

echo 'Extracted ' . count( $strings ) . " strings to languages/tossa-workshop.pot\n";
