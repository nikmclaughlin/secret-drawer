<?php
/**
 * Plugin Name:       Secret Drawer
 * Description:       A hidden drawer in wp-admin, unlocked by a secret word. Silly to find, genuinely useful to have.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Nik
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       secret-drawer
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

define( 'SECRET_DRAWER_VERSION', '1.0.0' );
define( 'SECRET_DRAWER_FILE', __FILE__ );
define( 'SECRET_DRAWER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SECRET_DRAWER_URL', plugin_dir_url( __FILE__ ) );

require_once SECRET_DRAWER_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Secret_Drawer_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Secret_Drawer_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Secret_Drawer_Plugin', 'init' ) );