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
