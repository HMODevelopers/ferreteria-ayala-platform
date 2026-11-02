export function admin() {
  const menu = document.querySelector('[data-admin-menu]');
  const sidebar = document.querySelector('[data-admin-sidebar]');
  const scrim = document.querySelector('[data-admin-scrim]');
  const closeMenu = () => {
    sidebar?.classList.remove('is-open');
    menu?.setAttribute('aria-expanded', 'false');
  };
  if (menu && sidebar) {
    menu.addEventListener('click', () => {
      const open = sidebar.classList.toggle('is-open');
      menu.setAttribute('aria-expanded', String(open));
    });
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
    scrim?.addEventListener('click', closeMenu);
  }

  const accountMenu = document.querySelector('[data-admin-account-menu]');
  accountMenu?.addEventListener('toggle', () => {
    const trigger = accountMenu.querySelector('summary');
    trigger?.setAttribute('aria-label', accountMenu.open ? 'Cerrar menú de cuenta' : 'Abrir menú de cuenta');
  });
  document.addEventListener('click', event => {
    if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.open = false;
  });
  accountMenu?.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      accountMenu.open = false;
      accountMenu.querySelector('summary')?.focus();
    }
  });

  document.querySelectorAll('.admin-table').forEach(table => {
    const headings = [...table.querySelectorAll('thead th')].map(cell => cell.textContent.trim());
    table.querySelectorAll('tbody tr').forEach(row => {
      [...row.children].forEach((cell, index) => cell.dataset.label = headings[index] || 'Acciones');
    });
  });

  const modal = document.querySelector('[data-admin-modal]');
  const modalContent = modal?.querySelector('[data-admin-modal-content]');
  const modalTitle = modal?.querySelector('#admin-modal-title');
  const formCache = new Map();
  let activeModalRequest = 0;
  const closeModal = () => modal?.close();
  modal?.querySelector('[data-admin-modal-close]')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', event => {
    if (event.target === modal) closeModal();
  });

  const isFormLink = link => {
    const url = new URL(link.href, window.location.href);
    return url.origin === window.location.origin && /\/admin\/(?:users|branches|categories|brands|units|products|prices|roles)\/(?:create|edit)$/.test(url.pathname);
  };

  const skeleton = () => {
    const wrapper = document.createElement('div');
    wrapper.className = 'admin-modal-skeleton';
    wrapper.setAttribute('role', 'status');
    wrapper.setAttribute('aria-label', 'Cargando formulario');
    wrapper.innerHTML = '<span class="admin-skeleton admin-skeleton-kicker"></span><span class="admin-skeleton admin-skeleton-title"></span><span class="admin-skeleton admin-skeleton-copy"></span><div class="admin-skeleton-panel"><span class="admin-skeleton admin-skeleton-field"></span><span class="admin-skeleton admin-skeleton-field"></span><span class="admin-skeleton admin-skeleton-field is-wide"></span><span class="admin-skeleton admin-skeleton-button"></span></div>';
    return wrapper;
  };

  const loadForm = url => {
    if (formCache.has(url)) return formCache.get(url);
    const request = fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(async response => {
        if (!response.ok) throw new Error('No fue posible cargar el formulario.');
        if (response.redirected && new URL(response.url).pathname === '/admin/login') {
          window.location.assign(response.url);
          return null;
        }
        return response.text();
      })
      .catch(error => {
        formCache.delete(url);
        throw error;
      });
    formCache.set(url, request);
    return request;
  };

  document.querySelectorAll('a[href]').forEach(link => {
    if (!modal || !modalContent || !isFormLink(link)) return;
    const prefetch = () => { loadForm(link.href).catch(() => {}); };
    link.addEventListener('pointerenter', prefetch, {once: true});
    link.addEventListener('focus', prefetch, {once: true});
    link.addEventListener('click', async event => {
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      const requestId = ++activeModalRequest;
      modalContent.replaceChildren(skeleton());
      modalContent.setAttribute('aria-busy', 'true');
      modalTitle.textContent = link.textContent.trim().replace(/^\+\s*/, '') || 'Formulario';
      modal.showModal();
      try {
        const html = await loadForm(link.href);
        if (html === null || requestId !== activeModalRequest) return;
        const page = new DOMParser().parseFromString(html, 'text/html');
        const content = page.querySelector('#admin-content');
        if (!content) throw new Error('El formulario no está disponible.');
        modalContent.replaceChildren(...[...content.childNodes].map(node => node.cloneNode(true)));
        modalContent.removeAttribute('aria-busy');
        modalTitle.textContent = modalContent.querySelector('h1')?.textContent.trim() || modalTitle.textContent;
        modalContent.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
        modalContent.querySelectorAll('[data-confirm]').forEach(bindConfirmation);
      } catch (error) {
        if (requestId !== activeModalRequest) return;
        modalContent.replaceChildren();
        modalContent.removeAttribute('aria-busy');
        const alert = document.createElement('div');
        alert.className = 'admin-alert admin-alert-error';
        alert.setAttribute('role', 'alert');
        alert.textContent = error instanceof Error ? error.message : 'No fue posible cargar el formulario.';
        const fallback = document.createElement('a');
        fallback.className = 'admin-primary-button';
        fallback.href = link.href;
        fallback.textContent = 'Abrir formulario';
        modalContent.append(alert, fallback);
      }
    });
  });

  function bindConfirmation(control) {
    control.addEventListener('click', event => {
      if (!window.confirm(control.getAttribute('data-confirm') || '¿Confirmar esta acción?')) event.preventDefault();
    });
  }
  document.querySelectorAll('[data-confirm]').forEach(control => {
    bindConfirmation(control);
  });
}
