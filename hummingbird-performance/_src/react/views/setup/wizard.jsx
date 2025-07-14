/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
const { __, sprintf } = wp.i18n;

/**
 * Internal dependencies
 */
import './wizard.scss';
import Box from '../../components/sui-box';
import { getLink } from '../../../js/utils/helpers';
import Icon from '../../components/sui-icon';
import ProgressBar from '../../components/sui-progress';
import Tag from '../../components/sui-tag';
import MinifySetupWizard from '../../../js/scanners/MinifySetupWizard';
import Toggle from '../../components/sui-toggle';
import SettingsRow from '../../components/sui-box-settings/row';
import Button from '../../components/sui-button';
import Tabs from '../../components/sui-tabs';
import Tooltip from '../../components/sui-tooltip';
import {createInterpolateElement} from "@wordpress/element";

/**
 * Wizard module, extends React.Component.
 *
 * @since 3.3.1
 */
export default class Wizard extends React.Component {
	/**
	 * Component constructor.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		super( props );

		this.state = {
			steps: {
				1: __( 'Getting Started', 'wphb' ),
				2: __( 'Asset Optimization', 'wphb' ),
				3: __( 'Uptime', 'wphb' ),
				4: __( 'Page Caching', 'wphb' ),
				5: __( 'Advanced Tools', 'wphb' ),
				6: __( 'Finish', 'wphb' )
			},
			scanning: false,
			skip: {
				advCacheFile: false,
			}
		};

		this.continueToNextStep = this.continueToNextStep.bind( this );
	}

	/**
	 * Run actions on component update.
	 *
	 * @param {Object} prevProps
	 * @param {Object} prevState
	 */
	componentDidUpdate( prevProps, prevState ) {
		if ( 1 === this.props.step && this.props.showConflicts ) {
			if ( this.props.step === prevProps.step && this.props.showConflicts === prevProps.showConflicts ) {
				return; // Nothing changed after re-checking status.
			}

			// We need to save our state, so we don't show extra stuff on next step.
			this.setState( {
				skip: {
					advCacheFile: ! this.props.issues.advCacheFile
				}
			} );

			jQuery( '.sui-box-header' ).on( 'click', this.toggleContent );
		}

		if ( 2 === this.props.step && this.props.settings.aoEnable ) {
			if ( true === this.state.scanning && this.state.scanning !== prevState.scanning ) {
				const scanner = new MinifySetupWizard( this.props.minifySteps, 0 );
				scanner.start();
			}
		}

		if ( 3 <= this.props.step && this.props.step !== prevProps.step ) {
			this.setState( { scanning: false } );
		}
	}

	/**
	 * Show/hide content block with issues.
	 *
	 * @param {Object} e
	 */
	toggleContent( e ) {
		e.currentTarget.parentNode.classList.toggle( 'open' );
	}

	/**
	 * Get navigation.
	 *
	 * @return {JSX.Element} Side navigation
	 */
	getNavigation() {
		const mobileSteps = Object.entries( this.state.steps ).map( ( step, key ) => {
			if ( 6 === key ) {
				return null;
			}

			const x1 = key * 20;
			const x2 = step[ 0 ] * 20;
			const stroke = this.props.step <= step[ 0 ] ? '#D8D8D8' : '#1ABC9C';

			return <line key={ key } x1={ x1 + '%' } x2={ x2 + '%' } className="line-mobile" stroke={ stroke } />;
		} );

		const steps = Object.entries( this.state.steps ).map( ( step, key ) => {
			if ( 6 === key || ( 3 === parseInt( step[ 0 ] ) && ! this.props.hasUptime ) ) {
				return null;
			}

			const classes = classNames( {
				current: parseInt( step[ 0 ] ) === this.props.step,
				done: parseInt( step[ 0 ] ) < this.props.step,
			} );
			const incFlag = ( ! this.props.hasUptime && parseInt( step[ 0 ] ) > 3 ) ? 1 : 0;

			return (
				<React.Fragment key={ key }>
					<li className={ classes }>
						{ 'done' !== classes && <span>{ step[ 0 ] - incFlag }</span> }
						{ 'done' === classes && <Icon classes="sui-icon-check" /> }
						{ step[ 1 ] }
					</li>
					{ 5 > key && <svg focusable="false" aria-hidden="true"><line y1="0" y2="30px" /></svg> }
				</React.Fragment>
			);
		} );

		return (
			<div className="sui-sidenav">
				<svg focusable="false" aria-hidden="true">
					{ mobileSteps }
				</svg>
				<ul>{ steps }</ul>
			</div>
		);
	}

