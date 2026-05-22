<?php
/**
 * Abilities API registrations for the Chagency plugin.
 *
 * Exposes the chatbot as a first-class WordPress Ability so other plugins,
 * MCP clients, and AI agents can invoke it directly.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency\Abilities;

use Chagency\Services\AI_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ability registration + execution.
 */
class Registrar {

	public const ABILITY = 'chagency/send-message';

	/**
	 * Wires the hook.
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_ability' ) );
	}

	/**
	 * Registers the send-message ability.
	 */
	public static function register_ability(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Send a message to the chatbot', 'chagency' ),
				'description'         => __( 'Sends a single message to the configured AI provider using the chatbot system instruction, and returns the assistant reply.', 'chagency' ),
				'category'            => CHAGENCY_ABILITY_CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'message'            => array(
							'type'        => 'string',
							'description' => __( 'The user message to send.', 'chagency' ),
							'minLength'   => 1,
						),
						'system_instruction' => array(
							'type'        => 'string',
							'description' => __( 'Optional override for the system instruction.', 'chagency' ),
						),
					),
					'required'   => array( 'message' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'reply' => array(
							'type'        => 'string',
							'description' => __( 'Assistant reply text.', 'chagency' ),
						),
					),
					'required'   => array( 'reply' ),
				),
				'execute_callback'    => array( self::class, 'execute' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly' => false,
					),
				),
			)
		);
	}

	/**
	 * Executes the chagency/send-message ability.
	 *
	 * @param array<string,mixed> $input Payload matching the input schema.
	 * @return array<string,string>|WP_Error
	 */
	public static function execute( array $input ) {
		$message = isset( $input['message'] ) ? trim( (string) $input['message'] ) : '';
		if ( '' === $message ) {
			return new WP_Error( 'chagency_empty_message', __( 'Message cannot be empty.', 'chagency' ) );
		}

		$options = array();
		if ( isset( $input['system_instruction'] ) && '' !== (string) $input['system_instruction'] ) {
			$options['system_instruction'] = (string) $input['system_instruction'];
		}

		$builder = AI_Service::get_instance()->create_chat_prompt( $message, array(), $options );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		$reply = $builder->generate_text();
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		return array( 'reply' => (string) $reply );
	}
}
