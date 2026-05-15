<?php
/**
 * Settings registration.
 *
 * Owns the `register_setting()` call for `chagency_settings`. Kept separate from
 * `Settings_Page` so the option exists (with a proper JSON schema + sanitize
 * callback) regardless of which admin page the user is viewing, mirroring
 * WordPress/ai's split between `Settings_Registration` and `Settings_Page`.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency\Settings;

use function Chagency\default_settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings_Registration.
 */
class Settings_Registration {

	public const OPTION_GROUP = 'chagency';
	public const OPTION_KEY   = 'chagency_settings';

	/**
	 * Hooks into `admin_init` to register the option.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'rest_api_init', array( self::class, 'register' ) );
	}

	/**
	 * Registers the option with a full JSON schema. Enabling `show_in_rest`
	 * lets `/wp/v2/settings` read it natively, handy for MCP clients even
	 * though our React UI uses the custom `/chagency/v1/settings` endpoint.
	 */
	public static function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'object',
				'description'       => __( 'Chagency settings.', 'chagency' ),
				'default'           => default_settings(),
				'sanitize_callback' => '\\Chagency\\sanitize_settings',
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'admin_enabled'      => array( 'type' => 'boolean' ),
							'frontend_enabled'   => array( 'type' => 'boolean' ),
							'chat_title'         => array( 'type' => 'string' ),
							'system_instruction' => array( 'type' => 'string' ),
							'greeting'           => array( 'type' => 'string' ),
							'model_preference'   => array( 'type' => 'string' ),
						),
					),
				),
			)
		);
	}
}
