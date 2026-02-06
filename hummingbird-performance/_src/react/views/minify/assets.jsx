/* global SUI */
/* global wphbReact */

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
import './assets.scss';
import Action from '../../components/sui-box/action';
import Box from '../../components/sui-box';
import Tooltip from '../../components/sui-tooltip';
import BoxBuilder from '../../components/sui-box-builder';
import { createInterpolateElement } from "@wordpress/element";
import Icon from '../../components/sui-icon';

/**
 * Assets component.
 *
 * @since 2.7.2
 */
export default class Assets extends React.Component {
	/**
	 * Component constructor.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		super( props );
		this.onTabClick = this.onTabClick.bind( this );
		this.showHowDoesItWork = this.showHowDoesItWork.bind( this );
	}

	/**
	 * Component header.
	 *
	 * @return {JSX.Element}  Header action buttons.
	 */
	getHeaderActions() {
		const buttons = (
			<div className="sui-actions-right">
				<Tooltip classes="sui-tooltip-top-right sui-tooltip-constrained wphb-ao-mode-switch-tooltip" text={ 'advanced' === this.props.mode ? __( 'Optimize your assets and improve page load times based on our automated options.' ) : __( "Manually configure each file yourself to achieve the exact setup for your site. If you're unfamiliar with manual optimization, check the documentation first.", 'wphb' ) }>
					<Icon classes="sui-icon-info sui-md" />
				</Tooltip>
				<span className="sui-description" style={ { marginTop: '0px' } }>
					{ createInterpolateElement(
						__( 'Switch to <a></a>', 'wphb' ),
						{
							a: <a onClick={ this.onTabClick } id={ 'advanced' === this.props.mode ? 'auto-tab' : 'manual-tab' } href="javascript:void(0)">{ 'advanced' === this.props.mode ? __( 'Automatic Mode', 'wphb' ) : __( 'Dev Mode', 'wphb' ) }</a>
						}
					) }
				</span>
			</div>
		);

		return <Action type="right" content={ buttons } />;
	}

	/**
	 * Show "How does it work" modal.
	 */
	showHowDoesItWork() {
		let current = 'auto';
		let other = 'manual';
		let mode = 'automatic';

		if ( 'advanced' === this.props.mode ) {
			current = 'manual';
			other = 'auto';
			mode = 'manual';
		}

		// Reset tab selection.
		const label = document.getElementById( 'hdw-' + current + '-trigger-label' );
		if ( label ) {
			label.classList.add( 'active' );
			document
				.getElementById( 'hdw-' + other + '-trigger-label' )
				.classList.remove( 'active' );
		}

		SUI.openModal(
			mode + '-ao-hdiw-modal-content',
			'wphb-basic-hdiw-link'
		);
	}

	/**
	 * Handle "Automatic"/"Manual" button click.
	 *
	 * @param {Object} e
	 */
	onTabClick( e ) {
		if ( 'manual-tab' === e.target.id && 'advanced' === this.props.mode ) {
			return;
		}

		if ( 'auto-tab' === e.target.id && 'basic' === this.props.mode ) {
			return;
		}

		const type = 'advanced' === this.props.mode ? 'basic' : 'advanced';
		const modalId = 'wphb-' + type + '-minification-modal';
		const modalElement = document.getElementById( modalId );

		if ( this.props.showModal && modalElement ) {
			SUI.openModal(
				modalId,
				'wphb-switch-to-' + type
			);
		} else {
			window.WPHB_Admin.minification.switchView( type );
		}
	}

	/**
	 * Component body.
	 *
	 * @param {JSX.Element} content Content.
	 *
	 * @return {JSX.Element}  Content.
	 */
	getContent( content ) {
		return (
			<React.Fragment>
				{ 'advanced' === this.props.mode ? (
					<React.Fragment>
						<p>
							{ createInterpolateElement(
								__( 'In Dev Mode, you can manually optimize (compress, combine, move, inline, defer, async, and preload) individual files. With this amount of freedom comes the possibility of damaging your site so if you are unfamiliar with manually optimizing your files, we recommend reviewing the <a>How Does it Work?</a> guide.', 'wphb' ),
								{
									a: <a href={ wphbReact.links.aoDocLink } target="_blank" rel="noopener noreferrer">{ __( 'How Does it Work?', 'wphb' ) }</a>
								}
							) }
						</p>
						<p className="sui-description">
							{ __( 'Manually configure your optimization settings (compress, combine, move, inline, defer, async, and preload) and then publish your changes.', 'wphb' ) }
						</p>
					</React.Fragment>
				) : (
					<p>
						{ __(
							'Optimizing your assets will compress and organize them in a way that improves page load times. You can choose to use our automated options, or manually configure each file yourself.',
							'wphb'
						) }
					</p>
				) }

				<BoxBuilder flushed={ true } fields={ content } />
			</React.Fragment>
		);
	}

	/**
	 * Render component.
	 *
	 * @return {JSX.Element}  Assets component.
	 */
	render() {
		const type = 'advanced' === this.props.mode ? 'manual' : 'auto';

		return (
			<Box
				boxClass={ 'box-minification-assets-' + type }
				loading={ this.props.loading }
				title={ __( 'Assets Optimization', 'wphb' ) }
				headerActions={ this.getHeaderActions() }
				content={ this.getContent( this.props.content ) }
			/>
		);
	}
}
