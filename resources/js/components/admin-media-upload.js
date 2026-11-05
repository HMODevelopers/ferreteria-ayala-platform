export function initMediaUpload(form) {
  if (!form) return () => {};
  const input = form.querySelector('[name="image"]');
  const zone = form.querySelector('[data-media-drop-zone]');
  const feedback = form.querySelector('[data-media-upload-feedback]');
  const preview = form.querySelector('[data-media-preview]');
  const image = form.querySelector('[data-media-preview-image]');
  const caption = form.querySelector('[data-media-preview-caption]');
  let reader;
  let active = true;
  const select = () => {
    reader?.abort();
    preview.hidden = true;
    image.removeAttribute('src');
    input.setCustomValidity('');
    const file = input.files?.[0];
    if (!file) { feedback.textContent = 'Aún no has seleccionado una imagen.'; return; }
    if (file.size > 10485760 || (file.type && !['image/jpeg','image/png','image/webp'].includes(file.type))) {
      const message = file.size > 10485760 ? 'La imagen supera 10 MiB. Reduce su tamaño antes de subirla.' : 'Selecciona una imagen JPG, PNG o WebP.';
      input.setCustomValidity(message);
      feedback.textContent = message;
      return;
    }
    feedback.textContent = file.name + ' · ' + (file.size / 1048576).toFixed(2) + ' MiB. Revisa la vista previa y completa la descripción.';
    image.onload = () => {
      if (!active) return;
      preview.hidden = false;
      caption.textContent = file.name + ' · ' + image.naturalWidth + ' × ' + image.naturalHeight + ' px';
    };
    image.onerror = () => { if (active) feedback.textContent = 'No se pudo mostrar la vista previa. Al subir, el sistema comprobará si el archivo es una imagen válida.'; };
    reader = new FileReader();
    reader.onload = () => { if (active) image.src = reader.result; };
    reader.onerror = () => { if (active) feedback.textContent = 'No pudimos leer el archivo. Selecciónalo de nuevo.'; };
    reader.readAsDataURL(file);
  };
  input.addEventListener('change', select);
  for (const event of ['dragenter','dragover']) zone.addEventListener(event, e => { e.preventDefault(); zone.classList.add('is-dragging'); });
  for (const event of ['dragleave','drop']) zone.addEventListener(event, e => { e.preventDefault(); zone.classList.remove('is-dragging'); });
  zone.addEventListener('drop', event => {
    if (event.dataTransfer?.files.length !== 1) { feedback.textContent = 'Selecciona una sola imagen a la vez.'; return; }
    input.files = event.dataTransfer.files;
    select();
  });
  return () => { active = false; reader?.abort(); image.onload = null; image.onerror = null; };
}
