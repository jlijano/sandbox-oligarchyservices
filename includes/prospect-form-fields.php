<?php
/** @var array $blank */
/** @var array $statuses */
/** @var array $priorities */
?>
<label>Business Name<input name="company" maxlength="190" required value="<?= e((string) ($blank['company'] ?? '')) ?>" placeholder="Company name"></label>
<label>Status<select name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= (string) ($blank['status'] ?? 'New Lead') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
<label>Website<input name="website" maxlength="255" value="<?= e((string) ($blank['website'] ?? '')) ?>" placeholder="https://example.com"></label>
<label>Industry / Category<input name="industry_category" maxlength="190" value="<?= e((string) ($blank['industry_category'] ?? '')) ?>" placeholder="Industry or niche"></label>
<label>Percentage<input type="text" value="<?= e(isset($blank['conversion_percentage']) ? number_format((float) $blank['conversion_percentage'], 0) . '%' : 'Auto-calculated') ?>" readonly aria-describedby="percentage-help"><small id="percentage-help">Calculated from status, priority, available contact data, fit signals, buying trigger, outreach angle, and recent interaction.</small></label>
<label>Priority<select name="priority"><?php foreach ($priorities as $priority): ?><option value="<?= e($priority) ?>" <?= (string) ($blank['priority'] ?? 'Medium') === $priority ? 'selected' : '' ?>><?= e($priority) ?></option><?php endforeach; ?></select></label>
<label>Contact Person<input name="contact" maxlength="190" value="<?= e((string) ($blank['contact'] ?? '')) ?>" placeholder="Primary contact"></label>
<label>Decision Maker Title<input name="decision_maker_title" maxlength="190" value="<?= e((string) ($blank['decision_maker_title'] ?? '')) ?>" placeholder="CEO, COO, IT Manager, Operations Head"></label>
<label>Department / Function<input name="decision_maker_department" maxlength="190" value="<?= e((string) ($blank['decision_maker_department'] ?? '')) ?>" placeholder="IT, Operations, Procurement, Admin"></label>
<label>Contact Confidence<select name="contact_confidence"><?php foreach (prospect_contact_confidences() as $confidence): ?><option value="<?= e($confidence) ?>" <?= (string) ($blank['contact_confidence'] ?? 'Not Verified') === $confidence ? 'selected' : '' ?>><?= e($confidence) ?></option><?php endforeach; ?></select></label>
<label>Email<input name="email" type="email" maxlength="190" value="<?= e((string) ($blank['email'] ?? '')) ?>" placeholder="name@example.com"></label>
<label>Phone<input name="phone" maxlength="80" value="<?= e((string) ($blank['phone'] ?? '')) ?>" placeholder="(555) 555-0100"></label>
<label>Location<input name="location" maxlength="190" value="<?= e((string) ($blank['location'] ?? '')) ?>" placeholder="City, state, country"></label>
<label>Last Contact<input name="last_contact" type="date" value="<?= e((string) ($blank['last_contact'] ?? '')) ?>"></label>
<label>Last Verified<input name="last_verified" type="date" value="<?= e((string) ($blank['last_verified'] ?? '')) ?>"></label>
<label>Next Step<input name="next_step" maxlength="255" value="<?= e((string) ($blank['next_step'] ?? '')) ?>" placeholder="Next outreach or follow-up action"></label>
<label>Company Source URL<input name="company_source_url" maxlength="255" value="<?= e((string) ($blank['company_source_url'] ?? '')) ?>" placeholder="Public source for company evidence"></label>
<label>Decision Maker Profile URL<input name="decision_maker_profile_url" maxlength="255" value="<?= e((string) ($blank['decision_maker_profile_url'] ?? '')) ?>" placeholder="Public profile or source URL"></label>
<label class="wide-field">Decision Maker Source<textarea name="decision_maker_source" rows="3" placeholder="Where the decision-maker data came from"><?= e((string) ($blank['decision_maker_source'] ?? '')) ?></textarea></label>
<label class="wide-field">Social Media Links<textarea name="social_media_links" rows="3" placeholder="LinkedIn, Facebook, Instagram, etc."><?= e((string) ($blank['social_media_links'] ?? '')) ?></textarea></label>
<label class="wide-field">Notes<textarea name="notes" rows="4" placeholder="General notes, context, or relationship history"><?= e((string) ($blank['notes'] ?? '')) ?></textarea></label>
<label class="wide-field">Reason Relevant<textarea name="reason_relevant" rows="3" placeholder="Why this prospect fits the target profile"><?= e((string) ($blank['reason_relevant'] ?? '')) ?></textarea></label>
<label class="wide-field">Pain Point / Buying Trigger<textarea name="pain_point_trigger" rows="3" placeholder="Publicly supported need, gap, or trigger"><?= e((string) ($blank['pain_point_trigger'] ?? '')) ?></textarea></label>
<label class="wide-field">Outreach Angle<textarea name="outreach_angle" rows="3" placeholder="Suggested message angle or value hook"><?= e((string) ($blank['outreach_angle'] ?? '')) ?></textarea></label>
<label class="wide-field">Additional Notes<textarea name="additional_notes" rows="3" placeholder="Extra notes or internal context"><?= e((string) ($blank['additional_notes'] ?? '')) ?></textarea></label>
<label class="wide-field">Data Gaps / Validation Notes<textarea name="data_gaps_validation_notes" rows="3" placeholder="Missing fields, public-source caveats, or validation needed"><?= e((string) ($blank['data_gaps_validation_notes'] ?? '')) ?></textarea></label>
<input type="hidden" name="source" value="<?= e((string) ($blank['source'] ?? '')) ?>">
<input type="hidden" name="value" value="<?= e((string) ($blank['value'] ?? '0')) ?>">
<input type="hidden" name="owner" value="<?= e((string) ($blank['owner'] ?? '')) ?>">
<input type="hidden" name="follow_up" value="<?= e((string) ($blank['follow_up'] ?? '')) ?>">
<input type="hidden" name="last_activity" value="<?= e((string) ($blank['last_activity'] ?? '')) ?>">
