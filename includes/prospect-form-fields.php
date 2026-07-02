<?php
/** @var array $blank */
/** @var array $statuses */
/** @var array $priorities */

if (!function_exists('prospect_field_needs_attention')) {
    function prospect_field_needs_attention(array $record, string $field): bool
    {
        $value = trim((string) ($record[$field] ?? ''));
        if ($value === '') return true;

        $normalized = strtolower((string) preg_replace('/\s+/', ' ', $value));
        $unusableValues = [
            '-',
            '--',
            'n/a',
            'na',
            'none',
            'no data',
            'not available',
            'unavailable',
            'unknown',
            'tbd',
            'to be determined',
            'missing',
            'not found',
            'not publicly found',
            'not publicly available',
            'not verified',
            'unverified',
            'needs verification',
            'needs update',
        ];
        if (in_array($normalized, $unusableValues, true)) return true;

        $unusableFragments = [
            'not publicly found',
            'not publicly available',
            'not found publicly',
            'not verified',
            'needs verification',
            'needs update',
        ];
        foreach ($unusableFragments as $fragment) {
            if (str_contains($normalized, $fragment)) return true;
        }

        return false;
    }
}

if (!function_exists('prospect_attention_class')) {
    function prospect_attention_class(array $record, string $field, string $baseClass = ''): string
    {
        $classes = trim($baseClass . (prospect_field_needs_attention($record, $field) ? ' needs-attention' : ''));
        return $classes !== '' ? ' class="' . e($classes) . '"' : '';
    }
}

if (!defined('PROSPECT_ATTENTION_FIELD_STYLES_RENDERED')):
    define('PROSPECT_ATTENTION_FIELD_STYLES_RENDERED', true);
?>
<style>
  .prospect-form label.needs-attention {
    color: #ffd7df;
  }

  .prospect-form label.needs-attention > input,
  .prospect-form label.needs-attention > select,
  .prospect-form label.needs-attention > textarea {
    border-color: rgba(255, 45, 72, 0.95) !important;
    background: rgba(32, 8, 13, 0.7) !important;
    box-shadow: 0 0 0 2px rgba(255, 45, 72, 0.22), 0 0 18px rgba(255, 45, 72, 0.45) !important;
  }

  .prospect-form label.needs-attention > input:focus,
  .prospect-form label.needs-attention > select:focus,
  .prospect-form label.needs-attention > textarea:focus {
    border-color: rgba(255, 87, 112, 1) !important;
    box-shadow: 0 0 0 3px rgba(255, 45, 72, 0.28), 0 0 24px rgba(255, 45, 72, 0.58) !important;
  }
