<?php
/**
 * Storage abstraction.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists scan state and reports using the Options API and Transients.
 *
 * This class is the single storage surface for the plugin. A Pro version can
 * extend it (and be swapped in via the `iac_storage` filter) to persist the
 * same data in custom database tables without changing any caller.
 */
class Database {

	/**
	 * Option key for the in-progress scan state.
	 */
	public const STATE_OPTION = 'iac_scan_state';

	/**
	 * Option key for the last completed report.
	 */
	public const REPORT_OPTION = 'iac_last_report';

	/**
	 * Transient key for the attachment URL lookup cache.
	 */
	public const CACHE_TRANSIENT = 'iac_url_cache';

	/**
	 * Retrieve the current scan state.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state(): array {
		$state = get_option( self::STATE_OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist the scan state.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return void
	 */
	public function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Delete the scan state.
	 *
	 * @return void
	 */
	public function delete_state(): void {
		delete_option( self::STATE_OPTION );
	}

	/**
	 * Whether a scan is currently in progress.
	 *
	 * @return bool
	 */
	public function has_active_scan(): bool {
		$state = $this->get_state();

		return isset( $state['status'] ) && 'running' === $state['status'];
	}

	/**
	 * Retrieve the last completed report.
	 *
	 * @return array<string, mixed>
	 */
	public function get_report(): array {
		$report = get_option( self::REPORT_OPTION, array() );

		return is_array( $report ) ? $report : array();
	}

	/**
	 * Persist a completed report.
	 *
	 * @param array<string, mixed> $report Report data.
	 * @return void
	 */
	public function save_report( array $report ): void {
		update_option( self::REPORT_OPTION, $report, false );
	}

	/**
	 * Delete the stored report.
	 *
	 * @return void
	 */
	public function delete_report(): void {
		delete_option( self::REPORT_OPTION );
	}

	/**
	 * Retrieve the cached attachment URL lookup map.
	 *
	 * @return array<string, int>
	 */
	public function get_url_cache(): array {
		$cache = get_transient( self::CACHE_TRANSIENT );

		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * Persist the attachment URL lookup map.
	 *
	 * @param array<string, int> $cache    URL to attachment ID map.
	 * @param int                $lifetime Cache lifetime in seconds.
	 * @return void
	 */
	public function save_url_cache( array $cache, int $lifetime ): void {
		set_transient( self::CACHE_TRANSIENT, $cache, max( 0, $lifetime ) );
	}

	/**
	 * Clear all cached data created during scanning.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_TRANSIENT );
	}
}
