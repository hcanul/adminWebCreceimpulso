const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
 const browser = await chromium.launch({channel:'chrome',headless:true});
 try {
  const page = await browser.newPage({viewport:{width:1440,height:1000}});
  page.setDefaultTimeout(12000);
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  let fail=false, restricted=false, hits=0;
  await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|dashboard-fixture'));
  await page.route('**/api/**',async route=>{
   const u=new URL(route.request().url());let body;
   if(u.pathname.endsWith('/me')) body={id:'66666666-6666-4666-8666-666666666666',name:'Gerencia Demo',is_active:true,branch_id:'11111111-1111-4111-8111-111111111111',roles:[{id:1,name:'Gerente'}],permissions:['intake.access','credit.view','credit.create','credit.propose','credit.disburse','cash.audit','cash.view']};
   else if(u.pathname.endsWith('/dashboard')){
    hits++;
    if(fail) return route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({message:'Servicio temporalmente no disponible.'})});
    body={as_of:'2026-10-15T10:30:00-05:00',business_date:'2026-10-15',scope:'Sucursal DEMO',groups:restricted?null:12,in_review:3,active_credits:8,payments:restricted?null:{count:4,total:'1700.25',cash:'500.00',bank:'1200.25',scope:'Tu sucursal'},inbox:restricted?[]:[{label:'Solicitudes con observaciones',count:2,to:'/solicitudes-credito?status=returned'}]};
   } else if(u.pathname.endsWith('/credit/applications')){assert.equal(u.searchParams.get('review_status'),'returned');body={data:[],last_page:1,total:0,current_page:1};}
   else throw Error('Unexpected endpoint '+u.pathname);
   return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});
  });
  const base=process.env.CRECE_UI_URL||'http://localhost:9001';
  await page.goto(base+'/#/');
  await page.getByText('$1,700.25',{exact:true}).waitFor();
  assert.equal(await page.locator('.metric-card').nth(0).locator('strong').textContent(),'12');
  assert.equal(await page.locator('.metric-card').nth(1).locator('strong').textContent(),'3');
  assert.equal(await page.locator('.metric-card').nth(2).locator('strong').textContent(),'8');
  await page.getByText('Solicitudes con observaciones',{exact:true}).click();
  await page.getByRole('heading',{name:'Solicitudes de crédito',exact:true}).waitFor();
  await page.getByText('Aún no hay solicitudes que coincidan.',{exact:false}).waitFor();
  await page.goto(base+'/#/');
  await page.getByText('$1,700.25',{exact:true}).waitFor();
  await page.reload();await page.getByText('$1,700.25',{exact:true}).waitFor();
  fail=true;await page.getByRole('button',{name:'Actualizar indicadores',exact:true}).click();
  await page.getByRole('button',{name:'Reintentar',exact:true}).waitFor();
  assert.equal(await page.getByText('$1,700.25',{exact:true}).count(),0);
  fail=false;await page.getByRole('button',{name:'Reintentar',exact:true}).click();
  await page.getByText('$1,700.25',{exact:true}).waitFor();
  await page.screenshot({path:'/tmp/crece-dashboard-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});await page.waitForTimeout(400);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:'/tmp/crece-dashboard-mobile.png',fullPage:true});
  restricted=true;await page.getByRole('button',{name:'Actualizar indicadores',exact:true}).click();
  await page.getByText('Sin pendientes en estas etapas',{exact:true}).waitFor();
  assert.equal(await page.getByText('Sin acceso',{exact:true}).count(),2);
  assert.ok(hits>=5);assert.deepEqual(errors,[]);
  console.log('OK: indicadores reales de API, navegación filtrada, recarga, recuperación de error sin ceros falsos, sin acceso y móvil sin desbordamiento; API simulada.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
