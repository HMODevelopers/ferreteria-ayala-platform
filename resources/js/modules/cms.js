export function initCmsTerms(){
 document.addEventListener('click',event=>{
  const button=event.target.closest('[data-cms-copy-terms]');if(!button)return;
  const root=button.closest('form')??button.closest('section');const field=root?.querySelector('textarea[name="terms"]');
  if(field){field.value=button.dataset.cmsCopyTerms;field.dispatchEvent(new Event('input',{bubbles:true}));field.focus();}
 });
}
