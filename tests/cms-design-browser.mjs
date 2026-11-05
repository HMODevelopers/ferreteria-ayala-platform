import {chromium,expect} from '@playwright/test';
import {createServer} from 'node:http';
import {readFile,writeFile,mkdir} from 'node:fs/promises';
import path from 'node:path';
const directory='tests/artifacts/cms-design';await mkdir(directory,{recursive:true});const assetRoot=path.resolve('public/assets');
const types=['banners','promotions','events','reviews','pages','site_settings','navigation_items'];
let submissions=0,saved=false;const payloads=[];const postHeaders=[];
const fixture=(type,state)=>readFile(path.join(directory,type+'-'+state+'.html'));
const server=createServer(async(req,res)=>{try{
 const url=new URL(req.url,'http://localhost');
 if(url.pathname.startsWith('/assets/')){const file=path.resolve('public','.'+decodeURIComponent(url.pathname));if(!file.startsWith(assetRoot+path.sep)){res.writeHead(404);res.end();return}const body=await readFile(file);const mime={'.css':'text/css','.js':'application/javascript','.woff2':'font/woff2'}[path.extname(file)]||'application/octet-stream';res.writeHead(200,{'Content-Type':mime});res.end(body);return;}
 const type=url.searchParams.get('type')||'pages';if(!types.includes(type)){res.writeHead(404);res.end();return}
 if(req.method==='POST'&&url.pathname==='/admin/cms/save'){
  postHeaders.push(req.headers['x-requested-with']||'');let body='';for await(const chunk of req)body+=chunk;payloads.push(body);submissions++;
  if(submissions===1){res.writeHead(422,{'Content-Type':'text/html; charset=utf-8'});res.end(await fixture(type,'error'));return}
  saved=true;res.writeHead(303,{Location:'/admin/cms?type='+type});res.end();return;
 }
 if(req.method!=='GET'||url.pathname!=='/admin/cms'){res.writeHead(404);res.end();return}
 const state=url.searchParams.get('case')||(url.searchParams.has('id')?'edit':url.searchParams.has('editor')?'create':saved?'populated':'empty');
 if(!['empty','populated','restricted','create','edit','error','readonly','draftonly'].includes(state)){res.writeHead(404);res.end();return}
 res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'});res.end(await fixture(type,state));
}catch{res.writeHead(404);res.end()}});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const base='http://127.0.0.1:'+server.address().port;
const browser=await chromium.launch({channel:'chrome',headless:true});const checks=[],errors=[];const check=(v,label)=>{if(!v)throw new Error(label);checks.push(label)};
try{
 const context=await browser.newContext();const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 for(const type of types)for(const width of [360,768,1440,2560]){
  await page.setViewportSize({width,height:900});await page.goto(base+'/admin/cms?type='+type);await page.evaluate(()=>document.fonts.ready);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),type+' fits '+width);
  check(await page.locator('#admin-content form').count()===0,type+' has no inline form at '+width);
  if(type==='pages'){
   const geometry=await page.locator('.cms-workspace').evaluate(el=>{const parent=el.parentElement;const style=getComputedStyle(parent);return Math.abs(el.clientWidth-(parent.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight)))<2});check(geometry,'Content fills available width '+width);
   await page.screenshot({path:directory+'/listing-'+width+'.png',fullPage:true});
   await page.getByRole('link',{name:'Crear contenido',exact:true}).first().click();const modal=page.locator('[data-admin-modal]');await expect(modal).toBeVisible();await expect(modal.locator('[name=title]')).toBeFocused();
   await page.waitForTimeout(200);await page.screenshot({path:directory+'/editor-'+width+'.png',fullPage:true});check(await modal.evaluate(el=>el.scrollWidth<=el.clientWidth+1),'Modal fits '+width+' '+JSON.stringify(await modal.evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth,overflow:[...el.querySelectorAll('*')].filter(n=>n.getBoundingClientRect().right>el.getBoundingClientRect().right+1).map(n=>({tag:n.tagName,class:n.className,width:n.clientWidth}))}))));
   await page.screenshot({path:directory+'/editor-'+width+'.png',fullPage:true});
   await modal.getByRole('link',{name:'Cancelar',exact:true}).click();await expect(modal).not.toBeVisible();checks.push('Cancel closes modal and preserves list '+width);
  }
 }
 for(const type of types)for(const width of [360,1440]){
  await page.setViewportSize({width,height:900});await page.goto(base+'/admin/cms?type='+type);
  await page.getByRole('link',{name:'Crear contenido',exact:true}).first().click();const modal=page.locator('[data-admin-modal]');await expect(modal.locator('[data-cms-content-type="'+type+'"]')).toBeVisible();await page.waitForTimeout(200);
  const titles={banners:'Crear banner',promotions:'Crear promoción',events:'Crear evento',reviews:'Agregar testimonio',pages:'Crear página',site_settings:'Crear presentación',navigation_items:'Agregar enlace'};
  await expect(modal.getByRole('heading',{name:titles[type],exact:true})).toBeVisible();checks.push('Specific editor title for '+type+' at '+width);
  check(await modal.evaluate(el=>el.scrollWidth<=el.clientWidth+1),'Specific editor fits '+type+' at '+width);
  const primary=modal.locator('.cms-editor-primary');
  if(type==='events'){await expect(primary.locator('[name="fields[event_date]"]')).toHaveAttribute('type','date');await expect(primary.locator('[name="fields[location]"]')).toBeVisible();check(await primary.locator('[name="fields[event_date]"]').evaluate(el=>el.required),'Event date is a required primary field');}
  if(type==='reviews'){await expect(primary.locator('select[name="fields[rating]"]')).toBeVisible();await expect(primary.locator('[name="fields[author]"]')).toBeVisible();checks.push('Testimonial uses author and rating controls');}
  if(type==='navigation_items'){check(await primary.locator('textarea,select[name=media_id]').count()===0,'Navigation omits visible image and description');await expect(primary.locator('[name="fields[link]"]')).toHaveAttribute('required','');await expect(primary.locator('[name=display_order]')).toBeVisible();}
  const order=await modal.evaluate(()=>{const a=document.querySelector('.cms-editor-primary');const b=document.querySelector('.cms-editor-publication');return a.compareDocumentPosition(b)&Node.DOCUMENT_POSITION_FOLLOWING});check(Boolean(order),'Type-specific fields precede publication for '+type);
  await page.screenshot({path:directory+'/specific-'+type+'-'+width+'.png',fullPage:true});await modal.getByRole('button',{name:'Cerrar',exact:true}).click();
 }
 await page.setViewportSize({width:1440,height:900});await page.goto(base+'/admin/cms?type=pages');
 check(!/M11|CMS PÚBLICO|Formularios estructurados/.test(await page.locator('#admin-content').innerText()),'Product copy omits technical change labels');
 await page.getByRole('link',{name:'Crear contenido',exact:true}).first().click();const modal=page.locator('[data-admin-modal]');let form=modal.locator('[data-cms-editor-form]');
 const fields=await form.evaluate(el=>Object.fromEntries(new FormData(el)));check(fields._token==='cms-design-fixture-only'&&fields.operation_key.length>0&&fields.status==='draft','Creation keeps CSRF, operation key and draft default');
 await form.locator('[name=title]').fill('Título conservado');await form.locator('[name=slug]').fill('slug-invalido!');await form.getByRole('button',{name:'Guardar página',exact:true}).click();await expect(modal.getByRole('alert')).toContainText('identificador');await expect(modal.locator('[name=title]')).toHaveValue('Título conservado');await expect(modal).toBeVisible();check(new URL(page.url()).searchParams.get('editor')===null,'Validation remains inside modal on listing URL');
 await modal.locator('[name=slug]').fill('pagina-de-prueba');await modal.getByRole('button',{name:'Guardar página',exact:true}).click();await expect(page.locator('.cms-content-row')).toContainText('Consejos para tu proyecto');await expect(page.locator('[data-admin-modal]')).not.toBeVisible();await expect(page.getByRole('status')).toContainText('Contenido guardado.');checks.push('Successful save preserves server confirmation in the refreshed list');check(postHeaders.every(h=>h===''),'POST requests full server response so redirects include flash confirmation');check(submissions===2&&payloads.every(p=>p.includes('name="_token"')&&p.includes('cms-design-fixture-only')),'Submission uses existing POST contract and returns to listing (fixture only)');
 await page.locator('.cms-content-row').click();await expect(page.locator('[data-admin-modal]')).toBeVisible();await expect(page.locator('[data-admin-modal] [name=id]')).toHaveValue('42');await expect(page.locator('[data-admin-modal] [name=lock_version]')).toHaveValue('3');checks.push('Editing opens existing record with concurrency version');
 await page.locator('[data-admin-modal]').getByRole('heading',{name:'Versiones anteriores'}).scrollIntoViewIfNeeded();await page.locator('.cms-revision-list summary').click();await expect(page.locator('.cms-revision-list dd').first()).toHaveText('Consejos para tu proyecto');check(await page.locator('.cms-revision-list pre').count()===0,'History uses readable fields instead of raw JSON');
 await page.keyboard.press('Escape');await expect(page.locator('[data-admin-modal]')).not.toBeVisible();checks.push('Escape closes the shared modal');
 await page.goto(base+'/admin/cms?case=restricted');check(await page.locator('#admin-content [data-admin-form-link]').count()===0,'Restricted profile cannot create content');
 const noJsContext=await browser.newContext({javaScriptEnabled:false});const noJs=await noJsContext.newPage();await noJs.goto(base+'/admin/cms?editor=1');await expect(noJs.locator('[data-cms-editor-form]')).toBeVisible();checks.push('Direct form remains available without JavaScript');
 check(errors.length===0,'No script errors');await writeFile(directory+'/pass.json',JSON.stringify({passed_at:Math.floor(Date.now()/1000),checks},null,2));console.log('PASS: '+checks.length+' CMS design browser checks; fixture-only server, no database writes.');
}catch(e){console.error(e);process.exitCode=1}finally{await browser.close();await new Promise(resolve=>server.close(resolve))}
