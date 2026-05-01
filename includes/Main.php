<?php
/**
 * The main plugin class.
 *
 * Structure mirrors WordPress/ai's `Main` singleton: `setup()` is called once
 * from the constructor; `load()` fires on `plugins_loaded`; feature
 * initialization is deferred to `init` so abilities/connectors/settings are
 * all available.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency;

use Chagency\Abilities\Registrar as Abilities_Registrar;
use Chagency\Admin\Widget_Loader;
use Chagency\REST\Routes;
use Chagency\Settings\Settings_Page;
use Chagency\Settings\Settings_Registration;

defined( 'ABSPATH' ) || exit;

/**
 * Class Main.
 *
 * @internal This class should not be used outside the plugin; there is no
 *           guarantee of backwards compatibility.
 */
final class Main {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Gets the (singleton) instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Registers the bootstrap hooks. Called exactly once from `get_instance()`.
	 */
	private function setup(): void {
		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	/**
	 * Runs on `plugins_loaded`. Verifies requirements, registers wp-admin
	 * action links, hooks feature initialization to `init`.
	 */
	public function load(): void {
		if ( ! ( new Requirements() )->are_requirements_met() ) {
			return;
		}

		require_once CHAGENCY_PLUGIN_DIR . 'includes/helpers.php';

		add_filter( 'plugin_action_links_' . plugin_basename( CHAGENCY_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		add_action( 'init', array( $this, 'initialize' ), 15 );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
	}

	/**
	 * Initializes every feature. Runs on `init` (priority 15) so connectors
	 * and the Abilities API are available.
	 */
	public function initialize(): void {
		try {
			Settings_Registration::init();
			Settings_Page::init();
			Widget_Loader::init();
			Routes::init();
			Abilities_Registrar::init();
		} catch ( \Throwable $e ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: Error message. */
					esc_html__( 'Chagency initialization failed: %s', 'chagency' ),
					esc_html( $e->getMessage() )
				),
				'0.0.1'
			);
		}
	}

	/**
	 * Registers the default ability category.
	 */
	public function register_ability_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			CHAGENCY_ABILITY_CATEGORY,
			array(
				'label'       => __( 'Chagency', 'chagency' ),
				'description' => __( 'Conversational abilities exposed by the Chagency plugin.', 'chagency' ),
			)
		);
	}

	/**
	 * Adds Chagency + Settings links on the Plugins screen.
	 *
	 * @param array<string, string> $links Existing action links.
	 * @return array<string, string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'options-general.php?page=chagency' ) ),
			esc_html__( 'Settings', 'chagency' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Prevent cloning of the singleton.
	 */
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'The Main class should not be cloned.', 'chagency' ),
			'0.0.1'
		);
	}

	/**
	 * Prevent unserializing the singleton.
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'De-serializing instances of Main is not allowed.', 'chagency' ),
			'0.0.1'
		);
	}
}
