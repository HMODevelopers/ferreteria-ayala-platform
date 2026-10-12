import Swiper from 'swiper';
import { Navigation, Pagination, A11y } from 'swiper/modules';
export function sliders() {
  const root = document.querySelector('.hero-swiper');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const sync = swiper => {
    document.querySelector('#slide-number').textContent = `${String(swiper.activeIndex + 1).padStart(2, '0')} / 03`;
    root.querySelectorAll('.hero-slide').forEach((slide, index) => {
      slide.inert = index !== swiper.activeIndex;
      slide.setAttribute('aria-hidden', String(index !== swiper.activeIndex));
    });
    document.querySelectorAll('.hero-pagination button').forEach((button, index) => {
      button.setAttribute('aria-label', `Ir a campaña ${index + 1}`);
      button.setAttribute('aria-current', index === swiper.activeIndex ? 'true' : 'false');
    });
  };
  const swiper = new Swiper(root, {
    modules: [Navigation, Pagination, A11y],
    speed: reduced.matches ? 0 : 500, rewind: true, grabCursor: true,
    navigation: { nextEl: '#hero-next', prevEl: '#hero-prev' },
    pagination: { el: '.hero-pagination', clickable: true, renderBullet: (index, className) => `<button type="button" class="${className}" aria-label="Ir a campaña ${index + 1}"></button>` },
    a11y: { enabled: true, prevSlideMessage: 'Campaña anterior', nextSlideMessage: 'Campaña siguiente', slideLabelMessage: 'Campaña {{index}} de {{slidesLength}}', paginationBulletMessage: 'Ir a campaña {{index}}' },
    on: { init: sync, slideChange: sync },
  });
  reduced.addEventListener('change', event => { swiper.params.speed = event.matches ? 0 : 500; });
  // Manual navigation: no automatic motion to pause on hover, focus or hidden tabs.
}
