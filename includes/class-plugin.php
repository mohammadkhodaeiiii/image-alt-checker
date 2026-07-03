<?php
/**
 * Plugin bootstrapper.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

use ImageAltChecker\Interfaces\ServiceInterface;
use ImageAltChecker\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin: wires the loader, instantiates services and runs them.
 *
 * This class is intentionally thin. It contains no business logic; it only
 * assembles collaborators and starts the loader.
 */
final class Plugin {

	use Singleton;

	/**
	 * Hook loader shared across services.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Registered services.
	 *
	 * @var array<int, ServiceInterface>
	 */
	private array $services = array();

	/**
	 * Whether the plugin has already booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Bootstrap the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		$this->loader = new Loader();

		$this->load_textdomain();
		$this->register_services();
		$this->loader->run();
	}

	/**
	 * Access the shared loader instance.
	 *
	 * @return Loader
	 */
	public function loader(): Loader {
		return $this->loader;
	}

	/**
	 * Build, register and store every service.
	 *
	 * Domain collaborators (Database, Media, Analyzer, Reporter, Scanner) are
	 * plain, reusable objects injected into the hookable services. Only the
	 * services that actually register WordPress hooks implement ServiceInterface.
	 *
	 * @return void
	 */
	private function register_services(): void {
		$database = $this->build_storage();
		$media    = new Media( $database );
		$analyzer = new Analyzer();
		$reporter = new Reporter( $database );
		$scanner  = new Scanner( $database, $media, $analyzer, $reporter );

		$this->services = array(
			new Settings( $this->loader ),
			new Assets( $this->loader ),
			new Admin( $this->loader, $reporter, $database ),
			new Ajax( $this->loader, $scanner, $reporter, $database ),
			new Rest( $this->loader, $reporter ),
		);

		/**
		 * Filter the registered services before they hook into WordPress.
		 *
		 * A Pro add-on can append additional ServiceInterface implementations
		 * here without modifying the core plugin.
		 *
		 * @param array<int, ServiceInterface> $services Registered services.
		 * @param Loader                       $loader   Shared hook loader.
		 * @param Database                     $database Storage abstraction.
		 */
		$this->services = (array) apply_filters( 'iac_services', $this->services, $this->loader, $database );

		foreach ( $this->services as $service ) {
			if ( $service instanceof ServiceInterface ) {
				$service->register();
			}
		}
	}

	/**
	 * Build the storage abstraction.
	 *
	 * The instance is filterable so a Pro version can return a custom-table
	 * backed implementation that extends Database without touching core.
	 *
	 * @return Database
	 */
	private function build_storage(): Database {
		$database = new Database();

		/**
		 * Filter the storage implementation used by the plugin.
		 *
		 * @param Database $database Default Options/Transients storage.
		 */
		$filtered = apply_filters( 'iac_storage', $database );

		return $filtered instanceof Database ? $filtered : $database;
	}

	/**
	 * Load the plugin translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'image-alt-checker',
			false,
			dirname( plugin_basename( IAC_FILE ) ) . '/languages'
		);
	}
}
