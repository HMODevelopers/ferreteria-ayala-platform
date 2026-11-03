import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';

for (const key of ['M07_HTTP_BASE_URL', 'M07_HTTP_ADMIN_EMAIL', 'M07_HTTP_ADMIN_PASSWORD']) {
  assert.ok(process.env[key], `${key} is required`);
}

const base = process.env.M07_HTTP_BASE_URL;
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || '/opt/google/chrome/chrome',
  headless: true,
});
const errors = [];
const submit = async (form) => Promise.all([
  form.page().waitForNavigation(),
  form.locator('button[type="submit"],button:not([type])').last().click(),
]);

try {
  const context = await browser.newContext();
  const page = await context.newPage();
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });

  await page.goto(`${base}/admin/login`);
  await page.locator('input[name="email"]').fill(process.env.M07_HTTP_ADMIN_EMAIL);
  await page.locator('input[name="password"]').fill(process.env.M07_HTTP_ADMIN_PASSWORD);
  await submit(page.locator('form[action="/admin/login"]'));
  assert.equal(page.url(), `${base}/admin`);

  for (const [path, title] of [['/admin/customers', 'Clientes'], ['/admin/customers/create', 'Nuevo cliente']]) {
    const response = await page.goto(`${base}${path}`);
    assert.equal(response.status(), 200, path);
    assert.equal((await page.locator('h1').textContent()).trim(), title);
    for (const width of [360, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} overflow ${width}`);
    }
  }

  const forged = await page.request.post(`${base}/admin/customers/create`, {
    form: { _token: 'invalid', code: 'FORGED' },
    maxRedirects: 0,
  });
  assert.equal(forged.status(), 419);

  await page.goto(`${base}/admin/customers/create`);
  let form = page.locator('form[action="/admin/customers/create"]');
  await form.locator('select[name="branch_id"]').selectOption({ label: 'Sucursal HTTP M07' });
  await form.locator('select[name="customer_type"]').selectOption('business');
  await form.locator('input[name="code"]').fill('M07-HTTP-CUSTOMER');
  await form.locator('input[name="legal_name"]').fill('Cliente HTTP M07');
  await form.locator('input[name="email"]').fill('customer-m07@example.test');
  await form.locator('input[name="phone"]').fill('+52 641 000 0000');
  await form.locator('input[name="contact_name"]').fill('Contacto HTTP M07');
  await form.locator('input[name="address_line1"]').fill('Dirección HTTP M07');
  await form.locator('input[name="city"]').fill('Santa Ana');
  await form.locator('input[name="state_name"]').fill('Sonora');
  await submit(form);

  let row = page.locator('tr').filter({ hasText: 'M07-HTTP-CUSTOMER' });
  assert.equal(await row.count(), 1, await page.locator('body').textContent());
  await row.locator('a', { hasText: 'Detalle' }).click();
  await page.waitForLoadState();
  assert.equal(await page.locator('input[name="contact_name"]').inputValue(), 'Contacto HTTP M07');
  assert.equal(await page.locator('input[name="address_line1"]').inputValue(), 'Dirección HTTP M07');

  await page.locator('a', { hasText: 'Editar' }).click();
  await page.waitForLoadState();
  form = page.locator('form[action="/admin/customers/update"]');
  const staleData = await form.evaluate((element) => Object.fromEntries(new FormData(element)));
  await form.locator('input[name="legal_name"]').fill('Cliente HTTP M07 actualizado');
  await form.locator('input[name="contact_role"]').fill('Compras');
  await submit(form);
  assert.match(await page.locator('tr').filter({ hasText: 'M07-HTTP-CUSTOMER' }).textContent(), /actualizado/);

  await page.goto(`${base}/admin/customers?q=M07-HTTP-CUSTOMER&type=business&status=active`);
  assert.equal(await page.locator('tr').filter({ hasText: 'M07-HTTP-CUSTOMER' }).count(), 1);
  await page.goto(`${base}/admin/customers?type=individual&status=active`);
  assert.match(await page.locator('.admin-empty').textContent(), /Sin clientes/);

  const stale = await page.request.post(`${base}/admin/customers/update`, { form: staleData, maxRedirects: 0 });
  assert.equal(stale.status(), 303);
  await page.goto(`${base}/admin/customers`);
  assert.match(await page.locator('body').textContent(), /cambió en otra sesión/i);
  row = page.locator('tr').filter({ hasText: 'M07-HTTP-CUSTOMER' });
  assert.match(await row.textContent(), /actualizado/);

  page.once('dialog', (dialog) => dialog.accept());
  await submit(row.locator('form[action="/admin/customers/status"]'));
  assert.match(await page.locator('tr').filter({ hasText: 'M07-HTTP-CUSTOMER' }).textContent(), /Inactivo/);

  await page.goto(`${base}/admin/audit`);
  const audit = await page.locator('body').textContent();
  for (const action of ['customer.create', 'customer.update', 'customer.status']) {
    assert.match(audit, new RegExp(action.replaceAll('.', '\\.')));
  }
  assert.deepEqual(errors, []);

  await page.locator('[data-admin-account-menu] summary').click();
  await submit(page.locator('form[action="/admin/logout"]'));
  assert.equal(page.url(), `${base}/admin/login`);
  const unauthorized = await page.request.get(`${base}/admin/customers`, { maxRedirects: 0 });
  assert.equal(unauthorized.status(), 303);
  assert.equal(unauthorized.headers().location, '/admin/login');

  await context.close();
  console.log('PASS: M07 authenticated HTTP/browser create, edit, detail, filters, stale-version rejection, status, audit, CSRF, unauthorized access and four responsive viewports.');
} finally {
  await browser.close();
}
