<?php
/**
 * Global helpers for the Chagency plugin.
 *
 * @package Chagency
 */

declare( strict_types=1 );

namespace Chagency;

defined( 'ABSPATH' ) || exit;

/**
 * Option key that stores the plugin's single settings array.
 */
const OPTION_KEY = 'chagency_settings';

/**
 * Default system instruction. Uses the plugin's placeholder vocabulary so the
 * bot always knows who it is, who the user is, and where they are.
 */
function default_system_instruction(): string {
	return __(
		"You are a helpful WordPress assistant embedded in the WordPress admin. You are talking to {user_name} ({user_role}) on the site \"{site_name}\". The user is currently viewing: {current_page}.\n\nBe concise and direct. When referencing WordPress screens, use their full menu path (e.g. \"Settings → Writing\"). If you are not certain of something, say so.",
		'chagency'
	);
}

/**
 * Default greeting.
 */
function default_greeting(): string {
	return __( "Hi! I'm your WordPress assistant. Ask me anything!", 'chagency' );
}

/**
 * Returns the stored settings merged with defaults.
 *
 * Named `get_plugin_settings()` rather than `get_settings()` to avoid
 * a name collision with WordPress core's long-deprecated `get_settings()`
 * function — Plugin Check flags any `get_settings()` call regardless of
 * namespace.
 *
 * @return array{enabled:bool,system_instruction:string,greeting:string,model_preference:string}
 */
