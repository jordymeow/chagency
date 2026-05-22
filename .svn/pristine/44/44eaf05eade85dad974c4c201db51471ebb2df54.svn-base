<?php
/**
 * Asset loader utility.
 *
 * Enqueues compiled scripts and styles from `build/`, honouring the
 * `.asset.php` metadata produced by `@wordpress/scripts`. Handles are prefixed
 * with `chagency_` so they never collide with other plugins.
 *
 * Mirrors WordPress/ai's `Asset_Loader`.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers for enqueuing build artifacts.
 */
class Asset_Loader {

	/**
	 * Handle prefix. All enqueued handles become `chagency_<handle>`.
	 */
	private const HANDLE_PREFIX = 'chagency_';

	/**
	 * Relative path (inside the plugin) to the build directory.
	 */
	private const BUILD_SUBDIR = 'build/';

	/**
	 * Enqueues a compiled script. The file is located at
	 * `build/{file_name}.js` with its dependencies declared in
	 * `build/{file_name}.asset.php`.
	 *
	 * @param string                    $handle    Handle (will be prefixed).
	 * @param string                    $file_name Bundle name without extension.
	 * @param array<string,mixed>|null  $override  Optional. Override dependencies/version.
	 * @param array<string,mixed>       $extra     Optional. Passed to `wp_enqueue_script`'s $args.
	 */
	public static function enqueue_script(
		string $handle,
		string $file_name,
		?array $override = null,
		array $extra = array( 'in_footer' => true )
	): void {
		$path = CHAGENCY_PLUGIN_DIR . self::BUILD_SUBDIR . $file_name . '.js';
		$url  = CHAGENCY_PLUGIN_URL . self::BUILD_SUBDIR . $file_name . '.js';
		if ( ! file_exists( $path ) ) {
			return;
		}

		$asset = self::read_asset_meta( $path, $override );

		wp_enqueue_script(
			self::HANDLE_PREFIX . $handle,
			$url,
			$asset['dependencies'],
			$asset['version'],
			$extra
		);
	}

	/**
	 * Enqueues a compiled stylesheet. The file is at `build/{file_name}.css`.
	 *
	 * @param string                   $handle    Handle (will be prefixed).
	 * @param string                   $file_name CSS file name without extension.
	 * @param array<string,string>     $deps      Style dependencies.
	 * @param array<string,mixed>|null $override  Optional. Override version.
	 */
	public static function enqueue_style(
		string $handle,
		string $file_name,
		array $deps = array(),
		?array $override = null
	): void {
		$path = CHAGENCY_PLUGIN_DIR . self::BUILD_SUBDIR . $file_name . '.css';
		$url  = CHAGENCY_PLUGIN_URL . self::BUILD_SUBDIR . $file_name . '.css';
		if ( ! file_exists( $path ) ) {
			return;
		}

		$version = $override['version'] ?? self::read_version_from_asset( $path ) ?? filemtime( $path );

		$prefixed = self::HANDLE_PREFIX . $handle;
		wp_enqueue_style( $prefixed, $url, $deps, (string) $version );
		wp_style_add_data( $prefixed, 'path', $path );

		$rtl_path = substr( $path, 0, -4 ) . '-rtl.css';
		if ( ! file_exists( $rtl_path ) ) {
			return;
		}
		wp_style_add_data( $prefixed, 'rtl', 'replace' );
		if ( is_rtl() ) {
			wp_style_add_data( $prefixed, 'path', $rtl_path );
		}
	}

	/**
	 * Localizes data for a previously enqueued script.
	 *
	 * @param string              $handle      Script handle (without prefix).
	 * @param string              $object_name JS object name (will be prefixed with `chagency`).
	 * @param array<string,mixed> $data        Data to expose.
	 */
	public static function localize_script( string $handle, string $object_name, array $data ): void {
		wp_localize_script( self::HANDLE_PREFIX . $handle, 'chagency' . $object_name, $data );
	}

	/**
	 * Sets script translations for an already-enqueued script.
	 */
	public static function set_script_translations( string $handle, string $domain = 'chagency' ): void {
		wp_set_script_translations( self::HANDLE_PREFIX . $handle, $domain );
	}

	/**
	 * Reads `{path}.asset.php` (version + dependencies). Falls back to
	 * `filemtime()` for versioning when the file isn't present.
	 *
	 * @param string                   $path     Absolute path to the script/style.
	 * @param array<string,mixed>|null $override Optional overrides.
	 * @return array{dependencies:array<string>, version:string}
	 */
	private static function read_asset_meta( string $path, ?array $override ): array {
		$asset_path = substr( $path, 0, -strlen( pathinfo( $path, PATHINFO_EXTENSION ) ) - 1 ) . '.asset.php';

		$defaults = array(
			'dependencies' => array(),
			'version'      => (string) filemtime( $path ),
		);

		if ( file_exists( $asset_path ) ) {
			$loaded = require $asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $loaded ) ) {
				$defaults = array_merge( $defaults, $loaded );
			}
		}
		if ( is_array( $override ) ) {
			$defaults = array_merge( $defaults, $override );
		}

		return $defaults;
	}

	/**
	 * Reads just the `version` value from a sibling asset.php (for styles).
	 */
	private static function read_version_from_asset( string $path ): ?string {
		$asset_path = substr( $path, 0, -strlen( pathinfo( $path, PATHINFO_EXTENSION ) ) - 1 ) . '.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return null;
		}
		$loaded = require $asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		return isset( $loaded['version'] ) ? (string) $loaded['version'] : null;
	}
}
