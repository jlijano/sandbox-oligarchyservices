<?php
$carrierForm = is_array($formMail ?? null) ? $formMail : [];
$carrierValue = static function (string $key, string $default = '') use ($carrierForm): string {
    return (string) ($carrierForm[$key] ?? $default);
};
if (!$carrierForm): ?>
<style>
  #compose-carrier .carrier-form {
    position: relative;
    padding-bottom: 52px;
  }
  #compose-carrier .carrier-compose-toolbar {
    position: absolute;
    left: 104px;
    right: 12px;
    bottom: 0;
    z-index: 2;
    display: flex;
    min-width: 0;
    min-height: 52px;
    align-items: center;
    gap: 7px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: #1d1e21;
  }
  #compose-carrier .carrier-compose-toolbar button {
    position: relative;
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    min-width: 30px;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: #c9ced6;
    padding: 0;
    font: 800 .95rem/1 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    cursor: pointer;
  }
  #compose-carrier .carrier-compose-toolbar button:hover,
  #compose-carrier .carrier-compose-toolbar button:focus-visible {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    outline: 0;
  }
  #compose-carrier .carrier-compose-toolbar button::after {
    position: absolute;
    left: 50%;
    bottom: calc(100% + 8px);
    z-index: 10;
    display: none;
    width: max-content;
    max-width: 180px;
    transform: translateX(-50%);
    border: 1px solid rgba(157, 163, 173, 0.45);
    border-radius: 4px;
    background: #2b2d35;
    color: #fff;
    padding: 5px 8px;
    content: attr(aria-label);
    font-size: .72rem;
    font-weight: 800;
    line-height: 1.2;
    white-space: nowrap;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.34);
    pointer-events: none;
  }
  #compose-carrier .carrier-compose-toolbar button:hover::after,
  #compose-carrier .carrier-compose-toolbar button:focus-visible::after {
    display: block;
  }
  #compose-carrier .carrier-compose-toolbar .carrier-compose-discard {
    margin-left: auto;
  }
  #compose-carrier .carrier-compose-toolbar .carrier-compose-discard::after {
    left: auto;
    right: 0;
    transform: none;
  }
  #compose-carrier .carrier-compose-attachments {
    max-width: min(240px, 34vw);
    overflow: hidden;
    color: #aeb3bd;
    font-size: .75rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  #compose-carrier .carrier-form > .button.primary {
    position: absolute;
    left: 14px;
    bottom: 8px;
    z-index: 3;
    margin: 0;
  }
  #compose-carrier .carrier-new-mail-grid {
    position: relative;
    z-index: 1;
    pointer-events: auto;
  }
  #compose-carrier .carrier-new-mail-grid label {
    pointer-events: auto;
  }
  #compose-carrier .carrier-new-mail-grid input,
  #compose-carrier .carrier-new-mail-grid textarea {
    position: relative;
    z-index: 1;
    display: block;
    pointer-events: auto;
    color: #f4f5f7;
    caret-color: #ffffff;
    line-height: 1.45;
    cursor: text;
  }
  #compose-carrier .carrier-new-mail-grid input {
    min-height: 32px !important;
  }
  #compose-carrier .carrier-new-mail-grid textarea[name="body"] {
    width: 100%;
    height: 100%;
    min-height: 260px !important;
  }
  #compose-carrier .carrier-new-mail-grid input:focus,
  #compose-carrier .carrier-new-mail-grid textarea:focus {
    outline: 2px solid rgba(93, 140, 255, 0.72);
    outline-offset: 2px;
  }
  @media (max-width: 700px) {
    #compose-carrier .carrier-compose-toolbar {
      left: 96px;
      gap: 4px;
      overflow-x: auto;
      scrollbar-width: none;
    }
    #compose-carrier .carrier-compose-toolbar::-webkit-scrollbar {
      display: none;
    }
    #compose-carrier .carrier-compose-toolbar button {
      width: 28px;
      min-width: 28px;
    }
    #compose-carrier .carrier-compose-attachments {
      max-width: 140px;
    }
    #compose-carrier .carrier-new-mail-grid textarea[name="body"] {
      min-height: 220px !important;
    }
  }
