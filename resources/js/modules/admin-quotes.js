export function initAdminQuotes(scope = document) {
  scope.querySelectorAll('[data-quote-print]').forEach(button => button.addEventListener('click', () => window.print()));
  scope.querySelectorAll('[data-quote-editor]').forEach(form => {
    const search = form.querySelector('[data-quote-search]');
    const button = form.querySelector('[data-quote-search-button]');
    const results = form.querySelector('[data-quote-results]');
    const status = form.querySelector('[data-quote-search-status]');
    if (!search || !button || !results || !status) return;
    let request;
    const find = async () => {
      request?.abort(); request = new AbortController();
      status.textContent = 'Consultando catálogo, precios e inventario…';
      const params = new URLSearchParams({ branch_id: form.elements.branch_id.value, price_list_id: form.elements.price_list_id.value, q: search.value });
      try {
        const response = await fetch('/admin/quotes/lookup?' + params, { signal: request.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('No se pudo completar la consulta.');
        const data = await response.json();
        results.replaceChildren();
        for (const item of data.items) {
          const card = document.createElement('div'); card.className = 'quote-result';
          const title = document.createElement('strong'); title.textContent = item.product_name + ' · ' + item.name;
          const code = document.createElement('small'); code.textContent = item.sku + ' · ' + item.unit_name;
          const price = document.createElement('p'); price.textContent = item.price ? '$' + item.price.price.amount + ' MXN · ' + (item.price.price.tax_included === 1 || item.price.price.tax_included === '1' ? 'Impuesto incluido' : 'Más impuesto') : 'Sin precio configurado.';
          const stock = document.createElement('p'); stock.textContent = item.balance ? 'Físico: ' + item.balance.physical_quantity + ' · Apartado: ' + item.balance.reserved_quantity + ' · Disponible: ' + item.balance.available_quantity : 'Sin existencia inicial registrada.';
          const add = document.createElement('button'); add.type = 'button'; add.className = 'admin-secondary-button'; add.textContent = 'Seleccionar material';
          add.addEventListener('click', () => {
            const select = form.querySelector('[data-quote-add-variant]');
            let option = [...select.options].find(option => option.value === String(item.id));
            if (!option) { option = new Option(item.sku + ' · ' + item.product_name, String(item.id)); select.add(option); }
            select.value = String(item.id); form.elements.add_quantity.focus();
            status.textContent = 'Material seleccionado. Captura cantidad y pulsa Agregar y calcular.';
          });
          card.append(title, code, price, stock, add); results.append(card);
        }
        status.textContent = data.items.length ? data.items.length + ' resultados. La disponibilidad es informativa y no garantiza existencias futuras.' : 'No se encontraron variantes activas.';
      } catch (error) { if (error.name !== 'AbortError') status.textContent = error.message; }
    };
    button.addEventListener('click', find);
    search.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); find(); } });
  });
}
