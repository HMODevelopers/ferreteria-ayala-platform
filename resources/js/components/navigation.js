export function navigation() {
  const menu = document.querySelector('#mobile-menu');
  const trigger = document.querySelector('#menu-open');
  if (!menu || !trigger) return;
  trigger.addEventListener('click', () => { menu.showModal(); trigger.setAttribute('aria-expanded', 'true'); });
  menu.addEventListener('close', () => { trigger.setAttribute('aria-expanded', 'false'); trigger.focus(); });
  menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => menu.close()));
  document.querySelectorAll('dialog').forEach(dialog => {
    dialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const focusable = [...dialog.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled)')].filter(node => node.getClientRects().length);
      const first = focusable[0];
      const last = focusable.at(-1);
      if (!first) { event.preventDefault(); return; }
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    dialog.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => {
      const bounds = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
  });
}
