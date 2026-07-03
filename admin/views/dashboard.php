<?php
/**
 * Dashboard view.
 *
 * @package ImageAltChecker
 *
 * @var array<string, mixed> $report          Last stored report.
 * @var bool                 $has_active_scan Whether a scan is in progress.
 * @var string               $scanner_url     Scanner page URL.
 * @var string               $reports_url     Reports page URL.
 * @var string               $settings_url    Settings page URL.
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$iac_has_report   = ! empty( $report ) && isset( $report['total_images'] );
$iac_total_images = (int) ( $report['total_images'] ?? 0 );
$iac_passed       = (int) ( $report['passed'] ?? 0 );
$iac_score        = $iac_total_images > 0 ? (int) round( ( $iac_passed / $iac_total_images ) * 100 ) : 0;
?>
<div class="wrap iac-wrap">
	<h1 class="iac-title">
		<span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
		<?php echo esc_html__( 'Image Alt Checker', 'image-alt-checker' ); ?>
	</h1>
	<p class="iac-subtitle"><?php echo esc_html__( 'Find missing, empty, duplicate or low-quality image ALT text across your content.', 'image-alt-checker' ); ?></p>

	<?php if ( $has_active_scan ) : ?>
		<div class="notice notice-info iac-inline-notice">
			<p>
				<?php echo esc_html__( 'A scan is currently in progress.', 'image-alt-checker' ); ?>
				<a href="<?php echo esc_url( $scanner_url ); ?>"><?php echo esc_html__( 'Open the Scanner', 'image-alt-checker' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $iac_has_report ) : ?>
		<div class="iac-card iac-empty-state">
			<span class="dashicons dashicons-search" aria-hidden="true"></span>
			<h2><?php echo esc_html__( 'No scan has been run yet', 'image-alt-checker' ); ?></h2>
			<p><?php echo esc_html__( 'Run your first scan to see accessibility and SEO statistics for your images.', 'image-alt-checker' ); ?></p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( $scanner_url ); ?>">
				<?php echo esc_html__( 'Start scanning', 'image-alt-checker' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="iac-score-row">
			<div class="iac-score" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: percentage. */ __( 'Health score: %d percent', 'image-alt-checker' ), $iac_score ) ); ?>" style="--iac-score: <?php echo (int) $iac_score; ?>;">
				<span class="iac-score-value"><?php echo esc_html( number_format_i18n( $iac_score ) ); ?>%</span>
				<span class="iac-score-label"><?php echo esc_html__( 'Healthy images', 'image-alt-checker' ); ?></span>
			</div>
			<div class="iac-score-meta">
				<?php
				$iac_completed = (int) ( $report['completed_at'] ?? 0 );
				if ( $iac_completed > 0 ) :
					?>
					<p>
						<strong><?php echo esc_html__( 'Last scan:', 'image-alt-checker' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $iac_completed ) ); ?>
					</p>
				<?php endif; ?>
				<p>
					<strong><?php echo esc_html__( 'Duration:', 'image-alt-checker' ); ?></strong>
					<?php
					/* translators: %s: number of seconds. */
					echo esc_html( sprintf( __( '%s seconds', 'image-alt-checker' ), number_format_i18n( (float) ( $report['duration'] ?? 0 ), 2 ) ) );
					?>
				</p>
				<p class="iac-actions">
					<a class="button button-primary" href="<?php echo esc_url( $scanner_url ); ?>"><?php echo esc_html__( 'Run new scan', 'image-alt-checker' ); ?></a>
					<a class="button" href="<?php echo esc_url( $reports_url ); ?>"><?php echo esc_html__( 'View full report', 'image-alt-checker' ); ?></a>
					<a class="button" href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'Settings', 'image-alt-checker' ); ?></a>
				</p>
			</div>
		</div>

		<div class="iac-cards">
			<?php
			$iac_cards = array(
				array(
					'label' => __( 'Scanned posts', 'image-alt-checker' ),
					'value' => (int) ( $report['total_posts'] ?? 0 ),
					'icon'  => 'dashicons-admin-post',
					'tone'  => 'neutral',
				),
				array(
					'label' => __( 'Scanned images', 'image-alt-checker' ),
					'value' => $iac_total_images,
					'icon'  => 'dashicons-format-image',
					'tone'  => 'neutral',
				),
				array(
					'label' => __( 'Passed images', 'image-alt-checker' ),
					'value' => $iac_passed,
					'icon'  => 'dashicons-yes-alt',
					'tone'  => 'good',
				),
				array(
					'label' => __( 'Without ALT', 'image-alt-checker' ),
					'value' => (int) ( $report['images_without_alt'] ?? 0 ),
					'icon'  => 'dashicons-warning',
					'tone'  => 'bad',
				),
				array(
					'label' => __( 'Empty ALT', 'image-alt-checker' ),
					'value' => (int) ( $report['empty'] ?? 0 ),
					'icon'  => 'dashicons-editor-removeformatting',
					'tone'  => 'warn',
				),
				array(
					'label' => __( 'Duplicate ALT', 'image-alt-checker' ),
					'value' => (int) ( $report['duplicate'] ?? 0 ),
					'icon'  => 'dashicons-admin-page',
					'tone'  => 'warn',
				),
			);

			foreach ( $iac_cards as $iac_card ) :
				?>
				<div class="iac-card iac-card--<?php echo esc_attr( $iac_card['tone'] ); ?>">
					<span class="dashicons <?php echo esc_attr( $iac_card['icon'] ); ?>" aria-hidden="true"></span>
					<span class="iac-card-value"><?php echo esc_html( number_format_i18n( $iac_card['value'] ) ); ?></span>
					<span class="iac-card-label"><?php echo esc_html( $iac_card['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
