<?php
/**
 * Settings → Chagency admin page.
 *
 * This page is rendered by the `@wordpress/boot` script-module system , 
 * the same framework Settings → Connectors uses. See
 * `docs/WP7-BOOT-ARCHITECTURE.md` for the full writeup.
 *
 * Flow:
 *   1. Register our React "stage" as a script module: `chagency/settings/content`.
 *   2. Register a classic "prerequisites" script whose only job is to
 *      carry the `wp-*` dependencies (so `window.wp.element`, etc. are
 *      populated) and to hold an inline `initSinglePage` call.
 *   3. On render, enqueue both + print the `boot-layout-container` mount.
 *      Boot builds the DOM hierarchy (surfaces + stage + admin-ui-page),
 *      injects WPDS CSS, and renders our `stage` React component.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency\Settings;

use function Chagency\conversation_storage_key;
use function Chagency\get_plugin_settings;
use function Chagency\has_credentials;
use function Chagency\list_connectors_status;
use function Chagency\supported_placeholders;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Chagency.
 */
class Settings_Page {

	public const PAGE_SLUG       = 'chagency';
	public const MODULE_ID       = 'chagency/settings/content';
	public const PREREQ_HANDLE   = 'chagency-settings-prerequisites';
	public const MOUNT_ID        = 'chagency-settings-root';

