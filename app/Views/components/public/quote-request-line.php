<?php
$variantId=(string)($line['variant_id']??'');
$catalogVariant=null;
foreach($variants as $variant)if((string)$variant['id']===$variantId){$catalogVariant=$variant;break;}
$isCatalog=$catalogVariant!==null;
?>
<tr class="public-quote-line<?= $isCatalog?' is-catalog':'' ?>" data-quote-line>
  <td data-label="Material" class="public-quote-material-cell">
    <input type="hidden" name="variant_id[]" value="<?= e($variantId) ?>" data-quote-variant-id>
    <span class="public-quote-source" data-quote-source><?= $isCatalog?e($catalogVariant['sku'].' · Catálogo'):'Material libre' ?></span>
    <input class="public-quote-table-input" name="description[]" maxlength="400" value="<?= e($line['description']??'') ?>" placeholder="Describe el material" aria-label="Producto o descripción" required <?= $isCatalog?'readonly':'' ?>>
  </td>
  <td data-label="Cantidad"><input class="public-quote-table-input" name="quantity[]" inputmode="decimal" maxlength="32" value="<?= e($line['quantity']??'1') ?>" aria-label="Cantidad" required></td>
  <td data-label="Unidad"><input class="public-quote-table-input" name="unit[]" maxlength="80" value="<?= e($line['unit']??'') ?>" placeholder="Pieza, saco…" aria-label="Unidad" required <?= $isCatalog?'readonly':'' ?>></td>
  <td data-label="Observaciones"><input class="public-quote-table-input" name="observations[]" maxlength="400" value="<?= e($line['observations']??'') ?>" placeholder="Medida, color o presentación" aria-label="Observaciones"></td>
  <td class="public-quote-remove-cell"><button type="button" class="admin-quiet-button" data-quote-remove-line aria-label="Quitar material">Quitar</button></td>
</tr>
