<?php
/**
 * ALT text analyzer.
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies the quality of a single image's ALT text.
 *
 * The analyzer is stateless and free of WordPress data access: it receives the
 * already-extracted attributes and returns a structured verdict. Cross-image
 * concerns such as duplicate detection are handled by the Scanner/Reporter,
 * since they require the full data set.
 */
class Analyzer {

	/**
	 * Maximum recommended ALT length.
	 *
	 * @var int
	 */
	private int $max_length;

	/**
	 * Minimum meaningful ALT length.
	 *
	 * @var int
	 */
	private int $min_length;

	/**
	 * Constructor.
	 *
	 * @param int|null $max_length Optional maximum length override.
	 * @param int|null $min_length Optional minimum length override.
	 */
	public function __construct( ?int $max_length = null, ?int $min_length = null ) {
		$this->max_length = $max_length ?? Helper::ALT_MAX_LENGTH;
		$this->min_length = $min_length ?? Helper::ALT_MIN_LENGTH;
	}

	/**
	 * Analyze one image.
	 *
	 * @param string|null $alt        Raw ALT attribute value, or null when absent.
	 * @param string      $filename   File name (without extension) for comparison.
	 * @param bool        $decorative Whether the image is explicitly decorative.
	 * @return array{decorative:bool, issues:array<int,string>, has_alt:bool, normalized:string}
	 */
	public function analyze( ?string $alt, string $filename, bool $decorative = false ): array {
		if ( $decorative ) {
			return array(
				'decorative' => true,
				'issues'     => array(),
				'has_alt'    => false,
				'normalized' => '',
			);
		}

		if ( null === $alt ) {
			return array(
				'decorative' => false,
				'issues'     => array( Helper::ISSUE_MISSING ),
				'has_alt'    => false,
				'normalized' => '',
			);
		}

		$trimmed = trim( $alt );

		if ( '' === $alt ) {
			return array(
				'decorative' => false,
				'issues'     => array( Helper::ISSUE_EMPTY ),
				'has_alt'    => false,
				'normalized' => '',
			);
		}

		if ( '' === $trimmed ) {
			return array(
				'decorative' => false,
				'issues'     => array( Helper::ISSUE_WHITESPACE ),
				'has_alt'    => false,
				'normalized' => '',
			);
		}

		$normalized = Helper::normalize_alt( $alt );
		$length     = Helper::length( $trimmed );
		$issues     = array();

		if ( $length > $this->max_length ) {
			$issues[] = Helper::ISSUE_TOO_LONG;
		}

		if ( $length < $this->min_length ) {
			$issues[] = Helper::ISSUE_TOO_SHORT;
		}

		if ( '' !== $filename && $this->matches_filename( $normalized, $filename ) ) {
			$issues[] = Helper::ISSUE_FILENAME;
		}

		if ( $this->is_suspicious( $normalized ) ) {
			$issues[] = Helper::ISSUE_SUSPICIOUS;
		}

		return array(
			'decorative' => false,
			'issues'     => $issues,
			'has_alt'    => true,
			'normalized' => $normalized,
		);
	}

	/**
	 * Whether the ALT text is effectively the image file name.
	 *
	 * @param string $normalized_alt Normalized ALT text.
	 * @param string $filename       File name without extension.
	 * @return bool
	 */
	private function matches_filename( string $normalized_alt, string $filename ): bool {
		$normalized_file = Helper::normalize_alt( (string) preg_replace( '/[-_]+/', ' ', $filename ) );

		if ( '' === $normalized_file ) {
			return false;
		}

		return $normalized_alt === $normalized_file || $normalized_alt === Helper::normalize_alt( $filename );
	}

	/**
	 * Whether the ALT text looks auto-generated or meaningless.
	 *
	 * @param string $normalized_alt Normalized ALT text.
	 * @return bool
	 */
	private function is_suspicious( string $normalized_alt ): bool {
		if ( '' === $normalized_alt ) {
			return false;
		}

		$generic = $this->generic_terms();

		if ( in_array( $normalized_alt, $generic, true ) ) {
			return true;
		}

		$patterns = $this->suspicious_patterns();

		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $normalized_alt ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generic, meaningless ALT terms.
	 *
	 * @return array<int, string>
	 */
	private function generic_terms(): array {
		$terms = array(
			'image',
			'img',
			'photo',
			'picture',
			'pic',
			'untitled',
			'alt',
			'alt text',
			'placeholder',
			'no alt',
			'none',
			'default',
			'thumbnail',
			'thumb',
		);

		/**
		 * Filter the list of generic ALT terms considered suspicious.
		 *
		 * @param array<int, string> $terms Generic terms (lowercase).
		 */
		return (array) apply_filters( 'iac_generic_alt_terms', $terms );
	}

	/**
	 * Regular expressions that match auto-generated file-style ALT text.
	 *
	 * @return array<int, string>
	 */
	private function suspicious_patterns(): array {
		$patterns = array(
			'/^\d+$/',
			'/^(dsc|dscn|dscf|img|imgp|image|photo|pic|picture|screenshot|screen shot|capture|scaled|untitled|p)[-_ ]?\d+$/u',
			'/^[0-9a-f]{16,}$/',
		);

		/**
		 * Filter the suspicious ALT regular expression patterns.
		 *
		 * @param array<int, string> $patterns Regex patterns.
		 */
		return (array) apply_filters( 'iac_suspicious_alt_patterns', $patterns );
	}
}
