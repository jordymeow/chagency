<?php
/**
 * AI service singleton.
 *
 * Wraps `wp_ai_client_prompt()` with the plugin's defaults (model preference,
 * system instruction, history handling). Structurally mirrors WordPress/ai's
 * `AI_Service` — there's only one call-site per concern, so features don't
 * rebuild the fluent chain each time.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency\Services;

use Throwable;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WP_AI_Client_Prompt_Builder;
use WP_Error;

use function Chagency\default_model_preferences;

defined( 'ABSPATH' ) || exit;

/**
 * AI Service singleton.
 */
class AI_Service {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * @return self The singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor enforces the singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Builds a configured prompt for chat text generation.
	 *
	 * @param string                                                           $current       Current user turn.
	 * @param list<array{role:'user'|'assistant',content:string}>              $history       Prior conversation turns (last-to-first).
	 * @param array{system_instruction?:string,model_preference?:string}       $options       Overrides. Empty values fall back to `get_plugin_settings()`.
	 * @return WP_AI_Client_Prompt_Builder|WP_Error
	 */
	public function create_chat_prompt( string $current, array $history, array $options = array() ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'chagency_framework_missing',
				__( 'The AI Client framework is not available.', 'chagency' )
			);
		}

		$settings = \Chagency\get_plugin_settings();
		$system   = $options['system_instruction'] ?? $settings['system_instruction'];
		$model    = $options['model_preference'] ?? $settings['model_preference'];

		$history_dto = array();
		foreach ( $history as $h ) {
			if ( empty( $h['content'] ) ) {
				continue;
			}
			$role            = 'user' === $h['role'] ? MessageRoleEnum::user() : MessageRoleEnum::model();
			$history_dto[] = new Message( $role, array( new MessagePart( (string) $h['content'] ) ) );
		}

		$builder = wp_ai_client_prompt( $current )
			->using_system_instruction( (string) $system );

		if ( ! empty( $history_dto ) ) {
			$builder = $builder->with_history( ...$history_dto );
		}

		if ( 'auto' === $model || '' === $model ) {
			$builder = $builder->using_model_preference( ...default_model_preferences() );
		} else {
			$builder = $builder->using_provider( (string) $model );
		}

		return $builder;
	}

	/**
	 * Sends a canary prompt to a specific provider to verify it answers.
	 *
	 * @param string $provider_id Connector ID.
	 * @return array{ok:bool,reply:string,ms:int}|WP_Error
	 */
	public function test_provider( string $provider_id ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'chagency_framework_missing',
				__( 'The AI Client framework is not available.', 'chagency' )
			);
		}

		$started = microtime( true );

		try {
			$builder = wp_ai_client_prompt( 'Reply with exactly the word: pong.' )
				->using_provider( $provider_id );

			if ( ! $builder->is_supported_for_text_generation() ) {
				return new WP_Error(
					'chagency_provider_unsupported',
					sprintf(
						/* translators: %s: provider ID. */
						__( 'Provider "%s" cannot generate text right now. Check its API key under Settings → Connectors.', 'chagency' ),
						$provider_id
					),
					array( 'status' => 503 )
				);
			}

			$reply = $builder->generate_text();
			if ( is_wp_error( $reply ) ) {
				return $reply;
			}

			return array(
				'ok'    => true,
				'reply' => (string) $reply,
				'ms'    => (int) round( ( microtime( true ) - $started ) * 1000 ),
			);
		} catch ( Throwable $t ) {
			return new WP_Error(
				'chagency_test_failed',
				$t->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
