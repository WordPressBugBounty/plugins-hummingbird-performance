/* global wphb */
import Fetcher from './utils/fetcher';

( function() {
	'use strict';

	window.wphbMixPanel = {
		/**
		 * Opt in tracking.
		 */
		optIn() {
			wphb.mixpanel.enabled = true;
		},

		/**
		 * Deactivate feedback.
		 *
		 * @param {string} reason   Deactivation reason.
		 * @param {string} feedback Deactivation feedback.
		 */
		deactivate( reason, feedback = '' ) {
			this.track( 'plugin_deactivate', {
				reason,
				feedback,
			} );
		},

		/**
		 * Track HB Upsell events.
		 *
		 * @param {string} feature  Feature name.
		 * @param {string} location Location.
		 * @param {string} action   Action.
		 * @param {string} utmLink  UTM link.
		 * @param {string} evenName Event name.
		 */
		trackHBUpsell( feature, location, action, utmLink, evenName = 'hb_upsell_triggered' ) {
			this.track( evenName, {
				Feature: feature,
				Location: location,
				'User Action': action,
				'UTM Link': utmLink,
			} );
		},

		/**
		 * Track feature enable.
		 *
		 * @param {string} feature Feature name.
		 */
		enableFeature( feature ) {
			this.track( 'plugin_feature_activate', { feature } );
		},

		/**
		 * Track EO Upsell event.
		 *
		 * @param {string} eventName Event name.
		 * @param {string} location  Location.
		 */
		trackEoUpsell( eventName, location ) {
			const mpEventName = 'delayjs' === eventName ? 'js_delay_upsell' : 'critical_css_upsell';
	
			this.track( mpEventName, {
				'Modal Action': 'direct_cta',
				'Location': location,
			} );
		},

		/**
		 * Get update type
		 *
		 * @param {string} update_type Update type.
		 */
		getUpdateType( update_type ) {
			if ( 'activate' === update_type ) {
				return 'plugin_feature_activate';
			}

			if ( 'deactivate' === update_type ) {
				return 'plugin_feature_deactivate';
			}

			return '';
		},

		/**
		 * Track Delay JS Upsell event.
		 *
		 * @param {object} properties Properties.
		 */
		 trackDelayJSEvent( properties ) {
			if ( 'activate' === properties.update_type ) {
				this.enableFeature( 'JS Delay' );
			}

			if ( 'deactivate' === properties.update_type ) {
				this.disableFeature( 'JS Delay' );
			}

			this.track( 'js_delay_updated', properties );
		},

		/**
		 * Track Font Optimization event.
		 *
		 * @param {string} updateType Properties.
		 * @param {string} feature    Feature.
		 */
		trackFontOptimizationEvent( updateType, feature ) {
			if ( 'activate' === updateType ) {
				this.enableFeature( feature );
			}

			if ( 'deactivate' === updateType ) {
				this.disableFeature( feature );
			}
		},

		/**
		 * Track Critical Upsell event.
		 *
		 * @param {object} properties Properties.
		 */
		trackCriticalCSSEvent( properties ) {
			if ( 'activate' === properties.update_type ) {
				this.enableFeature( 'Critical Css' );
			}

			if ( 'deactivate' === properties.update_type ) {
				this.disableFeature( 'Critical Css' );
			}

			this.track( 'critical_css_updated', properties );
		},

		/**
		 * Track AO updated event.
		 *
		 * @param {object} properties Properties.
		 */
		 trackAOUpdated( properties ) {
			this.track( 'ao_updated', properties );
		},

		/**
		 * Track AO updated event.
		 *
		 * @param {object} feature Feature.
		 */
		 trackGutenbergEvent( feature ) {
			this.track( 'critical_css_gutenberg', { feature } );

			if ( 'revert' === feature ) {
				this.track( 'critical_css_cache_purge', {
					location: 'gutenberg'
				} );
			}
		},

		/**
		 * Track Page Caching event.
		 *
		 * @param {string} updateType       Update type.
		 * @param {string} method           Method.
		 * @param {string} location         Location.
		 * @param {string} settingsModified Modified Settings.
		 * @param {string} preloadHomepage  Settings default.
		 */
		trackPageCachingSettings( updateType, method, location, settingsModified, preloadHomepage ) {
			const feature = 'local_page_cache' === method ? 'Page Caching' : method;
			if ( 'activate' === updateType ) {
				this.enableFeature( feature );
			}

			if ( 'deactivate' === updateType ) {
				this.disableFeature( feature );
			}

			this.track( 'page_caching_updated', {
				'update_type': updateType,
				'Method': method,
				'Location': location,
				'modified_settings': settingsModified,
				'preload_homepage': preloadHomepage,
			} );
		},

		/**
		 * Track PRO Upsell event.
		 *
		 * @param {string} eventName Event name.
		 * @param {string} action    Action.
		 * @param {string} location  Location.
		 */
		trackProUpsell( eventName, action, location = 'submenu' ) {
			this.track( eventName, {
				'Location': location,
				'User Action': action,
			} );
		},

		/**
		 * Track feature disable.
		 *
		 * @param {string} feature Feature name.
		 */
		disableFeature( feature ) {
			this.track( 'plugin_feature_deactivate', { feature } );
		},

		/**
		 * Track an event.
		 *
		 * @param {string} event                Event ID.
		 * @param {Object} data                 Event data.
		 */
		track( event, data = {} ) {
			if (
				'undefined' === typeof wphb.mixpanel ||
				! wphb.mixpanel.enabled
			) {
				return;
			}

			Fetcher.mixpanel.trackMixpanelEvent( event, data );
		}
	};
}() );
