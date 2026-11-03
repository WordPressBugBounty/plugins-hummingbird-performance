/* global ajaxurl */
/* global wphb */

/**
 * External dependencies
 */
import assign from 'lodash/assign';

/**
 * Fetcher.
 *
 * @member {string} wphb.nonces.HBFetchNonce
 * @class
 */
function Fetcher() {
	const fetchUrl = ajaxurl;
	const fetchNonce = wphb.nonces.HBFetchNonce;
	const actionPrefix = 'wphb_';

	/**
	 * Request ajax with a promise.
	 * Use FormData Object as data if you need to upload file
	 *
	 * @param {string}          action
	 * @param {Object|FormData} data
	 * @param {string}          method
	 * @return {Promise<any>} Request results.
	 */
	function request( action, data = {}, method = 'GET' ) {
		const args = {
			url: fetchUrl,
			method,
			cache: false
		};

		if ( data instanceof FormData ) {
			data.append( 'nonce', fetchNonce );
			data.append( 'action', action );
			args.contentType = false;
			args.processData = false;
		} else {
			data.nonce 	= fetchNonce;
			data.action = action;
		}
		args.data = data;
		const Promise = require( 'es6-promise' ).Promise;
		return new Promise( ( resolve, reject ) => {
			jQuery.ajax( args ).done( resolve ).fail( reject );
		} ).then( ( response ) => checkStatus( response ) );
	}

	const methods = {
		/**
		 * Caching module actions.
		 */
		caching: {
			/**
			 * Clear cache for post.
			 *
			 * @param {number} postId
			 */
			clearCacheForPost: ( postId ) => {
				return request(
					actionPrefix + 'gutenberg_clear_post_cache',
					{ postId },
					'POST'
				);
			},
		},

		/**
		 * Asset Optimization module actions.
		 */
		minification: {
			/**
			 * Create CSS files from gutenberg.
			 *
			 * @since 3.6.0
			 * @param          postId
			 * @param {string} postID
			 */
			createCSSForPost: ( postId ) => {
				return request(
					actionPrefix + 'gutenberg_create_css_file',
					{ postId },
					'POST'
				);
			},

			/**
			 * Re Create CSS files from gutenberg.
			 *
			 * @since 3.6.0
			 * @param          postId
			 * @param {string} postID
			 */
			reCreateCSSForPost: ( postId ) => {
				return request(
					actionPrefix + 'gutenberg_recreate_css_file',
					{ postId },
					'POST'
				);
			},

			/**
			 * Revert CSS files from gutenberg.
			 *
			 * @since 3.6.0
			 * @param          postId
			 * @param {string} postID
			 */
			revertCSSForPost: ( postId ) => {
				return request(
					actionPrefix + 'gutenberg_revert_css_file',
					{ postId },
					'POST'
				);
			},

			/**
			 * Get critical css status for single post.
			 *
			 * @since 3.6.0
			 * @param          postId
			 * @param {string} postID
			 */
			 getCriticalStatusForSinglePost: ( postId ) => {
				return request(
					actionPrefix + 'gutenberg_get_critical_status_for_single_post',
					{ postId },
					'POST'
				);
			},
		},

		/**
		 * Common action that are used for mixpanel.
		 *
		 * @since 3.9.4
		 */
		mixpanel: {
			/**
			 * Track mixpanel event.
			 *
			 * @since 3.9.4
			 * @param {string} event
			 * @param {Object} properties
			 */
			trackMixpanelEvent: ( event, properties ) => {
				const action = actionPrefix + 'analytics_track_event';
				return request( action, { event, properties }, 'POST' );
			},
		},

	};

	assign( this, methods );
}

const HBFetcher = new Fetcher();
export default HBFetcher;

/**
 * Check status.
 *
 * @param {Object|string} response
 * @return {*} Response
 */
function checkStatus( response ) {
	if ( typeof response !== 'object' ) {
		response = JSON.parse( response );
	}
	if ( response.success ) {
		return response.data;
	}

	const data = response.data || {};
	const error = new Error(
		data.message || 'Error trying to fetch response from server'
	);
	error.response = response;
	throw error;
}
