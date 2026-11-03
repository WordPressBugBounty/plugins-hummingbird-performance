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

export const currentPage = () => {
	const url = window.location.href;
	const urlparse = new URL( url );
	const page = urlparse.searchParams.get( 'page' );
	if ( page ) {
		switch ( page ) {
			case 'wphb':
				return 'Dashboard';
			case 'wphb-caching':
				return 'Caching';
			case 'wphb-minification':
				return 'Asset Optimization';
			case 'wphb-advanced':
				return 'Advanced Tools';
			case 'wphb-settings':
				return 'Settings';
		}
	}
	return 'External';
};
