import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
export async function runM08Browser(cfg) {
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const context=await browser.newContext({reducedMotion:'reduce'});const page=await context.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));page.on('dialog',dialog=>dialog.accept());
 const url=path=>cfg.base+path;
 const visit=async path=>{await page.goto(url(path));await page.waitForLoadState('networkidle');};
 const click=async label=>{await page.getByRole('button',{name:label,exact:true}).click();await page.waitForLoadState('networkidle');};
 const clean=async()=>{const alerts=await page.locator('.admin-alert-error').allTextContents();assert.equal(alerts.length,0,alerts.join(' '));};
 try {
  await mkdir('tests/artifacts',{recursive:true});
  await visit('/admin/quotes');assert.match(page.url(),/admin\/login/);
  await page.getByLabel('Correo electrónico').fill(cfg.email);await page.getByLabel('Contraseña',{exact:true}).fill(cfg.password);await click('Iniciar sesión');
  await visit('/admin/quotes/create?branch_id='+cfg.branch);
  await page.getByLabel('Cliente registrado').selectOption(String(cfg.customer));await page.locator('select[name=price_list_id]').selectOption(String(cfg.commercial));
  const valid=new Date(Date.now()+5*86400000).toISOString().slice(0,10);await page.getByLabel('Vigencia elegida por el vendedor').fill(valid);await page.getByLabel('Condiciones comerciales',{exact:true}).fill('Condiciones comerciales confirmadas en la prueba aislada.');await page.getByLabel('Modalidad y condiciones de entrega').fill('Recolección acordada en sucursal.');await page.getByLabel('Importe de entrega acordado, MXN').fill('0.00');await click('Crear borrador');await clean();
  const quoteId=new URL(page.url()).searchParams.get('id');assert.ok(quoteId);
  await page.locator('[data-quote-search]').fill('M08-SKU-A');await click('Buscar productos');await page.getByText('1 resultados.',{exact:false}).waitFor();await click('Seleccionar material');await page.getByLabel('Cantidad nueva').fill('2.5');await click('Agregar y calcular');await clean();
  await page.getByLabel('Descuento partida 1',{exact:true}).fill('5');await click('Guardar y actualizar precios');await clean();assert.match(await page.locator('.quote-save-bar').innerText(),/247.95/);
  // Full form survives validation and optimistically rejects a stale edit.
  await page.getByLabel('Cantidad partida 1',{exact:true}).fill('0');await click('Guardar y actualizar precios');assert.equal(await page.locator('[role="alert"]').count(),1);assert.equal(await page.getByLabel('Cantidad partida 1',{exact:true}).inputValue(),'0');await page.getByLabel('Cantidad partida 1',{exact:true}).fill('2.5');await click('Guardar y actualizar precios');await clean();
  for(const width of [360,768,1024,1440]) {await page.setViewportSize({width,height:960});await page.waitForTimeout(100);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Editor overflow '+width);assert.equal(await page.locator('.admin-quote-section').first().evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(255, 255, 255)');assert.notEqual(await page.locator('input[name=valid_until]').evaluate(el=>getComputedStyle(el).color),'rgb(255, 255, 255)');await page.screenshot({path:'tests/artifacts/m08-editor-'+width+'.png',fullPage:true,animations:'disabled'});}
  await visit('/admin/quotes/detail?id='+quoteId);await click('Emitir cotización');await clean();assert.equal(await page.locator('.quote-document-reference').getByText('COTIZACIÓN',{exact:true}).count(),1);
  const response=await context.request.get(url('/admin/quotes/pdf?id='+quoteId));assert.equal(response.status(),200);assert.match(response.headers()['content-type'],/application\/pdf/);assert.ok((await response.body()).subarray(0,8).toString().startsWith('%PDF-1.'));
  await page.getByLabel('Motivo / justificación').fill('Aceptación confirmada por el cliente de integración');await click('Registrar aceptación y apartar');await clean();assert.match(await page.locator('.admin-page-head').innerText(),/Aceptada/);
  await page.getByLabel('Motivo / justificación').fill('Cancelación confirmada por el cliente');await click('Cancelar cotización');await clean();assert.match(await page.locator('.admin-page-head').innerText(),/Cancelada/);
  for(const width of [360,768,1024,1440]) {await page.setViewportSize({width,height:960});await page.waitForTimeout(100);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Document overflow '+width);await page.screenshot({path:'tests/artifacts/m08-document-'+width+'.png',fullPage:true});}
  const limited=await browser.newContext();const lp=await limited.newPage();await lp.goto(url('/admin/login'));await lp.getByLabel('Correo electrónico').fill(cfg.restrictedEmail);await lp.getByLabel('Contraseña',{exact:true}).fill(cfg.restrictedPassword);await lp.getByRole('button',{name:'Iniciar sesión',exact:true}).click();await lp.goto(url('/admin/quotes/detail?id='+quoteId));
  assert.equal(await lp.getByRole('button',{name:'Emitir cotización',exact:true}).count(),0);
  const forbidden=await limited.request.get(url('/admin/quotes/lookup?branch_id='+cfg.other+'&price_list_id='+cfg.commercial+'&q=M08'));assert.equal(forbidden.status(),403);
  const limitedToken=await lp.locator('input[name="_token"]').first().inputValue();const forbiddenWrite=await limited.request.post(url('/admin/quotes/action'),{form:{id:quoteId,action:'issue',lock_version:'1',operation_key:'aaaaaaaaaaaaaaaaaaaa',_token:limitedToken}});assert.ok([403,404].includes(forbiddenWrite.status()));await limited.close();
  const denied=await browser.newContext();const dp=await denied.newPage();await dp.goto(url('/admin/login'));await dp.getByLabel('Correo electrónico').fill(cfg.noPermissionEmail);await dp.getByLabel('Contraseña',{exact:true}).fill(cfg.restrictedPassword);await dp.getByRole('button',{name:'Iniciar sesión',exact:true}).click();const deniedResponse=await denied.request.get(url('/admin/quotes'));assert.equal(deniedResponse.status(),403);await denied.close();
  const csrf=await context.request.post(url('/admin/quotes/action'),{form:{id:quoteId,action:'cancelled',_token:'invalid'}});assert.equal(csrf.status(),419);
  const outside=await context.request.get(url('/admin/quotes/lookup?branch_id='+cfg.other+'&price_list_id='+cfg.commercial+'&q=M08'));assert.equal(outside.status(),200); // global admin has both branches.
  await visit('/admin/quotes?q=M08');await page.setViewportSize({width:360,height:960});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'List overflow');
  assert.deepEqual(errors,[]);console.log('PASS: M08 authenticated create/search/items/exact totals/validation/issue/PDF/accept/cancel/CSRF and four responsive widths.');
 } catch(error) {await page.screenshot({path:'tests/artifacts/m08-browser-failure.png',fullPage:true});throw error;} finally { await browser.close(); }
}
