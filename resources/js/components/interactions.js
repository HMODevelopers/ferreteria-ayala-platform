const normalized = value => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
export function interactions() {
  const cards = [...document.querySelectorAll('[data-product]')];
  if (!cards.length) return;
  const search = document.querySelector('#search');
  const status = document.querySelector('#search-status');
  const list = new Map();
  const dialog = document.querySelector('#quote-dialog');
  const items = document.querySelector('#quote-items');
  const download = document.querySelector('#quote-download');
  let category = 'all';
  let toastTimer;
  const toast = message => {
    const node = document.querySelector('#toast');
    node.textContent = message;
    node.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove('is-visible'), 3500);
  };
  const filter = () => {
    const term = normalized(search.value.trim());
    let count = 0;
    cards.forEach(card => {
      const match = (category === 'all' || card.dataset.category === category) && normalized(`${card.dataset.name} ${card.dataset.sku} ${card.dataset.category}`).includes(term);
      card.hidden = !match;
      if (match) count++;
    });
    document.querySelectorAll('[data-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.filter === category)));
    document.querySelector('#empty-results').hidden = count > 0;
    status.textContent = `${count} ${count === 1 ? 'producto' : 'productos'} de referencia${search.value.trim() ? ` para “${search.value.trim()}”` : ''}`;
  };
  document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => { category = button.dataset.filter; filter(); }));
  document.querySelectorAll('[data-category-link]').forEach(link => link.addEventListener('click', () => {
    category = link.dataset.categoryLink; search.value = ''; filter();
  }));
  search.addEventListener('input', filter);
  document.querySelector('#product-search').addEventListener('submit', event => {
    event.preventDefault(); category = 'all'; filter();
    document.querySelector('#productos').scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  });
  document.querySelector('#reset-search').addEventListener('click', () => { category = 'all'; search.value = ''; filter(); document.querySelector('[data-filter="all"]').focus(); });
  const create = (tag, className, text) => {
    const element = document.createElement(tag); element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  };
  const updateCount = () => document.querySelectorAll('[data-quote-count]').forEach(node => { node.textContent = [...list.values()].reduce((sum, product) => sum + product.quantity, 0); });
  const renderList = () => {
    items.replaceChildren();
    download.disabled = list.size === 0;
    if (!list.size) items.append(create('p', 'quote-empty', 'Tu lista está lista para empezar. Explora los productos y pulsa + para agregar materiales de referencia.'));
    list.forEach((product, id) => {
      const row = create('div', 'quote-item');
      const info = create('div', '');
      info.append(create('h3', '', product.name), create('p', '', `${product.sku} · ${product.unit}`));
      const label = create('label', '', 'Cantidad');
      const input = create('input', ''); input.type = 'number'; input.min = '1'; input.max = '999'; input.step = '1'; input.value = product.quantity;
      input.setAttribute('aria-label', `Cantidad de ${product.name}`);
      input.addEventListener('change', () => {
        product.quantity = Math.max(1, Math.min(999, Math.trunc(Number(input.value)) || 1));
        input.value = product.quantity; updateCount();
      });
      label.append(input);
      const remove = create('button', '', '×'); remove.type = 'button'; remove.setAttribute('aria-label', `Quitar ${product.name}`);
      remove.addEventListener('click', () => {
        const ids = [...list.keys()]; const index = ids.indexOf(id);
        list.delete(id); renderList(); updateCount();
        const remaining = items.querySelectorAll('.quote-item button');
        if (remaining.length) remaining[Math.min(index, remaining.length - 1)].focus(); else dialog.querySelector('[data-close]').focus();
      });
      row.append(info, label, remove); items.append(row);
    });
  };
  cards.forEach(card => {
    const button = card.querySelector('.add-product'); button.disabled = false;
    button.addEventListener('click', () => {
      const { id, name, sku, unit } = card.dataset;
      const product = list.get(id);
      if (product) product.quantity = Math.min(999, product.quantity + 1);
      else list.set(id, { name, sku, unit, quantity: 1 });
      updateCount(); toast(`${name} agregado a tu lista demo.`);
    });
  });
  const open = () => { renderList(); dialog.showModal(); };
  const quoteOpen = document.querySelector('#quote-open'); quoteOpen.disabled = false; quoteOpen.addEventListener('click', open);
  document.querySelector('.header-quote').addEventListener('click', event => { event.preventDefault(); open(); });
  download.addEventListener('click', () => {
    const text = ['FERRETERÍA AYALA — LISTA DE DEMOSTRACIÓN', 'No enviada. Precios y disponibilidad por confirmar.', '', ...[...list.values()].map(product => `${product.quantity} × ${product.name} | ${product.unit} | ${product.sku}`)].join('\n');
    const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
    const anchor = create('a', ''); anchor.href = url; anchor.download = 'ayala-lista-demo.txt'; anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000); toast('Lista descargada. No se ha enviado ninguna solicitud.');
  });
  const query = new URLSearchParams(location.search).get('q');
  if (query) { search.value = query.slice(0, 100); filter(); }
}
