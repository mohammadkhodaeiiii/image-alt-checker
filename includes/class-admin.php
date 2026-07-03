<?php
/**
 * Admin menus and page rendering.
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
 * Registers the native admin menu and renders the plugin's admin views.
 *
 * Contains no business logic: it pulls data from the Reporter/Database and
 * hands it to the view templates.
 */
final class Admin implements ServiceInterface {

	/**
	 * Top-level menu / Dashboard slug.
	 */
	public const MENU_SLUG = 'image-alt-checker';

	/**
	 * Scanner page slug.
	 */
	public const SCANNER_SLUG = 'image-alt-checker-scanner';

	/**
	 * Reports page slug.
	 */
	public const REPORTS_SLUG = 'image-alt-checker-reports';

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
	 * @param Reporter $reporter Reporter.
	 * @param Database $database Storage abstraction.
	 */
	public function __construct( Loader $loader, Reporter $reporter, Database $database ) {
		$this->loader   = $loader;
		$this->reporter = $reporter;
		$this->database = $database;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'admin_menu', $this, 'register_menu' );
	}

	/**
	 * Register the native admin menu and its submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Image Alt Checker', 'image-alt-checker' ),
			__( 'Image Alt Checker', 'image-alt-checker' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-images-alt2',
			81
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'image-alt-checker' ),
			__( 'Dashboard', 'image-alt-checker' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Scanner', 'image-alt-checker' ),
			__( 'Scanner', 'image-alt-checker' ),
			self::CAPABILITY,
			self::SCANNER_SLUG,
			array( $this, 'render_scanner' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Reports', 'image-alt-checker' ),
			__( 'Reports', 'image-alt-checker' ),
			self::CAPABILITY,
			self::REPORTS_SLUG,
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'image-alt-checker' ),
			__( 'Settings', 'image-alt-checker' ),
			self::CAPABILITY,
			Settings::PAGE,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->guard();

		$report          = $this->reporter->get_last_report();
		$has_active_scan = $this->database->has_active_scan();
		$scanner_url     = $this->page_url( self::SCANNER_SLUG );
		$reports_url     = $this->page_url( self::REPORTS_SLUG );
		$settings_url    = $this->page_url( Settings::PAGE );

		$this->view( 'dashboard', compact( 'report', 'has_active_scan', 'scanner_url', 'reports_url', 'settings_url' ) );
	}

	/**
	 * Render the Scanner page.
	 *
	 * @return void
	 */
	public function render_scanner(): void {
		$this->guard();

		$report          = $this->reporter->get_last_report();
		$has_active_scan = $this->database->has_active_scan();
		$enabled         = Helper::to_bool( Helper::get_setting( 'enabled', true ) );
		$post_types      = Helper::sanitize_post_types( Helper::get_setting( 'post_types', array() ) );
		$settings_url    = $this->page_url( Settings::PAGE );

		$this->view( 'scanner', compact( 'report', 'has_active_scan', 'enabled', 'post_types', 'settings_url' ) );
	}

	/**
	 * Render the Reports page.
	 *
	 * @return void
	 */
	public function render_reports(): void {
		$this->guard();

		$report      = $this->reporter->get_last_report();
		$scanner_url = $this->page_url( self::SCANNER_SLUG );

		$this->view( 'reports', compact( 'report', 'scanner_url' ) );
	}

	/**
	 * Render the Settings page.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		$this->guard();

		$reset_url = wp_nonce_url(
			add_query_arg( 'action', Settings::RESET_ACTION, admin_url( 'admin-post.php' ) ),
			Settings::RESET_ACTION
		);

		$notice = isset( $_GET['iac_notice'] ) ? sanitize_key( wp_unslash( $_GET['iac_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->view( 'settings', compact( 'reset_url', 'notice' ) );
	}

	/**
	 * Render an admin notice partial.
	 *
	 * @param string $type    Notice type (success|error|warning|info).
	 * @param string $message Message text.
	 * @return void
	 */
	public function render_notice( string $type, string $message ): void {
		$partial = IAC_PATH . 'admin/partials/notice.php';

		if ( is_readable( $partial ) ) {
			require $partial;
		}
	}

	/**
	 * Include an admin view template with scoped variables.
	 *
	 * @param string               $name View file name (without extension).
	 * @param array<string, mixed> $vars Variables exposed to the template.
	 * @return void
	 */
	private function view( string $name, array $vars = array() ): void {
		$file = IAC_PATH . 'admin/views/' . sanitize_file_name( $name ) . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$admin = $this;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );

		require $file;
	}

	/**
	 * Build the admin URL for one of the plugin pages.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	private function page_url( string $slug ): string {
		return add_query_arg( 'page', $slug, admin_url( 'admin.php' ) );
	}

	/**
	 * Ensure the current user may view the plugin pages.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'image-alt-checker' ) );
		}
	}
}
