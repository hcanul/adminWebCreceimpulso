const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const b = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const p = await b.newPage();
    const branches = [
      { id: '11111111-1111-4111-8111-111111111111', name: 'Carrillo' },
      { id: '22222222-2222-4222-8222-222222222222', name: 'Tulum' },
    ];
    const calls = [];
    await p.addInitScript(() => localStorage.setItem('access_token', '__q_strn|qa'));
    await p.route('**/api/**', (r) => {
      const u = new URL(r.request().url());
      let data = {};
      if (u.pathname.endsWith('/me'))
        data = {
          id: 'qa',
          email: 'qa@example.invalid',
          is_active: true,
          roles: [{ name: 'Super Admin' }],
          permissions: [],
        };
      else if (u.pathname.endsWith('/messaging/branches')) data = { data: branches };
      else if (u.pathname.endsWith('/messaging/recipients')) {
        calls.push(Object.fromEntries(u.searchParams));
        data = {
          data: [
            {
              id: '33333333-3333-4333-8333-333333333333',
              name: 'María López',
              identifier: 'CLI-001',
              branch: 'Carrillo',
            },
          ],
        };
      }
      return r.fulfill({ json: data });
    });
    await p.goto(`${process.env.UI_BASE_URL || 'http://127.0.0.1:9001'}/#/mensajes/enviar`);
    const cliente = p.getByRole('combobox', { name: 'Cliente', exact: true });
    await cliente.waitFor();
    assert(await cliente.isDisabled());
    await p.getByRole('combobox', { name: 'Sucursal', exact: true }).click();
    await p.getByRole('option').filter({ hasText: 'Carrillo' }).click();
    await cliente.fill('m');
    await p.getByRole('option').filter({ hasText: 'María López' }).click();
    await p.getByText('Para:', { exact: true }).waitFor();
    assert.equal(calls[0].search, 'm');
    assert.equal(calls[0].branch_id, branches[0].id);
    assert.equal(calls[0].match, 'prefix');
    await p.getByRole('combobox', { name: 'Sucursal', exact: true }).click();
    await p.getByRole('option').filter({ hasText: 'Tulum' }).click();
    assert(await p.getByRole('button', { name: 'Revisar y enviar' }).isDisabled());
    assert.equal(await p.getByText('Para:', { exact: true }).count(), 0);
    console.log(
      'OK: sucursal obligatoria, autocompletado con m, filtro remoto y limpieza al cambiar sucursal.',
    );
  } finally {
    await b.close();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