	/**
	 * Wires the hooks.
	 */
	public static function init(): void {
		// Priority 20: runs after other plugins, so our Connectors-index
		// lookup is stable.
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'maybe_register_assets' ) );
	}

	/**
	 * Registers the menu entry directly below Connectors.
	 */
	public static function register_menu(): void {
		global $submenu;
		$items    = array_values( $submenu['options-general.php'] ?? array() );
		$position = 1;
		foreach ( $items as $index => $entry ) {
			if ( is_array( $entry ) && ( $entry[2] ?? '' ) === 'options-connectors.php' ) {
				$position = $index + 1;
				break;
			}
		}
		add_options_page(
			__( 'Chagency', 'chagency' ),
			__( 'Chagency', 'chagency' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render' ),
			$position
		);
	}

	/**
	 * Registers + enqueues the script module and its classic
	 * prerequisites on our settings screen.
	 *
	 * @param string $hook Current admin-screen hook.
	 */
	public static function maybe_register_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		$build_dir = \CHAGENCY_PLUGIN_DIR . 'build/settings/';
		$build_url = \CHAGENCY_PLUGIN_URL . 'build/settings/';
		$module_js = 'index.js';
		if ( ! file_exists( $build_dir . $module_js ) ) {
			return;
		}

		// -- The script module that exports `stage`. Registered but NOT
		//    enqueued: enqueued modules are loaded directly as
		//    `<script type="module" src>` and skipped from the importmap.
		//    We want ours IN the importmap so `@wordpress/boot`'s
		//    `import(content_module)` call resolves. We wire it up below
		//    as a `module_dependencies` entry on the prereq classic script.
		wp_register_script_module(
			self::MODULE_ID,
			$build_url . $module_js,
			array(
				array( 'id' => '@wordpress/boot', 'import' => 'static' ),
			),
			\CHAGENCY_VERSION
		);

		// -- Prerequisites classic script. --
		// All `window.wp.*` globals our module reads from must be listed
		// here so wp-element, wp-components, etc. are loaded before the
		// boot module imports us dynamically. The script itself is an
		// empty placeholder whose only job is to hold the inline
		// `initSinglePage` call and carry the `module_dependencies` that
		// populate the importmap.
		// Mirror `@wordpress/boot`'s own classic dependencies (see
		// /wp-includes/js/dist/script-modules/boot/index.min.asset.php).
		// Boot reads `window.wp.data`, `window.wp.notices`, etc., at
		// runtime, so every `wp-*` script it expects needs to be loaded
		// before our inline `initSinglePage` call runs.
		wp_register_script(
			self::PREREQ_HANDLE,
			'',
			array(
				'react', 'react-dom', 'react-jsx-runtime',
				'wp-commands', 'wp-components', 'wp-compose',
				'wp-core-data', 'wp-data', 'wp-editor',
				'wp-element', 'wp-html-entities', 'wp-i18n',
				'wp-keyboard-shortcuts', 'wp-keycodes', 'wp-notices',
				'wp-primitives', 'wp-private-apis', 'wp-theme',
				'wp-url', 'wp-api-fetch',
			),
			\CHAGENCY_VERSION,
			true
		);

		// Pull our module + @wordpress/boot into the importmap by declaring
		// them as module dependencies of the prereq classic script.
		// Static imports pre-load + cache the modules. Boot's subsequent
		// dynamic `import(content_module)` returns the cached module
		// without re-resolving the specifier, which avoids a
		// "failed to resolve" race we hit when using `dynamic` here.
		wp_scripts()->add_data(
			self::PREREQ_HANDLE,
			'module_dependencies',
			array(
				array( 'id' => '@wordpress/boot', 'import' => 'static' ),
				array( 'id' => self::MODULE_ID,   'import' => 'static' ),
			)
		);

		// Expose the page config on a well-known global that our stage
		// reads on mount.
		wp_add_inline_script(
			self::PREREQ_HANDLE,
			'window.chagencySettingsConfig = ' . wp_json_encode( self::stage_config() ) . ';',
			'before'
		);

		// Kick off boot. Dynamic import resolves via the importmap WP
		// emits from `wp_register_script_module` registrations.
		$routes = array(
			array(
				'path'           => '/',
				'content_module' => self::MODULE_ID,
			),
		);
		wp_add_inline_script(
			self::PREREQ_HANDLE,
			sprintf(
				'import("@wordpress/boot").then((mod) => mod.initSinglePage({ mountId: %s, routes: %s }));',
				wp_json_encode( self::MOUNT_ID ),
				wp_json_encode( $routes )
			)
		);

		// Admin-chrome reset that lets boot's dark canvas reach edge-to-edge.
		// Same shape as the reset `options-connectors.php` uses; piped through
		// `wp_add_inline_style` to satisfy Plugin Check (no raw `<style>` tags).
		$reset_css = '#wpwrap{background:var(--wpds-color-fg-content-neutral,#1e1e1e);overflow-y:auto;}'
			. 'body.wp-admin{background:#fff;}'
			. '#wpcontent{padding-inline-start:0;}'
			. '#wpbody-content{padding-bottom:0;}'
			. '#wpbody-content > div:not(.boot-layout-container):not(#screen-meta):not(#screen-meta-links){display:none;}'
			. '#wpfooter{display:none;}'
			. '.a11y-speak-region{inset-inline-start:-1px;top:-1px;}'
			. '@media (min-width:782px){#wpwrap{overflow-y:initial;}}';
		wp_add_inline_style( 'wp-components', $reset_css );

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_script( self::PREREQ_HANDLE );
		// No wp_enqueue_script_module() for our content module on purpose.
	}

	/**
	 * Config the stage's React component reads from
	 * `window.chagencySettingsConfig`.
	 *
	 * @return array<string,mixed>
	 */
	private static function stage_config(): array {
		return array(
			'restUrl'        => esc_url_raw( rest_url( 'chagency/v1' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'connectorsUrl'  => esc_url_raw( admin_url( 'options-connectors.php' ) ),
			'settingsUrl'    => esc_url_raw( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			'hasCredentials' => has_credentials(),
			'settings'       => get_plugin_settings(),
			'connectors'     => list_connectors_status(),
			'placeholders'   => supported_placeholders(),
			'storageKey'     => conversation_storage_key(),
		);
	}

	/**
	 * Renders the mount point. Boot + `initSinglePage` do the rest:
	 * they build `.boot-layout-container` → `.boot-layout` (dark canvas)
	 * → `.boot-layout__surfaces` (with 8-px margin) → `.boot-layout__stage`
	 * (white rounded card) and render our React `stage` inside.
	 *
	 * The admin-chrome reset is enqueued via `wp_add_inline_style` in
	 * `maybe_register_assets`.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<h1 class="screen-reader-text"><?php echo esc_html__( 'Chagency', 'chagency' ); ?></h1>
		<div id="<?php echo esc_attr( self::MOUNT_ID ); ?>" class="boot-layout-container">
			<noscript>
				<div class="notice notice-warning">
					<p><?php echo esc_html__( 'JavaScript is required for the Chagency settings interface.', 'chagency' ); ?></p>
				</div>
			</noscript>
		</div>
		<?php
	}
}
