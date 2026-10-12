import { navigation } from './components/navigation.js';
import { interactions } from './components/interactions.js';
navigation();
interactions();
if (document.querySelector('.hero-swiper')) import('./components/sliders.js').then(({ sliders }) => sliders()).catch(() => {
  document.querySelector('.hero-controls').hidden = true;
});