	/**
	 * Wizard header.
	 *
	 * @return {JSX.Element} Header block
	 */
	getHeader() {
		let title = this.state.steps[ this.props.step ];
		let name = this.state.steps[ this.props.step ].replace( /\s+/g, '-' ).toLowerCase();

		if ( 1 === this.props.step && this.props.showConflicts ) {
			name = ! this.props.issues.advCacheFile ? 'success' : 'failed';
			title = __( 'Plugin Conflict', 'wphb' );
		} else if ( 6 === this.props.step ) {
			name = 'success';
			title = __( 'Wizard Completed!', 'wphb' );
		}

		return (
			<React.Fragment>
				<img
					className="sui-image"
					alt={ this.state.steps[ this.props.step ] }
					src={ getLink( 'wphbDirUrl' ) + 'admin/assets/image/setup/' + name + '.png' }
					srcSet={
						getLink( 'wphbDirUrl' ) + 'admin/assets/image/setup/' + name + '.png 1x, ' +
						getLink( 'wphbDirUrl' ) + 'admin/assets/image/setup/' + name + '@2x.png 2x'
					} />

				<small>{ __( 'Hummingbird Setup', 'wphb' ) }</small>

				<h2>{ title }</h2>
			</React.Fragment>
		);
	}

	/**
	 * Plugins compatibility content.
	 *
	 * @return {JSX.Element} Content block.
	 */
	getCompatPluginsContent() {
		if ( ! this.props.showConflicts || this.state.skip.advCacheFile ) {
			return null;
		}

		let title = __( 'No other caching plugin is detected', 'wphb' );
		let icon = 'check-tick sui-success';
		let description = (
			<p className="sui-description">
				{ __( 'No other caching plugin is detected. You can proceed with the setup.', 'wphb' ) }
			</p>
		);

		if ( this.props.issues.advCacheFile ) {
			title = __( 'Another caching plugin is detected', 'wphb' );
			icon = 'warning-alert sui-error';

			const message = createInterpolateElement(
				__('Hummingbird has detected an advanced-cache.php file in your site’s wp-content directory. <a>Manage your plugins</a> and disable any other active caching plugins to ensure Hummingbird’s page caching works properly.', 'wphb'),
				{
					a: <a href={getLink('plugins')}/>
				}
			);

			description = (
				<React.Fragment>
					<p className="sui-description">{message}</p>
					<p className="sui-description">
						{ __( 'If no other caching plugins are active, the advanced-cache.php may have been left by a previously used caching plugin. You can remove the file from the wp-content directory, or remove it via your file manager or FTP.', 'wphb' ) }
					</p>
				</React.Fragment>
			);
		}

		return (
			<Box
				boxClass="open"
				icon={ icon }
				title={ title }
				headerActions={
					<div className="sui-actions-right">
						<Button
							onClick={ this.toggleContent }
							type="button"
							classes="sui-button-icon"
							icon="sui-icon-chevron-up" />
					</div>
				}
				content={ description }
				footerActions={
					<React.Fragment>
						<Button
							onClick={ this.props.reCheckRequirements }
							type="button"
							classes={ [ 'sui-button', 'sui-button-ghost' ] }
							icon="sui-icon-update"
							text={ __( 'Re-check status', 'wphb' ) } />

						{ this.props.issues.advCacheFile &&
							<div className="sui-actions-right">
								<Button
									onClick={ this.props.removeAdvancedCache }
									type="button"
									classes={ [ 'sui-button', 'sui-button-blue' ] }
									text={ __( 'Remove file', 'wphb' ) } />
							</div> }
					</React.Fragment>
				}
			/>
		);
	}

