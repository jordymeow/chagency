/**
 * `useConversation` — a tiny hook that persists chat history in localStorage
 * under a per-user key (seeded from PHP via `chagencyConfig.storageKey`). Both
 * the full-page app and the floating widget share this store so the user can
 * continue a conversation across admin pages.
 *
 * @package
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

const STORAGE_VERSION = 1;
const SEEN_SUFFIX = ':seen';

function readStorage( key ) {
	if ( ! key || typeof window === 'undefined' || ! window.localStorage ) {
		return null;
	}
	try {
		const raw = window.localStorage.getItem( key );
		if ( ! raw ) {
			return null;
		}
		const parsed = JSON.parse( raw );
		if (
			! parsed ||
			parsed.v !== STORAGE_VERSION ||
			! Array.isArray( parsed.messages )
		) {
			return null;
		}
		return parsed.messages;
	} catch ( _ ) {
		return null;
	}
}

function writeStorage( key, messages ) {
	if ( ! key || typeof window === 'undefined' || ! window.localStorage ) {
		return;
	}
	try {
		window.localStorage.setItem(
			key,
			JSON.stringify( { v: STORAGE_VERSION, messages } )
		);
	} catch ( _ ) {
		/* quota exceeded, private mode, etc. — silently ignore. */
	}
}

function readSeen( key ) {
	if ( ! key || typeof window === 'undefined' || ! window.localStorage ) {
		return 0;
	}
	try {
		const raw = window.localStorage.getItem( key + SEEN_SUFFIX );
		return raw ? parseInt( raw, 10 ) || 0 : 0;
	} catch ( _ ) {
		return 0;
	}
}

function writeSeen( key, count ) {
	if ( ! key || typeof window === 'undefined' || ! window.localStorage ) {
		return;
	}
	try {
		window.localStorage.setItem( key + SEEN_SUFFIX, String( count ) );
	} catch ( _ ) {
		/* noop */
	}
}

function buildInitial( greeting ) {
	if ( ! greeting ) {
		return [];
	}
	return [ { role: 'assistant', content: greeting, tone: 'greeting' } ];
}

/**
 * @param {Object} opts
 * @param {string} opts.storageKey - per-user key; scoped via PHP.
 * @param {string} opts.greeting   - first-message text (local-only).
 * @return {{ messages: Array, setMessages: Function, append: Function, reset: Function, unreadCount: number, markSeen: Function }} Conversation state + actions.
 */
export default function useConversation( { storageKey, greeting } ) {
	const [ messages, setMessages ] = useState( () => {
		const stored = readStorage( storageKey );
		if ( stored && stored.length > 0 ) {
			return stored;
		}
		return buildInitial( greeting );
	} );

	const [ seenCount, setSeenCount ] = useState( () =>
		readSeen( storageKey )
	);

	const lastWriteRef = useRef( null );

	useEffect( () => {
		lastWriteRef.current = messages;
		writeStorage( storageKey, messages );
	}, [ messages, storageKey ] );

	useEffect( () => {
		if ( ! storageKey || typeof window === 'undefined' ) {
			return undefined;
		}
		function onStorage( e ) {
			if ( e.key === storageKey ) {
				const fresh = readStorage( storageKey );
				if (
					fresh &&
					JSON.stringify( fresh ) !==
						JSON.stringify( lastWriteRef.current )
				) {
					setMessages( fresh );
				}
			}
			if ( e.key === storageKey + SEEN_SUFFIX ) {
				setSeenCount( readSeen( storageKey ) );
			}
		}
		window.addEventListener( 'storage', onStorage );
		return () => window.removeEventListener( 'storage', onStorage );
	}, [ storageKey ] );

	const append = useCallback( ( msg ) => {
		setMessages( ( prev ) => prev.concat( [ msg ] ) );
	}, [] );

	const reset = useCallback( () => {
		const fresh = buildInitial( greeting );
		setMessages( fresh );
		setSeenCount( 0 );
		writeSeen( storageKey, 0 );
	}, [ greeting, storageKey ] );

	const markSeen = useCallback( () => {
		const count = messages.filter( ( m ) => m.tone !== 'greeting' ).length;
		setSeenCount( count );
		writeSeen( storageKey, count );
	}, [ messages, storageKey ] );

	const realCount = messages.filter( ( m ) => m.tone !== 'greeting' ).length;
	const unreadCount = Math.max( 0, realCount - seenCount );

	return { messages, setMessages, append, reset, unreadCount, markSeen };
}
