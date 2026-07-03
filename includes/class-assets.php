<?php
/**
 * Admin asset registration and enqueueing.
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
 * Registers and enqueues the admin assets.
 *
 * Assets load only on the plugin's own admin screens. No front-end assets are
 * ever enqueued: this is an administration-only tool.
 */
final class Assets implements ServiceInterface {

	/**
	 * Admin style/script handle prefix.
	 */
	private const HANDLE = 'image-alt-checker';

	/**
	 * Shared hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Shared hook loader.
	 */
	public function __construct( Loader $loader ) {
		$this->loader = $loader;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin', 10, 1 );
	}

	/**
	 * Enqueue admin assets on the plugin screens only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		if ( ! $this->is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE . '-admin',
			IAC_URL . 'assets/css/admin.css',
			array(),
			$this->asset_version( 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			self::HANDLE . '-admin',
			IAC_URL . 'assets/js/admin.js',
			array(),
			$this->asset_version( 'assets/js/admin.js' ),
			array( 'in_footer' => true )
		);

		wp_enqueue_script(
			self::HANDLE . '-scan',
			IAC_URL . 'assets/js/scan.js',
			array(),
			$this->asset_version( 'assets/js/scan.js' ),
			array( 'in_footer' => true )
		);

		wp_localize_script( self::HANDLE . '-scan', 'iacScan', $this->script_data() );
	}

	/**
	 * Build the data object exposed to the scan script.
	 *
	 * @return array<string, mixed>
	 */
	private function script_data(): array {
		return array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( Ajax::NONCE_ACTION ),
			'actions'  => array(
				'start'    => Ajax::ACTION_START,
				'continue' => Ajax::ACTION_CONTINUE,
				'cancel'   => Ajax::ACTION_CANCEL,
				'clear'    => Ajax::ACTION_CLEAR,
				'refresh'  => Ajax::ACTION_REFRESH,
			),
			'autoScan' => Helper::to_bool( Helper::get_setting( 'auto_scan', false ) ),
			'labels'   => Helper::issue_labels(),
			'i18n'     => array(
				'starting'   => __( 'Starting scan…', 'image-alt-checker' ),
				'scanning'   => __( 'Scanning…', 'image-alt-checker' ),
				'complete'   => __( 'Scan complete.', 'image-alt-checker' ),
				'cancelled'  => __( 'Scan cancelled.', 'image-alt-checker' ),
				'error'      => __( 'An error occurred. Please try again.', 'image-alt-checker' ),
				'confirm'    => __( 'Are you sure?', 'image-alt-checker' ),
				'cleaveWarn' => __( 'A scan is in progress. Leaving will stop it.', 'image-alt-checker' ),
				'cacheClear' => __( 'Cache cleared.', 'image-alt-checker' ),
				'posts'      => __( 'posts', 'image-alt-checker' ),
			),
		);
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	private function is_plugin_screen( string $hook_suffix ): bool {
		return false !== strpos( $hook_suffix, 'image-alt-checker' );
	}

	/**
	 * Resolve an asset version, using filemtime() during development.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 * @return string
	 */
	private function asset_version( string $relative_path ): string {
		$absolute = IAC_PATH . ltrim( $relative_path, '/' );

		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && is_readable( $absolute ) ) {
			$mtime = filemtime( $absolute );

			if ( false !== $mtime ) {
				return (string) $mtime;
			}
		}

		return IAC_VERSION;
	}
}
