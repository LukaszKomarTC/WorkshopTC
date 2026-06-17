<?php
/**
 * Client notification templates (subject + body) per status, per language.
 *
 * Templates are *content*, not UI chrome, so they live as literal per-language
 * arrays rather than gettext strings — exactly so ca/fr can be added later by
 * dropping in another array. Staff overrides are stored in options and merged
 * over these defaults.
 *
 * Placeholders: {client_name} {bike} {job_id} {status_url} {estimate}
 * {final_price} {shop_address} {status}.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Default templates and template resolution.
 */
class Notification_Templates {

	const TEMPLATES_OPTION = 'tcw_notify_templates';
	const ENABLED_OPTION   = 'tcw_notify_enabled';

	/** Languages that ship with a full template set. */
	const SHIPPED_LANGS = array( 'es', 'en', 'de' );

	/** Placeholder tokens available to templates. */
	const PLACEHOLDERS = array( '{client_name}', '{bike}', '{job_id}', '{status_url}', '{estimate}', '{final_price}', '{shop_address}', '{status}' );

	/**
	 * Statuses that send a client email by default.
	 *
	 * @return array<string,bool>
	 */
	public static function default_enabled() {
		$enabled = array();
		foreach ( Job_Status_Taxonomy::status_slugs() as $slug ) {
			$enabled[ $slug ] = in_array( $slug, array( 'received', 'waiting_approval', 'ready' ), true );
		}
		return $enabled;
	}

	/**
	 * Whether a status sends a client email (stored override or default).
	 *
	 * @param string $status Status slug.
	 * @return bool
	 */
	public static function is_enabled( $status ) {
		$stored = get_option( self::ENABLED_OPTION, array() );
		if ( is_array( $stored ) && array_key_exists( $status, $stored ) ) {
			return (bool) $stored[ $status ];
		}
		$defaults = self::default_enabled();
		return ! empty( $defaults[ $status ] );
	}

	/**
	 * Resolve subject + body for a status/language: stored override first, then
	 * the shipped default, then the per-language generic. Unknown languages
	 * (ca/fr) fall back to Spanish.
	 *
	 * @param string $status Status slug.
	 * @param string $lang   Language code.
	 * @return array{subject:string,body:string}
	 */
	public static function get( $status, $lang ) {
		$lang = in_array( $lang, self::SHIPPED_LANGS, true ) ? $lang : 'es';

		$defaults = self::defaults();
		$def      = isset( $defaults[ $lang ][ $status ] ) ? $defaults[ $lang ][ $status ] : $defaults[ $lang ]['_generic'];

		$overrides = get_option( self::TEMPLATES_OPTION, array() );
		$ov        = ( is_array( $overrides ) && isset( $overrides[ $lang ][ $status ] ) ) ? $overrides[ $lang ][ $status ] : array();

		return array(
			'subject' => ( isset( $ov['subject'] ) && '' !== trim( $ov['subject'] ) ) ? $ov['subject'] : $def['subject'],
			'body'    => ( isset( $ov['body'] ) && '' !== trim( $ov['body'] ) ) ? $ov['body'] : $def['body'],
		);
	}

	/**
	 * Substitute placeholders in a template string.
	 *
	 * @param string                $template Template text.
	 * @param array<string,string>  $vars     Placeholder => value.
	 * @return string
	 */
	public static function render( $template, array $vars ) {
		$search  = array();
		$replace = array();
		foreach ( $vars as $token => $value ) {
			$search[]  = '{' . $token . '}';
			$replace[] = $value;
		}
		return str_replace( $search, $replace, $template );
	}

