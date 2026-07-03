<?php
/**
 * Settings registration and Settings API integration.
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
 * Registers the plugin settings, sections and fields with the Settings API and
 * handles the reset-to-defaults action.
 */
final class Settings implements ServiceInterface {

	/**
	 * Settings page identifier (also used as the Settings submenu slug).
	 */
	public const PAGE = 'image-alt-checker-settings';

	/**
	 * Settings group name used by the Settings API.
	 */
	public const OPTION_GROUP = 'iac_settings_group';

	/**
	 * Reset action name.
	 */
	public const RESET_ACTION = 'iac_reset_settings';

	/**
	 * General section ID.
	 */
	private const SECTION_GENERAL = 'iac_section_general';

	/**
	 * Performance section ID.
	 */
	private const SECTION_PERFORMANCE = 'iac_section_performance';

	/**
	 * Required capability to manage settings.
	 */
	public const CAPABILITY = 'manage_options';

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
		$this->loader->add_action( 'admin_init', $this, 'register_settings' );
		$this->loader->add_action( 'admin_post_' . self::RESET_ACTION, $this, 'handle_reset' );
	}

	/**
	 * Register the setting, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			IAC_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Helper::class, 'sanitize_settings' ),
				'default'           => Helper::default_settings(),
				'show_in_rest'      => false,
			)
		);

		$this->register_general_section();
		$this->register_performance_section();
	}

	/**
	 * Register the general behaviour section and its fields.
	 *
	 * @return void
	 */
	private function register_general_section(): void {
		add_settings_section(
			self::SECTION_GENERAL,
			__( 'General', 'image-alt-checker' ),
			array( $this, 'render_general_intro' ),
			self::PAGE
		);

		$this->add_field( 'enabled', __( 'Enable scanner', 'image-alt-checker' ), 'render_enabled_field', self::SECTION_GENERAL );
		$this->add_field( 'post_types', __( 'Post types', 'image-alt-checker' ), 'render_post_types_field', self::SECTION_GENERAL );
		$this->add_field( 'ignore_svg', __( 'Ignore SVG', 'image-alt-checker' ), 'render_ignore_svg_field', self::SECTION_GENERAL );
		$this->add_field( 'ignore_decorative', __( 'Ignore decorative images', 'image-alt-checker' ), 'render_ignore_decorative_field', self::SECTION_GENERAL );
		$this->add_field( 'auto_scan', __( 'Automatic scan', 'image-alt-checker' ), 'render_auto_scan_field', self::SECTION_GENERAL );
	}

	/**
	 * Register the performance section and its fields.
	 *
	 * @return void
	 */
	private function register_performance_section(): void {
		add_settings_section(
			self::SECTION_PERFORMANCE,
			__( 'Performance', 'image-alt-checker' ),
			array( $this, 'render_performance_intro' ),
			self::PAGE
		);

		$this->add_field( 'batch_size', __( 'Batch size', 'image-alt-checker' ), 'render_batch_size_field', self::SECTION_PERFORMANCE );
		$this->add_field( 'max_scan_limit', __( 'Maximum scan limit', 'image-alt-checker' ), 'render_max_scan_limit_field', self::SECTION_PERFORMANCE );
		$this->add_field( 'cache_lifetime', __( 'Cache lifetime', 'image-alt-checker' ), 'render_cache_lifetime_field', self::SECTION_PERFORMANCE );
	}

	/**
	 * Register a single settings field.
	 *
	 * @param string $id       Field ID.
	 * @param string $label    Field label.
	 * @param string $callback Render callback method name.
	 * @param string $section  Section ID.
	 * @return void
	 */
	private function add_field( string $id, string $label, string $callback, string $section ): void {
		add_settings_field(
			'iac_field_' . $id,
			$label,
			array( $this, $callback ),
			self::PAGE,
			$section,
			array( 'label_for' => 'iac-field-' . $id )
		);
	}

	/**
	 * General section description.
	 *
	 * @return void
	 */
	public function render_general_intro(): void {
		echo '<p>' . esc_html__( 'Choose what the scanner inspects and how it treats special images.', 'image-alt-checker' ) . '</p>';
	}

	/**
	 * Performance section description.
	 *
	 * @return void
	 */
	public function render_performance_intro(): void {
		echo '<p>' . esc_html__( 'Tune how the scanner processes large sites in batches.', 'image-alt-checker' ) . '</p>';
	}

	/**
	 * Render the enable checkbox.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$this->checkbox( 'enabled', __( 'Allow the scanner to run on this site.', 'image-alt-checker' ) );
	}

	/**
	 * Render the post type checkboxes.
	 *
	 * @return void
	 */
	public function render_post_types_field(): void {
		$selected = Helper::sanitize_post_types( Helper::get_setting( 'post_types', array() ) );
		$types    = Helper::scannable_post_types();

		if ( empty( $types ) ) {
			echo '<p class="description">' . esc_html__( 'No public post types found.', 'image-alt-checker' ) . '</p>';
			return;
		}

		echo '<fieldset>';
		foreach ( $types as $slug ) {
			$object = get_post_type_object( $slug );
			$label  = $object instanceof \WP_Post_Type ? $object->labels->singular_name : $slug;

			printf(
				'<label class="iac-checkbox-row"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( $this->name( 'post_types' ) ),
				esc_attr( $slug ),
				checked( in_array( $slug, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Content of the selected post types will be scanned.', 'image-alt-checker' ) . '</p>';
	}

	/**
	 * Render the ignore SVG checkbox.
	 *
	 * @return void
	 */
	public function render_ignore_svg_field(): void {
		$this->checkbox( 'ignore_svg', __( 'Skip SVG images during scanning.', 'image-alt-checker' ) );
	}

	/**
	 * Render the ignore decorative checkbox.
	 *
	 * @return void
	 */
	public function render_ignore_decorative_field(): void {
		$this->checkbox( 'ignore_decorative', __( 'Skip images marked as decorative (role="presentation" or aria-hidden).', 'image-alt-checker' ) );
	}

	/**
	 * Render the automatic scan checkbox.
	 *
	 * @return void
	 */
	public function render_auto_scan_field(): void {
		$this->checkbox( 'auto_scan', __( 'Start a scan automatically when opening the Scanner page.', 'image-alt-checker' ) );
	}

	/**
	 * Render the batch size field.
	 *
	 * @return void
	 */
	public function render_batch_size_field(): void {
		$this->number( 'batch_size', Helper::BATCH_MIN, Helper::BATCH_MAX, 1, __( 'posts per batch', 'image-alt-checker' ) );
	}

	/**
	 * Render the maximum scan limit field.
	 *
	 * @return void
	 */
	public function render_max_scan_limit_field(): void {
		$this->number( 'max_scan_limit', Helper::LIMIT_MIN, Helper::LIMIT_MAX, 1, __( 'posts', 'image-alt-checker' ) );
	}

	/**
	 * Render the cache lifetime field.
	 *
	 * @return void
	 */
	public function render_cache_lifetime_field(): void {
		$this->number( 'cache_lifetime', Helper::CACHE_MIN, Helper::CACHE_MAX, 60, __( 'seconds', 'image-alt-checker' ) );
	}

	/**
	 * Handle the reset-to-defaults action.
	 *
	 * @return void
	 */
	public function handle_reset(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'image-alt-checker' ) );
		}

		check_admin_referer( self::RESET_ACTION );

		update_option( IAC_OPTION, Helper::default_settings() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE,
					'iac_notice'   => 'reset',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render a checkbox control.
	 *
	 * @param string $key         Setting key.
	 * @param string $description Inline description.
	 * @return void
	 */
	private function checkbox( string $key, string $description ): void {
		$checked = Helper::to_bool( Helper::get_setting( $key ) );

		printf(
			'<label class="iac-checkbox-row"><input type="checkbox" id="iac-field-%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			checked( $checked, true, false ),
			esc_html( $description )
		);
	}

	/**
	 * Render a number control.
	 *
	 * @param string $key  Setting key.
	 * @param int    $min  Minimum value.
	 * @param int    $max  Maximum value.
	 * @param int    $step Step value.
	 * @param string $unit Optional unit suffix.
	 * @return void
	 */
	private function number( string $key, int $min, int $max, int $step, string $unit ): void {
		printf(
			'<input type="number" id="iac-field-%1$s" class="small-text" name="%2$s" value="%3$d" min="%4$d" max="%5$d" step="%6$d">',
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			(int) Helper::get_setting( $key ),
			(int) $min,
			(int) $max,
			(int) $step
		);

		if ( '' !== $unit ) {
			echo ' <span class="iac-unit">' . esc_html( $unit ) . '</span>';
		}
	}

	/**
	 * Build a settings field name within the option array.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function name( string $key ): string {
		return IAC_OPTION . '[' . $key . ']';
	}
}
