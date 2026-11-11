import { initBranchMaps } from './branch-map.js';
import { initDeliveries } from '../modules/deliveries.js';
import { initTransfers } from '../modules/transfers.js';
import { initAdminQuotes } from '../modules/admin-quotes.js';
import { initQuoteWorkflow } from './quote-workflow.js';
import { initMediaUpload } from './admin-media-upload.js';
import { initAdminNavigation } from './admin-navigation.js';
import { initAdminRoles } from './admin-roles.js';
import { initCmsEditor } from '../modules/cms.js';

export function admin() {
  initAdminNavigation();
  initAdminRoles(document);
  initBranchMaps();
  initQuoteWorkflow();
  initMediaUpload(document.querySelector('[data-media-upload-form]'));
  const menu = document.querySelector('[data-admin-menu]');
  const sidebar = document.querySelector('[data-admin-sidebar]');
  const scrim = document.querySelector('[data-admin-scrim]');
  const main = document.querySelector('.admin-main');
  const mobile = window.matchMedia('(max-width: 900px)');
  const syncMenu = () => {
    const open = mobile.matches && sidebar?.classList.contains('is-open');
    menu?.setAttribute('aria-expanded', open ? 'true' : 'false');
    menu?.setAttribute('aria-label', open ? 'Cerrar navegación' : 'Abrir navegación');
    if (sidebar) sidebar.inert = mobile.matches && !open;
    if (main) main.inert = Boolean(open);
  };
  const closeMenu = (restoreFocus = false) => {
    sidebar?.classList.remove('is-open');
    syncMenu();
    if (restoreFocus && mobile.matches) menu?.focus();
  };
  if (menu && sidebar) {
    syncMenu();
    menu.addEventListener('click', () => {
      sidebar.classList.toggle('is-open');
      syncMenu();
      if (sidebar.classList.contains('is-open')) {
        (sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector('a'))?.focus();
      }
    });
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => closeMenu()));
    sidebar.querySelector('[data-admin-sidebar-close]')?.addEventListener('click', () => closeMenu(true));
    scrim?.addEventListener('click', () => closeMenu(true));
    mobile.addEventListener('change', () => {
      const returnFocus = mobile.matches && sidebar.contains(document.activeElement);
      closeMenu(returnFocus);
    });
    document.addEventListener('keydown', event => {
      if (!mobile.matches || !sidebar.classList.contains('is-open')) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu(true);
      } else if (event.key === 'Tab') {
        const controls = [...sidebar.querySelectorAll('a[href], button:not([disabled])')].filter(el => el.getClientRects().length);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
      }
    });
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
  let cleanupModalMedia = () => {};
  let cleanupModalMaps = () => {};
  const closeModal = () => modal?.close();
  modal?.addEventListener('close', () => { activeModalRequest++; cleanupModalMedia(); cleanupModalMaps(); });
  modal?.querySelector('[data-admin-modal-close]')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', event => {
    if (event.target === modal) closeModal();
  });

  const isFormLink = link => {
    const url = new URL(link.href, window.location.href);
    return url.origin === window.location.origin && ((link.hasAttribute('data-admin-form-link') && ['/admin/branches/create','/admin/branches/edit','/admin/branches/detail','/admin/prices/create','/admin/prices/edit','/admin/prices/calculate','/admin/products/create','/admin/products/edit','/admin/products/detail','/admin/categories/create','/admin/categories/edit','/admin/brands/create','/admin/brands/edit','/admin/units/create','/admin/units/edit','/admin/attributes','/admin/deliveries/create','/admin/deliveries/detail','/admin/deliveries/resources','/admin/returns/create','/admin/returns/detail','/admin/transfers/create','/admin/transfers/detail','/admin/purchases/create','/admin/purchases/edit','/admin/purchases/detail','/admin/purchases/receipts/create','/admin/suppliers/create','/admin/suppliers/edit','/admin/inventory/adjustments','/admin/inventory/movements','/admin/cms','/admin/cms/terms','/admin/media','/admin/customers/create','/admin/customers/edit','/admin/customers/detail','/admin/customers/status','/admin/quotes/intake','/admin/quotes/workflow','/admin/quotes/workflow/action','/admin/quotes/workflow/prepare','/admin/quotes/create','/admin/quotes/edit','/admin/quotes/detail','/admin/quotes/action'].includes(url.pathname)) || /\/admin\/(?:users|branches|categories|brands|units|products|prices|roles)\/(?:create|edit)$/.test(url.pathname));
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
    const cacheable = !['/admin/cms','/admin/cms/terms','/admin/media'].includes(new URL(url).pathname) && !new URL(url).pathname.startsWith('/admin/customers/') && !new URL(url).pathname.startsWith('/admin/quotes/') && !new URL(url).pathname.startsWith('/admin/inventory/') && !new URL(url).pathname.startsWith('/admin/purchases/') && !new URL(url).pathname.startsWith('/admin/suppliers/') && !new URL(url).pathname.startsWith('/admin/transfers/') && !new URL(url).pathname.startsWith('/admin/deliveries/') && !new URL(url).pathname.startsWith('/admin/returns/') && !/^\/admin\/(branches|prices|products|categories|brands|units|attributes)(\/|$)/.test(new URL(url).pathname);
    if (cacheable && formCache.has(url)) return formCache.get(url);
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
    if (cacheable) formCache.set(url, request);
    return request;
  };

  const renderModalContent = html => {
    cleanupModalMedia();
    cleanupModalMaps();
    const page = new DOMParser().parseFromString(html, 'text/html');
    const content = page.querySelector('#admin-content');
    if (!content) throw new Error('El formulario no está disponible.');
    modalContent.replaceChildren(...[...content.childNodes].map(node => node.cloneNode(true)));
    modalContent.removeAttribute('aria-busy');
    modalTitle.textContent = modalContent.querySelector('h1')?.textContent.trim() || modalTitle.textContent;
    modalContent.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
    modalContent.querySelectorAll('[data-confirm]').forEach(bindConfirmation);
    cleanupModalMedia = initMediaUpload(modalContent.querySelector('[data-media-upload-form]'));
    initAdminQuotes(modalContent);
    initQuoteWorkflow(modalContent);
    initTransfers(modalContent);
    initDeliveries(modalContent);
    initAdminRoles(modalContent);
    initCmsEditor(modalContent);
    cleanupModalMaps = initBranchMaps(modalContent);
  };

  document.querySelectorAll('a[href]').forEach(link => {
    if (!modal || !isFormLink(link)) return;
    const prefetch = () => { loadForm(link.href).catch(() => {}); };
    link.addEventListener('pointerenter', prefetch, {once: true});
    link.addEventListener('focus', prefetch, {once: true});
  });
  document.addEventListener('click', async event => {
    const minimumButton = event.target.closest('[data-inventory-minimum]');
    if (minimumButton && modal && modalContent) {
      const template = document.getElementById(minimumButton.dataset.inventoryMinimum);
      if (!(template instanceof HTMLTemplateElement)) return;
      activeModalRequest++;
      cleanupModalMedia();
      modalContent.replaceChildren(template.content.cloneNode(true));
      modalContent.removeAttribute('aria-busy');
      modalTitle.textContent = 'Configurar existencia mínima';
      if (!modal.open) modal.showModal();
      modalContent.querySelector('input:not([type="hidden"])')?.focus();
      return;
    }
    const link = event.target.closest('a[href]');
    if (link?.hasAttribute('data-admin-modal-cancel') && modal?.open) {
      event.preventDefault();
      closeModal();
      return;
    }
    if (!link || !modal || !modalContent || !isFormLink(link)) return;
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    const requestId = ++activeModalRequest;
    modalContent.replaceChildren(skeleton());
    modalContent.setAttribute('aria-busy', 'true');
    modalTitle.textContent = link.textContent.trim().replace(/^\+\s*/, '') || 'Formulario';
    if (!modal.open) modal.showModal();
    try {
      const html = await loadForm(link.href);
      if (html === null || requestId !== activeModalRequest || !modal.open) return;
      renderModalContent(html);
    } catch (error) {
      if (requestId !== activeModalRequest || !modal.open) return;
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
  modalContent?.addEventListener('submit', async event => {
    const form = event.target;
    if(form.matches('[data-admin-modal-get]')){
      event.preventDefault();
      const editor=modalContent.querySelector('form[action="/admin/quotes/workflow/prepare"][method="post"]');
      const saved=editor?[...editor.elements].filter(el=>el.name&&el.name!=='_token').map(el=>({name:el.name,value:el.value,type:el.type,checked:el.checked,label:el.selectedOptions?.[0]?.textContent})):[];
      const agreementOpen=editor?.querySelector('.quote-agreement')?.open;
      const restorePreparation=()=>{const target=modalContent.querySelector('form[action="/admin/quotes/workflow/prepare"][method="post"]');if(!target)return;const positions=new Map();for(const savedField of saved){const position=positions.get(savedField.name)||0;positions.set(savedField.name,position+1);const el=[...target.elements].filter(field=>field.name===savedField.name)[position];if(!el)continue;if(el.tagName==='SELECT'&&savedField.value&&![...el.options].some(option=>option.value===savedField.value))el.add(new Option(savedField.label||savedField.value,savedField.value));el.value=savedField.value;if(savedField.type==='checkbox'||savedField.type==='radio')el.checked=savedField.checked;}const agreement=target.querySelector('.quote-agreement');if(agreement)agreement.open=Boolean(agreementOpen);};
      const requestId=++activeModalRequest;const url=new URL(form.getAttribute('action') || window.location.href, window.location.href);url.search=new URLSearchParams(new FormData(form)).toString();modalContent.setAttribute('aria-busy','true');
      try{const html=await loadForm(url.href);if(html!==null&&requestId===activeModalRequest&&modal.open){renderModalContent(html);restorePreparation();}}catch{if(requestId===activeModalRequest){const alert=document.createElement('p');alert.className='admin-alert admin-alert-error';alert.setAttribute('role','alert');alert.textContent='No se pudo actualizar la búsqueda. Intenta nuevamente.';form.prepend(alert);modalContent.removeAttribute('aria-busy');}}return;
    }
    if (!form.matches('[data-cms-editor-form],[data-media-upload-form],[data-customer-form],[data-quote-workflow-form],[data-purchasing-form],[data-transfer-create],[data-transfer-event],[data-delivery-create],[data-delivery-event],[data-delivery-resource],form[action="/admin/returns/create"],form[action="/admin/returns/action"],[data-catalog-form],[data-pricing-form],[data-branch-form],[data-branch-schedule],[data-role-form]')) return;
    event.preventDefault();
    if (form.dataset.submitting === '1') return;
    form.dataset.submitting = '1';
    const retainedFiles = form.querySelector('input[type=file]')?.files;
    const requestId = ++activeModalRequest;
    const submit = form.querySelector('button[type="submit"], button:not([type])');
    if (submit) submit.disabled = true;
    form.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(form.getAttribute('action') || window.location.href, {method: 'POST', body: new FormData(form, event.submitter), headers: {'Accept':'text/html'}});
      if (requestId !== activeModalRequest) return;
      if (response.redirected) {
        const destination = new URL(response.url);
        if(response.ok&&destination.origin===window.location.origin&&(['/admin/branches/edit','/admin/branches/detail','/admin/products/detail','/admin/products/edit','/admin/categories/edit','/admin/brands/edit','/admin/units/edit','/admin/deliveries/detail','/admin/deliveries/resources','/admin/returns/detail','/admin/transfers/detail','/admin/purchases/detail','/admin/quotes/workflow','/admin/quotes/edit','/admin/quotes/detail'].includes(destination.pathname)||(destination.pathname==='/admin/attributes'&&destination.searchParams.has('attribute_id')))){
          const html=await response.text();if(requestId!==activeModalRequest||!modal.open)return;renderModalContent(html);
          if (document.querySelector('.quote-inbox,.purchasing-workspace,.transfers-workspace,.deliveries-workspace,.catalog-workspace,.branches-workspace')) {
            try {
              const listResponse = await fetch(window.location.href, {headers: {Accept: 'text/html'}});
              if (!listResponse.ok || listResponse.redirected) return;
              const doc = new DOMParser().parseFromString(await listResponse.text(), 'text/html');
              const content = doc.querySelector('#admin-content');
              const current = document.querySelector('#admin-content');
              if (content && current && document.querySelector('.quote-inbox,.purchasing-workspace,.transfers-workspace,.deliveries-workspace,.catalog-workspace,.branches-workspace')) {
                current.replaceChildren(...[...content.childNodes].map(node => node.cloneNode(true)));
                current.querySelectorAll('[data-confirm]').forEach(bindConfirmation);
              }
            } catch { /* The modal retains the saved result; the list can be refreshed. */ }
          }
          return;
        }
        if (response.ok && destination.origin === window.location.origin && ['/admin/branches','/admin/prices','/admin/products','/admin/categories','/admin/brands','/admin/units','/admin/attributes','/admin/purchases','/admin/suppliers','/admin/cms','/admin/cms/terms','/admin/media','/admin/customers','/admin/roles'].includes(destination.pathname)) {
          // The redirected GET already consumed the flash; reuse its rendered list.
          const html = await response.text();
          if (requestId !== activeModalRequest || !modal.open) return;
          const page = new DOMParser().parseFromString(html, 'text/html');
          const content = page.querySelector('#admin-content');
          const current = document.querySelector('#admin-content');
          if (!content || !current) throw new Error('El contenido se guardó, pero no fue posible actualizar la lista. Recarga la página.');
          current.replaceChildren(...[...content.childNodes].map(node => node.cloneNode(true)));
          current.querySelectorAll('[data-confirm]').forEach(bindConfirmation);
          window.history.replaceState(null, '', destination.href);
          closeModal();
          const heading = current.querySelector('h1');
          if (heading) { heading.tabIndex = -1; heading.focus(); }
          return;
        }
        window.location.assign(response.url);
        return;
      }
      if (response.status === 422) {
        const html = await response.text();
        if (requestId !== activeModalRequest || !modal.open) return;
        renderModalContent(html);
        const fileInput = modalContent.querySelector('[data-media-upload-form] input[type=file]');
        if (fileInput && retainedFiles?.length) { fileInput.files = retainedFiles; fileInput.dispatchEvent(new Event('change')); }
        const alert = modalContent.querySelector('[role="alert"]');
        if (alert) { alert.tabIndex = -1; alert.focus(); }
        return;
      }
      throw new Error('No se pudo confirmar el guardado. Conservamos tus datos; revisa la conexión antes de volver a intentarlo.');
    } catch (error) {
      if (requestId !== activeModalRequest || !modal.open) return;
      const alert = document.createElement('p');
      alert.className = 'admin-alert admin-alert-error';
      alert.setAttribute('role', 'alert');
      alert.textContent = error instanceof Error ? error.message : 'No se pudo guardar el contenido.';
      form.prepend(alert);
    } finally {
      delete form.dataset.submitting;
      form.removeAttribute('aria-busy');
      if (submit) submit.disabled = false;
    }
  });

  function bindConfirmation(control) {
    if (control.dataset.confirmBound === 'true') return;
    control.dataset.confirmBound = 'true';
    control.addEventListener('click', event => {
      const confirmation = document.querySelector('[data-admin-confirm-modal]');
      if (!confirmation) return;
      event.preventDefault();
      const title = confirmation.querySelector('#admin-confirm-title');
      const message = confirmation.querySelector('#admin-confirm-message');
      const accept = confirmation.querySelector('[data-admin-confirm-accept]');
      const prompt = control.getAttribute('data-confirm') || '¿Confirmas esta acción?';
      const actionLabel = control.textContent.trim().replace(/^sí,\s*/i, '');
      if (title) title.textContent = control.getAttribute('data-confirm-title') || actionLabel || 'Confirmar acción';
      if (message) message.textContent = prompt;
      if (accept) {
        const destructive = control.classList.contains('admin-danger-button') || /eliminar|desactivar|revocar|cancelar/i.test(`${prompt} ${control.textContent}`);
        accept.classList.toggle('admin-danger-button', destructive);
        accept.classList.toggle('admin-primary-button', !destructive);
        accept.textContent = control.getAttribute('data-confirm-label') || 'Sí, confirmar';
      }
      confirmation.pendingControl = control;
      confirmation.showModal();
    });
  }
  const confirmation = document.querySelector('[data-admin-confirm-modal]');
  const cancelConfirmation = () => confirmation?.close('cancel');
  confirmation?.querySelector('[data-admin-confirm-cancel]')?.addEventListener('click', cancelConfirmation);
  confirmation?.addEventListener('click', event => {
    if (event.target === confirmation) cancelConfirmation();
  });
  confirmation?.querySelector('[data-admin-confirm-accept]')?.addEventListener('click', () => {
    const control = confirmation.pendingControl;
    confirmation.close('confirm');
    confirmation.pendingControl = null;
    if (!control) return;
    if (control instanceof HTMLButtonElement && control.form) control.form.requestSubmit(control);
    else if (control instanceof HTMLInputElement && control.form) control.form.requestSubmit(control);
    else if (control instanceof HTMLAnchorElement) window.location.assign(control.href);
  });
  confirmation?.addEventListener('close', () => { confirmation.pendingControl = null; });
  document.querySelectorAll('[data-confirm]').forEach(control => {
    bindConfirmation(control);
  });
}
