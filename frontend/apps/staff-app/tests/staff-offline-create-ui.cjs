const { chromium } = require(process.env.PLAYWRIGHT_MODULE);
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const p = await browser.newPage();
    p.setDefaultTimeout(20000);
    const branch = '11111111-1111-4111-8111-111111111111',
      actor = '22222222-2222-4222-8222-222222222222';
    const requests = [];
    let loseReply = true,
      saved;
    await p.addInitScript(() => sessionStorage.setItem('crece.staff.session', 'qa'));
    await p.route('**/api/**', async (r) => {
      const url = new URL(r.request().url()).pathname;
      let data = {};
      if (url.endsWith('/me'))
        data = {
          id: actor,
          branch_id: branch,
          name: 'Promotor QA',
          is_active: true,
          roles: [{ name: 'Promotor' }],
          permissions: ['intake.access'],
        };
      else if (url.endsWith('/intake/options'))
        data = {
          branches: [{ id: branch, name: 'Carrillo' }],
          promoters: [{ id: actor, name: 'Asesor QA', branch_id: branch }],
          promoter_candidates: [],
        };
      else if (url.endsWith('/offline/teams')) {
        const body = r.request().postDataJSON();
        requests.push({ kind: 'group', body });
        data = { ...body, is_active: true };
      } else if (url.endsWith('/offline/clients')) {
        const body = r.request().postDataJSON();
        requests.push({ kind: 'client', body });
        assert.equal(body.team_id, requests[0].body.id);
        saved ??= {
          ...body,
          client_id: body.proposed_client_id,
          version: 1,
          client: { id: body.proposed_client_id, version: 1, folio: 'QA-1' },
          fields: body.fields,
        };
        if (loseReply) {
          loseReply = false;
          await r.abort('connectionreset');
          return;
        }
        data = saved;
      } else {
        return r.fulfill({ status: 404, json: {} });
      }
      await r.fulfill({ json: data });
    });
    await p.goto('http://127.0.0.1:9014/#/sin-conexion');
    await p.getByLabel('Clave local', { exact: true }).fill('ClaveLocal2026');
    await p
      .getByLabel('Repite la clave local para un espacio nuevo', { exact: true })
      .fill('ClaveLocal2026');
    await p
      .getByRole('button', { name: 'Preparar espacio de trabajo con conexión', exact: true })
      .click();
    await p.getByRole('heading', { name: 'Nuevo grupo sin conexión' }).waitFor();
    await p.context().setOffline(true);
    await p.getByLabel('Nombre del nuevo grupo', { exact: true }).fill('Grupo offline QA');
    await p.getByRole('combobox', { name: 'Sucursal del nuevo grupo', exact: true }).click();
    await p.getByRole('option').filter({ hasText: 'Carrillo' }).click();
    await p.getByRole('combobox', { name: 'Promotor del nuevo grupo', exact: true }).click();
    await p.getByRole('option').filter({ hasText: 'Asesor QA' }).click();
    await p.getByRole('button', { name: 'Guardar grupo en dispositivo' }).click();
    await p.getByRole('heading', { name: 'Grupo offline QA', exact: true }).waitFor();
    assert.equal(requests.length, 0);
    await p.getByRole('combobox', { name: 'Grupo del nuevo integrante', exact: true }).click();
    await p.getByRole('option').filter({ hasText: 'Grupo offline QA' }).click();
    await p.getByLabel('CURP del nuevo integrante', { exact: true }).fill('GODE561231HDFRRN09');
    await p.getByLabel('Nombre del nuevo integrante', { exact: true }).fill('María');
    await p.getByLabel('Apellido paterno del nuevo integrante', { exact: true }).fill('Prueba');
    await p.getByRole('button', { name: 'Guardar integrante en dispositivo' }).click();
    await p.getByRole('heading', { name: 'María Prueba', exact: true }).waitFor();
    assert.equal(requests.length, 0);
    await p.context().setOffline(false);
    await p.getByRole('button', { name: 'Sincronizar pendientes', exact: true }).click();
    await p.getByRole('button', { name: 'Sincronizar pendientes', exact: true }).isEnabled();
    await p.waitForFunction(() => !document.querySelector('.q-btn--loading'));
    await p.getByRole('button', { name: 'Sincronizar pendientes', exact: true }).click();
    await p.getByText('Grupo offline QA · Sincronizado', { exact: true }).waitFor();
    assert.deepEqual(
      requests.map((r) => r.kind),
      ['group', 'client', 'client'],
    );
    assert.equal(requests[1].body.proposed_client_id, requests[2].body.proposed_client_id);
    assert.equal(requests[1].body.id, requests[2].body.id);
    console.log(
      'OK: grupo e integrante creados sin red, grupo enviado primero y reintento de alta con UUIDs conservados.',
    );
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
