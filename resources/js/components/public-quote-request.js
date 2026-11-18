const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

const selectedVariantIds = form => new Set([...form.querySelectorAll('[data-quote-variant-id]')].map(input => input.value).filter(Boolean));

const updateSelection = form => {
  const lines = [...form.querySelectorAll('[data-quote-line]')];
  const empty = form.querySelector('[data-quote-selection-empty]');
  const count = form.querySelector('[data-quote-selection-count]');
  if (empty) empty.hidden = lines.length > 0;
  if (count) count.textContent = `${lines.length} ${lines.length === 1 ? 'seleccionado' : 'seleccionados'}`;
  const selected = selectedVariantIds(form);
  form.querySelectorAll('[data-quote-add-product]').forEach(button => {
    const added = selected.has(button.dataset.variantId || '');
    button.disabled = added;
    button.textContent = added ? 'Agregado' : 'Agregar';
  });
};

const bindLine = (form, line) => {
  line.querySelector('[data-quote-remove-line]')?.addEventListener('click', () => {
    const next = line.nextElementSibling || line.previousElementSibling;
    line.remove();
    updateSelection(form);
    next?.querySelector('input:not([type="hidden"])')?.focus();
  });
};

const newLine = form => {
  if (form.querySelectorAll('[data-quote-line]').length >= 20) return null;
  const template = form.parentElement?.querySelector('[data-quote-line-template]');
  return template?.content.firstElementChild?.cloneNode(true) || null;
};

const appendLine = (form, data = {}) => {
  const variantId = String(data.variantId || '');
  if (variantId) {
    const existing = [...form.querySelectorAll('[data-quote-line]')].find(line => line.querySelector('[data-quote-variant-id]')?.value === variantId);
    if (existing) {
      existing.querySelector('[name="quantity[]"]')?.focus();
      return existing;
    }
  }
  const line = newLine(form);
  if (!line) return null;
  const variant = line.querySelector('[data-quote-variant-id]');
  const description = line.querySelector('[name="description[]"]');
  const unit = line.querySelector('[name="unit[]"]');
  const source = line.querySelector('[data-quote-source]');
  if (variant) variant.value = variantId;
  if (description) {
    description.value = data.description || '';
    description.readOnly = Boolean(variantId);
  }
  if (unit) {
    unit.value = data.unit || '';
    unit.readOnly = Boolean(variantId);
  }
  if (source) source.textContent = variantId ? `${data.sku || 'Catálogo'} · Catálogo` : 'Material libre';
  line.classList.toggle('is-catalog', Boolean(variantId));
  form.querySelector('[data-quote-lines]')?.append(line);
  bindLine(form, line);
  updateSelection(form);
  form.querySelector('[data-quote-selection-error]')?.setAttribute('hidden', '');
  (variantId ? line.querySelector('[name="quantity[]"]') : description)?.focus();
  return line;
};

const bindCatalog = form => {
  const search = form.querySelector('[data-quote-product-search]');
  const category = form.querySelector('[data-quote-category-filter]');
  const rows = [...form.querySelectorAll('[data-product-result]')];
  const count = form.querySelector('[data-quote-results-count]');
  const noResults = form.querySelector('[data-quote-no-results]');
  const filter = () => {
    const term = normalize(search?.value);
    const selectedCategory = category?.value || '';
    let matches = 0;
    rows.forEach(row => {
      const matchesSearch = term === '' || normalize(row.dataset.search).includes(term);
      const matchesCategory = selectedCategory === '' || row.dataset.category === selectedCategory;
      const visible = matchesSearch && matchesCategory && matches < 10;
      row.hidden = !visible;
      if (matchesSearch && matchesCategory) matches++;
    });
    if (count) count.textContent = matches === 1 ? '1 resultado' : `${matches} resultados`;
    if (noResults) noResults.hidden = matches > 0;
  };
  search?.addEventListener('input', filter);
  category?.addEventListener('change', filter);
  form.querySelectorAll('[data-quote-add-product]').forEach(button => button.addEventListener('click', () => appendLine(form, {
    variantId: button.dataset.variantId,
    description: button.dataset.description,
    unit: button.dataset.unit,
    sku: button.dataset.sku,
  })));
  filter();
};

const bindForm = form => {
  form.querySelectorAll('[data-quote-line]').forEach(line => bindLine(form, line));
  form.querySelector('[data-quote-add-free], [data-quote-add-line]')?.addEventListener('click', () => appendLine(form));
  bindCatalog(form);
  updateSelection(form);
  form.addEventListener('submit', event => {
    if (!form.querySelector('[data-quote-line]')) {
      event.preventDefault();
      const error = form.querySelector('[data-quote-selection-error]');
      if (error) error.hidden = false;
      form.querySelector('[data-quote-product-search]')?.focus();
      return;
    }
    const submit = form.querySelector('[data-quote-submit]');
    if (submit) { submit.disabled = true; submit.textContent = 'Enviando…'; }
  });
};

export function publicQuoteRequest() {
  const dialog = document.querySelector('#public-quote-dialog');
  [...document.querySelectorAll('[data-public-quote-form]')].forEach(bindForm);
  if (!dialog) return;
  const close = () => { if (dialog.open) dialog.close(); };
  dialog.querySelectorAll('[data-quote-close]').forEach(button => button.addEventListener('click', close));
  dialog.addEventListener('click', event => { if (event.target === dialog) close(); });
  dialog.addEventListener('close', () => document.body.classList.remove('has-modal-open'));
  const open = trigger => {
    if (!dialog.open) dialog.showModal();
    document.body.classList.add('has-modal-open');
    const form = dialog.querySelector('[data-public-quote-form]');
    const variantId = trigger?.dataset.quoteVariant;
    if (form && variantId) appendLine(form, {
      variantId,
      description: trigger.dataset.quoteDescription,
      unit: trigger.dataset.quoteUnit,
      sku: trigger.dataset.quoteSku,
    });
  };
  document.querySelectorAll('[data-quote-open]').forEach(trigger => trigger.addEventListener('click', event => { event.preventDefault(); open(trigger); }));
  if (dialog.hasAttribute('data-quote-reopen')) open();
}
