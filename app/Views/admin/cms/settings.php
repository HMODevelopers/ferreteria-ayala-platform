<?php $configuredBranches=count(array_filter($branches,static fn(array $b):bool=>(bool)$b['intake_enabled'])); ?>
<div class="publication-settings">
    <header class="admin-page-head publication-page-head">
        <div><p class="admin-kicker">SITIO WEB</p><h1>Publicación del sitio</h1><p>Decide qué ven tus visitantes y cómo recibes sus solicitudes de cotización.</p></div>
        <div class="publication-header-actions">
            <?php if($canViewContent??false):?><a class="admin-secondary-button" href="/admin/cms">Contenido del sitio</a><?php endif?>
            <a class="publication-preview-link" href="/" target="_blank" rel="noopener">Ver sitio público <?= icon('arrow') ?><span class="sr-only">(abre en otra pestaña)</span></a>
        </div>
    </header>

    <section class="publication-overview" aria-label="Estado guardado de la publicación">
        <article class="publication-status-card">
            <span class="publication-status-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01M7 13h4M7 16h8"/></svg></span>
            <div><p>Sitio público</p><strong><?= $siteEnabled?'Publicación activada':'En demostración' ?></strong><span class="publication-caption"><?= $siteEnabled?'Utiliza tu contenido publicado':'Tu contenido todavía no está visible' ?></span></div>
            <span class="publication-status-dot <?= $siteEnabled?'is-on':'is-pending' ?>" aria-hidden="true"></span>
        </article>
        <article class="publication-status-card">
            <span class="publication-status-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 5h18v14H3zM3 6l9 7 9-7"/></svg></span>
            <div><p>Solicitudes de cotización</p><strong><?= $intakeEnabled?'Activadas globalmente':'Recepción desactivada' ?></strong><span class="publication-caption">También requieren habilitar cada sucursal</span></div>
            <span class="publication-status-dot <?= $intakeEnabled?'is-on':'is-off' ?>" aria-hidden="true"></span>
        </article>
        <article class="publication-status-card">
            <span class="publication-status-icon" aria-hidden="true"><?= icon('shield') ?></span>
            <div><p>Aviso de privacidad</p><strong><?= ($privacyPublished??false)?'Publicado':'Pendiente de publicación' ?></strong><span class="publication-caption"><?= ($privacyPublished??false)?'Hay una versión pública disponible':'Necesario para recibir solicitudes' ?></span></div>
            <span class="publication-status-dot <?= ($privacyPublished??false)?'is-on':'is-pending' ?>" aria-hidden="true"></span>
        </article>
    </section>

    <div class="publication-controls">
        <form method="post" action="/admin/cms/settings" class="publication-control" aria-labelledby="publication-site-title">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="target" value="site"><input type="hidden" name="expected" value="<?= e($siteEnabled?'1':'0') ?>">
            <header class="publication-control-head"><span class="publication-step">01</span><span class="publication-state <?= $siteEnabled?'is-on':'is-pending' ?>"><?= $siteEnabled?'Activada':'En demostración' ?></span></header>
            <h2 id="publication-site-title">Publicar tu sitio</h2><p class="publication-control-copy">Muestra la información y las imágenes que ya revisaste. Mientras esté desactivado, se verá la demostración identificada.</p>
            <label class="admin-field publication-select"><span>Qué mostrar al visitante</span><select name="enabled"><option value="0">Sitio de demostración</option><option value="1" <?= $siteEnabled?'selected':'' ?>>Contenido publicado</option></select></label>
            <footer class="publication-control-footer"><span>Los cambios se aplican al guardar.</span><button type="submit" class="admin-primary-button">Guardar publicación</button></footer>
        </form>
        <form method="post" action="/admin/cms/settings" class="publication-control" aria-labelledby="publication-intake-title">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="target" value="intake_global"><input type="hidden" name="expected" value="<?= e($intakeEnabled?'1':'0') ?>">
            <header class="publication-control-head"><span class="publication-step">02</span><span class="publication-state <?= $intakeEnabled?'is-on':'is-off' ?>"><?= $intakeEnabled?'Activada globalmente':'Desactivada' ?></span></header>
            <h2 id="publication-intake-title">Recibir solicitudes de cotización</h2><p class="publication-control-copy">Permite que tus visitantes soliciten materiales. Las solicitudes y su historial se conservan cuando desactivas la recepción.</p>
            <label class="admin-field publication-select"><span>Recepción de solicitudes</span><select name="enabled"><option value="0">Desactivada globalmente</option><option value="1" <?= $intakeEnabled?'selected':'' ?>>Activada globalmente</option></select></label>
            <footer class="publication-control-footer"><span>Configura las sucursales a continuación.</span><button type="submit" class="admin-primary-button">Guardar recepción global</button></footer>
        </form>
    </div>

    <aside class="publication-readiness" aria-labelledby="publication-readiness-title">
        <div><span class="publication-readiness-icon" aria-hidden="true"><?= icon('shield') ?></span><div><h2 id="publication-readiness-title">Antes de recibir una solicitud</h2><p>La recepción necesita estas tres condiciones, además de la activación global.</p></div></div>
        <ul>
            <li><span class="publication-check <?= $siteEnabled?'is-ready':'' ?>" aria-hidden="true"><?= icon($siteEnabled?'check':'list') ?></span><span>Sitio público activado<strong><?= $siteEnabled?'Listo':'Pendiente' ?></strong></span></li>
            <li><span class="publication-check <?= ($privacyPublished??false)?'is-ready':'' ?>" aria-hidden="true"><?= icon(($privacyPublished??false)?'check':'list') ?></span><span>Aviso de privacidad publicado<strong><?= ($privacyPublished??false)?'Listo':'Pendiente' ?></strong></span></li>
            <li><span class="publication-check <?= $configuredBranches>0?'is-ready':'' ?>" aria-hidden="true"><?= icon($configuredBranches>0?'check':'list') ?></span><span>Una sucursal habilitada<strong><?= $configuredBranches>0?'Listo':'Pendiente' ?></strong></span></li>
        </ul>
        <?php if(!($privacyPublished??false)&&($canManageLegal??false)):?><a href="/admin/cms/legal">Revisar documentos legales <?= icon('arrow') ?></a><?php endif?>
    </aside>

    <section class="publication-branches" aria-labelledby="publication-branches-title">
        <header class="publication-branches-head"><div><h2 id="publication-branches-title">Recepción por sucursal</h2><p>Elige cuáles participarán cuando la recepción esté disponible.</p></div><?php if($branches):?><span class="publication-branch-count"><?= e($configuredBranches) ?> de <?= e(count($branches)) ?> habilitadas</span><?php endif?></header>
        <?php if(!$branches):?>
            <div class="publication-empty">
                <span class="publication-empty-icon" aria-hidden="true"><svg viewBox="0 0 40 40"><path d="M7 17h26v17H7zM5 17l4-9h22l4 9M15 34V23h10v11M7 17c0 5 6 5 6 0 0 5 7 5 7 0 0 5 7 5 7 0 0 5 6 5 6 0"/></svg></span>
                <h3>No hay sucursales activas</h3><p>Registra una sucursal o activa una existente para configurar dónde recibirás las solicitudes.</p>
                <div class="publication-empty-actions"><?php if($canCreateBranch??false):?><a class="admin-primary-button" href="/admin/branches/create"><?= icon('plus') ?> Registrar sucursal</a><?php endif?><?php if($canViewBranches??false):?><a class="admin-secondary-button" href="/admin/branches">Ver sucursales</a><?php endif?></div>
            </div>
        <?php else:?>
            <div class="publication-branch-list"><?php foreach($branches as $b):?><form method="post" action="/admin/cms/settings" class="publication-branch" aria-labelledby="publication-branch-<?= e($b['id']) ?>">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="target" value="intake"><input type="hidden" name="branch_id" value="<?= e($b['id']) ?>"><input type="hidden" name="expected" value="<?= e($b['intake_enabled']) ?>">
                <div class="publication-branch-name"><span class="publication-branch-icon" aria-hidden="true"><?= icon('pin') ?></span><div><h3 id="publication-branch-<?= e($b['id']) ?>"><?= e($b['name']) ?></h3><span class="publication-state <?= (int)$b['intake_enabled']?'is-on':'is-off' ?>"><?= (int)$b['intake_enabled']?'Habilitada':'Deshabilitada' ?></span></div></div>
                <label class="admin-field publication-select"><span>Recepción en esta sucursal</span><select name="enabled"><option value="0">No recibe solicitudes</option><option value="1" <?= (int)$b['intake_enabled']?'selected':'' ?>>Recibe solicitudes</option></select></label>
                <button type="submit" class="admin-secondary-button">Guardar sucursal</button>
            </form><?php endforeach?></div>
        <?php endif?>
    </section>
</div>
