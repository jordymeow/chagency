/**
 * Snackbar host. Consumes `@wordpress/notices`' `snackbar` notices and
 * renders them with the same black-pill visual the AI plugin and core
 * Connectors page use for save feedback.
 *
 * Anywhere in the app you can dispatch:
 *   createSuccessNotice( 'Settings saved.', { type: 'snackbar' } )
 *
 * and it'll show up here.
 *
 * @package
 */

import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

export default function Snackbars() {
	const snackbars = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( n ) => n.type === 'snackbar' ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<SnackbarList
			className="chagency-snackbars"
			notices={ snackbars }
			onRemove={ removeNotice }
		/>
	);
}
