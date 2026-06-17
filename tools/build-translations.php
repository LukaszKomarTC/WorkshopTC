<?php
/**
 * Build languages/tossa-workshop-es_ES.po and .mo from the .pot + the ES map
 * below. Dependency-free (no gettext binaries required).
 *
 * Run: php tools/i18n-extract.php && php tools/build-translations.php
 *
 * @package TossaWorkshop
 */

$root = dirname( __DIR__ );
$pot  = $root . '/languages/tossa-workshop.pot';

if ( ! is_readable( $pot ) ) {
	fwrite( STDERR, "Run tools/i18n-extract.php first.\n" );
	exit( 1 );
}

/**
 * Spanish (es_ES) translations. Keys are the msgid, or "context\x04msgid"
 * for strings registered with _x().
 */
$es = array(
	'(required)' => '(obligatorio)',
	'A timestamp is recorded automatically when first checked.' => 'Se registra automáticamente la fecha y hora al marcarlo por primera vez.',
	'Accident history' => 'Historial de accidentes',
	'Add New' => 'Añadir nuevo',
	'Add New Bike' => 'Añadir bicicleta',
	'Add New Repair Job' => 'Añadir trabajo de reparación',
	'Add New Status' => 'Añadir estado',
	'Add photos' => 'Añadir fotos',
	'Add photos and optionally label each (left, right, drivetrain, cockpit, damage, before/after).' => 'Añade fotos y, opcionalmente, etiqueta cada una (izquierda, derecha, transmisión, cabina, daño, antes/después).',
	'All Bikes' => 'Todas las bicicletas',
	'All Repair Jobs' => 'Todos los trabajos de reparación',
	'All Statuses' => 'Todos los estados',
	'Aluminium' => 'Aluminio',
	'Approved' => 'Aprobado',
	'Attention needed' => 'Requiere atención',
	'Basic Details' => 'Datos básicos',
	'Battery Wh' => 'Batería (Wh)',
	'Battery brand' => 'Marca de la batería',
	'Battery health (%)' => 'Salud de la batería (%)',
	'Battery model' => 'Modelo de la batería',
	'Battery serial' => 'Nº de serie de la batería',
	'Bike %s. Please contact the shop for details.' => 'Bicicleta %s. Contacta con la tienda para más información.',
	'Bike type' => 'Tipo de bicicleta',
	'Brake pad type' => 'Tipo de pastillas de freno',
	'Brakes' => 'Frenos',
	'Brand' => 'Marca',
	'Cancelled' => 'Cancelado',
	'Carbon' => 'Carbono',
	'Cargo' => 'Carga',
	'Cassette' => 'Casete',
	'Catalan' => 'Catalán',
	'Category' => 'Categoría',
	'Chain' => 'Cadena',
	'Charger' => 'Cargador',
	'City' => 'Ciudad',
	'Closed' => 'Cerrado',
	'Colour' => 'Color',
	'Components' => 'Componentes',
	'Condition rating' => 'Valoración del estado',
	'Could not render the QR image.' => 'No se pudo generar la imagen QR.',
	'Could not store the QR attachment.' => 'No se pudo guardar el archivo QR.',
	'Crankset' => 'Bielas',
	'Customer bike' => 'Bicicleta de cliente',
	'Declined' => 'Rechazado',
	'Delivered' => 'Entregado',
	'Derailleurs' => 'Cambios',
	'Display model' => 'Modelo del display',
	'Drivetrain brand' => 'Marca de la transmisión',
	'Drivetrain model' => 'Modelo de la transmisión',
	'E-Gravel' => 'E-Gravel',
	'E-MTB' => 'E-MTB',
	'E-Road' => 'E-Road',
	'E-bike' => 'E-bike',
	'Edit Bike' => 'Editar bicicleta',
	'Edit Repair Job' => 'Editar trabajo de reparación',
	'Edit Status' => 'Editar estado',
	'English' => 'Inglés',
	'Excellent' => 'Excelente',
	'Fair' => 'Regular',
	'Firmware' => 'Firmware',
	'Fleet' => 'Flota',
	'Fleet bike' => 'Bicicleta de flota',
	'Fleet number' => 'Número de flota',
	'Fork brand' => 'Marca de la horquilla',
	'Fork model' => 'Modelo de la horquilla',
	'Fork serial' => 'Nº de serie de la horquilla',
	'Fork travel' => 'Recorrido de la horquilla',
	'Frame material' => 'Material del cuadro',
	'Frame size' => 'Talla del cuadro',
	'French' => 'Francés',
	'GDPR consent given' => 'Consentimiento RGPD otorgado',
	'General' => 'General',
	'German' => 'Alemán',
	'Good' => 'Bueno',
	'Gravel' => 'Gravel',
	'Handlebar' => 'Manillar',
	'Identification' => 'Identificación',
	'In Repair' => 'En reparación',
	'Inspected' => 'Inspeccionado',
	'Internal ID' => 'ID interno',
	'Internal Notes' => 'Notas internas',
	'Invalid request.' => 'Solicitud no válida.',
	'Kids' => 'Infantil',
	'Label' => 'Etiqueta',
	'Last brake-pad change' => 'Último cambio de pastillas',
	'Last cassette change' => 'Último cambio de casete',
	'Last chain change' => 'Último cambio de cadena',
	'Last rental date' => 'Última fecha de alquiler',
	'Last service date' => 'Fecha del último mantenimiento',
	'Last suspension service' => 'Último mantenimiento de la suspensión',
	'Last tyre change' => 'Último cambio de neumáticos',
	'Linked WordPress/WooCommerce user' => 'Usuario de WordPress/WooCommerce vinculado',
	'MTB' => 'MTB',
	'Maintenance Summary' => 'Resumen de mantenimiento',
	'Mechanic notes' => 'Notas del mecánico',
	'Model' => 'Modelo',
	'Motor brand' => 'Marca del motor',
	'Motor model' => 'Modelo del motor',
	'Motor serial' => 'Nº de serie del motor',
	'New Bike' => 'Nueva bicicleta',
	'New Repair Job' => 'Nuevo trabajo de reparación',
	'Next service due' => 'Próximo mantenimiento',
	'No bikes found' => 'No se encontraron bicicletas',
	'No bikes found in Trash' => 'No se encontraron bicicletas en la papelera',
	'No repair jobs found' => 'No se encontraron trabajos de reparación',
	'No repair jobs found in Trash' => 'No se encontraron trabajos de reparación en la papelera',
	'Odometer' => 'Odómetro',
	'Open scan URL' => 'Abrir URL de escaneo',
	'Other' => 'Otro',
	'Owner / Client' => 'Propietario / Cliente',
	'Owner email' => 'Correo del propietario',
	'Owner name' => 'Nombre del propietario',
	'Owner phone' => 'Teléfono del propietario',
	'Parts Arrived' => 'Piezas recibidas',
	'Pedals' => 'Pedales',
	'Photo gallery' => 'Galería de fotos',
	'Photos' => 'Fotos',
	'Poor' => 'Malo',
	'Preferred language' => 'Idioma preferido',
	'Print' => 'Imprimir',
	'Print label' => 'Imprimir etiqueta',
	'Public base URL' => 'URL base pública',
	'QR code %s' => 'Código QR %s',
	'Quality Check' => 'Control de calidad',
	'Ready for Pickup' => 'Listo para recoger',
	'Received' => 'Recibido',
	'Recurring problems' => 'Problemas recurrentes',
	'Regenerate QR' => 'Regenerar QR',
	'Remove' => 'Eliminar',
	'Rental active' => 'Alquiler activo',
	'Rental category' => 'Categoría de alquiler',
	'Rental days count' => 'Número de días de alquiler',
	'Replacement planning' => 'Planificación de reemplazo',
	'Road' => 'Carretera',
	'Rotor sizes' => 'Tamaño de los discos',
	'Saddle' => 'Sillín',
	'Safe' => 'Seguro',
	'Safety status' => 'Estado de seguridad',
	'Search Bikes' => 'Buscar bicicletas',
	'Search Repair Jobs' => 'Buscar trabajos de reparación',
	'Search Statuses' => 'Buscar estados',
	'Seatpost' => 'Tija',
	'Secondary frame number' => 'Número de cuadro secundario',
	'Select image' => 'Seleccionar imagen',
	'Serial number' => 'Número de serie',
	'Serial photo' => 'Foto del número de serie',
	'Shifters' => 'Mandos de cambio',
	'Shock brand' => 'Marca del amortiguador',
	'Shock model' => 'Modelo del amortiguador',
	'Shock travel' => 'Recorrido del amortiguador',
	'Shop address' => 'Dirección de la tienda',
	'Shop phone' => 'Teléfono de la tienda',
	'Shown on printed labels (and later on client communications).' => 'Se muestra en las etiquetas impresas (y más adelante en las comunicaciones al cliente).',
	'Sorry, we could not find that bike. Please contact the shop.' => 'Lo sentimos, no hemos encontrado esa bicicleta. Contacta con la tienda.',
	'Spanish' => 'Español',
	'Speeds' => 'Velocidades',
	'Statuses' => 'Estados',
	'Steel' => 'Acero',
	'Stem' => 'Potencia',
	'Suspension' => 'Suspensión',
	'The internal ID and QR code are generated automatically when you first save this bike.' => 'El ID interno y el código QR se generan automáticamente al guardar la bicicleta por primera vez.',
	'The public base URL must start with http:// or https://. It has been cleared.' => 'La URL base pública debe empezar por http:// o https://. Se ha borrado.',
	'Titanium' => 'Titanio',
	'Tossa Cycling' => 'Tossa Cycling',
	'Tossa Workshop' => 'Taller Tossa',
	'Trekking' => 'Trekking',
	'Tubeless' => 'Tubeless',
	'Tyres' => 'Neumáticos',
	'Unknown' => 'Desconocido',
	'Unsafe' => 'No seguro',
	'Update Status' => 'Actualizar estado',
	'Used to build scan / status links. Leave empty to use the site URL.' => 'Se usa para generar los enlaces de escaneo / estado. Déjalo vacío para usar la URL del sitio.',
	'Used to build the fleet ID, e.g. TCF-EMTB-001.' => 'Se usa para generar el ID de flota, p. ej. TCF-EMTB-001.',
	'Used when no linked user is selected.' => 'Se usa cuando no se selecciona un usuario vinculado.',
	'View Bike' => 'Ver bicicleta',
	'View Repair Job' => 'Ver trabajo de reparación',
	'Waiting for Approval' => 'Esperando aprobación',
	'Waiting for Parts' => 'Esperando piezas',
	'Warning flags' => 'Avisos',
	'Wheel size' => 'Tamaño de rueda',
	'Wheelset' => 'Ruedas',
	'Workshop' => 'Taller',
	'Workshop Front Desk' => 'Recepción del taller',
	'Workshop ID & QR' => 'ID del taller y QR',
	'Workshop Manager' => 'Gerente del taller',
	'Workshop Mechanic' => 'Mecánico del taller',
	'Workshop Settings' => 'Ajustes del taller',
	'Year' => 'Año',
	'Yes' => 'Sí',
	'You do not have permission to access workshop settings.' => 'No tienes permiso para acceder a los ajustes del taller.',
	'You do not have permission to do this.' => 'No tienes permiso para hacer esto.',
	'— None —' => '— Ninguno —',
	'— Select —' => '— Seleccionar —',
	'Accessories received' => 'Accesorios recibidos',
	'Actual completion' => 'Finalización real',
	'After photos' => 'Fotos posteriores',
	'Approval' => 'Aprobación',
	'Assigned mechanic' => 'Mecánico asignado',
	'Bags' => 'Bolsas',
	'Battery' => 'Batería',
	'Bike' => 'Bicicleta',
	'Bike condition on arrival' => 'Estado de la bicicleta a la llegada',
	'Bolts' => 'Tornillería',
	'Bottle cages' => 'Portabidones',
	'Child seat' => 'Silla infantil',
	'Clear' => 'Borrar',
	'Client approval required' => 'Requiere aprobación del cliente',
	'Client declined' => 'Cliente rechazó',
	'Client link' => 'Enlace para el cliente',
	'Client notes' => 'Notas del cliente',
	'Closing notes' => 'Notas de cierre',
	'Collected by' => 'Recogido por',
	'Collection date/time' => 'Fecha/hora de recogida',
	'Computer mount' => 'Soporte de ciclocomputador',
	'Date received' => 'Fecha de recepción',
	'E-bike system' => 'Sistema e-bike',
	'Estimate amount' => 'Importe del presupuesto',
	'Final price' => 'Precio final',
	'Gears' => 'Cambios',
	'High' => 'Alta',
	'Inspection & Estimate' => 'Inspección y presupuesto',
	'Inspection notes' => 'Notas de inspección',
	'Intake' => 'Recepción',
	'Intake photos' => 'Fotos de recepción',
	'Job Details' => 'Detalles del trabajo',
	'Job ID' => 'ID del trabajo',
	'Labour time' => 'Tiempo de mano de obra',
	'Lights' => 'Luces',
	'Low' => 'Baja',
	'Mandatory work' => 'Trabajo obligatorio',
	'Normal' => 'Normal',
	'Not required' => 'No requerida',
	'Not safe' => 'No seguro',
	'Optional work' => 'Trabajo opcional',
	'Parts used' => 'Piezas utilizadas',
	'Passed' => 'Apto',
	'Passed with notes' => 'Apto con observaciones',
	'Payment link' => 'Enlace de pago',
	'Pending' => 'Pendiente',
	'Priority' => 'Prioridad',
	'Problem description' => 'Descripción del problema',
	'Promised completion' => 'Finalización prometida',
	'Quality check' => 'Control de calidad',
	'Recommended work' => 'Trabajo recomendado',
	'Safety issues' => 'Problemas de seguridad',
	'Search bikes…' => 'Buscar bicicletas…',
	'Search by internal ID, serial number, or owner name / email / phone.' => 'Busca por ID interno, número de serie o nombre / correo / teléfono del propietario.',
	'Selected:' => 'Seleccionada:',
	'Status' => 'Estado',
	'Status & Client' => 'Estado y cliente',
	'Test ride' => 'Prueba de rodaje',
	'The Job ID and client link are generated automatically on first save.' => 'El ID del trabajo y el enlace para el cliente se generan automáticamente al guardar por primera vez.',
	'Urgent' => 'Urgente',
	'Visible damage' => 'Daños visibles',
	'Wheels' => 'Ruedas',
	'When enabled, the client can approve or decline via their status link.' => 'Si se activa, el cliente puede aprobar o rechazar desde su enlace de estado.',
	'Work & Completion' => 'Trabajo y finalización',
	'Work log' => 'Registro de trabajo',
	"admin menu\x04Bikes" => 'Bicicletas',
	"admin menu\x04Repair Jobs" => 'Trabajos de reparación',
	"post type general name\x04Bikes" => 'Bicicletas',
	"post type general name\x04Repair Jobs" => 'Trabajos de reparación',
	"post type singular name\x04Bike" => 'Bicicleta',
	"post type singular name\x04Repair Job" => 'Trabajo de reparación',
	"taxonomy general name\x04Job Statuses" => 'Estados de trabajo',
	"taxonomy singular name\x04Job Status" => 'Estado de trabajo',
);

