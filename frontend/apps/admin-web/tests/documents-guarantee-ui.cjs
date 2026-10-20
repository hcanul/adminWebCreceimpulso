const{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');const assert=require('node:assert/strict');const fs=require('node:fs');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:1440,height:1000}});page.setDefaultTimeout(12000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
const id='11111111-1111-4111-8111-111111111111',docId='22222222-2222-4222-8222-222222222222',fileId='33333333-3333-4333-8333-333333333333';let issued=false,signed=false,applied=false,reversed=false,eligible=false;let posts=0;
await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|document-fixture'));
const guarantee=()=>({credit_folio:'CRE-DEMO-000001',status:'entregado',as_of:'2026-10-17',available_on:'2026-10-17',eligible_date:eligible,version:'a'.repeat(64),members:[{client_id:'client-1',name:'María DEMO',folio:'CLI-DEMO-000001',retained:'1000.00',applied:applied&&!reversed?'800.00':'0.00',available:applied&&!reversed?'200.00':'1000.00',balance:applied&&!reversed?'0.00':'800.00',applicable:applied&&!reversed?'0.00':'800.00'}],history:applied?[{id:'application',actor_name:'Comercial DEMO',amount:'800.00',reason:'Aplicación al terminar el ciclo.',applied_on:'2026-10-17',reversal:reversed?{actor_name:'Comercial DEMO',reason:'Corrección documentada de la aplicación.',reversed_on:'2026-10-17'}:null,allocations:[{client_id:'client-1',amount:'800.00',number:16,due_on:'2026-10-17'}]}]:[]});
await page.route('**/api/**',async route=>{const u=new URL(route.request().url()),method=route.request().method();let body;
if(u.pathname.endsWith('/me'))body={id,name:'Comercial DEMO',is_active:true,roles:[{name:'Super Admin'}],permissions:['guarantee.apply','credit.view','intake.access']};
else if(u.pathname.includes('/documents/sources/')){if(method==='POST'){issued=true;body={id:docId};}else body={source_version:1,can_issue:true,documents:issued?[{id:docId,kind:'dossier',source_version:1,template_version:1,actor_name:'Gerente DEMO',created_at:'2026-10-17 10:00:00',sha256:'a'.repeat(64),signed_files:signed?[{id:fileId,actor_name:'Gerente DEMO',created_at:'2026-10-17',bytes:1000,sha256:'b'.repeat(64)}]:[]}]:[]};}
else if(u.pathname.endsWith('/signed')&&method==='POST'){assert.match(route.request().headers()['content-type'],/multipart\/form-data; boundary=/);signed=true;body={id:fileId};}
else if(u.pathname.includes('/documents/versions/'))body={html:fs.readFileSync('/tmp/crece-dossier.html','utf8'),sha256:'a'.repeat(64)};
else if(u.pathname.includes('/documents/signed/'))return route.fulfill({status:200,contentType:'application/pdf',body:fs.readFileSync('/tmp/crece-dossier.pdf')});
else if(u.pathname.endsWith('/guarantee')){if(method==='POST'){assert.equal(eligible,true);assert.equal(route.request().postDataJSON().reason,'Aplicación al terminar el ciclo.');applied=true;posts++;}body=guarantee();}
else if(u.pathname.endsWith('/reverse')){reversed=true;body={reversed:true};}
else throw Error('Unexpected endpoint '+u.pathname);
await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});
});
const base=process.env.CRECE_UI_URL||'http://localhost:9012';
await page.goto(base+'/#/documentos/file/'+id);await page.getByRole('button',{name:'Generar Expediente del cliente',exact:true}).click();
await page.frameLocator('iframe').getByRole('heading',{name:'Expediente del cliente',exact:true}).waitFor();
await page.frameLocator('iframe').locator('body').evaluate(()=>{window.print=()=>{window.wasPrinted=true;};});
await page.getByRole('button',{name:'Imprimir o guardar PDF',exact:true}).click();assert.equal(await page.frameLocator('iframe').locator('body').evaluate(()=>window.wasPrinted),true);
await page.getByRole('button',{name:'Cerrar',exact:true}).click();
await page.locator('input[type=file]').setInputFiles('/tmp/crece-dossier.pdf');await page.getByRole('button',{name:'Adjuntar firmado',exact:true}).click();await page.getByRole('button',{name:'Sí, continuar',exact:true}).click();
await page.getByRole('button',{name:'Descargar firmado',exact:true}).waitFor();await page.reload();await page.getByRole('button',{name:'Descargar firmado',exact:true}).waitFor();
const downloaded=page.waitForEvent('download');await page.getByRole('button',{name:'Descargar firmado',exact:true}).click();assert.match((await downloaded).suggestedFilename(),/^firmado-/);
await page.setViewportSize({width:390,height:844});await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth);await page.screenshot({path:'/tmp/crece-documents-mobile.png',fullPage:true});
await page.goto(base+'/#/creditos/'+id+'/garantia');await page.getByRole('table',{name:'Saldos de garantía'}).waitFor();assert.equal(await page.getByRole('button',{name:'Aplicar garantía al adeudo',exact:true}).isDisabled(),true);
eligible=true;await page.getByRole('button',{name:'Actualizar',exact:true}).click();await page.getByRole('table',{name:'Saldos de garantía'}).waitFor();await page.getByLabel('Motivo de la aplicación',{exact:true}).fill('Aplicación al terminar el ciclo.');await page.getByRole('button',{name:'Aplicar garantía al adeudo',exact:true}).click();await page.getByRole('button',{name:'Sí, continuar',exact:true}).click();await page.getByLabel('Motivo del reverso',{exact:true}).waitFor();assert.equal(posts,1);
assert.match(await page.getByRole('table',{name:'Saldos de garantía'}).innerText(),/200.00/);await page.screenshot({path:'/tmp/crece-guarantee-mobile.png',fullPage:true});
await page.getByLabel('Motivo del reverso',{exact:true}).fill('Corrección documentada de la aplicación.');await page.getByRole('button',{name:'Revertir aplicación',exact:true}).click();await page.getByRole('button',{name:'Sí, continuar',exact:true}).click();await page.getByText(/Revertida el 2026-10-17/).waitFor();
assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);assert.deepEqual(errors,[]);console.log('OK: emisión, impresión, PDF firmado, recarga, descarga, bloqueo antes de semana 16, aplicación, remanente, reverso y móvil; API simulada.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
