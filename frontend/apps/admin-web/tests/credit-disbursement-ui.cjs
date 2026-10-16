const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  page.setDefaultTimeout(12000);
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const base = process.env.CRECE_UI_URL || 'http://localhost:9001';
  const id = '22222222-2222-4222-8222-222222222222',
    branch = { id: '11111111-1111-4111-8111-111111111111', name: 'Carrillo' };
  const money = (n) => n.toFixed(2),
    estimate = (p) => ({
      principal: money(p),
      weekly_payment: money(p * 0.08),
      total_repayment: money(p * 0.08 * 16),
      interest: money(p * 0.08 * 16 - p),
      guarantee: money(p * 0.1),
      net_delivery: money(p * 0.9),
    });
  const members = ['María López', 'Ana Pérez', 'Rosa Díaz'].map((name, i) => ({
    client_id: `55555555-5555-4555-8555-55555555555${i}`,
    name,
    folio: `CLI-S001-00000${i + 1}`,
    role_in_group: ['presidenta', 'tesorera', 'integrante'][i],
    amount_requested: '10000.00',
    amount_approved: '10000.00',
    estimate: estimate(10000),
  }));
  const terms = {
    weeks: 16,
    guarantee_percent: 10,
    member_limit: '12000.00',
    rate: { id: '44444444-4444-4444-8444-444444444444', name: 'Cobre', factor: '80.0000' },
    members,
    totals: estimate(30000),
    disbursement_method: 'bank_deposit',
    repayment_method: 'cash_branch',
  };
  const loan = {
    id,
    folio: 'CRE-S001-000001',
    status: 'aprobado',
    review_status: 'ready_for_authorization',
    authorization_status: 'authorized',
    authorization_version: 2,
    total_requested: '30000.00',
    total_approved: '30000.00',
    request_snapshot: terms,
    approved_snapshot: terms,
    disbursement_method: 'bank_deposit',
    repayment_method: 'cash_branch',
    team: { id: '33333333-3333-4333-8333-333333333333', name: 'Las Flores' },
    branch,
  };
  const data = { record: null, installments: [] };
  let posts = 0;
  const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Cancun',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date());
  const first = new Date(today + 'T00:00:00Z');
  first.setUTCDate(first.getUTCDate() + 7);
  const firstDue = first.toISOString().slice(0, 10);
  terms.first_due_on = firstDue;
  try {
    await page.addInitScript(() =>
      localStorage.setItem('access_token', '__q_strn|disbursement-fixture'),
    );
    await page.route('**/api/**', async (route) => {
      const u = new URL(route.request().url());
      let body;
      if (u.pathname.endsWith('/me'))
        body = {
          id: '66666666-6666-4666-8666-666666666666',
          name: 'Caja de prueba',
          is_active: true,
          branch_id: branch.id,
          roles: [{ id: 1, name: 'Caja' }],
          permissions: ['credit.view', 'credit.disburse'],
        };
      else if (u.pathname.endsWith('/disbursement')) {
        if (route.request().method() === 'POST') {
          const input = route.request().postDataJSON();
          assert.equal(input.authorization_version, 2);
          assert.equal(input.confirmed, true);
          assert.equal(input.bank_name, 'Banco de prueba');
          assert.equal(input.bank_reference, 'ENTREGA-001');
          assert.equal(input.first_due_on, firstDue);
          assert.equal(input.disbursed_on, today);
          assert.equal(input.net_delivered, undefined);
          assert.equal(posts++, 0);
          data.record = {
            id: input.id,
            actor_name: 'Caja de prueba',
            disbursed_on: today,
            first_due_on: firstDue,
            method: 'bank_deposit',
            bank_name: input.bank_name,
            bank_reference: input.bank_reference,
            capital: '30000.00',
            guarantee_retained: '3000.00',
            net_delivered: '27000.00',
            notes: input.notes,
            snapshot: terms,
          };
          for (let i = 0; i < 16; i++) {
            const date = new Date(first);
            date.setUTCDate(date.getUTCDate() + 7 * i);
            for (const m of members)
              data.installments.push({
                id: `${i}-${m.client_id}`,
                client_id: m.client_id,
                number: i + 1,
                due_on: date.toISOString().slice(0, 10),
                amount_due: '800.00',
                amount_paid: '0.00',
              });
          }
          loan.status = 'entregado';
        }
        body = data;
      } else if (u.pathname.endsWith('/credit/applications/' + id)) body = loan;
      else if (u.pathname.endsWith('/credit/applications')) {
        assert.ok(
          ['aprobado', 'entregado', 'pagado'].includes(u.searchParams.get('financial_status')),
        );
        body = { data: [loan], last_page: 1, current_page: 1, total: 1 };
      } else return route.fulfill({ status: 500, body: 'Unexpected mock endpoint' });
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(body),
      });
    });
    await page.goto(base + '/#/desembolsos');
    await page.getByRole('heading', { name: 'Desembolsos y calendario', exact: true }).waitFor();
    assert.equal(await page.getByRole('link', { name: 'Clientes', exact: true }).count(), 0);
    await page.getByText('CRE-S001-000001 · Las Flores', { exact: true }).click();
    await page.getByRole('heading', { name: 'Desembolso del crédito', exact: true }).waitFor();
    assert.equal(
      await page
        .getByRole('button', { name: 'Registrar entrega realizada', exact: true })
        .isDisabled(),
      true,
    );
    assert.equal(await page.getByLabel('Fecha del primer pago', { exact: true }).inputValue(), firstDue);
    assert.equal(await page.getByLabel('Fecha del primer pago', { exact: true }).isEditable(), false);
    await page.getByLabel('Banco de la entrega', { exact: true }).fill('Banco de prueba');
    await page.getByLabel('Referencia de la entrega bancaria', { exact: true }).fill('ENTREGA-001');
    await page
      .getByLabel('Observaciones de la entrega', { exact: true })
      .fill('Entrega cotejada con el recibo firmado.');
    await page.getByRole('checkbox').check();
    await page.locator('.schedule-table tbody tr').last().waitFor();
    assert.equal(await page.locator('.schedule-table tbody tr').count(), 16);
    await page.waitForTimeout(400);
    await page.screenshot({ path: '/tmp/crece-disbursement-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(400);
    assert.equal(
      await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),
      false,
    );
    await page.screenshot({ path: '/tmp/crece-disbursement-mobile.png', fullPage: true });
    await page.getByRole('button', { name: 'Registrar entrega realizada', exact: true }).click();
    await page.getByRole('button', { name: 'Sí, registrar entrega', exact: true }).click();
    await page.getByRole('heading', { name: 'Calendario registrado', exact: true }).waitFor();
    await page.reload();
    await page.getByRole('heading', { name: 'Calendario registrado', exact: true }).waitFor();
    assert.equal(await page.locator('.schedule-table tbody tr').count(), 16);
    assert.equal(
      await page.getByRole('button', { name: 'Registrar entrega realizada', exact: true }).count(),
      0,
    );
    assert.equal(posts, 1);
    assert.deepEqual(errors, []);
    console.log(
      'OK: Caja sin acceso a captura, entrega bancaria, confirmación, 16 semanas, recarga persistente y móvil sin desbordamiento; API simulada.',
    );
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