// --- Parse the .pot for the full key list --------------------------------
$entries = array(); // key => true ; key is "ctx\x04msgid" or "msgid".
$lines   = file( $pot, FILE_IGNORE_NEW_LINES );
$ctx     = null;
foreach ( $lines as $line ) {
	if ( 0 === strpos( $line, 'msgctxt "' ) ) {
		$ctx = po_unescape( substr( $line, 9, -1 ) );
	} elseif ( 0 === strpos( $line, 'msgid "' ) ) {
		$msgid = po_unescape( substr( $line, 7, -1 ) );
		if ( '' === $msgid ) {
			$ctx = null;
			continue;
		}
		$key             = ( null !== $ctx ) ? $ctx . "\x04" . $msgid : $msgid;
		$entries[ $key ] = true;
		$ctx             = null;
	}
}

// --- Coverage report ------------------------------------------------------
$missing = array();
foreach ( array_keys( $entries ) as $key ) {
	if ( ! isset( $es[ $key ] ) || '' === $es[ $key ] ) {
		$missing[] = str_replace( "\x04", ' | ', $key );
	}
}
if ( $missing ) {
	fwrite( STDERR, 'WARNING: ' . count( $missing ) . " untranslated string(s):\n  - " . implode( "\n  - ", $missing ) . "\n" );
}

