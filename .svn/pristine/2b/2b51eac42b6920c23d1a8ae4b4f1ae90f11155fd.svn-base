<?php
/**
 * Chagency
 *
 * @package     Chagency
 * @author      Jordy Meow
 * @copyright   2026 Jordy Meow
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Chagency
 * Description:       A chatbot today, an agent tomorrow — built natively on the WordPress 7 AI framework, with zero third-party plugin requirements.
 * Version: 0.0.1
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Jordy Meow
 * Author URI:        https://jordymeow.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       chagency
 * Domain Path:       /languages
 */

declare( strict_types=1 );

namespace Chagency;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Defines the plugin constants.
 */
function constants(): void {
	if ( ! defined( 'CHAGENCY_PLUGIN_FILE' ) ) {
		define( 'CHAGENCY_PLUGIN_FILE', __FILE__ );
	}
	if ( ! defined( 'CHAGENCY_VERSION' ) ) {
		define( 'CHAGENCY_VERSION', '0.0.1' );
	}
	if ( ! defined( 'CHAGENCY_PLUGIN_DIR' ) ) {
		define( 'CHAGENCY_PLUGIN_DIR', plugin_dir_path( CHAGENCY_PLUGIN_FILE ) );
	}
	if ( ! defined( 'CHAGENCY_PLUGIN_URL' ) ) {
		define( 'CHAGENCY_PLUGIN_URL', plugin_dir_url( CHAGENCY_PLUGIN_FILE ) );
	}
	if ( ! defined( 'CHAGENCY_ABILITY_CATEGORY' ) ) {
		define( 'CHAGENCY_ABILITY_CATEGORY', 'chagency' );
	}
}
constants();

// Load the autoloader.
require_once CHAGENCY_PLUGIN_DIR . 'includes/autoload.php';

// Boot the plugin.
Main::get_instance();
