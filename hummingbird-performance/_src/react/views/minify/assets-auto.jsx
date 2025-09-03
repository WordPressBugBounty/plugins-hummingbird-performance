/* global WPHB_Admin */

/**
 * External dependencies
 */
import React from 'react';

/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import Assets from './assets';
import Configurations from './configurations';
import Toggle from '../../components/sui-toggle';
import SettingsRow from '../../components/sui-box-settings/row';
import RecheckFilesButton from './recheck-files-button';
/**
 * AutoAssets component.
 *
 * @since 3.3.0
 */
export default class AutoAssets extends React.Component {
	/**
	 * Component constructor.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		super( props );

		this.state = {
			loading: this.props.loading,
			compress: true,
			combine: true,
			assets: {
				styles: {},
				scripts: {},
			},
			enabled: {
				styles: true,
				scripts: true,
				fonts: true,
			},
			exclusions: {
				styles: [],
				scripts: [],
			},
		};

		this.resetSettings = this.resetSettings.bind( this );
		this.handleToggleChange = this.handleToggleChange.bind( this );
		this.updateCheckBox = this.updateCheckBox.bind( this );
		this.updateExclusions = this.updateExclusions.bind( this );
		this.saveSettings = this.saveSettings.bind( this );
	}

	/**
	 * Invoked immediately after a component is mounted.
	 */
	componentDidMount() {
		this.props.api
			.post( 'minify_auto_status' )
			.then( ( response ) => {
				// If combine is enabled, also enable compress
				const compress = response.combine ? true : response.compress;

				this.setState( {
					loading: false,
					compress,
					combine: response.combine,
					assets: response.assets,
					enabled: response.enabled,
					exclusions: response.exclusions,
				} );
			} )
			.catch( ( error ) => window.console.log( error ) );
	}

	/**
	 * Reset asset optimization settings.
	 */
	resetSettings() {
		this.setState( { loading: true } );

		this.props.api
			.post( 'minify_reset_settings' )
			.then( () => {
				WPHB_Admin.notices.show(
					__( 'Settings restored to defaults', 'wphb' )
				);
				this.setState( {
					loading: false,
					compress: true,
					combine: true,
					enabled: {
						styles: true,
						scripts: true,
						fonts: true,
					},
					exclusions: {
						styles: [],
						scripts: [],
					},
				} );
			} )
			.catch( ( error ) => window.console.log( error ) );
	}

	/**
	 * Handle toggle click (Combine/Compress).
	 *
	 * @param {Object} e Event.
	 */
	handleToggleChange( e ) {
		const isCompressToggle = 'compress' === e.target.dataset.type;
		const compress = isCompressToggle ? ! this.state.compress : this.state.compress;
		const combine = isCompressToggle ? this.state.combine : ! this.state.combine;

		// If combine is being turned on, also turn on compress.
		const shouldForceCompress = ( ! isCompressToggle && combine ) ? true : compress;

		this.setState( { compress: shouldForceCompress, combine } );
	}

	/**
	 * Update files checkbox states.
	 *
	 * @param {Object} e
	 */
	updateCheckBox( e ) {
		if ( 'undefined' === e.target.id ) {
			return;
		}

		const enabled = {
			styles: this.state.enabled.styles,
			scripts: this.state.enabled.scripts,
			fonts: this.state.enabled.fonts,
		};

		if ( 'wphb-auto-css' === e.target.id ) {
			enabled.styles = e.target.checked;
		}

		if ( 'wphb-auto-js' === e.target.id ) {
			enabled.scripts = e.target.checked;
		}

		if ( 'wphb-auto-fonts' === e.target.id ) {
			enabled.fonts = e.target.checked;
		}

		this.setState( { enabled } );
	}

	/**
	 * Update exclusions list.
	 *
	 * @param {Object} e
	 */
	updateExclusions( e ) {
		if ( ! e.target.value ) {
			return;
		}

		const selected = jQuery( '#wphb-auto-exclude' ).find( ':selected' );

		const exclusions = { styles: [], scripts: [] };

		for ( let i = 0; i < selected.length; ++i ) {
			/**
			 * Our values in select are in the format of <type>-<handle>.
			 * So we separate the string into type and handle values.
			 */
			const type = selected[ i ].value.slice( 0, selected[ i ].value.indexOf( '-' ) );
			const handle = selected[ i ].value.slice( selected[ i ].value.indexOf( '-' ) + 1 );
			exclusions[ type ].push( handle );
		}

		this.setState( { exclusions } );
	}

