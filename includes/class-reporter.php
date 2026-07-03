<?php
/**
 * Report builder.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the shape of the scan state, accumulates per-image verdicts and turns the
 * raw accumulators into human-facing statistics.
 */
class Reporter {

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Constructor.
	 *
	 * @param Database $database Storage abstraction.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Create a fresh scan-state accumulator.
	 *
	 * @param array<int, int>    $queue      Post IDs queued for scanning.
	 * @param array<int, string> $post_types Post type slugs being scanned.
	 * @return array<string, mixed>
	 */
	public function create_state( array $queue, array $post_types ): array {
		return array(
			'status'          => 'running',
			'queue'           => array_values( array_map( 'absint', $queue ) ),
			'total_posts'     => count( $queue ),
			'processed_posts' => 0,
			'total_images'    => 0,
			'clean_total'     => 0,
			'counts'          => array(
				Helper::ISSUE_MISSING    => 0,
				Helper::ISSUE_EMPTY      => 0,
				Helper::ISSUE_WHITESPACE => 0,
				Helper::ISSUE_TOO_LONG   => 0,
				Helper::ISSUE_TOO_SHORT  => 0,
				Helper::ISSUE_FILENAME   => 0,
				Helper::ISSUE_SUSPICIOUS => 0,
				Helper::ISSUE_DECORATIVE => 0,
			),
			'alt_index'       => array(),
			'started_at'      => microtime( true ),
			'post_types'      => array_values( $post_types ),
		);
	}

	/**
	 * Build the derived statistics from a state accumulator.
	 *
	 * Works for both an in-progress and a finished scan.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	public function summarize( array $state ): array {
		$counts          = isset( $state['counts'] ) && is_array( $state['counts'] ) ? $state['counts'] : array();
		$alt_index       = isset( $state['alt_index'] ) && is_array( $state['alt_index'] ) ? $state['alt_index'] : array();
		$total_images    = (int) ( $state['total_images'] ?? 0 );
		$clean_total     = (int) ( $state['clean_total'] ?? 0 );
		$processed_posts = (int) ( $state['processed_posts'] ?? 0 );
		$total_posts     = (int) ( $state['total_posts'] ?? 0 );

		$duplicates    = 0;
		$clean_but_dup = 0;

		foreach ( $alt_index as $entry ) {
			$entry_total = (int) ( $entry[0] ?? 0 );
			$entry_clean = (int) ( $entry[1] ?? 0 );

			if ( $entry_total > 1 ) {
				$duplicates    += $entry_total;
				$clean_but_dup += $entry_clean;
			}
		}

		$passed = max( 0, $clean_total - $clean_but_dup );
		$failed = max( 0, $total_images - $passed );

		$missing    = (int) ( $counts[ Helper::ISSUE_MISSING ] ?? 0 );
		$empty      = (int) ( $counts[ Helper::ISSUE_EMPTY ] ?? 0 );
		$whitespace = (int) ( $counts[ Helper::ISSUE_WHITESPACE ] ?? 0 );

		$full_counts                            = $counts;
		$full_counts[ Helper::ISSUE_DUPLICATE ] = $duplicates;

		$started  = (float) ( $state['started_at'] ?? microtime( true ) );
		$duration = isset( $state['duration'] ) ? (float) $state['duration'] : max( 0.0, microtime( true ) - $started );

		return array(
			'total_posts'        => $processed_posts,
			'queued_posts'       => $total_posts,
			'total_images'       => $total_images,
			'images_without_alt' => $missing + $empty + $whitespace,
			'missing'            => $missing,
			'empty'              => $empty,
			'whitespace'         => $whitespace,
			'duplicate'          => $duplicates,
			'too_long'           => (int) ( $counts[ Helper::ISSUE_TOO_LONG ] ?? 0 ),
			'too_short'          => (int) ( $counts[ Helper::ISSUE_TOO_SHORT ] ?? 0 ),
			'filename'           => (int) ( $counts[ Helper::ISSUE_FILENAME ] ?? 0 ),
			'suspicious'         => (int) ( $counts[ Helper::ISSUE_SUSPICIOUS ] ?? 0 ),
			'decorative'         => (int) ( $counts[ Helper::ISSUE_DECORATIVE ] ?? 0 ),
			'passed'             => $passed,
			'failed'             => $failed,
			'duration'           => round( $duration, 2 ),
			'counts'             => $full_counts,
		);
	}

	/**
	 * Finalize a completed scan into a stored report.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	public function finalize( array $state ): array {
		$started            = (float) ( $state['started_at'] ?? microtime( true ) );
		$state['duration']  = max( 0.0, microtime( true ) - $started );
		$report             = $this->summarize( $state );
		$report['post_types']   = array_values( (array) ( $state['post_types'] ?? array() ) );
		$report['completed_at'] = time();

		$this->database->save_report( $report );

		return $report;
	}

	/**
	 * Retrieve the last stored report.
	 *
	 * @return array<string, mixed>
	 */
	public function get_last_report(): array {
		return $this->database->get_report();
	}
}
