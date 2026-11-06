let restoreBound = false;
export function initDeliveries(root = document) {
  const creation = root.querySelector('[data-delivery-create]');
  if (creation && !creation.dataset.modeInitialized) {
    creation.dataset.modeInitialized = '1';
    const mode = creation.elements.mode;
    const address = creation.elements.address;
    const syncAddress = () => { address.required = mode.value === 'home'; };
    mode.addEventListener('change', syncAddress);
    syncAddress();
  }
  const route = root.querySelector('[data-delivery-resource]:has([data-delivery-resource-select])');
  if (route && !route.dataset.resourcesInitialized) {
    route.dataset.resourcesInitialized = '1';
    const branch = route.elements.branch_id;
    const status = route.querySelector('[data-delivery-resource-status]');
    const syncResources = () => {
      const missing = [];
      for (const select of route.querySelectorAll('[data-delivery-resource-select]')) {
        let available = 0;
        for (const option of select.querySelectorAll('option[data-branch-id]')) {
          const matches = option.dataset.branchId === branch.value;
          option.hidden = !matches;
          option.disabled = !matches;
          if (matches) available++;
          else if (option.selected) select.value = '';
        }
        const vehicle = select.name === 'vehicle_id';
        select.options[0].textContent = available ? (vehicle ? 'Selecciona un vehículo' : 'Selecciona un repartidor') : (vehicle ? 'No hay vehículos disponibles' : 'No hay repartidores disponibles');
        if (!available) missing.push(vehicle ? 'vehículos' : 'repartidores');
      }
      if (status) status.textContent = missing.length ? 'No hay ' + missing.join(' ni ') + ' activos y disponibles en esta sucursal. Revisa los catálogos antes de registrar la ruta.' : 'Selecciona los recursos disponibles de esta sucursal. Al cambiar de sucursal se limpian las selecciones que no correspondan.';
    };
    branch.addEventListener('change', syncResources);
    syncResources();
  }
  for (const form of root.querySelectorAll('[data-delivery-event], [data-delivery-create], [data-delivery-resource]')) {
    if (form.closest('[data-admin-modal]') || form.dataset.submitInitialized) continue;
    form.dataset.submitInitialized = '1';
    form.addEventListener('submit', event => {
      if (form.dataset.submitting) { event.preventDefault(); return; }
      form.dataset.submitting = '1';
      form.setAttribute('aria-busy', 'true');
      for (const button of form.querySelectorAll('button')) button.disabled = true;
    });
  }
  if (!restoreBound) {
    restoreBound = true;
    window.addEventListener('pageshow', () => {
      for (const form of document.querySelectorAll('[data-delivery-event], [data-delivery-create], [data-delivery-resource]')) {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        for (const button of form.querySelectorAll('button')) button.disabled = false;
      }
    });
  }
}
