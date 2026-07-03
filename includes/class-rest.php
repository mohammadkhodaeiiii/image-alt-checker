<?php
/**
 * REST API integration.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

use ImageAltChecker\Interfaces\ServiceInterface;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes a single, capability-gated REST endpoint with the latest report and
 * provides the extension surface for future endpoints. No public endpoints are
 * registered.
 */
final class Rest implements ServiceInterface {

	/**
	 * REST namespace.
	 */
	public const REST_NAMESPACE = 'image-alt-checker/v1';

	/**
	 * Shared hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Reporter.
	 *
	 * @var Reporter
	 */
	private Reporter $reporter;

	/**
	 * Constructor.
	 *
	 * @param Loader   $loader   Shared hook loader.
	 * @param Reporter $reporter Reporter.
	 */
	public function __construct( Loader $loader, Reporter $reporter ) {
		$this->loader   = $loader;
		$this->reporter = $reporter;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/report',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_report' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		/**
		 * Fires after core REST routes are registered.
		 *
		 * Future Pro endpoints can hook here to register additional routes
		 * under the same namespace without modifying core.
		 *
		 * @param string $namespace REST namespace.
		 */
		do_action( 'iac_register_rest_routes', self::REST_NAMESPACE );
	}

	/**
	 * Permission check for the report endpoint.
	 *
	 * @return bool
	 */
	public function can_view(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return the latest stored report.
	 *
	 * @return WP_REST_Response
	 */
	public function get_report(): WP_REST_Response {
		return new WP_REST_Response( $this->reporter->get_last_report(), 200 );
	}
}
