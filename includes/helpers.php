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
 * Default system instruction.
 */
function default_system_instruction(): string {
	return __(
		"You are a helpful assistant for the site \"{site_name}\". You are talking to {user_name}, currently viewing: {current_page}.\n\nBe concise and direct. If you are not certain of something, say so.",
		'chagency'
	);
}

/**
 * Default greeting.
 */
function default_greeting(): string {
	return __( "Hi! How can I help?", 'chagency' );
}

/**
 * Default chat title shown in the panel header and on the launcher tooltip.
 * Kept neutral on purpose so it suits any site.
 */
function default_chat_title(): string {
	return __( 'Assistant', 'chagency' );
}

/**
 * Returns the default settings shape.
 *
 * @return array{admin_enabled:bool,frontend_enabled:bool,chat_title:string,system_instruction:string,greeting:string,model_preference:string,abilities_enabled:bool,abilities:list<string>}
 */
function default_settings(): array {
	return array(
		'admin_enabled'      => true,
		'frontend_enabled'   => false,
		'chat_title'         => default_chat_title(),
		'system_instruction' => default_system_instruction(),
		'greeting'           => default_greeting(),
		'model_preference'   => 'auto',
		'abilities_enabled'  => false,
		'abilities'          => array(),
	);
}

/**
 * Returns the stored settings merged with defaults.
 *
 * Named `get_plugin_settings()` rather than `get_settings()` to avoid
 * a name collision with WordPress core's long-deprecated `get_settings()`
 * function. Plugin Check flags any `get_settings()` call regardless of
 * namespace.
 *
 * @return array{admin_enabled:bool,frontend_enabled:bool,chat_title:string,system_instruction:string,greeting:string,model_preference:string,abilities_enabled:bool,abilities:list<string>}
 */
