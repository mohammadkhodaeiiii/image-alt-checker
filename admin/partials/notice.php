<?php
/**
 * Admin notice partial.
 *
 * @package ImageAltChecker
 *
 * @var string $type    Notice type (success|error|warning|info).
 * @var string $message Message text.
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$iac_allowed_types = array( 'success', 'error', 'warning', 'info' );
$iac_type          = in_array( $type ?? '', $iac_allowed_types, true ) ? $type : 'info';
$iac_message       = isset( $message ) ? (string) $message : '';

if ( '' === $iac_message ) {
	return;
}
?>
<div class="notice notice-<?php echo esc_attr( $iac_type ); ?> is-dismissible">
	<p><?php echo esc_html( $iac_message ); ?></p>
</div>
<?php
