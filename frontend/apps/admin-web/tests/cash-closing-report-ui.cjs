const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    page.setDefaultTimeout(12000);
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    const id = '11111111-1111-4111-8111-111111111111';
    let fail = false, reads = 0;
    const payments = Array.from({length: 36}, (_, i) => ({
      id: 'payment-' + i, credit_folio: `CRE-S008-${String(i+1).padStart(6,'0')}`, number: i+1,
      received_on: '2026-10-15', method: i%2 ? 'bank_deposit' : 'cash_branch', amount: '500.00',
      bank_name: i%2 ? 'Banco DEMO' : null, bank_reference: i%2 ? 'FOLIO-'+(i+1) : null,
      deposited_on: i%2 ? '2026-10-14' : null,
    }));
    const report = {
      record: { id, folio:'COR-S008-000001', branch_id:id, branch_name:'Sucursal DEMO', actor_name:'Caja DEMO', business_date:'2026-10-15', cash_total:'9000.00', bank_total:'9000.00', cash_counted:'8975.00', cash_difference:'-25.00', payment_count:36, notes:'Faltante de 25 pendiente de revisión.', created_at:'2026-10-15 16:00:00', closed_at_local:'2026-10-15 11:00:00' },
      payments, adjustments:[{id:'reversal-1',credit_payment_id:'payment-35',credit_folio:payments[35].credit_folio,number:36,actor_name:'Gerencia DEMO',amount:'500.00',method:'bank_deposit',reason:'Aplicación a beneficiaria incorrecta.\nSe conserva el corte original. <script>alert(1)</script>',reversed_on:'2026-10-16'}],
      adjustment_totals:{cash_total:'0.00',bank_total:'500.00',count:1},generated_at_local:'2026-10-16 10:00:00',
    };
    await page.addInitScript(() => {
      localStorage.setItem('access_token','__q_strn|cash-print-fixture');
      window.printCount = 0; window.print = () => { window.printCount++; };
    });
    await page.route('**/api/**', async route => {
      assert.equal(route.request().method(),'GET','The report must never create financial operations');
      const u = new URL(route.request().url()); let body;
      if (u.pathname.endsWith('/me')) body={id,name:'Caja DEMO',is_active:true,branch_id:id,roles:[{id:1,name:'Caja'}],permissions:['cash.view']};
      else if (u.pathname.endsWith('/report')) {
        reads++;
        if(fail) return route.fulfill({status:403,contentType:'application/json',body:JSON.stringify({message:'No tienes permiso para consultar este corte.'})});
        body=report;
      } else if(u.pathname.endsWith('/cash/closings/'+id)) body={record:report.record,payments:{data:payments.slice(0,30),current_page:1,last_page:2,total:36},adjustments:{data:report.adjustments,current_page:1,last_page:1,total:1}};
      else throw Error('Unexpected endpoint '+u.pathname);
      await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});
    });
    const base=process.env.CRECE_UI_URL||'http://localhost:9001';
    await page.goto(base+'/#/cortes/'+id);
    await page.getByRole('link',{name:'Reporte para imprimir',exact:true}).click();
    await page.waitForURL('**/imprimir');
    await page.locator('.report-table tbody tr').nth(35).waitFor();
    await page.getByRole('heading',{name:report.record.folio,exact:true}).waitFor();
    assert.equal(await page.locator('.report-table tbody tr').count(),36);
    await page.getByText('FOLIO-36',{exact:false}).waitFor();
    await page.getByText('Revertido posteriormente',{exact:true}).waitFor();
    assert.equal(await page.locator('.adjustment script').count(),0);
    await page.getByRole('button',{name:'Imprimir o guardar PDF',exact:true}).click();
    assert.equal(await page.evaluate(()=>window.printCount),1);
    assert.equal(await page.locator('vite-error-overlay').count(),0);
    assert.equal(await page.getByText('[vue-tsc]',{exact:false}).count(),0);
    await page.screenshot({path:'/tmp/crece-cut-report-desktop.png',fullPage:true});
    await page.setViewportSize({width:390,height:844});
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
    await page.screenshot({path:'/tmp/crece-cut-report-mobile.png',fullPage:true});
    await page.emulateMedia({media:'print'});
    assert.equal(await page.locator('.report-actions').isVisible(),false);
    await page.pdf({path:'/tmp/crece-cut-report.pdf',preferCSSPageSize:true,printBackground:true});
    await page.emulateMedia({media:'screen'});
    fail=true;
    await page.getByRole('button',{name:'Actualizar reporte',exact:true}).click();
    await page.getByRole('alert').waitFor();
    assert.equal(await page.locator('.report-paper').count(),0);
    assert.equal(await page.getByRole('button',{name:'Imprimir o guardar PDF',exact:true}).isDisabled(),true);
    fail=false;
    await page.getByRole('button',{name:'Reintentar',exact:true}).click();
    await page.getByRole('heading',{name:report.record.folio,exact:true}).waitFor();
    await page.reload();
    await page.getByRole('heading',{name:report.record.folio,exact:true}).waitFor();
    assert.equal(await page.locator('.report-table tbody tr').count(),36);
    assert.equal(reads,4); assert.deepEqual(errors,[]);
    console.log('OK: 36 cobros, reversos, texto escapado, impresión, error sin datos obsoletos, reintento, recarga y móvil; API simulada.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
