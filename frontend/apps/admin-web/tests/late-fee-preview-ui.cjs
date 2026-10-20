const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const origin = process.env.APP_URL || 'http://localhost:9012';
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1366, height: 1000 } });
    const errors = [];
    let writes = 0;
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => localStorage.setItem('access_token', '__q_strn|late-fee-test'));
    const id = '11111111-1111-4111-8111-111111111111';
    const member = (client_id, name) => ({ client_id, name, folio: `CLI-${client_id}`, role_in_group: 'integrante', principal: '10000.00', interest: '2800.00', weekly_payment: '800.00', guarantee: '1000.00', net_delivery: '9000.00', total_repayment: '12800.00', paid: '0.00', balance: '12800.00', due: '1600.00', overdue: '1600.00', days_overdue: 8 });
    const statement = {
      credit: { id, folio: 'CRE-DEMO-00001', status: 'entregado', group: 'Las Flores', branch: 'Carrillo', disbursed_on: '2026-10-01', first_due_on: '2026-10-10', repayment_method: 'cash_branch', rate_name: 'Cobre', rate_factor: '80' },
      as_of: '2026-10-18', generated_at_local: '2026-10-18 09:00:00', members: [member('maria', 'María López'), member('ana', 'Ana Pérez')],
      installments: [], payments: [], guarantees: [],
      late_fee_preview: { mode: 'simulation', policy: 'weekly-started-10-percent-v1', weekly_percent: 10, as_of: '2026-10-18', total: '240.00', installments: [
        { client_id: 'maria', number: 1, due_on: '2026-10-10', amount: '160.00', charges: [{ week: 1, charged_on: '2026-10-11', base: '800.00', amount: '80.00' }, { week: 2, charged_on: '2026-10-18', base: '800.00', amount: '80.00' }] },
        { client_id: 'ana', number: 1, due_on: '2026-10-10', amount: '80.00', charges: [{ week: 1, charged_on: '2026-10-11', base: '800.00', amount: '80.00' }] },
      ] },
    };
    await page.route('**/api/**', async route => {
      if (route.request().method() !== 'GET') writes++;
      const path = new URL(route.request().url()).pathname;
      const data = path.endsWith('/me') ? { id, name: 'Gerente de prueba', is_active: true, roles: [{ name: 'Super Admin' }], permissions: ['credit.view'] } : path.endsWith('/statement') ? statement : {};
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(data) });
    });
    await page.goto(`${origin}/#/creditos/${id}/estado-de-cuenta`);
    const panel = page.getByRole('region', { name: 'Vista previa de mora' });
    await panel.waitFor();
    assert.match(await panel.innerText(), /NO COBRABLE/);
    assert.match(await panel.locator('.late-fee-total').innerText(), /240\.00/);
    await panel.locator('summary').click();
    assert.equal(await panel.locator('tbody tr').count(), 3);
    await panel.screenshot({ path: '/tmp/crece-late-fee-desktop.png' });
    await page.goto(`${origin}/#/creditos/${id}/estado-de-cuenta?cliente=maria`);
    await page.waitForFunction(() => document.querySelector('.late-fee-total')?.textContent.includes('160.00'));
    assert.equal(await panel.locator('tbody tr').count(), 2);
    await page.reload();
    await panel.waitFor();
    assert.match(await panel.locator('.late-fee-total').innerText(), /160\.00/);
    await page.setViewportSize({ width: 390, height: 844 });
    await panel.locator('summary').click();
    await panel.screenshot({ path: '/tmp/crece-late-fee-mobile.png' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    assert.equal(writes, 0);
    assert.deepEqual(errors, []);
    console.log('OK: simulación separada, desglose semanal, filtro individual, recarga y móvil; sin escrituras, API simulada.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
