const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1050 } });
  page.setDefaultTimeout(12000);
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const money = (n) => n.toFixed(2),
    estimate = (p) => ({
      principal: money(p),
      weekly_payment: money(p * 0.08),
      total_repayment: money(p * 0.08 * 16),
      interest: money(p * 0.08 * 16 - p),
      guarantee: money(p * 0.1),
      net_delivery: money(p * 0.9),
    });
  const branch = { id: '11111111-1111-4111-8111-111111111111', name: 'Carrillo' },
    id = '22222222-2222-4222-8222-222222222222';
  const members = ['María López', 'Ana Pérez', 'Rosa Díaz'].map((name, i) => ({
    client_id: `55555555-5555-4555-8555-55555555555${i}`,
    name,
    folio: `CLI-S001-00000${i + 1}`,
    role_in_group: ['presidenta', 'tesorera', 'integrante'][i],
    amount_requested: money([10000, 8000, 5000][i]),
    estimate: estimate([10000, 8000, 5000][i]),
  }));
  const snapshot = {
    weeks: 16,
    guarantee_percent: 10,
    member_limit: '12000.00',
    rate: { id: '44444444-4444-4444-8444-444444444444', name: 'Cobre', factor: '80.0000' },
    members,
    totals: estimate(23000),
  };
  const loan = {
    id,
    folio: 'CRE-S001-000001',
    status: 'pendiente',
    review_status: 'ready_for_authorization',
    authorization_status: 'awaiting_proposal',
    total_requested: '23000.00',
    total_approved: null,
    request_snapshot: snapshot,
    approved_snapshot: null,
    disbursement_method: null,
    repayment_method: null,
    team: { id: '33333333-3333-4333-8333-333333333333', name: 'Las Flores' },
    branch,
  };
  const data = { status: 'awaiting_proposal', version: 0, proposal: null, history: [] };
  let actor = 'Gerente';
  const mutations = [];
  const record = (action, notes) =>
    data.history.unshift({
      id: String(data.version),
      version: data.version,
      actor_name: actor,
      created_at: '2026-10-14 12:00:00',
      action,
      notes,
      proposal: structuredClone(data.proposal),
    });
  try {
    await page.addInitScript(() =>
      localStorage.setItem('access_token', '__q_strn|authorization-ui-fixture'),
    );
    await page.route('**/api/**', async (route) => {
      const u = new URL(route.request().url());
      let body;
      if (u.pathname.endsWith('/me'))
        body = {
          id: '66666666-6666-4666-8666-666666666666',
          name: actor,
          is_active: true,
          branch_id: branch.id,
          roles: [{ id: 1, name: actor }],
          permissions: [
            'intake.access',
            'credit.view',
            ...(actor === 'Gerente' ? ['credit.create', 'credit.propose'] : ['credit.authorize']),
          ],
        };
      else if (u.pathname.endsWith('/disbursement')) body = {record: null, installments: []};
      else if (u.pathname.endsWith('/review'))
        body = {
          status: 'ready_for_authorization',
          version: 1,
          checks: [],
          files: [],
          history: [],
        };
      else if (u.pathname.endsWith('/proposal')) {
        assert.equal(actor, 'Gerente');
        const p = route.request().postDataJSON();
        assert.equal(p.version, data.version);
        assert.equal(p.participants.length, 3);
        assert.equal(p.disbursement_method, 'cash_branch');
        assert.equal(p.repayment_method, 'bank_deposit');
        data.proposal = {
          review_version: 1,
          weeks: 16,
          guarantee_percent: 10,
          rate: snapshot.rate,
          members: members.map((m) => {
            const amount = p.participants.find(
              (row) => row.client_id === m.client_id,
            ).amount_proposed;
            return {
              ...m,
              amount_proposed: money(Number(amount)),
              estimate: estimate(Number(amount)),
            };
          }),
          totals: estimate(
            p.participants.reduce((sum, row) => sum + Number(row.amount_proposed), 0),
          ),
          disbursement_method: p.disbursement_method,
          repayment_method: p.repayment_method,
          proposed_by_name: actor,
          proposed_at: '2026-10-14T12:00:00Z',
        };
        data.status = 'pending_authorization';
        data.version++;
        loan.authorization_status = data.status;
        mutations.push('propose');
        record('propose', p.notes);
        body = { status: data.status, version: data.version };
      } else if (u.pathname.endsWith('/authorization')) {
        if (route.request().method() === 'POST') {
          assert.equal(actor, 'Coordinación Comercial');
          const p = route.request().postDataJSON();
          assert.equal(p.version, data.version);
          assert.equal(data.status, 'pending_authorization');
          data.status = p.decision === 'approve' ? 'authorized' : 'returned';
          data.version++;
          loan.authorization_status = data.status;
          mutations.push(p.decision);
          record(p.decision, p.notes);
          if (p.decision === 'approve') {
            loan.status = 'aprobado';
            loan.total_approved = data.proposal.totals.principal;
            assert.equal(p.first_due_on, '2028-01-08');
            loan.approved_snapshot = {...structuredClone(data.proposal), first_due_on: p.first_due_on};
            loan.approved_snapshot.members = loan.approved_snapshot.members.map((row) => {
              row.amount_approved = row.amount_proposed;
              delete row.amount_proposed;
              return row;
            });
            loan.disbursement_method = data.proposal.disbursement_method;
            loan.repayment_method = data.proposal.repayment_method;
          }
          body = { status: data.status, version: data.version };
        } else body = data;
      } else if (u.pathname.endsWith('/credit/applications/' + id)) body = loan;
      else if (u.pathname.endsWith('/credit/applications')) {
        assert.ok(
          ['awaiting_proposal', 'pending_authorization', 'returned', 'authorized'].includes(
            u.searchParams.get('authorization_status'),
          ),
        );
        body = { data: [loan], last_page: 1, current_page: 1, total: 1 };
      } else return route.fulfill({ status: 500, body: 'Unexpected mock endpoint' });
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(body),
      });
    });
    await page.goto(`${process.env.CRECE_UI_URL || 'http://localhost:9001'}/#/autorizaciones`);
    await page.getByRole('heading', { name: 'Autorización comercial', exact: true }).waitFor();
    await page.getByLabel('Estado de autorización', { exact: true }).fill('devueltas');
    await page
      .getByRole('option', { name: 'Condiciones devueltas al gerente', exact: true })
      .click();
    await page.getByText('CRE-S001-000001 · Las Flores', { exact: true }).click();
    await page.getByLabel('Importe propuesto para María López (MXN)', { exact: true }).fill('9000');
    await page.getByLabel('Forma de entregar el crédito', { exact: true }).fill('sucursal');
    await page.getByRole('option', { name: 'En sucursal', exact: true }).click();
    await page
      .getByLabel('Forma de realizar los pagos semanales', { exact: true })
      .fill('depósito');
    await page.getByRole('option', { name: 'Depósito bancario', exact: true }).click();
    await page
      .getByLabel('Observaciones de la propuesta o decisión', { exact: true })
      .fill('Propuesta inicial para el grupo.');
    assert.equal(
      await page.getByRole('button', { name: 'Autorizar crédito', exact: true }).count(),
      0,
    );
    await page
      .getByRole('button', { name: 'Enviar propuesta a Coordinación', exact: true })
      .click();
    await page.getByRole('button', { name: 'Sí, continuar', exact: true }).click();
    await page
      .getByRole('heading', { name: 'Condiciones enviadas por el gerente', exact: true })
      .waitFor();
    assert.equal(loan.total_approved, null);
    actor = 'Coordinación Comercial';
    await page.reload();
    await page.getByRole('button', { name: 'Autorizar crédito', exact: true }).waitFor();
    assert.equal(
      await page.getByLabel('Importe propuesto para María López (MXN)', { exact: true }).count(),
      0,
    );
    await page
      .getByLabel('Observaciones de la propuesta o decisión', { exact: true })
      .fill('Revisar importe de María.');
    await page
      .getByRole('button', { name: 'Devolver condiciones al gerente', exact: true })
      .click();
    await page.getByRole('button', { name: 'Sí, continuar', exact: true }).click();
    await page.getByRole('status').filter({hasText:'Condiciones devueltas al gerente'}).waitFor();
    actor = 'Gerente';
    await page.reload();
    await page.getByLabel('Importe propuesto para María López (MXN)', { exact: true }).fill('8500');
    await page
      .getByLabel('Observaciones de la propuesta o decisión', { exact: true })
      .fill('Importe ajustado para revisión.');
    await page
      .getByRole('button', { name: 'Enviar propuesta a Coordinación', exact: true })
      .click();
    await page.getByRole('button', { name: 'Sí, continuar', exact: true }).click();
    await page
      .getByRole('heading', { name: 'Condiciones enviadas por el gerente', exact: true })
      .waitFor();
    actor = 'Coordinación Comercial';
    await page.reload();
    await page.getByRole('button', { name: 'Autorizar crédito', exact: true }).waitFor();
    await page
      .getByLabel('Observaciones de la propuesta o decisión', { exact: true })
      .fill('Autorizado según condiciones propuestas.');
    await page.waitForTimeout(450);
    await page.screenshot({ path: '/tmp/crece-authorization-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(450);
    assert.equal(
      await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),
      false,
    );
    await page.screenshot({ path: '/tmp/crece-authorization-mobile.png', fullPage: true });
    assert.equal(await page.getByRole('button', { name: 'Autorizar crédito', exact: true }).isDisabled(), true);
    await page.getByLabel('Fecha pactada del primer pago', {exact:true}).fill('2028-01-08');
    assert.equal(await page.getByRole('table', {name:'Calendario pactado'}).locator('tbody tr').count(), 16);
    await page.screenshot({ path: '/tmp/crece-agreed-calendar-mobile.png', fullPage: true });
    await page.getByRole('button', { name: 'Autorizar crédito', exact: true }).click();
    await page.getByRole('button', { name: 'Sí, autorizar', exact: true }).click();
    await page.getByRole('heading', { name: 'Condiciones autorizadas', exact: true }).waitFor();
    await page.reload();
    await page.getByRole('heading', { name: 'Condiciones autorizadas', exact: true }).waitFor();
    assert.equal(loan.approved_snapshot.first_due_on, '2028-01-08');
    await page.getByText('2028-01-08', {exact:true}).first().waitFor();
    assert.equal(loan.total_approved, '21500.00');
    assert.equal(loan.total_requested, '23000.00');
    assert.equal(
      await page.getByRole('button', { name: 'Autorizar crédito', exact: true }).count(),
      0,
    );
    assert.deepEqual(mutations, ['propose', 'return', 'propose', 'approve']);
    assert.deepEqual(errors, []);
    console.log(
      'OK: gerente propone, coordinación devuelve, gerente corrige, coordinación autoriza; importes separados, recarga, permisos de UI y móvil sin desbordamiento. API simulada sin escrituras reales.',
    );
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