	/**
	 * Shipped default templates.
	 *
	 * @return array<string,array<string,array{subject:string,body:string}>>
	 */
	public static function defaults() {
		return array(
			'es' => array(
				'_generic'         => array(
					'subject' => 'Actualización de tu reparación — {job_id}',
					'body'    => "Hola {client_name}:\n\nEl estado de tu {bike} (ref. {job_id}) se ha actualizado a: {status}.\n\nPuedes seguir el estado aquí:\n{status_url}\n\nGracias,\nTossa Cycling",
				),
				'received'         => array(
					'subject' => 'Hemos recibido tu bici — {job_id}',
					'body'    => "Hola {client_name}:\n\nHemos recibido tu {bike} en Tossa Cycling. Tu referencia es {job_id}.\n\nPuedes seguir el estado de la reparación en cualquier momento aquí:\n{status_url}\n\nGracias,\nTossa Cycling",
				),
				'waiting_approval' => array(
					'subject' => 'Tu presupuesto está listo — {job_id}',
					'body'    => "Hola {client_name}:\n\nHemos revisado tu {bike} y preparado un presupuesto: {estimate}.\n\nPor favor, apruébalo o recházalo aquí:\n{status_url}\n\nGracias,\nTossa Cycling",
				),
				'ready'            => array(
					'subject' => 'Tu bici está lista para recoger — {job_id}',
					'body'    => "¡Buenas noticias, {client_name}!\n\nTu {bike} ya está lista. Precio final: {final_price}.\n\nPuedes recogerla en:\n{shop_address}\n\nMás detalles:\n{status_url}\n\nGracias,\nTossa Cycling",
				),
				'delivered'        => array(
					'subject' => 'Gracias por tu visita — {job_id}',
					'body'    => "Hola {client_name}:\n\nGracias por recoger tu {bike}. Ha sido un placer ayudarte.\n\n¡Buenas rutas!\nTossa Cycling",
				),
			),
			'en' => array(
				'_generic'         => array(
					'subject' => 'Update on your repair — {job_id}',
					'body'    => "Hello {client_name},\n\nThe status of your {bike} (ref {job_id}) has been updated to: {status}.\n\nYou can follow the status here:\n{status_url}\n\nThank you,\nTossa Cycling",
				),
				'received'         => array(
					'subject' => 'We have received your bike — {job_id}',
					'body'    => "Hello {client_name},\n\nWe have received your {bike} at Tossa Cycling. Your reference is {job_id}.\n\nYou can follow the repair status anytime here:\n{status_url}\n\nThank you,\nTossa Cycling",
				),
				'waiting_approval' => array(
					'subject' => 'Your estimate is ready — {job_id}',
					'body'    => "Hello {client_name},\n\nWe have inspected your {bike} and prepared an estimate: {estimate}.\n\nPlease approve or decline it here:\n{status_url}\n\nThank you,\nTossa Cycling",
				),
				'ready'            => array(
					'subject' => 'Your bike is ready for pickup — {job_id}',
					'body'    => "Good news, {client_name}!\n\nYour {bike} is ready. Final price: {final_price}.\n\nYou can pick it up at:\n{shop_address}\n\nMore details:\n{status_url}\n\nThank you,\nTossa Cycling",
				),
				'delivered'        => array(
					'subject' => 'Thanks for your visit — {job_id}',
					'body'    => "Hello {client_name},\n\nThank you for picking up your {bike}. It was a pleasure to help.\n\nHappy riding!\nTossa Cycling",
				),
			),
			'de' => array(
				'_generic'         => array(
					'subject' => 'Update zu deiner Reparatur — {job_id}',
					'body'    => "Hallo {client_name},\n\nder Status deines {bike} (Ref. {job_id}) wurde aktualisiert auf: {status}.\n\nDu kannst den Status hier verfolgen:\n{status_url}\n\nDanke,\nTossa Cycling",
				),
				'received'         => array(
					'subject' => 'Wir haben dein Rad erhalten — {job_id}',
					'body'    => "Hallo {client_name},\n\nwir haben dein {bike} bei Tossa Cycling erhalten. Deine Referenz ist {job_id}.\n\nDu kannst den Reparaturstatus jederzeit hier verfolgen:\n{status_url}\n\nDanke,\nTossa Cycling",
				),
				'waiting_approval' => array(
					'subject' => 'Dein Kostenvoranschlag ist fertig — {job_id}',
					'body'    => "Hallo {client_name},\n\nwir haben dein {bike} geprüft und einen Kostenvoranschlag erstellt: {estimate}.\n\nBitte bestätige oder lehne ihn hier ab:\n{status_url}\n\nDanke,\nTossa Cycling",
				),
				'ready'            => array(
					'subject' => 'Dein Rad ist abholbereit — {job_id}',
					'body'    => "Gute Nachrichten, {client_name}!\n\nDein {bike} ist fertig. Endpreis: {final_price}.\n\nAbholung bei:\n{shop_address}\n\nWeitere Details:\n{status_url}\n\nDanke,\nTossa Cycling",
				),
				'delivered'        => array(
					'subject' => 'Danke für deinen Besuch — {job_id}',
					'body'    => "Hallo {client_name},\n\ndanke, dass du dein {bike} abgeholt hast. Es war uns eine Freude.\n\nGute Fahrt!\nTossa Cycling",
				),
			),
		);
	}
}
