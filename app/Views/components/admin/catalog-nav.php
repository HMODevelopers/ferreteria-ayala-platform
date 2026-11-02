<nav class="admin-catalog-nav" aria-label="Secciones del catálogo">
<?php foreach ([['products','/admin/products','Productos'],['categories','/admin/categories','Categorías'],['brands','/admin/brands','Marcas'],['units','/admin/units','Unidades'],['attributes','/admin/attributes','Atributos']] as [$key,$href,$label]): ?>
<a href="<?= e($href) ?>" class="<?= ($catalogSection ?? '') === $key ? 'is-active' : '' ?>" <?= ($catalogSection ?? '') === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach ?>
</nav>
