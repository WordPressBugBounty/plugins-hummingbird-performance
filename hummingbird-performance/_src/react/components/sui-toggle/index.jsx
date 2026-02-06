/**
 * External dependencies
 */
import React from 'react';
import classnames from 'classnames';
import { getLink } from '../../../js/utils/helpers';
import Tooltip from '../sui-tooltip';
import Icon from '../sui-icon';

/**
 * Toggle functional component.
 *
 * @param {Object}  props             Component props.
 * @param {*}       props.text        Toggle text.
 * @param {string}  props.id          Toggle ID.
 * @param {string}  props.name        Toggle name.
 * @param {string}  props.className
 * @param {*}       props.onChange    On change action.
 * @param {boolean} props.checked     Checked status.
 * @param {boolean} props.disabled    Disabled status.
 * @param {boolean} props.hideToggle  Hide toggle icon.
 * @param {string}  props.description Description text.
 *
 * @param           props.learnMore   Learn more link text.
 * @param           props.tag         Tag text.
 * @param           props.layout      Layout type (left/right).
 * @return {JSX.Element} Toggle component.
 *
 * @class
 */
export default function Toggle( {
	text,
	id,
	name,
	className,
	onChange,
	checked = false,
	hideToggle = false,
	disabled = false,
	description = '',
	learnMore = '',
	tag = '',
	layout = 'left',
	...props
} ) {
	if ( 'right' === layout ) {
		return (
			<div className={ classnames( 'wphb-toggle-right', 'sui-form-field' ) }>
				<label htmlFor={ id } className={ classnames( className, 'sui-toggle' ) }>

					{ text &&
						<span id={ id + '-label' } className="sui-toggle-label">
							{ text }
							{ tag && <span className="sui-tag sui-tag-sm" style={ { position: 'relative', top: '-4px' } }>{ tag }</span> }
						</span> }

					{ description &&
						<Tooltip
							text={ description }
							classes={ 'setting-row-tooltip sui-tooltip-constrained' }
						>
							<Icon classes="hum-icon-question" />
						</Tooltip>
					}

					<input
						type="checkbox"
						name={ name }
						id={ id }
						checked={ checked }
						disabled={ disabled }
						onChange={ onChange }
						aria-labelledby={ id + '-label' }
						{ ...props }
					/>

					{ ! hideToggle &&
						<span className="sui-toggle-slider" aria-hidden="true" /> }
				</label>
			</div>
		);
	}
	return (
		<div className="sui-form-field ">
			<label htmlFor={ id } className={ classnames( className, 'sui-toggle' ) }>
				<input
					type="checkbox"
					name={ name }
					id={ id }
					checked={ checked }
					disabled={ disabled }
					onChange={ onChange }
					aria-labelledby={ id + '-label' }
					{ ...props }
				/>

				{ ! hideToggle &&
					<span className="sui-toggle-slider" aria-hidden="true" /> }

				{ text &&
					<span id={ id + '-label' } className="sui-toggle-label">
						{ text }
						{ tag && <span className="sui-tag sui-tag-sm" style={ { position: 'relative', top: '-4px' } }>{ tag }</span> }
					</span> }

				{ description &&
					<span id={ id + '-description' } className="sui-description">{ description }</span>
				}
				{ learnMore && (
					<a className="sui-button sui-button-ghost" href={ getLink( 'tracking' ) } target="_blank" rel="noreferrer">{ learnMore }</a>
				) }
			</label>
		</div>
	);
}
