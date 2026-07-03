<?php
/**
 * Settings view.
 *
 * @package ImageAltChecker
 *
 * @var string $reset_url Nonce-protected reset URL.
 * @var string $notice    Notice key from the query string.
 * @var Admin  $admin     Admin service (for rendering partials).
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap iac-wrap iac-settings">
	<h1 class="iac-title">
		<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
		<?php echo esc_html__( 'Settings', 'image-alt-checker' ); ?>
	</h1>

	<?php
	if ( 'reset' === ( $notice ?? '' ) && isset( $admin ) && $admin instanceof Admin ) {
		$admin->render_notice( 'success', __( 'Settings have been reset to their defaults.', 'image-alt-checker' ) );
	}
	?>

	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
		<?php
		settings_fields( Settings::OPTION_GROUP );
		do_settings_sections( Settings::PAGE );
		submit_button();
		?>
	</form>

	<hr>

	<h2><?php echo esc_html__( 'Reset', 'image-alt-checker' ); ?></h2>
	<p class="description"><?php echo esc_html__( 'Restore every setting to its default value. This cannot be undone.', 'image-alt-checker' ); ?></p>
	<p>
		<a
			href="<?php echo esc_url( $reset_url ); ?>"
			class="button button-secondary iac-reset"
			data-iac-confirm="<?php echo esc_attr__( 'Reset all settings to defaults?', 'image-alt-checker' ); ?>"
		>
			<?php echo esc_html__( 'Reset settings', 'image-alt-checker' ); ?>
		</a>
	</p>
</div>
