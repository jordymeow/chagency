/**
 * Settings → Chagency. Two cards: Chatbot (toggles + behaviour) and
 * Providers (status + test).
 *
 * @package
 */

import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	/* eslint-disable @wordpress/no-unsafe-wp-apis -- Same layout primitives used by core's Connectors screen. */
	__experimentalConfirmDialog as ConfirmDialog,
	__experimentalHStack as HStack,
	__experimentalHeading as Heading,
	__experimentalText as Text,
	__experimentalVStack as VStack,
	/* eslint-enable @wordpress/no-unsafe-wp-apis */
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import {
	createInterpolateElement,
	useCallback,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { Page } from '@wordpress/admin-ui';

import { resetSettings, saveSettings, testProvider } from './rest';
import Snackbars from './Snackbars';

function ProviderRow( { connector, notice } ) {
	const [ busy, setBusy ] = useState( false );

	const runTest = useCallback( () => {
		setBusy( true );
		testProvider( connector.id )
			.then( ( data ) => {
				const snippet =
					data && data.reply
						? String( data.reply ).trim().slice( 0, 60 )
						: '';
				const ms = data && data.ms ? ` (${ data.ms }ms)` : '';
				notice.success(
					`${ connector.name }: ${
						snippet || __( 'Responded', 'chagency' )
					}${ ms }`
				);
			} )
			.catch( ( err ) => {
				notice.error(
					`${ connector.name }: ${
						( err && err.message ) || __( 'Failed', 'chagency' )
					}`
				);
			} )
			.finally( () => setBusy( false ) );
	}, [ connector, notice ] );

	return (
		<HStack
			alignment="center"
			spacing={ 3 }
			className="chagency-provider-row"
		>
			<div className="chagency-provider-row__name">
				<Text weight="600">{ connector.name }</Text>
			</div>
			<span
				className={
					connector.isConfigured
						? 'chagency-badge chagency-badge--ok'
						: 'chagency-badge chagency-badge--warn'
				}
			>
				{ connector.isConfigured
					? __( 'Configured', 'chagency' )
					: __( 'Not configured', 'chagency' ) }
			</span>
			<Button
				variant="secondary"
				size="compact"
				onClick={ runTest }
				disabled={ ! connector.isConfigured || busy }
				isBusy={ busy }
			>
				{ __( 'Test', 'chagency' ) }
			</Button>
		</HStack>
	);
}

function PlaceholderHelp( { placeholders } ) {
	const entries = Object.entries( placeholders || {} );
	if ( entries.length === 0 ) {
		return null;
	}
	return (
		<details className="chagency-placeholders">
			<summary>{ __( 'Available placeholders', 'chagency' ) }</summary>
			<ul>
				{ entries.map( ( [ token, descr ] ) => (
					<li key={ token }>
						<code>{ token }</code>, { descr }
					</li>
				) ) }
			</ul>
		</details>
	);
}

export default function Settings( { cfg } ) {
	const initial = cfg.settings || {
		admin_enabled: true,
		frontend_enabled: false,
		chat_title: '',
		system_instruction: '',
		greeting: '',
		model_preference: 'auto',
	};
	const connectors = Array.isArray( cfg.connectors ) ? cfg.connectors : [];
	const placeholders = cfg.placeholders || {};
	const hasCredentials = !! cfg.hasCredentials;
	const connectorsUrl = cfg.connectorsUrl || '';

	const [ form, setForm ] = useState( initial );
	const [ saving, setSaving ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ dirty, setDirty ] = useState( false );
	const [ confirmOpen, setConfirmOpen ] = useState( false );

	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );
	const snackbar = {
		success: ( msg ) => createSuccessNotice( msg, { type: 'snackbar' } ),
		error: ( msg ) => createErrorNotice( msg, { type: 'snackbar' } ),
	};

	const update = ( patch ) => {
		setForm( ( prev ) => ( { ...prev, ...patch } ) );
		setDirty( true );
	};

	// Lets the live admin widget pick up enable/disable + greeting changes
	// without a page reload.
	const broadcast = ( payload ) => {
		try {
			window.dispatchEvent(
				new CustomEvent( 'chagency:settings-changed', {
					detail: payload,
				} )
			);
		} catch ( _ ) {
			/* noop */
		}
	};

	const save = useCallback( () => {
		setSaving( true );
		saveSettings( form )
			.then( ( saved ) => {
				setForm( saved );
				setDirty( false );
				broadcast( saved );
				snackbar.success( __( 'Settings saved.', 'chagency' ) );
			} )
			.catch( ( err ) => {
				snackbar.error(
					( err && err.message ) ||
						__( 'Could not save settings.', 'chagency' )
				);
			} )
			.finally( () => setSaving( false ) );
	}, [ form ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const handleReset = useCallback( () => {
		setResetting( true );
		resetSettings()
			.then( ( fresh ) => {
				setForm( fresh );
				setDirty( false );
				broadcast( fresh );
				snackbar.success(
					__( 'Settings reset to defaults.', 'chagency' )
				);
			} )
			.catch( ( err ) => {
				snackbar.error(
					( err && err.message ) ||
						__( 'Could not reset settings.', 'chagency' )
				);
			} )
			.finally( () => {
				setResetting( false );
				setConfirmOpen( false );
			} );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const modelOptions = [
		{
			label: __( 'Automatic (use best available)', 'chagency' ),
			value: 'auto',
		},
		...connectors.map( ( c ) => ( { label: c.name, value: c.id } ) ),
	];

	return (
		<Page
			title={ __( 'Chagency', 'chagency' ) }
			subTitle={ __(
				'The first chatbot built natively on the WordPress 7 AI infrastructure.',
				'chagency'
			) }
		>
			<div className="chagency-settings-column">
				<VStack spacing={ 5 }>
					{ ! hasCredentials && (
						<Notice status="warning" isDismissible={ false }>
							{ createInterpolateElement(
								__(
									'No AI provider is configured yet. Add an API key under <a>Settings → Connectors</a>, the chatbot stays hidden until then.',
									'chagency'
								),
								{
									a: connectorsUrl ? (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a href={ connectorsUrl } />
									) : (
										<span />
									),
								}
							) }
						</Notice>
					) }

					<Card size="small">
						<CardHeader>
							<Heading level={ 3 }>
								{ __( 'Chatbot', 'chagency' ) }
							</Heading>
						</CardHeader>
						<CardBody>
							<VStack spacing={ 5 }>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Show in the WordPress admin',
										'chagency'
									) }
									help={ __(
										'A floating chat button appears for users who can manage options.',
										'chagency'
									) }
									checked={ !! form.admin_enabled }
									onChange={ ( admin_enabled ) =>
										update( { admin_enabled } )
									}
								/>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Show on the public site',
										'chagency'
									) }
									help={ __(
										'A floating chat button appears on every page of the site, available to every visitor.',
										'chagency'
									) }
									checked={ !! form.frontend_enabled }
									onChange={ ( frontend_enabled ) =>
										update( { frontend_enabled } )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Chat title', 'chagency' ) }
									help={ __(
										'Shown in the panel header and on the launcher tooltip.',
										'chagency'
									) }
									value={ form.chat_title || '' }
									onChange={ ( chat_title ) =>
										update( { chat_title } )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Greeting', 'chagency' ) }
									help={ __(
										'Shown as the first message of every fresh conversation.',
										'chagency'
									) }
									value={ form.greeting || '' }
									onChange={ ( greeting ) =>
										update( { greeting } )
									}
								/>
								<TextareaControl
									__nextHasNoMarginBottom
									label={ __(
										'System instruction',
										'chagency'
									) }
									help={ __(
										'Shapes how the assistant responds. Placeholders are expanded server-side.',
										'chagency'
									) }
									rows={ 6 }
									value={ form.system_instruction || '' }
									onChange={ ( value ) =>
										update( { system_instruction: value } )
									}
								/>
								<PlaceholderHelp
									placeholders={ placeholders }
								/>
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Model', 'chagency' ) }
									help={ __(
										'Pin to a specific provider, or leave on Automatic.',
										'chagency'
									) }
									options={ modelOptions }
									value={ form.model_preference || 'auto' }
									onChange={ ( value ) =>
										update( { model_preference: value } )
									}
								/>
							</VStack>
						</CardBody>
						<CardFooter>
							<HStack justify="space-between">
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => setConfirmOpen( true ) }
									disabled={ saving || resetting }
								>
									{ __( 'Reset to defaults', 'chagency' ) }
								</Button>
								<Button
									variant="primary"
									onClick={ save }
									isBusy={ saving }
									disabled={
										saving || resetting || ! dirty
									}
								>
									{ saving
										? __( 'Saving…', 'chagency' )
										: __( 'Save changes', 'chagency' ) }
								</Button>
							</HStack>
						</CardFooter>
					</Card>

					<Card size="small">
						<CardHeader>
							<VStack spacing={ 1 }>
								<Heading level={ 3 }>
									{ __( 'Providers', 'chagency' ) }
								</Heading>
								<Text variant="muted" size="13px">
									{ __( 'Manage API keys under', 'chagency' ) }{ ' ' }
									<a href={ connectorsUrl }>
										{ __(
											'Settings → Connectors',
											'chagency'
										) }
									</a>
									.
								</Text>
							</VStack>
						</CardHeader>
						<CardBody>
							{ connectors.length === 0 ? (
								<Text variant="muted">
									{ __(
										'No AI providers registered.',
										'chagency'
									) }
								</Text>
							) : (
								<VStack
									spacing={ 3 }
									className="chagency-providers"
								>
									{ connectors.map( ( c ) => (
										<ProviderRow
											key={ c.id }
											connector={ c }
											notice={ snackbar }
										/>
									) ) }
								</VStack>
							) }
						</CardBody>
					</Card>
				</VStack>

				<ConfirmDialog
					isOpen={ confirmOpen }
					onConfirm={ handleReset }
					onCancel={ () => setConfirmOpen( false ) }
					confirmButtonText={ __( 'Reset', 'chagency' ) }
				>
					{ __(
						'Reset all settings to their defaults? Your conversation history is not touched.',
						'chagency'
					) }
				</ConfirmDialog>
				<Snackbars />
			</div>
		</Page>
	);
}
