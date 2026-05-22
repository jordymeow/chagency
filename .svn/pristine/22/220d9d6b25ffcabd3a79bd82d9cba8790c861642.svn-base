/**
 * Returns the page-context payload the server uses to expand
 * {current_page} / {current_url} placeholders in the system instruction.
 *
 * @package
 */

export function getPageContext() {
	try {
		const rawTitle = document.title || '';
		// Strip trailing "‹ Site Name, WordPress" to keep the placeholder tight.
		const title = rawTitle.replace( /\s*[‹<]\s.*$/, '' ).trim();
		return {
			title,
			path: window.location.pathname + window.location.search,
		};
	} catch ( _ ) {
		return { title: '', path: '' };
	}
}
