export function admin() {
  const menu = document.querySelector('[data-admin-menu]');
  const sidebar = document.querySelector('[data-admin-sidebar]');
  if (menu && sidebar) {
    menu.addEventListener('click', () => {
      const open = sidebar.classList.toggle('is-open');
      menu.setAttribute('aria-expanded', String(open));
    });
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
      sidebar.classList.remove('is-open');
      menu.setAttribute('aria-expanded', 'false');
    }));
  }
  document.querySelectorAll('[data-confirm]').forEach(control => {
    control.addEventListener('click', event => {
      if (!window.confirm(control.getAttribute('data-confirm') || '¿Confirmar esta acción?')) event.preventDefault();
    });
  });
}
