const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.setDefaultTimeout(12000);
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    const id = '22222222-2222-4222-8222-222222222222';
    const branch = { id: '11111111-1111-4111-8111-111111111111', name: 'Sucursal DEMO' };
    const members = ['María López', 'Ana Pérez', 'Rosa Díaz'].map((name, i) => ({ client_id: `55555555-5555-4555-8555-55555555555${i}`, name, folio: `CLI-S008-00000${i+1}`, balance: '12800.00', due: '800.00', overdue: '800.00', days_overdue: 2 }));
    const loan = { id, folio: 'CRE-S008-000001', status: 'entregado', total_requested: '30000.00', total_approved: '30000.00', repayment_method: 'bank_deposit', team: { name: 'Las Semillas' }, branch };
    const data = { status: 'entregado', as_of: '2026-10-14', balance_version: 'a'.repeat(64), members, payments: { data: [], total: 0, current_page: 1, last_page: 1 } };
    let posts = 0; let firstInput;
    await page.addInitScript(() => localStorage.setItem('access_token', '__q_strn|collections-fixture'));
    await page.route('**/api/**', async route => {
      const u = new URL(route.request().url()); let body;
      if (u.pathname.endsWith('/me')) body = { id: '66666666-6666-4666-8666-666666666666', name: 'Caja DEMO', is_active: true, branch_id: branch.id, roles: [{ id: 1, name: 'Caja' }], permissions: ['credit.view', 'credit.collect'] };
      else if (u.pathname.includes('/credit/receipts/')) { const payment = data.payments.data[0]; body = { ...payment, folio: 'REC-CRE-S008-000001-0001', credit_folio: loan.folio, status: 'registered', verification_url: 'http://localhost:8000/comprobantes/' + payment.id + '/verificar?signature=' + 'a'.repeat(64), allocations: [{ payer: 'Ana Pérez', beneficiary: 'María López', amount: '500.00', number: 1, due_on: '2026-10-12' }] }; }
      else if (u.pathname.endsWith('/payments')) {
        if (route.request().method() === 'POST') {
          const input = route.request().postDataJSON(); posts++;
          assert.equal(input.amount, '500.00'); assert.equal(input.confirmed, true); assert.equal(input.bank_reference, 'DEPOSITO-001'); assert.equal(input.method, 'bank_deposit'); assert.equal(input.contributions[0].client_id, members[0].client_id); assert.equal(input.contributions[0].payer_client_id, members[1].client_id);
          if (posts === 1) { firstInput = input; return route.abort('failed'); }
          assert.deepEqual(input, firstInput);
          data.payments.data = [{ id: input.id, actor_name: 'Caja DEMO', received_on: '2026-10-14', method: input.method, bank_name: input.bank_name, bank_reference: input.bank_reference, deposited_on: input.deposited_on, notes: input.notes, amount: input.amount, allocations: [{ ...input.contributions[0], number: 1, due_on: '2026-10-12' }] }];
          data.payments.total = 1; members[0].balance = '12300.00'; members[0].overdue = '300.00'; members[0].due = '300.00'; data.balance_version = 'b'.repeat(64);
        }
        body = data;
      } else if (u.pathname.endsWith('/credit/applications/' + id)) body = loan;
      else if (u.pathname.endsWith('/credit/applications')) { assert.equal(u.searchParams.get('financial_status'), 'entregado'); body = { data: [loan], last_page: 1, current_page: 1, total: 1 }; }
      else throw Error('Unexpected mock endpoint: ' + u.pathname);
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
    });
    await page.goto((process.env.CRECE_UI_URL || 'http://localhost:9001') + '/#/cobros');
    await page.getByRole('heading', { name: 'Cobros en caja', exact: true }).waitFor();
    assert.equal(await page.getByRole('link', { name: 'Clientes', exact: true }).count(), 0);
    await page.getByText('CRE-S008-000001 · Las Semillas', { exact: true }).click();
    await page.getByRole('heading', { name: 'Registrar pago recibido', exact: true }).waitFor();
    await page.getByLabel('Total recibido (MXN)', { exact: true }).fill('500');
    await page.getByLabel('Banco del ticket', { exact: true }).fill('Banco DEMO');
    await page.getByLabel('Folio del ticket bancario', { exact: true }).fill('DEPOSITO-001');
    await page.getByLabel('Fecha del depósito', { exact: true }).fill('2026-10-14');
    await page.getByLabel('Quién aporta', { exact: true }).fill('Ana');
    await page.getByRole('option', { name: /Ana Pérez/ }).click();
    await page.getByLabel('Aplicar al crédito de', { exact: true }).fill('María');
    await page.getByRole('option', { name: /María López/ }).click();
    await page.getByLabel('Importe (MXN)', { exact: true }).fill('500');
    await page.getByRole('checkbox').check();
    await page.screenshot({ path: '/tmp/crece-collections-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(400);
    await page.screenshot({ path: '/tmp/crece-collections-mobile.png', fullPage: true });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.getByRole('button', { name: 'Confirmar cobro', exact: true }).click();
    await page.getByRole('button', { name: 'Sí, registrar cobro', exact: true }).click();
    await page.getByRole('button', { name: 'Entendido', exact: true }).click();
    await page.getByRole('button', { name: 'Reintentar el mismo cobro', exact: true }).click();
    await page.getByText('$500.00 · Depósito bancario · 2026-10-14', { exact: true }).waitFor();
    await page.reload();
    await page.getByText('$500.00 · Depósito bancario · 2026-10-14', { exact: true }).click();
    await page.getByText('Banco DEMO · Folio DEPOSITO-001 · Depósito 2026-10-14', { exact: true }).waitFor();
    assert.equal(await page.getByLabel('Total recibido (MXN)', { exact: true }).inputValue(), '');
    await page.getByRole('link', { name: 'Ver comprobante QR', exact: true }).click();
    await page.getByRole('heading', { name: 'REC-CRE-S008-000001-0001', exact: true }).waitFor();
    await page.getByRole('img', { name: 'QR para verificar este pago', exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Imprimir comprobante', exact: true }).isDisabled(), false);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: '/tmp/crece-receipt-mobile.png', fullPage: true });
    await page.setViewportSize({ width: 900, height: 1100 });
    await page.emulateMedia({ media: 'print' });
    assert.equal(await page.locator('.receipt-actions').isVisible(), false);
    await page.screenshot({ path: '/tmp/crece-receipt-print.png', fullPage: true });
    assert.equal(posts, 2); assert.deepEqual(errors, []);
    console.log('OK: Caja, aportación solidaria, reintento idéntico, historial tras recarga y móvil sin desbordamiento; API simulada.');
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
