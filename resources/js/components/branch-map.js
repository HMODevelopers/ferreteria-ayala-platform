const searchCache = new Map();
let nextSearchAt = 0;
let autocompleteId = 0;
const instances = new WeakMap();
export function initBranchMaps(root = document) {
  const cleanups = [];
  for (const region of root.querySelectorAll('[data-branch-map]')) {
    if (instances.has(region)) continue;
    const form = region.closest('form');
    const readOnly = region.hasAttribute('data-read-only');
    const latitude = region.querySelector('[name="latitude"]');
    const longitude = region.querySelector('[name="longitude"]');
    const status = region.querySelector('[data-map-status]');
    const canvas = region.querySelector('[data-map-canvas]');
    const apply = region.querySelector('[data-map-apply]');
    const query = region.querySelector('[data-map-query]');
    const find = region.querySelector('[data-map-search]');
    const results = region.querySelector('[data-map-results]');
    const manual = region.querySelector('.branch-map-coordinates');
    let map, marker, leaflet, opening, observer, draft, controller, searchTimer, dismissSuggestions, requestId = 0, disposed = false;
    const current = () => {
      const lat = latitude ? latitude.value.trim() : region.dataset.latitude;
      const lon = longitude ? longitude.value.trim() : region.dataset.longitude;
      if (!lat || !lon || !/^-?\d{1,3}(?:\.\d{1,6})?$/.test(lat) || !/^-?\d{1,3}(?:\.\d{1,6})?$/.test(lon)) return null;
      const point = [Number(lat), Number(lon)];
      return point.every(Number.isFinite) && Math.abs(point[0]) <= 90 && Math.abs(point[1]) <= 180 ? point : null;
    };
    const showMarker = point => {
      if (!map) return;
      if (!marker) {
        marker = leaflet.marker(point, {draggable: !readOnly, keyboard: true, title: readOnly ? 'Ubicación registrada' : 'Punto de la sucursal', icon: leaflet.divIcon({className: 'branch-map-pin', html: '<span aria-hidden="true"></span>', iconSize: [28, 36], iconAnchor: [14, 36]})}).addTo(map);
        if (!readOnly) marker.on('dragend', () => pick(marker.getLatLng()));
      } else marker.setLatLng(point);
    };
    const pick = point => {
      const lon = ((point.lng + 180) % 360 + 360) % 360 - 180;
      draft = [Math.max(-90, Math.min(90, point.lat)), lon];
      showMarker(draft);
      apply.disabled = false;
      latitude.setCustomValidity('Pulsa «Usar esta ubicación» para aplicar el punto seleccionado.');
      status.textContent = 'Punto seleccionado: ' + draft.map(value => value.toFixed(6)).join(', ') + '. Ajusta el marcador y pulsa «Usar esta ubicación».';
    };
    const open = async () => {
      if (map) { map.invalidateSize(); return map; }
      if (opening) return opening;
      status.textContent = 'Cargando mapa…';
      opening = (async () => {
        const module = await import('/assets/js/leaflet.js');
        if (disposed) return null;
        leaflet = module.default || module;
        canvas.hidden = false;
        region.querySelector('[data-map-actions]')?.removeAttribute('hidden');
        const point = current();
        map = leaflet.map(canvas, {scrollWheelZoom: false, attributionControl: true, maxBounds: [[-85, -180], [85, 180]], maxBoundsViscosity: 1});
        map.attributionControl.setPrefix(false);
        leaflet.tileLayer(region.dataset.tileUrl, {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors'}).on('tileerror', () => { if (!disposed) status.textContent = 'No se pudo cargar parte de la cartografía. Puedes reintentar o capturar las coordenadas manualmente.'; }).addTo(map);
        map.setView(point || [23.6, -102.5], point ? 16 : 5, {animate: false});
        if (point) showMarker(point);
        if (!readOnly) map.on('click', event => pick(event.latlng));
        observer = new ResizeObserver(() => { if (!disposed && map) map.invalidateSize({pan: false}); });
        observer.observe(canvas);
        status.textContent = point ? 'Comprueba el punto registrado sobre el mapa.' : 'Busca una dirección o selecciona el punto de la sucursal en el mapa.';
        region.querySelector('[data-map-open]').textContent = 'Mapa abierto';
        return map;
      })().catch(error => { opening = null; throw error; });
      return opening;
    };
    region.querySelector('[data-map-open]').addEventListener('click', () => open().catch(() => { status.textContent = 'No se pudo abrir el mapa. La captura manual de coordenadas sigue disponible.'; }));
    if (!readOnly) {
      const autocomplete = region.querySelector('.branch-map-autocomplete');
      results.id = 'branch-map-suggestions-' + (++autocompleteId);
      query.setAttribute('aria-controls', results.id);
      let activeOption = -1;
      const options = () => [...results.querySelectorAll('[role="option"]')];
      const hideSuggestions = () => {
        results.hidden = true;
        query.setAttribute('aria-expanded', 'false');
        query.removeAttribute('aria-activedescendant');
        activeOption = -1;
        options().forEach(option => option.setAttribute('aria-selected', 'false'));
      };
      const showSuggestions = () => {
        if (!results.children.length) return;
        results.hidden = false;
        query.setAttribute('aria-expanded', 'true');
      };
      const activateOption = index => {
        const items = options();
        if (!items.length) return;
        showSuggestions();
        activeOption = (index + items.length) % items.length;
        items.forEach((option, position) => option.setAttribute('aria-selected', String(position === activeOption)));
        query.setAttribute('aria-activedescendant', items[activeOption].id);
        items[activeOption].scrollIntoView({block: 'nearest'});
      };
      const choose = async (result, lat, lon) => {
        clearTimeout(searchTimer); controller?.abort(); requestId++;
        find.disabled = false; query.removeAttribute('aria-busy');
        query.value = result.label;
        hideSuggestions();
        try {
          await open(); if (disposed) return;
          map.setView([lat, lon], 17, {animate: false}); pick({lat, lng: lon});
          canvas.focus({preventScroll: true});
        } catch { status.textContent = 'No se pudo abrir el mapa. Reintenta o captura las coordenadas manualmente.'; }
      };
      const search = async ({automatic = false} = {}) => {
        clearTimeout(searchTimer);
        if (disposed) return;
        const term = (query.value.trim() || (!automatic && form?.querySelector('[name="address"]')?.value.trim()) || '').trim();
        if (term.length < 3 || term.length > 240) {
          hideSuggestions();
          if (!automatic) { status.textContent = 'Escribe entre 3 y 240 caracteres para buscar la dirección o lugar.'; query.focus(); }
          return;
        }
        query.value = term;
        const endpoint = new URL(region.dataset.geocoderUrl);
        endpoint.search = new URLSearchParams({q: term, limit: '5'}).toString();
        const cacheKey = endpoint.href;
        if (!searchCache.has(cacheKey) && Date.now() < nextSearchAt) {
          searchTimer = setTimeout(() => search({automatic}), nextSearchAt - Date.now() + 10);
          return;
        }
        controller?.abort(); controller = new AbortController();
        const active = ++requestId;
        find.disabled = true; results.replaceChildren(); hideSuggestions();
        query.setAttribute('aria-busy', 'true');
        if (!automatic) query.focus({preventScroll: true});
        status.textContent = 'Buscando ubicaciones…';
        try {
          let features = searchCache.get(cacheKey);
          if (!features) {
            nextSearchAt = Date.now() + 1100;
            const response = await fetch(endpoint, {signal: controller.signal, credentials: 'omit', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error(response.status === 429 ? 'El buscador está ocupado. Espera unos segundos y vuelve a intentar.' : 'No se pudo buscar la ubicación. Puedes seleccionar el punto en el mapa.');
            const data = await response.json();
            if (!Array.isArray(data.features)) throw new Error('No se pudo interpretar la búsqueda.');
            features = data.features.slice(0, 5);
            if (searchCache.size >= 50) searchCache.delete(searchCache.keys().next().value);
            searchCache.set(cacheKey, features);
          }
          if (active !== requestId || disposed) return;
          for (const feature of features) {
            if (feature.geometry?.type !== 'Point' || !Array.isArray(feature.geometry.coordinates)) continue;
            const [lon, lat] = feature.geometry.coordinates.map(Number);
            if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) continue;
            const properties = feature.properties || {};
            const label = [...new Set(['name','street','housenumber','district','city','state','country'].map(key => properties[key]).filter(value => typeof value === 'string' && value.trim()))].join(', ');
            if (!label) continue;
            const option = document.createElement('button');
            option.type = 'button'; option.className = 'branch-map-result'; option.textContent = label;
            option.id = results.id + '-' + results.children.length;
            option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false'); option.tabIndex = -1;
            option.addEventListener('mousedown', event => event.preventDefault());
            option.addEventListener('click', () => choose({label}, lat, lon));
            results.append(option);
          }
          if (!results.children.length) { status.textContent = 'Sin coincidencias. Añade la ciudad o selecciona el punto directamente en el mapa.'; return; }
          if (document.activeElement === query) showSuggestions();
          status.textContent = results.children.length + ' sugerencias. Usa las flechas y Enter, o selecciona una ubicación.';
        } catch (error) {
          if (error.name !== 'AbortError' && active === requestId && !disposed) status.textContent = error.message || 'No se pudo buscar la dirección.';
        } finally {
          if (active === requestId && !disposed) { find.disabled = false; query.removeAttribute('aria-busy'); }
        }
      };
      find.addEventListener('click', () => search());
      query.addEventListener('input', () => {
        clearTimeout(searchTimer); controller?.abort(); requestId++;
        find.disabled = false; query.removeAttribute('aria-busy');
        results.replaceChildren(); hideSuggestions();
        if (query.value.trim().length >= 3) searchTimer = setTimeout(() => search({automatic: true}), Math.max(650, nextSearchAt - Date.now()));
      });
      query.addEventListener('focus', showSuggestions);
      query.addEventListener('keydown', event => {
        if (event.isComposing) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          if (options().length) activateOption(activeOption < 0 ? (event.key === 'ArrowDown' ? 0 : options().length - 1) : activeOption + (event.key === 'ArrowDown' ? 1 : -1));
          else search();
        } else if (event.key === 'Enter') {
          event.preventDefault();
          if (!results.hidden && activeOption >= 0) options()[activeOption]?.click();
          else search();
        } else if (event.key === 'Escape' && !results.hidden) {
          event.preventDefault(); event.stopPropagation(); hideSuggestions();
        } else if (event.key === 'Tab') hideSuggestions();
      });
      autocomplete.addEventListener('focusout', event => { if (!autocomplete.contains(event.relatedTarget)) hideSuggestions(); });
      dismissSuggestions = event => {
        if (!autocomplete.contains(event.target) && event.target !== find) {
          clearTimeout(searchTimer); controller?.abort(); requestId++;
          find.disabled = false; query.removeAttribute('aria-busy'); hideSuggestions();
        }
      };
      document.addEventListener('pointerdown', dismissSuggestions);
      region.querySelector('[data-map-center]').addEventListener('click', () => { if (map) pick(map.getCenter()); });
      apply.addEventListener('click', () => {
        if (!draft) return;
        latitude.value = draft[0].toFixed(6); longitude.value = draft[1].toFixed(6);
        latitude.setCustomValidity(''); longitude.setCustomValidity(''); draft = null; apply.disabled = true;
        status.textContent = 'Ubicación aplicada: ' + latitude.value + ', ' + longitude.value + '. Se guardará junto con los datos de la sucursal.';
      });
      region.querySelector('[data-map-clear]').addEventListener('click', () => {
        latitude.value = ''; longitude.value = ''; latitude.setCustomValidity(''); longitude.setCustomValidity(''); draft = null; apply.disabled = true;
        if (marker) { marker.remove(); marker = null; }
        status.textContent = 'Ubicación eliminada del formulario. El cambio se aplica al guardar.';
      });
      const syncManual = () => {
        draft = null; apply.disabled = true; latitude.setCustomValidity(''); longitude.setCustomValidity('');
        const point = current();
        if (!latitude.value.trim() && !longitude.value.trim()) {
          if (marker) { marker.remove(); marker = null; }
          status.textContent = 'Sin ubicación seleccionada.'; return;
        }
        if (!point) { latitude.setCustomValidity('Captura latitud y longitud válidas: −90 a 90 y −180 a 180.'); status.textContent = 'Revisa ambas coordenadas antes de guardar.'; return; }
        showMarker(point); if (map) map.setView(point, 16, {animate: false});
        status.textContent = 'Coordenadas capturadas. Puedes verificarlas en el mapa antes de guardar.';
      };
      for (const input of [latitude, longitude]) {
        input.addEventListener('change', syncManual);
        input.addEventListener('invalid', () => { manual.open = true; });
      }
    }
    const cleanup = () => { if (disposed) return; disposed = true; clearTimeout(searchTimer); if (dismissSuggestions) document.removeEventListener('pointerdown', dismissSuggestions); requestId++; controller?.abort(); observer?.disconnect(); map?.remove(); instances.delete(region); };
    instances.set(region, cleanup); cleanups.push(cleanup);
  }
  return () => cleanups.forEach(cleanup => cleanup());
}
