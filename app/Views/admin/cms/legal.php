<?php
$documentLabels=['privacy'=>'Aviso de privacidad','quote_conditions'=>'Condiciones de cotización'];
$selectedCode=(string)($values['code']??'privacy');
?>
<div class="legal-workspace">
<section class="admin-page-head legal-page-head"><div><p class="admin-kicker">Sitio web</p><h1>Documentos legales</h1><p>Conserva textos aprobados, identifica cada versión y decide cuándo hacerla visible para tus visitantes.</p></div><a class="admin-secondary-button" href="/admin/cms">Ir a contenido del sitio</a></section>

<section class="legal-guide" aria-labelledby="legal-guide-title">
    <div class="legal-guide-intro"><span class="legal-document-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H5V3h2Zm7 0v5h4M8 12h7M8 16h7"/></svg></span><div><h2 id="legal-guide-title">Una versión nueva en cada guardado</h2><p>Los documentos anteriores no se modifican. Así puedes consultar exactamente qué texto estuvo vigente.</p></div></div>
    <ol><li><span>1</span><div><strong>Selecciona el documento</strong><p>Captura solamente el texto aprobado por el negocio.</p></div></li><li><span>2</span><div><strong>Guarda la versión</strong><p>Quedará registrada como un borrador inmutable.</p></div></li><li><span>3</span><div><strong>Publica cuando corresponda</strong><p>La versión marcada sustituirá a la visible actualmente.</p></div></li></ol>
</section>

<?php if($error):?><p class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></p><?php endif?>
<div class="legal-layout">
<form class="admin-panel admin-form legal-editor" method="post" action="/admin/cms/legal">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="operation_key" value="<?= e(($values['operation_key']??'')?:bin2hex(random_bytes(24))) ?>">
    <header><span class="legal-section-number">01</span><div><h2>Crear una versión</h2><p>El título y el texto se mostrarán tal como los registres. No se acepta HTML.</p></div></header>
    <div class="admin-form-grid"><label class="admin-field"><span>Tipo de documento *</span><select name="code"><option value="privacy" <?= $selectedCode==='privacy'?'selected':'' ?>>Aviso de privacidad</option><option value="quote_conditions" <?= $selectedCode==='quote_conditions'?'selected':'' ?>>Condiciones de cotización</option></select><small>El tipo permite mantener historiales independientes.</small></label><label class="admin-field"><span>Título público *</span><input name="title" maxlength="180" value="<?= e($values['title']??'') ?>" required placeholder="Ejemplo: Aviso de privacidad integral"><small>Usa el nombre que reconocerán tus visitantes.</small></label></div>
    <label class="admin-field legal-body-field"><span>Texto aprobado *</span><textarea name="body" rows="14" maxlength="40000" required placeholder="Escribe o pega aquí el documento aprobado. Se conservará como texto, con sus saltos de línea."><?= e($values['body']??'') ?></textarea><small>Hasta 40,000 caracteres. Revisa responsables, medios de contacto y vigencia antes de publicar.</small></label>
    <label class="admin-check legal-publish-check"><input type="checkbox" name="publish" value="1" <?= ($values['publish']??'')==='1'?'checked':'' ?>><span><strong>Publicar esta versión al guardarla</strong><small>Si no la marcas, quedará registrada como borrador para consulta interna.</small></span></label>
    <footer><p>Guardar crea un registro nuevo; las versiones anteriores permanecerán disponibles.</p><button class="admin-primary-button" type="submit">Crear versión</button></footer>
</form>

<section class="admin-panel legal-versions" aria-labelledby="legal-versions-title">
    <header><div><h2 id="legal-versions-title">Versiones registradas</h2><p>Consulta el historial y abre los textos publicados.</p></div><span class="cms-list-count"><?= e(min(25,count($versions))) ?> en esta página</span></header>
    <?php if(!$versions):?><div class="legal-empty"><span class="legal-document-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H5V3h2Zm7 0v5h4M8 12h7M8 16h7"/></svg></span><h3>Todavía no hay documentos</h3><p>Crea la primera versión aprobada. La recepción pública permanecerá cerrada mientras no exista un aviso de privacidad publicado.</p></div>
    <?php else:?><div class="legal-version-list"><?php foreach(array_slice($versions,0,25) as $v):?><details class="legal-version"><summary><span><strong><?= e($documentLabels[$v['code']]??$v['code']) ?></strong><small>Versión <?= e($v['version_number']) ?> · <?= e($v['created_at']) ?> UTC</small></span><span class="legal-version-state <?= $v['published_at']?'is-published':'' ?>"><?= $v['published_at']?'Publicada':'Borrador' ?></span></summary><div class="legal-version-content"><h3><?= e($v['title']) ?></h3><p class="legal-version-body"><?= e($v['body']) ?></p><dl><div><dt>Integridad del contenido</dt><dd><?= e($v['content_hash']) ?></dd></div></dl><?php if($v['published_at']):?><a class="admin-secondary-button" href="/legal?document=<?= e($v['code']) ?>&amp;version=<?= e($v['id']) ?>">Consultar versión pública</a><?php endif?></div></details><?php endforeach?></div><?php endif?>
    <?php if($offset||count($versions)>25):?><nav class="cms-list-pagination" aria-label="Paginación de versiones"><?php if($offset):?><a class="admin-secondary-button" href="/admin/cms/legal?offset=<?= e(max(0,$offset-25)) ?>">Anterior</a><?php endif?><?php if(count($versions)>25):?><a class="admin-secondary-button" href="/admin/cms/legal?offset=<?= e($offset+25) ?>">Siguiente</a><?php endif?></nav><?php endif?>
</section>
</div>
</div>
