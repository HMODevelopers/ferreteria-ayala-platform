import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';

for (const name of ['M03_HTTP_BASE_URL', 'M03_HTTP_ADMIN_EMAIL', 'M03_HTTP_ADMIN_PASSWORD']) {
  assert.ok(process.env[name], `${name} is required`);
}
const base = process.env.M03_HTTP_BASE_URL;
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || '/opt/google/chrome/chrome', headless: true });
const errors = [];
const submit = async form => Promise.all([
  form.page().waitForNavigation(),
  form.locator('button[type="submit"], button:not([type])').last().click(),
]);

try {
  mkdirSync('tests/artifacts', { recursive: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(`${base}/admin/login`);
  await page.locator('input[name="email"]').fill(process.env.M03_HTTP_ADMIN_EMAIL);
  await page.locator('input[name="password"]').fill(process.env.M03_HTTP_ADMIN_PASSWORD);
  await submit(page.locator('form[action="/admin/login"]'));
  assert.equal(page.url(), `${base}/admin`);

  const views = [
    ['/admin/products', 'Productos'], ['/admin/categories', 'Categorías'], ['/admin/brands', 'Marcas'],
    ['/admin/units', 'Unidades'], ['/admin/attributes', 'Atributos'],
  ];
  for (const [path, heading] of views) {
    const response = await page.goto(`${base}${path}`);
    assert.equal(response.status(), 200, path);
    assert.equal(await page.locator('h1').textContent(), heading, path);
    for (const width of [360, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} overflow at ${width}`);
    }
    await page.screenshot({ path: `tests/artifacts/m03-${path.split('/').pop()}-1440.png`, fullPage: true });
  }

  const forged = await page.request.post(`${base}/admin/categories/create`, {
    form: { _token: 'invalid', code: 'FORGED', name: 'Forged' }, maxRedirects: 0,
  });
  assert.equal(forged.status(), 419);

  await page.goto(`${base}/admin/categories/create`);
  let form = page.locator('form[action="/admin/categories/create"]');
  await form.locator('input[name="code"]').fill('M03_HTTP_CATEGORY');
  await form.locator('input[name="name"]').fill('Categoría sintética M03');
  await form.locator('textarea[name="description"]').fill('Registro sintético exclusivo de integración');
  await submit(form);
  let row = page.locator('tr').filter({ hasText: 'M03_HTTP_CATEGORY' });
  assert.equal(await row.count(), 1);
  assert.match(await row.textContent(), /Inactivo/i);
  page.once('dialog', dialog => dialog.accept());
  await submit(row.locator('form[action="/admin/categories/status"]'));

  await page.goto(`${base}/admin/brands/create`);
  form = page.locator('form[action="/admin/brands/create"]');
  await form.locator('input[name="code"]').fill('M03_HTTP_BRAND');
  await form.locator('input[name="name"]').fill('Marca sintética M03');
  await submit(form);
  row = page.locator('tr').filter({ hasText: 'M03_HTTP_BRAND' });
  page.once('dialog', dialog => dialog.accept());
  await submit(row.locator('form[action="/admin/brands/status"]'));

  await page.goto(`${base}/admin/units/create`);
  form = page.locator('form[action="/admin/units/create"]');
  await form.locator('input[name="code"]').fill('M03_HTTP_UNIT');
  await form.locator('input[name="name"]').fill('Unidad sintética M03');
  await form.locator('input[name="symbol"]').fill('u3');
  await form.locator('select[name="decimal_places"]').selectOption('2');
  await submit(form);
  row = page.locator('tr').filter({ hasText: 'M03_HTTP_UNIT' });
  page.once('dialog', dialog => dialog.accept());
  await submit(row.locator('form[action="/admin/units/status"]'));

  await page.goto(`${base}/admin/attributes?new=1`);
  form = page.locator('form[action="/admin/attributes/create"]');
  await form.locator('input[name="code"]').fill('M03_HTTP_POWER');
  await form.locator('input[name="name"]').fill('Potencia sintética M03');
  await form.locator('select[name="value_type"]').selectOption('number');
  await form.locator('input[name="unit_label"]').fill('W');
  await submit(form);
  row = page.locator('tr').filter({ hasText: 'M03_HTTP_POWER' });
  assert.equal(await row.count(), 1);
  page.once('dialog', dialog => dialog.accept());
  await submit(row.locator('form[action="/admin/attributes/status"]'));

  await page.goto(`${base}/admin/products/create`);
  form = page.locator('form[action="/admin/products/create"]');
  await form.locator('input[name="code"]').fill('M03.HTTP.PRODUCT');
  await form.locator('input[name="name"]').fill('Producto sintético M03');
  await form.locator('select[name="category_id"]').selectOption({ label: 'Categoría sintética M03' });
  await form.locator('select[name="brand_id"]').selectOption({ label: 'Marca sintética M03' });
  await form.locator('input[name="sku"]').fill('M03-HTTP-SKU-1');
  await form.locator('input[name="variant_name"]').fill('Presentación sintética');
  await form.locator('select[name="unit_id"]').selectOption({ label: 'Unidad sintética M03 (u3)' });
  await form.locator('textarea[name="barcodes"]').fill('7500000000032');
  await submit(form);
  row = page.locator('tr').filter({ hasText: 'M03.HTTP.PRODUCT' });
  assert.equal(await row.count(), 1);
  assert.match(await row.textContent(), /Inactivo/i);

  await page.locator('input[name="q"]').fill('M03-HTTP-SKU-1');
  await submit(page.locator('form.admin-filter-products'));
  assert.equal(await page.locator('tr').filter({ hasText: 'M03.HTTP.PRODUCT' }).count(), 1);
  await page.locator('input[name="q"]').fill('7500000000032');
  await submit(page.locator('form.admin-filter-products'));
  row = page.locator('tr').filter({ hasText: 'M03.HTTP.PRODUCT' });
  assert.equal(await row.count(), 1);
  const detailHref = await row.locator('a', { hasText: 'Consultar' }).getAttribute('href');
  assert.ok(detailHref);
  await page.goto(`${base}${detailHref}`);
  assert.match(await page.locator('body').textContent(), /M03-HTTP-SKU-1/);
  await page.goto(new URL(await page.getByRole('link',{name:'Administrar variante',exact:true}).getAttribute('href'),base).href);
  assert.match(await page.locator('body').textContent(), /7500000000032/);
  page.once('dialog', dialog => dialog.accept());
  await submit(page.locator('form[action="/admin/products/variants/status"]'));

  await page.goto(`${base}/admin/products`);
  row = page.locator('tr').filter({ hasText: 'M03.HTTP.PRODUCT' });
  page.once('dialog', dialog => dialog.accept());
  await submit(row.locator('form[action="/admin/products/status"]'));
  assert.match(await page.locator('tr').filter({ hasText: 'M03.HTTP.PRODUCT' }).textContent(), /Activo/i);

  await page.goto(`${base}/admin/audit`);
  const audit = await page.locator('body').textContent();
  for (const action of ['categories.create', 'brands.create', 'units.create', 'attributes.create', 'products.create', 'products.activate']) {
    assert.match(audit, new RegExp(action.replace('.', '\\.')));
  }
  assert.deepEqual(errors, []);
  await submit(page.locator('form[action="/admin/logout"]'));
  assert.equal(page.url(), `${base}/admin/login`);
  await context.close();
  console.log('PASS: M03 authenticated catalog HTTP/browser flows, responsive views, CSRF, search, activation, audit and no console errors.');
} finally {
  await browser.close();
}
