<?php
/**
 * REST endpoints for the Chagency plugin.
 *
 * Routes:
 *   POST /chagency/v1/chat       , generate a reply for a conversation
 *   POST /chagency/v1/test       , canary probe against a specific provider
 *   GET  /chagency/v1/connectors , connector status (used by the React UI)
 *   GET  /chagency/v1/settings   , current settings
 *   POST /chagency/v1/settings   , persist new settings
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency\REST;

use Chagency\Services\AI_Service;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function Chagency\admin_permission_check;
use function Chagency\chat_permission_check;
use function Chagency\expand_placeholders;
use function Chagency\get_plugin_settings;
use function Chagency\list_connectors_status;
use function Chagency\sanitize_settings;

defined( 'ABSPATH' ) || exit;

/**
 * REST route registration + handlers.
 */
class Routes {

	public const NAMESPACE = 'chagency/v1';

	/**
	 * Wires the hook.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers every route.
	 */
	public static function register_routes(): void {
		$admin = '\\Chagency\\admin_permission_check';

		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'handle_chat' ),
					'permission_callback' => '\\Chagency\\chat_permission_check',
					'args'                => array(
						'messages' => array(
							'required'    => true,
							'type'        => 'array',
							'description' => __( 'Conversation messages in order. Each item: { role: "user"|"assistant", content: string }.', 'chagency' ),
						),
						'system_instruction' => array( 'required' => false, 'type' => 'string' ),
						'model_preference'   => array( 'required' => false, 'type' => 'string' ),
						'page_context'       => array(
							'required' => false,
							'type'     => 'object',
							'description' => __( 'Optional context about the admin page the user is on: { title, path }. Expanded into {current_page} / {current_url} placeholders in the system instruction.', 'chagency' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'handle_test' ),
					'permission_callback' => $admin,
					'args'                => array(
						'provider' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Connector ID to probe.', 'chagency' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/connectors',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn(): WP_REST_Response => new WP_REST_Response( list_connectors_status() ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static fn(): WP_REST_Response => new WP_REST_Response( get_plugin_settings() ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'handle_save_settings' ),
					'permission_callback' => $admin,
					'args'                => array(
						'admin_enabled'      => array( 'type' => 'boolean' ),
						'frontend_enabled'   => array( 'type' => 'boolean' ),
						'chat_title'         => array( 'type' => 'string' ),
						'system_instruction' => array( 'type' => 'string' ),
						'greeting'           => array( 'type' => 'string' ),
						'model_preference'   => array( 'type' => 'string' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'handle_reset_settings' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * Handles POST /chagency/v1/chat.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_chat( WP_REST_Request $request ) {
		$messages = $request->get_param( 'messages' );
		if ( ! is_array( $messages ) || empty( $messages ) ) {
			return new WP_Error(
				'chagency_empty_messages',
				__( 'At least one user message is required.', 'chagency' ),
				array( 'status' => 400 )
			);
		}

		$normalized = array();
		foreach ( $messages as $msg ) {
			if ( ! is_array( $msg ) ) {
				continue;
			}
			$role    = isset( $msg['role'] ) ? (string) $msg['role'] : '';
			$content = isset( $msg['content'] ) ? trim( (string) $msg['content'] ) : '';
			if ( '' === $content ) {
				continue;
			}
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
				continue;
			}
			$normalized[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}
		if ( empty( $normalized ) ) {
			return new WP_Error(
				'chagency_empty_messages',
				__( 'At least one user message is required.', 'chagency' ),
				array( 'status' => 400 )
			);
		}

		$last = end( $normalized );
		if ( 'user' !== $last['role'] ) {
			return new WP_Error(
				'chagency_last_not_user',
				__( 'The last message in the conversation must be from the user.', 'chagency' ),
				array( 'status' => 400 )
			);
		}
		$history = array_slice( $normalized, 0, -1 );

		$options = array();
		$system_raw = $request->get_param( 'system_instruction' );
		if ( null !== $system_raw ) {
			$options['system_instruction'] = (string) $system_raw;
		}
		if ( null !== $request->get_param( 'model_preference' ) ) {
			$options['model_preference'] = (string) $request->get_param( 'model_preference' );
		}

		// Expand placeholders server-side using the current user + provided
		// page context. Whether the system instruction is the stored one or
		// a per-request override, placeholders are honoured either way.
		$page_context = $request->get_param( 'page_context' );
		$page_context = is_array( $page_context ) ? $page_context : null;
		$settings     = get_plugin_settings();
		$template     = $options['system_instruction'] ?? $settings['system_instruction'];
		$options['system_instruction'] = expand_placeholders( (string) $template, $page_context );

		$builder = AI_Service::get_instance()->create_chat_prompt( (string) $last['content'], $history, $options );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		try {
			if ( ! $builder->is_supported_for_text_generation() ) {
				return new WP_Error(
					'chagency_unsupported',
					__( 'No configured AI provider can generate text right now.', 'chagency' ),
					array( 'status' => 503 )
				);
			}
			$reply = $builder->generate_text();
			if ( is_wp_error( $reply ) ) {
				return $reply;
			}
			return new WP_REST_Response( array( 'reply' => (string) $reply ) );
		} catch ( Throwable $t ) {
			return new WP_Error(
				'chagency_unexpected_error',
				$t->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Handles POST /chagency/v1/test.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_test( WP_REST_Request $request ) {
		$provider = (string) $request->get_param( 'provider' );
		if ( '' === $provider ) {
			return new WP_Error(
				'chagency_bad_provider',
				__( 'A provider ID is required.', 'chagency' ),
				array( 'status' => 400 )
			);
		}

		$result = AI_Service::get_instance()->test_provider( $provider );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['provider'] = $provider;
		return new WP_REST_Response( $result );
	}

	/**
	 * Handles POST /chagency/v1/settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_save_settings( WP_REST_Request $request ): WP_REST_Response {
		$input = array(
			'admin_enabled'      => (bool) $request->get_param( 'admin_enabled' ),
			'frontend_enabled'   => (bool) $request->get_param( 'frontend_enabled' ),
			'chat_title'         => (string) $request->get_param( 'chat_title' ),
			'system_instruction' => (string) $request->get_param( 'system_instruction' ),
			'greeting'           => (string) $request->get_param( 'greeting' ),
			'model_preference'   => (string) $request->get_param( 'model_preference' ),
		);
		$sanitized = sanitize_settings( $input );
		update_option( \Chagency\OPTION_KEY, $sanitized );
		return new WP_REST_Response( get_plugin_settings() );
	}

	/**
	 * Handles DELETE /chagency/v1/settings. Resets the option back to defaults.
	 */
	public static function handle_reset_settings(): WP_REST_Response {
		delete_option( \Chagency\OPTION_KEY );
		return new WP_REST_Response( get_plugin_settings() );
	}
}
