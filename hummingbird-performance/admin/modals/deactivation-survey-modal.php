<?php
/**
 * Deactivation Survey Modal
 */

use Hummingbird\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="<?php echo esc_attr( WPHB_SUI_VERSION ); ?>">
	<div class="sui-wrap">
		<div class="sui-modal sui-modal-lg">
			<div
				role="dialog"
				id="wphb-deactivation-survey-modal"
				class="sui-modal-content wphb-deactivation-survey-modal"
				aria-modal="true"
				aria-labelledby="title-wphb-deactivation-survey-modal"
				aria-describedby="desc-wphb-deactivation-survey-modal"
			>
				<div class="sui-box" role="document">
					<div class="sui-box-header">
						<h3 class="sui-box-title" style="white-space: nowrap;">
							<img style="margin-right:6px" src="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-icon.png' ); ?>" width="30" srcset="<?php echo esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-icon.png' ); ?> 2x" alt="<?php esc_attr_e( 'Hummingbird', 'wphb' ); ?>" aria-hidden="true" />
							<?php esc_html_e( 'Deactivate Hummingbird?', 'wphb' ); ?>
						</h3>
						<div class="sui-actions-right">
							<button type="button" class="sui-button-icon" onclick="window.SUI?.closeModal( true );">
								<span class="sui-icon-close sui-md" aria-hidden="true"></span>
								<span class="sui-screen-reader-text"><?php esc_html_e( 'Close this dialog window', 'wphb' ); ?></span>
							</button>
						</div>
					</div>
					<div class="sui-box-body">
						<p class="sui-description">
							<?php
							printf(
								/* translators: %s: Support link */
								esc_html__( 'Please tell us why. Your feedback helps us improve. %s', 'wphb' ),
								Utils::is_member() ? '<a id="wphb-support-link" style="text-decoration:underline" target="_blank" href="' . esc_url( Utils::get_link( 'get-support', 'hummingbird_deactivation_survey_help' ) ) . '">' . esc_html__( 'Need Help?', 'wphb' ) . '</a>' : ''
							);
							?>
						</p>
						<div class="wphb-deactivation-field-row">
							<label for="wphb-temp-deactivate-field" class="sui-radio wphb-deactivation-field" data-placeholder="<?php esc_html_e( 'What issue are you debugging? (optional)', 'wphb' ); ?>">
								<input
									type="radio"
									name="deactivation_reason"
									id="wphb-temp-deactivate-field"
									aria-labelledby="label-wphb-temp-deactivate-field"
									value="temp_deactivate"
								/>
								<span aria-hidden="true"></span>
								<span id="label-wphb-temp-deactivate-field"><?php esc_html_e( 'Temporary deactivation for debugging', 'wphb' ); ?></span>
							</label>
						</div>

						<div class="wphb-deactivation-field-row">
							<label for="wphb-not-working-field" class="sui-radio wphb-deactivation-field" data-placeholder="<?php esc_html_e( 'What issue did you face? (optional)', 'wphb' ); ?>">
								<input
									type="radio"
									name="deactivation_reason"
									id="wphb-not-working-field"
									aria-labelledby="label-wphb-not-working-field"
									value="not_working"
								/>
								<span aria-hidden="true"></span>
								<span id="label-wphb-not-working-field"><?php esc_html_e( "Can't make it work", 'wphb' ); ?></span>
							</label>
						</div>

						<div class="wphb-deactivation-field-row">
							<label for="wphb-breaks-site-field" class="sui-radio wphb-deactivation-field" data-placeholder="<?php esc_html_e( 'What issue did you face? (optional)', 'wphb' ); ?>">
								<input
									type="radio"
									name="deactivation_reason"
									id="wphb-breaks-site-field"
									aria-labelledby="label-wphb-breaks-site-field"
									value="breaks_site"
								/>
								<span aria-hidden="true"></span>
								<span id="label-wphb-breaks-site-field"><?php esc_html_e( 'Breaks the site or other plugins/services', 'wphb' ); ?></span>
							</label>
						</div>

						<div class="wphb-deactivation-field-row">
							<label for="wphb-expected-beter-field" class="sui-radio wphb-deactivation-field" data-placeholder="<?php esc_html_e( 'What could we do better? (optional)', 'wphb' ); ?>">
								<input
									type="radio"
									name="deactivation_reason"
									id="wphb-expected-beter-field"
									aria-labelledby="label-wphb-expected-beter-field"
									value="expected_better"
								/>
								<span aria-hidden="true"></span>
								<span id="label-wphb-expected-beter-field"><?php esc_html_e( "Doesn't meet expectations", 'wphb' ); ?></span>
							</label>
						</div>

						<div class="wphb-deactivation-field-row">
							<label for="wphb-found-better-field" class="sui-radio wphb-deactivation-field" data-placeholder="<?php esc_html_e( 'Which plugin and how is it better? (optional)', 'wphb' ); ?>">
								<input
									type="radio"
									name="deactivation_reason"
									id="wphb-found-better-field"
									aria-labelledby="label-wphb-found-better-field"
									value="found_better"
								/>
								<span aria-hidden="true"></span>
								<span id="label-wphb-found-better-field"><?php esc_html_e( 'Found a better plugin', 'wphb' ); ?></span>
							</label>
						</div>

						<div class="wphb-deactivation-field-row">
							<label for="wphb-not-required-field" class="sui-radio wphb-deactivation-field" data-placeholder="<?php esc_html_e( 'Please tell us why. (optional)', 'wphb' ); ?>">
								<input
									type="radio"
									name="deactivation_reason"
									id="wphb-not-required-field"
									aria-labelledby="label-wphb-not-required-field"
									value="not_required"
								/>
								<span aria-hidden="true"></span>
								<span id="label-wphb-not-required-field"><?php esc_html_e( 'No longer required', 'wphb' ); ?></span>
							</label>
						</div>

						<div class="wphb-deactivation-field-row">
							<label for="wphb-other-field" class="sui-radio wphb-deactivation-field" data-placeholder="<?php esc_html_e( 'Please tell us why. (Optional)', 'wphb' ); ?>">
								<input
									type="radio"
									name="deactivation_reason"
									id="wphb-other-field"
									aria-labelledby="label-wphb-other-field"
									value="other_issues"
								/>
								<span aria-hidden="true"></span>
								<span id="label-wphb-other-field"><?php esc_html_e( 'Other', 'wphb' ); ?></span>
							</label>
							<div id="wphb-deactivation-feedback-field" class="sui-hidden" style="padding-left:25px; margin:10px 0;">
								<textarea
									placeholder="<?php esc_html_e( 'Please tell us why. (optional)', 'wphb' ); ?>"
									class="sui-form-control"
									aria-labelledby="label-wphb-deactivation-feedback"
									style="height: 40px"
									aria-describedby="error-wphb-deactivation-feedback description-wphb-deactivation-feedback"
								></textarea>
							</div>
						</div>
					</div>
					<div class="sui-box-footer">
						<button type="button" class="sui-button-ghost sui-button wphb-deactivate-without-feedback-button"><?php esc_html_e( 'Skip & Deactivate', 'wphb' ); ?></button>
						<div class="sui-actions-right">
							<button type="button" class="sui-button-blue sui-button wphb-submit-feedback-deactivate-button"><?php esc_html_e( 'Submit & Deactivate', 'wphb' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
