(function () {
  const form = document.querySelector('[data-page-builder-form]');
  if (!form) return;

  const stateNode = document.getElementById('page-builder-state');
  const hiddenJson = form.querySelector('[data-builder-json]');
  const editorMode = form.querySelector('[data-editor-mode]');
  const titleField = form.querySelector('[data-page-title]');
  const slugField = form.querySelector('[data-page-slug]');
  const errorBox = form.querySelector('[data-builder-error]');
  const library = form.querySelector('[data-block-library]');
  const canvas = form.querySelector('[data-builder-canvas]');
  const inspector = form.querySelector('[data-block-inspector]');
  const blockCount = form.querySelector('[data-block-count]');
  const workbench = form.querySelector('[data-builder-workbench]');
  const legacyEditor = form.querySelector('[data-legacy-editor]');
  const legacyBody = form.querySelector('[data-legacy-body]');
  const previewModal = document.querySelector('[data-preview-modal]');
  const previewBody = document.querySelector('[data-preview-body]');
  const sidebarPagesLink = document.querySelector('.sidebar-nav a[href="/dashboard.php#pages"]');

  if (sidebarPagesLink) {
    sidebarPagesLink.href = '/pages.php';
    delete sidebarPagesLink.dataset.sectionLink;
    sidebarPagesLink.classList.add('is-active');
    sidebarPagesLink.setAttribute('aria-current', 'page');
    const group = sidebarPagesLink.closest('[data-playground-group]');
    if (group) {
      group.classList.add('is-open', 'is-active');
      group.querySelector('[data-playground-toggle]')?.setAttribute('aria-expanded', 'true');
    }
  }

  const definitions = {
    hero: { label: 'Hero section', fields: [['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'], ['body', 'Body', 'textarea'], ['buttonLabel', 'Button label', 'text'], ['buttonUrl', 'Button URL', 'text']] },
    text: { label: 'Text section', fields: [['heading', 'Heading', 'text'], ['body', 'Body', 'textarea']] },
    'two-column': { label: 'Two-column text', fields: [['leftHeading', 'Left heading', 'text'], ['leftBody', 'Left body', 'textarea'], ['rightHeading', 'Right heading', 'text'], ['rightBody', 'Right body', 'textarea']] },
    image: { label: 'Image', fields: [['src', 'Image URL', 'text'], ['alt', 'Alt text', 'text'], ['caption', 'Caption', 'text']] },
    cta: { label: 'CTA band', fields: [['heading', 'Heading', 'text'], ['body', 'Body', 'textarea'], ['buttonLabel', 'Button label', 'text'], ['buttonUrl', 'Button URL', 'text']] },
    'feature-grid': { label: 'Feature grid', fields: [['heading', 'Heading', 'text'], ['itemsText', 'Items', 'items']] },
    faq: { label: 'FAQ', fields: [['heading', 'Heading', 'text'], ['itemsText', 'Questions', 'items']] },
    button: { label: 'Button/link', fields: [['label', 'Label', 'text'], ['url', 'URL', 'text']] },
    spacer: { label: 'Divider/spacer', fields: [['size', 'Size', 'select']] }
  };

  const defaults = {
    hero: { eyebrow: 'Oligarchy Services', heading: 'Page heading', body: '', buttonLabel: '', buttonUrl: '' },
    text: { heading: 'Section heading', body: 'Add section copy.' },
    'two-column': { leftHeading: 'Left column', leftBody: '', rightHeading: 'Right column', rightBody: '' },
    image: { src: '', alt: '', caption: '' },
    cta: { heading: 'Call to action', body: '', buttonLabel: 'Contact us', buttonUrl: '/contact.html' },
    'feature-grid': { heading: 'Features', items: [{ title: 'Feature', body: 'Describe the feature.' }] },
    faq: { heading: 'FAQ', items: [{ title: 'Question', body: 'Answer.' }] },
    button: { label: 'Learn more', url: '/contact.html' },
    spacer: { size: 'medium' }
  };

  const escapeHtml = (value) => String(value || '').replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
  const uid = () => 'block_' + Math.random().toString(16).slice(2) + Date.now().toString(16);
  const clone = (value) => JSON.parse(JSON.stringify(value));

  let initial = { mode: 'builder', blocks: [], legacyBody: '' };
  try { initial = JSON.parse(stateNode ? stateNode.textContent : '{}'); } catch (error) {}
  let mode = initial.mode === 'legacy' ? 'legacy' : 'builder';
  let blocks = Array.isArray(initial.blocks) && initial.blocks.length ? initial.blocks : [{ id: uid(), type: 'hero', data: clone(defaults.hero) }];
  let selectedId = blocks[0] ? blocks[0].id : '';
  if (legacyBody && initial.legacyBody) legacyBody.value = initial.legacyBody;

  const selectedBlock = () => blocks.find((block) => block.id === selectedId) || null;
  const labelFor = (type) => definitions[type]?.label || type;

  const showError = (message) => {
    if (!errorBox) return;
    errorBox.textContent = message;
    errorBox.hidden = message === '';
  };

  const syncMode = () => {
    if (editorMode) editorMode.value = mode;
    workbench.hidden = mode !== 'builder';
    legacyEditor.hidden = mode !== 'legacy';
    document.querySelectorAll('[data-mode-button]').forEach((button) => button.classList.toggle('is-active', button.dataset.modeButton === mode));
  };

  const normalizeItemsText = (items) => (Array.isArray(items) ? items : []).map((item) => `${item.title || ''}|${item.body || ''}`).join('\n');
  const parseItemsText = (value) => String(value || '').split('\n').map((line) => {
    const parts = line.split('|');
    return { title: (parts.shift() || '').trim(), body: parts.join('|').trim() };
  }).filter((item) => item.title || item.body);

  const addBlock = (type) => {
    blocks.push({ id: uid(), type, data: clone(defaults[type] || {}) });
    selectedId = blocks[blocks.length - 1].id;
    render();
  };

  const duplicateBlock = (id) => {
    const index = blocks.findIndex((block) => block.id === id);
    if (index < 0) return;
    const copy = clone(blocks[index]);
    copy.id = uid();
    blocks.splice(index + 1, 0, copy);
    selectedId = copy.id;
    render();
  };

  const removeBlock = (id) => {
    blocks = blocks.filter((block) => block.id !== id);
    selectedId = blocks[0] ? blocks[0].id : '';
    render();
  };

  const renderLibrary = () => {
    library.innerHTML = Object.keys(definitions).map((type) => `<button type="button" data-add-block="${type}">${escapeHtml(definitions[type].label)}</button>`).join('');
  };

  const renderCanvas = () => {
    if (!blocks.length) {
      canvas.innerHTML = '<p class="empty-state">No blocks yet.</p>';
    } else {
      canvas.innerHTML = blocks.map((block, index) => {
        const data = block.data || {};
        const title = data.heading || data.leftHeading || data.label || data.alt || labelFor(block.type);
        const detail = data.body || data.leftBody || data.caption || '';
        return `<article class="canvas-block ${block.id === selectedId ? 'is-selected' : ''}" draggable="true" data-block-id="${escapeHtml(block.id)}"><button class="canvas-block-main" type="button" data-select-block="${escapeHtml(block.id)}"><span>${index + 1}</span><div><strong>${escapeHtml(labelFor(block.type))}</strong><p>${escapeHtml(title)}</p>${detail ? `<small>${escapeHtml(detail).slice(0, 120)}</small>` : ''}</div></button><div class="canvas-block-actions"><button type="button" data-duplicate-block="${escapeHtml(block.id)}">Duplicate</button><button type="button" data-remove-block="${escapeHtml(block.id)}">Remove</button></div></article>`;
      }).join('');
    }
    if (blockCount) blockCount.textContent = `${blocks.length} block${blocks.length === 1 ? '' : 's'}`;
  };

  const fieldValue = (block, key) => {
    if (key === 'itemsText') return normalizeItemsText(block.data.items);
    return block.data[key] || '';
  };

  const renderInspector = () => {
    const block = selectedBlock();
    if (!block) {
      inspector.innerHTML = '<p class="empty-state">Select a block to edit settings.</p>';
      return;
    }
    const definition = definitions[block.type];
    inspector.innerHTML = `<div class="inspector-heading"><h4>${escapeHtml(definition.label)}</h4><span>${escapeHtml(block.type)}</span></div>` + definition.fields.map(([key, label, fieldType]) => {
      const value = fieldValue(block, key);
      if (fieldType === 'textarea' || fieldType === 'items') {
        const hint = fieldType === 'items' ? '<small>Use one item per line: title|body</small>' : '';
        return `<label>${escapeHtml(label)}<textarea data-inspector-field="${key}" rows="${fieldType === 'items' ? 6 : 4}">${escapeHtml(value)}</textarea>${hint}</label>`;
      }
      if (fieldType === 'select') {
        return `<label>${escapeHtml(label)}<select data-inspector-field="${key}"><option value="small" ${value === 'small' ? 'selected' : ''}>Small</option><option value="medium" ${value === 'medium' ? 'selected' : ''}>Medium</option><option value="large" ${value === 'large' ? 'selected' : ''}>Large</option></select></label>`;
      }
      return `<label>${escapeHtml(label)}<input data-inspector-field="${key}" value="${escapeHtml(value)}"></label>`;
    }).join('');
  };

  const render = () => {
    renderCanvas();
    renderInspector();
    if (hiddenJson) hiddenJson.value = JSON.stringify({ blocks });
  };

  const previewBlock = (block) => {
    const data = block.data || {};
    if (block.type === 'hero') return `<section class="page-hero"><p class="eyebrow">${escapeHtml(data.eyebrow)}</p><h1>${escapeHtml(data.heading)}</h1><p>${escapeHtml(data.body)}</p>${data.buttonLabel && data.buttonUrl ? `<a class="button primary" href="${escapeHtml(data.buttonUrl)}">${escapeHtml(data.buttonLabel)}</a>` : ''}</section>`;
    if (block.type === 'text') return `<section class="section"><h2>${escapeHtml(data.heading)}</h2><p>${escapeHtml(data.body).replace(/\n/g, '<br>')}</p></section>`;
    if (block.type === 'two-column') return `<section class="section two-column"><div><h2>${escapeHtml(data.leftHeading)}</h2><p>${escapeHtml(data.leftBody).replace(/\n/g, '<br>')}</p></div><div><h2>${escapeHtml(data.rightHeading)}</h2><p>${escapeHtml(data.rightBody).replace(/\n/g, '<br>')}</p></div></section>`;
    if (block.type === 'image') return data.src ? `<section class="section"><figure><img src="${escapeHtml(data.src)}" alt="${escapeHtml(data.alt)}"><figcaption>${escapeHtml(data.caption)}</figcaption></figure></section>` : '';
    if (block.type === 'cta') return `<section class="section cta-band"><h2>${escapeHtml(data.heading)}</h2><p>${escapeHtml(data.body)}</p>${data.buttonLabel && data.buttonUrl ? `<a class="button primary" href="${escapeHtml(data.buttonUrl)}">${escapeHtml(data.buttonLabel)}</a>` : ''}</section>`;
    if (block.type === 'feature-grid') return `<section class="section"><h2>${escapeHtml(data.heading)}</h2><div class="detail-grid">${(data.items || []).map((item) => `<article><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.body)}</p></article>`).join('')}</div></section>`;
    if (block.type === 'faq') return `<section class="section"><h2>${escapeHtml(data.heading)}</h2>${(data.items || []).map((item) => `<details><summary>${escapeHtml(item.title)}</summary><p>${escapeHtml(item.body)}</p></details>`).join('')}</section>`;
    if (block.type === 'button') return data.label && data.url ? `<section class="section"><a class="button primary" href="${escapeHtml(data.url)}">${escapeHtml(data.label)}</a></section>` : '';
    if (block.type === 'spacer') return `<div class="preview-spacer ${escapeHtml(data.size || 'medium')}"></div>`;
    return '';
  };

  renderLibrary();
  syncMode();
  render();

  titleField?.addEventListener('input', () => {
    if (!slugField || slugField.dataset.touched === 'true') return;
    slugField.value = String(titleField.value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  });
  slugField?.addEventListener('input', () => { slugField.dataset.touched = 'true'; });

  document.querySelectorAll('[data-mode-button]').forEach((button) => button.addEventListener('click', () => {
    mode = button.dataset.modeButton === 'legacy' ? 'legacy' : 'builder';
    syncMode();
  }));

  library.addEventListener('click', (event) => {
    const button = event.target.closest('[data-add-block]');
    if (button) addBlock(button.dataset.addBlock);
  });

  canvas.addEventListener('click', (event) => {
    const select = event.target.closest('[data-select-block]');
    const duplicate = event.target.closest('[data-duplicate-block]');
    const remove = event.target.closest('[data-remove-block]');
    if (select) selectedId = select.dataset.selectBlock;
    if (duplicate) duplicateBlock(duplicate.dataset.duplicateBlock);
    if (remove) removeBlock(remove.dataset.removeBlock);
    render();
  });

  let draggedId = '';
  canvas.addEventListener('dragstart', (event) => {
    const block = event.target.closest('[data-block-id]');
    if (!block) return;
    draggedId = block.dataset.blockId;
    event.dataTransfer.effectAllowed = 'move';
  });
  canvas.addEventListener('dragover', (event) => {
    if (draggedId) event.preventDefault();
  });
  canvas.addEventListener('drop', (event) => {
    const target = event.target.closest('[data-block-id]');
    if (!target || !draggedId || target.dataset.blockId === draggedId) return;
    event.preventDefault();
    const from = blocks.findIndex((block) => block.id === draggedId);
    const to = blocks.findIndex((block) => block.id === target.dataset.blockId);
    if (from < 0 || to < 0) return;
    const [moved] = blocks.splice(from, 1);
    blocks.splice(to, 0, moved);
    draggedId = '';
    render();
  });

  inspector.addEventListener('input', (event) => {
    const field = event.target.closest('[data-inspector-field]');
    const block = selectedBlock();
    if (!field || !block) return;
    const key = field.dataset.inspectorField;
    if (key === 'itemsText') {
      block.data.items = parseItemsText(field.value);
    } else {
      block.data[key] = field.value;
    }
    renderCanvas();
    if (hiddenJson) hiddenJson.value = JSON.stringify({ blocks });
  });

  document.querySelector('[data-preview-page]')?.addEventListener('click', () => {
    if (!previewModal || !previewBody) return;
    previewBody.innerHTML = mode === 'legacy' ? `<section class="section"><div class="cms-content"><p>${escapeHtml(legacyBody?.value || '').replace(/\n/g, '<br>')}</p></div></section>` : blocks.map(previewBlock).join('');
    previewModal.hidden = false;
    document.body.classList.add('builder-preview-open');
  });
  document.querySelectorAll('[data-preview-close]').forEach((button) => button.addEventListener('click', () => {
    if (previewModal) previewModal.hidden = true;
    document.body.classList.remove('builder-preview-open');
  }));

  form.addEventListener('submit', (event) => {
    showError('');
    if (!titleField?.value.trim()) {
      event.preventDefault();
      showError('Title is required.');
      titleField?.focus();
      return;
    }
    if (!slugField?.value.trim()) {
      event.preventDefault();
      showError('Slug is required.');
      slugField?.focus();
      return;
    }
    if (mode === 'builder' && !blocks.length) {
      event.preventDefault();
      showError('Add at least one block before saving.');
      return;
    }
    if (mode === 'legacy' && !legacyBody?.value.trim()) {
      event.preventDefault();
      showError('Body content is required.');
      legacyBody?.focus();
      return;
    }
    if (hiddenJson) hiddenJson.value = JSON.stringify({ blocks });
  });
})();
