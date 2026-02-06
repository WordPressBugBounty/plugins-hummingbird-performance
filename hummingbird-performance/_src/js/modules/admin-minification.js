/* global WPHB_Admin */
/* global wphb */
/* global ajaxurl */

/**
 * Asset Optimization scripts.
 *
 * @package
 */

import Fetcher from '../utils/fetcher';
import { getString, getLink } from '../utils/helpers';
import MinifyScanner from '../scanners/MinifyScanner';

/**
 * External dependencies
 */
let criticalAjaxInterval;
const ajaxExecutionInterval = 10000; // The interval set to 10 seconds

( function( $ ) {
	'use strict';

	WPHB_Admin.minification = {
		module: 'minification',
		$checkFilesResultsContainer: null,
		checkURLSList: null,
		checkedURLS: 0,
		SELECTORS: [
			'#item_delay_js_post_urls_exclusion',
			'#item_delay_js_all_exclusions',
			'#item_delay_js_plugins_themes_exclusion',
			'#item_delay_js_post_types_exclusion',
			'#item_delay_js_files_exclusion',
			'#item_delay_js_exclusions',
			'#item_delay_js_ads_tracker_exclusion',
			'#item_delay_js_default_exclusions',
			'#item_critical_css_all_exclusions',
			'#item_critical_css_files_exclusion',
			'#item_critical_css_post_types_exclusion',
			'#item_critical_css_post_urls_exclusion',
			'#item_critical_css_plugins_themes_exclusion',
			'#item_critical_css_keywords',
		],
		EXCLUSION_SELECTORS: [
			'#item_delay_js_all_exclusions',
			'#item_critical_css_all_exclusions'
		],
		CONTAINER_CLASSES: {
			delayJs: '.js_exclusion_container',
			criticalCss: '.critical_css_exclusion_container'
		},
		ALL_EXCLUSIONS: {
			delayJs: 'delay_js_all_exclusions',
			criticalCss: 'critical_css_all_exclusions'
		},

		exclusionType: 'delay_js',

		init() {
			const self = this;
			if ( 'undefined' !== typeof wphb.minification.criticalStatusForQueue.status && ( 'pending' === wphb.minification.criticalStatusForQueue.status || 'processing' === wphb.minification.criticalStatusForQueue.status ) ) {
				criticalAjaxInterval = setInterval( this.criticalUpdateStatusNotice, ajaxExecutionInterval );
			}

			// Init files scanner.
			this.scanner = new MinifyScanner(
				wphb.minification.get.totalSteps,
				wphb.minification.get.currentScanStep
			);

			// Initialize exclusion selectors and event handlers.
			this.initializeSelectorsAndEventHandlers();

			// Check files button.
			$( '#check-files' ).on( 'click', function( e ) {
				e.preventDefault();
				$( document ).trigger( 'check-files' );
			} );

			$( '#hb_cdn_upsell' ).on( 'click', function( e ) {
				window.wphbMixPanel.trackHBUpsell( 'cdn', 'ao_settings_link', 'cta_clicked', e.target.href, 'hb_cdn_upsell' );
			} );

			$( document ).on( 'check-files', function() {
				window.SUI.openModal( 'check-files-modal', 'wpbody-content', 'check-files-modal' );
				$( this ).attr( 'disabled', true );
				self.scanner.start();
			} );

			// CDN checkbox update status
			const checkboxes = $( 'input[type=checkbox][name=use_cdn]' );
			checkboxes.on( 'change', function() {
				$( '#cdn_file_exclude' ).toggleClass( 'sui-hidden' );
				const cdnValue = $( this ).is( ':checked' );

				// Handle two CDN checkboxes on Asset Optimization page
				checkboxes.each( function() {
					this.checked = cdnValue;
				} );

				// Update CDN status
				Fetcher.minification.toggleCDN( cdnValue ).then( () => {
					WPHB_Admin.notices.show();
				} );
			} );

			$( '#delay_js_keywords_advanced_view' ).on( 'change', () => this.toggleAdvancedKeywordsView() );

			// Delay Js Execution checkbox update status.
			$( 'input[type=checkbox][name=delay_js]' ).on(
				'change',
				function() {
					$( '#delay_js_file_exclude' ).toggleClass( 'sui-hidden' );
				}
			);

			// Critical CSS checkbox update status.
			const criticalCss = $( 'input[type=checkbox][name=critical_css_option]' );
			criticalCss.on( 'change', function() {
				$( '#critical_css_file_exclude' ).toggleClass( 'sui-hidden' );
			} );

			// Critical CSS checkbox update status.
			const criticalCssType = $( 'select[name=critical_css_type]' );
			criticalCssType.on( 'change', function( e ) {
				$( '.load_cs_options' ).addClass( 'sui-hidden' );
				$( '.load_' + e.target.value ).removeClass( 'sui-hidden' );
			} );

			$( '#manual_css_switch_now' ).on( 'click', function() {
				if ( ! wphbReact.isMember ) {
					self.hbTrackEoMPEvent( this );
					return;
				}

				window.WPHB_Admin.minification.criticalCSSSwitchMode( 'critical_css' );
			} );

			// Font display radio update status.
			$( 'input[type=radio][name=font_display_value]' ).on(
				'change',
				function() {
					const fontDisplayValue = $(this).val();
					$( '.font_display_safe_helper' ).toggle( fontDisplayValue === 'swap' );
					$( '.font_display_performant_helper' ).toggle( fontDisplayValue === 'optional' );
				}
			);

			// Font swap checkbox update status.
			$( 'input[type=checkbox][name=font_swap]' ).on(
				'change',
				function() {
					$( '#font_display_settings' ).toggleClass( 'sui-hidden' );
				}
			);

			// Font optimization checkbox update status.
			$( 'input[type=checkbox][name=font_optimization]' ).on(
				'change',
				function() {
					$( '#font_optimization_preload_box' ).toggleClass( 'sui-hidden' );
				}
			);

			// Preload fonts mode option changed.
			$( 'input[type=radio][name=preload_fonts_mode]' ).on(
				'change',
				function() {
					const fontDisplayValue = $(this).val();
					$( '.preload_fonts_mode_automatic_helper' ).toggle( fontDisplayValue === 'automatic' );
					$( '.preload_fonts_mode_manuel_helper' ).toggle( fontDisplayValue === 'manual' );
				}
			);

			$( 'input[type=checkbox][name=debug_log]' ).on(
				'change',
				function() {
					const enabled = $( this ).is( ':checked' );
					if ( enabled ) {
						$( '.wphb-logging-box' ).show();
					} else {
						$( '.wphb-logging-box' ).hide();
					}
				}
			);

			/**
			 * Save critical css file
			 */
			$( '#wphb-minification-tools-form' ).on( 'submit', function( e ) {
				e.preventDefault();

				const spinner = $( this ).find( '.spinner' );
				spinner.addClass( 'visible' );

				Fetcher.minification
					.saveCriticalCss( $( this ).serialize() )
					.then( ( response ) => {
						spinner.removeClass( 'visible' );
						if ( 'undefined' !== typeof response && response.success ) {
							const eventUpdateSummary = new Event( 'reloadSummary' );
							document.getElementById( 'wphb-minification-tools-form' ).dispatchEvent( eventUpdateSummary );
							if ( response.is_delay_value_updated ) {
								window.wphbMixPanel.trackDelayJSEvent( {
									update_type: response.delay_js_update_type,
									Location: 'eo_settings',
									Timeout: response.delay_js_timeout,
									'Excluded Files': ( response.delay_js_exclude ) ? 'yes' : 'no',
									exclusions_files: response.delayJsMixpanelValues.exclusions_files,
									exclusions_posttypes: response.delayJsMixpanelValues.exclusions_posttypes,
									exclusions_urls: response.delayJsMixpanelValues.exclusions_urls,
									exclusions_plugins: response.delayJsMixpanelValues.exclusions_plugins,
									exclusions_trackers: response.delayJsMixpanelValues.exclusions_trackers,
									exclusions_keywords: response.delayJsMixpanelValues.exclusions_keywords,
								} );
							}

							if ( response.fontOptimizationUpdateType ) {
								window.wphbMixPanel.trackFontOptimizationEvent( response.fontOptimizationUpdateType, 'font_preload' );
							}

							if ( response.fontSwapUpdateType ) {
								window.wphbMixPanel.trackFontOptimizationEvent( response.fontSwapUpdateType, 'font_swapping' );
							}

							if ( response.isCriticalValueUpdated ) {
								window.wphbMixPanel.trackCriticalCSSEvent( {
									update_type: response.updateType,
									Location: response.location,
									mode: response.mode,
									settings_modified: response.settingsModified,
									settings_default: response.settingsDefault,
									exclusions_files: response.criticalCssMixpanelValues.exclusions_files,
									exclusions_posttypes: response.criticalCssMixpanelValues.exclusions_posttypes,
									exclusions_urls: response.criticalCssMixpanelValues.exclusions_urls,
									exclusions_plugins: response.criticalCssMixpanelValues.exclusions_plugins,
									exclusions_keywords: response.criticalCssMixpanelValues.exclusions_keywords,
									manual_inclusions: response.manualInclusion,
								} );
							}

							if ( response.isStatusTagNeedsUpdate ) {
								self.triggerCriticalStatusUpdateAjax( response.htmlForStatusTag );
							} else if ( 'deactivate' === response.updateType ) {
								self.criticalUpdateStatusTag( response.htmlForStatusTag );
							}

							const styleType = 'activate' === response.updateType ? 'block' : 'deactivate' === response.updateType ? 'none' : '';
							self.hbToggleElement( 'wphb-clear-critical-css', styleType );

							WPHB_Admin.notices.show( response.message, 'blue', false );
						} else {
							WPHB_Admin.notices.show( response.message, 'error' );
						}
					} );
			} );

			/**
			 * Parse custom asset dir input
			 *
			 * @since 1.9
			 */
			const textField = document.getElementById( 'file_path' );
			if ( null !== textField ) {
				textField.onchange = function( e ) {
					e.preventDefault();
					Fetcher.minification
						.updateAssetPath( $( this ).val() )
						.then( ( response ) => {
							if ( response.message ) {
								WPHB_Admin.notices.show( response.message, 'error' );
							} else {
								WPHB_Admin.notices.show();
							}
						} );
				};
			}

			/**
			 * Asset optimization network settings page.
			 *
			 * @since 2.0.0
			 */

			// Show/hide settings, based on checkbox value.
			$( '#wphb-network-ao' ).on( 'click', function() {
				$( '#wphb-network-border-frame' ).toggleClass( 'sui-hidden' );
			} );

			// Handle settings select.
			$( '#wphb-box-minification-network-settings' ).on(
				'change',
				'input[type=radio]',
				function( e ) {
					const divs = document.querySelectorAll(
						'input[name=' + e.target.name + ']'
					);

					// Toggle logs frame.
					if ( 'log' === e.target.name ) {
						$( '.wphb-logs-frame' ).toggle( e.target.value );
					}

					for ( let i = 0; i < divs.length; ++i ) {
						divs[ i ].parentNode.classList.remove( 'active' );
					}

					e.target.parentNode.classList.add( 'active' );
				}
			);

			// Network settings.
			$( '#wphb-ao-network-settings' ).on( 'click', function( e ) {
				e.preventDefault();

				const spinner = $( '.sui-box-footer' ).find( '.spinner' );
				spinner.addClass( 'visible' );

				const form = $( '#ao-network-settings-form' ).serialize();
				Fetcher.minification
					.saveNetworkSettings( form )
					.then( ( response ) => {
						spinner.removeClass( 'visible' );
						if ( 'undefined' !== typeof response && response.success ) {
							WPHB_Admin.notices.show();
						} else {
							WPHB_Admin.notices.show( getString( 'errorSettingsUpdate' ), 'error' );
						}
					} );
			} );

			/**
			 * Save exclusion rules.
			 */
			$( '#wphb-ao-settings-update' ).on( 'click', function( e ) {
				e.preventDefault();

				const spinner = $( '.sui-box-footer' ).find( '.spinner' );
				spinner.addClass( 'visible' );

				const data = self.getMultiSelectValues( 'cdn_exclude' );
				const debugLog = $( 'input[name=debug_log]' ).is( ':checked' );
				const requestData = {
					excludeAssets: data,
					debugLog
				};

				Fetcher.minification
					.updateExcludeList( JSON.stringify( requestData ) )
					.then( () => {
						spinner.removeClass( 'visible' );
						WPHB_Admin.notices.show();
					} );
			} );

			/**
			 * Asset optimization 2.0
			 *
			 * @since 2.6.0
			 */

			// How does it work? stuff.
			const expandButtonManual = document.getElementById( 'manual-ao-hdiw-modal-expand' );
			if ( expandButtonManual ) {
				expandButtonManual.onclick = function() {
					document.getElementById( 'manual-ao-hdiw-modal' ).classList.remove( 'sui-modal-sm' );
					document.getElementById( 'manual-ao-hdiw-modal-header-wrap' ).classList.remove( 'sui-box-sticky' );
					document.getElementById( 'automatic-ao-hdiw-modal' ).classList.remove( 'sui-modal-sm' );
				};
			}

			const collapseButtonManual = document.getElementById( 'manual-ao-hdiw-modal-collapse' );
			if ( collapseButtonManual ) {
				collapseButtonManual.onclick = function() {
					document.getElementById( 'manual-ao-hdiw-modal' ).classList.add( 'sui-modal-sm' );
					const el = document.getElementById( 'manual-ao-hdiw-modal-header-wrap' );
					if ( el.classList.contains( 'video-playing' ) ) {
						el.classList.add( 'sui-box-sticky' );
					}
					document.getElementById( 'automatic-ao-hdiw-modal' ).classList.add( 'sui-modal-sm' );
				};
			}

			// How does it work? stuff.
			const expandButtonAuto = document.getElementById( 'automatic-ao-hdiw-modal-expand' );
			if ( expandButtonAuto ) {
				expandButtonAuto.onclick = function() {
					document.getElementById( 'automatic-ao-hdiw-modal' ).classList.remove( 'sui-modal-sm' );
					document.getElementById( 'manual-ao-hdiw-modal' ).classList.remove( 'sui-modal-sm' );
				};
			}

			const collapseButtonAuto = document.getElementById( 'automatic-ao-hdiw-modal-collapse' );
			if ( collapseButtonAuto ) {
				collapseButtonAuto.onclick = function() {
					document.getElementById( 'automatic-ao-hdiw-modal' ).classList.add( 'sui-modal-sm' );
					document.getElementById( 'manual-ao-hdiw-modal' ).classList.add( 'sui-modal-sm' );
				};
			}

			const autoTrigger = document.getElementById( 'hdw-auto-trigger-label' );
			if ( autoTrigger ) {
				autoTrigger.addEventListener( 'click', () => {
					window.SUI.replaceModal(
						'automatic-ao-hdiw-modal-content',
						'wphb-basic-hdiw-link'
					);
				} );
			}

			const manualTrigger = document.getElementById( 'hdw-manual-trigger-label' );
			if ( manualTrigger ) {
				manualTrigger.addEventListener( 'click', () => {
					window.SUI.replaceModal(
						'manual-ao-hdiw-modal-content',
						'wphb-basic-hdiw-link'
					);
				} );
			}
			// Clear critical css files.
			$( '#wphb-clear-critical-css' ).on( 'click', ( e ) => {
				e.preventDefault();
				self.clearCriticalCss( e.target );
			} );
			return this;
		},

		/**
		 * Initialize selectors and set up event handlers for exclusions.
		 */
		initializeSelectorsAndEventHandlers() {
			const self = this;
			// Initialize all selectors
			$( this.SELECTORS.join( ', ' ) ).each( function() {
				// eslint-disable-next-line no-nested-ternary
				const config = this.id.includes( 'delay_js_exclusions' ) || this.id.includes( 'critical_css_keywords' ) ? self.getSelect2ConfigForTag() : this.id.includes( 'post_urls' ) ? self.getAjaxSelectConfig() : self.getCommonSelect2Config();
				const $select = $( this ).SUIselect2( config );
				// Add data-hb-exclusion-type to the selected option
				$select.on( 'select2:select select2:unselect', function( e ) {
					const isCriticalCss = this.id.includes( 'critical_css' );
					const $allExclusions = $( isCriticalCss ? '#item_critical_css_all_exclusions' : '#item_delay_js_all_exclusions' );
					self.toggleSelectBoxOption( $allExclusions, e, e.type === 'select2:select' );
					self.enableDisableResetButton();
					$( this ).SUIselect2( 'open' );
				} );
			} );

			// Set up event handlers
			this.EXCLUSION_SELECTORS.forEach( ( selector ) => {
				const containerClass = selector === '#item_delay_js_all_exclusions' ? this.CONTAINER_CLASSES.delayJs : this.CONTAINER_CLASSES.criticalCss;
				// Combine all exclusions
				self.combineAllExclusions( selector, containerClass );
				// Bind select and unselect events to each exclusion selector
				$( selector ).on( 'select2:select select2:unselect', function( e ) {
					const isAdding = e.type === 'select2:select';
					self.synchronizeExclusionValues( e.params.data.id, isAdding, containerClass, e );
				} );
			} );

			$( '#delay_js_exclusion_options, #critical_css_exclusion_options' ).SUIselect2( {
				templateSelection: ( data ) => this.formatSelectResult( data, false ),
				templateResult: ( data ) => this.formatSelectResult( data, false ),
				minimumResultsForSearch: Infinity,
			} );

			self.enableDisableResetButton();
			$( 'select[name=delay_js_exclusion_options], select[name=critical_css_exclusion_options]' ).on( 'change', self.toggleExclusionBox.bind( self ) );
		},

		/**
		 * Select or unselect a value in a select box.
		 *
		 * @param {jQuery}  $box         - The select box to update.
		 * @param {Object}  element      - The selected or unselected element.
		 * @param {boolean} shouldSelect - True to select the value, false to unselect it.
		 */
		toggleSelectBoxOption( $box, element, shouldSelect = true ) {
			const selectedValue = element.id || element.params?.data?.id;
			const text = element.text || element.params?.data?.text;
			const hbExclusionType = element?.element?.dataset?.hbExclusionType ?? element.params?.data?.element?.dataset?.hbExclusionType ?? element.params?.data?.hbExclusionType;
			// Check if the option exists; if not, add it with the necessary attributes
			let $option = $box.find( `option[value="${ selectedValue }"]` );
			if ( $option.length === 0 ) {
				$option = $( new Option( text, selectedValue ) ).attr( 'data-hb-exclusion-type', hbExclusionType );
				$box.append( $option );
			}

			// Toggle selection state only if there's a mismatch with shouldSelect
			if ( $option.prop( 'selected' ) !== shouldSelect ) {
				$option.prop( 'selected', shouldSelect );
				$box.trigger( 'change' );
			}
		},

		/**
		 * Synchronize a value across all relevant exclusion boxes, adding or removing it as specified.
		 *
		 * @param {string}  value         - The ID of the value to be synchronized across exclusion boxes.
		 * @param {boolean} isAdding      - True to add the value, false to remove it.
		 * @param {string}  containerType - The type of container to target (e.g., `.js_exclusion_container`).
		 * @param {Object}  element       - The selected or unselected element.
		 */
		synchronizeExclusionValues( value, isAdding = true, containerType = '.js_exclusion_container', element ) {
			const exclusionType = element?.element?.dataset?.hbExclusionType ?? element.params?.data?.element?.dataset?.hbExclusionType ?? element.params?.data?.hbExclusionType;
			const targetBoxId = this.getExclusionTypeData( exclusionType );
			// Determine the correct box to target
			const $box = containerType.includes( 'critical_css' ) ? $( `#${ targetBoxId.criticalCssBox }` ) : $( `#${ targetBoxId.delayBox }` );

			// If the box doesn't exist, exit early
			if ( ! $box.length ) {
				return;
			}

			const currentValues = $box.val() || [];

			if ( isAdding ) {
				// Add the value if it's not already present
				if ( ! currentValues.includes( value ) ) {
					const $option = $box.find( `option[value="${ value }"]` );
					$option.prop( 'selected', true );
					$box.trigger( 'change' );
				}
			} else {
				// Remove the value if it exists
				const updatedValues = currentValues.filter( ( val ) => val !== value );
				$box.val( updatedValues ).trigger( 'change.select2' );
			}
		},

		/**
		 * Get common select2 configuration.
		 */
		getCommonSelect2Config() {
			return {
				templateSelection: this.formatSelectResult.bind( this ),
				templateResult: this.formatSelectResult.bind( this ),
				closeOnSelect: false,
			};
		},

		/**
		 * Get common select2 configuration.
		 */
		getSelect2ConfigForTag() {
			return {
				...this.getCommonSelect2Config(),
				tags: true,
				closeOnSelect: true,
				createTag: ( params ) => {
					// Format the newly created tag
					const term = params.term.trim();
					if ( term === '' ) {
						return null;
					}

					return {
						id: term,
						text: term,
						isNew: true,
						hbExclusionType: 'keywords'
					};
				},
				tokenSeparators: [ ',', ' ', '\n' ],
				language: {
					noResults() {
						return wphb.strings.select2Tags;
					}
				},
			};
		},

		/**
		 * Get ajax select configuration.
		 */
		getAjaxSelectConfig() {
			return {
				...this.getCommonSelect2Config(),
				minimumInputLength: 3,
				ajax: {
					url: ajaxurl,
					method: 'POST',
					dataType: 'json',
					delay: 250,
					data: ( params ) => ( {
						action: 'wphb_search_posts',
						nonce: wphb.nonces.HBFetchNonce,
						query: params.term,
					} ),
					processResults: ( data ) => ( {
						results: data.data.map( ( item ) => ( {
							text: item.name,
							id: item.id,
							hbExclusionType: item.label,
						} ) ),
					} ),
				},
			};
		},

		/**
		 * Toggle the exclusion box based on the selected option.
		 *
		 * @param {Event} e
		 * @since 3.11.0
		 */
		toggleExclusionBox( e ) {
			const { value, name } = e.target;
			const isDelayJs = name === 'delay_js_exclusion_options';
			const containerClass = isDelayJs ? this.CONTAINER_CLASSES.delayJs : this.CONTAINER_CLASSES.criticalCss;

			$( containerClass ).addClass( 'sui-hidden' );
			$( '#' + value ).removeClass( 'sui-hidden' );

			this.enableDisableResetButton();

			const advancedViewLabel = $( '#delay_js_keywords_advanced_view_label' );
			const delayJsExclusions = $( '#delay_js_legacy_keywords_container' );

			if ( isDelayJs && value === 'delay_js_exclusions' ) {
				this.copySelect2ToTextarea();
				this.toggleAdvancedKeywordsView();
				advancedViewLabel.removeClass( 'sui-hidden' );
			} else {
				if ( value === 'delay_js_all_exclusions' ) {
					this.syncTextareaSelectData( ! this.isAdvancedViewChecked() );
				}
				advancedViewLabel.addClass( 'sui-hidden' );
				delayJsExclusions.addClass( 'sui-hidden' );
			}
		},

		/**
		 * Toggle advanced view for keywords.
		 */
		toggleAdvancedKeywordsView() {
			const isAdvancedView = this.isAdvancedViewChecked();
			this.syncTextareaSelectData( isAdvancedView );
			const delayJsExclusions = $( '#delay_js_exclusions' );
			const legacyKeywords = $( '#delay_js_legacy_keywords_container' );

			delayJsExclusions.toggleClass( 'sui-hidden', isAdvancedView );
			legacyKeywords.toggleClass( 'sui-hidden', ! isAdvancedView );
		},

		/**
		 * Checks if the advanced view toggle is checked.
		 *
		 * @return {boolean} True if the advanced view is enabled, otherwise false.
		 */
		isAdvancedViewChecked() {
			return $( '#delay_js_keywords_advanced_view' ).is( ':checked' );
		},

		/**
		 * Syncs data between the textarea and the select2 component based on the view.
		 *
		 * @param {boolean} isAdvancedView - Whether the advanced view is active.
		 */
		syncTextareaSelectData( isAdvancedView ) {
			// eslint-disable-next-line no-unused-expressions
			isAdvancedView ? this.copySelect2ToTextarea() : this.copyTextareaToSelect2();
		},

		/**
		 * Copy textarea data to select2.
		 */
		copyTextareaToSelect2() {
			const textareaData = $( '#delay_js_legacy_keywords' ).val();
			const tags = textareaData.split( '\n' ).map( ( line ) => line.trim() ).filter( ( line ) => line );
			const select2Element = $( '#item_delay_js_exclusions' );
			const allExclusionsBox = $( '#item_delay_js_all_exclusions' );

			if ( select2Element.length && select2Element.data( 'select2' ) ) {
				const existingOptions = select2Element.find( 'option' ).map( ( _, option ) => option.value ).get();
				const allExclusionsOptions = allExclusionsBox.find( 'option' ).map( ( _, option ) => option.value ).get();

				tags.forEach( ( tag ) => {
					const $option = select2Element.find( `option[value="${ tag }"]` );
					if ( $option.length === 0 ) {
						const newOption = $( new Option( tag, tag, true, true ) ).attr( 'data-hb-exclusion-type', 'keywords' );
						select2Element.append( newOption );

						if ( ! allExclusionsOptions.includes( tag ) ) {
							this.toggleSelectBoxOption( allExclusionsBox, { id: tag, text: tag, element: newOption[ 0 ] }, true );
						}
					} else {
						$option.prop( 'selected', true );
						this.toggleSelectBoxOption( allExclusionsBox, { id: tag, text: tag, element: $option[ 0 ] }, true );
					}
				} );

				existingOptions.forEach( ( existingValue ) => {
					if ( ! tags.includes( existingValue ) ) {
						const $existingOption = select2Element.find( `option[value="${ existingValue }"]` );
						$existingOption.prop( 'selected', false );

						if ( allExclusionsOptions.includes( existingValue ) ) {
							this.toggleSelectBoxOption( allExclusionsBox, { id: existingValue, text: existingValue }, false );
						}
					}
				} );

				select2Element.trigger( 'change' );
			}
		},

		/**
		 * Copy select2 data to textarea.
		 */
		copySelect2ToTextarea() {
			const tags = $( '#item_delay_js_exclusions' ).val() || [];
			const trimmedTags = tags.map( ( tag ) => tag.trim() );
			$( '#delay_js_legacy_keywords' ).val( trimmedTags.join( '\n' ) );
		},

		/**
		 * Enable or disable the reset button based on the selected option.
		 */
		enableDisableResetButton() {
			const options = [
				{
					selector: '#delay_js_exclusion_options', modal: '.reset-delay-exclusion-modal'
				},
				{
					selector: '#critical_css_exclusion_options', modal: '.reset-critical-exclusion-modal'
				}
			];

			options.forEach( ( { selector, modal } ) => {
				const exclusionData = $( `#item_${ $( selector ).val() }` ).val() || '';
				$( modal ).toggleClass( 'disabled', exclusionData.length === 0 );
			} );
		},

		/**
		 * Format data for select2.
		 *
		 * @param {Object}  data
		 * @param {boolean} includeChevron - Whether to include the chevron icon in the label.
		 */
		formatSelectResult( data, includeChevron = true ) {
			if ( ! data.id ) {
				return data.text;
			}

			const key = data?.hbExclusionType || $( data.element ).data( 'hb-exclusion-type' );
			const item = this.getExclusionTypeData( key );
			let selectLabel = data.text;

			if ( item ) {
				selectLabel = item.icon + data.text;
				if ( includeChevron ) {
					selectLabel = item.icon + item.name + this.getIcon( 'chevron-right' ) + data.text;
				}
			}

			return $( '<span></span>' ).html( selectLabel );
		},

		/**
		 * Get name or icon for the key.
		 *
		 * @param {string} key
		 */
		getExclusionTypeData( key ) {
			const dataMap = {
				files: { name: wphb.strings.exclusionFiles, icon: this.getIcon( 'page' ), delayBox: 'item_delay_js_files_exclusion', criticalCssBox: 'item_critical_css_files_exclusion' },
				core_file: { name: wphb.strings.exclusionWpFile, icon: this.getIcon( 'page' ), delayBox: 'item_delay_js_files_exclusion', criticalCssBox: 'item_critical_css_files_exclusion' },
				posts: { name: wphb.strings.exclusionPostTypes, icon: this.getIcon( 'post-pin' ), delayBox: 'item_delay_js_post_types_exclusion', criticalCssBox: 'item_critical_css_post_types_exclusion' },
				urls: { name: wphb.strings.exclusionPostUrls, icon: this.getIcon( 'link' ), delayBox: 'item_delay_js_post_urls_exclusion', criticalCssBox: 'item_critical_css_post_urls_exclusion' },
				plugins: { name: wphb.strings.exclusionPluginThemes, icon: this.getIcon( 'plugin-2' ), delayBox: 'item_delay_js_plugins_themes_exclusion', criticalCssBox: 'item_critical_css_plugins_themes_exclusion' },
				keywords: { name: wphb.strings.exclusionKeywords, icon: this.getIcon( 'key' ), delayBox: 'item_delay_js_exclusions', criticalCssBox: 'item_critical_css_keywords' },
				default: { name: wphb.strings.exclusionDefault, icon: this.getIcon( 'key' ), delayBox: 'item_delay_js_exclusions', criticalCssBox: null },
				ads: { name: wphb.strings.exclusionAds, icon: this.getIcon( 'graph-line' ), delayBox: 'item_delay_js_ads_tracker_exclusion', criticalCssBox: null },
				default_exclusions: { name: wphb.strings.exclusionDefault, icon: this.getIcon( 'widget-settings-config' ), delayBox: null, criticalCssBox: null },
				all_exclusions: { name: wphb.strings.exclusionAll, icon: this.getIcon( 'thumbnails' ), delayBox: null, criticalCssBox: null },
			};

			return dataMap[ key ] || null;
		},

		/**
		 * Helper function to get icon markup.
		 *
		 * @param {string} iconName
		 */
		getIcon( iconName ) {
			return `<span class="sui-icon sui-icon-${ iconName }" aria-hidden="true"></span>`;
		},

		/**
		 * Combine exclusion to all exclusion box.
		 *
		 * @param {string} itemAllExclusionsSelector
		 * @param {string} exclusionContainerSelector
		 */
		combineAllExclusions( itemAllExclusionsSelector, exclusionContainerSelector ) {
			const self = this;
			const itemAllExclusions = $( itemAllExclusionsSelector );

			// Gather selected options from each exclusion box except the All Exclusions box
			$( exclusionContainerSelector + ' > select' ).not( `${ itemAllExclusionsSelector }, #item_delay_js_default_exclusions` ).each( function() {
				const $this = $( this );
				$this.SUIselect2( 'data' ).forEach( ( option ) => {
					if ( $this.val().includes( option.id ) ) {
						self.toggleSelectBoxOption( itemAllExclusions, option );
					}
				} );
			} );
		},

		/**
		 * Parse confirm exclusion reset from the modal.
		 *
		 * @param {Object} element
		 */
		confirmReset( element ) {
			this.exclusionType = [ 'delay_js', 'critical_css' ].includes( element.id ) ? element.id : null;
			if ( this.exclusionType ) {
				$( '[id$="_reset_modal_content"]' ).addClass( 'sui-hidden' );
				$( `#${ this.exclusionType }_reset_modal_content` ).removeClass( 'sui-hidden' );
				window.SUI.openModal( 'wphb-reset-exclusions-modal', 'wpbody-content' );
			}
		},

		/**
		 * Make an ajax call to reset the exclusion.
		 */
		resetExclusion() {
			const exclusionOptions = $( `select[name=${ this.exclusionType }_exclusion_options]` ).val();
			if ( exclusionOptions ) {
				Fetcher.minification.resetExclusions( exclusionOptions, this.exclusionType ).then( () => {
					window.location.reload();
				} );
			}
		},

		/**
		 * Call ajax to get the critical css status for queue.
		 *
		 * @param {string} statusHtml
		 */
		triggerCriticalStatusUpdateAjax( statusHtml ) {
			criticalAjaxInterval = setInterval( this.criticalUpdateStatusNotice, ajaxExecutionInterval );
			this.criticalUpdateStatusTag( statusHtml );
		},

		/**
		 * Call ajax to get the critical css status for queue.
		 */
		criticalUpdateStatusNotice() {
			Fetcher.minification
				.getCriticalStatusForQueue()
				.then( ( response ) => {
					if ( 'undefined' !== typeof response.criticalStatusForQueue.status && 'complete' === response.criticalStatusForQueue.status ) {
						clearInterval( criticalAjaxInterval );
						WPHB_Admin.minification.criticalUpdateStatusTag( response.htmlForStatusTag );
						const criticalDisplayError = 'critical_display_error_message';

					if ( 'COMPLETE' === response.criticalStatusForQueue.result ) {
						WPHB_Admin.notices.show( getString( 'criticalGeneratedNotice' ), 'success', false );
						WPHB_Admin.minification.hbToggleElement( criticalDisplayError, 'none' );
					} else if ( 'ERROR' === response.criticalStatusForQueue.result ) {
						window.SUI.closeNotice( 'wphb-ajax-update-notice' );
						const errorMessage = response.criticalStatusForQueue.error_message;
						window.wphbMixPanel.track( 'critical_css_error', {
							mode: response.criticalMode,
							'Error Type': response.errorCode,
							'Error Message': errorMessage.length > 256 ? errorMessage.substring( 0, 256 ) + '...' : errorMessage
						} );
						WPHB_Admin.minification.hbToggleElement( criticalDisplayError, 'block' );
						document.getElementById( 'critical_error_message_tag' ).innerHTML = response.criticalErrorMessage;
					}
				}
			});
		},

		/**
		 * Update the status html.
		 *
		 * @param {string} statusHtml
		 */
		 criticalUpdateStatusTag( statusHtml ) {
			document.getElementById( 'critical_progress_tag' ).remove();
			document.getElementById( 'generate_css_label' ).insertAdjacentHTML( 'afterend', statusHtml );
		},

		/**
		 * Toggle an element.
		 */
		hbToggleElement( elementId, styleType ) {
			if ( '' === styleType ) {
				return;
			}

			const regenerateButton = document.getElementById( elementId );
			regenerateButton.style.display = styleType;
		},

		/**
		 * Track MP event for delay js and critical css.
		 *
		 * @param {object} element
		 */
		hbTrackEoMPEvent( element ) {
			window.wphbMixPanel.trackEoUpsell( element.dataset.eventname, element.dataset.location );
		},

		/**
		 * Track MP event on AO activation.
		 */
		hbTrackMPOnAoActivate() {
			window.wphbMixPanel.enableFeature( 'Asset Optimization' );
			window.wphbMixPanel.trackAOUpdated( {
				Mode: [ 'AO_Combine', 'AO_Compress', 'speedy' ],
				assets_found: 0,
				location: 'ao_settings',
				total_files: 0,
				filesize_reductions: 0,
			} );
		},

		/**
		 * Switch from advanced to basic view.
		 * Called from switch view modal.
		 *
		 * @param {string} mode
		 */
		switchView( mode ) {
			let hide = false;
			const trackBox = document.getElementById(
				'hide-' + mode + '-modal'
			);

			if ( trackBox && true === trackBox.checked ) {
				hide = true;
			}

			Fetcher.minification.toggleView( mode, hide ).then( ( response ) => {
				window.wphbMixPanel.trackAOUpdated( {
					Mode: response.mode,
					assets_found: wphb.stats.assetsFound,
					total_files: wphb.stats.totalFiles,
					filesize_reductions: wphb.stats.filesizeReductions,
					location: 'ao_settings',
				} );

				window.location.href = getLink( 'minification' );
			} );
		},

		/**
		 * Go to the Asset Optimization files page.
		 *
		 * @since 1.9.2
		 * @since 2.1.0  Added show_tour parameter.
		 * @since 2.6.0  Remove show_tour parameter.
		 */
		goToSettings() {
			window.SUI.closeModal();

			Fetcher.minification
				.toggleCDN( $( 'input#enable_cdn' ).is( ':checked' ) )
				.then( () => {
					window.location.href = getLink( 'minification' );
				} );
		},

		/**
		 * Get all selected values from multiselect.
		 *
		 * @since 2.6.0
		 *
		 * @param {string} id Select ID.
		 * @return {{styles: *[], scripts: *[]}}  Styles & scripts array.
		 */
		getMultiSelectValues( id ) {
			const selected = $( '#' + id ).find( ':selected' );

			const data = { scripts: [], styles: [] };

			for ( let i = 0; i < selected.length; ++i ) {
				data[ selected[ i ].dataset.type ].push( selected[ i ].value );
			}

			return data;
		},

		/**
		 * Skip upgrade.
		 *
		 * @since 2.6.0
		 */
		skipUpgrade() {
			Fetcher.common.call( 'wphb_ao_skip_upgrade' ).then( () => {
				window.location.href = getLink( 'minification' );
			} );
		},

		/**
		 * Perform AO upgrade.
		 *
		 * @since 2.6.0
		 */
		doUpgrade() {
			Fetcher.common.call( 'wphb_ao_do_upgrade' ).then( () => {
				window.location.href = getLink( 'minification' );
			} );
		},

		/**
		 * Purge asset optimization orphaned data.
		 *
		 * @since 3.1.2
		 * @see Admin\Pages\Minification::orphaned_notice
		 */
		purgeOrphanedData() {
			const count = document.getElementById( 'count-ao-orphaned' )
				.innerHTML;

			Fetcher.advanced.clearOrphanedBatch( count ).then( () => {
				window.location.reload();
			} );
		},

		/**
		 * Clear critical CSS.
		 *
		 * @since 3.6.0
		 *
		 * @param {Object} target Target button that was clicked.
		 */
		clearCriticalCss: ( target ) => {
			target.classList.add( 'sui-button-onload-text' );
			Fetcher.minification.clearCriticalCssFiles().then( ( response ) => {
				if ( 'undefined' !== typeof response && response.success ) {
					window.wphbMixPanel.track( 'critical_css_cache_purge', {
						location: 'eo_settings'
					} );

					WPHB_Admin.minification.triggerCriticalStatusUpdateAjax( response.htmlForStatusTag );
					$( '.box-caching-summary span.sui-summary-large' ).html( '0' ); 
					WPHB_Admin.notices.show( getString( 'successCriticalCssPurge' ), 'blue', false );
				} else {
					WPHB_Admin.notices.show( getString( 'errorCriticalCssPurge' ), 'error' );
				}
			} ).finally( () => target.classList.remove( 'sui-button-onload-text' ) );
		},

		criticalCSSSwitchMode( mode ) {
			$('#critical_css_mode').val( mode )
			if ( 'manual_css' === mode ) {
				$("#manual_css_delivery_box").removeClass('sui-hidden');
				$("#critical_css_delivery_box").addClass('sui-hidden');
			} else {
				$("#manual_css_delivery_box").addClass('sui-hidden');
				$("#critical_css_delivery_box").removeClass('sui-hidden');
				const manualCriticalBox = document.getElementById( 'manual_critical_css' ).value;
				const advancedCriticalBox = document.getElementById( 'critical_css_advanced' ).value;

				if ( '' === advancedCriticalBox ) {
					document.getElementById( 'critical_css_advanced' ).value = manualCriticalBox;
				}
			}
		},
	}; // End WPHB_Admin.minification.
}( jQuery ) );
