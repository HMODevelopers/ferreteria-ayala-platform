import {chromium,expect} from '@playwright/test';
import {createServer} from 'node:http';
import {readFile,writeFile,mkdir} from 'node:fs/promises';
import path from 'node:path';
const directory='tests/artifacts/publication-settings';await mkdir(directory,{recursive:true});const assetRoot=path.resolve('public/assets');
const server=createServer(async(req,res)=>{try{
 if(req.method!=='GET'){res.writeHead(405);res.end();return}
 const url=new URL(req.url,'http://localhost');let file;
 if(url.pathname.startsWith('/assets/')){file=path.resolve('public','.'+decodeURIComponent(url.pathname));if(!file.startsWith(assetRoot+path.sep)){res.writeHead(404);res.end();return}}
 else if(url.pathname==='/admin/cms/settings'){const state=url.searchParams.get('case')||'empty';if(!['empty','branches','restricted'].includes(state)){res.writeHead(404);res.end();return}file=path.join(directory,state+'.html')}
 else{res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'});res.end('<main id="admin-content"><h1>Vista de prueba</h1></main>');return}
 const body=await readFile(file);const mime={'.html':'text/html; charset=utf-8','.css':'text/css','.js':'application/javascript','.woff2':'font/woff2','.svg':'image/svg+xml'}[path.extname(file)]||'application/octet-stream';res.writeHead(200,{'Content-Type':mime,'Cache-Control':'no-store'});res.end(body);
 }catch{res.writeHead(404);res.end()}});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const base='http://127.0.0.1:'+server.address().port;const browser=await chromium.launch({channel:'chrome',headless:true});const checks=[];const errors=[];
const check=(v,label)=>{if(!v)throw new Error(label);checks.push(label)};
try{
 const context=await browser.newContext();const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});page.on('requestfailed',r=>errors.push(r.url()));
 for(const state of ['empty','branches'])for(const width of [360,768,1024,1440,1920,2560]){
  await page.setViewportSize({width,height:1000});await page.goto(base+'/admin/cms/settings?case='+state);await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(80);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),state+' fits viewport '+width);
  const fullWidth=await page.evaluate(()=>{const main=document.querySelector('.admin-content');const panel=document.querySelector('.publication-settings');const style=getComputedStyle(main);const box=panel.getBoundingClientRect();const parent=main.getBoundingClientRect();const left=parseFloat(style.paddingLeft);const right=parseFloat(style.paddingRight);return Math.abs(box.left-parent.left-left)<2&&Math.abs(box.width-(main.clientWidth-left-right))<2});check(fullWidth,'Publication fills available workspace at '+width+' for '+state);
  const cards=page.locator('.publication-control');const first=await cards.first().boundingBox();const second=await cards.nth(1).boundingBox();
  if(width>=1024)check(Math.abs(first.y-second.y)<2&&second.x>first.x,'Configuration cards sit side by side at '+width+' for '+state);
  if(width===360)check(second.y>first.y+first.height-1,'Configuration cards stack on mobile for '+state);
  const button=cards.first().getByRole('button',{name:'Guardar publicación',exact:true});const box=await button.boundingBox();check(box.width<first.width-60,'Save action uses compact width at '+width+' for '+state);
  await page.screenshot({path:directory+'/'+state+'-'+width+'.png',fullPage:true});
 }
 await page.goto(base+'/admin/cms/settings?case=empty');await expect(page.locator('.publication-status-card').nth(0)).toContainText('En demostración');await expect(page.locator('.publication-status-card').nth(1)).toContainText('Recepción desactivada');await expect(page.locator('.publication-status-card').nth(2)).toContainText('Pendiente de publicación');checks.push('Overview shows persisted site, intake and privacy states');
 await expect(page.getByRole('heading',{name:'No hay sucursales activas',exact:true})).toBeVisible();await expect(page.getByRole('link',{name:'Registrar sucursal',exact:true})).toHaveAttribute('href','/admin/branches/create');await expect(page.getByRole('link',{name:'Ver sucursales',exact:true})).toHaveAttribute('href','/admin/branches');checks.push('Empty state offers authorized branch actions');
 check(!await page.locator('.publication-settings').innerText().then(text=>/\bM(?:01|08|11)\b/.test(text)),'Visible copy avoids internal module identifiers');
 const site=page.locator('form[aria-labelledby="publication-site-title"]');await site.getByRole('combobox',{name:'Qué mostrar al visitante'}).selectOption('1');await expect(page.locator('.publication-status-card').nth(0)).toContainText('En demostración');checks.push('Changing control does not claim an unsaved state');
 const data=await site.evaluate(form=>({action:new URL(form.action).pathname,method:form.method,values:Object.fromEntries(new FormData(form))}));check(data.action==='/admin/cms/settings'&&data.method==='post'&&data.values.target==='site'&&data.values.expected==='0'&&data.values.enabled==='1'&&data.values._token==='publication-fixture-only','Site form preserves action, method, CSRF and expected-state contract');
 await site.getByRole('combobox').focus();await page.keyboard.press('Tab');await expect(site.getByRole('button',{name:'Guardar publicación',exact:true})).toBeFocused();checks.push('Keyboard reaches compact save action after its control');
 await page.goto(base+'/admin/cms/settings?case=branches');await expect(page.locator('.publication-status-card').nth(0)).toContainText('Publicación activada');await expect(page.locator('.publication-status-card').nth(2)).toContainText('Publicado');await expect(page.locator('.publication-branch-count')).toHaveText('1 de 2 habilitadas');checks.push('Enabled overview and branch count reflect actual fixture values');
 for(const id of ['21','22']){const form=page.locator('form').filter({has:page.locator('[name=branch_id][value="'+id+'"]')});const values=await form.evaluate(el=>Object.fromEntries(new FormData(el)));check(values.target==='intake'&&values.branch_id===id&&values.expected===(id==='21'?'1':'0')&&values.enabled===values.expected,'Branch '+id+' has independent existing save contract');}
 await expect(page.locator('.publication-branch').first()).toContainText('Habilitada');await expect(page.locator('.publication-branch').nth(1)).toContainText('Deshabilitada');checks.push('Each branch has its own visible saved status');
 await page.goto(base+'/admin/cms/settings?case=restricted');for(const name of ['Registrar sucursal','Ver sucursales','Contenido del sitio','Revisar documentos legales'])check(await page.getByRole('link',{name,exact:true}).count()===0,'Unauthorized shortcut omitted: '+name);
 const preview=page.getByRole('link',{name:/Ver sitio público/});await expect(preview).toHaveAttribute('href','/');await expect(preview).toHaveAttribute('rel',/noopener/);checks.push('Public preview is local and safely opens another tab');
 const noJsContext=await browser.newContext({javaScriptEnabled:false});const noJs=await noJsContext.newPage();await noJs.goto(base+'/admin/cms/settings?case=empty');await noJs.getByRole('combobox',{name:'Qué mostrar al visitante'}).selectOption('1');await expect(noJs.getByRole('button',{name:'Guardar publicación',exact:true})).toBeEnabled();checks.push('Native settings controls work without JavaScript');
 check(errors.length===0,'No console, script or failed network errors');await writeFile(directory+'/pass.json',JSON.stringify({passed_at:Math.floor(Date.now()/1000),checks},null,2));console.log('PASS: '+checks.length+' publication design browser checks; no settings or database changed.');
}catch(e){console.error(e);process.exitCode=1}finally{await browser.close();await new Promise(resolve=>server.close(resolve))}
