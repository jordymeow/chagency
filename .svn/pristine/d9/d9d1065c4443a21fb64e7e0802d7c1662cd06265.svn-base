<?php
/**
 * Plugin environment requirements.
 *
 * Verifies PHP version, WordPress version, the WP 7 AI framework (presence of
 * `wp_ai_client_prompt()`), and that the compiled React bundle exists.
 * Structure mirrors WordPress/ai's `Requirements` class.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency;

defined( 'ABSPATH' ) || exit;

/**
 * Requirements checker.
 *
 * @internal This class should not be used outside the plugin.
 */
final class Requirements {

	private const MIN_PHP_VERSION = '7.4';
	private const MIN_WP_VERSION  = '7.0';

	/**
	 * Resolved check results. Keys are slugs; values are either `true` (met)
	 * or a callable returning a localized error string (not met).
	 *
	 * @var array<string, true|callable():string>
	 */
	private array $results = array();

	/**
	 * Runs every check. Returns true if all pass. On failure, queues an
	 * admin notice via `admin_notices`.
	 */
	public function are_requirements_met(): bool {
		foreach ( $this->get_requirements() as $slug => $check ) {
			$success = (bool) $check['check']();
			$this->results[ $slug ] = $success ? true : $check['error_message'];
		}

		if ( $this->all_met() ) {
			return true;
		}

		$this->queue_admin_notice();
		return false;
	}

	/**
	 * Returns the list of checks to run, keyed by slug.
	 *
	 * @return array<string, array{check:callable():bool, error_message:callable():string}>
	 */
	private function get_requirements(): array {
		return array(
			'php'       => array(
				'check'         => static fn(): bool => version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' ),
				'error_message' => static fn(): string => sprintf(
					/* translators: 1: Required PHP version, 2: Current PHP version. */
					esc_html__( 'Chagency requires PHP %1$s or higher. You are running PHP %2$s.', 'chagency' ),
					esc_html( self::MIN_PHP_VERSION ),
					esc_html( PHP_VERSION )
				),
			),
			'wp'        => array(
				'check'         => static fn(): bool => function_exists( 'is_wp_version_compatible' )
					&& is_wp_version_compatible( self::MIN_WP_VERSION ),
				'error_message' => static fn(): string => sprintf(
					/* translators: %s: Required WordPress version. */
					esc_html__( 'Chagency requires WordPress %s or higher.', 'chagency' ),
					esc_html( self::MIN_WP_VERSION )
				),
			),
			'framework' => array(
				'check'         => static fn(): bool => function_exists( 'wp_ai_client_prompt' )
					&& function_exists( 'wp_get_connectors' ),
				'error_message' => static fn(): string => esc_html__(
					'Chagency requires the WordPress 7 AI framework (wp_ai_client_prompt) which is not available on this install.',
					'chagency'
				),
			),
			'build'     => array(
				'check'         => static fn(): bool => file_exists( CHAGENCY_PLUGIN_DIR . 'build/index.js' ),
				'error_message' => static fn(): string => esc_html__(
					'The Chagency plugin assets are not built. Run `pnpm install && pnpm run build` from the plugin directory.',
					'chagency'
				),
			),
		);
	}

	/**
	 * Returns true if every check passed.
	 */
	private function all_met(): bool {
		foreach ( $this->results as $value ) {
			if ( true !== $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Queues a single admin notice listing every failed check.
	 */
	private function queue_admin_notice(): void {
		$message_html = $this->build_notice_html();
		$hooks        = array( 'admin_notices', 'network_admin_notices' );
		foreach ( $hooks as $hook ) {
			add_action(
				$hook,
				static function () use ( $message_html ): void {
					wp_admin_notice(
						wp_kses(
							$message_html,
							array(
								'br' => array(),
								'ul' => array(),
								'li' => array(),
							)
						),
						array( 'type' => 'error' )
					);
				}
			);
		}
	}

	/**
	 * Assembles the admin-notice HTML from failed checks.
	 */
	private function build_notice_html(): string {
		$messages = array();
		foreach ( $this->results as $value ) {
			if ( is_callable( $value ) ) {
				$messages[] = $value();
			}
		}
		$messages = array_filter( $messages );

		if ( 1 === count( $messages ) ) {
			return (string) reset( $messages );
		}

		return esc_html__( 'Chagency cannot run due to the following issues:', 'chagency' )
			. '<br><ul><li>' . implode( '</li><li>', $messages ) . '</li></ul>';
	}
}
