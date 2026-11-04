export function initTransfers(root = document) {
 const form=root.querySelector('[data-transfer-create]');
 if(form&&!form.dataset.initialized){form.dataset.initialized='1';const result=form.querySelector('[data-transfer-results]');const status=form.querySelector('[data-transfer-status]');
 form.querySelector('[data-transfer-find]').addEventListener('click',async()=>{try{
 const query=new URLSearchParams({q:form.querySelector('[data-transfer-search]').value,source_branch_id:form.elements.source_branch_id.value});
 const response=await fetch('/admin/transfers/products?'+query,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const variants=await response.json();
 const retained=new Set();for(const row of [...result.rows]){const input=row.querySelector('input[name^="quantity"]');if(input?.value&&input.value!=='0')retained.add(input.name);else row.remove();}
 for(const v of variants){if(retained.has('quantity['+v.id+']'))continue;const row=document.createElement('tr');const cell=(label,text)=>{const c=document.createElement('td');c.dataset.label=label;c.textContent=text;row.append(c);return c;};
 cell('Producto / SKU',v.product_name+' · '+v.sku);cell('Disponible origen',v.balance?.available_quantity??'Sin existencia inicial');const unit=cell('Unidad','');const select=document.createElement('select');select.name='unit_id['+v.id+']';select.setAttribute('aria-label','Unidad '+v.sku);for(const u of v.units){const option=document.createElement('option');option.value=u.id;option.textContent=u.name+' ('+u.symbol+')';select.append(option);}unit.append(select);
 const qty=cell('Cantidad solicitada','');const input=document.createElement('input');input.name='quantity['+v.id+']';input.inputMode='decimal';input.setAttribute('aria-label','Cantidad '+v.sku);qty.append(input);result.append(row);}
 status.textContent=variants.length+' resultados. Disponibilidad en unidad base; se verifica nuevamente al aprobar.';
 }catch{status.textContent='No se pudo consultar la mercancía. Conservamos las cantidades capturadas.';}});
 }
 for(const event of root.querySelectorAll('[data-transfer-event], [data-transfer-create]')){if(event.dataset.submitInitialized)continue;event.dataset.submitInitialized='1';event.addEventListener('submit',e=>{if(event.dataset.submitting){e.preventDefault();return;}event.dataset.submitting='1';for(const b of event.querySelectorAll('button[type="submit"]'))b.disabled=true;});}
}
