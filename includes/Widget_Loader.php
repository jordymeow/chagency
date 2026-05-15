<?php
/**
 * Floating-widget loader.
 *
 * Mounts the chat widget on every admin page (when `admin_enabled` is on
 * and the user can manage options) and/or on every front-end page (when
 * `frontend_enabled` is on, to every visitor).
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the floating widget on admin and/or frontend.
 */
class Widget_Loader {

	public const SCRIPT_KEY = 'app';

	/**
	 * Wires the hooks.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'maybe_enqueue_admin' ) );
		add_action( 'admin_footer',          array( self::class, 'maybe_print_mount_admin' ), 20 );
		add_action( 'wp_enqueue_scripts',    array( self::class, 'maybe_enqueue_frontend' ) );
		add_action( 'wp_footer',             array( self::class, 'maybe_print_mount_frontend' ), 20 );
	}

	/**
	 * True when the admin widget should load on the current admin page.
	 */
	private static function should_render_admin(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return has_credentials();
	}

	/**
	 * True when the front-end widget should load on the current page.
	 * Unlike the admin surface, this is open to anonymous visitors when
	 * the toggle is on.
	 */
	private static function should_render_frontend(): bool {
		$settings = get_plugin_settings();
		if ( empty( $settings['frontend_enabled'] ) ) {
			return false;
		}
		return has_credentials();
	}

	public static function maybe_enqueue_admin(): void {
		if ( ! self::should_render_admin() ) {
			return;
		}
		self::enqueue( 'admin' );
	}

	public static function maybe_print_mount_admin(): void {
		if ( ! self::should_render_admin() ) {
			return;
		}
		self::print_mount();
	}

	public static function maybe_enqueue_frontend(): void {
		if ( ! self::should_render_frontend() ) {
			return;
		}
		self::enqueue( 'frontend' );
	}

	public static function maybe_print_mount_frontend(): void {
		if ( ! self::should_render_frontend() ) {
			return;
		}
		self::print_mount();
	}

	/**
	 * Shared enqueue logic for both surfaces.
	 *
	 * @param 'admin'|'frontend' $surface Which surface we're rendering on.
	 */
	private static function enqueue( string $surface ): void {
		Asset_Loader::enqueue_style( self::SCRIPT_KEY, 'style-index' );
		Asset_Loader::enqueue_script( self::SCRIPT_KEY, 'index' );
		Asset_Loader::set_script_translations( self::SCRIPT_KEY );
		Asset_Loader::localize_script(
			self::SCRIPT_KEY,
			'Config',
			self::widget_config( $surface )
		);
	}

	/**
	 * Prints the widget mount point.
	 */
	private static function print_mount(): void {
		echo '<div id="chagency-widget-root" class="chagency-widget-root"></div>';
	}

	/**
	 * Builds the JS-visible config for the widget.
	 *
	 * @param 'admin'|'frontend' $surface Which surface we're rendering on.
	 * @return array<string,mixed>
	 */
	private static function widget_config( string $surface ): array {
		$settings = get_plugin_settings();
		return array(
			'surface'        => $surface,
			'restUrl'        => esc_url_raw( rest_url( 'chagency/v1' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'hasCredentials' => has_credentials(),
			'settings'       => $settings,
			'storageKey'     => conversation_storage_key(),
		);
	}
}
