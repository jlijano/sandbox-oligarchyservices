<?php
$carrierForm = is_array($formMail ?? null) ? $formMail : [];
$carrierValue = static function (string $key, string $default = '') use ($carrierForm): string {
    return (string) ($carrierForm[$key] ?? $default);
};
if (!$carrierForm): ?>
<input type="hidden" name="action" value="send_carrier_email">
<input type="hidden" name="compose_mode" value="new">
<div class="carrier-form-grid carrier-new-mail-grid">
  <label>To<input name="to" type="email" placeholder="recipient@example.com" required></label>
  <label class="wide-field">Subject<input name="subject" type="text" placeholder="Subject" required></label>
  <label class="wide-field">Message<textarea name="body" rows="14" required></textarea></label>
</div>
<div class="carrier-compose-toolbar" aria-label="Compose options">
  <button type="button" title="Formatting options" aria-label="Formatting options">A</button>
  <button type="button" title="Attach file" aria-label="Attach file">⌕</button>
  <button type="button" title="Insert link" aria-label="Insert link">🔗</button>
  <button type="button" title="Insert emoji" aria-label="Insert emoji">☺</button>
  <button type="button" title="Insert from Drive" aria-label="Insert from Drive">△</button>
  <button type="button" title="Insert photo" aria-label="Insert photo">▧</button>
  <button type="button" title="Confidential mode" aria-label="Confidential mode">🔒</button>
  <button type="button" title="More options" aria-label="More options">⋮</button>
  <button class="carrier-compose-discard" type="reset" title="Discard draft" aria-label="Discard draft">⌫</button>
</div>
<?php return; endif; ?>
<div class="carrier-form-grid">
  <label>Carrier name<input name="carrier_name" value="<?= e($carrierValue('carrier_name')) ?>" required></label>
  <label>Carrier email<input name="carrier_email" type="email" value="<?= e($carrierValue('carrier_email')) ?>" placeholder="carrier@example.com"></label>
  <label class="wide-field">Subject<input name="subject" value="<?= e($carrierValue('subject')) ?>" required></label>
  <label>Status<select name="status"><?php foreach (carrier_statuses() as $status): ?><option value="<?= e($status) ?>" <?= $carrierValue('status', 'New') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
  <label>Priority<select name="priority"><?php foreach (carrier_priorities() as $priority): ?><option value="<?= e($priority) ?>" <?= $carrierValue('priority', 'Normal') === $priority ? 'selected' : '' ?>><?= e($priority) ?></option><?php endforeach; ?></select></label>
  <label>Received at<input name="received_at" type="datetime-local" value="<?= e(carrier_datetime_input($carrierValue('received_at'))) ?>"></label>
  <label>Attachments<input name="attachments" value="<?= e($carrierValue('attachments')) ?>" placeholder="File names, links, or notes"></label>
  <label class="wide-field">Preview text<input name="preview_text" value="<?= e($carrierValue('preview_text')) ?>" placeholder="Optional inbox preview"></label>
  <label class="wide-field">Message<textarea name="message" rows="10" required><?= e($carrierValue('message')) ?></textarea></label>
  <label class="check-row"><input name="is_read" type="checkbox" value="1" <?= (int) ($carrierForm['is_read'] ?? 0) === 1 ? 'checked' : '' ?>><span>Read</span></label>
  <label class="check-row"><input name="is_starred" type="checkbox" value="1" <?= (int) ($carrierForm['is_starred'] ?? 0) === 1 ? 'checked' : '' ?>><span>Starred</span></label>
</div>
