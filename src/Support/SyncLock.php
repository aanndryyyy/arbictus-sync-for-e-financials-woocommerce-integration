<?php
/**
 * Short-lived cross-process lock guarding a single order sync.
 *
 * @package Arbictus\EFinancialsPlugin
 */

declare(strict_types=1);

namespace Aanndryyyy\EFinancialsPlugin\Support;

/**
 * Action Scheduler only de-duplicates *pending* actions, so a claimed job plus a
 * freshly enqueued one can run concurrently and register two invoices for one
 * order.
 *
 * The lock is an options row claimed with `INSERT IGNORE`, so the unique
 * `option_name` key makes acquisition atomic in the database (the same approach
 * as core's `WP_Upgrader::create_lock()`). Transients and `add_option()` are not
 * safe here: the former is get-then-set, the latter upserts. A lock older than
 * the TTL is taken over with a compare-and-swap so a fatal never leaves it stuck,
 * and each holder stores a unique token so it can only ever release its own lock.
 */
final class SyncLock {

	/**
	 * Lock lifetime in seconds.
	 */
	public const TTL = 300;

	/**
	 * Tokens of the locks this process holds, keyed by option name.
	 *
	 * @var array<string, string>
	 */
	private static array $tokens = [];

	/**
	 * Try to claim the lock for a subject.
	 *
	 * @param string $key Lock key (for example "order-42").
	 *
	 * @return bool True when this process owns the lock.
	 */
	public static function acquire( string $key ): bool {

		global $wpdb;
		assert( $wpdb instanceof \wpdb );

		$name  = self::name( $key );
		$now   = \time();
		$token = $now . ':' . \wp_generate_uuid4();

		$insert = $wpdb->prepare(
			"INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$wpdb->options,
			$name,
			$token
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Atomic lock; must bypass the options cache.
		if ( \is_string( $insert ) && 1 === $wpdb->query( $insert ) ) {
			self::$tokens[ $name ] = $token;

			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must read the live row, not the cache.
		$held_since = $wpdb->get_var(
			$wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $name )
		);

		if ( ! \is_string( $held_since ) || (int) $held_since > $now - self::TTL ) {
			return false;
		}

		// Stale lock: only one process can swap the exact value it read.
		$swap = $wpdb->prepare(
			'UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s',
			$wpdb->options,
			$token,
			$name,
			$held_since
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Compare-and-swap; must bypass the options cache.
		if ( ! \is_string( $swap ) || 1 !== $wpdb->query( $swap ) ) {
			return false;
		}

		self::$tokens[ $name ] = $token;

		return true;
	}

	/**
	 * Release a previously acquired lock.
	 *
	 * @param string $key Lock key.
	 */
	public static function release( string $key ): void {

		global $wpdb;
		assert( $wpdb instanceof \wpdb );

		$name = self::name( $key );

		if ( ! isset( self::$tokens[ $name ] ) ) {
			return;
		}

		// Only delete our own token: after a stale takeover the row belongs to
		// another process, and removing it would let a third one in.
		$delete = $wpdb->prepare(
			'DELETE FROM %i WHERE option_name = %s AND option_value = %s',
			$wpdb->options,
			$name,
			self::$tokens[ $name ]
		);

		unset( self::$tokens[ $name ] );

		if ( \is_string( $delete ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Compare-and-delete; the row never enters the options cache.
			$wpdb->query( $delete );
		}
	}

	/**
	 * Build the option name for a lock key.
	 *
	 * @param string $key Lock key.
	 */
	private static function name( string $key ): string {

		return 'ef_sync_lock_' . \sanitize_key( $key );
	}
}
