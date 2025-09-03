/* global wphbReact */
/* global WPHB_Admin */

/**
 * External dependencies
 */
import React, { useState } from 'react';
import ReactDOM from 'react-dom';
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { dispatch } from '@wordpress/data';
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import HBAPIFetch from '../api';
import AutoAssets from '../views/minify/assets-auto';
import { ManualAssets } from '../views/minify/assets-manual';
import { MinifySummary } from '../views/minify/summary';
import ClearCacheButton from '../views/minify/clear-cache-button';
import { STORE_NAME } from '../data/minify';

/**
 * MinifyPage component.
 *
 * @since 2.7.2
 *
 * @param {Object} props
 * @return {JSX.Element} Manual or Auto assets component.
 */
export const MinifyPage = ( props ) => {
	const api = new HBAPIFetch();
	const [ loading, setLoading ] = useState( true );

	/**
	 * Re-check files.
	 */
	const reCheckFiles = () => {
		setLoading( true );

		api.post( 'minify_recheck_files' )
			.then( () => window.location.reload() )
			.catch( window.console.log );
	};

	if ( 'advanced' === props.wphbData.mode ) {
		return (
			<ManualAssets
				loading={ loading }
				api={ api }
				mode={ props.wphbData.mode }
				reCheckFiles={ reCheckFiles }
				showModal={ props.wphbData.showModal }
				filters={ props.wphbData.filters }
				links={ props.wphbData.links }
				isMember={ Boolean( props.wphbData.isMember ) } />
		);
	}

	return (
		<AutoAssets
			loading={ loading }
			api={ api }
			mode={ props.wphbData.mode }
			reCheckFiles={ reCheckFiles }
			showModal={ props.wphbData.showModal } />
	);
};

MinifyPage.propTypes = {
	wphbData: PropTypes.object,
};

domReady( function() {
	const minify = document.getElementById( 'wrap-wphb-minify' );
	if ( minify ) {
		ReactDOM.render( <MinifyPage wphbData={ wphbReact } />, minify );
	}

	const summary = document.getElementById( 'wrap-wphb-summary' );
	if ( summary ) {
		ReactDOM.render( <MinifySummary wphbData={ wphbReact } />, summary );
	}

	const clearAO = document.getElementById( 'wrap-wphb-clear-ao-files' );
	if ( clearAO ) {
		ReactDOM.render( <ClearCacheButton />, clearAO );
	}
} );