function get_plugin_settings(): array {
	$defaults = array(
		'enabled'            => false,
		'system_instruction' => default_system_instruction(),
		'greeting'           => default_greeting(),
		'model_preference'   => 'auto',
	);
	$stored = get_option( OPTION_KEY, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	$out = array_merge( $defaults, $stored );

	$out['enabled']          = (bool) $out['enabled'];
	$out['model_preference'] = is_string( $out['model_preference'] ) && '' !== $out['model_preference']
		? $out['model_preference']
		: 'auto';

	return $out;
}

/**
 * Sanitize callback for `register_setting`.
 *
 * @param mixed $input Raw submitted value.
 * @return array<string,mixed>
 */
function sanitize_settings( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = array();

	$out['enabled'] = ! empty( $input['enabled'] );

	$system = isset( $input['system_instruction'] ) ? sanitize_textarea_field( (string) $input['system_instruction'] ) : '';
	$out['system_instruction'] = '' !== $system ? $system : default_system_instruction();

	$greeting = isset( $input['greeting'] ) ? sanitize_text_field( (string) $input['greeting'] ) : '';
	$out['greeting'] = '' !== $greeting ? $greeting : default_greeting();

	$model = isset( $input['model_preference'] ) ? sanitize_text_field( (string) $input['model_preference'] ) : '';
	$out['model_preference'] = '' !== $model ? $model : 'auto';

	return $out;
}

/**
 * Returns the complete list of placeholder substitutions the plugin supports.
 * The keys are the display names shown in the Settings UI; the values are the
 * actual `{placeholder}` tokens they expand into.
 *
 * @return array<string,string>
 */
function supported_placeholders(): array {
	return array(
		'{user_name}'    => __( 'Display name of the current user.', 'chagency' ),
		'{user_login}'   => __( 'Login handle of the current user.', 'chagency' ),
		'{user_role}'    => __( 'Primary role (administrator, editor, etc.).', 'chagency' ),
		'{site_name}'    => __( 'Site title as set under Settings → General.', 'chagency' ),
		'{site_url}'     => __( 'Site home URL.', 'chagency' ),
		'{current_page}' => __( 'Title of the admin page the user is currently viewing.', 'chagency' ),
		'{current_url}'  => __( 'URL path of the admin page the user is currently viewing.', 'chagency' ),
	);
}

/**
 * Expands placeholder tokens in a system instruction.
 *
 * @param string                                $template     Raw system instruction.
 * @param array{title?:string,path?:string}|null $page_context Optional page context from the client.
 * @return string
 */
function expand_placeholders( string $template, ?array $page_context = null ): string {
	$user  = wp_get_current_user();
	$roles = array();
	if ( $user && is_array( $user->roles ) ) {
		$roles = $user->roles;
	}
	$primary_role = ! empty( $roles ) ? (string) $roles[0] : __( 'visitor', 'chagency' );

	$display = $user && ! empty( $user->display_name ) ? (string) $user->display_name : ( $user ? (string) $user->user_login : '' );

	$title = isset( $page_context['title'] ) && is_string( $page_context['title'] ) ? sanitize_text_field( $page_context['title'] ) : '';
	$path  = isset( $page_context['path'] )  && is_string( $page_context['path'] )  ? sanitize_text_field( $page_context['path'] )  : '';

	$replacements = array(
		'{user_name}'    => '' !== $display ? $display : __( 'there', 'chagency' ),
		'{user_login}'   => $user ? (string) $user->user_login : '',
		'{user_role}'    => $primary_role,
		'{site_name}'    => (string) get_bloginfo( 'name' ),
		'{site_url}'     => home_url(),
		'{current_page}' => '' !== $title ? $title : __( '(unknown page)', 'chagency' ),
		'{current_url}'  => $path,
	);

	return strtr( $template, $replacements );
}

/**
 * Default preferred model order when no user preference is set.
 *
 * @return list<array{0:string,1:string}>
 */
function default_model_preferences(): array {
	return array(
		array( 'anthropic', 'claude-sonnet-4-6' ),
		array( 'google', 'gemini-3-flash-preview' ),
		array( 'google', 'gemini-2.5-flash' ),
		array( 'openai', 'gpt-5.4-mini' ),
		array( 'openai', 'gpt-4.1-mini' ),
	);
}

/**
 * Returns true if at least one AI provider is configured.
 */
function has_credentials(): bool {
	if ( ! function_exists( 'wp_get_connectors' ) ) {
		return false;
	}
	foreach ( wp_get_connectors() as $data ) {
		if ( ! is_array( $data ) || 'ai_provider' !== ( $data['type'] ?? '' ) ) {
			continue;
		}
		$auth = $data['authentication'] ?? array();
		if ( 'api_key' !== ( $auth['method'] ?? '' ) ) {
			return true;
		}
		if ( empty( $auth['setting_name'] ) ) {
			continue;
		}
		if ( '' !== (string) get_option( $auth['setting_name'], '' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Returns connectors and their configuration status.
 *
 * @return list<array{id:string,name:string,method:string,isConfigured:bool}>
 */
function list_connectors_status(): array {
	$out = array();
	if ( ! function_exists( 'wp_get_connectors' ) ) {
		return $out;
	}
	foreach ( wp_get_connectors() as $id => $data ) {
		if ( ! is_array( $data ) || 'ai_provider' !== ( $data['type'] ?? '' ) ) {
			continue;
		}
		$auth          = $data['authentication'] ?? array();
		$method        = (string) ( $auth['method'] ?? 'none' );
		$is_configured = false;
		if ( 'api_key' === $method ) {
			$setting_name  = (string) ( $auth['setting_name'] ?? '' );
			$is_configured = '' !== $setting_name && '' !== (string) get_option( $setting_name, '' );
		} else {
			$is_configured = true;
		}
		$out[] = array(
			'id'           => (string) $id,
			'name'         => (string) ( $data['name'] ?? $id ),
			'method'       => $method,
			'isConfigured' => $is_configured,
		);
	}
	usort(
		$out,
		static fn( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] )
	);
	return $out;
}

/**
 * REST permission check — same bar as managing Connectors.
 */
function rest_permission_check(): bool {
	return current_user_can( 'manage_options' );
}

/**
 * Returns a stable conversation storage key tied to the current user.
 * The JS widget uses this as the localStorage key so each admin user has
 * their own history.
 */
function conversation_storage_key(): string {
	$user_id = get_current_user_id();
	return 'chagency:conversation:' . intval( $user_id );
}
