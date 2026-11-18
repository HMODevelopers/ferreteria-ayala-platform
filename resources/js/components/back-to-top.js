export function backToTop() {
  const link = document.querySelector('[data-back-to-top]');
  if (!link) return;
  const update = () => { link.hidden = window.scrollY < 400 && document.activeElement !== link; };
  window.addEventListener('scroll', update, { passive: true });
  link.addEventListener('blur', update);
  update();
}
