<?php
/**
 * Service contract.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every bootable service must implement this contract.
 *
 * The single responsibility of a service is to hook itself into WordPress when
 * its register() method is invoked by the Plugin bootstrapper.
 */
interface ServiceInterface {

	/**
	 * Register the service hooks with WordPress.
	 *
	 * @return void
	 */
	public function register(): void;
}