	/**
	 * Toggle module buttons.
	 *
	 * @return {JSX.Element} Buttons.
	 */
	toggleButtons() {
		if ( 2 > this.props.step || 4 < this.props.step ) {
			return null;
		}

		// Do not show during AO scanning.
		if ( 2 === this.props.step && this.state.scanning ) {
			return null;
		}

		const id = [ 'aoEnable', 'uptimeEnable', 'cacheEnable' ];

		let tabEnabledChecked = this.props.settings[ id[ this.props.step - 2 ] ];
		let tabDisabledChecked = ! this.props.settings[ id[ this.props.step - 2 ] ];
		if ( this.props.step === 4 && this.props.settings.isFastCGISupported ) {
			tabEnabledChecked = this.props.settings[ id[ this.props.step - 2 ] ] && ! this.props.settings.fastCGI;
			tabDisabledChecked = ! this.props.settings[ id[ this.props.step - 2 ] ] && ! this.props.settings.fastCGI;
		}

		const sideTabs = [
			{
				title: this.props.step === 4 ? __( 'Local Page Cache', 'wphb' ) : __( 'Enable', 'wphb' ),
				checked: tabEnabledChecked,
				id: 'enable',
				onClick: () => this.props.toggleModule( 'enable', id[ this.props.step - 2 ] )
			},
			{
				title: __( 'Disable', 'wphb' ),
				checked: tabDisabledChecked,
				id: 'disable',
				onClick: () => this.props.toggleModule( 'disable', id[ this.props.step - 2 ] )
			},
		];

		if ( this.props.step === 4 && this.props.settings.isFastCGISupported ) {
			sideTabs.unshift({
				title: __( 'Static Server Cache', 'wphb' ),
				checked: this.props.settings.isFastCGISupported && this.props.settings.fastCGI,
				id: 'ssc',
				onClick: () => this.props.toggleModule( 'enable', 'fastCGI' )
			});
		}

		return <Tabs sideTabs="true" menu={ sideTabs } />;
	}

	/**
	 * Asset optimization settings tab.
	 *
	 * @return {JSX.Element} Tab content.
	 */
	assetOptimizationSettings() {
		return (
			<React.Fragment>
				{ this.props.settings.aoEnable &&
					<div className={ classNames( 'sui-border-frame', { 'sui-hidden': this.state.scanning || ( this.props.isNetworkAdmin && ! this.props.isMember ) } ) }>
						{ ! this.props.isNetworkAdmin &&
							<SettingsRow
								classes="sui-flushed"
								content={
									<Toggle
										id="aoSpeedy"
										onChange={ this.props.updateSettings }
										text={ __( 'Enable Speedy Compression', 'wphb' ) }
										checked={ this.props.settings.aoSpeedy }
										description={ __( 'Our automatic solution for optimization, the Speedy compression will auto-compress and auto-combine smaller files together. This can help to decrease the number of requests made when a page is loaded.', 'wphb' ) } />
								} /> }
						<SettingsRow
							classes="sui-flushed"
							content={
								<Toggle
									id="aoCdn"
									onChange={ this.props.updateSettings }
									text={ this.props.isMember ? __( 'WPMU DEV CDN', 'wphb' ) : createInterpolateElement(
										__( 'WPMU DEV CDN <span>PRO</span>', 'wphb' ),
										{
											span: <span className="sui-tag sui-tag-pro" />
										}
									) }
									checked={ this.props.settings.aoCdn }
									disabled={ ! this.props.isMember }
									description={ __( 'WPMU DEV CDN will serve your CSS, JS and other compatible files from our external CDN, effectively taking the load off your server so that pages load faster for your visitors.', 'wphb' ) } />
							} />
					</div> }
				{ this.eoSettings() }
				{ this.state.scanning &&
					<React.Fragment>
						<div className="wphb-progress-wrapper">
							<ProgressBar status={ this.props.settings.aoSpeedy ? __( 'Activating Speedy Optimization...', 'wphb' ) : __( 'Activating Basic Optimization...', 'wphb' ) } />
						</div>
						<p className="sui-description">
							{ __( 'Please wait, this won’t take more than a minute...', 'wphb' ) }
						</p>
					</React.Fragment> }
			</React.Fragment>
		);
	}

