<?php $multiple=count($slides)>1; ?>
<section class="hero <?= !empty($slides[0]['is_fallback'])?'hero-fallback':'' ?>" aria-label="<?= ($isDemo??true)?'Campañas de demostración':'Información destacada' ?>">
  <div class="swiper <?= $multiple?'hero-swiper':'' ?>"><div class="swiper-wrapper">
  <?php foreach($slides as $index=>$slide):$image=$slide['image_url']??(($isDemo??true)?asset('images/'.$slide['image_desktop']):null);?>
    <article class="swiper-slide hero-slide">
      <?php if($image):?><picture><img class="hero-image" src="<?= e($image) ?>" alt="<?= e($slide['image_alt']??'') ?>" width="1600" height="1067" <?= $index===0?'fetchpriority="high"':'loading="lazy"' ?>></picture><?php endif?>
      <div class="hero-shade"></div><div class="container hero-content"><p class="hero-eyebrow"><span></span><?= e($slide['eyebrow']) ?></p>
      <?php if($index===0):?><h1 class="hero-title"><?= nl2br(e(str_replace('\n',"\n",$slide['title']))) ?></h1><?php else:?><h2 class="hero-title"><?= nl2br(e(str_replace('\n',"\n",$slide['title']))) ?></h2><?php endif?>
      <p class="hero-description"><?= e($slide['subtitle']) ?></p><?php component('button',['href'=>$slide['href'],'label'=>$slide['button']]);?><?php if($isDemo??true):?><p class="hero-disclaimer">Campaña de demostración · Imagen de referencia</p><?php endif?></div>
    </article>
  <?php endforeach?></div></div>
  <?php if($multiple):?><div class="container hero-bottom"><div class="hero-pagination"></div><div class="hero-controls"><span id="slide-number" aria-live="polite">01 / <?= e(str_pad((string)count($slides),2,'0',STR_PAD_LEFT)) ?></span><button id="hero-prev" aria-label="Anterior"><?= icon('arrow','rotate-180') ?></button><button id="hero-next" aria-label="Siguiente"><?= icon('arrow') ?></button></div></div><?php endif?>
</section>
