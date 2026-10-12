<?php $style = ($variant ?? 'primary') === 'secondary' ? 'btn btn-secondary' : 'btn'; ?>
<a class="<?= e($style) ?>" href="<?= e($href) ?>"><?= e($label) ?><?= icon('arrow') ?></a>
