const STORAGE_PREFIX = 'ayala-public-quote:';

export function publicQuoteRequest() {
  const requestForm = document.querySelector('.cms-request-form');
  const searchForm = document.querySelector('.cms-select');
  const completed = document.querySelector('[data-public-request-complete]');
  if (completed) {
    const key = completed.dataset.publicRequestComplete;
    if (/^[a-f0-9]{48}$/.test(key)) sessionStorage.removeItem(`${STORAGE_PREFIX}${key}`);
    return;
  }
  if (!requestForm || !searchForm) return;

  const operation = requestForm.querySelector('[name="operation_key"]')?.value || '';
  if (!/^[a-f0-9]{48}$/.test(operation)) return;
  const storageKey = `${STORAGE_PREFIX}${operation}`;
  let selected = {};
  try {
    const stored = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
    if (stored && typeof stored === 'object' && !Array.isArray(stored)) selected = stored;
  } catch (_) {
    sessionStorage.removeItem(storageKey);
  }

  const quantities = [...requestForm.querySelectorAll('input[name^="quantity["]')];
  const save = () => sessionStorage.setItem(storageKey, JSON.stringify(selected));
  quantities.forEach(input => {
    const match = input.name.match(/^quantity\[(\d+)\]$/);
    if (!match) return;
    const id = match[1];
    if (input.value === '' && typeof selected[id] === 'string') input.value = selected[id];
    input.addEventListener('input', () => {
      const value = input.value.trim();
      if (value === '') delete selected[id]; else selected[id] = value.slice(0, 32);
      save();
    });
  });

  searchForm.addEventListener('submit', save);
  requestForm.addEventListener('submit', () => {
    const rendered = new Set(quantities.map(input => input.name.match(/^quantity\[(\d+)\]$/)?.[1]).filter(Boolean));
    Object.entries(selected).forEach(([id, value]) => {
      if (!/^\d+$/.test(id) || rendered.has(id) || typeof value !== 'string' || value === '') return;
      const hidden = document.createElement('input');
      hidden.type = 'hidden'; hidden.name = `quantity[${id}]`; hidden.value = value;
      requestForm.append(hidden);
    });
  });
}
