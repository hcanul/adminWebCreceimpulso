const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
(async()=>{
  const output=process.env.CRECE_PRINT_OUTPUT||'/tmp/crece-receipt-formats';fs.mkdirSync(output,{recursive:true});
  const browser=await chromium.launch({channel:'chrome',headless:true});
  try{
    const page=await browser.newPage({viewport:{width:1440,height:1000}});page.setDefaultTimeout(15000);
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|print-test'));
    const id='11111111-1111-4111-8111-111111111111';
    const receipt={id,folio:'REC-CRE-S001-000001-0001',credit_folio:'CRE-S001-000001',amount:'2400.00',installment_amount:'2320.00',late_fee_amount:'80.00',received_on:'2026-11-18',method:'bank_deposit',status:'registered',reversed_on:null,reversal:null,balance_version:'a'.repeat(64),actor_name:'Caja DEMO',bank_name:'Banco DEMO',bank_reference:'DEP-20261118-1234',deposited_on:'2026-11-18',verification_url:`https://example.test/comprobantes/${id}/verificar?signature=${'a'.repeat(64)}`,allocations:[{payer:'María López DEMO',beneficiary:'María López DEMO',number:1,due_on:'2026-11-17',amount:'800.00'},{payer:'Ana Pérez DEMO',beneficiary:'María López DEMO',number:1,due_on:'2026-11-17',kind:'late_fee',charged_on:'2026-11-18',amount:'80.00'},{payer:'Ana Pérez DEMO',beneficiary:'Ana Pérez DEMO',number:1,due_on:'2026-11-17',amount:'800.00'},{payer:'Rosa Gómez DEMO',beneficiary:'Rosa Gómez DEMO',number:1,due_on:'2026-11-17',amount:'720.00'}]};
    await page.route('**/api/**',async route=>{
      assert.equal(route.request().method(),'GET');
      const u=new URL(route.request().url());
      const body=u.pathname.endsWith('/me')?{id:'user',is_active:true,name:'Caja DEMO',roles:[{name:'Caja'}],permissions:['credit.view','credit.collect']}:receipt;
      await route.fulfill({contentType:'application/json',body:JSON.stringify(body)});
    });
    await page.goto(`${process.env.CRECE_UI_URL||'http://127.0.0.1:18915'}/#/comprobantes/${id}`);
    const print=page.getByRole('button',{name:'Imprimir comprobante',exact:true});
    await print.waitFor();
    await page.waitForFunction(()=>Array.from(document.querySelectorAll('button')).some(b=>b.textContent.includes('Imprimir comprobante')&&!b.disabled));
    const frame=()=>page.frames().find(f=>f.url()==='about:srcdoc');
    assert.equal(await frame().locator('.sheet').count(),1);assert.equal(await frame().locator('.copy').count(),2);
    assert.equal(await frame().locator('.copy:first-child tbody tr').count(),4);
    assert.equal(await frame().locator('.copy .qr').count(),2);
    await page.getByRole('button',{name:'Vista previa de impresión',exact:true}).click();
    await page.screenshot({path:path.join(output,'preview-letter.png'),fullPage:true});
    async function pdf(name){
      const p=await browser.newPage();await p.goto(process.env.CRECE_UI_URL||'http://127.0.0.1:18915');await p.setContent(await frame().content());await p.locator('img').evaluateAll(async images=>Promise.all(images.map(i=>i.complete?Promise.resolve():new Promise(r=>{i.onload=r;i.onerror=r;}))));assert.ok(await p.locator('img').evaluateAll(images=>images.every(i=>i.naturalWidth>0)));await p.pdf({path:path.join(output,name+'.pdf'),preferCSSPageSize:true,printBackground:true});await p.close();
    }
    await pdf('carta-dos-copias-demo');
    await frame().evaluate(()=>{window.print=()=>{window.__printCalls=(window.__printCalls||0)+1;};});
    await print.click();assert.equal(await frame().evaluate(()=>window.__printCalls),1);
    const format=page.getByLabel('Formato del comprobante',{exact:true});
    async function choose(label,term){await format.click();await format.fill(term);await page.getByRole('option',{name:label,exact:true}).click();await page.waitForFunction(()=>Array.from(document.querySelectorAll('button')).some(b=>b.textContent.includes('Imprimir comprobante')&&!b.disabled));}
    await choose('Ticket · 80 mm','80');assert.equal(await frame().locator('.copy').count(),1);await pdf('ticket-80mm-demo');
    await page.reload();await page.getByRole('heading',{name:receipt.folio,exact:true}).waitFor();assert.match(await format.inputValue(),/80/);
    await page.waitForFunction(()=>Array.from(document.querySelectorAll('button')).some(b=>b.textContent.includes('Imprimir comprobante')&&!b.disabled));
    await choose('Ticket · 58 mm','58');await pdf('ticket-58mm-demo');
    receipt.allocations=Array.from({length:20},(_,i)=>({payer:`Integrante DEMO ${i+1}`,beneficiary:`Integrante DEMO ${i+1}`,number:1,due_on:'2026-11-17',amount:'120.00'}));receipt.installment_amount='2400.00';receipt.late_fee_amount='0.00';
    await page.reload();await page.getByRole('heading',{name:receipt.folio,exact:true}).waitFor();await choose('Hoja carta · dos copias','carta');
    assert.equal(await frame().locator('.sheet').count(),4);assert.equal(await frame().locator('.copy:first-child tbody tr').count(),20);await pdf('carta-continuacion-20-demo');
    receipt.status='reversed';receipt.reversed_on='2026-11-19';receipt.reversal={id:'rev',actor_name:'Gerencia DEMO',reason:'Se capturó en otro crédito. Corrección auditada.',reversed_on:'2026-11-19'};receipt.allocations=receipt.allocations.slice(0,4);
    await page.reload();await page.waitForFunction(()=>Array.from(document.querySelectorAll('button')).some(b=>b.textContent.includes('Imprimir comprobante')&&!b.disabled));assert.equal(await frame().locator('.reversed').count(),2);await pdf('carta-reverso-demo');
    assert.deepEqual(errors,[]);console.log('OK: carta con dos copias, continuación sin perder filas, tickets 80/58, preferencia persistente, aviso de reverso, QR y llamada de impresión sin escrituras API.');
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
