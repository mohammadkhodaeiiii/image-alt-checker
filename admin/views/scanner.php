<?php
/**
 * Scanner view.
 *
 * @package ImageAltChecker
 *
 * @var array<string, mixed> $report          Last stored report.
 * @var bool                 $has_active_scan Whether a scan is in progress.
 * @var bool                 $enabled         Whether scanning is enabled.
 * @var array<int, string>   $post_types      Selected post type slugs.
 * @var string               $settings_url    Settings page URL.
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$iac_can_scan = $enabled && ! empty( $post_types );

$iac_stat_rows = array(
	'total_posts'        => __( 'Scanned posts', 'image-alt-checker' ),
	'total_images'       => __( 'Scanned images', 'image-alt-checker' ),
	'passed'             => __( 'Passed images', 'image-alt-checker' ),
	'images_without_alt' => __( 'Without ALT', 'image-alt-checker' ),
	'empty'              => __( 'Empty ALT', 'image-alt-checker' ),
	'duplicate'          => __( 'Duplicate ALT', 'image-alt-checker' ),
	'too_long'           => __( 'ALT too long', 'image-alt-checker' ),
	'too_short'          => __( 'ALT too short', 'image-alt-checker' ),
	'filename'           => __( 'ALT equals file name', 'image-alt-checker' ),
	'suspicious'         => __( 'Suspicious ALT', 'image-alt-checker' ),
	'decorative'         => __( 'Decorative images', 'image-alt-checker' ),
	'failed'             => __( 'Images needing attention', 'image-alt-checker' ),
);
?>
<div class="wrap iac-wrap">
	<h1 class="iac-title">
		<span class="dashicons dashicons-search" aria-hidden="true"></span>
		<?php echo esc_html__( 'Scanner', 'image-alt-checker' ); ?>
	</h1>
	<p class="iac-subtitle"><?php echo esc_html__( 'Scan your content in batches and watch the results update live.', 'image-alt-checker' ); ?></p>

	<?php if ( ! $enabled ) : ?>
		<div class="notice notice-warning iac-inline-notice">
			<p>
				<?php echo esc_html__( 'The scanner is currently disabled.', 'image-alt-checker' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'Enable it in Settings', 'image-alt-checker' ); ?></a>
			</p>
		</div>
	<?php elseif ( empty( $post_types ) ) : ?>
		<div class="notice notice-warning iac-inline-notice">
			<p>
				<?php echo esc_html__( 'No post types are selected for scanning.', 'image-alt-checker' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'Choose post types in Settings', 'image-alt-checker' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<div class="iac-card iac-scanner" data-iac-scanner data-iac-can-scan="<?php echo $iac_can_scan ? '1' : '0'; ?>" data-iac-active="<?php echo $has_active_scan ? '1' : '0'; ?>">
		<div class="iac-scanner-controls">
			<button type="button" class="button button-primary button-hero" data-iac-start <?php disabled( ! $iac_can_scan ); ?>>
				<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
				<?php echo esc_html__( 'Start scan', 'image-alt-checker' ); ?>
			</button>
			<button type="button" class="button button-hero" data-iac-cancel hidden>
				<span class="dashicons dashicons-no" aria-hidden="true"></span>
				<?php echo esc_html__( 'Cancel', 'image-alt-checker' ); ?>
			</button>
			<button type="button" class="button" data-iac-clear>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php echo esc_html__( 'Clear cache', 'image-alt-checker' ); ?>
			</button>
		</div>

		<div class="iac-progress-wrap">
			<div class="iac-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-iac-progressbar>
				<span class="iac-progress-fill" data-iac-progress-fill style="width:0%;"></span>
			</div>
			<div class="iac-progress-meta">
				<span class="iac-status" data-iac-status aria-live="polite"><?php echo esc_html__( 'Idle', 'image-alt-checker' ); ?></span>
				<span class="iac-progress-text" data-iac-progress-text>0%</span>
			</div>
		</div>
	</div>

	<h2 class="iac-section-title"><?php echo esc_html__( 'Live results', 'image-alt-checker' ); ?></h2>
	<div class="iac-stats" data-iac-stats>
		<?php foreach ( $iac_stat_rows as $iac_key => $iac_label ) : ?>
			<div class="iac-stat">
				<span class="iac-stat-value" data-iac-stat="<?php echo esc_attr( $iac_key ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $report[ $iac_key ] ?? 0 ) ) ); ?></span>
				<span class="iac-stat-label"><?php echo esc_html( $iac_label ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</div>
