/* global WPHB_Admin, wphbReact */

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
// eslint-disable-next-line import/default
import ButtonLoading from '../../components/sui-button-loading';
// eslint-disable-next-line import/default
import Tooltip from '../../components/sui-tooltip';

// ===================================
// Re-Check Files Button View Component
// ===================================

/**
 * RecheckFilesButton component.
 *
 * This component provides a standalone re-check files button with tooltip
 * that can be rendered independently to any DOM element.
 *
 * @since 3.16.0
 */
export default class RecheckFilesButton extends Component {
	/**
	 * Component constructor.
	 *
	 * @param {Object} props Component props.
	 */
	constructor( props ) {
		super( props );
		this.state = {
			loading: false,
			backgroundProcessing: false,
		};

		// Bind methods
		this.handleAOScan = this.handleAOScan.bind( this );
		this.checkBackgroundProcessing = this.checkBackgroundProcessing.bind( this );

		// Initialize API instance once
		this.api = new HBAPIFetch();

		// Polling configuration
		this.pollingInterval = 5000; // 5 seconds
		this.intervalId = null;
	}

	/**
	 * Component lifecycle method - runs after component mounts.
	 */
	componentDidMount() {
		this.startBackgroundProcessingMonitoring();
	}

	/**
	 * Component lifecycle method - cleanup when component unmounts.
	 */
	componentWillUnmount() {
		this.stopBackgroundProcessingMonitoring();
	}

	/**
	 * Start monitoring background processing status.
	 */
	startBackgroundProcessingMonitoring() {
		// Initial check
		this.checkBackgroundProcessing();

		// Start polling if not already active
		if ( ! this.intervalId ) {
			this.intervalId = setInterval( this.checkBackgroundProcessing, this.pollingInterval );
		}
	}

	/**
	 * Stop monitoring background processing status.
	 */
	stopBackgroundProcessingMonitoring() {
		if ( this.intervalId ) {
			clearInterval( this.intervalId );
			this.intervalId = null;
		}
	}

	/**
	 * Check if background processing is currently running.
	 */
	checkBackgroundProcessing() {
		this.api.get( 'minify_background_processing_status' )
			.then( ( response ) => {
				const isProcessing = response?.isAoScanProcessing || false;
				this.setState( { backgroundProcessing: isProcessing } );

				// Stop polling when processing completes
				if ( ! isProcessing ) {
					this.stopBackgroundProcessingMonitoring();
				}

				if ( response?.notice && response.notice !== '' ) {
					// eslint-disable-next-line camelcase
					WPHB_Admin.notices.show( response.notice, 'success', false );
				}
			} )
			.catch( () => {
				// If API call fails, assume no background processing and stop polling
				this.setState( { backgroundProcessing: false } );

				this.stopBackgroundProcessingMonitoring();
			} );
	}

	/**
	 * Default re-check files handler.
	 */
	handleAOScan() {
		// Don't allow starting if background processing is running
		if ( this.state.backgroundProcessing ) {
			return;
		}

		this.setState( { loading: true } );

		this.api.post( 'minify_start_ao_scan' )
			.then( ( response ) => {
				// Invalidate cache
				dispatch( STORE_NAME ).invalidateResolution( 'getAssets' );
				dispatch( STORE_NAME ).invalidateResolution( 'getOptions' );

				this.setState( { loading: false } );

				// Start monitoring background processing
				this.startBackgroundProcessingMonitoring();

				// Show success notice
				if ( response?.notice && response.notice !== '' ) {
					// eslint-disable-next-line camelcase
					WPHB_Admin.notices.show( response.notice, 'blue', false );
				}
			} )
			.catch( ( error ) => {
				window.console.log( error );
				this.setState( { loading: false } );

				// Show error notice
				const errorMessage = __( 'Error while starting AO scan. Please try again.', 'wphb' );
				// eslint-disable-next-line camelcase
				WPHB_Admin.notices.show( errorMessage, 'error', false );
			} );
	}

	/**
	 * Render component.
	 */
	render() {
		const {
			text = __( 'Re-Check Files', 'wphb' ),
			classes = [ 'sui-button', 'sui-button-ghost' ],
			icon = 'sui-icon-update',
			tooltipText = __( 'Added/removed plugins or themes? Update your file list to include new files, and remove old ones.', 'wphb' ),
			showTooltip = true,
			onClick,
			...otherProps
		} = this.props;

		const { loading, backgroundProcessing } = this.state;
		const isDisabled = backgroundProcessing || loading;

		// Determine button text and tooltip based on state
		const buttonText = backgroundProcessing ? __( 'Scanning...', 'wphb' ) : text;
		const loadingText = backgroundProcessing ? __( 'Scanning...', 'wphb' ) : __( 'Starting Scan', 'wphb' );
		const currentTooltipText = backgroundProcessing
			? __( 'Background processing is currently running. Please wait for it to complete before re-checking files.', 'wphb' )
			: tooltipText;

		// Use custom onClick handler if provided, otherwise use default
		const handleClick = onClick || this.handleAOScan;

		// Button classes
		const buttonClasses = isDisabled
			? [ ...classes, 'sui-button-disabled' ]
			: classes;

		const button = (
			<ButtonLoading
				text={ buttonText }
				loadingText={ loadingText }
				classes={ buttonClasses }
				icon={ icon }
				onClick={ isDisabled ? null : handleClick }
				loading={ loading || backgroundProcessing }
				disabled={ isDisabled }
				{ ...otherProps }
			/>
		);

		// Conditionally wrap with tooltip
		return showTooltip ? (
			<Tooltip
				classes="sui-tooltip sui-tooltip-constrained"
				text={ currentTooltipText }
			>
				{ button }
			</Tooltip>
		) : button;
	}
}
