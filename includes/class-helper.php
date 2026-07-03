<?php
/**
 * Helper utilities.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless utility methods for sanitization, validation and option access.
 */
final class Helper {

	/**
	 * Maximum recommended ALT length in characters.
	 */
	public const ALT_MAX_LENGTH = 125;

	/**
	 * Minimum meaningful ALT length in characters.
	 */
	public const ALT_MIN_LENGTH = 3;

	/**
	 * Minimum allowed batch size.
	 */
	public const BATCH_MIN = 1;

	/**
	 * Maximum allowed batch size.
	 */
	public const BATCH_MAX = 200;

	/**
	 * Minimum cache lifetime in seconds.
	 */
	public const CACHE_MIN = 60;

	/**
	 * Maximum cache lifetime in seconds.
	 */
	public const CACHE_MAX = 86400;

	/**
	 * Minimum maximum-scan-limit value.
	 */
	public const LIMIT_MIN = 1;

	/**
	 * Maximum maximum-scan-limit value.
	 */
	public const LIMIT_MAX = 100000;

	/**
	 * Issue code: image has no alt attribute at all.
	 */
	public const ISSUE_MISSING = 'missing';

	/**
	 * Issue code: alt attribute present but empty.
	 */
	public const ISSUE_EMPTY = 'empty';

	/**
	 * Issue code: alt attribute contains only whitespace.
	 */
	public const ISSUE_WHITESPACE = 'whitespace';

	/**
	 * Issue code: duplicate alt text across images.
	 */
	public const ISSUE_DUPLICATE = 'duplicate';

	/**
	 * Issue code: alt text equals the file name.
	 */
	public const ISSUE_FILENAME = 'filename';

	/**
	 * Issue code: alt text longer than the recommended maximum.
	 */
	public const ISSUE_TOO_LONG = 'too_long';

	/**
	 * Issue code: alt text shorter than the recommended minimum.
	 */
	public const ISSUE_TOO_SHORT = 'too_short';

	/**
	 * Issue code: alt text looks auto-generated or meaningless.
	 */
	public const ISSUE_SUSPICIOUS = 'suspicious';

	/**
	 * Issue code: decorative image (informational, not a failure).
	 */
	public const ISSUE_DECORATIVE = 'decorative';

	/**
	 * Retrieve the default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		/**
		 * Filter the default plugin settings.
		 *
		 * @param array<string, mixed> $defaults Default settings.
		 */
		return (array) apply_filters(
			'iac_default_settings',
			array(
				'enabled'           => true,
				'post_types'        => array( 'post', 'page' ),
				'batch_size'        => 20,
				'ignore_svg'        => true,
				'ignore_decorative' => true,
				'cache_lifetime'    => 3600,
				'max_scan_limit'    => 500,
				'auto_scan'         => false,
			)
		);
	}

	/**
	 * Retrieve the merged plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( IAC_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::default_settings() );
	}

	/**
	 * Retrieve a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is missing.
	 * @return mixed
	 */
	public static function get_setting( string $key, mixed $default = null ): mixed {
		$settings = self::get_settings();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Sanitize the full settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::default_settings();

		return array(
			'enabled'           => self::to_bool( $input['enabled'] ?? false ),
			'post_types'        => self::sanitize_post_types( $input['post_types'] ?? array() ),
			'batch_size'        => self::clamp_int( $input['batch_size'] ?? $defaults['batch_size'], self::BATCH_MIN, self::BATCH_MAX ),
			'ignore_svg'        => self::to_bool( $input['ignore_svg'] ?? false ),
			'ignore_decorative' => self::to_bool( $input['ignore_decorative'] ?? false ),
			'cache_lifetime'    => self::clamp_int( $input['cache_lifetime'] ?? $defaults['cache_lifetime'], self::CACHE_MIN, self::CACHE_MAX ),
			'max_scan_limit'    => self::clamp_int( $input['max_scan_limit'] ?? $defaults['max_scan_limit'], self::LIMIT_MIN, self::LIMIT_MAX ),
			'auto_scan'         => self::to_bool( $input['auto_scan'] ?? false ),
		);
	}

	/**
	 * Cast a mixed value to boolean.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function to_bool( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize and clamp an integer to a range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Lower bound.
	 * @param int   $max   Upper bound.
	 * @return int
	 */
	public static function clamp_int( mixed $value, int $min, int $max ): int {
		$number = (int) $value;

		return (int) max( $min, min( $max, $number ) );
	}

	/**
	 * Sanitize a list of post type slugs against the registered public types.
	 *
	 * @param mixed $value Array or comma separated string.
	 * @return array<int, string>
	 */
	public static function sanitize_post_types( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value ) ?: array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = self::scannable_post_types();
		$clean   = array();

		foreach ( $value as $item ) {
			$key = sanitize_key( (string) $item );

			if ( '' !== $key && in_array( $key, $allowed, true ) ) {
				$clean[] = $key;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Retrieve the list of post type slugs that can be scanned.
	 *
	 * @return array<int, string>
	 */
	public static function scannable_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );

		unset( $types['attachment'] );

		/**
		 * Filter the post types that are eligible for scanning.
		 *
		 * @param array<int, string> $types Public post type slugs.
		 */
		$types = (array) apply_filters( 'iac_scannable_post_types', array_values( $types ) );

		return array_values( array_unique( array_map( 'sanitize_key', $types ) ) );
	}

	/**
	 * Normalize an alt string for comparison: trim, collapse whitespace, lowercase.
	 *
	 * @param string $value Raw alt text.
	 * @return string
	 */
	public static function normalize_alt( string $value ): string {
		$value = trim( $value );
		$value = (string) preg_replace( '/\s+/u', ' ', $value );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Multibyte safe string length.
	 *
	 * @param string $value String.
	 * @return int
	 */
	public static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Human readable label for an issue code.
	 *
	 * @param string $code Issue code.
	 * @return string
	 */
	public static function issue_label( string $code ): string {
		$labels = self::issue_labels();

		return $labels[ $code ] ?? $code;
	}

	/**
	 * Map of issue codes to translated labels.
	 *
	 * @return array<string, string>
	 */
	public static function issue_labels(): array {
		return array(
			self::ISSUE_MISSING    => __( 'Missing ALT', 'image-alt-checker' ),
			self::ISSUE_EMPTY      => __( 'Empty ALT', 'image-alt-checker' ),
			self::ISSUE_WHITESPACE => __( 'Whitespace-only ALT', 'image-alt-checker' ),
			self::ISSUE_DUPLICATE  => __( 'Duplicate ALT', 'image-alt-checker' ),
			self::ISSUE_FILENAME   => __( 'ALT equals file name', 'image-alt-checker' ),
			self::ISSUE_TOO_LONG   => __( 'ALT too long', 'image-alt-checker' ),
			self::ISSUE_TOO_SHORT  => __( 'ALT too short', 'image-alt-checker' ),
			self::ISSUE_SUSPICIOUS => __( 'Suspicious ALT', 'image-alt-checker' ),
			self::ISSUE_DECORATIVE => __( 'Decorative image', 'image-alt-checker' ),
		);
	}
}
