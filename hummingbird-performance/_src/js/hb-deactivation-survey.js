// Deactivation survey.
export default class HBDeactivationSurvey {
	constructor() {
		this.modalId = 'wphb-deactivation-survey-modal';
		this.modal = document.getElementById( this.modalId );
		this.deactivatePluginLink = document.querySelector( 'a[id^="deactivate-hummingbird-pro"], a[id^="deactivate-hummingbird-performance"]' );
		this.reason = 'not_set';
		this.requestedAssistance = 'na';
		this.modalAction = 'close';
		this.userMessageField = null;
	}

	init() {
		if ( this.modal && this.deactivatePluginLink ) {
			this.setupSurveyModal();
		}
	}

	setupSurveyModal() {
		this.deactivatePluginLink.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			this.showModal();
			this.setupSurveyForm();
		} );
	}

	setupSurveyForm() {
		this.setupRequestAssistanceLink();
		this.setupRadioChange();
		this.setupButton( '.wphb-deactivate-without-feedback-button', 'skip' );
		this.setupButton( '.wphb-submit-feedback-deactivate-button', 'submit' );
		this.trackDeactivation();
	}

	setupRequestAssistanceLink() {
		const requestAssistanceLink = this.modal.querySelector( '#wphb-support-link' );
		if ( requestAssistanceLink ) {
			this.requestedAssistance = 'no';
			requestAssistanceLink.addEventListener( 'click', () => {
				this.requestedAssistance = 'yes';
			} );
		}
	}

	setupRadioChange() {
		this.userMessageField = document.getElementById( 'wphb-deactivation-feedback-field' );
		if ( ! this.userMessageField ) {
			return;
		}

		this.modal.querySelectorAll( 'input[type="radio"]' ).forEach( ( radio ) => {
			radio.addEventListener( 'change', () => {
				this.reason = radio.value;
				this.toggleUserMessageField( radio.parentElement );
			} );
		} );
	}

	setupButton( selector, action ) {
		const button = this.modal.querySelector( selector );
		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', ( e ) => {
			e.target.classList.add( 'sui-button-onload' );
			this.modalAction = action;
			this.closeModal();
			if ( ! wphb.mixpanel.enabled && action === 'skip' ) {
				this.redirectToDeactivateLink();
			}
		}, { once: true } );
	}

	handleEvent( selector, eventType, callback ) {
		const element = this.modal.querySelector( selector );
		if ( element ) {
			element.addEventListener( eventType, callback );
		}
	}

	toggleUserMessageField( labelField ) {
		if ( ! this.userMessageField || ! labelField.dataset?.placeholder ) {
			return;
		}

		const textarea = this.userMessageField.querySelector( 'textarea' );
		textarea.placeholder = labelField.dataset.placeholder;
		labelField.after( this.userMessageField );
		this.userMessageField.classList.remove( 'sui-hidden' );
		textarea.focus();
	}

	showModal() {
		window.SUI?.openModal( this.modalId, 'wpbody-content', undefined, true, false, true );
	}

	closeModal() {
		window.SUI?.closeModal( true );
	}

	trackDeactivation() {
		this.modal.addEventListener( 'afterClose', () => {
			if ( ! this.shouldTrack() ) {
				return;
			}

			const message = this.userMessageField.querySelector( 'textarea' ).value;
			const properties = {
				Reason: this.reason,
				Message: message,
				'Modal Action': this.modalAction,
				'Requested Assistance': this.requestedAssistance,
				'Tracking Status': wphb.mixpanel.enabled ? 'opted_in' : 'opted_out',
			};

			jQuery.ajax( {
				url: wphbGlobal.ajaxurl,
				method: 'POST',
				data: {
					nonce: wphbGlobal.nonce,
					action: 'wphb_track_deactivation',
					event: 'Deactivation Survey',
					properties,
				},
			} ).done( () => {
				if ( this.shouldDeactivatePlugin() ) {
					this.redirectToDeactivateLink();
				}
			} );
		}, { once: true } );
	}

	shouldTrack() {
		return wphb.mixpanel.enabled || this.isSubmitAction();
	}

	isSubmitAction() {
		return this.modalAction === 'submit';
	}

	shouldDeactivatePlugin() {
		return this.modalAction === 'skip' || this.isSubmitAction();
	}

	redirectToDeactivateLink() {
		window.location.href = this.deactivatePluginLink.href;
	}
}
