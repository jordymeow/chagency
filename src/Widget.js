/**
 * Floating chat widget.
 *
 * Renders as a small round button in the bottom-right of every admin page
 * (when enabled). Clicking it opens a compact chat panel — compact enough
 * that bubbles are the right visual choice here, even though the full-page
 * app uses flat typography. Conversation history is shared with the
 * full-page app via `useConversation`.
 *
 * @package
 */

import { Button, TextareaControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import Markdown from 'markdown-to-jsx';

import { sendChat } from './rest';
import useConversation from './useConversation';

/**
 * Shared `markdown-to-jsx` options. `disableParsingRawHTML` prevents the
 * model from smuggling raw HTML/`<script>` tags into the chat. Links open
 * in a new tab with `noopener`.
 */
const MARKDOWN_OPTIONS = {
	disableParsingRawHTML: true,
	overrides: {
		a: {
			props: {
				target: '_blank',
				rel: 'noopener noreferrer',
			},
		},
	},
};

/**
 * Animated "typing" indicator — three dots pulsing in sequence. Replaces
 * the Gutenberg Spinner here because Spinner's default rendering is a bit
 * loud for a chat bubble and its ARIA label is localized per site.
 */
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

/**
 * Launcher — soft-gradient flush-to-bottom tab. Shows the green status dot,
 * "AI Chagency" label, and a red round badge when there are unread messages.
 *
 * @param {{ onOpen: () => void, unreadCount: number, busy: boolean }} props
 */
function Launcher( { onOpen, unreadCount, busy } ) {
	const hasUnread = unreadCount > 0;
	const className = [
		'chagency-widget-launcher',
		busy ? 'is-busy' : '',
		hasUnread ? 'has-unread' : '',
	]
		.filter( Boolean )
		.join( ' ' );
	return (
		<button
			type="button"
			className={ className }
			onClick={ onOpen }
			aria-label={
				hasUnread
					? __( 'Open chat — new messages', 'chagency' )
					: __( 'Open chat', 'chagency' )
			}
		>
			<span
				className="chagency-widget-launcher__dot"
				aria-hidden="true"
			/>
			<span className="chagency-widget-launcher__label">
				{ __( 'Chagency', 'chagency' ) }
			</span>
			{ hasUnread ? (
				<span
					className="chagency-widget-launcher__badge"
					aria-label={ `${ unreadCount }` }
				>
					{ unreadCount > 99 ? '99+' : unreadCount }
				</span>
			) : null }
		</button>
	);
}

function Bubble( { from, body, tone } ) {
	if ( tone === 'pending' ) {
		return (
			<div className="chagency-widget-bubble chagency-widget-bubble--assistant chagency-widget-bubble--pending">
				<TypingDots />
			</div>
		);
	}
	const isUser = from === 'user';
	const cls = [
		'chagency-widget-bubble',
		isUser
			? 'chagency-widget-bubble--user'
			: 'chagency-widget-bubble--assistant',
	];
	if ( tone === 'error' ) {
		cls.push( 'chagency-widget-bubble--error' );
	}
	// User input stays plain text (safer, matches what they typed). Assistant
	// replies are rendered as markdown — models tend to reach for it naturally.
	return (
		<div className={ cls.join( ' ' ) }>
			{ isUser ? (
				body
			) : (
				<Markdown options={ MARKDOWN_OPTIONS }>{ body || '' }</Markdown>
			) }
		</div>
	);
}

export default function Widget( { cfg } ) {
	// Local settings state mirrors the server, but updates live when the
	// Settings page dispatches `chagency:settings-changed`. This lets the
	// "Enable chatbot" toggle show / hide the launcher immediately.
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

	const enabled = !! settings.enabled;
	const greeting = settings.greeting || '';

	const { messages, append, reset, unreadCount, markSeen } = useConversation(
		{
			storageKey: cfg.storageKey,
			greeting,
		}
	);

	const [ open, setOpen ] = useState( false );
	const [ input, setInput ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const listRef = useRef( null );

	// Turning the chatbot off while the panel is open also closes the panel.
	useEffect( () => {
		if ( ! enabled && open ) {
			setOpen( false );
		}
	}, [ enabled, open ] );

	const { createErrorNotice } = useDispatch( noticesStore );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		const node = listRef.current;
		if ( node ) {
			node.scrollTop = node.scrollHeight;
		}
	}, [ messages, busy, open ] );

	// When the panel is open, mark messages as seen so the unread badge
	// clears. Also runs whenever new messages arrive while the panel is open.
	useEffect( () => {
		if ( open ) {
			markSeen();
		}
	}, [ open, messages, markSeen ] );

	const handleSend = useCallback( () => {
		const text = ( input || '' ).trim();
		if ( ! text || busy ) {
			return;
		}
		const userMsg = { role: 'user', content: text };
		const wire = messages
			.filter(
				( m ) =>
					m.tone !== 'greeting' &&
					( m.role === 'user' || m.role === 'assistant' )
			)
			.map( ( m ) => ( { role: m.role, content: m.content } ) );
		wire.push( userMsg );

		append( userMsg );
		setInput( '' );
		setBusy( true );

		sendChat( wire )
			.then( ( data ) => {
				const reply =
					data && typeof data.reply === 'string' ? data.reply : '';
				if ( ! reply ) {
					append( {
						role: 'assistant',
						content: __( '(empty reply)', 'chagency' ),
						tone: 'error',
					} );
					return;
				}
				append( { role: 'assistant', content: reply } );
			} )
			.catch( ( err ) => {
				const msg =
					( err && err.message ) ||
					__( 'Something went wrong.', 'chagency' );
				append( { role: 'assistant', content: msg, tone: 'error' } );
				createErrorNotice( msg, { type: 'snackbar' } );
			} )
			.finally( () => setBusy( false ) );
	}, [ input, messages, busy, append, createErrorNotice ] );

	const onKeyDown = ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			handleSend();
		}
	};

	const canSend = ! busy && !! ( input && input.trim() );
	const canReset =
		messages.filter( ( m ) => m.tone !== 'greeting' ).length > 0;

	// When the chatbot is disabled in Settings, render nothing.
	if ( ! enabled ) {
		return null;
	}

	return (
		<>
			{ ! open && (
				<Launcher
					onOpen={ () => setOpen( true ) }
					unreadCount={ unreadCount }
					busy={ busy }
				/>
			) }

			{ open && (
				<div
					className="chagency-widget-panel"
					role="dialog"
					aria-label={ __( 'Chagency', 'chagency' ) }
				>
					<header className="chagency-widget-panel__header">
						<span className="chagency-widget-panel__title">
							<span
								className="chagency-widget-launcher__dot is-idle"
								aria-hidden="true"
							/>
							{ __( 'Chagency', 'chagency' ) }
						</span>
						<div className="chagency-widget-panel__header-actions">
							{ canReset ? (
								<Button
									variant="tertiary"
									size="compact"
									onClick={ reset }
									disabled={ busy }
								>
									{ __( 'Reset', 'chagency' ) }
								</Button>
							) : null }
							<Button
								className="chagency-widget-panel__minimize"
								variant="tertiary"
								size="small"
								icon={
									<svg
										xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 20 20"
										width="18"
										height="18"
										aria-hidden="true"
									>
										<path
											fill="currentColor"
											d="M5 13h10v2H5z"
										/>
									</svg>
								}
								onClick={ () => setOpen( false ) }
								label={ __( 'Minimize', 'chagency' ) }
							/>
						</div>
					</header>

					<div
						className="chagency-widget-panel__body"
						ref={ listRef }
						aria-live="polite"
					>
						{ messages.map( ( m, i ) => (
							<Bubble
								key={ i }
								from={ m.role }
								body={ m.content }
								tone={ m.tone }
							/>
						) ) }
						{ busy ? (
							<Bubble
								from="assistant"
								body={ __( 'Thinking…', 'chagency' ) }
								tone="pending"
							/>
						) : null }
					</div>

					<div className="chagency-widget-panel__composer">
						<TextareaControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							className="chagency-widget-input"
							placeholder={ __(
								'Type your message…',
								'chagency'
							) }
							value={ input }
							onChange={ setInput }
							rows={ 1 }
							disabled={ busy }
							onKeyDown={ onKeyDown }
							hideLabelFromVision
							label={ __( 'Your message', 'chagency' ) }
						/>
						<Button
							__next40pxDefaultSize
							variant="primary"
							onClick={ handleSend }
							disabled={ ! canSend }
							isBusy={ busy }
						>
							{ __( 'Send', 'chagency' ) }
						</Button>
					</div>
				</div>
			) }
		</>
	);
}