</style>
<?php endif; ?>
<label<?= prospect_attention_class($blank, 'company') ?>>Business Name<input name="company" maxlength="190" required value="<?= e((string) ($blank['company'] ?? '')) ?>" placeholder="Company name"></label>
<label>Status<select name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= (string) ($blank['status'] ?? 'New Lead') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
<label<?= prospect_attention_class($blank, 'website') ?>>Website<input name="website" maxlength="255" value="<?= e((string) ($blank['website'] ?? '')) ?>" placeholder="https://example.com"></label>
<label<?= prospect_attention_class($blank, 'industry_category') ?>>Industry / Category<input name="industry_category" maxlength="190" value="<?= e((string) ($blank['industry_category'] ?? '')) ?>" placeholder="Industry or niche"></label>
<label>Percentage<input type="text" value="<?= e(isset($blank['conversion_percentage']) ? number_format((float) $blank['conversion_percentage'], 0) . '%' : 'Auto-calculated') ?>" readonly aria-describedby="percentage-help"><small id="percentage-help">Calculated from status, priority, available contact data, fit signals, buying trigger, outreach angle, recommended services, and recent interaction.</small></label>
<label>Priority<select name="priority"><?php foreach ($priorities as $priority): ?><option value="<?= e($priority) ?>" <?= (string) ($blank['priority'] ?? 'Medium') === $priority ? 'selected' : '' ?>><?= e($priority) ?></option><?php endforeach; ?></select></label>
<label<?= prospect_attention_class($blank, 'contact') ?>>Contact Person<input name="contact" maxlength="190" value="<?= e((string) ($blank['contact'] ?? '')) ?>" placeholder="Primary contact"></label>
<label<?= prospect_attention_class($blank, 'decision_maker_title') ?>>Decision Maker Title<input name="decision_maker_title" maxlength="190" value="<?= e((string) ($blank['decision_maker_title'] ?? '')) ?>" placeholder="CEO, COO, IT Manager, Operations Head"></label>
<label<?= prospect_attention_class($blank, 'decision_maker_department') ?>>Department / Function<input name="decision_maker_department" maxlength="190" value="<?= e((string) ($blank['decision_maker_department'] ?? '')) ?>" placeholder="IT, Operations, Procurement, Admin"></label>
<label<?= prospect_attention_class($blank, 'contact_confidence') ?>>Contact Confidence<select name="contact_confidence"><?php foreach (prospect_contact_confidences() as $confidence): ?><option value="<?= e($confidence) ?>" <?= (string) ($blank['contact_confidence'] ?? 'Not Verified') === $confidence ? 'selected' : '' ?>><?= e($confidence) ?></option><?php endforeach; ?></select></label>
<label<?= prospect_attention_class($blank, 'email') ?>>Email<input name="email" type="email" maxlength="190" value="<?= e((string) ($blank['email'] ?? '')) ?>" placeholder="name@example.com"></label>
<label<?= prospect_attention_class($blank, 'phone') ?>>Phone<input name="phone" maxlength="80" value="<?= e((string) ($blank['phone'] ?? '')) ?>" placeholder="(555) 555-0100"></label>
<label<?= prospect_attention_class($blank, 'location') ?>>Location<input name="location" maxlength="190" value="<?= e((string) ($blank['location'] ?? '')) ?>" placeholder="City, state, country"></label>
<label<?= prospect_attention_class($blank, 'last_contact') ?>>Last Contact<input name="last_contact" type="date" value="<?= e((string) ($blank['last_contact'] ?? '')) ?>"></label>
<label<?= prospect_attention_class($blank, 'last_verified') ?>>Last Verified<input name="last_verified" type="date" value="<?= e((string) ($blank['last_verified'] ?? '')) ?>"></label>
<label<?= prospect_attention_class($blank, 'next_step') ?>>Next Step<input name="next_step" maxlength="255" value="<?= e((string) ($blank['next_step'] ?? '')) ?>" placeholder="Next outreach or follow-up action"></label>
<label<?= prospect_attention_class($blank, 'company_source_url') ?>>Company Source URL<input name="company_source_url" maxlength="255" value="<?= e((string) ($blank['company_source_url'] ?? '')) ?>" placeholder="Public source for company evidence"></label>
<label<?= prospect_attention_class($blank, 'decision_maker_profile_url') ?>>Decision Maker Profile URL<input name="decision_maker_profile_url" maxlength="255" value="<?= e((string) ($blank['decision_maker_profile_url'] ?? '')) ?>" placeholder="Public profile or source URL"></label>
<label<?= prospect_attention_class($blank, 'recommended_services', 'wide-field') ?>>Recommended Services<textarea name="recommended_services" rows="3" placeholder="Relevant Oligarchy Services offers for this lead"><?= e((string) ($blank['recommended_services'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'decision_maker_source', 'wide-field') ?>>Decision Maker Source<textarea name="decision_maker_source" rows="3" placeholder="Where the decision-maker data came from"><?= e((string) ($blank['decision_maker_source'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'social_media_links', 'wide-field') ?>>Social Media Links<textarea name="social_media_links" rows="3" placeholder="LinkedIn, Facebook, Instagram, etc."><?= e((string) ($blank['social_media_links'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'notes', 'wide-field') ?>>Notes<textarea name="notes" rows="4" placeholder="General notes, context, or relationship history"><?= e((string) ($blank['notes'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'reason_relevant', 'wide-field') ?>>Reason Relevant<textarea name="reason_relevant" rows="3" placeholder="Why this prospect fits the target profile"><?= e((string) ($blank['reason_relevant'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'pain_point_trigger', 'wide-field') ?>>Pain Point / Buying Trigger<textarea name="pain_point_trigger" rows="3" placeholder="Publicly supported need, gap, or trigger"><?= e((string) ($blank['pain_point_trigger'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'outreach_angle', 'wide-field') ?>>Outreach Angle<textarea name="outreach_angle" rows="3" placeholder="Suggested message angle or value hook"><?= e((string) ($blank['outreach_angle'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'additional_notes', 'wide-field') ?>>Additional Notes<textarea name="additional_notes" rows="3" placeholder="Extra notes or internal context"><?= e((string) ($blank['additional_notes'] ?? '')) ?></textarea></label>
<label<?= prospect_attention_class($blank, 'data_gaps_validation_notes', 'wide-field') ?>>Data Gaps / Validation Notes<textarea name="data_gaps_validation_notes" rows="3" placeholder="Missing fields, public-source caveats, or validation needed"><?= e((string) ($blank['data_gaps_validation_notes'] ?? '')) ?></textarea></label>
<input type="hidden" name="source" value="<?= e((string) ($blank['source'] ?? '')) ?>">
<input type="hidden" name="value" value="<?= e((string) ($blank['value'] ?? '0')) ?>">
<input type="hidden" name="owner" value="<?= e((string) ($blank['owner'] ?? '')) ?>">
<input type="hidden" name="follow_up" value="<?= e((string) ($blank['follow_up'] ?? '')) ?>">
<input type="hidden" name="last_activity" value="<?= e((string) ($blank['last_activity'] ?? '')) ?>">
