/**
 * Tiny REST helper. Uses the `chagency/v1` namespace with the nonce seeded by
 * `wp_localize_script`.
 *
 * @package
 */

import { getPageContext } from './pageContext';

// The widget bundle seeds `chagencyConfig`; the Settings page seeds
// `chagencySettingsConfig`. Both shapes carry `restUrl` + `nonce`, so we
// fall back so REST calls work even when the widget bundle isn't loaded
// (e.g. on the Settings page before any AI provider has been configured).
const cfg = window.chagencyConfig || window.chagencySettingsConfig || {};

/**
 * Performs a JSON request to a path inside /chagency/v1.
 *
 * @param {string} path   Path within the namespace (e.g. "/chat").
 * @param {Object} [opts] Options: { method, body }.
 * @return {Promise<any>} Parsed JSON response.
 */
export function apiRequest( path, opts = {} ) {
	const url = ( cfg.restUrl || '' ).replace( /\/$/, '' ) + path;
	const method = opts.method || ( opts.body !== undefined ? 'POST' : 'GET' );
	const init = {
		method,
		headers: { Accept: 'application/json' },
		credentials: 'same-origin',
	};
	if ( opts.body !== undefined ) {
		init.headers[ 'Content-Type' ] = 'application/json';
		init.body = JSON.stringify( opts.body );
	}
	if ( cfg.nonce ) {
		init.headers[ 'X-WP-Nonce' ] = cfg.nonce;
	}

	return fetch( url, init ).then( async ( response ) => {
		const data = await response.json().catch( () => null );
		if ( ! response.ok ) {
			const err = new Error(
				( data && data.message ) || response.statusText
			);
			err.data = data;
			throw err;
		}
		return data;
	} );
}

export const getSettings = () => apiRequest( '/settings' );
export const saveSettings = ( payload ) =>
	apiRequest( '/settings', { method: 'POST', body: payload } );
export const resetSettings = () =>
	apiRequest( '/settings', { method: 'DELETE' } );
export const getConnectors = () => apiRequest( '/connectors' );
export const testProvider = ( provider ) =>
	apiRequest( '/test', { body: { provider } } );

/**
 * Sends a chat conversation. Always forwards the live page context so the
 * server can expand `{current_page}` / `{current_url}` in the system prompt.
 *
 * @param {Array<{role: string, content: string}>} messages Wire-format history.
 * @return {Promise<any>} Parsed JSON response from /chat.
 */
export function sendChat( messages ) {
	return apiRequest( '/chat', {
		body: {
			messages,
			page_context: getPageContext(),
		},
	} );
}
