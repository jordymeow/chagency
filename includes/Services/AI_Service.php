<?php
/**
 * AI service singleton.
 *
 * Wraps `wp_ai_client_prompt()` with the plugin's defaults (model preference,
 * system instruction, history handling) and owns the ability-calling loop.
 * Structurally mirrors WordPress/ai's `AI_Service`, there's only one call-site
 * per concern, so features don't rebuild the fluent chain each time.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency\Services;

use Throwable;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WP_AI_Client_Ability_Function_Resolver;
use WP_AI_Client_Prompt_Builder;
use WP_Error;

use function Chagency\default_model_preferences;

defined( 'ABSPATH' ) || exit;

/**
 * AI Service singleton.
 */
class AI_Service {

	/**
	 * How many times the model may call abilities before we stop looping.
	 *
	 * Each round is one extra round-trip to the provider, so this is both a
	 * cost ceiling and a runaway guard. Four is plenty for "read a couple of
	 * things, then answer".
	 */
	public const MAX_ABILITY_ROUNDS = 4;

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
	 * @param string                                                     $current  Current user turn.
	 * @param list<array{role:'user'|'assistant',content:string}>         $history  Prior conversation turns, oldest first.
	 * @param array{system_instruction?:string,model_preference?:string,abilities?:list<string>} $options Overrides.
	 * @return WP_AI_Client_Prompt_Builder|WP_Error
	 */
	public function create_chat_prompt( string $current, array $history, array $options = array() ) {
		$messages   = self::to_messages( $history );
		$messages[] = new Message( MessageRoleEnum::user(), array( new MessagePart( $current ) ) );

		return $this->create_prompt( $messages, $options );
	}

	/**
	 * Generates a reply, running the ability-calling loop when abilities are
	 * allowed for this request.
	 *
	 * Core does the heavy lifting: `using_abilities()` turns each ability into
	 * a function declaration, and `WP_AI_Client_Ability_Function_Resolver`
	 * executes the calls the model makes, honouring every ability's own
	 * `permission_callback`. All we own is the loop and the transcript.
	 *
	 * @param string                                                     $current  Current user turn.
	 * @param list<array{role:'user'|'assistant',content:string}>         $history  Prior conversation turns, oldest first.
	 * @param array{system_instruction?:string,model_preference?:string,abilities?:list<string>} $options Overrides.
	 * @return array{reply:string,steps:list<array{ability:string,ok:bool}>}|WP_Error
	 */
	public function generate_chat_reply( string $current, array $history, array $options = array() ) {
		$abilities = isset( $options['abilities'] ) && is_array( $options['abilities'] ) ? $options['abilities'] : array();

		$messages   = self::to_messages( $history );
		$messages[] = new Message( MessageRoleEnum::user(), array( new MessagePart( $current ) ) );

		$resolver = ! empty( $abilities ) && class_exists( 'WP_AI_Client_Ability_Function_Resolver' )
			? new WP_AI_Client_Ability_Function_Resolver( ...$abilities )
			: null;

		$steps = array();

		for ( $round = 0; $round <= self::MAX_ABILITY_ROUNDS; $round++ ) {
			$builder = $this->create_prompt( $messages, $options );
			if ( is_wp_error( $builder ) ) {
				return $builder;
			}

			try {
				if ( 0 === $round && ! $builder->is_supported_for_text_generation() ) {
					return new WP_Error(
						'chagency_unsupported',
						__( 'No configured AI provider can generate text right now.', 'chagency' ),
						array( 'status' => 503 )
					);
				}

				$result = $builder->generate_text_result();
			} catch ( Throwable $t ) {
				return self::friendly_error(
					new WP_Error( 'chagency_unexpected_error', $t->getMessage(), array( 'status' => 500 ) )
				);
			}

			if ( is_wp_error( $result ) ) {
				return self::friendly_error( $result );
			}

			$message = $result->toMessage();

			// No ability calls: this is the final answer.
			if ( null === $resolver || ! $resolver->has_ability_calls( $message ) ) {
				return array(
					'reply' => self::message_to_text( $message ),
					'steps' => $steps,
				);
			}

			// Last round: stop asking. If the model already wrote something
			// alongside its calls, that partial answer beats losing the turn.
			if ( self::MAX_ABILITY_ROUNDS === $round ) {
				$text = self::message_to_text( $message );
				if ( '' !== $text ) {
					return array(
						'reply' => $text,
						'steps' => $steps,
					);
				}
				break;
			}

			$responses  = $resolver->execute_abilities( $message );
			$steps      = array_merge( $steps, self::describe_calls( $message, $responses ) );
			$messages[] = $message;
			$messages[] = $responses;
		}

		return new WP_Error(
			'chagency_ability_loop',
			__( 'The assistant kept calling abilities without reaching an answer.', 'chagency' ),
			array( 'status' => 500 )
		);
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

	/**
	 * Rewrites a provider failure into something a human can act on.
	 *
	 * Core hands us structured codes (`prompt_network_error`,
	 * `prompt_client_error` with the HTTP status, and so on), so the mapping is
	 * on codes rather than on message text. The provider's own wording is kept
	 * in `data.detail` because it is what actually helps when debugging.
	 *
	 * @param WP_Error $error The raw error.
	 */
	private static function friendly_error( WP_Error $error ): WP_Error {
		$code   = $error->get_error_code();
		$data   = $error->get_error_data();
		$data   = is_array( $data ) ? $data : array();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 500;

		$message = '';
		switch ( $code ) {
			case 'prompt_network_error':
				$message = __( 'Could not reach the AI provider. Check this site can make outgoing requests, then try again.', 'chagency' );
				break;
			case 'prompt_upstream_server_error':
				$message = __( 'The AI provider is having trouble on their side. Try again in a moment.', 'chagency' );
				break;
			case 'prompt_token_limit_reached':
				$message = __( 'This conversation is too long for the model. Hit Reset in the chat panel to start a fresh one.', 'chagency' );
				break;
			case 'prompt_client_error':
				if ( 401 === $status || 403 === $status ) {
					$message = __( 'The AI provider rejected the API key. Check it under Settings → Connectors.', 'chagency' );
				} elseif ( 404 === $status ) {
					$message = __( 'The AI provider does not offer that model. Pick another one under Settings → Chagency.', 'chagency' );
				} elseif ( 429 === $status ) {
					$message = __( 'The AI provider is rate limiting this site. Wait a moment and try again.', 'chagency' );
				} else {
					$message = __( 'The AI provider rejected the request.', 'chagency' );
				}
				break;
		}

		if ( '' === $message ) {
			return $error;
		}

		$data['detail'] = $error->get_error_message();
		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Builds the fluent chain for a full message list.
	 *
	 * `wp_ai_client_prompt()` accepts a `list<Message>` and treats it as the
	 * whole conversation, which is what the ability loop needs: each round
	 * appends the model's tool calls and their responses, then replays
	 * everything. That is also why `with_history()` is not used here.
	 *
	 * @param list<Message>                                                                      $messages Full conversation.
	 * @param array{system_instruction?:string,model_preference?:string,abilities?:list<string>} $options  Overrides.
	 * @return WP_AI_Client_Prompt_Builder|WP_Error
	 */
	private function create_prompt( array $messages, array $options = array() ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'chagency_framework_missing',
				__( 'The AI Client framework is not available.', 'chagency' )
			);
		}

		$settings = \Chagency\get_plugin_settings();
		$system   = $options['system_instruction'] ?? $settings['system_instruction'];
		$model    = $options['model_preference'] ?? $settings['model_preference'];

		$builder = wp_ai_client_prompt( $messages )
			->using_system_instruction( (string) $system );

		if ( 'auto' === $model || '' === $model ) {
			$builder = $builder->using_model_preference( ...default_model_preferences() );
		} else {
			$builder = $builder->using_provider( (string) $model );
		}

		$abilities = isset( $options['abilities'] ) && is_array( $options['abilities'] ) ? $options['abilities'] : array();
		if ( ! empty( $abilities ) ) {
			$builder = $builder->using_abilities( ...$abilities );
		}

		return $builder;
	}

