<?php
/**
 * Media library helper.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves information about images, bridging rendered markup and the Media
 * Library. Handles both attachments and externally hosted images and caches the
 * expensive URL-to-attachment lookups.
 */
class Media {

	/**
	 * Storage abstraction used for the persistent URL cache.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * In-memory copy of the URL to attachment ID map.
	 *
	 * @var array<string, int>|null
	 */
	private ?array $cache = null;

	/**
	 * Whether the cache has unsaved changes.
	 *
	 * @var bool
	 */
	private bool $dirty = false;

	/**
	 * Constructor.
	 *
	 * @param Database $database Storage abstraction.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Extract the file name (without extension) from an image URL.
	 *
	 * @param string $url Image URL or path.
	 * @return string
	 */
	public function filename_from_url( string $url ): string {
		$url  = (string) wp_parse_url( $url, PHP_URL_PATH ) ?: $url;
		$base = wp_basename( $url );

		$dot = strrpos( $base, '.' );
		if ( false !== $dot ) {
			$base = substr( $base, 0, $dot );
		}

		return rawurldecode( $base );
	}

	/**
	 * Whether a URL points to an SVG image.
	 *
	 * @param string $url Image URL.
	 * @return bool
	 */
	public function is_svg( string $url ): bool {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH ) ?: $url;

		return 'svg' === strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
	}

	/**
	 * Resolve the attachment ID for a local image URL.
	 *
	 * Returns 0 for externally hosted images or unresolved URLs.
	 *
	 * @param string $url Image URL.
	 * @return int
	 */
	public function get_attachment_id( string $url ): int {
		$url = $this->normalize_url( $url );

		if ( '' === $url || ! $this->is_local( $url ) ) {
			return 0;
		}

		$this->load_cache();

		if ( array_key_exists( $url, (array) $this->cache ) ) {
			return (int) $this->cache[ $url ];
		}

		$id = attachment_url_to_postid( $url );

		$this->cache[ $url ] = (int) $id;
		$this->dirty         = true;

		return (int) $id;
	}

	/**
	 * Retrieve the Media Library ALT text for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public function get_library_alt( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return is_string( $alt ) ? $alt : '';
	}

	/**
	 * Whether a URL belongs to this site.
	 *
	 * @param string $url Image URL.
	 * @return bool
	 */
	public function is_local( string $url ): bool {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( null === $host || '' === (string) $host ) {
			return true;
		}

		return is_string( $home ) && strtolower( (string) $host ) === strtolower( $home );
	}

	/**
	 * Persist the URL cache when it has changed.
	 *
	 * @return void
	 */
	public function flush(): void {
		if ( ! $this->dirty || null === $this->cache ) {
			return;
		}

		$lifetime = Helper::clamp_int(
			Helper::get_setting( 'cache_lifetime', 3600 ),
			Helper::CACHE_MIN,
			Helper::CACHE_MAX
		);

		$this->database->save_url_cache( $this->cache, $lifetime );
		$this->dirty = false;
	}

	/**
	 * Normalize a URL to a protocol-relative-safe absolute form.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function normalize_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';
			$url    = $scheme . $url;
		}

		return $url;
	}

	/**
	 * Lazily load the persistent URL cache.
	 *
	 * @return void
	 */
	private function load_cache(): void {
		if ( null === $this->cache ) {
			$this->cache = $this->database->get_url_cache();
		}
	}
}
