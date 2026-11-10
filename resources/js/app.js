import { navigation } from './components/navigation.js';
import { interactions } from './components/interactions.js';
import { admin } from './components/admin.js';
navigation();
interactions();
admin();
if (document.querySelector('.hero-swiper')) import('./components/sliders.js').then(({ sliders }) => sliders()).catch(() => {
  document.querySelector('.hero-controls').hidden = true;
});

import { initAdminQuotes } from './modules/admin-quotes.js';
initAdminQuotes();

import { initTransfers } from './modules/transfers.js';
initTransfers();

import { initDeliveries } from './modules/deliveries.js';
initDeliveries();

import { initCmsTerms } from './modules/cms.js';
initCmsTerms();

const notificationCenter = document.querySelector('[data-notification-center]');
if (notificationCenter) {
  const badge = notificationCenter.querySelector('[data-notification-count]');
  let polling = false;
  const refreshNotifications = async () => {
    if (polling || document.visibilityState !== 'visible') return;
    polling = true;
    try {
      const response = await fetch(notificationCenter.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok) return;
      const data = await response.json();
      const count = Number.isInteger(data.unread) && data.unread >= 0 ? data.unread : 0;
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.hidden = count === 0;
    } catch (_) {
      // El centro conserva el último conteo confirmado si la red falla.
    } finally { polling = false; }
  };
  document.addEventListener('visibilitychange', refreshNotifications);
  window.setInterval(refreshNotifications, 60000);
}
