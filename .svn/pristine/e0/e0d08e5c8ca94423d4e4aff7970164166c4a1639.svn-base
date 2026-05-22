<?php
/**
 * Floating-widget loader.
 *
 * When the plugin is enabled in settings, prints a `#chagency-widget-root`
 * mount point in the admin footer and enqueues the shared React bundle on
 * every admin page. The bundle auto-detects the mount point and renders the
 * floating chat button + panel.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency\Admin;

use Chagency\Asset_Loader;

use function Chagency\conversation_storage_key;
use function Chagency\get_plugin_settings;
use function Chagency\has_credentials;
use function Chagency\list_connectors_status;
use function Chagency\supported_placeholders;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the floating widget on every admin page.
 */
class Widget_Loader {

	public const SCRIPT_KEY = 'app';

	/**
	 * Wires the hooks.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'maybe_enqueue' ) );
		add_action( 'admin_footer', array( self::class, 'maybe_print_mount' ), 20 );
	}

	/**
	 * Returns true when the widget bundle should be enqueued on the current
	 * screen. We intentionally do NOT gate on the `enabled` setting here —
	 * the bundle always loads so toggling "Enable chatbot" in Settings can
	 * show / hide the launcher live without a page reload. The React widget
	 * reads `settings.enabled` and renders `null` when it's off.
	 */
	private static function should_render(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return has_credentials();
	}

	/**
	 * Enqueues the bundle + seeds its config.
	 */
	public static function maybe_enqueue(): void {
		if ( ! self::should_render() ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );
		Asset_Loader::enqueue_style(
			self::SCRIPT_KEY,
			'style-index',
			array( 'wp-components' )
		);
		Asset_Loader::enqueue_script( self::SCRIPT_KEY, 'index' );
		Asset_Loader::set_script_translations( self::SCRIPT_KEY );

		Asset_Loader::localize_script(
			self::SCRIPT_KEY,
			'Config',
			self::widget_config()
		);
	}

	/**
	 * Prints the widget mount point in the admin footer.
	 */
	public static function maybe_print_mount(): void {
		if ( ! self::should_render() ) {
			return;
		}
		echo '<div id="chagency-widget-root" class="chagency-widget-root"></div>';
	}

	/**
	 * Builds the JS-visible config for the widget.
	 *
	 * @return array<string,mixed>
	 */
	private static function widget_config(): array {
		return array(
			'mode'            => 'widget',
			'restUrl'         => esc_url_raw( rest_url( 'chagency/v1' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'connectorsUrl'   => esc_url_raw( admin_url( 'options-connectors.php' ) ),
			'pageUrl'         => esc_url_raw( admin_url( 'tools.php?page=chagency' ) ),
			'hasCredentials'  => has_credentials(),
			'settings'        => get_plugin_settings(),
			'connectors'      => list_connectors_status(),
			'placeholders'    => supported_placeholders(),
			'storageKey'      => conversation_storage_key(),
		);
	}
}
