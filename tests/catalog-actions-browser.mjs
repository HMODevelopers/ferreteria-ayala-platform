import { chromium, expect } from '@playwright/test';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

const fixtureDirectory = 'tests/artifacts/catalog-design';
const posts = [];
const errors = [];
const server = createServer(async (request, response) => {
  try {
    const url = new URL(request.url, 'http://localhost');
    if (request.method === 'POST') {
      let size = 0;
      for await (const chunk of request) size += chunk.length;
      posts.push({ path: url.pathname, size });
      response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      response.end(await readFile(fixtureDirectory + '/import.html'));
      return;
    }
    let file;
    if (url.pathname.startsWith('/assets/')) file = path.resolve('public', '.' + url.pathname);
    else if (url.pathname === '/admin/products/barcodes') file = fixtureDirectory + '/barcode.html';
    else if (url.pathname === '/admin/products/import') file = fixtureDirectory + '/import.html';
    else if (url.pathname === '/admin/products/create') file = fixtureDirectory + '/create.html';
    else if (url.pathname === '/admin/products') file = fixtureDirectory + '/populated.html';
    else throw new Error('route');
    const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'application/javascript', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' };
    response.writeHead(200, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream' });
    response.end(await readFile(file));
  } catch {
    response.writeHead(404);
    response.end();
  }
});

await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = 'http://127.0.0.1:' + server.address().port;
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
page.on('pageerror', error => errors.push(error.message));
page.setDefaultTimeout(10000);

try {
  await page.goto(base + '/admin/products');
  const original = page.url();
  const modal = page.locator('[data-admin-modal]');

  await page.getByRole('link', { name: 'Escanear código', exact: true }).click();
  await expect(modal).toBeVisible();
  await expect(modal.getByRole('heading', { name: 'Escanear código' })).toBeVisible();
  await modal.locator('input[name="barcode"]').fill('7500000000032');
  await modal.getByRole('button', { name: 'Buscar variante' }).click();
  await expect(modal.getByRole('heading', { name: 'Escanear código' })).toBeVisible();
  assert.equal(page.url(), original, 'barcode tool remains in the modal');
  await page.keyboard.press('Escape');

  await page.getByRole('link', { name: 'Importar XLSX', exact: true }).click();
  await expect(modal.getByRole('heading', { name: 'Importar productos' })).toBeVisible();
  await modal.locator('input[type="file"]').setInputFiles({ name: 'catalogo.csv', mimeType: 'text/csv', buffer: Buffer.from('product_code,sku\nTEST,TEST-1') });
  await modal.getByRole('button', { name: 'Analizar archivo' }).click();
  await expect.poll(() => posts.some(post => post.path === '/admin/products/import/preview')).toBeTruthy();
  await expect(modal.getByRole('heading', { name: 'Importar productos' })).toBeVisible();
  assert.equal(page.url(), original, 'import tool remains in the modal');
  await modal.getByRole('link', { name: 'Cancelar' }).click();
  await expect(modal).not.toBeVisible();

  await page.locator('.catalog-action-menu summary').click();
  await expect(page.getByRole('link', { name: 'Editar producto' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Activar' })).toBeVisible();
  await page.screenshot({ path: fixtureDirectory + '/catalog-actions-1440.png' });
  assert.deepEqual(errors, []);
  console.log('PASS: catalog action hierarchy, row menu, barcode modal and import modal.');
} finally {
  await browser.close();
  server.closeAllConnections();
  await new Promise(resolve => server.close(resolve));
}
