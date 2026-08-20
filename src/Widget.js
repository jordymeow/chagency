/**
 * Floating chat widget, works on both the WordPress admin and the public
 * site. The PHP loader passes `cfg.surface` ("admin" | "frontend") and the
 * widget reads the matching `enabled` flag from the settings.
 *
 * @package
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Markdown from 'markdown-to-jsx';

import { sendChat } from './rest';
import useConversation from './useConversation';

/**
 * Markdown options for assistant replies. `disableParsingRawHTML` blocks
 * the model from smuggling raw HTML / `<script>` tags into the chat.
 */
const MARKDOWN_OPTIONS = {
	disableParsingRawHTML: true,
	overrides: {
		a: {
			props: { target: '_blank', rel: 'noopener noreferrer' },
		},
	},
};

function TypingDots() {
	return (
		<span
			className="chagency-typing"
			aria-label={ __( 'Assistant is typing', 'chagency' ) }
		>
			<span className="chagency-typing__dot" />
			<span className="chagency-typing__dot" />
			<span className="chagency-typing__dot" />
		</span>
	);
}

function ChatIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="22"
			height="22"
			aria-hidden="true"
		>
			<path
				fill="currentColor"
				d="M12 3C6.48 3 2 6.86 2 11.6c0 2.5 1.27 4.74 3.32 6.32V21l3.6-2.04c.99.21 2.02.32 3.08.32 5.52 0 10-3.86 10-8.6S17.52 3 12 3z"
			/>
		</svg>
	);
}

function CloseIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 20 20"
			width="18"
			height="18"
			aria-hidden="true"
		>
			<path
				fill="currentColor"
				d="M14.7 5.3a1 1 0 0 0-1.4 0L10 8.59 6.7 5.3A1 1 0 0 0 5.3 6.7L8.59 10 5.3 13.3a1 1 0 1 0 1.4 1.4L10 11.41l3.3 3.29a1 1 0 0 0 1.4-1.4L11.41 10l3.29-3.3a1 1 0 0 0 0-1.4z"
			/>
		</svg>
	);
}

function SendIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="18"
			height="18"
			aria-hidden="true"
		>
			<path
				fill="currentColor"
				d="M3.4 20.4 21 12 3.4 3.6c-.7-.3-1.5.4-1.3 1.2L4 11l11 1-11 1-1.9 6.2c-.2.8.6 1.5 1.3 1.2z"
			/>
		</svg>
	);
}

function Launcher( { onOpen, unreadCount, label } ) {
	const hasUnread = unreadCount > 0;
	const aria = hasUnread
		? __( 'Open chat, new messages', 'chagency' )
		: label || __( 'Open chat', 'chagency' );
	return (
		<button
			type="button"
			className={
				hasUnread ? 'chagency-launcher has-unread' : 'chagency-launcher'
			}
			onClick={ onOpen }
			title={ label || __( 'Open chat', 'chagency' ) }
			aria-label={ aria }
		>
			<ChatIcon />
			{ hasUnread ? (
				<span
					className="chagency-launcher__badge"
					aria-label={ `${ unreadCount }` }
				>
					{ unreadCount > 9 ? '9+' : unreadCount }
				</span>
			) : null }
		</button>
	);
}

function Steps( { steps } ) {
	if ( ! Array.isArray( steps ) || steps.length === 0 ) {
		return null;
	}
	return (
		<div className="chagency-steps">
			{ steps.map( ( step, i ) => (
				<span
					key={ `${ step.ability }-${ i }` }
					className={
						step.ok
							? 'chagency-step'
							: 'chagency-step chagency-step--failed'
					}
				>
					{ step.ability }
				</span>
			) ) }
		</div>
	);
}

function Bubble( { from, body, tone, steps, detail, onRetry } ) {
	if ( tone === 'pending' ) {
		return (
			<div className="chagency-bubble chagency-bubble--assistant chagency-bubble--pending">
				<TypingDots />
			</div>
		);
	}
	const isUser = from === 'user';
	const cls = [
		'chagency-bubble',
		isUser ? 'chagency-bubble--user' : 'chagency-bubble--assistant',
	];
	if ( tone === 'error' ) {
		cls.push( 'chagency-bubble--error' );
	}
	return (
		<div className={ cls.join( ' ' ) }>
			{ isUser ? (
				body
			) : (
				<Markdown options={ MARKDOWN_OPTIONS }>{ body || '' }</Markdown>
			) }
			<Steps steps={ steps } />
			{ detail ? (
				<details className="chagency-detail">
					<summary>{ __( 'Details', 'chagency' ) }</summary>
					<p>{ detail }</p>
				</details>
			) : null }
			{ tone === 'error' && onRetry ? (
				<button
					type="button"
					className="chagency-retry"
					onClick={ onRetry }
				>
					{ __( 'Try again', 'chagency' ) }
				</button>
			) : null }
		</div>
	);
}

