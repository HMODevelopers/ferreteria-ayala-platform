export function initTransfers(root = document) {
  const form = root.querySelector('[data-transfer-create]');
  if (form && !form.dataset.initialized) {
    form.dataset.initialized = '1';
    const results = form.querySelector('[data-transfer-results]');
    const status = form.querySelector('[data-transfer-status]');
    const search = form.querySelector('[data-transfer-search]');
    const find = form.querySelector('[data-transfer-find]');
    let requestId = 0;
    const updateProducts = async () => {
      const source = form.elements.source_branch_id.value;
      if (!source) { status.textContent = 'Selecciona la sucursal origen antes de buscar mercancía.'; form.elements.source_branch_id.focus(); return; }
      const currentRequest = ++requestId;
      find.disabled = true;
      status.textContent = 'Buscando mercancía y disponibilidad…';
      try {
        const query = new URLSearchParams({q: search.value, source_branch_id: source});
        const response = await fetch('/admin/transfers/products?' + query, {headers: {Accept: 'application/json'}});
        if (!response.ok || response.redirected) throw new Error();
        const variants = await response.json();
        if (currentRequest !== requestId || !form.isConnected) return;
        const retained = new Map();
        for (const row of [...results.rows]) {
          const input = row.querySelector('input[name^="quantity"]');
          if (input?.value && !/^0(?:\.0+)?$/.test(input.value)) retained.set(input.name, row);
          else row.remove();
        }
        for (const variant of variants) {
          const existing = retained.get('quantity[' + variant.id + ']');
          if (existing) { existing.cells[1].textContent = variant.balance?.available_quantity ?? 'Sin existencia inicial'; continue; }
          const row = document.createElement('tr');
          const cell = (label, text) => { const td = document.createElement('td'); td.dataset.label = label; td.textContent = text; row.append(td); return td; };
          cell('Producto / SKU', variant.product_name + ' · ' + variant.sku);
          cell('Disponible origen', variant.balance?.available_quantity ?? 'Sin existencia inicial');
          const unit = cell('Unidad', '');
          const select = document.createElement('select');
          select.name = 'unit_id[' + variant.id + ']'; select.setAttribute('aria-label', 'Unidad ' + variant.sku);
          for (const item of variant.units) { const option = document.createElement('option'); option.value = item.id; option.textContent = item.name + ' (' + item.symbol + ')'; select.append(option); }
          unit.append(select);
          const quantityCell = cell('Cantidad solicitada', '');
          const quantity = document.createElement('input'); quantity.name = 'quantity[' + variant.id + ']'; quantity.inputMode = 'decimal'; quantity.setAttribute('aria-label', 'Cantidad ' + variant.sku); quantityCell.append(quantity); results.append(row);
        }
        status.textContent = variants.length + ' resultados. ' + (variants.length ? 'Disponibilidad en unidad base; se verifica nuevamente al aprobar.' : 'Prueba con otro nombre o SKU. Las cantidades capturadas se conservan.');
      } catch {
        if (currentRequest === requestId) status.textContent = 'No se pudo consultar la mercancía. Conservamos las cantidades capturadas.';
      } finally { if (currentRequest === requestId) find.disabled = false; }
    };
    find.addEventListener('click', updateProducts);
    search.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); updateProducts(); } });
    form.elements.source_branch_id.addEventListener('change', () => {
      requestId++; find.disabled = false;
      for (const row of results.rows) row.cells[1].textContent = 'Vuelve a buscar para consultar este origen';
      status.textContent = 'Origen actualizado. Busca nuevamente para revisar la disponibilidad; conservamos las cantidades.';
    });
  }
  for (const form of root.querySelectorAll('[data-transfer-event], [data-transfer-create]')) {
    // Modal submission is handled by the shared admin controller, including retries.
    if (form.closest('[data-admin-modal]') || form.dataset.submitInitialized) continue;
    form.dataset.submitInitialized = '1';
    form.addEventListener('submit', event => {
      if (form.dataset.submitting) { event.preventDefault(); return; }
      form.dataset.submitting = '1';
      for (const button of form.querySelectorAll('button[type="submit"]')) button.disabled = true;
    });
  }
}
