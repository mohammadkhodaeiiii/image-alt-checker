<?php
/**
 * Uninstall routine.
 *
 * Removes every trace of the plugin: options on a single site and across all
 * sites of a multisite network, plus any transients it created.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Settings option key stored by the plugin.
 */
const IAC_UNINSTALL_OPTION = 'iac_settings';

/**
 * Scan-state option key stored by the plugin.
 */
const IAC_UNINSTALL_STATE = 'iac_scan_state';

/**
 * Last-report option key stored by the plugin.
 */
const IAC_UNINSTALL_REPORT = 'iac_last_report';

/**
 * Transient key stored by the plugin.
 */
const IAC_UNINSTALL_TRANSIENT = 'iac_url_cache';

/**
 * Delete all plugin data for the current site.
 *
 * @return void
 */
function iac_uninstall_site(): void {
	delete_option( IAC_UNINSTALL_OPTION );
	delete_option( IAC_UNINSTALL_STATE );
	delete_option( IAC_UNINSTALL_REPORT );
	delete_transient( IAC_UNINSTALL_TRANSIENT );
}

if ( is_multisite() ) {
	$iac_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $iac_site_ids as $iac_site_id ) {
		switch_to_blog( (int) $iac_site_id );
		iac_uninstall_site();
		restore_current_blog();
	}

	delete_site_option( IAC_UNINSTALL_OPTION );
} else {
	iac_uninstall_site();
}
