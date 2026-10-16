const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.setDefaultTimeout(12000);
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    const id = '11111111-1111-4111-8111-111111111111';
    const ids = ['22222222-2222-4222-8222-222222222222','33333333-3333-4333-8333-333333333333','44444444-4444-4444-8444-444444444444'];
    let fail = false, role = 'Gerente';
    const members = ['Alfa DEMO', 'Beta DEMO', 'Gama DEMO'].map((name,i) => {
      const principal = [10000,8000,5000][i], weekly = principal*.08, total = weekly*16, paid = i===0 ? 500 : 0;
      return {client_id:ids[i],name,folio:`CLI-S008-00000${i+1}`,role_in_group:['presidenta','tesorera','integrante'][i],principal:principal.toFixed(2),interest:(total-principal).toFixed(2),weekly_payment:weekly.toFixed(2),guarantee:(principal*.1).toFixed(2),net_delivery:(principal*.9).toFixed(2),total_repayment:total.toFixed(2),paid:paid.toFixed(2),balance:(total-paid).toFixed(2),due:(weekly-paid).toFixed(2),overdue:(weekly-paid).toFixed(2),days_overdue:1};
    });
    const data = {
      credit:{id,folio:'CRE-S008-000001',status:'entregado',group:'Las Semillas DEMO',branch:'Sucursal DEMO',disbursed_on:'2026-10-07',first_due_on:'2026-10-14',repayment_method:'cash_branch',rate_name:'Cobre',rate_factor:'80.0000'},
      as_of:'2026-10-15',generated_at_local:'2026-10-15 10:00:00',members,
      installments:members.flatMap((m,i) => Array.from({length:16},(_,n) => ({client_id:m.client_id,number:n+1,due_on:new Date(Date.UTC(2026,9,14+n*7)).toISOString().slice(0,10),amount_due:m.weekly_payment,amount_paid:i===0 && n===0 ? '500.00' : '0.00'}))),
      payments:[
        {id:'payment-one',number:1,folio:'REC-CRE-S008-000001-0001',received_on:'2026-10-14',method:'cash_branch',amount:'500.00',reversed_on:null,allocations:[{client_id:ids[0],payer_client_id:ids[1],amount:'500.00',number:1,due_on:'2026-10-14'}]},
        {id:'payment-two',number:2,folio:'REC-CRE-S008-000001-0002',received_on:'2026-10-14',method:'cash_branch',amount:'200.00',reversed_on:'2026-10-15',allocations:[{client_id:ids[0],payer_client_id:ids[0],amount:'200.00',number:1,due_on:'2026-10-14'}]},
      ],
    };
    await page.addInitScript(() => {localStorage.setItem('access_token','__q_strn|statement-fixture');window.printCount=0;window.print=()=>{window.printCount++;};});
    await page.route('**/api/**', async route => {
      assert.equal(route.request().method(),'GET');
      const path = new URL(route.request().url()).pathname;
      let body;
      if(path.endsWith('/me')) body={id,name:role+' DEMO',is_active:true,branch_id:id,roles:[{id:1,name:role}],permissions:role==='Gerente'?['credit.view','credit.reverse']:['credit.view']};
      else if(path.endsWith('/statement')) {
        if(fail) return route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({message:'Consulta temporalmente no disponible.'})});
        body=data;
      } else throw Error('Unexpected endpoint '+path);
      await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});
    });
    const base=process.env.CRECE_UI_URL||'http://localhost:9001';
    await page.goto(base+'/#/creditos/'+id+'/estado-de-cuenta');
    await page.getByRole('table',{name:'Calendario de pagos',exact:true}).waitFor();
    assert.equal(await page.getByRole('table',{name:'Calendario de pagos',exact:true}).locator('tbody tr').count(),16);
    assert.equal(await page.getByRole('table',{name:'Saldo por integrante',exact:true}).locator('tbody tr').count(),3);
    assert.match(await page.locator('.balance-grid').innerText(),/28,940\.00/);
    assert.match(await page.locator('.balance-grid').innerText(),/1,340\.00/);
    assert.equal(await page.getByRole('link',{name:'Ver comprobante QR',exact:true}).count(),2);
    const selector=page.getByLabel('Consultar grupo o integrante',{exact:true});
    await selector.fill('Alfa'); await page.getByRole('option',{name:'Alfa DEMO · CLI-S008-000001',exact:true}).click();
    await page.waitForURL('**cliente='+ids[0]);
    assert.equal(await page.getByRole('table',{name:'Saldo por integrante',exact:true}).count(),0);
    assert.match(await page.locator('.balance-grid').innerText(),/12,300\.00/);
    assert.equal(await page.locator('.payment-entry').count(),2);
    await page.getByText('PAGO REVERTIDO · 2026-10-15',{exact:true}).waitFor();
    await page.reload();
    await page.getByRole('heading',{name:'Alfa DEMO',exact:true}).waitFor();
    await page.getByRole('button',{name:'Imprimir o guardar PDF',exact:true}).click();
    assert.equal(await page.evaluate(()=>window.printCount),1);
    assert.equal(await page.locator('vite-error-overlay').count(),0);
    assert.equal(await page.getByText('[vue-tsc]',{exact:false}).count(),0);
    await page.screenshot({path:'/tmp/crece-statement-desktop.png',fullPage:true});
    await page.setViewportSize({width:390,height:844});
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
    await page.screenshot({path:'/tmp/crece-statement-mobile.png',fullPage:true});
    await page.emulateMedia({media:'print'});
    assert.equal(await page.locator('.statement-controls').isVisible(),false);
    await page.pdf({path:'/tmp/crece-statement.pdf',preferCSSPageSize:true,printBackground:true});
    await page.emulateMedia({media:'screen'});
    await selector.fill('Beta'); await page.getByRole('option',{name:'Beta DEMO · CLI-S008-000002',exact:true}).click();
    await page.waitForURL('**cliente='+ids[1]);
    assert.equal(await page.locator('.payment-entry').count(),0,'Helping Alfa must not count as a payment to Beta');
    assert.match(await page.locator('.balance-grid').innerText(),/10,240\.00/);
    fail=true; await page.getByRole('button',{name:'Actualizar',exact:true}).click();
    await page.getByRole('alert').waitFor();
    assert.equal(await page.locator('.statement-paper').count(),0);
    assert.equal(await page.getByRole('button',{name:'Imprimir o guardar PDF',exact:true}).isDisabled(),true);
    fail=false; await page.getByRole('button',{name:'Reintentar',exact:true}).click();
    await page.getByRole('heading',{name:'Beta DEMO',exact:true}).waitFor();
    role='Promotor'; await page.reload();
    await page.getByRole('heading',{name:'Beta DEMO',exact:true}).waitFor();
    await selector.fill('Alfa'); await page.getByRole('option',{name:'Alfa DEMO · CLI-S008-000001',exact:true}).click();
    await page.waitForURL('**cliente='+ids[0]);
    assert.equal(await page.locator('.payment-entry').count(),2);
    assert.equal(await page.getByRole('link',{name:'Ver comprobante QR',exact:true}).count(),0);
    await page.goto(base+'/#/creditos/'+id+'/estado-de-cuenta?cliente=unknown');
    await page.getByRole('alert').waitFor();
    assert.equal(await page.locator('.statement-paper').count(),0);
    assert.equal(await page.getByRole('button',{name:'Imprimir o guardar PDF',exact:true}).isDisabled(),true);
    assert.deepEqual(errors,[]);
    console.log('OK: grupo, filtro individual persistente, solidaridad, reversos, 16 semanas, error sin saldos obsoletos, permisos de QR, impresión y móvil; API simulada.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
