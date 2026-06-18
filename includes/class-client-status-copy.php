<?php
/**
 * Client-facing copy for the status page, per language.
 *
 * Rendered in the *client's* stored language regardless of the site locale,
 * so this is content (literal per-language arrays), not gettext — same pattern
 * as the notification templates. Unknown languages (ca/fr) fall back to ES.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Status descriptions and UI labels for the public client page.
 */
class Client_Status_Copy {

	const LANGS = array( 'es', 'en', 'de' );

	/**
	 * Normalize a language code to a shipped one.
	 *
	 * @param string $lang Requested language.
	 * @return string
	 */
	public static function lang( $lang ) {
		return in_array( $lang, self::LANGS, true ) ? $lang : 'es';
	}

	/**
	 * Friendly label + one-line explanation for a status, in a language.
	 *
	 * @param string $status Status slug.
	 * @param string $lang   Language code.
	 * @return array{label:string,desc:string}
	 */
	public static function status( $status, $lang ) {
		$lang = self::lang( $lang );
		$all  = self::statuses();
		if ( isset( $all[ $lang ][ $status ] ) ) {
			return $all[ $lang ][ $status ];
		}
		return array(
			'label' => $status,
			'desc'  => '',
		);
	}

	/**
	 * A UI label by key, in a language (falls back to ES then the key).
	 *
	 * @param string $key  Label key.
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function t( $key, $lang ) {
		$lang   = self::lang( $lang );
		$labels = self::labels();
		if ( isset( $labels[ $lang ][ $key ] ) ) {
			return $labels[ $lang ][ $key ];
		}
		return isset( $labels['es'][ $key ] ) ? $labels['es'][ $key ] : $key;
	}

	/**
	 * Per-language status label + description.
	 *
	 * @return array<string,array<string,array{label:string,desc:string}>>
	 */
	public static function statuses() {
		return array(
			'es' => array(
				'received'         => array( 'label' => 'Recibida', 'desc' => 'Hemos recibido tu bici y pronto la revisaremos.' ),
				'inspected'        => array( 'label' => 'Inspeccionada', 'desc' => 'Hemos revisado tu bici y estamos preparando el presupuesto.' ),
				'waiting_approval' => array( 'label' => 'Esperando tu aprobación', 'desc' => 'Hay un presupuesto pendiente de tu aprobación.' ),
				'approved'         => array( 'label' => 'Aprobada', 'desc' => 'Gracias, hemos recibido tu aprobación y empezaremos el trabajo.' ),
				'waiting_parts'    => array( 'label' => 'Esperando piezas', 'desc' => 'Estamos esperando las piezas necesarias para tu reparación.' ),
				'parts_arrived'    => array( 'label' => 'Piezas recibidas', 'desc' => 'Las piezas han llegado y continuaremos con la reparación.' ),
				'in_repair'        => array( 'label' => 'En reparación', 'desc' => 'Estamos trabajando en tu bici.' ),
				'quality_check'    => array( 'label' => 'Control de calidad', 'desc' => 'Tu bici está en la revisión final de calidad.' ),
				'ready'            => array( 'label' => 'Lista para recoger', 'desc' => '¡Tu bici está lista! Puedes pasar a recogerla.' ),
				'delivered'        => array( 'label' => 'Entregada', 'desc' => 'Tu bici ha sido entregada. ¡Gracias!' ),
				'closed'           => array( 'label' => 'Cerrada', 'desc' => 'Este trabajo está cerrado. ¡Gracias por confiar en nosotros!' ),
				'declined'         => array( 'label' => 'Rechazada', 'desc' => 'Has rechazado el presupuesto. Contáctanos si quieres comentarlo.' ),
				'cancelled'        => array( 'label' => 'Cancelada', 'desc' => 'Este trabajo ha sido cancelado.' ),
			),
			'en' => array(
				'received'         => array( 'label' => 'Received', 'desc' => 'We have received your bike and will inspect it soon.' ),
				'inspected'        => array( 'label' => 'Inspected', 'desc' => 'We have inspected your bike and are preparing the estimate.' ),
				'waiting_approval' => array( 'label' => 'Waiting for your approval', 'desc' => 'There is an estimate awaiting your approval.' ),
				'approved'         => array( 'label' => 'Approved', 'desc' => 'Thanks — we have your approval and will start the work.' ),
				'waiting_parts'    => array( 'label' => 'Waiting for parts', 'desc' => 'We are waiting for the parts needed for your repair.' ),
				'parts_arrived'    => array( 'label' => 'Parts arrived', 'desc' => 'The parts have arrived and we will continue the repair.' ),
				'in_repair'        => array( 'label' => 'In repair', 'desc' => 'We are working on your bike.' ),
				'quality_check'    => array( 'label' => 'Quality check', 'desc' => 'Your bike is in the final quality check.' ),
				'ready'            => array( 'label' => 'Ready for pickup', 'desc' => 'Your bike is ready! You can come and pick it up.' ),
				'delivered'        => array( 'label' => 'Delivered', 'desc' => 'Your bike has been delivered. Thank you!' ),
				'closed'           => array( 'label' => 'Closed', 'desc' => 'This job is closed. Thanks for trusting us!' ),
				'declined'         => array( 'label' => 'Declined', 'desc' => 'You have declined the estimate. Contact us if you would like to discuss it.' ),
				'cancelled'        => array( 'label' => 'Cancelled', 'desc' => 'This job has been cancelled.' ),
			),
			'de' => array(
				'received'         => array( 'label' => 'Erhalten', 'desc' => 'Wir haben dein Rad erhalten und prüfen es in Kürze.' ),
				'inspected'        => array( 'label' => 'Geprüft', 'desc' => 'Wir haben dein Rad geprüft und erstellen den Kostenvoranschlag.' ),
				'waiting_approval' => array( 'label' => 'Wartet auf deine Freigabe', 'desc' => 'Ein Kostenvoranschlag wartet auf deine Freigabe.' ),
				'approved'         => array( 'label' => 'Freigegeben', 'desc' => 'Danke — wir haben deine Freigabe und beginnen mit der Arbeit.' ),
				'waiting_parts'    => array( 'label' => 'Warten auf Teile', 'desc' => 'Wir warten auf die benötigten Teile für deine Reparatur.' ),
				'parts_arrived'    => array( 'label' => 'Teile eingetroffen', 'desc' => 'Die Teile sind da und wir setzen die Reparatur fort.' ),
				'in_repair'        => array( 'label' => 'In Reparatur', 'desc' => 'Wir arbeiten an deinem Rad.' ),
				'quality_check'    => array( 'label' => 'Qualitätskontrolle', 'desc' => 'Dein Rad ist in der abschließenden Qualitätskontrolle.' ),
				'ready'            => array( 'label' => 'Abholbereit', 'desc' => 'Dein Rad ist fertig! Du kannst es abholen.' ),
				'delivered'        => array( 'label' => 'Übergeben', 'desc' => 'Dein Rad wurde übergeben. Danke!' ),
				'closed'           => array( 'label' => 'Abgeschlossen', 'desc' => 'Dieser Auftrag ist abgeschlossen. Danke für dein Vertrauen!' ),
				'declined'         => array( 'label' => 'Abgelehnt', 'desc' => 'Du hast den Kostenvoranschlag abgelehnt. Melde dich bei Fragen.' ),
				'cancelled'        => array( 'label' => 'Storniert', 'desc' => 'Dieser Auftrag wurde storniert.' ),
			),
		);
	}

