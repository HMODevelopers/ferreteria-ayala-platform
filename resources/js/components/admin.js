import { initAdminQuotes } from '../modules/admin-quotes.js';
import { initQuoteWorkflow } from './quote-workflow.js';
import { initMediaUpload } from './admin-media-upload.js';
import { initAdminNavigation } from './admin-navigation.js';

export function admin() {
  initAdminNavigation();
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
  const closeModal = () => modal?.close();
  modal?.addEventListener('close', () => { activeModalRequest++; cleanupModalMedia(); });
  modal?.querySelector('[data-admin-modal-close]')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', event => {
    if (event.target === modal) closeModal();
  });

  const isFormLink = link => {
    const url = new URL(link.href, window.location.href);
    return url.origin === window.location.origin && ((link.hasAttribute('data-admin-form-link') && ['/admin/cms','/admin/cms/terms','/admin/media','/admin/customers/create','/admin/customers/edit','/admin/customers/detail','/admin/customers/status','/admin/quotes/intake','/admin/quotes/workflow','/admin/quotes/workflow/action','/admin/quotes/workflow/prepare','/admin/quotes/create','/admin/quotes/edit','/admin/quotes/detail','/admin/quotes/action'].includes(url.pathname)) || /\/admin\/(?:users|branches|categories|brands|units|products|prices|roles)\/(?:create|edit)$/.test(url.pathname));
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
    const cacheable = !['/admin/cms','/admin/cms/terms','/admin/media'].includes(new URL(url).pathname) && !new URL(url).pathname.startsWith('/admin/customers/') && !new URL(url).pathname.startsWith('/admin/quotes/');
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
  };

  document.querySelectorAll('a[href]').forEach(link => {
    if (!modal || !isFormLink(link)) return;
    const prefetch = () => { loadForm(link.href).catch(() => {}); };
    link.addEventListener('pointerenter', prefetch, {once: true});
    link.addEventListener('focus', prefetch, {once: true});
  });
  document.addEventListener('click', async event => {
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
    if (!form.matches('[data-cms-editor-form],[data-media-upload-form],[data-customer-form],[data-quote-workflow-form]')) return;
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
        if(response.ok&&destination.origin===window.location.origin&&['/admin/quotes/workflow','/admin/quotes/edit','/admin/quotes/detail'].includes(destination.pathname)){
          const html=await response.text();if(requestId!==activeModalRequest||!modal.open)return;renderModalContent(html);
          if(document.querySelector('.quote-inbox')){try{const listResponse=await fetch(window.location.href,{headers:{Accept:'text/html'}});if(!listResponse.ok||listResponse.redirected)return;const doc=new DOMParser().parseFromString(await listResponse.text(),'text/html');const content=doc.querySelector('#admin-content');if(content&&document.querySelector('.quote-inbox'))document.querySelector('#admin-content').replaceChildren(...[...content.childNodes].map(node=>node.cloneNode(true)));}catch{/* The modal retains the saved result; refreshing the list remains available. */}}
          return;
        }
        if (response.ok && destination.origin === window.location.origin && ['/admin/cms','/admin/cms/terms','/admin/media','/admin/customers'].includes(destination.pathname)) {
          // The redirected GET already consumed the flash; reuse its rendered list.
          const html = await response.text();
          if (requestId !== activeModalRequest || !modal.open) return;
          const page = new DOMParser().parseFromString(html, 'text/html');
          const content = page.querySelector('#admin-content');
          const current = document.querySelector('#admin-content');
          if (!content || !current) throw new Error('El contenido se guardó, pero no fue posible actualizar la lista. Recarga la página.');
          current.replaceChildren(...[...content.childNodes].map(node => node.cloneNode(true)));
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
    control.addEventListener('click', event => {
      if (!window.confirm(control.getAttribute('data-confirm') || '¿Confirmar esta acción?')) event.preventDefault();
    });
  }
  document.querySelectorAll('[data-confirm]').forEach(control => {
    bindConfirmation(control);
  });
}