// --- Build translation table ( original => translation ) ------------------
$table        = array();
$header_value = "Project-Id-Version: Tossa Workshop\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nLanguage: es_ES\nPlural-Forms: nplurals=2; plural=(n != 1);\n";
$table['']    = $header_value;
foreach ( array_keys( $entries ) as $key ) {
	if ( isset( $es[ $key ] ) && '' !== $es[ $key ] ) {
		$table[ $key ] = $es[ $key ];
	}
}

write_po( $root . '/languages/tossa-workshop-es_ES.po', $table, $header_value );
write_mo( $root . '/languages/tossa-workshop-es_ES.mo', $table );

printf(
	"Built es_ES: %d translated / %d total strings.\n",
	count( $table ) - 1,
	count( $entries )
);

// =========================================================================
// Helpers
// =========================================================================

/**
 * Unescape a .po quoted string body.
 *
 * @param string $s Raw body.
 * @return string
 */
function po_unescape( $s ) {
	return strtr( $s, array( '\\n' => "\n", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\' ) );
}

/**
 * Escape a string for a .po literal.
 *
 * @param string $s Raw string.
 * @return string
 */
function po_escape( $s ) {
	return strtr( $s, array( '\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\t" => '\\t' ) );
}

/**
 * Write a .po file.
 *
 * @param string $path   Output path.
 * @param array  $table  original => translation (key may contain \x04 context).
 * @param string $header Header value (for the empty msgid).
 * @return void
 */
function write_po( $path, array $table, $header ) {
	$out = "# Tossa Workshop - Spanish (es_ES)\n";
	$out .= "msgid \"\"\nmsgstr \"\"\n";
	foreach ( explode( "\n", rtrim( $header, "\n" ) ) as $h ) {
		$out .= '"' . po_escape( $h ) . '\\n"' . "\n";
	}
	$out .= "\n";

	foreach ( $table as $key => $value ) {
		if ( '' === $key ) {
			continue;
		}
		if ( false !== strpos( $key, "\x04" ) ) {
			list( $context, $msgid ) = explode( "\x04", $key, 2 );
			$out .= 'msgctxt "' . po_escape( $context ) . "\"\n";
		} else {
			$msgid = $key;
		}
		$out .= 'msgid "' . po_escape( $msgid ) . "\"\n";
		$out .= 'msgstr "' . po_escape( $value ) . "\"\n\n";
	}

	file_put_contents( $path, $out );
}

/**
 * Write a binary .mo file.
 *
 * @param string $path  Output path.
 * @param array  $table original => translation (key '' is the header).
 * @return void
 */
function write_mo( $path, array $table ) {
	ksort( $table ); // Sorted originals (empty string first) for MO binary search.

	$originals    = array_keys( $table );
	$translations = array_values( $table );
	$n            = count( $table );

	$o_offsets = '';
	$t_offsets = '';
	$o_data    = '';
	$t_data    = '';

	// Layout: header (28) + O table (8n) + T table (8n), then data.
	$base = 28 + ( 8 * $n ) + ( 8 * $n );

	foreach ( $originals as $str ) {
		$o_offsets .= pack( 'VV', strlen( $str ), $base + strlen( $o_data ) );
		$o_data    .= $str . "\0";
	}
	$t_base = $base + strlen( $o_data );
	foreach ( $translations as $str ) {
		$t_offsets .= pack( 'VV', strlen( $str ), $t_base + strlen( $t_data ) );
		$t_data    .= $str . "\0";
	}

	$header = pack(
		'VVVVVVV',
		0x950412de, // Magic.
		0,          // Revision.
		$n,         // Number of strings.
		28,         // Offset of originals table.
		28 + ( 8 * $n ), // Offset of translations table.
		0,          // Hash table size.
		28 + ( 8 * $n ) + ( 8 * $n ) // Hash table offset (unused).
	);

	file_put_contents( $path, $header . $o_offsets . $t_offsets . $o_data . $t_data );
}
