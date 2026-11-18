<?php $publishedCount=count(array_filter($rows,static fn(array $row):bool=>(bool)$row['is_published'])); ?>
<div class="website-brands">
  <section class="admin-page-head website-brands-page-head">
    <div><p class="admin-kicker">SITIO WEB / INICIO</p><h1>Carrusel de marcas</h1><p>Administra los logotipos que tus visitantes verán en la página de inicio.</p></div>
    <a class="admin-secondary-button" href="/#inicio" target="_blank" rel="noopener">Ver sitio público <span aria-hidden="true">↗</span></a>
  </section>
  <?php if(!$installed):?><div class="admin-alert">El carrusel requiere aplicar la migración pendiente antes de agregar marcas.</div><?php else:?>
  <?php if($error):?><p class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></p><?php endif?>
  <section class="website-brands-intro" aria-label="Cómo administrar el carrusel">
    <div class="website-brands-intro-copy"><span class="website-brands-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15l3-3 3 3 2-2 3 3M8 9h.01"/></svg></span><div><strong>Una vitrina para tus marcas</strong><p>Agrega cada logotipo y decide cuándo mostrarlo.</p></div></div>
    <ol><li><span>01</span>Sube el logotipo</li><li><span>02</span>Revisa el borrador</li><li><span>03</span>Publícalo</li></ol>
  </section>
  <section class="admin-panel website-brands-upload" aria-labelledby="brand-upload-heading">
    <header class="website-brands-section-head"><div><p class="admin-kicker">NUEVA MARCA</p><h2 id="brand-upload-heading">Agregar un logotipo</h2><p>Completa los datos y selecciona una imagen autorizada. La marca se guardará como borrador.</p></div><span class="website-brands-draft-badge">Se guarda como borrador</span></header>
    <form class="website-brands-form" method="post" action="/admin/cms/brands/create" enctype="multipart/form-data" data-media-upload-form data-media-min-width="<?= e(\App\Application\Services\WebsiteBrandCarouselService::MIN_WIDTH) ?>" data-media-min-height="<?= e(\App\Application\Services\WebsiteBrandCarouselService::MIN_HEIGHT) ?>" data-media-feedback-context="Revisa la vista previa antes de guardar.">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="operation_key" value="<?= e($values['operation_key']??bin2hex(random_bytes(24))) ?>">
      <div class="website-brands-form-layout">
        <div class="website-brands-image-column">
          <span class="website-brands-field-title">Logotipo <span aria-hidden="true">*</span></span>
          <div class="website-brands-drop-zone" data-media-drop-zone>
            <span class="website-brands-upload-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 16V4m-4 4 4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg></span>
            <strong>Selecciona o arrastra tu logotipo</strong><p>PNG, JPG o WebP · hasta 10 MiB</p>
            <label class="website-brands-file-label">Seleccionar imagen<input name="image" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="brand-image-help brand-image-feedback"></label>
          </div>
          <p class="website-brands-feedback" id="brand-image-feedback" data-media-upload-feedback role="status" aria-live="polite">Aún no has seleccionado una imagen.</p>
          <figure class="website-brands-preview" data-media-preview hidden><div><img data-media-preview-image alt="Vista previa del logotipo seleccionado"></div><figcaption data-media-preview-caption></figcaption></figure>
        </div>
        <div class="website-brands-details">
          <div class="website-brands-fields-heading"><span>01</span><div><h3>Datos de la marca</h3><p>Asigna un nombre para identificarla y define su posición.</p></div></div>
          <div class="admin-form-grid website-brands-fields"><label class="admin-field"><span>Nombre de la marca *</span><input name="name" maxlength="150" value="<?= e($values['name']??'') ?>" placeholder="Ej. Nombre de la marca" required></label><label class="admin-field"><span>Orden de aparición</span><input name="display_order" type="number" min="0" max="65535" value="<?= e($values['display_order']??'0') ?>" required><small>Los números menores aparecen primero.</small></label></div>
          <div class="website-brands-requirements" id="brand-image-help"><span class="website-brands-requirements-icon" aria-hidden="true">i</span><div><strong>Para que el logotipo se vea bien</strong><p>Usa una imagen nítida de al menos <b>64 × 64 px</b>. Se admiten logotipos horizontales y cuadrados; recomendamos PNG con fondo transparente.</p></div></div>
        </div>
      </div>
      <footer class="website-brands-form-footer"><p><span aria-hidden="true">●</span> Se guardará como borrador hasta que lo publiques.</p><button class="admin-primary-button" type="submit">Guardar marca <span aria-hidden="true">→</span></button></footer>
    </form>
  </section>
  <section class="admin-panel website-brands-list" aria-labelledby="brands-list-heading">
    <header class="website-brands-list-head"><div><p class="admin-kicker">CONTENIDO DEL CARRUSEL</p><h2 id="brands-list-heading">Marcas registradas</h2><p>Revisa los logotipos, organiza su orden y controla cuáles se muestran.</p></div><div class="website-brands-counts"><span><strong><?= e(count($rows)) ?></strong> registradas</span><span><strong><?= e($publishedCount) ?></strong> publicadas</span></div></header>
    <?php if(!$rows):?><div class="website-brands-empty"><span class="website-brands-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="1"/><path d="m4 17 5-5 4 4 2-2 5 4"/></svg></span><h3>Aún no hay marcas registradas</h3><p>Cuando agregues un logotipo, aparecerá aquí para que puedas revisarlo y publicarlo.</p><a href="#brand-upload-heading">Agregar la primera marca ↑</a></div>
    <?php else:?><div class="website-brands-cards"><?php foreach($rows as $row):?><article class="website-brand-card"><div class="website-brand-image"><img src="/admin/media/file?id=<?= e($row['media_id']) ?>" alt="<?= e($row['alt_text']) ?>" loading="lazy"><span class="admin-status <?= (int)$row['is_published']?'is-active':'' ?>"><?= (int)$row['is_published']?'Publicada':'Borrador' ?></span></div><div class="website-brand-card-content"><h3><?= e($row['name']) ?></h3><p><?= e($row['width']) ?> × <?= e($row['height']) ?> px</p><form method="post" action="/admin/cms/brands/order" class="website-brands-order"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><label class="admin-field"><span>Orden</span><input type="number" name="display_order" min="0" max="65535" value="<?= e($row['display_order']) ?>" aria-label="Orden de <?= e($row['name']) ?>" required></label><button class="admin-secondary-button">Guardar orden</button></form><?php if($canPublish):?><form method="post" action="/admin/cms/brands/visibility" class="website-brand-visibility"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="published" value="<?= (int)$row['is_published']?'0':'1' ?>"><button class="<?= (int)$row['is_published']?'admin-secondary-button':'admin-primary-button' ?>"><?= (int)$row['is_published']?'Dejar de mostrar':'Publicar marca' ?></button></form><?php else:?><p class="website-brand-permission">Requiere permiso de publicación.</p><?php endif?></div></article><?php endforeach?></div><?php endif?>
  </section>
  <?php endif?>
</div>
