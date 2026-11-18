<?php $style = ($variant ?? 'primary') === 'secondary' ? 'btn btn-secondary' : 'btn'; ?>
<a class="<?= e($style) ?>" href="<?= e($href) ?>" <?= str_starts_with($href,'/solicitar-cotizacion')?'data-quote-open':'' ?>><?= e($label) ?><?= icon('arrow') ?></a>
