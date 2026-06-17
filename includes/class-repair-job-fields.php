<?php
/**
 * Repair job field + section definitions (spec §3.2).
 *
 * Covers the editable scalar/text/photo fields handled generically by
 * Field_Kit. The bike link, status, and client-approval/token fields are
 * managed separately by Job_Meta_Boxes because they have bespoke UI and
 * side effects.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Declarative repair-job field catalog.
 */
class Repair_Job_Fields {

	/**
	 * Option lists for select / multicheck fields.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function option_sets() {
		return array(
			'priority'      => array(
				'low'    => __( 'Low', 'tossa-workshop' ),
				'normal' => __( 'Normal', 'tossa-workshop' ),
				'high'   => __( 'High', 'tossa-workshop' ),
				'urgent' => __( 'Urgent', 'tossa-workshop' ),
			),
			'accessories'   => array(
				'battery'        => __( 'Battery', 'tossa-workshop' ),
				'charger'        => __( 'Charger', 'tossa-workshop' ),
				'pedals'         => __( 'Pedals', 'tossa-workshop' ),
				'bags'           => __( 'Bags', 'tossa-workshop' ),
				'lights'         => __( 'Lights', 'tossa-workshop' ),
				'computer_mount' => __( 'Computer mount', 'tossa-workshop' ),
				'bottle_cages'   => __( 'Bottle cages', 'tossa-workshop' ),
				'child_seat'     => __( 'Child seat', 'tossa-workshop' ),
				'other'          => __( 'Other', 'tossa-workshop' ),
			),
			'quality_check' => array(
				'brakes'        => __( 'Brakes', 'tossa-workshop' ),
				'gears'         => __( 'Gears', 'tossa-workshop' ),
				'wheels'        => __( 'Wheels', 'tossa-workshop' ),
				'tyres'         => __( 'Tyres', 'tossa-workshop' ),
				'bolts'         => __( 'Bolts', 'tossa-workshop' ),
				'ebike_system'  => __( 'E-bike system', 'tossa-workshop' ),
				'test_ride'     => __( 'Test ride', 'tossa-workshop' ),
			),
			'safety_status' => array(
				'passed'            => __( 'Passed', 'tossa-workshop' ),
				'passed_with_notes' => __( 'Passed with notes', 'tossa-workshop' ),
				'not_safe'          => __( 'Not safe', 'tossa-workshop' ),
				'client_declined'   => __( 'Client declined', 'tossa-workshop' ),
			),
		);
	}

	/**
	 * Section + field definitions.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function sections() {
		$opts = self::option_sets();

		return array(
			array(
				'id'        => 'details',
				'title'     => __( 'Job Details', 'tossa-workshop' ),
				'collapsed' => false,
				'fields'    => array(
					array(
						'key'   => 'assigned_mechanic',
						'label' => __( 'Assigned mechanic', 'tossa-workshop' ),
						'type'  => 'user',
					),
					array(
						'key'     => 'date_received',
						'label'   => __( 'Date received', 'tossa-workshop' ),
						'type'    => 'date',
						'default' => current_time( 'Y-m-d' ),
					),
					array(
						'key'     => 'priority',
						'label'   => __( 'Priority', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['priority'],
						'default' => 'normal',
					),
					array(
						'key'   => 'promised_completion',
						'label' => __( 'Promised completion', 'tossa-workshop' ),
						'type'  => 'date',
					),
					array(
						'key'   => 'actual_completion',
						'label' => __( 'Actual completion', 'tossa-workshop' ),
						'type'  => 'date',
					),
				),
			),
			array(
				'id'        => 'intake',
				'title'     => __( 'Intake', 'tossa-workshop' ),
				'collapsed' => false,
				'fields'    => array(
					array(
						'key'   => 'problem_description',
						'label' => __( 'Problem description', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'client_notes',
						'label' => __( 'Client notes', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'bike_condition_on_arrival',
						'label' => __( 'Bike condition on arrival', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'     => 'accessories_received',
						'label'   => __( 'Accessories received', 'tossa-workshop' ),
						'type'    => 'multicheck',
						'options' => $opts['accessories'],
					),
					array(
						'key'   => 'visible_damage',
						'label' => __( 'Visible damage', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'intake_photos',
						'label' => __( 'Intake photos', 'tossa-workshop' ),
						'type'  => 'gallery',
					),
				),
			),
			array(
				'id'        => 'inspection',
				'title'     => __( 'Inspection & Estimate', 'tossa-workshop' ),
				'collapsed' => true,
				'fields'    => array(
					array(
						'key'   => 'inspection_notes',
						'label' => __( 'Inspection notes', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'safety_issues',
						'label' => __( 'Safety issues', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'recommended_work',
						'label' => __( 'Recommended work', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'mandatory_work',
						'label' => __( 'Mandatory work', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'optional_work',
						'label' => __( 'Optional work', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'estimate_amount',
						'label' => __( 'Estimate amount', 'tossa-workshop' ),
						'type'  => 'decimal',
						'cap'   => 'tcw_edit_prices',
					),
					array(
						'key'   => 'approval_required',
						'label' => __( 'Client approval required', 'tossa-workshop' ),
						'type'  => 'checkbox',
						'help'  => __( 'When enabled, the client can approve or decline via their status link.', 'tossa-workshop' ),
					),
				),
			),
			array(
				'id'        => 'work',
				'title'     => __( 'Work & Completion', 'tossa-workshop' ),
				'collapsed' => true,
				'fields'    => array(
					array(
						'key'   => 'work_log',
						'label' => __( 'Work log', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'parts_used',
						'label' => __( 'Parts used', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'labor_time',
						'label' => __( 'Labour time', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'after_photos',
						'label' => __( 'After photos', 'tossa-workshop' ),
						'type'  => 'gallery',
					),
					array(
						'key'     => 'quality_check',
						'label'   => __( 'Quality check', 'tossa-workshop' ),
						'type'    => 'multicheck',
						'options' => $opts['quality_check'],
					),
					array(
						'key'     => 'safety_status',
						'label'   => __( 'Safety status', 'tossa-workshop' ),
						'type'    => 'select',
						'options' => $opts['safety_status'],
					),
					array(
						'key'   => 'final_price',
						'label' => __( 'Final price', 'tossa-workshop' ),
						'type'  => 'decimal',
						'cap'   => 'tcw_edit_prices',
					),
					array(
						'key'   => 'payment_link',
						'label' => __( 'Payment link', 'tossa-workshop' ),
						'type'  => 'url',
					),
					array(
						'key'   => 'collected_by',
						'label' => __( 'Collected by', 'tossa-workshop' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'collection_datetime',
						'label' => __( 'Collection date/time', 'tossa-workshop' ),
						'type'  => 'datetime',
					),
					array(
						'key'   => 'closing_notes',
						'label' => __( 'Closing notes', 'tossa-workshop' ),
						'type'  => 'textarea',
					),
				),
			),
		);
	}

	/**
	 * Flat map of every field key => definition.
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
}
