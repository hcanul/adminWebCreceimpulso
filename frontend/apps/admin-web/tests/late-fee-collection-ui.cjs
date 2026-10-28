const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
(async () => {
 const browser = await chromium.launch({channel:'chrome',headless:true});
 try {
  const page = await browser.newPage({viewport:{width:1366,height:1000}});
  const errors=[]; page.on('pageerror', e=>errors.push(e.message));
  await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|fee-ui-test'));
  const id='11111111-1111-4111-8111-111111111111';
  const fee={id:'fee',client_id:'maria',number:1,due_on:'2026-10-10',charged_on:'2026-10-11',base:'800.00',amount:'80.00',paid:'0.00',balance:'80.00'};
  const member={client_id:'maria',name:'María López',folio:'CLI-DEMO-001',installment_balance:'12800.00',installment_due:'800.00',late_fee_balance:'80.00',balance:'12880.00',due:'880.00',overdue:'880.00',days_overdue:1};
  const state={balance_version:'a'.repeat(64),status:'entregado',as_of:'2026-10-11',members:[member],late_fees:{enabled:true,total:'80.00',paid:'0.00',balance:'80.00',charges:[fee]},payments:{data:[],total:0,current_page:1,last_page:1}};
  const receipt={id,folio:'REC-DEMO-0001',credit_folio:'CRE-DEMO-001',amount:'1000.00',installment_amount:'920.00',late_fee_amount:'80.00',received_on:'2026-10-11',method:'cash_branch',status:'registered',balance_version:'a'.repeat(64),reversed_on:null,reversal:null,actor_name:'Caja Demo',bank_reference:null,verification_url:'https://example.test/verify/demo',allocations:[{beneficiary:'María López',payer:'María López',number:1,due_on:'2026-10-10',amount:'800.00',kind:'installment'},{beneficiary:'María López',payer:'María López',number:1,due_on:'2026-10-10',charged_on:'2026-10-11',amount:'80.00',kind:'late_fee'},{beneficiary:'María López',payer:'María López',number:2,due_on:'2026-10-17',amount:'120.00',kind:'installment'}]};
  const statement={credit:{id,folio:'CRE-DEMO-001',status:'entregado',group:'Las Flores',branch:'Carrillo',disbursed_on:'2026-10-03',first_due_on:'2026-10-10',repayment_method:'cash_branch',rate_name:'Cobre',rate_factor:'80.00'},as_of:'2026-10-11',generated_at_local:'2026-10-11 12:00:00',members:[{...member,role_in_group:'presidenta',principal:'10000.00',interest:'2800.00',weekly_payment:'800.00',guarantee:'1000.00',net_delivery:'9000.00',total_repayment:'12800.00',paid:'0.00'}],installments:[],payments:[],guarantees:[],late_fee_preview:null,late_fees:state.late_fees};
  await page.route('**/api/**',async route=>{
   const path=new URL(route.request().url()).pathname;
   let data={};
   if(path.endsWith('/me'))data={id,name:'Caja Demo',is_active:true,roles:[{name:'Super Admin'}],permissions:[]};
   else if(path.endsWith('/payments'))data=state;
   else if(path.includes('/receipts/'))data=receipt;
   else if(path.endsWith('/statement'))data=statement;
   else if(path.includes('/applications/'))data={id,folio:'CRE-DEMO-001',status:'entregado',team:{name:'Las Flores'},branch:{name:'Carrillo'},repayment_method:'cash_branch'};
   await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(data)});
  });
  const origin=process.env.APP_URL||'http://localhost:9012';
  await page.goto(`${origin}/#/cobros/${id}`);
  await page.getByText('Mora pendiente',{exact:true}).waitFor();
  assert.match(await page.locator('.collection-table').first().innerText(),/80.00/);
  assert.match(await page.getByText(/Cada aportación cubre primero las cuotas exigibles/).innerText(),/mora pendiente/);
  await page.screenshot({path:'/tmp/crece-fee-caja.png',fullPage:true});
  await page.goto(`${origin}/#/comprobantes/${id}`);
  await page.getByText(/Aplicado a cuotas:/).waitFor();
  assert.match(await page.getByText(/Aplicado a cuotas:/).innerText(),/920.00.*80.00/);
  await page.getByRole('img',{name:'QR para verificar este pago'}).waitFor();
  assert.match(await page.locator('tbody').innerText(),/Mora de cuota/);
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:'/tmp/crece-fee-ticket-mobile.png',fullPage:true});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.goto(`${origin}/#/creditos/${id}/estado-de-cuenta`);
  await page.getByRole('heading',{name:'Mora semanal del crédito'}).waitFor();
  assert.equal(await page.getByText('SIMULACIÓN · NO COBRABLE').count(),0);
  await page.getByText('Ver cargos semanales',{exact:true}).click();
  await page.getByRole('table',{name:'Cargos de mora'}).waitFor();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  assert.deepEqual(errors,[]);
  console.log('OK: Caja muestra cuotas y mora; comprobante separa importes y genera QR; estado de cuenta usa cargos cobrables; móvil sin desbordamiento. API simulada.');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
