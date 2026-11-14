import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';

const required = ['M02_HTTP_BASE_URL', 'M02_HTTP_ADMIN_EMAIL', 'M02_HTTP_ADMIN_PASSWORD', 'M02_HTTP_USER_EMAIL', 'M02_HTTP_USER_PASSWORD', 'M02_HTTP_USER_PASSWORD_CHANGED'];
for (const name of required) assert.ok(process.env[name], `${name} is required`);

const base = process.env.M02_HTTP_BASE_URL;
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || '/opt/google/chrome/chrome', headless: true });
const errors = [];

const watch = page => {
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
};
const submit = async form => {
  const page = form.page();
  const navigation = page.waitForNavigation();
  await form.locator('button[type="submit"]').click();
  const confirmation = page.locator('[data-admin-confirm-modal]');
  if (await confirmation.isVisible()) await confirmation.locator('[data-admin-confirm-accept]').click();
  await navigation;
};
const login = async (page, email, password) => {
  await page.goto(`${base}/admin/login`);
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await submit(page.locator('form[action="/admin/login"]'));
  assert.equal(page.url(), `${base}/admin`);
};
const logout = async page => {
  const token = await page.locator('form[action="/admin/logout"] input[name="_token"]').inputValue();
  const response = await page.request.post(`${base}/admin/logout`, { form: { _token: token }, maxRedirects: 0 });
  assert.equal(response.status(), 303);
  assert.equal(response.headers().location, '/admin/login');
  await page.goto(`${base}/admin/login`);
};

