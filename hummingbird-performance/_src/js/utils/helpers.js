import HBAPIFetch from '../../react/api';
/* global wphb */

/**
 * Strings internationalization
 *
 * @param {string} str
 * @return {*|string} String
 */
export const getString = ( str ) => {
	return wphb.strings[ str ] || '';
};

/**
 * Get a link to a HB screen
 *
 * @param {string} screen Screen slug
 * @return {string} URL
 */
export const getLink = ( screen ) => {
	return wphb.links[ screen ] || '';
};

/**
 * Toggle CDN usage
 *
 * @param {boolean} value value Enable/disable CDN.
 */
export const toggleCDNHelper = ( value ) => {
	const api = new HBAPIFetch();

	const fileExclude = document.getElementById( 'cdn_file_exclude' );
	if ( fileExclude ) {
		fileExclude.classList.toggle( 'sui-hidden' );
	}

	return api.post( 'minify_toggle_cdn', value )
		.then( ( response ) => {
			WPHB_Admin.notices.show();
			if ( response.cdn ) {
				window.wphbMixPanel.enableFeature( 'CDN' );
			} else {
				window.wphbMixPanel.disableFeature( 'CDN' );
			}
			return response;
		} );
};
