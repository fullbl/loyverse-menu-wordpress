<?php
/**
 * Plugin Name:       Menu for Loyverse
 * Plugin URI:        https://github.com/fullbl/loyverse-menu-wordpress
 * Description:       Sync your Loyverse POS menu to WordPress as real posts and categories.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            FullBL
 * Author URI:        https://fullbl.it
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       menu-for-loyverse
 * Domain Path:       /languages
 *
 * @package MenuForLoyverse
 */

defined( 'ABSPATH' ) || exit;

define( 'MFL_VERSION', '0.1.0' );
define( 'MFL_PLUGIN_FILE', __FILE__ );
define( 'MFL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MFL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MFL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once MFL_PLUGIN_DIR . 'includes/class-api-client.php';
require_once MFL_PLUGIN_DIR . 'includes/class-cpt.php';
require_once MFL_PLUGIN_DIR . 'includes/class-settings.php';
require_once MFL_PLUGIN_DIR . 'includes/class-sync.php';
require_once MFL_PLUGIN_DIR . 'includes/class-cron.php';
require_once MFL_PLUGIN_DIR . 'includes/class-webhook.php';
require_once MFL_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once MFL_PLUGIN_DIR . 'includes/class-assets.php';
require_once MFL_PLUGIN_DIR . 'includes/class-locks.php';
require_once MFL_PLUGIN_DIR . 'includes/class-templates.php';
require_once MFL_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Bootstrap the plugin.
 */
function mfl_plugin(): MFL_Plugin {
	return MFL_Plugin::instance();
}

register_activation_hook( __FILE__, array( 'MFL_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MFL_Plugin', 'deactivate' ) );

mfl_plugin();