	/**
	 * Converts wire-format turns into AI Client messages.
	 *
	 * @param list<array{role:'user'|'assistant',content:string}> $turns Conversation turns.
	 * @return list<Message>
	 */
	private static function to_messages( array $turns ): array {
		$out = array();
		foreach ( $turns as $turn ) {
			if ( empty( $turn['content'] ) ) {
				continue;
			}
			$role  = 'user' === ( $turn['role'] ?? '' ) ? MessageRoleEnum::user() : MessageRoleEnum::model();
			$out[] = new Message( $role, array( new MessagePart( (string) $turn['content'] ) ) );
		}
		return $out;
	}

	/**
	 * Concatenates the text parts of a message.
	 *
	 * `GenerativeAiResult::toText()` throws when a candidate carries no text,
	 * which happens on any turn that is pure function calls, so the parts are
	 * walked by hand instead.
	 *
	 * @param Message $message Message to read.
	 */
	private static function message_to_text( Message $message ): string {
		$chunks = array();
		foreach ( $message->getParts() as $part ) {
			if ( $part->getType()->isText() ) {
				$text = $part->getText();
				if ( is_string( $text ) && '' !== $text ) {
					$chunks[] = $text;
				}
			}
		}
		return trim( implode( "\n\n", $chunks ) );
	}

	/**
	 * Describes the abilities a model message called, and whether each one
	 * came back without an error. Used for the "used X" line in the chat.
	 *
	 * @param Message $calls     The model message carrying the function calls.
	 * @param Message $responses The user message carrying the function responses.
	 * @return list<array{ability:string,ok:bool}>
	 */
	private static function describe_calls( Message $calls, Message $responses ): array {
		$errors = array();
		foreach ( $responses->getParts() as $part ) {
			if ( ! $part->getType()->isFunctionResponse() ) {
				continue;
			}
			$response = $part->getFunctionResponse();
			if ( null === $response ) {
				continue;
			}
			$data = $response->getResponse();
			if ( is_array( $data ) && isset( $data['error'] ) ) {
				$errors[ (string) $response->getName() ] = true;
			}
		}

		$out = array();
		foreach ( $calls->getParts() as $part ) {
			if ( ! $part->getType()->isFunctionCall() ) {
				continue;
			}
			$call = $part->getFunctionCall();
			if ( null === $call ) {
				continue;
			}
			$function_name = (string) $call->getName();
			$out[]         = array(
				'ability' => WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $function_name ),
				'ok'      => ! isset( $errors[ $function_name ] ),
			);
		}
		return $out;
	}
}
