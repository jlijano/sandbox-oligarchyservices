<?php
/** @var array $blank */
/** @var array $statuses */
/** @var array $priorities */
?>
<label>Company<input name="company" maxlength="190" required value="<?= e((string) ($blank['company'] ?? '')) ?>" placeholder="Company name"></label>
<label>Contact<input name="contact" maxlength="190" value="<?= e((string) ($blank['contact'] ?? '')) ?>" placeholder="Primary contact"></label>
<label>Email<input name="email" type="email" maxlength="190" value="<?= e((string) ($blank['email'] ?? '')) ?>" placeholder="name@example.com"></label>
<label>Phone<input name="phone" maxlength="80" value="<?= e((string) ($blank['phone'] ?? '')) ?>" placeholder="(555) 555-0100"></label>
<label>Source<input name="source" maxlength="120" value="<?= e((string) ($blank['source'] ?? '')) ?>" placeholder="Website, referral, event"></label>
<label>Status<select name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= (string) ($blank['status'] ?? 'New') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
<label>Priority<select name="priority"><?php foreach ($priorities as $priority): ?><option value="<?= e($priority) ?>" <?= (string) ($blank['priority'] ?? 'Medium') === $priority ? 'selected' : '' ?>><?= e($priority) ?></option><?php endforeach; ?></select></label>
<label>Estimated value<input name="value" type="number" min="0" max="999999999" step="1" value="<?= e((string) ($blank['value'] ?? '')) ?>" placeholder="0"></label>
<label>Owner<input name="owner" maxlength="120" value="<?= e((string) ($blank['owner'] ?? '')) ?>"></label>
<label>Follow-up date<input name="follow_up" type="date" value="<?= e((string) ($blank['follow_up'] ?? '')) ?>"></label>
<label class="wide-field">Last activity<input name="last_activity" maxlength="255" value="<?= e((string) ($blank['last_activity'] ?? '')) ?>" placeholder="Discovery call completed"></label>
<label class="wide-field">Notes<textarea name="notes" rows="4" placeholder="Context, needs, next step, blockers"><?= e((string) ($blank['notes'] ?? '')) ?></textarea></label>