	/**
	 * Per-language UI labels for the page.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function labels() {
		return array(
			'es' => array(
				'page_title'      => 'Estado de tu reparación',
				'job'             => 'Trabajo',
				'bike'            => 'Bicicleta',
				'status'          => 'Estado',
				'estimate'        => 'Presupuesto',
				'promised'        => 'Fecha estimada',
				'final_price'     => 'Precio final',
				'pickup'          => 'Dirección de recogida',
				'recommended'     => 'Trabajo recomendado',
				'mandatory'       => 'Trabajo obligatorio',
				'optional'        => 'Trabajo opcional',
				'approve_intro'   => 'Por favor, revisa el presupuesto y apruébalo o recházalo:',
				'approve'         => 'Aprobar',
				'decline'         => 'Rechazar',
				'comments'        => 'Comentarios (opcional)',
				'ask'             => 'Hacer una pregunta',
				'whatsapp'        => 'WhatsApp',
				'contact'         => 'Contactar con la tienda',
				'invalid_title'   => 'Enlace no válido',
				'invalid_msg'     => 'Este enlace no es válido o ha caducado. Por favor, contacta con la tienda.',
				'approved_thanks' => '¡Gracias! Hemos registrado tu aprobación.',
				'declined_thanks' => 'Gracias. Hemos registrado tu respuesta.',
				'too_many'        => 'Demasiados intentos. Inténtalo de nuevo más tarde.',
			),
			'en' => array(
				'page_title'      => 'Your repair status',
				'job'             => 'Job',
				'bike'            => 'Bike',
				'status'          => 'Status',
				'estimate'        => 'Estimate',
				'promised'        => 'Estimated ready by',
				'final_price'     => 'Final price',
				'pickup'          => 'Pickup address',
				'recommended'     => 'Recommended work',
				'mandatory'       => 'Mandatory work',
				'optional'        => 'Optional work',
				'approve_intro'   => 'Please review the estimate and approve or decline:',
				'approve'         => 'Approve',
				'decline'         => 'Decline',
				'comments'        => 'Comments (optional)',
				'ask'             => 'Ask a question',
				'whatsapp'        => 'WhatsApp',
				'contact'         => 'Contact the shop',
				'invalid_title'   => 'Link not valid',
				'invalid_msg'     => 'This link is invalid or has expired. Please contact the shop.',
				'approved_thanks' => 'Thank you! Your approval has been recorded.',
				'declined_thanks' => 'Thank you. We have recorded your response.',
				'too_many'        => 'Too many attempts. Please try again later.',
			),
			'de' => array(
				'page_title'      => 'Status deiner Reparatur',
				'job'             => 'Auftrag',
				'bike'            => 'Rad',
				'status'          => 'Status',
				'estimate'        => 'Kostenvoranschlag',
				'promised'        => 'Voraussichtlich fertig',
				'final_price'     => 'Endpreis',
				'pickup'          => 'Abholadresse',
				'recommended'     => 'Empfohlene Arbeiten',
				'mandatory'       => 'Notwendige Arbeiten',
				'optional'        => 'Optionale Arbeiten',
				'approve_intro'   => 'Bitte prüfe den Kostenvoranschlag und bestätige oder lehne ihn ab:',
				'approve'         => 'Bestätigen',
				'decline'         => 'Ablehnen',
				'comments'        => 'Anmerkungen (optional)',
				'ask'             => 'Eine Frage stellen',
				'whatsapp'        => 'WhatsApp',
				'contact'         => 'Werkstatt kontaktieren',
				'invalid_title'   => 'Link ungültig',
				'invalid_msg'     => 'Dieser Link ist ungültig oder abgelaufen. Bitte kontaktiere die Werkstatt.',
				'approved_thanks' => 'Danke! Deine Freigabe wurde gespeichert.',
				'declined_thanks' => 'Danke. Wir haben deine Antwort gespeichert.',
				'too_many'        => 'Zu viele Versuche. Bitte versuche es später erneut.',
			),
		);
	}
}
