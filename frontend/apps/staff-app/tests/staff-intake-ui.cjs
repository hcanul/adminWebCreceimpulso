const {chromium}=require(process.env.PLAYWRIGHT_MODULE);const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:390,height:844}});page.setDefaultTimeout(15000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
const team={id:'11111111-1111-4111-8111-111111111111',name:'Las Rosas',branch_id:'22222222-2222-4222-8222-222222222222',promoter_id:'33333333-3333-4333-8333-333333333333',is_active:true,branch:{id:'22222222-2222-4222-8222-222222222222',name:'Tulum'},promoter:{id:'33333333-3333-4333-8333-333333333333',name:'Ana'}};
let saved=null,lookupMode='normal',posts=0,conflict=false;
await page.addInitScript(()=>sessionStorage.setItem('crece.staff.session','test-token'));
await page.route('**/api/**',async r=>{const req=r.request(),u=new URL(req.url());let data;
if(u.pathname.endsWith('/me'))data={id:team.promoter_id,name:'Ana',email:'ana@example.test',branch_id:team.branch_id,is_active:true,roles:[{id:1,name:'Promotor'}],permissions:['intake.access']};
else if(u.pathname.endsWith('/intake/options'))data={branches:[team.branch],promoters:[team.promoter],promoter_candidates:[]};
else if(u.pathname.endsWith('/intake/teams/'+team.id))data=team;
else if(u.pathname.endsWith('/intake/clients/lookup')){
assert.equal(req.postDataJSON().team_id,team.id);
if(lookupMode==='restricted')data={found:true,can_edit:false,client:{id:'other',folio:'CLI-CAN-001',branch:'Cancún',status:'activo'},active_credits:[],last_credit:null};
else data=saved?{found:true,can_edit:true,client:{id:saved.client_id,folio:'CLI-TUL-001',name:'Maria Prueba',branch:'Tulum',status:'activo',version:1},application:saved,active_credits:[],last_credit:null}:{found:false};
}else if(u.pathname.endsWith('/intake/client-files')){posts++;const b=req.postDataJSON();assert.equal(b.submit,false);assert.equal(b.curp,'GODE561231HDFRRN09');assert.equal('amount_requested' in b,false);assert.equal('ncliente' in b.fields,false);assert.match(b.id,/^[a-f0-9-]{36}$/);
if(conflict){await r.fulfill({status:409,json:{message:'El cliente cambió. Consulta nuevamente la CURP antes de guardar.'}});return;}
saved={...b,client_id:'44444444-4444-4444-8444-444444444444',version:1,status:'borrador',client:{folio:'CLI-TUL-001',version:1},fields:b.fields};data=saved;
}else {await r.fulfill({status:404,json:{}});return;}await r.fulfill({json:data});});
const url='http://127.0.0.1:9014/#/clientes/capturar?grupo='+team.id;
await page.goto(url);await page.getByText('Seleccionado: Las Rosas',{exact:false}).waitFor();
await page.getByLabel('CURP',{exact:true}).fill('GODE561231HDFRRN09');await page.getByRole('button',{name:'Verificar antes de continuar'}).click();await page.getByRole('button',{name:'Iniciar expediente'}).click();
await page.getByRole('button',{name:'Guardar borrador'}).waitFor();assert.equal(await page.getByRole('button',{name:'Enviar a revisión'}).count(),0);
assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
await page.getByRole('button',{name:'Datos Solicitante',exact:false}).click();

await page.getByLabel('Primer Nombre *',{exact:true}).fill('Maria');
await page.getByLabel('Apellido Paterno *',{exact:true}).fill('Prueba');
await page.getByRole('button',{name:'Guardar borrador'}).click();await page.getByRole('status').filter({hasText:'Borrador guardado'}).waitFor();assert.equal(posts,1);
await page.reload();await page.getByText('Seleccionado: Las Rosas',{exact:false}).waitFor();await page.getByLabel('CURP',{exact:true}).fill('GODE561231HDFRRN09');await page.getByRole('button',{name:'Verificar antes de continuar'}).click();await page.getByRole('button',{name:'Sí, abrir expediente'}).click();await page.getByRole('button',{name:'Datos Solicitante',exact:false}).click();assert.equal(await page.getByLabel('Primer Nombre *',{exact:true}).inputValue(),'Maria');
await page.screenshot({path:'/tmp/staff-capture.png',fullPage:true});
conflict=true;await page.getByLabel('Primer Nombre *',{exact:true}).fill('Maria Editada');await page.getByRole('button',{name:'Guardar borrador'}).click();await page.getByRole('button',{name:'Entendido',exact:true}).click();assert.equal(await page.getByLabel('Primer Nombre *',{exact:true}).inputValue(),'Maria Editada');
await page.getByRole('button',{name:'Cambiar de persona'}).click();await page.getByRole('button',{name:'Descartar cambios',exact:true}).click();lookupMode='restricted';await page.getByLabel('CURP',{exact:true}).fill('GODE561231HDFRRN09');await page.getByRole('button',{name:'Verificar antes de continuar'}).click();await page.getByRole('heading',{name:'CURP registrada · edición restringida'}).waitFor();await page.getByRole('button',{name:'Entendido',exact:true}).click();assert.equal(await page.getByRole('button',{name:'Guardar borrador'}).count(),0);assert.deepEqual(errors,[]);
console.log('OK: CURP gate, draft save/reopen, no financial fields, submit permission, conflict retained, restricted duplicate blocked.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
