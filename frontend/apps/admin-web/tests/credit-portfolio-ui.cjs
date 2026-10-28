const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
 const browser = await chromium.launch({channel:'chrome',headless:true});
 try {
  const page = await browser.newPage({viewport:{width:1440,height:1000}});
  const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  let fail=false;
  const requests=[];
  await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|portfolio-test'));
  await page.route('**/api/**',async route=>{
   assert.equal(route.request().method(),'GET');
   const u=new URL(route.request().url()); let body;
   if(u.pathname.endsWith('/me')) body={id:'user',name:'Gerente DEMO',is_active:true,roles:[{name:'Gerente'}],permissions:['credit.view']};
   else if(u.pathname.endsWith('/portfolio/export')) {
    assert.equal(u.searchParams.has('page'),false);
    assert.equal(u.searchParams.get('search'),'Flores');
    assert.equal(u.searchParams.get('status'),'overdue');
    return route.fulfill({status:200,contentType:'text/csv; charset=UTF-8',body:'Folio,Grupo\r\nCRE-DEMO-1,Las Flores\r\n'});
   }
   else if(u.pathname.endsWith('/portfolio')) {
    requests.push(u.searchParams.toString());
    if(fail) return route.fulfill({status:503,contentType:'application/json',body:'{"message":"Consulta no disponible"}'});
    const p=Number(u.searchParams.get('page')||1), empty=u.searchParams.get('search')==='Sin resultados';
    body={as_of:'2026-10-16',summary:{credits:empty?0:26,overdue_credits:empty?0:26,installment_balance:empty?'0.00':'27984.00',late_fee_balance:empty?'0.00':'176.00',balance:empty?'0.00':'28160.00',due:'1760.00',overdue:'1260.00',due_today:'500.00'},data:empty?[]:[{id:'11111111-1111-4111-8111-111111111111',folio:'CRE-DEMO-'+p,group:'Las Flores DEMO',branch:'Carrillo DEMO',installment_balance:'27984.00',late_fee_balance:'176.00',balance:'28160.00',due:'1760.00',overdue:'1260.00',due_today:'500.00',days_overdue:1,next_due:'2026-10-21',oldest_due:'2026-10-15'}],page:p,last_page:empty?1:2,total:empty?0:26};
   } else throw Error('Unexpected '+u.pathname);
   await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});
  });
  await page.goto((process.env.CRECE_UI_URL||'http://localhost:9012')+'/#/cartera');
  await page.getByText('CRE-DEMO-1',{exact:true}).waitFor();
  await page.getByRole('button',{name:'Siguiente',exact:true}).click();
  await page.getByText('CRE-DEMO-2',{exact:true}).waitFor();
  assert.match(await page.locator('.metrics').innerText(),/28,160.00/);
  assert.match(await page.locator('.metrics').innerText(),/176.00/);
  assert.match(await page.locator('tbody').innerText(),/27,984.00.*176.00.*28,160.00/s);
  const search=page.getByLabel('Buscar folio, grupo o sucursal',{exact:true});
  await search.fill('Flores');
  await page.waitForURL('**search=Flores');
  await page.getByText('CRE-DEMO-1',{exact:true}).waitFor();
  await page.reload(); await page.getByText('CRE-DEMO-1',{exact:true}).waitFor();
  assert.equal(await search.inputValue(),'Flores');
  const filter=page.getByLabel('Situación del crédito',{exact:true});
  await filter.click(); await filter.fill('atraso'); await page.getByRole('option',{name:'Con atraso (cuotas o mora)',exact:true}).click();
  await page.waitForURL('**status=overdue');
  await page.getByText('CRE-DEMO-1',{exact:true}).waitFor();
  assert.match(requests.at(-1),/status=overdue/);
  const downloaded=page.waitForEvent('download');
  await page.getByRole('button',{name:'Descargar CSV',exact:true}).click();
  const download=await downloaded;
  assert.equal(download.suggestedFilename(),'cartera-y-vencimientos.csv');
  assert.equal(await download.failure(),null);
  await page.screenshot({path:'/tmp/crece-portfolio-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth);
  await page.screenshot({path:'/tmp/crece-portfolio-mobile.png',fullPage:true});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:'/tmp/crece-portfolio-mobile.png',fullPage:true});
  fail=true; await page.getByRole('button',{name:'Actualizar',exact:true}).click();
  await page.getByRole('alert').waitFor();
  assert.equal(await page.locator('.metrics').count(),0);
  fail=false; await page.getByRole('button',{name:'Reintentar',exact:true}).click();
  await page.getByText('CRE-DEMO-1',{exact:true}).waitFor();
  await search.fill('Sin resultados'); await page.getByText('No hay créditos que coincidan con esta búsqueda.',{exact:true}).waitFor();
  assert.equal(await page.getByRole('table').count(),0);
  assert.deepEqual(errors,[]);
  console.log('OK: búsqueda, filtros, paginación, recarga, móvil, errores sin datos anteriores y reintento (API simulada).');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
