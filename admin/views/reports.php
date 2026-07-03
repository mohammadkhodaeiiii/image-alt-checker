<?php
/**
 * Reports view.
 *
 * @package ImageAltChecker
 *
 * @var array<string, mixed> $report      Last stored report.
 * @var string               $scanner_url Scanner page URL.
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$iac_has_report   = ! empty( $report ) && isset( $report['total_images'] );
$iac_total_images = (int) ( $report['total_images'] ?? 0 );
?>
<div class="wrap iac-wrap">
	<h1 class="iac-title">
		<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
		<?php echo esc_html__( 'Reports', 'image-alt-checker' ); ?>
	</h1>

	<?php if ( ! $iac_has_report ) : ?>
		<div class="iac-card iac-empty-state">
			<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
			<h2><?php echo esc_html__( 'No report available', 'image-alt-checker' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to generate a detailed report.', 'image-alt-checker' ); ?></p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( $scanner_url ); ?>">
				<?php echo esc_html__( 'Go to Scanner', 'image-alt-checker' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="iac-summary-line">
			<?php
			$iac_completed = (int) ( $report['completed_at'] ?? 0 );
			if ( $iac_completed > 0 ) {
				printf(
					/* translators: %s: formatted date/time. */
					esc_html__( 'Last scan completed on %s.', 'image-alt-checker' ),
					'<strong>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $iac_completed ) ) . '</strong>'
				);
			}
			?>
		</div>

		<table class="widefat striped iac-report-table">
			<caption class="screen-reader-text"><?php echo esc_html__( 'Scan results summary', 'image-alt-checker' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Metric', 'image-alt-checker' ); ?></th>
					<th scope="col" class="iac-num"><?php echo esc_html__( 'Count', 'image-alt-checker' ); ?></th>
					<th scope="col" class="iac-num"><?php echo esc_html__( 'Share of images', 'image-alt-checker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$iac_rows = array(
					array( __( 'Scanned posts', 'image-alt-checker' ), (int) ( $report['total_posts'] ?? 0 ), false ),
					array( __( 'Scanned images', 'image-alt-checker' ), $iac_total_images, false ),
					array( __( 'Passed images', 'image-alt-checker' ), (int) ( $report['passed'] ?? 0 ), true ),
					array( __( 'Images needing attention', 'image-alt-checker' ), (int) ( $report['failed'] ?? 0 ), true ),
					array( __( 'Missing ALT', 'image-alt-checker' ), (int) ( $report['missing'] ?? 0 ), true ),
					array( __( 'Empty ALT', 'image-alt-checker' ), (int) ( $report['empty'] ?? 0 ), true ),
					array( __( 'Whitespace-only ALT', 'image-alt-checker' ), (int) ( $report['whitespace'] ?? 0 ), true ),
					array( __( 'Without ALT (total)', 'image-alt-checker' ), (int) ( $report['images_without_alt'] ?? 0 ), true ),
					array( __( 'Duplicate ALT', 'image-alt-checker' ), (int) ( $report['duplicate'] ?? 0 ), true ),
					array( __( 'ALT equals file name', 'image-alt-checker' ), (int) ( $report['filename'] ?? 0 ), true ),
					array( __( 'ALT too long', 'image-alt-checker' ), (int) ( $report['too_long'] ?? 0 ), true ),
					array( __( 'ALT too short', 'image-alt-checker' ), (int) ( $report['too_short'] ?? 0 ), true ),
					array( __( 'Suspicious ALT', 'image-alt-checker' ), (int) ( $report['suspicious'] ?? 0 ), true ),
					array( __( 'Decorative images', 'image-alt-checker' ), (int) ( $report['decorative'] ?? 0 ), true ),
				);

				foreach ( $iac_rows as $iac_row ) :
					list( $iac_label, $iac_value, $iac_show_share ) = $iac_row;
					$iac_share = ( $iac_show_share && $iac_total_images > 0 ) ? round( ( $iac_value / $iac_total_images ) * 100, 1 ) : null;
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $iac_label ); ?></th>
						<td class="iac-num"><?php echo esc_html( number_format_i18n( $iac_value ) ); ?></td>
						<td class="iac-num">
							<?php echo null === $iac_share ? '&mdash;' : esc_html( number_format_i18n( $iac_share, 1 ) . '%' ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Scan duration', 'image-alt-checker' ); ?></th>
					<td class="iac-num" colspan="2">
						<?php
						/* translators: %s: number of seconds. */
						echo esc_html( sprintf( __( '%s seconds', 'image-alt-checker' ), number_format_i18n( (float) ( $report['duration'] ?? 0 ), 2 ) ) );
						?>
					</td>
				</tr>
			</tfoot>
		</table>

		<p class="iac-actions">
			<a class="button button-primary" href="<?php echo esc_url( $scanner_url ); ?>"><?php echo esc_html__( 'Run new scan', 'image-alt-checker' ); ?></a>
		</p>
	<?php endif; ?>
</div>
