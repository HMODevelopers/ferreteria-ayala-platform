<section class="brands" aria-labelledby="brands-heading" data-brand-carousel>
  <div class="brand-carousel-heading">
    <h2 id="brands-heading">Marcas que conoces</h2>
    <?php if($isDemo??true):?><p>Logotipos ficticios de demostración</p><?php endif?>
  </div>
  <?php if($brands):?>
    <div class="brand-carousel-viewport" tabindex="0" aria-label="Marcas disponibles; desplázate horizontalmente para verlas">
      <div class="brand-belt">
        <?php for($copy=0;$copy<2;$copy++):?><div class="brand-group<?= $copy?' brand-group-copy':'' ?>" <?= $copy?'aria-hidden="true" inert':'' ?>>
          <?php foreach($brands as $brand):?><div class="brand-card"><?php if(!empty($brand['image_url'])):?><img src="<?= e($brand['image_url']) ?>" alt="<?= $copy?'':e($brand['alt']??$brand['name']) ?>" loading="lazy" width="200" height="80"><?php else:?><span><?= e($brand['name']) ?></span><?php endif?></div><?php endforeach?>
        </div><?php endfor?>
      </div>
    </div>
    <?php if(count($brands)>1):?><button class="brand-motion-control" type="button" data-brand-motion hidden aria-pressed="false" aria-label="Pausar movimiento" title="Pausar movimiento"><svg viewBox="0 0 24 24" aria-hidden="true"><path class="brand-icon-pause" d="M8 5v14M16 5v14"/><path class="brand-icon-play" d="m9 5 10 7-10 7z"/></svg></button><?php endif?>
  <?php else:?><p class="brand-carousel-empty">Próximamente encontrarás aquí nuestras marcas.</p><?php endif?>
</section>
