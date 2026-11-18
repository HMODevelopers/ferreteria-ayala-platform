export function brandCarousels() {
  document.querySelectorAll('[data-brand-carousel]').forEach(root => {
    const button = root.querySelector('[data-brand-motion]');
    if (!button) return;
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let paused = false;
    const update = () => {
      const animate = !reducedMotion.matches;
      root.classList.toggle('is-animated', animate);
      root.classList.toggle('is-paused', paused);
      button.hidden = !animate;
      button.setAttribute('aria-pressed', String(paused));
      const label = paused ? 'Reanudar movimiento' : 'Pausar movimiento';
      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
    };
    button.addEventListener('click', () => { paused = !paused; update(); });
    reducedMotion.addEventListener('change', update);
    update();
  });
}
