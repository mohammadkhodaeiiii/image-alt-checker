<?php
/**
 * Deactivation routine.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cleanup of transient/temporary data on deactivation.
 *
 * Persistent settings and the last report are intentionally preserved here;
 * they are only removed on uninstall.
 */
final class Deactivator {

	/**
	 * Remove temporary data created by the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_option( Database::STATE_OPTION );
		delete_transient( Database::CACHE_TRANSIENT );

		/**
		 * Fires during plugin deactivation so add-ons can clean up too.
		 */
		do_action( 'iac_deactivate' );
	}
}
