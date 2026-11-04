export function initDeliveries(root = document) {
  for (const form of root.querySelectorAll('form[data-delivery-event], form[data-delivery-create]')) {
    form.addEventListener('submit', event => {
      if (form.dataset.submitting) { event.preventDefault(); return; }
      form.dataset.submitting = '1';
      form.setAttribute('aria-busy', 'true');
      for (const button of form.querySelectorAll('button')) button.disabled = true;
    });
  }
  window.addEventListener('pageshow', () => {
    for (const form of root.querySelectorAll('[data-delivery-event], [data-delivery-create]')) {
      delete form.dataset.submitting;
      form.removeAttribute('aria-busy');
      for (const button of form.querySelectorAll('button')) button.disabled = false;
    }
  });
}
