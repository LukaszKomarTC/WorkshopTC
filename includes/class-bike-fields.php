<?php
/**
 * Bike field + section definitions.
 *
 * Single source of truth for every editable bike meta field, grouped into the
 * meta-box sections from spec §3.1. The meta-box renderer/saver and the
 * print label all read from here, so adding a field is a one-line change.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Declarative bike field catalog.
 */
class Bike_Fields {

	/**
	 * Option lists for select fields.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function option_sets() {
		return array(
			'bike_type'      => array(
				'customer' => __( 'Customer bike', 'tossa-workshop' ),
				'fleet'    => __( 'Fleet bike', 'tossa-workshop' ),
			),
			'language'       => array(
				'es' => __( 'Spanish', 'tossa-workshop' ),
				'en' => __( 'English', 'tossa-workshop' ),
				'de' => __( 'German', 'tossa-workshop' ),
				'ca' => __( 'Catalan', 'tossa-workshop' ),
				'fr' => __( 'French', 'tossa-workshop' ),
			),
			'category'       => array(
				'road'     => __( 'Road', 'tossa-workshop' ),
				'eroad'    => __( 'E-Road', 'tossa-workshop' ),
				'gravel'   => __( 'Gravel', 'tossa-workshop' ),
				'egravel'  => __( 'E-Gravel', 'tossa-workshop' ),
				'mtb'      => __( 'MTB', 'tossa-workshop' ),
				'emtb'     => __( 'E-MTB', 'tossa-workshop' ),
				'city'     => __( 'City', 'tossa-workshop' ),
				'trekking' => __( 'Trekking', 'tossa-workshop' ),
				'kids'     => __( 'Kids', 'tossa-workshop' ),
				'cargo'    => __( 'Cargo', 'tossa-workshop' ),
				'other'    => __( 'Other', 'tossa-workshop' ),
			),
			'frame_material' => array(
				'carbon'    => __( 'Carbon', 'tossa-workshop' ),
				'aluminium' => __( 'Aluminium', 'tossa-workshop' ),
				'steel'     => __( 'Steel', 'tossa-workshop' ),
				'titanium'  => __( 'Titanium', 'tossa-workshop' ),
				'unknown'   => __( 'Unknown', 'tossa-workshop' ),
			),
			'condition'      => array(
				'excellent' => __( 'Excellent', 'tossa-workshop' ),
				'good'      => __( 'Good', 'tossa-workshop' ),
				'fair'      => __( 'Fair', 'tossa-workshop' ),
				'poor'      => __( 'Poor', 'tossa-workshop' ),
			),
			'safety'         => array(
				'safe'             => __( 'Safe', 'tossa-workshop' ),
				'attention_needed' => __( 'Attention needed', 'tossa-workshop' ),
				'unsafe'           => __( 'Unsafe', 'tossa-workshop' ),
			),
		);
	}

	/**
	 * Section + field definitions.
	 *
	 * Each section: id, title, context, collapsed, only ('customer'|'fleet'|'')
	 * and a fields list. Each field: key, label, type, and type-specific extras.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function sections() {
		$opts = self::option_sets();

		return array(
			array(
				'id'        => 'identification',
				'title'     => __( 'Identification', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => false,
				'only'      => '',
				'fields'    => array(
					array(
						'key'      => 'bike_type',
						'label'    => __( 'Bike type', 'tossa-workshop' ),
						'type'     => 'select',
						'options'  => $opts['bike_type'],
						'required' => true,
						'class'    => 'tcw-bike-type',
					),
					array(
						'key'      => 'brand',
						'label'    => __( 'Brand', 'tossa-workshop' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'      => 'model',
						'label'    => __( 'Model', 'tossa-workshop' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'   => 'serial_number',
						'label' => __( 'Serial number', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'secondary_frame_number',
						'label' => __( 'Secondary frame number', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'serial_photo',
						'label' => __( 'Serial photo', 'tossa-workshop' ),
						'type'  => 'attachment',
					),
				),
			),
			array(
				'id'        => 'ownership',
				'title'     => __( 'Owner / Client', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => false,
				'only'      => 'customer',
				'fields'    => array(
					array(
						'key'   => 'owner_user_id',
						'label' => __( 'Linked WordPress/WooCommerce user', 'tossa-workshop' ),
						'type'  => 'user',
					),
					array(
						'key'   => 'owner_name',
						'label' => __( 'Owner name', 'tossa-workshop' ),
						'type'  => 'text',
						'help'  => __( 'Used when no linked user is selected.', 'tossa-workshop' ),
					),
					array(
						'key'   => 'owner_email',
						'label' => __( 'Owner email', 'tossa-workshop' ),
						'type'  => 'email',
					),
					array(
						'key'   => 'owner_phone',
						'label' => __( 'Owner phone', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'     => 'owner_language',
						'label'   => __( 'Preferred language', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['language'],
						'default' => 'es',
					),
					array(
						'key'   => 'gdpr_consent',
						'label' => __( 'GDPR consent given', 'tossa-workshop' ),
						'type'  => 'checkbox',
						'help'  => __( 'A timestamp is recorded automatically when first checked.', 'tossa-workshop' ),
					),
				),
			),
			array(
				'id'        => 'basic',
				'title'     => __( 'Basic Details', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => false,
				'only'      => '',
				'fields'    => array(
					array(
						'key'   => 'bike_year',
						'label' => __( 'Year', 'tossa-workshop' ),
						'type'  => 'number',
					),
					array(
						'key'   => 'color',
						'label' => __( 'Colour', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'frame_size',
						'label' => __( 'Frame size', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'wheel_size',
						'label' => __( 'Wheel size', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'     => 'category',
						'label'   => __( 'Category', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['category'],
					),
					array(
						'key'     => 'frame_material',
						'label'   => __( 'Frame material', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['frame_material'],
					),
				),
			),
			array(
				'id'        => 'components',
				'title'     => __( 'Components', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => true,
				'only'      => '',
				'fields'    => array_merge(
					self::text_fields(
						array(
							'drivetrain_brand' => __( 'Drivetrain brand', 'tossa-workshop' ),
							'drivetrain_model' => __( 'Drivetrain model', 'tossa-workshop' ),
							'speeds'           => __( 'Speeds', 'tossa-workshop' ),
							'crankset'         => __( 'Crankset', 'tossa-workshop' ),
							'cassette'         => __( 'Cassette', 'tossa-workshop' ),
							'chain'            => __( 'Chain', 'tossa-workshop' ),
							'derailleurs'      => __( 'Derailleurs', 'tossa-workshop' ),
							'shifters'         => __( 'Shifters', 'tossa-workshop' ),
							'brakes'           => __( 'Brakes', 'tossa-workshop' ),
							'brake_pad_type'   => __( 'Brake pad type', 'tossa-workshop' ),
							'rotor_sizes'      => __( 'Rotor sizes', 'tossa-workshop' ),
							'wheelset'         => __( 'Wheelset', 'tossa-workshop' ),
							'tyres'            => __( 'Tyres', 'tossa-workshop' ),
						)
					),
					array(
						array(
							'key'   => 'tubeless',
							'label' => __( 'Tubeless', 'tossa-workshop' ),
							'type'  => 'checkbox',
						),
					),
					self::text_fields(
						array(
							'pedals'    => __( 'Pedals', 'tossa-workshop' ),
							'saddle'    => __( 'Saddle', 'tossa-workshop' ),
							'seatpost'  => __( 'Seatpost', 'tossa-workshop' ),
							'handlebar' => __( 'Handlebar', 'tossa-workshop' ),
							'stem'      => __( 'Stem', 'tossa-workshop' ),
						)
					)
				),
			),
			array(
				'id'        => 'ebike',
				'title'     => __( 'E-bike', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => true,
				'only'      => '',
				'fields'    => array_merge(
					self::text_fields(
						array(
							'motor_brand'    => __( 'Motor brand', 'tossa-workshop' ),
							'motor_model'    => __( 'Motor model', 'tossa-workshop' ),
							'motor_serial'   => __( 'Motor serial', 'tossa-workshop' ),
							'battery_brand'  => __( 'Battery brand', 'tossa-workshop' ),
							'battery_model'  => __( 'Battery model', 'tossa-workshop' ),
							'battery_serial' => __( 'Battery serial', 'tossa-workshop' ),
						)
					),
					array(
						array(
							'key'   => 'battery_wh',
							'label' => __( 'Battery Wh', 'tossa-workshop' ),
							'type'  => 'number',
						),
					),
					self::text_fields(
						array(
							'display_model' => __( 'Display model', 'tossa-workshop' ),
							'charger'       => __( 'Charger', 'tossa-workshop' ),
							'firmware'      => __( 'Firmware', 'tossa-workshop' ),
							'odometer'      => __( 'Odometer', 'tossa-workshop' ),
						)
					)
				),
			),
			array(
				'id'        => 'suspension',
				'title'     => __( 'Suspension', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => true,
				'only'      => '',
				'fields'    => array_merge(
					self::text_fields(
						array(
							'fork_brand'   => __( 'Fork brand', 'tossa-workshop' ),
							'fork_model'   => __( 'Fork model', 'tossa-workshop' ),
							'fork_travel'  => __( 'Fork travel', 'tossa-workshop' ),
							'fork_serial'  => __( 'Fork serial', 'tossa-workshop' ),
							'shock_brand'  => __( 'Shock brand', 'tossa-workshop' ),
							'shock_model'  => __( 'Shock model', 'tossa-workshop' ),
							'shock_travel' => __( 'Shock travel', 'tossa-workshop' ),
						)
					),
					array(
						array(
							'key'   => 'susp_last_service',
							'label' => __( 'Last service date', 'tossa-workshop' ),
							'type'  => 'date',
						),
						array(
							'key'   => 'susp_next_service',
							'label' => __( 'Next service due', 'tossa-workshop' ),
							'type'  => 'date',
						),
					)
				),
			),
			array(
				'id'        => 'photos',
				'title'     => __( 'Photos', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => true,
				'only'      => '',
				'fields'    => array(
					array(
						'key'   => 'photos_gallery',
						'label' => __( 'Photo gallery', 'tossa-workshop' ),
						'type'  => 'gallery',
						'help'  => __( 'Add photos and optionally label each (left, right, drivetrain, cockpit, damage, before/after).', 'tossa-workshop' ),
					),
				),
			),
			array(
				'id'        => 'maintenance',
				'title'     => __( 'Maintenance Summary', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => true,
				'only'      => '',
				'fields'    => array(
					array(
						'key'   => 'last_service_date',
						'label' => __( 'Last service date', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'next_service_due',
						'label' => __( 'Next service due', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'last_brake_pad_date',
						'label' => __( 'Last brake-pad change', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'last_chain_date',
						'label' => __( 'Last chain change', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'last_cassette_date',
						'label' => __( 'Last cassette change', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'last_tyre_date',
						'label' => __( 'Last tyre change', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'last_suspension_date',
						'label' => __( 'Last suspension service', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'     => 'condition_rating',
						'label'   => __( 'Condition rating', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['condition'],
					),
					array(
						'key'     => 'safety_status',
						'label'   => __( 'Safety status', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['safety'],
					),
				),
			),
			array(
				'id'        => 'notes',
				'title'     => __( 'Internal Notes', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => true,
				'only'      => '',
				'fields'    => array(
					array(
						'key'   => 'mechanic_notes',
						'label' => __( 'Mechanic notes', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'recurring_problems',
						'label' => __( 'Recurring problems', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'warning_flags',
						'label' => __( 'Warning flags', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
				),
			),
			array(
				'id'        => 'fleet',
				'title'     => __( 'Fleet', 'tossa-workshop' ),
				'context'   => 'normal',
				'collapsed' => false,
				'only'      => 'fleet',
				'fields'    => array(
					array(
						'key'   => 'fleet_number',
						'label' => __( 'Fleet number', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'     => 'rental_category',
						'label'   => __( 'Rental category', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['category'],
						'help'    => __( 'Used to build the fleet ID, e.g. TCF-EMTB-001.', 'tossa-workshop' ),
					),
					array(
						'key'   => 'rental_active',
						'label' => __( 'Rental active', 'tossa-workshop' ),
						'type'  => 'checkbox',
					),
					array(
						'key'   => 'last_rental_date',
						'label' => __( 'Last rental date', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'rental_days_count',
						'label' => __( 'Rental days count', 'tossa-workshop' ),
						'type'  => 'number',
					),
					array(
						'key'   => 'battery_health',
						'label' => __( 'Battery health (%)', 'tossa-workshop' ),
						'type'  => 'number',
					),
					array(
						'key'   => 'accident_history',
						'label' => __( 'Accident history', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'replacement_planning',
						'label' => __( 'Replacement planning', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
				),
			),
		);
	}

	/**
	 * Flat map of every field key => field definition (for the saver).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all_fields() {
		$flat = array();
		foreach ( self::sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$flat[ $field['key'] ] = $field;
			}
		}
		return $flat;
	}

	/**
	 * Helper to build a list of plain text field definitions.
	 *
	 * @param array<string,string> $map key => label.
	 * @return array<int,array<string,string>>
	 */
	private static function text_fields( array $map ) {
		$fields = array();
		foreach ( $map as $key => $label ) {
			$fields[] = array(
				'key'   => $key,
				'label' => $label,
				'type'  => 'text',
			);
		}
		return $fields;
	}
}
