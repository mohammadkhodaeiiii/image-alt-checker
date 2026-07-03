<?php
/**
 * Plugin Name:       Image Alt Checker
 * Plugin URI:        https://github.com/mohammadkhodaei/image-alt-checker
 * Description:       Scan posts, pages and custom post types to detect missing, empty, duplicate or low-quality image ALT text and improve accessibility and SEO.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.0
 * Author:            Mohammad Khodaei
 * Author URI:        https://github.com/mohammadkhodaei
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       image-alt-checker
 * Domain Path:       /languages
 *
 * @package ImageAltChecker
 */

declare(strict_types=1);

namespace ImageAltChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'IAC_VERSION' ) ) {
	return;
}

/**
 * Plugin version.
 */
define( 'IAC_VERSION', '1.0.0' );

/**
 * Absolute path to the main plugin file.
 */
define( 'IAC_FILE', __FILE__ );

/**
 * Filesystem path to the plugin directory (trailing slash).
 */
define( 'IAC_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory (trailing slash).
 */
define( 'IAC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Option key that stores all plugin settings.
 */
define( 'IAC_OPTION', 'iac_settings' );

require_once IAC_PATH . 'includes/autoload.php';

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function iac_bootstrap(): void {
	Plugin::instance()->run();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\iac_bootstrap' );
