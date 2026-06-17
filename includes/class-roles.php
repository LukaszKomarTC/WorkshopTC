<?php
/**
 * Roles and capabilities registration.
 *
 * Defines the three workshop roles and the semantic + CPT capabilities that
 * back them. The role names from the spec (workshop manager / mechanic /
 * front desk) are implemented as WordPress roles that are granted the
 * semantic capabilities (tcw_view_bikes, tcw_edit_prices, ...).
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Central registry for roles and capabilities.
 */
class Roles {

	const ROLE_MANAGER    = 'tcw_workshop_manager';
	const ROLE_MECHANIC   = 'tcw_mechanic';
	const ROLE_FRONT_DESK = 'tcw_front_desk';

	/**
	 * Semantic capabilities used by the plugin's own permission checks.
	 *
	 * @return string[]
	 */
	public static function semantic_caps() {
		return array(
			'tcw_view_bikes',
			'tcw_edit_bikes',
			'tcw_edit_jobs',
			'tcw_change_status',
			'tcw_advance_status_full',
			'tcw_edit_prices',
			'tcw_close_jobs',
			'tcw_manage_settings',
		);
	}

	/**
	 * Build the primitive capability list for a CPT capability_type pair.
	 *
	 * @param string $singular Singular capability base (e.g. tcw_bike).
	 * @param string $plural   Plural capability base (e.g. tcw_bikes).
	 * @return string[]
	 */
	public static function cpt_caps( $singular, $plural ) {
		return array(
			"edit_{$singular}",
			"read_{$singular}",
			"delete_{$singular}",
			"edit_{$plural}",
			"edit_others_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_private_{$plural}",
			"delete_published_{$plural}",
			"delete_others_{$plural}",
			"edit_private_{$plural}",
			"edit_published_{$plural}",
			"create_{$plural}",
		);
	}

	/**
	 * CPT caps for bikes.
	 *
	 * @return string[]
	 */
	public static function bike_caps() {
		return self::cpt_caps( 'tcw_bike', 'tcw_bikes' );
	}

	/**
	 * CPT caps for repair jobs.
	 *
	 * @return string[]
	 */
	public static function job_caps() {
		return self::cpt_caps( 'tcw_repair_job', 'tcw_repair_jobs' );
	}

	/**
	 * Every capability this plugin defines.
	 *
	 * @return string[]
	 */
	public static function all_caps() {
		return array_merge(
			self::semantic_caps(),
			self::bike_caps(),
			self::job_caps()
		);
	}

	/**
	 * Create roles and grant capabilities. Idempotent.
	 *
	 * @return void
	 */
	public static function add_roles() {
		// Workshop manager — full access.
		self::ensure_role(
			self::ROLE_MANAGER,
			__( 'Workshop Manager', 'tossa-workshop' ),
			array_merge(
				array( 'read' => true ),
				self::caps_true( self::all_caps() )
			)
		);

		// Mechanic — view bikes, work on jobs, change status (gated in logic),
		// no pricing, no closing, no settings.
		$mechanic_caps = array_merge(
			array( 'tcw_view_bikes', 'tcw_edit_jobs', 'tcw_change_status' ),
			self::job_caps(),
			array( 'read_tcw_bike', 'read_private_tcw_bikes' )
		);
		self::ensure_role(
			self::ROLE_MECHANIC,
			__( 'Workshop Mechanic', 'tossa-workshop' ),
			array_merge( array( 'read' => true ), self::caps_true( $mechanic_caps ) )
		);

		// Front desk — create bikes & jobs, intake, notifications, mark
		// collected (can advance status up to delivered, but not close); no
		// full financials, no closing, no settings.
		$front_desk_caps = array_merge(
			array( 'tcw_view_bikes', 'tcw_edit_bikes', 'tcw_edit_jobs', 'tcw_change_status', 'tcw_advance_status_full' ),
			self::bike_caps(),
			self::job_caps()
		);
		self::ensure_role(
			self::ROLE_FRONT_DESK,
			__( 'Workshop Front Desk', 'tossa-workshop' ),
			array_merge( array( 'read' => true ), self::caps_true( $front_desk_caps ) )
		);

		// Administrator gets everything.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove roles and strip caps from administrator. Used on uninstall.
	 *
	 * @return void
	 */
	public static function remove_roles() {
		remove_role( self::ROLE_MANAGER );
		remove_role( self::ROLE_MECHANIC );
		remove_role( self::ROLE_FRONT_DESK );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * Create or update a role with the given capabilities.
	 *
	 * @param string $slug Role slug.
	 * @param string $name Display name.
	 * @param array  $caps Capability map ( cap => bool ).
	 * @return void
	 */
	private static function ensure_role( $slug, $name, array $caps ) {
		// remove_role then add_role guarantees caps reflect the current spec
		// on re-activation without orphaning user assignments (WP keeps the
		// user's role slug; re-adding restores the definition).
		remove_role( $slug );
		add_role( $slug, $name, $caps );
	}

	/**
	 * Turn a flat cap list into a ( cap => true ) map.
	 *
	 * @param string[] $caps Capability names.
	 * @return array<string,bool>
	 */
	private static function caps_true( array $caps ) {
		return array_fill_keys( array_values( array_unique( $caps ) ), true );
	}
}
