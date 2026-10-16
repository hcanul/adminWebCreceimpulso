const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.setDefaultTimeout(12000);
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    const branch = { id: '11111111-1111-4111-8111-111111111111', name: 'Sucursal DEMO' };
    const payments = [
      { id: '33333333-3333-4333-8333-333333333333', credit_folio: 'CRE-S008-000001', number: 1, received_on: '2026-10-15', method: 'cash_branch', amount: '500.00', bank_name: null, bank_reference: null, deposited_on: null },
      { id: '44444444-4444-4444-8444-444444444444', credit_folio: 'CRE-S008-000002', number: 1, received_on: '2026-10-15', method: 'bank_deposit', amount: '1200.25', bank_name: 'Banco DEMO', bank_reference: 'DEPOSITO-001', deposited_on: '2026-10-14' },
    ];
    const preview = { cash_total: '500.00', bank_total: '1200.25', payment_count: 2, version: 'a'.repeat(64), payments: { data: payments, current_page: 1, last_page: 1, total: 2 } };
    let record = null, firstInput, posts = 0, role = 'Caja';
    await page.addInitScript(() => localStorage.setItem('access_token', '__q_strn|cash-fixture'));
    await page.route('**/api/**', async route => {
      const u = new URL(route.request().url()); let body;
      if(u.pathname.endsWith('/me')) body = { id: '66666666-6666-4666-8666-666666666666', name: role + ' DEMO', is_active: true, branch_id: branch.id, roles: [{ id: 1, name: role }], permissions: role === 'Caja' ? ['cash.view', 'cash.close'] : ['cash.view', 'cash.audit'] };
      else if(u.pathname.endsWith('/cash/options')) body = { branches: [branch], today: '2026-10-15' };
      else if(u.pathname.endsWith('/cash/preview')) { assert.equal(u.searchParams.get('branch_id'), branch.id); body = preview; }
      else if(u.pathname.endsWith('/cash/closings')) {
        if(route.request().method() === 'POST') {
          const input = route.request().postDataJSON(); posts++;
          assert.equal(input.cash_counted, '475.00'); assert.equal(input.confirmed, true); assert.equal(input.notes, 'Faltante de 25 por revisar.'); assert.equal(input.actor_id, undefined); assert.equal(input.payment_ids, undefined);
          if(posts === 1) { firstInput = input; record = { id: input.id, folio: 'COR-S008-000001', branch_id: branch.id, branch_name: branch.name, actor_name: 'Caja DEMO', business_date: input.business_date, cash_total: '500.00', bank_total: '1200.25', cash_counted: '475.00', cash_difference: '-25.00', payment_count: 2, notes: input.notes, created_at: '2026-10-15 12:00:00', closed_at_local: '2026-10-15 07:00:00' }; return route.abort('failed'); }
          assert.deepEqual(input, firstInput); body = { record, payments: preview.payments };
        } else body = { data: record ? [record] : [], current_page: 1, last_page: 1, total: record ? 1 : 0 };
      } else if(u.pathname.includes('/cash/closings/')) body = { record, payments: preview.payments };
      else throw Error('Unexpected endpoint ' + u.pathname);
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
    });
    const base = process.env.CRECE_UI_URL || 'http://localhost:9001';
    await page.goto(base + '/#/cortes');
    await page.getByRole('button', { name: 'Preparar mi corte', exact: true }).click();
    await page.getByRole('heading', { name: 'Mis cobros pendientes de corte', exact: true }).waitFor();
    await page.getByLabel('Efectivo contado para este corte (MXN)', { exact: true }).fill('475');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Cerrar mi corte', exact: true }).click();
    await page.getByText('Explica la diferencia antes de cerrar.', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Entendido', exact: true }).click();
    assert.equal(posts, 0);
    await page.getByLabel('Observaciones / explicación de diferencias', { exact: true }).fill('Faltante de 25 por revisar.');
    await page.screenshot({ path: '/tmp/crece-cash-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 }); await page.waitForTimeout(400);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: '/tmp/crece-cash-mobile.png', fullPage: true });
    await page.getByRole('button', { name: 'Cerrar mi corte', exact: true }).click();
    await page.getByRole('button', { name: 'Sí, cerrar corte', exact: true }).click();
    await page.getByRole('button', { name: 'Entendido', exact: true }).click();
    await page.getByRole('button', { name: 'Reintentar el mismo corte', exact: true }).click();
    await page.getByRole('heading', { name: 'COR-S008-000001', exact: true }).waitFor();
    await page.reload();
    await page.getByRole('heading', { name: 'COR-S008-000001', exact: true }).waitFor();
    await page.getByText('Banco DEMO · DEPOSITO-001', { exact: true }).waitFor();
    assert.equal(posts, 2);
    assert.equal(await page.getByRole('button', { name: 'Cerrar mi corte', exact: true }).count(), 0);
    role = 'Gerente'; await page.goto(base + '/#/cortes'); await page.reload();
    await page.getByText('COR-S008-000001 · Sucursal DEMO', { exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Preparar mi corte', exact: true }).count(), 0);
    assert.deepEqual(errors, []);
    console.log('OK: Caja, medios separados, explicación de diferencia, reintento exacto después de respuesta perdida, corte persistente, Gerencia solo consulta, móvil sin desbordamiento; API simulada.');
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
