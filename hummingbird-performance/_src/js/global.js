/* global wphb, wphbGlobal */

import HBDeactivationSurvey from './hb-deactivation-survey';

( function() {
	'use strict';

	const COPY_TOOLTIP_DURATION = 2000;

	const WPHBGlobal = {
		init() {
			this.registerUpsellClick();
			this.registerClearAllCache();
			this.registerClearNetworkCache();
			this.registerClearCacheFromNotice();
			this.registerClearCloudflare();
			this.registerSwitchCriticalCSS();
			this.registerDeactivationSurvey();
			this.registerCopyText();
		},

		/**
		 * Register copy text button.
		 *
		 * The button is the dashboard notice's, and the label it swaps to is the only feedback
		 * available: the notice has no tooltip styles, unlike the front-end bar's button of the
		 * same id.
		 *
		 * @since 3.18.0
		 */
		registerCopyText() {
			const btn = document.getElementById(
				'wphb-copy-test-link'
			);

			if ( ! btn ) {
				return;
			}

			const originalLabel = btn.textContent;
			let labelTimer;

			btn.addEventListener( 'click', ( event ) => {
				event.preventDefault();

				this.copyTextToClipboard( wphbGlobal.previewLink ).then( () => {
					btn.textContent = wphbGlobal.copiedLabel;
					window.clearTimeout( labelTimer );
					labelTimer = window.setTimeout( () => {
						btn.textContent = originalLabel;
					}, COPY_TOOLTIP_DURATION );
				} );
			} );
		},

		/**
		 * Copy text to the clipboard, including non-secure frontend contexts.
		 *
		 * @param {string} text Text to copy.
		 *
		 * @return {Promise<void>} Resolves after the text is copied.
		 */
		copyTextToClipboard( text ) {
			if ( navigator.clipboard?.writeText ) {
				return navigator.clipboard.writeText( text );
			}

			const textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'fixed';
			textarea.style.top = '-9999px';
			document.body.appendChild( textarea );
			textarea.select();

			let copied = false;
			try {
				copied = document.execCommand( 'copy' );
			} finally {
				textarea.remove();
			}

			return copied
				? Promise.resolve()
				: Promise.reject( new Error( 'Unable to copy text.' ) );
		},

		/**
		 * Track deactivation survey.
		 *
		 * @since 3.10.0
		 */
		registerDeactivationSurvey() {
			const deactivatePluginLink = document.querySelector( 'a[id^="deactivate-hummingbird-pro"]' ) || document.querySelector( 'a[id^="deactivate-hummingbird-performance"]' );
			if ( ! deactivatePluginLink ) {
				return;
			}

			// Deactivation survey modal.
			( new HBDeactivationSurvey() ).init();
		},

		/**
		 * Switch to critical CSS from legacy.
		 *
		 * @since 3.9.3
		 */
		registerSwitchCriticalCSS() {
			const btn = document.getElementById(
				'wphb-switch-critical-from-legacy'
			);

			if ( ! btn ) {
				return;
			}

			btn.addEventListener( 'click', () =>
				jQuery
					.ajax( {
						url: wphbGlobal.ajaxurl,
						method: 'POST',
						data: {
							nonce: wphbGlobal.nonce,
							action: 'wphb_switch_to_critical_css_from_legacy',
						},
					} )
					.done( function() {
						// Redirect to a response page
						window.location.href = wphbGlobal.minify_url + '&view=tools';
					} )
			);
		},

		/**
		 * Clear selected module from admin bar.
		 *
		 * @since 3.0.1
		 *
		 * @param {string} module Module ID.
		 */
		clearCache( module ) {
			jQuery
				.ajax( {
					url: wphbGlobal.ajaxurl,
					method: 'POST',
					data: {
						nonce: wphbGlobal.nonce,
						action: 'wphb_clear_caches',
						modules: [ module ],
					},
				} )
				.done( function() {
					location.reload();
				} );
		},

		/**
		 * Clear all cache from admin bar.
		 *
		 * @since 3.0.1
		 */
		registerClearAllCache() {
			const btn = document.getElementById(
				'wp-admin-bar-wphb-clear-all-cache'
			);

			if ( ! btn ) {
				return;
			}

			btn.addEventListener( 'click', function() {
				const action = 'wphb_global_clear_cache';
				this.post( action + '&all_clear=1' );
			}.bind( this )
			);
		},

		/**
		 * Track upsell menu click.
		 *
		 * @since 3.9.0
		 */
		registerUpsellClick() {
			const upsellSubmenuLink = document.querySelector( '#toplevel_page_wphb a[href^="https://wpmudev.com"]' );

			if ( ! upsellSubmenuLink ) {
				return;
			}

			if ( typeof wphb === 'undefined' || ! wphb.mixpanel.enabled ) {
				return;
			}

			if ( ! wphbGlobal.is_hb_page ) {
				require( './mixpanel' );
			}

			upsellSubmenuLink.addEventListener( 'click', ( event ) => {
				window.wphbMixPanel.trackHBUpsell( 'pro_general', 'submenu', 'cta_clicked', event.currentTarget.href, 'hb_pro_upsell' );
			} );
		},

		/**
		 * Clear network cache.
		 */
		registerClearNetworkCache() {
			const btn = document.querySelector(
				'#wp-admin-bar-wphb-clear-cache-network-wide > a'
			);

			if ( ! btn ) {
				return;
			}

			btn.addEventListener( 'click', () => {
				if ( 'undefined' === typeof window.WPHB_Admin ) {
					window.location.href =
						'/wp-admin/network/admin.php?page=wphb-caching&update=open-ccnw';
					return;
				}

				window.SUI.openModal(
					'ccnw-modal',
					'wpbody',
					'ccnw-clear-now'
				);
			} );
		},

		/**
		 * Clear cache from notice regarding plugin/theme updates.
		 */
		registerClearCacheFromNotice() {
			const btn = document.getElementById(
				'wp-admin-notice-wphb-clear-cache'
			);

			if ( ! btn ) {
				return;
			}

			btn.addEventListener( 'click', () =>
				this.post( 'wphb_global_clear_cache' + '&all_clear=1' )
			);
		},

		/**
		 * Clear Cloudflare browser cache.
		 *
		 * @since 2.7.2
		 */
		registerClearCloudflare() {
			const btn = document.querySelector(
				'#wp-admin-bar-wphb-clear-cloudflare > a'
			);

			if ( ! btn ) {
				return;
			}

			btn.addEventListener( 'click', () =>
				this.post( 'wphb_front_clear_cloudflare' )
			);
		},

		/**
		 * Send AJAX request.
		 *
		 * @param {string}          action
		 * @param {boolean}         reload
		 * @param {Function | null} callback
		 */
		post( action, reload = true, callback = null ) {
			this.request( action )
				.then( ( response ) => {
					if ( callback && typeof callback === 'function' ) {
						callback( response );
					}

					if ( reload ) {
						location.reload( action );
					}
				} );
		},

		request( action ) {
			return new Promise( ( resolve ) => {
				const xhr = new XMLHttpRequest();
				xhr.open( 'POST', wphbGlobal.ajaxurl + '?action=' + action + '&_ajax_nonce=' + wphbGlobal.nonce );
				xhr.onload = function() {
					if ( xhr.status === 200 ) {
						resolve();
					}
				};

				xhr.send();
			} );
		}
	};

	document.addEventListener( 'DOMContentLoaded', function() {
		WPHBGlobal.init();
	} );

	window.WPHBGlobal = WPHBGlobal;
}() );