</style>
<input type="hidden" name="action" value="send_carrier_email">
<input type="hidden" name="compose_mode" value="new">
<input class="carrier-compose-file-input" name="attachments[]" type="file" multiple hidden>
<div class="carrier-form-grid carrier-new-mail-grid">
  <label>To<input name="to" type="email" placeholder="recipient@example.com" required></label>
  <label class="wide-field">Subject<input name="subject" type="text" placeholder="Subject" required></label>
  <label class="wide-field">Message<textarea name="body" rows="14" required></textarea></label>
</div>
<div class="carrier-compose-toolbar" aria-label="Compose options">
  <button type="button" title="Bold selected text" aria-label="Bold selected text" data-compose-format>A</button>
  <button type="button" title="Attach file" aria-label="Attach file" data-compose-attach>⌕</button>
  <button type="button" title="Insert link" aria-label="Insert link" data-compose-link>🔗</button>
  <button type="button" title="Insert emoji" aria-label="Insert emoji" data-compose-emoji>☺</button>
  <button type="button" title="Attach from Drive" aria-label="Attach from Drive" data-compose-drive>△</button>
  <button type="button" title="Attach photo" aria-label="Attach photo" data-compose-photo>▧</button>
  <button type="button" title="Add confidential note" aria-label="Add confidential note" data-compose-confidential>🔒</button>
  <button type="button" title="More options" aria-label="More options" data-compose-more>⋮</button>
  <span class="carrier-compose-attachments" aria-live="polite"></span>
  <button class="carrier-compose-discard" type="reset" title="Discard draft" aria-label="Discard draft">⌫</button>
</div>
<script>
  (() => {
    const modal = document.getElementById('compose-carrier');
    if (!modal || modal.dataset.composeToolbarReady === 'true') return;
    modal.dataset.composeToolbarReady = 'true';
    const form = modal.querySelector('form');
    const body = modal.querySelector('textarea[name="body"]');
    const fileInput = modal.querySelector('.carrier-compose-file-input');
    const attachmentLabel = modal.querySelector('.carrier-compose-attachments');
    if (!form || !body) return;
    form.enctype = 'multipart/form-data';

    const insertAtCursor = (text, selectStartOffset = text.length, selectEndOffset = text.length) => {
      const start = body.selectionStart || 0;
      const end = body.selectionEnd || 0;
      body.setRangeText(text, start, end, 'end');
      body.focus();
      body.setSelectionRange(start + selectStartOffset, start + selectEndOffset);
    };
    const selectedText = () => body.value.slice(body.selectionStart || 0, body.selectionEnd || 0);

    modal.querySelector('[data-compose-format]')?.addEventListener('click', () => {
      const selected = selectedText() || 'bold text';
      insertAtCursor('**' + selected + '**', 2, 2 + selected.length);
    });
    modal.querySelector('[data-compose-link]')?.addEventListener('click', () => {
      const url = window.prompt('Paste a link');
      if (!url) return;
      const selected = selectedText();
      insertAtCursor(selected ? selected + ' (' + url + ')' : url);
    });
    modal.querySelector('[data-compose-emoji]')?.addEventListener('click', () => {
      const emoji = window.prompt('Emoji to insert', '🙂');
      if (emoji) insertAtCursor(emoji);
    });
    modal.querySelector('[data-compose-confidential]')?.addEventListener('click', () => {
      insertAtCursor('\n\nConfidential: Please do not forward this message without permission.');
    });
    modal.querySelector('[data-compose-more]')?.addEventListener('click', () => {
      window.alert('Available compose actions: attach files, insert a link, insert an emoji, add a confidential note, minimize, maximize, close, or discard the draft.');
    });
    ['[data-compose-attach]', '[data-compose-drive]', '[data-compose-photo]'].forEach((selector) => {
      modal.querySelector(selector)?.addEventListener('click', () => fileInput?.click());
    });
    fileInput?.addEventListener('change', () => {
      const names = Array.from(fileInput.files || []).map((file) => file.name);
      if (attachmentLabel) attachmentLabel.textContent = names.length ? names.join(', ') : '';
    });
    form.addEventListener('reset', () => {
      window.setTimeout(() => {
        if (attachmentLabel) attachmentLabel.textContent = '';
        if (fileInput) fileInput.value = '';
        body.focus();
      }, 0);
    });
  })();
</script>
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
