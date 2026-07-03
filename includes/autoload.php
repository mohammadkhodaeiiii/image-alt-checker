<?php
/**
 * PSR-4 style autoloader mapping the plugin namespace to WordPress-style file names.
 *
 * Classes resolve to "class-{name}.php", interfaces (placed under the Interfaces
 * sub-namespace) to "interface-{name}.php" and traits (under Traits) to
 * "trait-{name}.php". This keeps file naming consistent with the WordPress
 * Coding Standards while preserving PSR-4 namespace resolution.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$segments = explode( '\\', $relative );
		$name     = (string) array_pop( $segments );

		$directory = IAC_PATH . 'includes/';
		foreach ( $segments as $segment ) {
			$directory .= strtolower( $segment ) . '/';
		}

		if ( in_array( 'Interfaces', $segments, true ) ) {
			$type = 'interface';
			$name = (string) preg_replace( '/Interface$/', '', $name );
		} elseif ( in_array( 'Traits', $segments, true ) ) {
			$type = 'trait';
		} else {
			$type = 'class';
		}

		$slug = strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $name ) );
		$file = $directory . $type . '-' . $slug . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
