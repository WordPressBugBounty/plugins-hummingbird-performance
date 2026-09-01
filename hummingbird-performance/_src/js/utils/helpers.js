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

export const currentPage = () => {
	const url = window.location.href;
	const urlparse = new URL( url );
	const page = urlparse.searchParams.get( 'page' );
	if ( page ) {
		switch ( page ) {
			case 'wphb':
				return 'Dashboard';
			case 'wphb-performance':
				return 'Performance Test';
			case 'wphb-caching':
				return 'Caching';
			case 'wphb-minification':
				return 'Asset Optimization';
			case 'wphb-advanced':
				return 'Advanced Tools';
			case 'wphb-uptime':
				return 'Uptime';
			case 'wphb-notifications':
				return 'Notifications';
			case 'wphb-settings':
				return 'Settings';
		}
	}
	return 'External';
};
