/* global WPHB_Admin */

/**
 * External dependencies
 */
import React, { Component } from 'react';

/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import HBAPIFetch from '../../api';
import { STORE_NAME } from '../../data/minify';
import ButtonLoading from '../../components/sui-button-loading';
import Tooltip from '../../components/sui-tooltip';

// ===================================
// Clear Cache Button View Component
// ===================================

/**
 * ClearCacheButton component.
 *
 * This component provides a standalone clear cache button with tooltip
 * that can be rendered independently to any DOM element.
 *
 * @since 3.16.0
 */
export default class ClearCacheButton extends Component {
	/**
	 * Component constructor.
	 *
	 * @param {Object} props Component props.
	 */
	constructor( props ) {
		super( props );
		this.state = {
			loading: false,
		};

		this.handleClearCache = this.handleClearCache.bind( this );
	}

	/**
	 * Default clear cache handler.
	 */
	handleClearCache() {
		this.setState( { loading: true } );
		const api = new HBAPIFetch();

		api.post( 'minify_clear_cache' )
			.then( ( response ) => {
				dispatch( STORE_NAME ).invalidateResolution( 'getAssets' );
				dispatch( STORE_NAME ).invalidateResolution( 'getOptions' );
				this.setState( { loading: false } );
				if ( response.isCriticalActive ) {
					window.wphbMixPanel.track( 'critical_css_cache_purge', {
						location: 'ao_settings'
					} );
				}
				const message = __( 'Your cache has been successfully cleared. Your assets will regenerate the next time someone visits your website.', 'wphb' );
				WPHB_Admin.notices.show( message ); // eslint-disable-line camelcase
			} )
			.catch( ( error ) => {
				window.console.log( error );
				this.setState( { loading: false } );
			} );
	}

	/**
	 * Render component.
	 */
	render() {
		return (
			<Tooltip
				classes="sui-tooltip-constrained sui-tooltip-bottom-right"
				text={ __( 'Clears all local or hosted assets and recompresses files that need it', 'wphb' ) }
			>
				<ButtonLoading
					text={ __( 'Clear cache', 'wphb' ) }
					loadingText={ __( 'Clearing cache', 'wphb' ) }
					classes={ [ 'sui-button' ] }
					onClick={ this.handleClearCache }
					loading={ this.state.loading }
				/>
			</Tooltip>
		);
	}
}