try {
  const adminContext = await browser.newContext();
  const admin = await adminContext.newPage();
  watch(admin);
  await login(admin, process.env.M02_HTTP_ADMIN_EMAIL, process.env.M02_HTTP_ADMIN_PASSWORD);

  for (const path of ['/admin', '/admin/users', '/admin/roles', '/admin/branches', '/admin/audit']) {
    const response = await admin.goto(`${base}${path}`);
    assert.equal(response.status(), 200, path);
    assert.equal(await admin.locator('h1').count(), 1, path);
  }

  for (const width of [360, 768, 1024, 1440]) {
    await admin.setViewportSize({ width, height: 900 });
    await admin.goto(`${base}/admin`);
    assert.equal(await admin.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Admin overflow at ${width}`);
  }

  const forged = await admin.request.post(`${base}/admin/roles/create`, {
    form: { _token: 'invalid', code: 'FORGED_ROLE', name: 'Forged role', 'permission_ids[]': '1' },
    maxRedirects: 0,
  });
  assert.equal(forged.status(), 419);

  await admin.goto(`${base}/admin/branches/create`);
  const branchForm = admin.locator('form[action="/admin/branches/create"]');
  await branchForm.locator('input[name="code"]').fill('M02E2E');
  await branchForm.locator('input[name="name"]').fill('Sucursal sintética M02');
  await branchForm.locator('textarea[name="address"]').fill('Registro sintético exclusivo de integración');
  await submit(branchForm);
  let branchRow = admin.locator('tr').filter({ hasText: 'M02E2E' });
  assert.equal(await branchRow.count(), 1);
  const editBranchHref = await branchRow.locator('a', { hasText: 'Editar' }).getAttribute('href');
  const detailBranchHref = await branchRow.locator('a', { hasText: 'Consultar' }).getAttribute('href');
  assert.ok(editBranchHref && detailBranchHref);

  await admin.goto(`${base}${editBranchHref}`);
  const editBranchForm = admin.locator('form[action="/admin/branches/update"]');
  await editBranchForm.locator('input[name="name"]').fill('Sucursal sintética M02 actualizada');
  await submit(editBranchForm);
  assert.match(await admin.locator('.admin-alert').textContent(), /actualizada/i);

  await admin.goto(`${base}/admin/branches`);
  branchRow = admin.locator('tr').filter({ hasText: 'M02E2E' });
  admin.once('dialog', dialog => dialog.accept());
  await submit(branchRow.locator('form[action="/admin/branches/status"]'));
  assert.match(await admin.locator('.admin-alert').textContent(), /activada/i);
  await admin.goto(`${base}${detailBranchHref}`);
  assert.match(await admin.locator('body').textContent(), /No hay horarios registrados/);
  assert.match(await admin.locator('body').textContent(), /No hay servicios asociados/);

  await admin.goto(`${base}/admin/roles/create`);
  const roleForm = admin.locator('form[action="/admin/roles/create"]');
  await roleForm.locator('input[name="code"]').fill('M02_E2E_ROLE');
  await roleForm.locator('input[name="name"]').fill('Rol sintético M02');
  for (const code of ['dashboard.view', 'users.view', 'branches.view']) {
    await roleForm.locator('label').filter({ hasText: code }).locator('input[type="checkbox"]').check();
  }
  await submit(roleForm);
  let roleCard = admin.locator('.role-card').filter({ hasText: 'M02_E2E_ROLE' });
  assert.equal(await roleCard.count(), 1);
  const editRoleHref = await roleCard.locator('a', { hasText: 'Configurar permisos' }).getAttribute('href');
  assert.ok(editRoleHref);
  await admin.goto(`${base}${editRoleHref}`);
  const editRoleForm = admin.locator('form[action="/admin/roles/update"]');
  await editRoleForm.locator('input[name="name"]').fill('Rol sintético M02 actualizado');
  admin.once('dialog', dialog => dialog.accept());
  await submit(editRoleForm);
  assert.match(await admin.locator('.admin-alert').textContent(), /actualizado/i);

  await admin.goto(`${base}/admin/users/create`);
  const userForm = admin.locator('form[action="/admin/users/create"]');
  await userForm.locator('input[name="display_name"]').fill('Usuario sintético M02');
  await userForm.locator('input[name="email"]').fill(process.env.M02_HTTP_USER_EMAIL);
  await userForm.locator('input[name="password"]').fill(process.env.M02_HTTP_USER_PASSWORD);
  await userForm.locator('input[name="password_confirmation"]').fill(process.env.M02_HTTP_USER_PASSWORD);
  await userForm.locator('fieldset').filter({ hasText: 'Roles globales' }).locator('label').filter({ hasText: 'M02_E2E_ROLE' }).locator('input').check();
  await submit(userForm);
  let userRow = admin.locator('tr').filter({ hasText: process.env.M02_HTTP_USER_EMAIL });
  assert.equal(await userRow.count(), 1);
  const editUserHref = await userRow.locator('a', { hasText: 'Editar' }).getAttribute('href');
  assert.ok(editUserHref);

  admin.once('dialog', dialog => dialog.accept());
  await submit(userRow.locator('form[action="/admin/users/status"]'));
  assert.match(await admin.locator('.admin-alert').textContent(), /activado/i);

  await admin.goto(`${base}${editUserHref}`);
  const profileForm = admin.locator('form[action="/admin/users/update"]');
  await profileForm.locator('input[name="display_name"]').fill('Usuario sintético actualizado');
  await submit(profileForm);
  assert.match(await admin.locator('.admin-alert').textContent(), /actualizados/i);
  admin.once('dialog', dialog => dialog.accept());
  await submit(admin.locator('form[action="/admin/users/assignments"]'));
  assert.match(await admin.locator('.admin-alert').textContent(), /Asignaciones actualizadas/i);

  const userContext = await browser.newContext();
  const user = await userContext.newPage();
  watch(user);
  await login(user, process.env.M02_HTTP_USER_EMAIL, process.env.M02_HTTP_USER_PASSWORD);
  assert.equal((await user.goto(`${base}/admin/branches`)).status(), 200);
  assert.equal((await user.request.get(`${base}/admin/roles`)).status(), 403);

  await admin.goto(`${base}${editUserHref}`);
  const passwordForm = admin.locator('form[action="/admin/users/password"]');
  await passwordForm.locator('input[name="password"]').fill(process.env.M02_HTTP_USER_PASSWORD_CHANGED);
  await passwordForm.locator('input[name="password_confirmation"]').fill(process.env.M02_HTTP_USER_PASSWORD_CHANGED);
  admin.once('dialog', dialog => dialog.accept());
  await submit(passwordForm);
  assert.match(await admin.locator('.admin-alert').textContent(), /Contraseña actualizada/i);

  await user.goto(`${base}/admin`);
  assert.equal(user.url(), `${base}/admin/login`);
  await login(user, process.env.M02_HTTP_USER_EMAIL, process.env.M02_HTTP_USER_PASSWORD_CHANGED);
  await logout(user);
  assert.equal(user.url(), `${base}/admin/login`);
  await userContext.close();

  await admin.goto(`${base}/admin/users`);
  const adminRow = admin.locator('tr').filter({ hasText: process.env.M02_HTTP_ADMIN_EMAIL });
  admin.once('dialog', dialog => dialog.accept());
  await submit(adminRow.locator('form[action="/admin/users/status"]'));
  assert.match(await admin.locator('.admin-alert').textContent(), /último administrador global activo/i);

  await admin.goto(`${base}/admin/audit`);
  const auditText = await admin.locator('body').textContent();
  for (const action of ['users.create', 'users.password', 'roles.create', 'branches.create', 'auth.login']) assert.match(auditText, new RegExp(action.replace('.', '\\.')));
  await logout(admin);
  assert.equal(admin.url(), `${base}/admin/login`);
  await adminContext.close();

  assert.deepEqual(errors, []);
  console.log('PASS: authenticated admin HTTP/browser flows, writes, CSRF, authorization, revocation, audit and responsive panel.');
} finally {
  await browser.close();
}
