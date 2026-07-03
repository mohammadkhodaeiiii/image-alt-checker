<?php
/**
 * AJAX endpoints.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

use ImageAltChecker\Interfaces\ServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secure AJAX endpoints that drive the batch scanner from the admin UI.
 *
 * Every endpoint verifies a shared nonce and the manage_options capability
 * before doing any work.
 */
final class Ajax implements ServiceInterface {

	/**
	 * Nonce action shared by every endpoint.
	 */
	public const NONCE_ACTION = 'iac_scan';

	/**
	 * Start-scan action.
	 */
	public const ACTION_START = 'iac_start_scan';

	/**
	 * Continue-scan action.
	 */
	public const ACTION_CONTINUE = 'iac_continue_scan';

	/**
	 * Cancel-scan action.
	 */
	public const ACTION_CANCEL = 'iac_cancel_scan';

	/**
	 * Clear-cache action.
	 */
	public const ACTION_CLEAR = 'iac_clear_cache';

	/**
	 * Refresh-report action.
	 */
	public const ACTION_REFRESH = 'iac_refresh_report';

	/**
	 * Required capability.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Shared hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Scanner.
	 *
	 * @var Scanner
	 */
	private Scanner $scanner;

	/**
	 * Reporter.
	 *
	 * @var Reporter
	 */
	private Reporter $reporter;

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Constructor.
	 *
	 * @param Loader   $loader   Shared hook loader.
	 * @param Scanner  $scanner  Scanner.
	 * @param Reporter $reporter Reporter.
	 * @param Database $database Storage abstraction.
	 */
	public function __construct( Loader $loader, Scanner $scanner, Reporter $reporter, Database $database ) {
		$this->loader   = $loader;
		$this->scanner  = $scanner;
		$this->reporter = $reporter;
		$this->database = $database;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_START, $this, 'start' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_CONTINUE, $this, 'continue_scan' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_CANCEL, $this, 'cancel' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_CLEAR, $this, 'clear_cache' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_REFRESH, $this, 'refresh' );
	}

	/**
	 * Start a new scan.
	 *
	 * @return void
	 */
	public function start(): void {
		$this->guard();

		if ( ! Helper::to_bool( Helper::get_setting( 'enabled', true ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'The scanner is disabled in the settings.', 'image-alt-checker' ) ),
				403
			);
		}

		wp_send_json_success( $this->scanner->start() );
	}

	/**
	 * Continue the current scan.
	 *
	 * @return void
	 */
	public function continue_scan(): void {
		$this->guard();

		wp_send_json_success( $this->scanner->process_batch() );
	}

	/**
	 * Cancel the current scan.
	 *
	 * @return void
	 */
	public function cancel(): void {
		$this->guard();

		wp_send_json_success( $this->scanner->cancel() );
	}

	/**
	 * Clear cached lookups and any in-progress scan state.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->guard();

		$this->database->clear_cache();
		$this->database->delete_state();

		wp_send_json_success(
			array( 'message' => __( 'Cache cleared.', 'image-alt-checker' ) )
		);
	}

	/**
	 * Return the latest stored report.
	 *
	 * @return void
	 */
	public function refresh(): void {
		$this->guard();

		wp_send_json_success(
			array( 'stats' => $this->reporter->get_last_report() )
		);
	}

	/**
	 * Verify the nonce and capability, terminating the request on failure.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'image-alt-checker' ) ),
				403
			);
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do this.', 'image-alt-checker' ) ),
				403
			);
		}
	}
}
