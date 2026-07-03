<?php
/**
 * Content scanner.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walks published content in memory-efficient batches, extracts every image and
 * delegates ALT quality judgement to the Analyzer, accumulating results through
 * the Reporter and persisting them through the Database.
 */
class Scanner {

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Media helper.
	 *
	 * @var Media
	 */
	private Media $media;

	/**
	 * ALT analyzer.
	 *
	 * @var Analyzer
	 */
	private Analyzer $analyzer;

	/**
	 * Reporter.
	 *
	 * @var Reporter
	 */
	private Reporter $reporter;

	/**
	 * Constructor.
	 *
	 * @param Database $database Storage abstraction.
	 * @param Media    $media    Media helper.
	 * @param Analyzer $analyzer ALT analyzer.
	 * @param Reporter $reporter Reporter.
	 */
	public function __construct( Database $database, Media $media, Analyzer $analyzer, Reporter $reporter ) {
		$this->database = $database;
		$this->media    = $media;
		$this->analyzer = $analyzer;
		$this->reporter = $reporter;
	}

	/**
	 * Start a new scan: build the post queue and initialize the state.
	 *
	 * @return array<string, mixed> Progress payload.
	 */
	public function start(): array {
		$settings   = Helper::get_settings();
		$post_types = Helper::sanitize_post_types( $settings['post_types'] ?? array() );

		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$limit = Helper::clamp_int( $settings['max_scan_limit'] ?? 500, Helper::LIMIT_MIN, Helper::LIMIT_MAX );
		$queue = $this->collect_post_ids( $post_types, $limit );
		$state = $this->reporter->create_state( $queue, $post_types );

		$this->database->save_state( $state );

		return $this->progress( $state );
	}

	/**
	 * Process the next batch of queued posts.
	 *
	 * @return array<string, mixed> Progress payload.
	 */
	public function process_batch(): array {
		$state = $this->database->get_state();

		if ( empty( $state ) || 'running' !== ( $state['status'] ?? '' ) ) {
			return array(
				'status'          => 'idle',
				'processed_posts' => 0,
				'total_posts'     => 0,
				'percent'         => 0,
				'stats'           => $this->reporter->get_last_report(),
			);
		}

		$batch_size = Helper::clamp_int( Helper::get_setting( 'batch_size', 20 ), Helper::BATCH_MIN, Helper::BATCH_MAX );
		$queue      = isset( $state['queue'] ) && is_array( $state['queue'] ) ? $state['queue'] : array();
		$ids        = array_splice( $queue, 0, $batch_size );

		foreach ( $ids as $post_id ) {
			$this->scan_post( (int) $post_id, $state );
			++$state['processed_posts'];
		}

		$state['queue'] = array_values( $queue );

		$this->media->flush();

		if ( empty( $state['queue'] ) ) {
			$state['status'] = 'complete';
			$report          = $this->reporter->finalize( $state );
			$this->database->delete_state();

			return array(
				'status'          => 'complete',
				'processed_posts' => (int) $state['processed_posts'],
				'total_posts'     => (int) $state['total_posts'],
				'percent'         => 100,
				'stats'           => $report,
			);
		}

		$this->database->save_state( $state );

		return $this->progress( $state );
	}

	/**
	 * Cancel the current scan.
	 *
	 * @return array<string, mixed>
	 */
	public function cancel(): array {
		$this->database->delete_state();

		return array(
			'status'          => 'cancelled',
			'processed_posts' => 0,
			'total_posts'     => 0,
			'percent'         => 0,
		);
	}

	/**
	 * Retrieve the live progress payload for the current state.
	 *
	 * @return array<string, mixed>
	 */
	public function current_progress(): array {
		$state = $this->database->get_state();

		if ( empty( $state ) ) {
			return array(
				'status'          => 'idle',
				'processed_posts' => 0,
				'total_posts'     => 0,
				'percent'         => 0,
				'stats'           => $this->reporter->get_last_report(),
			);
		}

		return $this->progress( $state );
	}

	/**
	 * Collect the IDs of posts to scan with a memory-efficient query.
	 *
	 * @param array<int, string> $post_types Post type slugs.
	 * @param int                $limit      Maximum number of posts.
	 * @return array<int, int>
	 */
	private function collect_post_ids( array $post_types, int $limit ): array {
		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);

		$ids = array_map( 'absint', (array) $query->posts );

