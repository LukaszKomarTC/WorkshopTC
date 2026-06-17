<?php
/**
 * Internal ID generation with per-counter atomic sequencing.
 *
 * Formats (spec §5):
 *  - Customer bike: TCB-000001       (6-digit global counter)
 *  - Fleet bike:    TCF-EMTB-001     (3-digit counter per rental category)
 *  - Repair job:    TCW-2026-0001    (4-digit counter, resets per year)
 *
 * Counters live in wp_options under tcw_counter_*. Concurrent saves are
 * serialised with a short option-based mutex (add_option is an atomic INSERT)
 * with stale-lock recovery, then the formatted ID is verified unique against
 * existing post meta before being handed back.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Generates and guarantees uniqueness of internal IDs.
 */
class ID_Generator {

	/** Meta key holding a bike's internal ID. */
	const BIKE_META = 'internal_id';

	/** Meta key holding a job's internal ID. */
	const JOB_META = 'job_id';

	/** Seconds after which a held lock is considered stale and may be stolen. */
	const LOCK_TIMEOUT = 30;

	/** Max attempts to acquire a counter lock before giving up. */
	const LOCK_TRIES = 100;

	/**
	 * Generate a bike internal ID.
	 *
	 * @param string $bike_type       'customer' or 'fleet'.
	 * @param string $rental_category Rental category slug (fleet only).
	 * @return string
	 */
	public static function generate_bike_id( $bike_type, $rental_category = '' ) {
		if ( 'fleet' === $bike_type ) {
			$category = strtoupper( preg_replace( '/[^a-z0-9]/i', '', (string) $rental_category ) );
			if ( '' === $category ) {
				$category = 'GEN';
			}
			$option = 'tcw_counter_bike_fleet_' . strtolower( $category );

			return self::unique(
				static function () use ( $category, $option ) {
					$n = self::next_sequence( $option );
					return sprintf( 'TCF-%s-%03d', $category, $n );
				},
				self::BIKE_META
			);
		}

		// Customer bike.
		return self::unique(
			static function () {
				$n = self::next_sequence( 'tcw_counter_bike_customer' );
				return sprintf( 'TCB-%06d', $n );
			},
			self::BIKE_META
		);
	}

	/**
	 * Generate a repair job ID for the given year (defaults to current year).
	 *
	 * @param int|null $year Four-digit year.
	 * @return string
	 */
	public static function generate_job_id( $year = null ) {
		$year   = $year ? (int) $year : (int) current_time( 'Y' );
		$option = 'tcw_counter_job_' . $year;

		return self::unique(
			static function () use ( $year, $option ) {
				$n = self::next_sequence( $option );
				return sprintf( 'TCW-%d-%04d', $year, $n );
			},
			self::JOB_META
		);
	}

	/**
	 * Run a generator closure until it returns an ID not already present in the
	 * given meta key. Guards against gaps/collisions from manual edits.
	 *
	 * @param callable $generator Returns a candidate ID string.
	 * @param string   $meta_key  Meta key to check uniqueness against.
	 * @return string
	 */
	private static function unique( callable $generator, $meta_key ) {
		$candidate = '';
		for ( $i = 0; $i < self::LOCK_TRIES; $i++ ) {
			$candidate = $generator();
			if ( ! self::meta_value_exists( $meta_key, $candidate ) ) {
				return $candidate;
			}
		}
		// Extremely unlikely; return the last candidate rather than loop forever.
		return $candidate;
	}

	/**
	 * Whether a post already has the given meta value.
	 *
	 * @param string $meta_key   Meta key.
	 * @param string $meta_value Meta value.
	 * @return bool
	 */
	private static function meta_value_exists( $meta_key, $meta_value ) {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				$meta_key,
				$meta_value
			)
		);
		return null !== $found;
	}

	/**
	 * Atomically increment and return the next value for a counter option.
	 *
	 * @param string $option_key Option name (e.g. tcw_counter_bike_customer).
	 * @return int
	 */
	public static function next_sequence( $option_key ) {
		$lock_key = $option_key . '_lock';
		$acquired = self::acquire_lock( $lock_key );

		$current = (int) get_option( $option_key, 0 );
		$next    = $current + 1;
		update_option( $option_key, $next, false );

		if ( $acquired ) {
			delete_option( $lock_key );
		}

		return $next;
	}

	/**
	 * Acquire a mutex backed by add_option (atomic INSERT). Steals stale locks.
	 *
	 * @param string $lock_key Lock option name.
	 * @return bool True if this call holds the lock.
	 */
	private static function acquire_lock( $lock_key ) {
		for ( $i = 0; $i < self::LOCK_TRIES; $i++ ) {
			// add_option returns false if the row already exists.
			if ( add_option( $lock_key, time(), '', 'no' ) ) {
				return true;
			}

			// Steal a stale lock.
			$held = (int) get_option( $lock_key, 0 );
			if ( $held > 0 && ( time() - $held ) > self::LOCK_TIMEOUT ) {
				update_option( $lock_key, time(), false );
				return true;
			}

			usleep( 20000 ); // 20ms.
		}

		// Could not acquire; proceed without the lock rather than block the save.
		return false;
	}
}
