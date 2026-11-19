const {chromium}=require(process.env.PLAYWRIGHT_MODULE);
const assert=require('node:assert/strict');
(async()=>{const b=await chromium.launch({channel:'chrome',headless:true});try{for(const [app,port,label,remember,id] of [
 ['client-app',18917,'Número de cliente','Recordar número de cliente','CLI-DEMO-00001'],
 ['staff-app',18918,'Correo electrónico','Recordar correo','demo@example.test']
]){const p=await b.newPage({viewport:{width:390,height:844}});const errors=[];p.on('pageerror',e=>errors.push(e.message));await p.route('**/api/**',async route=>{const req=route.request(),url=new URL(req.url());let data={};
 if(url.pathname.endsWith('/login')){const input=req.postDataJSON();assert.equal(input.password,'OnlyMemory123');data=app==='staff-app'?{data:{token:'mock-staff-token'}}:{token:'mock-client-token'};}
 else if(url.pathname.endsWith('/me'))data=app==='staff-app'?{id:'demo',email:id,is_active:true,roles:[],permissions:[],password_change_required:true}:{name:'DEMO',folio:id,branch:'DEMO',must_change_password:true};
 else throw Error(`Unexpected request ${url.pathname}`);
 await route.fulfill({contentType:'application/json',body:JSON.stringify(data)});});
 const base=`http://127.0.0.1:${port}`;await p.goto(`${base}/#/login`);
 await p.getByLabel(label,{exact:true}).fill(id);await p.getByRole('checkbox',{name:remember,exact:true}).check();
 await p.reload();assert.equal(await p.getByLabel(label,{exact:true}).inputValue(),id);assert.equal(await p.getByLabel('Contraseña',{exact:true}).inputValue(),'');
 assert.equal(await p.getByRole('checkbox',{name:/Activar acceso con/}).count(),0);
 await p.getByLabel('Contraseña',{exact:true}).fill('OnlyMemory123');await p.getByRole('button',{name:'Entrar',exact:true}).click();await p.waitForURL('**/cambiar-contrasena');
 assert.ok(!(await p.evaluate(()=>JSON.stringify(localStorage))).includes('OnlyMemory123'));
 await p.goto(`${base}/#/configuracion`);await p.getByRole('checkbox',{name:remember,exact:true}).uncheck();
 await p.goto(`${base}/#/login`);assert.equal(await p.getByLabel(label,{exact:true}).inputValue(),'');
 await p.screenshot({path:`/tmp/crece-${app}-device-access.png`,fullPage:true});assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));assert.deepEqual(errors,[]);await p.close();console.log(`OK ${app}: remembered identifier, forget from settings, password not persisted, expired password redirects, responsive login; mocked API.`);
 }}finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
