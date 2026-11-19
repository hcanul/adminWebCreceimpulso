const{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:1440,height:1050}});page.setDefaultTimeout(15000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|branches-test'));
let role='Super Admin',records=[],attempts=[],lose=true,branchGets=0;
await page.route('**/api/**',async r=>{const req=r.request(),u=new URL(req.url());let body;
if(u.pathname.endsWith('/me'))body={id:'admin',name:'Administración DEMO',is_active:true,roles:[{name:role}],permissions:[]};
else if(u.pathname==='/api/branches'&&req.method()==='GET'){branchGets++;const selected=records.filter(b=>b.name.toLowerCase().includes((u.searchParams.get('search')||'').toLowerCase()));body={data:selected,current_page:1,last_page:1,total:selected.length};}
else if(u.pathname==='/api/branches'&&req.method()==='POST'){const p=req.postDataJSON();attempts.push(p);if(!records.length)records.push({...p,folio_code:'S010',management_version:1});if(lose){lose=false;return r.abort('failed');}body=records[0];}
else if(u.pathname.startsWith('/api/branches/')&&req.method()==='PUT'){const p=req.postDataJSON();assert.equal(p.folio_code,undefined);if(p.version!==records[0].management_version)return r.fulfill({status:409,contentType:'application/json',body:JSON.stringify({message:'La sucursal cambió. Actualiza la lista antes de editar.'})});records[0]={...records[0],...p,management_version:p.version+1};body=records[0];}
else if(u.pathname.endsWith('/dashboard'))body={as_of:'2026-11-18T10:00:00Z',business_date:'2026-11-18',scope:'Tu sucursal',groups:null,in_review:null,active_credits:null,payments:null,portfolio:null,inbox:[]};
else throw Error('Unexpected '+req.method()+' '+u.pathname);
await r.fulfill({contentType:'application/json',body:JSON.stringify(body)});
});
const origin=process.env.CRECE_UI_URL||'http://127.0.0.1:18916';
await page.goto(origin+'/#/administracion/sucursales');await page.getByRole('button',{name:'Nueva sucursal',exact:true}).click();
await page.getByLabel('Nombre de la sucursal',{exact:true}).fill('Carrillo DEMO');await page.getByLabel('Localidad',{exact:true}).fill('Felipe Carrillo Puerto');await page.getByLabel('Teléfono',{exact:true}).fill('0000000000');await page.getByLabel('Correo de contacto',{exact:true}).fill('demo@example.test');await page.getByLabel('Domicilio operativo',{exact:true}).fill('Domicilio de prueba');
await page.getByRole('button',{name:'Guardar sucursal',exact:true}).click();await page.getByRole('button',{name:'Entendido',exact:true}).click();await page.getByRole('button',{name:'Reintentar guardado',exact:true}).click();await page.locator('.swal2-container').waitFor({state:'hidden'});
assert.equal(attempts.length,2);assert.deepEqual(attempts[0],attempts[1]);assert.equal(records.length,1);
await page.reload();await page.getByRole('heading',{name:'Carrillo DEMO',exact:true}).waitFor();
await page.getByRole('button',{name:'Editar sucursal',exact:true}).click();await page.getByLabel('Nombre de la sucursal',{exact:true}).fill('Carrillo Centro DEMO');await page.getByRole('button',{name:'Guardar sucursal',exact:true}).click();await page.locator('.swal2-container').waitFor({state:'hidden'});assert.equal(records[0].management_version,2);assert.equal(records[0].folio_code,'S010');
await page.setViewportSize({width:390,height:844});await page.waitForTimeout(700);await page.screenshot({path:'/tmp/crece-branches-mobile.png',fullPage:true});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
role='Gerente';const before=branchGets;await page.reload();await page.waitForURL(url=>!url.hash.includes('administracion/sucursales'));assert.equal(branchGets,before);assert.equal(await page.getByRole('button',{name:'Nueva sucursal',exact:true}).count(),0);assert.deepEqual(errors,[]);
console.log('OK: sucursal, reintento sin duplicar, edición con versión, folio permanente, recarga, móvil y ruta protegida. API simulada.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