function get_plugin_settings(): array {
	$stored = get_option( OPTION_KEY, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	$out = array_merge( default_settings(), $stored );

	$out['admin_enabled']     = (bool) $out['admin_enabled'];
	$out['frontend_enabled']  = (bool) $out['frontend_enabled'];
	$out['abilities_enabled'] = (bool) $out['abilities_enabled'];
	$out['abilities']         = is_array( $out['abilities'] ) ? array_values( array_filter( $out['abilities'], 'is_string' ) ) : array();
	$out['model_preference']  = is_string( $out['model_preference'] ) && '' !== $out['model_preference']
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

	$out['admin_enabled']    = ! empty( $input['admin_enabled'] );
	$out['frontend_enabled'] = ! empty( $input['frontend_enabled'] );

	$title = isset( $input['chat_title'] ) ? sanitize_text_field( (string) $input['chat_title'] ) : '';
	$out['chat_title'] = '' !== $title ? $title : default_chat_title();

	$system = isset( $input['system_instruction'] ) ? sanitize_textarea_field( (string) $input['system_instruction'] ) : '';
	$out['system_instruction'] = '' !== $system ? $system : default_system_instruction();

	$greeting = isset( $input['greeting'] ) ? sanitize_text_field( (string) $input['greeting'] ) : '';
	$out['greeting'] = '' !== $greeting ? $greeting : default_greeting();

	$model = isset( $input['model_preference'] ) ? sanitize_text_field( (string) $input['model_preference'] ) : '';
	$out['model_preference'] = '' !== $model ? $model : 'auto';

	$out['abilities_enabled'] = ! empty( $input['abilities_enabled'] );
	$out['abilities']         = sanitize_ability_names( $input['abilities'] ?? array() );

	return $out;
}

/**
 * Keeps only well-formed ability names.
 *
 * Core requires lowercase, namespaced names such as `core/get-site-info`, so
 * anything else can never match a registered ability and is dropped here.
 *
 * @param mixed $names Raw list of ability names.
 * @return list<string>
 */
function sanitize_ability_names( $names ): array {
	if ( ! is_array( $names ) ) {
		return array();
	}
	$out = array();
	foreach ( $names as $name ) {
		if ( ! is_string( $name ) ) {
			continue;
		}
		$name = trim( $name );
		if ( 1 === preg_match( '#^[a-z0-9\-]+/[a-z0-9\-]+$#', $name ) ) {
			$out[] = $name;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Returns the complete list of placeholder substitutions the plugin supports.
 *
 * @return array<string,string>
 */
function supported_placeholders(): array {
	return array(
		'{user_name}'    => __( 'Display name of the current user (or "there" for visitors).', 'chagency' ),
		'{user_role}'    => __( 'Primary role (administrator, editor, subscriber, visitor).', 'chagency' ),
		'{site_name}'    => __( 'Site title.', 'chagency' ),
		'{site_url}'     => __( 'Site home URL.', 'chagency' ),
		'{current_page}' => __( 'Title of the page the user is currently viewing.', 'chagency' ),
		'{current_url}'  => __( 'URL path of the page the user is currently viewing.', 'chagency' ),
	);
}

/**
 * Expands placeholder tokens in a system instruction.
 *
 * @param string                                 $template     Raw system instruction.
 * @param array{title?:string,path?:string}|null $page_context Optional page context from the client.
 * @return string
 */
function expand_placeholders( string $template, ?array $page_context = null ): string {
	$user         = wp_get_current_user();
	$is_logged_in = $user && $user->ID > 0;
	$roles        = $is_logged_in && is_array( $user->roles ) ? $user->roles : array();
	$primary_role = ! empty( $roles ) ? (string) $roles[0] : __( 'visitor', 'chagency' );

	$display = '';
	if ( $is_logged_in ) {
		$display = ! empty( $user->display_name ) ? (string) $user->display_name : (string) $user->user_login;
	}

	$title = isset( $page_context['title'] ) && is_string( $page_context['title'] ) ? sanitize_text_field( $page_context['title'] ) : '';
	$path  = isset( $page_context['path'] )  && is_string( $page_context['path'] )  ? sanitize_text_field( $page_context['path'] )  : '';

	$replacements = array(
		'{user_name}'    => '' !== $display ? $display : __( 'there', 'chagency' ),
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
 * Returns true when an api_key connector has its key available from any source
 * WordPress 7 recognises: an environment variable, a PHP constant, or the saved
 * option, in that order, matching core's own `_wp_connectors_get_api_key_source()`.
 *
 * @param array<string,mixed> $auth A connector's `authentication` array.
 */
function api_key_connector_has_credentials( array $auth ): bool {
	$env_var = (string) ( $auth['env_var_name'] ?? '' );
	if ( '' !== $env_var ) {
		$value = getenv( $env_var );
		if ( false !== $value && '' !== $value ) {
			return true;
		}
	}
	$constant = (string) ( $auth['constant_name'] ?? '' );
	if ( '' !== $constant && defined( $constant ) ) {
		$value = constant( $constant );
		if ( is_string( $value ) && '' !== $value ) {
			return true;
		}
	}
	$setting_name = (string) ( $auth['setting_name'] ?? '' );
	return '' !== $setting_name && '' !== (string) get_option( $setting_name, '' );
}

/**
 * Returns true when an `application_password` connector (WP 7.1) has both a
 * username and a password available. Core resolves env var, constant and
 * option in that order, so we just ask it.
 *
 * @param array<string,mixed> $auth A connector's `authentication` array.
 */
function application_password_connector_has_credentials( array $auth ): bool {
	if ( ! function_exists( 'wp_connectors_get_application_password_credentials' ) ) {
		return false;
	}
	$credentials = wp_connectors_get_application_password_credentials( $auth );
	return ! empty( $credentials['username'] ) && ! empty( $credentials['password'] );
}

/**
 * Returns true when a connector has credentials available.
 *
 * Deliberately does NOT ask the AI Client registry. `isProviderConfigured()`
 * looks like the right call (it is what core's Connectors screen uses) but it
 * probes the provider over the network: the `ListModels` strategy performs an
 * HTTP request, cached for a day, and the `GenerateText` strategy fires a real,
 * billed generation request with no caching at all. Core can afford that on one
 * settings screen. `has_credentials()` gates the launcher on *every* admin page
 * load, and on every front-end page when the public widget is on, so it must
 * stay pure local reads.
 *
 * Unknown auth methods are treated as configured: a future WordPress may add
 * one we cannot read, and a launcher that appears and then reports a provider
 * error is a better failure than a launcher that silently never appears.
 *
 * @param array<string,mixed> $data Connector data from `wp_get_connectors()`.
 */
function connector_is_configured( array $data ): bool {
	$auth   = is_array( $data['authentication'] ?? null ) ? $data['authentication'] : array();
	$method = (string) ( $auth['method'] ?? 'none' );

	if ( 'api_key' === $method ) {
		return api_key_connector_has_credentials( $auth );
	}
	if ( 'application_password' === $method ) {
		return application_password_connector_has_credentials( $auth );
	}
	return true;
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
		if ( connector_is_configured( $data ) ) {
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
		$auth  = is_array( $data['authentication'] ?? null ) ? $data['authentication'] : array();
		$out[] = array(
			'id'           => (string) $id,
			'name'         => (string) ( $data['name'] ?? $id ),
			'method'       => (string) ( $auth['method'] ?? 'none' ),
			'isConfigured' => connector_is_configured( $data ),
		);
	}
	usort(
		$out,
		static fn( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] )
	);
	return $out;
}

/**
 * Returns every registered ability, described for the settings UI.
 *
 * Permissions are deliberately not evaluated here: an ability's
 * `permission_callback` runs at execution time, inside core, for the user who
 * is actually chatting. This list only decides what an administrator may
 * offer to the assistant.
 *
 * @return list<array{name:string,label:string,description:string,category:string,categoryLabel:string,readonly:bool,destructive:bool}>
 */
function list_abilities(): array {
	$out = array();
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return $out;
	}

	$category_labels = array();
	if ( function_exists( 'wp_get_ability_categories' ) ) {
		foreach ( wp_get_ability_categories() as $slug => $category ) {
			$category_labels[ (string) $slug ] = $category->get_label();
		}
	}

	foreach ( wp_get_abilities() as $ability ) {
		$annotations = $ability->get_meta_item( 'annotations', array() );
		$annotations = is_array( $annotations ) ? $annotations : array();
		$category    = $ability->get_category();

		$out[] = array(
			'name'          => $ability->get_name(),
			'label'         => $ability->get_label(),
			'description'   => $ability->get_description(),
			'category'      => $category,
			'categoryLabel' => (string) ( $category_labels[ $category ] ?? $category ),
			'readonly'      => ! empty( $annotations['readonly'] ),
			'destructive'   => ! empty( $annotations['destructive'] ),
		);
	}

	usort(
		$out,
		static fn( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] )
	);
	return $out;
}

/**
 * Returns the abilities the assistant may call for the current request.
 *
 * Empty unless the feature is on, the current user can manage options, and the
 * abilities are still registered. Keeping this admin-only means the public
 * widget never advertises the site's abilities to anonymous visitors, on top of
 * the per-ability permission checks core runs anyway.
 *
 * @return list<string>
 */
function agent_abilities(): array {
	$settings = get_plugin_settings();
	if ( empty( $settings['abilities_enabled'] ) || empty( $settings['abilities'] ) ) {
		return array();
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return array();
	}
	if ( ! function_exists( 'wp_has_ability' ) ) {
		return array();
	}

	$out = array();
	foreach ( sanitize_ability_names( $settings['abilities'] ) as $name ) {
		if ( wp_has_ability( $name ) ) {
			$out[] = $name;
		}
	}
	return $out;
}

/**
 * Admin permission check. Used by Settings, Providers test, etc.
 */
function admin_permission_check(): bool {
	return current_user_can( 'manage_options' );
}

/**
 * Chat permission check. Open to everyone when the front-end widget is on,
 * otherwise admin-only.
 */
function chat_permission_check(): bool {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$settings = get_plugin_settings();
	return ! empty( $settings['frontend_enabled'] );
}

/**
 * Returns a stable conversation storage key.
 * Logged-in users get a per-user key. Visitors share a per-site anonymous key.
 */
function conversation_storage_key(): string {
	$user_id = get_current_user_id();
	if ( $user_id > 0 ) {
		return 'chagency:conversation:user:' . intval( $user_id );
	}
	return 'chagency:conversation:guest';
}
