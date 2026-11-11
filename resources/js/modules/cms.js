const messages={
  draft:['Borrador privado','Sólo el equipo administrativo puede verlo. Puedes seguir editándolo antes de publicar.'],
  scheduled:['Publicación programada','Debes indicar una fecha futura en “Mostrar a partir de”. Hasta entonces permanecerá privado.'],
  published:['Visible en el sitio','Se mostrará mientras esté dentro de las fechas indicadas y la publicación del sitio esté activada.'],
  archived:['Contenido archivado','Permanece guardado y conserva su historial, pero ya no se muestra a los visitantes.']
};
const updateStatus=form=>{
  const select=form.querySelector('[data-cms-status]');const help=form.querySelector('[data-cms-status-help]');if(!select||!help)return;
  const [title,body]=messages[select.value]??messages.draft;help.dataset.state=select.value;help.innerHTML='';
  const strong=document.createElement('strong');strong.textContent=title;const span=document.createElement('span');span.textContent=body;help.append(strong,span);
  const starts=form.querySelector('[name="publish_at"]');if(starts)starts.closest('.admin-field')?.classList.toggle('is-required-context',select.value==='scheduled');
};

export function initCmsEditor(root=document){
 root.querySelectorAll('[data-cms-editor-form]').forEach(form=>updateStatus(form));
}

export function initCmsTerms(){
 document.addEventListener('click',event=>{
  const button=event.target.closest('[data-cms-copy-terms]');if(!button)return;
  const root=button.closest('form')??button.closest('section');const field=root?.querySelector('textarea[name="terms"]');
  if(field){field.value=button.dataset.cmsCopyTerms;field.dispatchEvent(new Event('input',{bubbles:true}));field.focus();}
 });
 document.addEventListener('change',event=>{if(!event.target.matches('[data-cms-status]'))return;updateStatus(event.target.closest('[data-cms-editor-form]'));});
 initCmsEditor();
}