// sessionStorage key used to keep the panel open across admin navigations.
const OPEN_STATE_KEY = 'chagency:panel-open';

function readOpenState( surface ) {
	if ( typeof window === 'undefined' || ! window.sessionStorage ) {
		return false;
	}
	try {
		return (
			window.sessionStorage.getItem( OPEN_STATE_KEY + ':' + surface ) ===
			'1'
		);
	} catch ( _ ) {
		return false;
	}
}

function writeOpenState( surface, isOpen ) {
	if ( typeof window === 'undefined' || ! window.sessionStorage ) {
		return;
	}
	try {
		window.sessionStorage.setItem(
			OPEN_STATE_KEY + ':' + surface,
			isOpen ? '1' : '0'
		);
	} catch ( _ ) {
		/* noop */
	}
}

export default function Widget( { cfg } ) {
	const surface = cfg.surface === 'frontend' ? 'frontend' : 'admin';
	const enabledKey =
		surface === 'frontend' ? 'frontend_enabled' : 'admin_enabled';

	// Local mirror of the server-side settings, updated live when Settings
	// dispatches `chagency:settings-changed`.
	const [ settings, setSettings ] = useState( cfg.settings || {} );

	useEffect( () => {
		const handler = ( e ) => {
			if ( e && e.detail && typeof e.detail === 'object' ) {
				setSettings( e.detail );
			}
		};
		window.addEventListener( 'chagency:settings-changed', handler );
		return () =>
			window.removeEventListener( 'chagency:settings-changed', handler );
	}, [] );

	const enabled = !! settings[ enabledKey ];
	const greeting = settings.greeting || '';

	const { messages, append, dropLast, reset, unreadCount, markSeen } =
		useConversation( {
			storageKey: cfg.storageKey,
			greeting,
		} );

	// Panel open state persists across admin navigations via sessionStorage.
	const [ open, setOpenState ] = useState( () => readOpenState( surface ) );
	const setOpen = useCallback(
		( next ) => {
			setOpenState( next );
			writeOpenState( surface, next );
		},
		[ surface ]
	);

	const [ input, setInput ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const listRef = useRef( null );
	const inputRef = useRef( null );

	useEffect( () => {
		if ( ! enabled && open ) {
			setOpen( false );
		}
	}, [ enabled, open, setOpen ] );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		const node = listRef.current;
		if ( node ) {
			node.scrollTop = node.scrollHeight;
		}
	}, [ messages, busy, open ] );

	useEffect( () => {
		if ( open && inputRef.current ) {
			// Tiny delay so the panel has finished its entry animation.
			const id = setTimeout( () => {
				try {
					inputRef.current.focus();
				} catch ( _ ) {
					/* noop */
				}
			}, 80 );
			return () => clearTimeout( id );
		}
		return undefined;
	}, [ open ] );

	useEffect( () => {
		if ( open ) {
			markSeen();
		}
	}, [ open, messages, markSeen ] );

	// Escape closes the panel.
	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const handler = ( e ) => {
			if ( e.key === 'Escape' ) {
				setOpen( false );
			}
		};
		window.addEventListener( 'keydown', handler );
		return () => window.removeEventListener( 'keydown', handler );
	}, [ open, setOpen ] );

	// Auto-grow textarea up to its CSS max-height.
	useEffect( () => {
		const el = inputRef.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, 120 ) + 'px';
	}, [ input ] );

	// The greeting is local-only, and an error bubble is our own text, not
	// something the assistant said. Neither belongs in what we replay.
	const toWire = useCallback(
		( history ) =>
			history
				.filter(
					( m ) =>
						m.tone !== 'greeting' &&
						m.tone !== 'error' &&
						( m.role === 'user' || m.role === 'assistant' )
				)
				.map( ( m ) => ( { role: m.role, content: m.content } ) ),
		[]
	);

	const send = useCallback(
		( wire ) => {
			setBusy( true );
			return sendChat( wire )
				.then( ( data ) => {
					const reply =
						data && typeof data.reply === 'string'
							? data.reply
							: '';
					if ( ! reply ) {
						append( {
							role: 'assistant',
							content: __(
								'The model returned an empty answer. Try rephrasing.',
								'chagency'
							),
							tone: 'error',
						} );
						return;
					}
					append( {
						role: 'assistant',
						content: reply,
						steps: Array.isArray( data.steps )
							? data.steps
							: undefined,
					} );
				} )
				.catch( ( err ) => {
					const msg =
						( err && err.message ) ||
						__( 'Something went wrong.', 'chagency' );
					// The provider's own wording, kept for admins only: it is
					// the useful half when debugging a key or a model name.
					const detail =
						surface === 'admin' &&
						err &&
						err.data &&
						err.data.data &&
						err.data.data.detail
							? String( err.data.data.detail )
							: undefined;
					append( {
						role: 'assistant',
						content: msg,
						tone: 'error',
						detail,
					} );
				} )
				.finally( () => setBusy( false ) );
		},
		[ append, surface ]
	);

	const handleSend = useCallback( () => {
		const text = ( input || '' ).trim();
		if ( ! text || busy ) {
			return;
		}
		const userMsg = { role: 'user', content: text };
		const wire = toWire( messages );
		wire.push( userMsg );

		append( userMsg );
		setInput( '' );
		send( wire );
	}, [ input, messages, busy, append, send, toWire ] );

	// Retry drops the error bubble and replays the conversation as it stood
	// before the failure, so the user never retypes anything.
	const handleRetry = useCallback( () => {
		if ( busy ) {
			return;
		}
		const history = messages.slice( 0, -1 );
		const wire = toWire( history );
		if ( wire.length === 0 || wire[ wire.length - 1 ].role !== 'user' ) {
			return;
		}
		dropLast();
		send( wire );
	}, [ messages, busy, dropLast, send, toWire ] );

	const onKeyDown = ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			handleSend();
		}
	};

	const canSend = ! busy && !! ( input && input.trim() );
	const canReset =
		messages.filter( ( m ) => m.tone !== 'greeting' ).length > 0;

	if ( ! enabled ) {
		return null;
	}

	const chatTitle = (
		settings.chat_title || __( 'Assistant', 'chagency' )
	).trim();
	const rootClass = `chagency chagency--${ surface }`;

	return (
		<div className={ rootClass }>
			{ ! open && (
				<Launcher
					onOpen={ () => setOpen( true ) }
					unreadCount={ unreadCount }
					label={ chatTitle }
				/>
			) }

			{ open && (
				<div
					className="chagency-panel"
					role="dialog"
					aria-label={ chatTitle }
				>
					<header className="chagency-panel__header">
						<span className="chagency-panel__title">
							<span
								className="chagency-panel__dot"
								aria-hidden="true"
							/>
							{ chatTitle }
						</span>
						<div className="chagency-panel__actions">
							{ canReset ? (
								<button
									type="button"
									className="chagency-panel__action chagency-panel__action--text"
									onClick={ reset }
									disabled={ busy }
								>
									{ __( 'Reset', 'chagency' ) }
								</button>
							) : null }
							<button
								type="button"
								className="chagency-panel__action"
								onClick={ () => setOpen( false ) }
								aria-label={ __( 'Close', 'chagency' ) }
							>
								<CloseIcon />
							</button>
						</div>
					</header>

					<div
						className="chagency-panel__body"
						ref={ listRef }
						aria-live="polite"
					>
						{ messages.map( ( m, i ) => (
							<Bubble
								key={ i }
								from={ m.role }
								body={ m.content }
								tone={ m.tone }
								steps={ m.steps }
								detail={ m.detail }
								onRetry={
									i === messages.length - 1
										? handleRetry
										: undefined
								}
							/>
						) ) }
						{ busy ? (
							<Bubble from="assistant" body="" tone="pending" />
						) : null }
					</div>

					<form
						className="chagency-panel__composer"
						onSubmit={ ( e ) => {
							e.preventDefault();
							handleSend();
						} }
					>
						<textarea
							ref={ inputRef }
							className="chagency-input"
							placeholder={ __( 'Type a message…', 'chagency' ) }
							value={ input }
							onChange={ ( e ) => setInput( e.target.value ) }
							rows={ 1 }
							disabled={ busy }
							onKeyDown={ onKeyDown }
							aria-label={ __( 'Your message', 'chagency' ) }
						/>
						<button
							type="submit"
							className="chagency-send"
							disabled={ ! canSend }
							aria-label={ __( 'Send', 'chagency' ) }
						>
							<SendIcon />
						</button>
					</form>
				</div>
			) }
		</div>
	);
}
