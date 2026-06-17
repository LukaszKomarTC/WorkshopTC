<?php
/**
 * Verifies the bundled chillerlan QR library produces a valid PNG.
 *
 * Run: php tests/test-qr-generation.php
 *
 * @package TossaWorkshop
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;

$data = 'https://tossacycling.com/workshop-scan/?id=TCB-000001';

$options = new QROptions(
	array(
		'version'      => QRCode::VERSION_AUTO,
		'outputType'   => QROutputInterface::GDIMAGE_PNG,
		'eccLevel'     => EccLevel::M,
		'scale'        => 6,
		'outputBase64' => false,
		'imageBase64'  => false,
	)
);

$png = ( new QRCode( $options ) )->render( $data );

$ok = is_string( $png ) && strlen( $png ) > 100 && "\x89PNG" === substr( $png, 0, 4 );
echo $ok ? "PASS: render returned PNG bytes (" . strlen( $png ) . " bytes)\n" : "FAIL: not a PNG\n";

$img = @imagecreatefromstring( $png );
if ( false !== $img ) {
	printf( "PASS: GD decoded PNG, %dx%d px\n", imagesx( $img ), imagesy( $img ) );
	$decoded = true;
} else {
	echo "FAIL: GD could not decode PNG\n";
	$decoded = false;
}

exit( ( $ok && $decoded ) ? 0 : 1 );