		return array_values( array_filter( $ids ) );
	}

	/**
	 * Scan a single post and accumulate its image verdicts into the state.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $state   Scan state (by reference).
	 * @return void
	 */
	private function scan_post( int $post_id, array &$state ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$ignore_svg        = Helper::to_bool( Helper::get_setting( 'ignore_svg', true ) );
		$ignore_decorative = Helper::to_bool( Helper::get_setting( 'ignore_decorative', true ) );

		foreach ( $this->extract_images( (string) $post->post_content ) as $tag ) {
			$src = (string) ( $this->get_attr( $tag, 'src' ) ?? '' );

			if ( '' === $src ) {
				$data_src = $this->get_attr( $tag, 'data-src' );
				$src      = is_string( $data_src ) ? $data_src : '';
			}

			if ( $ignore_svg && '' !== $src && $this->media->is_svg( $src ) ) {
				continue;
			}

			$decorative = $this->is_decorative( $tag );

			if ( $decorative && $ignore_decorative ) {
				continue;
			}

			$alt      = $this->get_attr( $tag, 'alt' );
			$filename = '' !== $src ? $this->media->filename_from_url( $src ) : '';
			$result   = $this->analyzer->analyze( $alt, $filename, $decorative );

			$this->accumulate( $state, $result );
		}
	}

	/**
	 * Fold a single analysis result into the running state.
	 *
	 * @param array<string, mixed> $state  Scan state (by reference).
	 * @param array<string, mixed> $result Analyzer verdict.
	 * @return void
	 */
	private function accumulate( array &$state, array $result ): void {
		++$state['total_images'];

		if ( ! empty( $result['decorative'] ) ) {
			++$state['counts'][ Helper::ISSUE_DECORATIVE ];
			++$state['clean_total'];
			return;
		}

		$issues = isset( $result['issues'] ) && is_array( $result['issues'] ) ? $result['issues'] : array();

		foreach ( $issues as $code ) {
			if ( isset( $state['counts'][ $code ] ) ) {
				++$state['counts'][ $code ];
			}
		}

		$has_issue = ! empty( $issues );

		if ( ! $has_issue ) {
			++$state['clean_total'];
		}

		$normalized = (string) ( $result['normalized'] ?? '' );

		if ( ! empty( $result['has_alt'] ) && '' !== $normalized ) {
			$hash = md5( $normalized );

			if ( ! isset( $state['alt_index'][ $hash ] ) ) {
				$state['alt_index'][ $hash ] = array( 0, 0 );
			}

			++$state['alt_index'][ $hash ][0];

			if ( ! $has_issue ) {
				++$state['alt_index'][ $hash ][1];
			}
		}
	}

	/**
	 * Build a progress payload for a running scan.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	private function progress( array $state ): array {
		$total     = (int) ( $state['total_posts'] ?? 0 );
		$processed = (int) ( $state['processed_posts'] ?? 0 );
		$percent   = $total > 0 ? (int) floor( ( $processed / $total ) * 100 ) : 0;

		return array(
			'status'          => (string) ( $state['status'] ?? 'running' ),
			'processed_posts' => $processed,
			'total_posts'     => $total,
			'percent'         => min( 100, max( 0, $percent ) ),
			'stats'           => $this->reporter->summarize( $state ),
		);
	}

	/**
	 * Extract every <img> tag from a chunk of HTML.
	 *
	 * @param string $content HTML content.
	 * @return array<int, string>
	 */
	private function extract_images( string $content ): array {
		if ( '' === trim( $content ) || false === stripos( $content, '<img' ) ) {
			return array();
		}

		if ( ! preg_match_all( '/<img\b[^>]*>/i', $content, $matches ) ) {
			return array();
		}

		return $matches[0];
	}

	/**
	 * Whether an image tag is explicitly marked as decorative.
	 *
	 * @param string $tag Image tag.
	 * @return bool
	 */
	private function is_decorative( string $tag ): bool {
		$role = strtolower( (string) ( $this->get_attr( $tag, 'role' ) ?? '' ) );
		$aria = strtolower( (string) ( $this->get_attr( $tag, 'aria-hidden' ) ?? '' ) );

		return in_array( $role, array( 'presentation', 'none' ), true ) || 'true' === $aria;
	}

	/**
	 * Read an HTML attribute value from a tag.
	 *
	 * Returns the decoded value, an empty string for valueless attributes, and
	 * null when the attribute is absent.
	 *
	 * @param string $tag  Image tag.
	 * @param string $name Attribute name.
	 * @return string|null
	 */
	private function get_attr( string $tag, string $name ): ?string {
		$pattern = '/(?:^|\s)' . preg_quote( $name, '/' ) . "(?=[\s=\/>]|$)(?:\s*=\s*(\"([^\"]*)\"|'([^']*)'|([^\s\"'>]+)))?/i";

		if ( ! preg_match( $pattern, $tag, $matches ) ) {
			return null;
		}

		$value = $matches[2] ?? ( $matches[3] ?? ( $matches[4] ?? null ) );

		if ( null === $value ) {
			return '';
		}

		return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
