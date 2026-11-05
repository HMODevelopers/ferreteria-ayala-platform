import {chromium,expect} from '@playwright/test';
import {createServer} from 'node:http';
import {readFile,writeFile,mkdir} from 'node:fs/promises';
import path from 'node:path';
const directory='tests/artifacts/admin-navigation';await mkdir(directory,{recursive:true});
const assetRoot=path.resolve('public/assets');const pages={'/admin':'dashboard','/admin/cms/settings':'cms_settings','/admin/quotes':'quotes','/admin/cms':'cms','/admin/deliveries':'deliveries'};
const server=createServer(async(req,res)=>{
 try{
  if(req.method!=='GET'){res.writeHead(405);res.end();return}
  const url=new URL(req.url,'http://localhost');let file;
  if(url.pathname.startsWith('/assets/')){file=path.resolve('public','.'+decodeURIComponent(url.pathname));if(!file.startsWith(assetRoot+path.sep)){res.writeHead(404);res.end();return}}
  else{const profile=url.searchParams.get('profile')||'all';if(!['all','page','logistics','none'].includes(profile)||!pages[url.pathname]){res.writeHead(404);res.end();return}file=path.join(directory,profile+'-'+pages[url.pathname]+'.html')}
  const body=await readFile(file);const mime={'.html':'text/html; charset=utf-8','.css':'text/css','.js':'application/javascript','.woff2':'font/woff2','.svg':'image/svg+xml'}[path.extname(file)]||'application/octet-stream';res.writeHead(200,{'Content-Type':mime,'Cache-Control':'no-store'});res.end(body);
 }catch{res.writeHead(404);res.end()}
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const base='http://127.0.0.1:'+server.address().port;
const browser=await chromium.launch({channel:'chrome',headless:true});const checks=[];const errors=[];
const check=(value,label)=>{if(!value)throw new Error(label);checks.push(label)};
const watch=p=>{p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(m.type()==='error')errors.push(m.text())});p.on('requestfailed',r=>errors.push(r.url()))};
const settle=async p=>{await p.evaluate(()=>document.fonts.ready);await p.waitForTimeout(80)};
const group=(p,id)=>p.locator('[data-admin-nav-group="'+id+'"]');
const openGroup=async(p,id)=>{await expect(group(p,id).locator('.admin-nav-children')).toBeVisible();};
try{
 const context=await browser.newContext({viewport:{width:1440,height:900}});const page=await context.newPage();watch(page);await page.goto(base+'/admin/cms/settings');await settle(page);
 await page.screenshot({path:directory+'/overview-1440.png',fullPage:true});check(await page.locator('[data-admin-nav-group]').count()===5,'All permitted groups are permanently visible');check(await page.locator('.admin-nav-summary svg').count()===0,'Group headings have no icons');check(await page.locator('.admin-nav-summary').evaluateAll(items=>new Set(items.map(el=>getComputedStyle(el).color)).size===1),'All group headings use the same neutral color');
 for(const id of ['commercial','logistics','catalog','website','administration']){await group(page,id).locator('h2').click();await expect(group(page,id).locator('.admin-nav-children')).toBeVisible();}checks.push('Clicking every group heading cannot collapse its links');
 check(await page.locator('.admin-nav-sections details,.admin-nav-sections summary').count()===0,'Group titles are static headings without disclosure controls');
 await expect(page.getByRole('link',{name:'Resumen',exact:true})).toBeVisible();check(await page.getByRole('link',{name:'Resumen',exact:true}).evaluate(el=>Boolean(el.closest('.admin-nav-sections'))),'Summary is part of the same scroll area');
 await page.goto(base+'/admin/quotes');await expect(group(page,'commercial').locator('.admin-nav-children')).toBeVisible();await expect(page.locator('a[aria-current="page"]')).toHaveAttribute('href','/admin/quotes');checks.push('Route change opens corresponding group and selects link');
 await page.goto(base+'/admin/cms?profile=page');check(await page.locator('[data-admin-nav-group]').count()===1,'Page profile only sees its permitted group');check(await page.locator('.admin-nav-link').count()===2,'Page profile only has permitted two links');check(await page.locator('.admin-nav-overview').count()===0,'No unauthorized summary for restricted profile');
 await page.evaluate(()=>localStorage.setItem('ayala.admin.navigation.v2:18',JSON.stringify({commercial:true,website:false,administration:true})));await page.reload();check(await page.locator('[data-admin-nav-group]').count()===1&&await group(page,'website').locator('.admin-nav-children').isVisible(),'Remembered preferences cannot reveal unauthorized groups');
 await page.goto(base+'/admin/deliveries?profile=logistics');check(await page.locator('.admin-nav-link').count()===1&&await group(page,'logistics').locator('.admin-nav-children').isVisible(),'Branch operations profile only renders its allowed delivery link');
 await page.goto(base+'/admin?profile=none');check(await page.locator('[data-admin-nav-group]').count()===0,'Empty groups are omitted for profile without permissions');
 await page.goto(base+'/admin/cms/settings');await page.setViewportSize({width:1440,height:700});for(const id of ['commercial','logistics','catalog','website','administration'])await openGroup(page,id);
 const scrollArea=page.locator('.admin-nav-sections');check(await scrollArea.evaluate(el=>getComputedStyle(el).scrollbarWidth)==='thin','Scrollbar remains narrow without changing layout');
 const frame=page.locator('[data-admin-nav-scroll-frame]');
 await scrollArea.evaluate(el=>el.scrollTop=0);await expect(frame).not.toHaveClass(/has-scroll-above/);await expect(frame).toHaveClass(/has-scroll-below/);checks.push('Top position indicates only hidden options below');
 await scrollArea.evaluate(el=>el.scrollTop=120);await expect(frame).toHaveClass(/has-scroll-above/);await expect(frame).toHaveClass(/has-scroll-below/);checks.push('Middle position indicates hidden options on both sides');
 await scrollArea.evaluate(el=>el.scrollTop=el.scrollHeight);await expect(frame).toHaveClass(/has-scroll-above/);await expect(frame).not.toHaveClass(/has-scroll-below/);checks.push('Bottom position removes the lower cue');
 await scrollArea.evaluate(el=>el.scrollTop=80);const stickyTop=await group(page,'commercial').locator('h2').boundingBox();const scrollBounds=await scrollArea.boundingBox();check(Math.abs(stickyTop.y-scrollBounds.y)<4,'Visible group header stays at top while its links scroll');
 await page.screenshot({path:directory+'/scroll-cues-1440.png',fullPage:true});
 await scrollArea.evaluate(el=>el.scrollTop=0);await scrollArea.hover();await page.mouse.wheel(0,500);await expect.poll(()=>scrollArea.evaluate(el=>el.scrollTop)).toBeGreaterThan(0);checks.push('Mouse wheel scrolls compact menu');
 await scrollArea.evaluate(el=>el.scrollTop=0);await scrollArea.focus();await page.keyboard.press('PageDown');await expect.poll(()=>scrollArea.evaluate(el=>el.scrollTop)).toBeGreaterThan(0);checks.push('Focused menu supports keyboard scrolling');
 const responsiveContext=await browser.newContext();const responsive=await responsiveContext.newPage();watch(responsive);
 for(const width of [360,768,1024,1440]){
  await responsive.setViewportSize({width,height:width<=900?740:900});await responsive.goto(base+'/admin/cms/settings');await settle(responsive);const sidebar=responsive.locator('[data-admin-sidebar]');
  check(await responsive.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow at '+width);
  if(width<=900){
   check(await sidebar.evaluate(e=>e.inert),'Closed mobile drawer is excluded from keyboard at '+width);await responsive.getByRole('button',{name:'Abrir navegación',exact:true}).click();await expect(sidebar).toHaveClass(/is-open/);await expect.poll(async()=>Math.abs((await sidebar.boundingBox()).x)).toBeLessThan(1);const drawerBounds=await sidebar.boundingBox();check(drawerBounds.width<=width&&drawerBounds.x>=-1,'Open drawer fully fits mobile viewport at '+width);await expect(responsive.locator('a[aria-current="page"]')).toBeFocused();check(await responsive.locator('.admin-main').evaluate(e=>e.inert),'Page content is inactive behind mobile drawer at '+width);
   await responsive.screenshot({path:directory+'/mobile-'+width+'.png',fullPage:true});
   for(const id of ['commercial','logistics','catalog','website','administration'])await openGroup(responsive,id);
   const scroll=responsive.locator('.admin-nav-sections');await scroll.evaluate(e=>e.scrollTop=0);const before=await responsive.locator('.admin-nav-overview').boundingBox();check(await scroll.evaluate(e=>e.scrollHeight>e.clientHeight),'Expanded mobile menu has its own scroll at '+width);await scroll.evaluate(e=>e.scrollTop=e.scrollHeight);const after=await responsive.locator('.admin-nav-overview').boundingBox();check(after.y<before.y-1,'Summary scrolls together with the menu at '+width);
   const controls=sidebar.locator('a:visible,button:visible,summary:visible');await controls.last().focus();await responsive.keyboard.press('Tab');await expect(controls.first()).toBeFocused();await responsive.keyboard.press('Shift+Tab');await expect(controls.last()).toBeFocused();checks.push('Mobile Tab focus stays inside menu at '+width);
   await responsive.keyboard.press('Escape');await expect(responsive.getByRole('button',{name:'Abrir navegación',exact:true})).toBeFocused();check(!await responsive.locator('.admin-main').evaluate(e=>e.inert),'Escape restores page and trigger focus at '+width);
   await responsive.getByRole('button',{name:'Abrir navegación',exact:true}).click();await responsive.getByRole('button',{name:'Cerrar navegación',exact:true}).filter({has:responsive.locator('svg')}).click();await expect(sidebar).not.toHaveClass(/is-open/);checks.push('Visible close button works at '+width);
   await responsive.getByRole('button',{name:'Abrir navegación',exact:true}).click();await responsive.locator('[data-admin-scrim]').click({position:{x:width-10,y:400}});await expect(sidebar).not.toHaveClass(/is-open/);checks.push('Backdrop closes drawer at '+width);
  }else{check(!await sidebar.evaluate(e=>e.inert),'Desktop navigation remains interactive at '+width);check(!await responsive.locator('.admin-main').evaluate(e=>e.inert),'Desktop page remains interactive at '+width);await responsive.screenshot({path:directory+'/desktop-'+width+'.png',fullPage:true})}
 }
 // Follow an existing link from the mobile drawer, then verify the new route's active group.
 await responsive.setViewportSize({width:360,height:740});await responsive.goto(base+'/admin/cms/settings');await responsive.getByRole('button',{name:'Abrir navegación',exact:true}).click();await openGroup(responsive,'commercial');await responsive.getByRole('link',{name:'Cotizaciones',exact:true}).click();await responsive.waitForURL(base+'/admin/quotes');await expect(group(responsive,'commercial').locator('.admin-nav-children')).toBeVisible();check(await responsive.locator('[data-admin-sidebar]').evaluate(e=>e.inert),'Following a mobile link closes drawer on new page');
 const broken=await browser.newContext();const fallback=await broken.newPage();watch(fallback);await fallback.addInitScript(()=>{Storage.prototype.getItem=()=>{throw new Error('storage blocked')};Storage.prototype.setItem=()=>{throw new Error('storage blocked')}});await fallback.goto(base+'/admin/cms/settings');await openGroup(fallback,'commercial');check(await group(fallback,'commercial').locator('.admin-nav-children').isVisible(),'Groups still work when localStorage is blocked');
 const noJsContext=await browser.newContext({javaScriptEnabled:false,viewport:{width:1440,height:900}});const noJs=await noJsContext.newPage();await noJs.goto(base+'/admin/cms/settings');await group(noJs,'commercial').locator('h2').click();await expect(noJs.getByRole('link',{name:'Cotizaciones',exact:true})).toBeVisible();checks.push('Group links remain visible after heading click without JavaScript');
 const reducedContext=await browser.newContext({reducedMotion:'reduce',viewport:{width:360,height:740}});const reduced=await reducedContext.newPage();watch(reduced);await reduced.goto(base+'/admin/cms/settings');check(await reduced.locator('.admin-sidebar').evaluate(e=>getComputedStyle(e).transitionDuration)==='0s','Reduced motion disables drawer transition');
 check(await page.locator('.admin-nav-chevron').count()===0,'Menu has no disclosure arrows');
 check(await page.locator('.admin-nav-overview').evaluate(e=>getComputedStyle(e).borderBottomWidth)==='0px'&&await page.locator('.admin-nav-children').first().evaluate(e=>getComputedStyle(e).borderLeftWidth)==='0px','Menu has no horizontal or vertical group separators');
 check(errors.length===0,'No console, script or failed network requests');await writeFile(directory+'/pass.json',JSON.stringify({passed_at:Math.floor(Date.now()/1000),checks},null,2));console.log('PASS: '+checks.length+' admin navigation browser checks; no database or account changes.');
}catch(e){console.error(e);process.exitCode=1}finally{await browser.close();await new Promise(resolve=>server.close(resolve))}
