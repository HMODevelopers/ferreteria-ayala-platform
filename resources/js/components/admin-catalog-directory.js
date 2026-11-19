const DIRECTORY_SELECTOR = '[data-catalog-async]';

export function initAdminCatalogDirectory() {
  let requestController = null;
  let searchTimer = 0;
  let loaderTimer = 0;

  const workspaceFor = element => element?.closest?.(DIRECTORY_SELECTOR);

  const labelTable = workspace => {
    workspace?.querySelectorAll('.admin-table').forEach(table => {
      const headings = [...table.querySelectorAll('thead th')].map(cell => cell.textContent.trim());
      table.querySelectorAll('tbody tr').forEach(row => {
        [...row.children].forEach((cell, index) => { cell.dataset.label = headings[index] || 'Acciones'; });
      });
    });
  };

  const setBusy = (workspace, busy, message = 'Actualizando resultados') => {
    if (!workspace) return;
    window.clearTimeout(loaderTimer);
    if (busy) workspace.setAttribute('aria-busy', 'true');
    else workspace.removeAttribute('aria-busy');
    const loader = workspace.querySelector('[data-catalog-loader]');
    const label = loader?.querySelector('[data-catalog-loader-label]');
    if (label) label.textContent = message;
    if (!busy) {
      workspace.classList.remove('is-loading');
      if (loader) loader.hidden = true;
      return;
    }
    if (loader) loader.hidden = true;
    loaderTimer = window.setTimeout(() => {
      if (!workspace.isConnected) return;
      workspace.classList.add('is-loading');
      if (loader) loader.hidden = false;
    }, 140);
  };

  const notify = (message, type = 'success') => {
    let region = document.querySelector('[data-admin-toast-region]');
    if (!region) {
      region = document.createElement('div');
      region.className = 'admin-toast-region';
      region.dataset.adminToastRegion = '';
      region.setAttribute('aria-live', 'polite');
      region.setAttribute('aria-atomic', 'true');
      document.body.append(region);
    }
    const toast = document.createElement('div');
    toast.className = `admin-toast is-${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.innerHTML = '<span class="admin-toast-icon" aria-hidden="true"></span><span></span><button type="button" aria-label="Cerrar notificación">×</button>';
    toast.children[1].textContent = message;
    const close = () => { toast.classList.add('is-leaving'); window.setTimeout(() => toast.remove(), 180); };
    toast.querySelector('button').addEventListener('click', close);
    region.append(toast);
    window.setTimeout(close, 4500);
  };

  const parsedWorkspace = html => {
    const page = new DOMParser().parseFromString(html, 'text/html');
    return { page, workspace: page.querySelector(DIRECTORY_SELECTOR) };
  };

  const loadDirectory = async (url, { push = true, focusName = '' } = {}) => {
    const current = document.querySelector(DIRECTORY_SELECTOR);
    if (!current) return false;
    requestController?.abort();
    requestController = new AbortController();
    setBusy(current, true, 'Actualizando resultados');
    try {
      const response = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
        credentials: 'same-origin',
        signal: requestController.signal,
      });
      if (response.redirected && new URL(response.url).pathname === '/admin/login') {
        window.location.assign(response.url);
        return false;
      }
      if (!response.ok) throw new Error('No fue posible actualizar la tabla.');
      const { workspace: next } = parsedWorkspace(await response.text());
      if (!next) throw new Error('La respuesta no contiene el listado solicitado.');
      const imported = document.importNode(next, true);
      setBusy(current, false);
      current.replaceWith(imported);
      labelTable(imported);
      document.dispatchEvent(new CustomEvent('admin:content-updated', { detail: { root: imported } }));
      if (push) window.history.pushState({ catalogDirectory: true }, '', url);
      if (focusName) {
        const field = imported.querySelector(`[name="${CSS.escape(focusName)}"]`);
        if (field) {
          field.focus({ preventScroll: true });
          if (field instanceof HTMLInputElement) field.setSelectionRange(field.value.length, field.value.length);
        }
      }
      return true;
    } catch (error) {
      if (error?.name === 'AbortError') return false;
      setBusy(current, false);
      notify(error instanceof Error ? error.message : 'No fue posible actualizar la tabla.', 'error');
      return false;
    }
  };

  const filterUrl = form => {
    const url = new URL(form.action || window.location.href, window.location.href);
    url.search = '';
    for (const [key, value] of new FormData(form)) {
      const normalized = String(value).trim();
      if (normalized !== '' && normalized !== 'all') url.searchParams.append(key, normalized);
    }
    return url;
  };

  document.addEventListener('input', event => {
    const field = event.target.closest(`${DIRECTORY_SELECTOR} [data-catalog-filter] input[type="search"]`);
    if (!field) return;
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => loadDirectory(filterUrl(field.form), { focusName: field.name }), 350);
  });

  document.addEventListener('change', event => {
    const field = event.target.closest(`${DIRECTORY_SELECTOR} [data-catalog-filter] select`);
    if (field) loadDirectory(filterUrl(field.form), { focusName: field.name });
  });

  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const workspace = workspaceFor(form);
    if (!workspace) return;
    if (form.matches('[data-catalog-filter]')) {
      event.preventDefault();
      window.clearTimeout(searchTimer);
      loadDirectory(filterUrl(form));
      return;
    }
    if (!form.matches('[data-catalog-status-form]')) return;
    event.preventDefault();
    if (form.dataset.submitting === '1') return;
    form.dataset.submitting = '1';
    const submit = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (submit) submit.disabled = true;
    setBusy(workspace, true, 'Guardando el cambio de estado');
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form, event.submitter),
        headers: { Accept: 'text/html' },
        credentials: 'same-origin',
      });
      if (response.redirected && new URL(response.url).pathname === '/admin/login') {
        window.location.assign(response.url);
        return;
      }
      if (!response.ok) throw new Error('No fue posible guardar el cambio de estado.');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const alert = page.querySelector('#admin-content > .admin-alert');
      const type = alert?.classList.contains('admin-alert-error') ? 'error' : 'success';
      const refreshed = await loadDirectory(window.location.href, { push: false });
      if (refreshed) notify(alert?.textContent.trim() || 'El estado se actualizó correctamente.', type);
    } catch (error) {
      setBusy(document.querySelector(DIRECTORY_SELECTOR), false);
      notify(error instanceof Error ? error.message : 'No fue posible guardar el cambio.', 'error');
    } finally {
      delete form.dataset.submitting;
      if (submit) submit.disabled = false;
    }
  });

  document.addEventListener('click', event => {
    const link = event.target.closest(`${DIRECTORY_SELECTOR} [data-catalog-page-link], ${DIRECTORY_SELECTOR} [data-catalog-clear]`);
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    loadDirectory(link.href);
  });

  document.addEventListener('click', event => {
    const activeMenu = event.target.closest('.catalog-action-menu');
    document.querySelectorAll('.catalog-action-menu[open]').forEach(menu => {
      if (menu !== activeMenu) menu.removeAttribute('open');
    });
    if (activeMenu && event.target.closest('a, button')) {
      window.setTimeout(() => activeMenu.removeAttribute('open'), 0);
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const menu = document.querySelector('.catalog-action-menu[open]');
    if (!menu) return;
    menu.removeAttribute('open');
    menu.querySelector('summary')?.focus();
  });

  window.addEventListener('popstate', () => {
    if (document.querySelector(DIRECTORY_SELECTOR)) loadDirectory(window.location.href, { push: false });
  });

  labelTable(document.querySelector(DIRECTORY_SELECTOR));
}
