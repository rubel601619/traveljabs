<?php
/**
 * Plugin Name: Traveljabs
 * Plugin URI: https://github.com/rubel601619/traveljabs
 * Description: A comprehensive WordPress management plugin for custom post types, custom post type settings, redirect management, and other site administration features.
 * Version: 1.0.0
 * Author: Yuma Technology
 * Author URI: https://yuma-technology.co.uk/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: traveljabs
 * Domain Path: /languages
 *
 * @package Traveljabs
 */

defined( 'ABSPATH' ) || exit;

define( 'TRAVELJABS_VERSION', '1.0.0' );
define( 'TRAVELJABS_FILE', __FILE__ );

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( \Traveljabs\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Traveljabs\Core\Activator::class, 'deactivate' ) );

\Traveljabs\Core\Plugin::instance()->run();