	/**
	 * Extra optimization settings tab.
	 *
	 * @return {JSX.Element} Tab content.
	 */
	eoSettings() {
		if ( ! this.props.settings.aoEnable || this.state.scanning || this.props.isNetworkAdmin ) {
			return null;
		}

		return (
			<>
			<p className='sui-description' style={{ textAlign: 'left', marginTop: '15px', fontWeight: 700 }}>
				{ __( 'Extra Optimization', 'wphb' ) }
			</p>
			<div className='sui-border-frame' style={{ marginTop: '15px' }}>
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="delayJS"
							onChange={ this.props.updateSettings }
							text={ this.props.isMember ? __( 'Delay JavaScript Execution', 'wphb' ) : createInterpolateElement(
								__( 'Delay JavaScript Execution <span>PRO</span>', 'wphb' ),
								{
									span: <span className="sui-tag sui-tag-pro" />
								}
							) }
							checked={ this.props.settings.delayJS }
							disabled={ ! this.props.isMember }
							description={ __( 'Improve performance by delaying the loading of non-critical JavaScript files above the fold until user interaction (e.g. scroll, click).', 'wphb' ) } />
					} />
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="criticalCSS"
							onChange={ this.props.updateSettings }
							text={ this.props.isMember ? __( 'Generate Critical CSS', 'wphb' ) : createInterpolateElement(
								__( 'Generate Critical CSS <span>PRO</span>', 'wphb' ),
								{
									span: <span className="sui-tag sui-tag-pro" />
								}
							) }
							checked={ this.props.settings.criticalCSS }
							disabled={ ! this.props.isMember }
							description={ __( 'Drastically reduce your page load time and eliminate render-blocking resources by automatically generating the critical CSS required to load your above-the-fold content.', 'wphb' ) } />
					} />
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="fontSwap"
							onChange={ this.props.updateSettings }
							text={ __( 'Swap Web Fonts', 'wphb' ) }
							checked={ this.props.settings.fontSwap }
							description={ __( 'Apply a similar fallback font that visitors will temporarily see until the primary font loads, to improve text visibility.', 'wphb' ) } />
					} />
			</div>
			<p className='sui-description' style={{ textAlign: 'left', marginTop: '10px' }}>
				{
					createInterpolateElement(
						__( "<strong>Note:</strong> You can tweak these settings further under Assets Optimization > Extra Optimization.", 'wphb' ),
						{
							strong: <strong />
						}
					) 
				}
			</p></>
		);
	}

	/**
	 * Page caching settings tab.
	 *
	 * @return {JSX.Element} Tab content.
	 */
	cacheSettings() {
		if ( ! this.props.settings.cacheEnable && ! this.props.settings.fastCGI ) {
			return null;
		}

		if ( this.props.settings.isFastCGISupported && this.props.settings.fastCGI ) {
			return (
				<div className="sui-border-frame">
					<SettingsRow
						classes="sui-flushed"
						content={
							<Toggle
								id="clearCacheButton"
								onChange={ this.props.updateSettings }
								text={ __( 'Show clear cache button in admin bar', 'wphb' ) }
								checked={ this.props.settings.clearCacheButton }
								description={ __( 'Add a shortcut to Hummingbird settings in the top WordPress Admin bar. Clicking the Clear Cache button in the WordPress Admin Bar will clear all active cache types.', 'wphb' ) } />
						} />
					<SettingsRow
						classes="sui-flushed"
						content={
							<Toggle
								id="clearOnComment"
								onChange={ this.props.updateSettings }
								text={ __( 'Clear cache on comment post', 'wphb' ) }
								checked={ this.props.settings.clearOnComment }
								description={ __( 'The page cache will be cleared after each comment made on a post.', 'wphb' ) } />
						} />
				</div>
			);
		}

		return (
			<div className="sui-border-frame">
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="cacheOnMobile"
							onChange={ this.props.updateSettings }
							text={ __( 'Cache on mobile devices', 'wphb' ) }
							checked={ this.props.settings.cacheOnMobile }
							description={ __( "By default, page caching is enabled for mobile devices. If you don't want to use mobile caching, simply disable this setting.", 'wphb' ) } />
					} />
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="clearOnComment"
							onChange={ this.props.updateSettings }
							text={ __( 'Clear cache on comment post', 'wphb' ) }
							checked={ this.props.settings.clearOnComment }
							description={ __( 'The page cache will be cleared after each comment made on a post.', 'wphb' ) } />
					} />
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="cacheHeader"
							onChange={ this.props.updateSettings }
							text={ __( 'Cache HTTP headers', 'wphb' ) }
							checked={ this.props.settings.cacheHeader }
							description={ __( "By default, Hummingbird won't cache HTTP headers. Enable this feature to include them.", 'wphb' ) } />
					} />
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="clearCacheButton"
							onChange={ this.props.updateSettings }
							text={ __( 'Show clear cache button in admin bar', 'wphb' ) }
							checked={ this.props.settings.clearCacheButton }
							description={ __( 'Add a shortcut to Hummingbird settings in the top WordPress Admin bar. Clicking the Clear Cache button in the WordPress Admin Bar will clear all active cache types.', 'wphb' ) } />
					} />
			</div>
		);
	}

	/**
	 * Advanced settings tab.
	 *
	 * @return {JSX.Element} Tab content.
	 */
	advancedSettings() {
		return (
			<div className="sui-border-frame">
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="queryStrings"
							onChange={ this.props.updateSettings }
							text={ __( 'Remove query strings from my assets', 'wphb' ) }
							checked={ this.props.settings.queryStrings }
							description={ __( 'Some of your resource URLs can end with something like “?x=y”, these are the query strings of the URL. Some servers, CDNs or caching systems don’t like query strings and removing them can help to increase speed.', 'wphb' ) } />
					} />
				{ this.props.hasWoo &&
					<SettingsRow
						classes="sui-flushed"
						content={
							<Toggle
								id="cartFragments"
								onChange={ this.props.updateSettings }
								text={ __( 'Disable cart fragments', 'wphb' ) }
								checked={ this.props.settings.cartFragments }
								description={ __( 'WooCommerce uses ajax calls to update cart totals without refreshing the page. These ajax calls run on every page and can drastically increase page load times. We recommend disabling cart fragments on all non-WooCommerce pages.', 'wphb' ) } />
						} /> }
				<SettingsRow
					classes="sui-flushed"
					content={
						<Toggle
							id="removeEmoji"
							onChange={ this.props.updateSettings }
							text={ __( 'Remove the default Emoji JS & CSS files', 'wphb' ) }
							checked={ this.props.settings.removeEmoji }
							description={ __( 'WordPress adds Javascript and CSS files to convert common symbols like “:)” to visual emojis. If you don’t need emojis this will remove two unnecessary assets.', 'wphb' ) } />
					} />
			</div>
		);
	}

	/**
	 * Results tab.
	 *
	 * @return {JSX.Element} Tab content.
	 */
	showResults() {
		return (
			<table className="sui-table">
				<thead>
					<tr>
						<th>{ __( 'Modules', 'wphb' ) }</th>
						<th>{ __( 'Settings applied', 'wphb' ) }</th>
						<th>{ __( 'Status', 'wphb' ) }</th>
					</tr>
				</thead>

				<tbody>
					{ this.props.settings.aoEnable &&
						<tr>
							<td className="sui-table-item-title">{ __( 'Asset Optimization', 'wphb' ) }</td>
							<td>
								{ ! this.props.isNetworkAdmin && this.props.settings.aoSpeedy && __( 'Speedy Optimization', 'wphb' ) }
								{ ! this.props.isNetworkAdmin && ! this.props.settings.aoSpeedy && __( 'Basic Optimization', 'wphb' ) }
								{ this.props.isNetworkAdmin && __( 'Active on subsites', 'wphb' ) }
								{ this.props.isMember && <br /> }
								{ this.props.isMember &&
									<React.Fragment>{ __( 'CDN', 'wphb' ) }</React.Fragment> }
							</td>
							<td>
								<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } />
								{ this.props.settings.aoCdn && this.props.isMember &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.aoCdn && this.props.isMember &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
							</td>
						</tr> }
					{ this.props.settings.aoEnable && ! this.props.isNetworkAdmin &&
						<tr>
							<td className="sui-table-item-title">{ __( 'Extra Optimization', 'wphb' ) }</td>
							<td>
								{ __( 'Delay JS Executions', 'wphb' ) }<br />
								{ __( 'Generate Critical CSS', 'wphb' ) }<br />
								{ __( 'Swap Web Fonts', 'wphb' ) }
							</td>
							<td>
								{ this.props.settings.delayJS && this.props.isMember &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.delayJS &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
								{ this.props.settings.criticalCSS && this.props.isMember &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.criticalCSS &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
								{ this.props.settings.fontSwap &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.fontSwap &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
							</td>
						</tr> }

					{ this.props.settings.uptimeEnable &&
						<tr>
							<td className="sui-table-item-title">{ __( 'Uptime', 'wphb' ) }</td>
							<td>{ __( 'Default settings', 'wphb' ) }</td>
							<td><Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /></td>
						</tr> }

					{ this.props.settings.fastCGI &&
						<tr>
							<td className="sui-table-item-title">{ __( 'Page Caching', 'wphb' ) }</td>
							<td>
								{ __( 'Static Server Cache', 'wphb' ) }<br />
								{ __( 'Show clear cache button in admin bar', 'wphb' ) }
							</td>
							<td>
								<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } />
								{ this.props.settings.clearCacheButton &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.clearCacheButton &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
							</td>
						</tr>
					}

					{ this.props.settings.cacheEnable && ! this.props.settings.fastCGI &&
						<tr>
							<td className="sui-table-item-title">{ __( 'Page Caching', 'wphb' ) }</td>
							<td>
								{ __( 'Cache on mobile devices', 'wphb' ) }<br />
								{ __( 'Clear cache on comment post', 'wphb' ) }<br />
								{ __( 'Cache HTTP headers', 'wphb' ) }<br />
								{ __( 'Show clear cache button in admin bar', 'wphb' ) }
							</td>
							<td>
								{ this.props.settings.cacheOnMobile &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.cacheOnMobile &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
								{ this.props.settings.clearOnComment &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.clearOnComment &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
								{ this.props.settings.cacheHeader &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.cacheHeader &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
								{ this.props.settings.clearCacheButton &&
									<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
								{ ! this.props.settings.clearCacheButton &&
									<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
							</td>
						</tr> }

					<tr>
						<td className="sui-table-item-title">{ __( 'Advanced Tools', 'wphb' ) }</td>
						<td>
							{ __( 'Remove query strings from my assets', 'wphb' ) }<br />
							{ this.props.hasWoo &&
								<React.Fragment>
									{ __( 'Disable cart fragments', 'wphb' ) }<br />
								</React.Fragment> }
							{ __( 'Remove the default Emoji JS & CSS files', 'wphb' ) }
						</td>
						<td>
							{ this.props.settings.queryStrings &&
								<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
							{ ! this.props.settings.queryStrings &&
								<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
							{ this.props.hasWoo &&
								<React.Fragment>
									{ this.props.settings.cartFragments &&
										<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
									{ ! this.props.settings.cartFragments &&
										<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
								</React.Fragment> }
							{ this.props.settings.removeEmoji &&
								<Tag type="blue sui-tag-sm" value={ __( 'Enabled', 'wphb' ) } /> }
							{ ! this.props.settings.removeEmoji &&
								<Tag type="grey sui-tag-sm" value={ __( 'Disabled', 'wphb' ) } /> }
						</td>
					</tr>
				</tbody>
			</table>
		);
	}

	/**
	 * Get content.
	 *
	 * @return {JSX.Element} Content
	 */
	getContent() {
		let description;

		if ( 1 === this.props.step ) {
			description = __( 'Get started by activating all our features with recommended default settings, then fine-tune them to suit your specific needs. Alternately you can skip this process if you’d prefer to start customizing.', 'wphb' );
			if ( this.props.showConflicts ) {
				description = __( 'Any issue reported here may cause issues while we set up the plugin.', 'wphb' );
				if ( ! this.props.issues.advCacheFile ) {
					description = __( 'There are no more potential issues. You can proceed with the setup.', 'wphb' );
				}
			}
		} else if ( 2 === this.props.step ) {
			description = __( "Hummingbird's Asset Optimization engine can combine and minify the files your website outputs when a user visits your website. The fewer requests your visitors have to make to your server, the better.", 'wphb' );
		} else if ( 3 === this.props.step ) {
			description = __( "Uptime monitors your server response time and lets you know when your website is down or too slow for your visitors. Monitor your site every minute to make sure it's up and graph your site speed so you can make sure everything is running super smooth.", 'wphb' );
		} else if ( 4 === this.props.step ) {
			description = __( 'Hummingbird stores static HTML copies of your pages and posts to decrease page load time. We will activate the default and basic settings and you can then fine-tune them to suit your specific needs.', 'wphb' );
		} else if ( 5 === this.props.step ) {
			description = __( 'Here are a few additional tweaks you can make to further reduce your page load times.', 'wphb' );
		} else if ( 6 === this.props.step ) {
			description = __( 'The setup is complete. We have activated the main features with the default settings. You can proceed to run a Performance Test or go directly to the Dashboard page.', 'wphb' );
		}

		return (
			<React.Fragment>
				<p className="sui-description">
					{ description }
				</p>

				{ 1 === this.props.step && ! this.props.showConflicts &&
					<div className="sui-border-frame wphb-getting-started-setup">
						<Toggle
							id="tracking"
							onChange={ this.props.updateSettings }
							text={ createInterpolateElement(
								__( "Help us Optimize your site for better Performance<span>Recommended</span>", 'wphb' ),
								{
									span: <span className="sui-tag sui-tag-sm" />
								}
							) }
							checked={ this.props.settings.tracking }
							description={
								createInterpolateElement(
									__( "Help us improve Hummingbird, minimize errors, and enhance the user experience by sharing anonymous, and non-sensitive usage data. You can change this option in the settings anytime. See <a>more</a> info about the data we collect.", 'wphb' ),
									{
										a: <a href={getLink('tracking')} target="_blank"/>
									}
								) } />
					</div> }

				{ 1 === this.props.step && this.props.showConflicts &&
					<div className="wphb-progress-wrapper">
						{ this.getCompatPluginsContent() }
					</div> }

				{ this.toggleButtons() }

				{ 2 === this.props.step && this.assetOptimizationSettings() }
				{ 4 === this.props.step && this.cacheSettings() }
				{ 5 === this.props.step && this.advancedSettings() }
				{ 6 === this.props.step && this.showResults() }
			</React.Fragment>
		);
	}

	/**
	 * Get footer actions.
	 *
	 * @return {JSX.Element} Footer content.
	 */
	getFooter() {
		return (
			<React.Fragment>
				{ 1 === this.props.step && ! this.props.showConflicts &&
					<span className="sui-description">
						<Button
							onClick={ () => this.props.finish( 'configs' ) }
							text={ __( 'Skip wizard and apply a config', 'wphb' ) } />
					</span> }
				{ 1 !== this.props.step &&
					<Button
						onClick={ this.props.prevStep }
						disabled={ this.state.scanning }
						type="button"
						icon="sui-icon-arrow-left"
						classes={ [ 'sui-button', 'sui-button-ghost' ] }
						text={ __( 'Back', 'wphb' ) } /> }
				<div className="sui-actions-right">
					{ 1 === this.props.step && ! this.props.showConflicts &&
						<Button
							onClick={ this.props.nextStep }
							type="button"
							classes={ [ 'sui-button', 'sui-button-blue' ] }
							text={ __( 'Get started', 'wphb' ) } /> }
					{ 1 === this.props.step && this.props.showConflicts && this.props.issues.advCacheFile &&
						<Tooltip
							classes="sui-tooltip-constrained sui-tooltip-top-right-mobile"
							text={ __( 'We advise to check the recommendations before proceeding.', 'wphb' ) }>
							<Button
								onClick={ this.props.skipConflicts }
								type="button"
								icon="sui-icon-arrow-right"
								classes={ [ 'sui-button', 'sui-button-ghost' ] }
								text={ __( 'Continue anyway', 'wphb' ) } />
						</Tooltip> }
					{ ( 1 !== this.props.step || ( this.props.showConflicts && ! this.props.issues.advCacheFile ) ) && 6 > this.props.step &&
						<Button
							onClick={ this.continueToNextStep }
							disabled={ this.state.scanning }
							type="button"
							icon="sui-icon-arrow-right"
							classes={ [ 'sui-button', 'sui-button-blue' ] }
							text={ __( 'Continue', 'wphb' ) } /> }
					{ 6 === this.props.step &&
						<React.Fragment>
							<Button
								onClick={ this.props.finish }
								type="button"
								classes={ [ 'sui-button', 'sui-button-ghost' ] }
								text={ __( 'Go to Dashboard', 'wphb' ) } />
							<Button
								onClick={ () => this.props.finish( 'runPerf' ) }
								type="button"
								classes={ [ 'sui-button', 'sui-button-blue' ] }
								text={ __( 'Run Performance Test', 'wphb' ) } />
						</React.Fragment> }
				</div>
			</React.Fragment>
		);
	}

	/**
	 * Handle "Continue" button click.
	 */
	continueToNextStep() {
		if ( 2 === this.props.step && this.props.settings.aoEnable && ! this.props.isNetworkAdmin ) {
			this.setState( { scanning: true } );
			this.props.scanning();
		} else {
			this.props.nextStep();
		}
	}

	/**
	 * Render component.
	 *
	 * @return {JSX.Element}  Requirements component.
	 */
	render() {
		return (
			<div className="sui-row-with-sidenav">
				{ this.getNavigation() }
				<Box
					boxClass="box-setup-requirements"
					hideHeader={ true }
					loading={ this.props.loading }
					loadingText={ 1 === this.props.step ? __( 'Checking status', 'wphb' ) : __( 'Saving settings', 'wphb' ) }
					content={
						<React.Fragment>
							{ this.getHeader() }
							{ this.getContent() }
						</React.Fragment>
					}
					footerActions={ this.getFooter() }
				/>
			</div>
		);
	}
}
