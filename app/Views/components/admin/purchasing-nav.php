<?php $links=[['invoices','/admin/purchases','Facturas'],['suppliers','/admin/suppliers','Proveedores']]; ?>
<nav class="admin-catalog-nav" aria-label="Compras"><?php foreach($links as[$key,$href,$label]):?><a href="<?= e($href) ?>" class="<?= ($purchasingSection??'')===$key?'is-active':'' ?>"><?= e($label) ?></a><?php endforeach?></nav>
