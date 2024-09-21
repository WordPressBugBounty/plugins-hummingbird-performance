/* global WPHB_Admin */
/* global wphbMixPanel */

/**
 * Internal dependencies
 */
import Fetcher from '../utils/fetcher';
import { getString } from '../utils/helpers';
import CacheScanner from '../scanners/CacheScanner';

( function( $ ) {
	'use strict';
	WPHB_Admin.caching = {
		module: 'caching',

		init() {
			const self = this,
				hash = window.location.hash,
				pageCachingForm = $( 'form[id="page_cache-form"]' ),
				fastCGICachingForm = $( 'form[id="fastcgi-form"]' ),
				rssForm = $( 'form[id="rss-form"]' ),
				settingsForm = $( 'form[id="settings-form"]' );

			// We assume there's at least one site, but this.scanner.init() will properly set the total sites.
			this.scanner = new CacheScanner( 1, 0 );

			if ( hash && $( hash ).length ) {
				setTimeout( function() {
					$( 'html, body' ).animate(
						{ scrollTop: $( hash ).offset().top },
						'slow'
					);
				}, 300 );
			}

			/**
			 * PAGE CACHING
			 *
			 * @since 1.7.0
			 */

			// Save page caching settings.
			pageCachingForm.on( 'submit', ( e ) => {
				e.preventDefault();
				self.saveSettings( 'page_cache', pageCachingForm );
			} );

			// Save fastCGI caching settings.
			fastCGICachingForm.on( 'submit', ( e ) => {
				e.preventDefault();
				const button = fastCGICachingForm.find( 'button.sui-button.sui-button-blue' );
				button.addClass( 'sui-button-onload-text' );

				Fetcher.caching
					.saveFastCGISettings( fastCGICachingForm.serialize() )
					.then( ( response ) => {
						button.removeClass( 'sui-button-onload-text' );
						if ( 'undefined' !== typeof response && '' === response.fastCGIResponse ) {
							wphbMixPanel.trackPageCachingSettings( 'modified', 'hosting_static_cache', 'caching_settings', response.settingsModified, response.preloadHomepage );
							window.location.search += '&updated=true';
						} else {
							const errorMessage = 'undefined' !== typeof response && '' !== response.fastCGIResponse ? response.fastCGIResponse : getString( 'errorSettingsUpdate' );
							WPHB_Admin.notices.show( errorMessage, 'error' );
						}
					} );
			} );

			// Clear page|gravatar cache.
			$( '#wphb-clear-cache' ).on( 'click', ( e ) => {
				e.preventDefault();
				self.clearCache( e.target );
			} );

			// Switch the cache method.
			$( '#wphb-switch-page-cache-method' ).on( 'click', ( e ) => {
				self.switchCacheMethod( e );
			} );

			/**
			 * Disable FastCGI cache.
			 *
			 * @since 3.4.0
			 */
			$( '#wphb-disable-fastcgi' ).on( 'click', ( e ) => {
				e.preventDefault();
				e.target.classList.add( 'sui-button-onload-text' );
				Fetcher.caching.disableFastCGI().then( ( response ) => {
					if ( 'undefined' !== typeof response && '' === response.fastCGIResponse ) {
						wphbMixPanel.trackPageCachingSettings( 'deactivate', 'hosting_static_cache', 'caching_settings', 'na', response.preloadHomepage );
						window.location.reload();
					} else {
						e.target.classList.remove( 'sui-button-onload-text' );
						const errorMessage = 'undefined' !== typeof response && '' !== response.fastCGIResponse ? response.fastCGIResponse : getString( 'errorSettingsUpdate' );
						WPHB_Admin.notices.show( errorMessage, 'error' );
					}
				} )
			} );

			/**
			 * Toggle clear cache settings.
			 *
			 * @since 2.1.0
			 */
			const intervalToggle = document.getElementById( 'clear_interval' );
			if ( intervalToggle ) {
				intervalToggle.addEventListener( 'change', function( e ) {
					e.preventDefault();
					$( '#page_cache_clear_interval' ).toggle();
				} );
			}

			/**
			 * Cancel cache preload.
			 *
			 * @since 2.1.0
			 */
			const cancelPreload = document.getElementById(
				'wphb-cancel-cache-preload'
			);
			if ( cancelPreload ) {
				cancelPreload.addEventListener( 'click', function( e ) {
					e.preventDefault();
					Fetcher.common.call( 'wphb_preload_cancel' ).then( () => {
						window.location.reload();
					} );
				} );
			}

			/**
			 * Show/hide preload settings.
			 *
			 * @since 2.3.0
			 */
			const preloadToggle = document.getElementById( 'preload' );
			if ( preloadToggle ) {
				preloadToggle.addEventListener( 'change', function( e ) {
					e.preventDefault();
					$( '#page_cache_preload_type' ).toggle();
				} );
			}

			/**
			 * Remove advanced-cache.php file.
			 *
			 * @since 3.1.1
			 */
			$( '#wphb-remove-advanced-cache' ).on( 'click', ( e ) => {
				e.preventDefault();
				Fetcher.common
					.call( 'wphb_remove_advanced_cache' )
					.then( () => location.reload() );
			} );

			/**
			 * CLOUDFLARE
			 */
			// "# of your cache types don’t meet the recommended expiry period" notice clicked.
			$( '#configure-link' ).on( 'click', function( e ) {
				e.preventDefault();
				$( 'html, body' ).animate(
					{
						scrollTop: $( '#wphb-box-caching-settings' ).offset()
							.top,
					},
					'slow'
				);
			} );

			/**
			 * RSS CACHING
			 *
			 * @since 1.8.0
			 */

			// Parse rss cache settings.
			rssForm.on( 'submit', ( e ) => {
				e.preventDefault();

				// Make sure a positive value is always reflected for the rss expiry time input.
				const rssExpiryTime = rssForm.find( '#rss-expiry-time' );
				rssExpiryTime.val( Math.abs( rssExpiryTime.val() ) );

				self.saveSettings( 'rss', rssForm );
			} );

			/**
			 * INTEGRATIONS
			 *
			 * @since 2.5.0
			 */
			const redisForm = document.getElementById( 'redis-settings-form' );
			if ( redisForm ) {
				redisForm.addEventListener( 'submit', ( e ) => {
					e.preventDefault();

					const btn = document.getElementById( 'redis-connect-save' );
					btn.classList.add( 'sui-button-onload-text' );

					const host = document.getElementById( 'redis-host' ).value;
					let port = document.getElementById( 'redis-port' ).value;
					const pass = document.getElementById( 'redis-password' )
						.value;
					const db = document.getElementById( 'redis-db' ).value;
					const connected = document.getElementById(
						'redis-connected'
					).value;

					if ( ! port ) {
						port = 6379;
					}

					// Submit via Fetcher. then close modal.
					Fetcher.caching
						.redisSaveSettings( host, port, pass, db )
						.then( ( response ) => {
							if (
								'undefined' !== typeof response &&
								response.success
							) {
								window.location.search +=
									connected === '1'
										? '&updated=redis-auth-2'
										: '&updated=redis-auth';
							} else {
								const notice = document.getElementById(
									'redis-connect-notice-on-modal'
								);
								notice.innerHTML = response.message;
								notice.parentNode.parentNode.parentNode.classList.remove(
									'sui-hidden'
								);
								notice.parentNode.parentNode.classList.add(
									'sui-spacing-top--10'
								);

								btn.classList.remove(
									'sui-button-onload-text'
								);
							}
						} );
				} );
			}

			const objectCache = document.getElementById( 'object-cache' );
			if ( objectCache ) {
				objectCache.addEventListener( 'change', ( e ) => {
					// Track feature enable.
					if ( e.target.checked ) {
						wphbMixPanel.enableFeature( 'Redis Cache' );
					} else {
						wphbMixPanel.disableFeature( 'Redis Cache' );
					}

					Fetcher.caching
						.redisObjectCache( e.target.checked )
						.then( ( response ) => {
							if (
								'undefined' !== typeof response &&
								response.success
							) {
								window.location.search +=
									'&updated=redis-object-cache';
							} else {
								WPHB_Admin.notices.show(
									getString( 'errorSettingsUpdate' ),
									'error'
								);
							}
						} );
				} );
			}

			const objectCachePurge = document.getElementById(
				'clear-redis-cache'
			);
			if ( objectCachePurge ) {
				objectCachePurge.addEventListener( 'click', () => {
					objectCachePurge.classList.add( 'sui-button-onload-text' );
					Fetcher.common
						.call( 'wphb_redis_cache_purge' )
						.then( () => {
							objectCachePurge.classList.remove(
								'sui-button-onload-text'
							);
							WPHB_Admin.notices.show(
								getString( 'successRedisPurge' )
							);
						} );
				} );
			}

			const redisCacheDisable = document.getElementById(
				'redis-disconnect'
			);
			if ( redisCacheDisable ) {
				redisCacheDisable.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					this.redisDisable();
				} );
			}

			/**
			 * SETTINGS
			 *
			 * @since 1.8.1
			 */

			// Parse page cache settings.
			settingsForm.on( 'submit', ( e ) => {
				e.preventDefault();

				// Hide the notice if it is showing.
				const detection = $(
					'input[name="detection"]:checked',
					settingsForm
				).val();
				if ( 'auto' === detection || 'none' === detection ) {
					$( '.wphb-notice.notice-info' ).slideUp();
				}

				self.saveSettings( 'other_cache', settingsForm );
			} );

			return this;
		},

		/**
		 * Disable Redis cache.
		 *
		 * @since 2.5.0
		 */
		redisDisable: () => {
			Fetcher.common.call( 'wphb_redis_disconnect' ).then( () => {
				window.location.search += '&updated=redis-disconnect';
			} );
		},

		/**
		 * Switch cache method.
		 *
		 * @since 3.9.0
		 *
		 * @param {Object} e Target element.
		 */
		switchCacheMethod: ( e ) => {
			e.preventDefault();
			const currentElement = e.currentTarget;
			const method = currentElement.dataset.method;
			const location = currentElement.dataset.location;
			currentElement.classList.add( 'sui-button', 'sui-button-onload-text' );
			currentElement.classList.remove( 'sui-tooltip' );
			const switchInfo = document.getElementById( 'wphb_switch_cache_info' );
			if ( switchInfo ) {
				switchInfo.style.display = 'none';
			}

			Fetcher.caching.switchCacheMethod( method ).then( ( response ) => {
				if ( 'undefined' !== typeof response && 'error' !== response.isFastCGIActivated ) {
					wphbMixPanel.trackPageCachingSettings( 'switch_method', method, location ? location : 'caching_settings', 'na', response.preloadHomepage );

					if ( 'dash_widget' === location && wphb.links.cachingPageURL ) {
						window.location.href = wphb.links.cachingPageURL;
					} else {
						window.location.reload();
					}
				} else {
					currentElement.classList.remove( 'sui-button' );
					currentElement.classList.remove( 'sui-button-onload-text' );
					const errorMessage = 'undefined' !== typeof response && 'error' === response.isFastCGIActivated ? response.fastCGIResponse : getString( 'errorSettingsUpdate' );
					WPHB_Admin.notices.show( errorMessage, 'error' );
				}
			} )
		},

		/**
		 * Process form submit from page caching, rss and settings forms.
		 *
		 * @since 1.9.0
		 *
		 * @param {string} module Module name.
		 * @param {Object} form   Form.
		 */
		saveSettings: ( module, form ) => {
			const button = form.find( 'button.sui-button' );
			button.addClass( 'sui-button-onload-text' );

			Fetcher.caching
				.saveSettings( module, form.serialize() )
				.then( ( response ) => {
					button.removeClass( 'sui-button-onload-text' );

					if ( 'undefined' !== typeof response && response.success ) {
						if ( 'page_cache' === module ) {
							wphbMixPanel.trackPageCachingSettings( 'modified', 'local_page_cache', 'caching_settings', response.settingsModified, response.preloadHomepage );
							window.location.search += '&updated=true';
						} else {
							WPHB_Admin.notices.show();
						}
					} else {
						WPHB_Admin.notices.show(
							getString( 'errorSettingsUpdate' ),
							'error'
						);
					}
				} );
		},

		/**
		 * Unified clear cache method that clears: page cache, gravatar cache and browser cache.
		 *
		 * @since 1.9.0
		 *
		 * @param {Object} target Target button that was clicked.
		 */
		clearCache: ( target ) => {
			const module = target.dataset.module;
			target.classList.add( 'sui-button-onload-text' );

			Fetcher.caching.clearCache( module ).then( ( response ) => {
				if ( 'page_cache' === module && 'undefined' !== typeof response && response.reload ) {
					window.location.reload();
				}

				if ( 'undefined' !== typeof response && response.success ) {
					if ( 'page_cache' === module ) {
						$( '.box-caching-summary span.sui-summary-large' ).html( '0' );
						WPHB_Admin.notices.show( getString( 'successPageCachePurge' ) );
					} else if ( 'gravatar' === module ) {
						WPHB_Admin.notices.show( getString( 'successGravatarPurge' ) );
					}
				} else {
					WPHB_Admin.notices.show( getString( 'errorCachePurge' ), 'error' );
				}
			} ).finally( () => target.classList.remove( 'sui-button-onload-text' ) );
		},

		/**
		 * Clear network wide page cache.
		 *
		 * @since 2.7.0
		 */
		clearNetworkCache() {
			window.SUI.slideModal( 'ccnw-slide-two', 'slide-next', 'next' );
			this.scanner.start();
		},
	};
} )( jQuery );
