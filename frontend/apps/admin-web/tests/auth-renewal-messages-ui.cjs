const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    let meCount = 0,
      changed = false,
      wrong = true;
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    const profile = {
      id: '11111111-1111-4111-8111-111111111111',
      name: 'QA Administrador',
      email: 'qa@example.invalid',
      is_active: true,
      roles: [{ id: 1, name: 'Super Admin' }],
      permissions: ['messages.send'],
      password_change_required: true,
    };
    await page.addInitScript(() => localStorage.setItem('access_token', '__q_strn|qa-initial'));
    await page.route('**/api/**', async (r) => {
      const req = r.request(),
        p = new URL(req.url()).pathname;
      if (p.endsWith('/dashboard'))
        return r.fulfill({
          json: {
            as_of: '2026-10-30T19:00:00Z',
            business_date: '2026-10-30',
            scope: 'Prueba',
            groups: 0,
            in_review: 0,
            active_credits: 0,
            portfolio: null,
            payments: null,
            inbox: [],
          },
        });
      if (p.endsWith('/me')) {
        meCount++;
        return r.fulfill({ json: { ...profile, password_change_required: !changed } });
      }
      if (p.endsWith('/password')) {
        assert.equal(req.headers().authorization, 'Bearer qa-initial');
        assert.equal(req.postDataJSON().current_password, ' actual123 ');
        if (wrong) {
          wrong = false;
          return r.fulfill({
            status: 422,
            json: { errors: { current_password: ['La contraseña actual no es correcta.'] } },
          });
        }
        changed = true;
        return r.fulfill({
          json: {
            token: 'qa-renewed',
            user: { ...profile, password_change_required: false },
            roles: ['Super Admin'],
          },
        });
      }
      if (p.endsWith('/messaging/recipients'))
        return r.fulfill({
          json: {
            data: [
              {
                id: '22222222-2222-4222-8222-222222222222',
                name: 'Cliente QA',
                identifier: 'CLI-QA-001',
                branch: 'Prueba',
              },
            ],
          },
        });
      if (p.endsWith('/messaging/send')) {
        assert.equal(req.postDataJSON().recipient_id, '22222222-2222-4222-8222-222222222222');
        return r.fulfill({ status: 201, json: { id: 'sent', status: 'queued' } });
      }
      if (p.endsWith('/messages'))
        return r.fulfill({
          json: {
            data: [
              {
                id: '33333333-3333-4333-8333-333333333333',
                title: 'Mensaje QA',
                body: 'Texto <script>alert(1)</script>',
                created_at: '2026-10-30 19:00:00',
                read_at: null,
              },
            ],
            has_more: false,
          },
        });
      if (p.endsWith('/read')) return r.fulfill({ status: 204 });
      return r.fulfill({ json: { data: [], stats: {} } });
    });
    await page.goto('http://localhost:9001/#/cambiar-contrasena');
    await page.getByText('qa@example.invalid', { exact: true }).waitFor();
    await page.getByLabel('Contraseña actual', { exact: true }).fill(' actual123 ');
    await page.getByLabel('Nueva contraseña', { exact: true }).fill('NuevaClave123456');
    await page.getByLabel('Confirma la nueva contraseña', { exact: true }).fill('NuevaClave123456');
    await page.getByText('Mostrar contraseñas', { exact: true }).click();
    assert.equal(
      await page.getByLabel('Contraseña actual', { exact: true }).getAttribute('type'),
      'text',
    );
    await page.getByRole('button', { name: 'Guardar y continuar', exact: true }).click();
    await page
      .getByRole('alert')
      .filter({ hasText: 'La contraseña actual no es correcta.' })
      .waitFor();
    assert.equal(
      await page.getByLabel('Contraseña actual', { exact: true }).getAttribute('type'),
      'password',
    );
    await page.screenshot({ path: '/tmp/crece-password-renewal.png', fullPage: true });
    await page.getByRole('button', { name: 'Guardar y continuar', exact: true }).click();
    await page.waitForURL('**/#/');
    assert.equal(meCount, 1);
    await page.goto('http://localhost:9001/#/mensajes');
    await page.getByRole('heading', { name: 'Mensaje QA', exact: true }).waitFor();
    await page.getByText('Texto <script>alert(1)</script>', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Marcar leído' }).click();
    await page.getByRole('button', { name: 'Marcar leído' }).waitFor({ state: 'detached' });
    await page.goto('http://localhost:9001/#/mensajes/enviar');
    await page.getByLabel('Buscar por nombre, folio o correo', { exact: true }).fill('Cliente');
    await page.getByText('Cliente QA', { exact: true }).click();
    await page.getByLabel('Asunto', { exact: true }).fill('Aviso de prueba');
    await page.getByLabel('Mensaje', { exact: true }).fill('Acude a sucursal.');
    await page.getByRole('button', { name: 'Revisar y enviar' }).click();
    await page.getByRole('button', { name: 'Enviar mensaje', exact: true }).click();
    await page
      .getByText(/Guardado en la bandeja|Mensaje guardado|Mensaje registrado/i)
      .first()
      .waitFor();
    assert.deepEqual(errors, []);
    console.log(
      'OK: cuenta visible, contraseña revelable, error recuperable, renovación sin segunda consulta, mensajes escapados, lectura y envío confirmado.',
    );
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
