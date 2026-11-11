import {chromium, expect} from '@playwright/test';
import {createServer} from 'node:http';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import path from 'node:path';

const directory = 'tests/artifacts/returns-design';
await mkdir(directory, {recursive: true});
const root = path.resolve('public/assets');
const server = createServer(async (request, response) => {
  try {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname.startsWith('/assets/')) {
      const file = path.resolve('public', `.${url.pathname}`);
      if (!file.startsWith(`${root}${path.sep}`)) throw new Error();
      const type = path.extname(file) === '.css' ? 'text/css' : path.extname(file) === '.js' ? 'text/javascript' : 'application/octet-stream';
      response.writeHead(200, {'Content-Type': type});
      response.end(await readFile(file));
      return;
    }
    const name = url.pathname === '/detail' || url.pathname === '/admin/returns/detail' ? 'detail'
      : url.pathname === '/empty' ? 'empty'
        : url.pathname === '/admin/returns/create' ? 'create'
          : 'list';
    response.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'});
    response.end(await readFile(`${directory}/${name}.html`));
  } catch {
    response.writeHead(404);
    response.end();
  }
});

await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({channel: 'chrome', headless: true});
const checks = [];

try {
  const page = await browser.newPage({reducedMotion: 'reduce'});
  for (const route of ['/', '/detail', '/empty']) {
    for (const width of [360, 768, 1440, 2560]) {
      await page.setViewportSize({width, height: 950});
      await page.goto(`${base}${route}`);
      await page.evaluate(() => document.fonts.ready);
      if (!await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)) {
        throw new Error(`Desbordamiento ${route} a ${width} px`);
      }
      checks.push(`${route} ${width}`);
      await page.screenshot({path: `${directory}/${route.replace('/', '')}-${width}.png`, fullPage: true});
    }
  }

  await page.goto(`${base}/detail`);
  await expect(page.getByRole('heading', {name: 'DEV-PRUEBA-001'})).toBeVisible();
  await expect(page.getByText('Pendiente de inspección', {exact: true})).toBeVisible();
  await expect(page.getByText(/M\d{2}/i)).toHaveCount(0);

  await page.goto(`${base}/`);
  await page.getByRole('link', {name: 'Abrir', exact: true}).click();
  let modal = page.locator('[data-admin-modal]');
  await expect(modal).toHaveAttribute('open', '');
  await expect(modal.getByRole('heading', {name: 'DEV-PRUEBA-001'})).toBeVisible();
  await modal.getByRole('button', {name: 'Cerrar'}).click();
  await expect(modal).not.toHaveAttribute('open', '');
  checks.push('detail modal');

  await page.goto(`${base}/empty`);
  await expect(page.getByText('Aún no hay devoluciones registradas', {exact: true})).toBeVisible();
  await page.getByRole('link', {name: 'Registrar devolución', exact: true}).click();
  modal = page.locator('[data-admin-modal]');
  await expect(modal).toHaveAttribute('open', '');
  await expect(modal.getByRole('heading', {name: 'Registrar devolución'})).toBeVisible();
  await expect(page.getByRole('heading', {name: 'Devoluciones', exact: true})).toBeVisible();
  checks.push('create modal');

  await writeFile(`${directory}/pass.json`, JSON.stringify({checks}, null, 2));
  console.log(`PASS: ${checks.length} return browser checks, including modal behavior.`);
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
