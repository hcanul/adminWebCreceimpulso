const {chromium}=require(process.env.PLAYWRIGHT_MODULE);
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:390,height:844}});page.setDefaultTimeout(12000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
let allowed=true,search='',loggedOut=false;
const user=()=>({id:'staff-1',name:'Ana Promotora',email:'ana@example.test',branch_id:'branch-1',is_active:true,roles:[{id:1,name:'Promotor'}],permissions:allowed?['intake.access']:[]});
const team={id:'group-1',name:'Las Rosas',is_active:true,branch:{id:'branch-1',name:'Tulum'},promoter:{id:'staff-1',name:'Ana Promotora'},memberships_count:3};
await page.route('**/api/**',async route=>{const req=route.request(),url=new URL(req.url());let data;
if(url.pathname.endsWith('/login')){assert.equal(req.postDataJSON().device_name,'Crece Impulso Colaboradores');data={data:{token:'staff-token'}};}
else if(url.pathname.endsWith('/me')){assert.equal(req.headers().authorization,'Bearer staff-token');data=user();}
else if(url.pathname.endsWith('/logout')){loggedOut=true;await route.fulfill({status:204});return;}
else if(url.pathname.endsWith('/intake/teams')){search=url.searchParams.get('search');data={data:[team],current_page:1,last_page:1,total:1};}
else if(url.pathname.endsWith('/intake/teams/group-1')){data={...team,memberships:[{id:'member-1',role_in_group:'presidenta',client:{id:'client-1',folio:'CLI-TUL-001',first_name:'Maria',last_name:'Prueba',status:'activo'}}]};}
else {await route.fulfill({status:404,json:{}});return;}
await route.fulfill({json:data});});
await page.goto('http://127.0.0.1:9014/#/grupos');await page.waitForURL('**/#/login');
await page.getByLabel('Correo electrónico').fill('ana@example.test');await page.getByLabel('Contraseña',{exact:true}).fill('Test-only-123');await page.getByRole('button',{name:'Entrar',exact:true}).click();
await page.getByText('Hola, Ana Promotora').waitFor();
await page.getByRole('link',{name:'Consultar grupos'}).click();await page.getByText('Las Rosas',{exact:true}).waitFor();
await page.getByLabel('Buscar grupo por nombre').fill('Rosas');await Promise.all([page.waitForResponse(r=>r.url().includes('search=Rosas')),page.getByRole('button',{name:'Buscar grupos'}).click()]);await page.getByText('Las Rosas',{exact:true}).waitFor();assert.equal(search,'Rosas');
await page.getByRole('link',{name:'Ver integrantes'}).click();await page.getByText('Maria Prueba',{exact:true}).waitFor();await page.reload();await page.getByText('Maria Prueba',{exact:true}).waitFor();
assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.screenshot({path:'/tmp/staff-group.png',fullPage:true});
allowed=false;await page.goto('http://127.0.0.1:9014/#/grupos');await page.getByText('Tu cuenta no tiene habilitado el acceso a grupos.',{exact:false}).waitFor();assert.equal(new URL(page.url()).hash,'#/');
allowed=true;await page.reload();await page.getByText('Hola, Ana Promotora').waitFor();await page.screenshot({path:'/tmp/staff-home.png',fullPage:true});
await page.getByRole('button',{name:'Abrir menú'}).click();await page.getByText('Cerrar sesión',{exact:true}).click();await page.getByRole('button',{name:'Cerrar sesión',exact:true}).click();await page.waitForURL('**/#/login');assert.equal(loggedOut,true);assert.equal(await page.evaluate(()=>sessionStorage.getItem('crece.staff.session')),null);assert.deepEqual(errors,[]);
console.log('OK: login, guards, permission changes, search, members, reload, logout and mobile layout (mock API).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
