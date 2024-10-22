import Scanner from '../utils/scanner';
import Fetcher from '../utils/fetcher';
import { getString, getLink } from '../utils/helpers';

class PerfScanner extends Scanner {
	/**
	 * Execute a scan step recursively.
	 *
	 * @param {number} remainingSteps
	 */
	step( remainingSteps ) {
		super.step( remainingSteps );

		this.currentStep++;

		// Update progress bar.
		this.updateProgressBar( this.getProgress() );

		Fetcher.common
			.call( 'wphb_performance_run_test' )
			.then( ( response ) => {
				if ( ! response.finished ) {
					// Try again 3 seconds later
					window.setTimeout( () => {
						this.step( this.totalSteps - this.currentStep );
					}, 3000 );
				} else {
					this.onFinish( response );
				}
			} );
	}

	updateProgressBar( progress, cancel = false ) {
		// Test has been initialized.
		if ( 0 === progress ) {
			this.currentStep = 2;

			this.timer = window.setInterval( () => {
				this.currentStep += 1;
				this.updateProgressBar( this.getProgress() );
			}, 100 );
		}

		const progressStatus = document.querySelector(
			'.wphb-performance-scan-modal .sui-progress-state .sui-progress-state-text'
		);

		if ( 3 === progress ) {
			progressStatus.innerHTML = getString( 'scanRunning' );
		}

		if ( 73 === progress ) {
			clearInterval( this.timer );
			this.timer = false;

			this.timer = window.setInterval( () => {
				this.currentStep += 1;
				this.updateProgressBar( this.getProgress() );
			}, 1000 );

			progressStatus.innerHTML = getString( 'scanAnalyzing' );
		}

		if ( 99 === progress ) {
			progressStatus.innerHTML = getString( 'scanWaiting' );
			clearInterval( this.timer );
			this.timer = false;
		}

		document.querySelector(
			'.wphb-performance-scan-modal .sui-progress-block .sui-progress-text span'
		).innerHTML = progress + '%';

		document.querySelector(
			'.wphb-performance-scan-modal .sui-progress-block .sui-progress-bar span'
		).style.width = progress + '%';

		if ( 100 === progress ) {
			const loader = document.querySelector(
				'.wphb-performance-scan-modal .sui-progress-block span.sui-icon-loader'
			);
			loader.classList.remove( 'sui-icon-loader', 'sui-loading' );
			loader.classList.add( 'sui-icon-check' );

			progressStatus.innerHTML = getString( 'scanComplete' );
			clearInterval( this.timer );
			this.timer = false;
		}
	}

	onStart() {
		return Promise.resolve();
	}

	retrieveValueFromObject( obj, prop ) {
		return obj && typeof obj[prop] !== 'undefined' ? obj[prop] : 'N/A';
	}

	onFinish( response ) {
		super.onFinish();

		window.wphbMixPanel.track( 'plugin_scan_finished', {
			score_mobile: response.mobileScore,
			score_desktop: response.desktopScore,
			active_features: response.HBSmushFeatures,
			'AO Status': response.aoStatus,
			cls_desktop: this.retrieveValueFromObject( response.hbPerformanceMetric, 'cls_desktop' ),
			cls_mobile: this.retrieveValueFromObject( response.hbPerformanceMetric, 'cls_mobile' ),
			fcp_desktop: this.retrieveValueFromObject( response.hbPerformanceMetric, 'fcp_desktop' ),
			fcp_mobile: this.retrieveValueFromObject( response.hbPerformanceMetric, 'fcp_mobile' ),
			inp_desktop: this.retrieveValueFromObject( response.hbPerformanceMetric, 'inp_desktop' ),
			inp_mobile: this.retrieveValueFromObject( response.hbPerformanceMetric, 'inp_mobile' ),
			lcp_desktop: this.retrieveValueFromObject( response.hbPerformanceMetric, 'lcp_desktop' ),
			lcp_mobile: this.retrieveValueFromObject( response.hbPerformanceMetric, 'lcp_mobile' ),
			speed_desktop: this.retrieveValueFromObject( response.hbPerformanceMetric, 'speed_desktop' ),
			speed_mobile: this.retrieveValueFromObject( response.hbPerformanceMetric, 'speed_mobile' ),
			tbt_desktop: this.retrieveValueFromObject( response.hbPerformanceMetric, 'tbt_desktop' ),
			tbt_mobile: this.retrieveValueFromObject( response.hbPerformanceMetric, 'tbt_mobile' ),
			ttfb_desktop: this.retrieveValueFromObject( response.hbPerformanceMetric, 'ttfb_desktop' ),
			ttfb_mobile: this.retrieveValueFromObject( response.hbPerformanceMetric, 'ttfb_mobile' ),
			lcp_ttfb_desktop: response.getLCPSubmetrics.lcp_ttfb.desktop,
			lcp_ttfb_mobile: response.getLCPSubmetrics.lcp_ttfb.desktop,
			lcp_load_delay_desktop: response.getLCPSubmetrics.lcp_load_delay.desktop,
			lcp_load_delay_mobile: response.getLCPSubmetrics.lcp_load_delay.mobile,
			lcp_render_delay_desktop: response.getLCPSubmetrics.lcp_render_delay.desktop,
			lcp_render_delay_mobile: response.getLCPSubmetrics.lcp_render_delay.mobile,
			lcp_load_time_desktop: response.getLCPSubmetrics.lcp_load_time.desktop,
			lcp_load_time_mobile: response.getLCPSubmetrics.lcp_load_time.mobile,
			lcp_element_desktop: response.getLCPSubmetrics.lcp_element.desktop,
			lcp_element_mobile: response.getLCPSubmetrics.lcp_element.mobile,
			audits_opportunities_desktop: response.getAudits.opportunities.desktop,
			audits_opportunities_mobile: response.getAudits.opportunities.mobile,
			audits_diagnostics_desktop: response.getAudits.diagnostics.desktop,
			audits_diagnostics_mobile: response.getAudits.diagnostics.mobile,
			audits_passed_desktop: response.getAudits.passed.desktop,
			audits_passed_mobile: response.getAudits.passed.mobile,
			core_web_vitals_mobile: response.coreWebVitalsMobile,
			core_web_vitals_desktop: response.coreWebVitalsDesktop,
			autoloader_health: response.autoloaderHealth,
			autoloader_size: response.autoloaderSize,
		} );

		if ( '' !== response.hasError ) {
			window.wphbMixPanel.track( 'performance_test_error', {
				'Error Message': response.hasError,
			} );
		}

		// Give a second for the report to be saved to the db.
		window.setTimeout( function() {
			window.location = getLink( 'audits' );
		}, 2000 );
	}
}

export default PerfScanner;