	/**
	 * Save asset optimization settings.
	 */
	saveSettings() {
		this.setState( { loading: true } );

		const settings = {
			compress: this.state.compress,
			combine: this.state.combine,
			styles: this.state.enabled.styles,
			scripts: this.state.enabled.scripts,
			fonts: this.state.enabled.fonts,
			exclusions: this.state.exclusions,
		};

		this.props.api
			.post( 'minify_auto_save_settings', settings )
			.then( ( r ) => {
				// Automatic type has not changed.
				if (
					'undefined' !== typeof r.notice &&
					false === r.notice
				) {
					WPHB_Admin.notices.show();
				} else {
					window.wphbMixPanel.trackAOUpdated( {
						Mode: r.mode,
						assets_found: wphb.stats.assetsFound,
						total_files: wphb.stats.totalFiles,
						filesize_reductions: wphb.stats.filesizeReductions,
						location: 'ao_settings',
					} );

					WPHB_Admin.notices.show( r.notice, 'success', false );

					// Allow opening a "how-to" modal from the notice.
					const noticeLink = document.getElementById(
						'wphb-basic-hdiw-link'
					);
					if ( noticeLink ) {
						noticeLink.addEventListener( 'click', () => {
							window.SUI.closeNotice( 'wphb-ajax-update-notice' );
							window.SUI.openModal(
								'automatic-ao-hdiw-modal-content',
								'automatic-ao-hdiw-modal-expand'
							);
						} );
					}
				}

				// If combine is enabled, also enable compress
				const compress = r.combine ? true : r.compress;

				this.setState( {
					loading: false,
					compress,
					combine: r.combine,
					assets: r.assets,
					enabled: r.enabled,
					exclusions: r.exclusions,
				} );
			} )
			.catch( ( error ) => window.console.log( error ) );
	}

	/**
	 * Basic view toggle.
	 *
	 * @return {JSX.Element}  BoxBuilderField element.
	 */
	optimizationSettings() {
		return (
			<>
				<SettingsRow
					classes="sui-flushed"
					label={ __( 'Optimization', 'wphb' ) }
					description={ __( 'Optimizing your assets will compress and organize them in a way that improves page load times.', 'wphb' ) }
					content={
						<>
							<Toggle
								text={ __( 'Compress', 'wphb' ) }
								checked={ this.state.compress }
								onChange={ this.handleToggleChange }
								data-type="compress"
								description={ __( 'Compresses your files for faster delivery while improving site speed by decluttering CSS and JavaScript.', 'wphb' ) }
							/>
							<Toggle
								text={ __( 'Combine', 'wphb' ) }
								checked={ this.state.combine }
								onChange={ this.handleToggleChange }
								data-type="combine"
								description={ __( 'Combines multiple JS and CSS files into fewer files, reducing the number of requests made when a page is loaded.', 'wphb' ) }
							/>
						</>
					} />
				<SettingsRow
					classes="sui-flushed"
					label={ __( 'Site Files', 'wphb' ) }
					description={ __( 'Added/removed plugins or themes? Update your file list to include new files, and remove old ones.', 'wphb' ) }
					content={
						<RecheckFilesButton />
					} />
			</>
		);
	}

	/**
	 * Render component.
	 *
	 * @return {JSX.Element}  Assets component.
	 */
	render() {
		return (
			<React.Fragment>
				<Assets
					loading={ this.state.loading }
					mode={ this.props.mode }
					clearCache={ this.props.clearCache }
					showModal={ this.props.showModal }
					content={
						<React.Fragment>
							{ this.optimizationSettings() }
						</React.Fragment>
					}
				/>
				<Configurations
					loading={ this.state.loading }
					resetSettings={ this.resetSettings }
					saveSettings={ this.saveSettings }
					onEnabledChange={ this.updateCheckBox }
					updateExclusions={ this.updateExclusions }
					assets={ this.state.assets }
					enabled={ this.state.enabled }
					exclusions={ this.state.exclusions }
					combine={ this.state.combine ? true : false }
				/>
			</React.Fragment>
		);
	}
}
