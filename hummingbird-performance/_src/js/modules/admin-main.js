/* global wphb */
/* global wphbMixPanel */

/**
 * Internal dependencies
 */
import Fetcher from '../utils/fetcher';
import { getString } from '../utils/helpers';

( function( $ ) {
	'use strict';

	const WPHB_Admin = {
		modules: [],
		// Common functionality to all screens
		init() {
			/**
			 * Handles the tab navigation on mobile.
			 *
			 * @since 2.7.2
			 */
			$( '.sui-mobile-nav' ).on( 'change', ( e ) => {
				window.location.href = e.target.value;
			} );

			/**
			 * Refresh page, when selecting a report type.
			 *
			 * @since 2.0.0
			 */
			$( 'select#wphb-performance-report-type' ).on(
				'change',
				function( e ) {
					const url = new URL( window.location );
					url.searchParams.set( 'type', e.target.value );
					window.location = url;
				}
			);

			$( '#safe_mode' ).on( 'click', function( e ) {
				e.preventDefault();
				const willBeChecked = ! this.checked; // This is the state after click
				const modalId = willBeChecked ? 'wphb-safe-mode-confirmation-modal' : 'wphb-safe-mode-modal';

				if ( willBeChecked ) {
					Fetcher.common
						.call( 'wphb_safemode_has_changes', true )
						.then( ( response ) => {
							if ( response.hasChanges ) {
								window.SUI.openModal( modalId, 'wpbody-content' );
							} else {
								const safeModeCheckbox = document.getElementById( 'safe_mode' );
								if ( safeModeCheckbox ) {
									safeModeCheckbox.checked = false;
								}
							}
						} );
				} else {
					window.SUI.openModal( modalId, 'wpbody-content' );
				}
			} );

			/**
			 * Clear log button clicked.
			 *
			 * @since 1.9.2
			 */
			$( '.wphb-logging-buttons' ).on(
				'click',
				'.wphb-logs-clear',
				function( e ) {
					e.preventDefault();

					Fetcher.common
						.clearLogs( e.target.dataset.module )
						.then( ( response ) => {
							if ( 'undefined' === typeof response.success ) {
								return;
							}

							if ( response.success ) {
								WPHB_Admin.notices.show( response.message );
							} else {
								WPHB_Admin.notices.show(
									response.message,
									'error'
								);
							}
						} );
				}
			);

			/**
			 * Track performance report scan init.
			 *
			 * @since 2.5.0
			 */
			$( '#performance-run-test, #performance-scan-website, #run-performance-test' ).on(
				'click',
				function() {
					const location = $( this ).attr( 'data-location' );
					wphbMixPanel.track( 'plugin_scan_started', {
						score_mobile_previous: getString(
							'previousScoreMobile'
						),
						score_desktop_previous: getString(
							'previousScoreDesktop'
						),
						'AO Status': getString(
							'aoStatus'
						),
						Location: typeof location !== 'undefined' ? location : 'unknown',
					} );
				}
			);

			const urlParams = new URLSearchParams( window.location.search );
			if ( urlParams.has( 'wphb_safemode_published' ) ) {
				WPHB_Admin.notices.show( getString( 'safeModePublished' ), 'success' );
				urlParams.delete( 'wphb_safemode_published' );
				window.history.replaceState( {}, document.title, window.location.pathname + '?' + urlParams.toString() );
			}
		},

		initModule( module ) {
			if ( this.hasOwnProperty( module ) ) {
				this.modules[ module ] = this[ module ].init();
				return this.modules[ module ];
			}

			return {};
		},

		toggleSafeMode( button ) {
			button.classList.add( 'disabled' );
			Fetcher.common
				.callWithParams( 'wphb_toggle_safe_mode', true )
				.then( ( response ) => {
					// Check the checkbox before closing modal and reloading
					const safeModeCheckbox = document.getElementById( 'safe_mode' );
					if ( safeModeCheckbox ) {
						safeModeCheckbox.checked = true;
					}
					window.SUI.closeModal();
					WPHB_Admin.notices.show( response.message );
					setTimeout( () => {
						window.location.reload();
					}, 500 );
					button.classList.remove( 'disabled' );
				} );
		},

		discardSafeMode( button ) {
			button.classList.add( 'disabled' );
			Fetcher.common
				.callWithParams( 'wphb_discard_safe_mode', true )
				.then( ( ) => {
					button.classList.remove( 'disabled' );
					window.SUI.closeModal();
					window.location.reload();
				} );
		},

		publishSafeMode( button ) {
			button.classList.add( 'disabled' );
			const clearAllCache = document.getElementById( 'wphb-safe-mode-clear-all-cache' );
			const clearAllCacheValue = clearAllCache ? clearAllCache.checked : false;
			Fetcher.common
				.callWithParams( 'wphb_publish_safe_mode', clearAllCacheValue )
				.then( ( response ) => {
					button.classList.remove( 'disabled' );
					WPHB_Admin.notices.show( response.message );
					setTimeout( () => {
						window.location.reload();
					}, 500 );
					window.SUI.closeModal();
				} );
		},

		getModule( module ) {
			if ( typeof this.modules[ module ] !== 'undefined' ) {
				return this.modules[ module ];
			}
			return this.initModule( module );
		},
	};

	/**
	 * Admin notices.
	 */
	WPHB_Admin.notices = {
		init() {
			const cfNotice = document.getElementById( 'dismiss-cf-notice' );
			if ( cfNotice ) {
				cfNotice.onclick = ( e ) => this.dismissCloudflareNotice( e );
			}

			const http2Notice = document.getElementById(
				'wphb-floating-http2-info'
			);
			if ( http2Notice ) {
				http2Notice.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					Fetcher.common.dismissNotice( 'http2-info' );
					$( '.wphb-box-notice' ).slideUp();
				} );
			}

			const rateButtons = document.querySelectorAll( '.wphb-rate-buttons>a' );
			rateButtons.forEach( ( button ) => {
				button.addEventListener( 'click', ( e ) => this.rateActions( e ) );
			} );
		},

		/**
		 * Show notice.
		 *
		 * @since 1.8
		 *
		 * @param {string}  message Message to display.
		 * @param {string}  type    Error or success.
		 * @param {boolean} dismiss Auto dismiss message.
		 */
		show( message = '', type = 'success', dismiss = true ) {
			if ( '' === message ) {
				message = getString( 'successUpdate' );
			}

			const options = {
				type,
				dismiss: {
					show: false,
					label: getString( 'dismissLabel' ),
					tooltip: getString( 'dismissLabel' ),
				},
				icon: 'info',
			};

			if ( ! dismiss ) {
				options.dismiss.show = true;
			}

			window.SUI.openNotice(
				'wphb-ajax-update-notice',
				'<p>' + message + '</p>',
				options
			);
		},

		/**
		 * Dismiss notice.
		 *
		 * @since 2.6.0  Refactored and moved from WPHB_Admin.init()
		 *
		 * @param {Object} el
		 */
		dismiss( el ) {
			const noticeId = el.closest( '.sui-notice' ).getAttribute( 'id' );
			Fetcher.common.dismissNotice( noticeId );
			window.SUI.closeNotice( noticeId );
		},

		/**
		 * Dismiss Cloudflare notice from Dashboard or Caching pages.
		 *
		 * @since 2.6.0  Refactored and moved from WPHB_Admin.dashboard.init() && WPHB_ADMIN.caching.init()
		 *
		 * @param {Object} e
		 */
		dismissCloudflareNotice( e ) {
			e.preventDefault();
			Fetcher.common.call( 'wphb_cf_notice_dismiss' );
			const cloudFlareDashNotice = $( '.cf-dash-notice' );
			cloudFlareDashNotice.slideUp();
			cloudFlareDashNotice.parent().addClass( 'no-background-image' );
		},

		/**
		 * Handle rate notice actions.
		 *
		 * @since 3.18.0
		 *
		 * @param {Object} e Event object.
		 */
		rateActions( e ) {
			const action = e.currentTarget.getAttribute( 'data-Action' );
			if ( 'rate' !== action ) {
				e.preventDefault();
			}

			const rateNotice = $( '.notice-free-rated' );
			let noticeType;
			if ( rateNotice.hasClass( 'notice-perf-rate' ) ) {
				noticeType = 'performance_score';
			} else {
				noticeType = 'seven_days';
			}

			const pageMap = {
				wphb: 'Dashboard',
				'wphb-performance': 'Performance Test',
				'wphb-caching': 'Caching',
				'wphb-minification': 'Asset Optimization',
				'wphb-advanced': 'Advanced Tools',
				'wphb-uptime': 'Uptime',
				'wphb-notifications': 'Notifications',
				'wphb-settings': 'Settings',
			};
			let location = 'Unknown';
			const urlParams = new URLSearchParams( window.location.search );
			const pageSlug = urlParams.get( 'page' );
			if ( pageSlug && pageMap[ pageSlug ] ) {
				location = pageMap[ pageSlug ];
			}

			wphbMixPanel.track( 'Rating Notice', {
				Action: action,
				'Notice Type': noticeType,
				Location: location,
			} );

			rateNotice.slideUp();

			Fetcher.common.dismissNotice( 'free-rated', action );
		},

	};

	window.WPHB_Admin = WPHB_Admin;
}( jQuery ) );
