const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('../apps/client-app/node_modules/typescript');
function fixture(app, native = true) {
  const source = fs.readFileSync(path.join(__dirname, '../apps', app, 'src/services/device-access.ts'), 'utf8');
  const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 }}).outputText;
  let server = 'https://a.example.test/api', authenticated = false, cancelled = false, strong = true, reads = 0;
  const local = new Map(), secure = new Map();
  const exports = {};
  vm.runInNewContext(code, { exports, crypto:require('node:crypto').webcrypto, TextEncoder, require(name) {
    if(name === '@capacitor/core') return {Capacitor:{isNativePlatform:()=>native}};
    if(name === './server-settings') return {getServerAddress:()=>server};
    if(name.includes('secure-storage')) return {KeychainAccess:{whenUnlockedThisDeviceOnly:'device-only'},SecureStorage:{
      async get(k){ assert.ok(authenticated,'credential read must follow biometric authentication');reads++;return secure.get(k); },
      async set(k,v,convert,sync,access){assert.ok(authenticated);assert.equal(sync,false);assert.equal(access,'device-only');secure.set(k,v);},
      async remove(k){secure.delete(k);}
    }};
    if(name.includes('biometric-auth')) return {BiometryType:{faceId:2,touchId:1,faceAuthentication:4},AndroidBiometryStrength:{strong:1},BiometricAuth:{
      async checkBiometry(){return {strongBiometryIsAvailable:strong,biometryType:2};},
      async authenticate(options){assert.equal(options.allowDeviceCredential,false);assert.equal(options.androidBiometryStrength,1);if(cancelled)throw Error('cancelled');authenticated=true;}
    }};
    throw Error(name);
  },localStorage:{getItem:k=>local.get(k)||null,setItem:(k,v)=>local.set(k,v),removeItem:k=>local.delete(k)}});
  return {api:exports,local,secure,get reads(){return reads;},setServer:s=>{server=s;authenticated=false;},cancel:()=>{cancelled=true;authenticated=false;},weak:()=>{strong=false;authenticated=false;}};
}
(async()=>{
 for(const app of ['client-app','staff-app']) {
  const f=fixture(app), a=f.api;
  a.rememberIdentifier('name@example.test',true);assert.equal(a.rememberedIdentifier(),'name@example.test');
  a.rememberIdentifier('',false);assert.equal(a.rememberedIdentifier(),'');
  assert.equal(a.hasBiometricAccess(),false);
  await a.enableBiometricAccess('name@example.test','Secret-not-localstorage-123');
  assert.equal(a.hasBiometricAccess(),true);
  assert.equal(await a.biometricAccountMatches('NAME@example.test'),true);
  assert.equal(await a.biometricAccountMatches('other@example.test'),false);
  assert.ok(!JSON.stringify([...f.local]).includes('Secret'));
  const credential=await a.biometricCredential();assert.equal(credential.password,'Secret-not-localstorage-123');
  f.setServer('https://b.example.test/api');assert.equal(a.hasBiometricAccess(),false);assert.equal(a.rememberedIdentifier(),'');
  await assert.rejects(a.biometricCredential());
  f.setServer('https://a.example.test/api');
  const before=f.reads;f.cancel();await assert.rejects(a.biometricCredential());assert.equal(f.reads,before);
  await a.removeBiometricAccess();assert.equal(a.hasBiometricAccess(),false);assert.equal(f.secure.size,0);
  const weak=fixture(app);weak.weak();await assert.rejects(weak.api.enableBiometricAccess('user','secret'));assert.equal(weak.secure.size,0);
  const web=fixture(app,false);await assert.rejects(web.api.enableBiometricAccess('user','secret'));assert.equal(web.secure.size,0);assert.equal(web.api.hasBiometricAccess(),false);
  console.log(`OK ${app}: remember/forget, native secure storage, cancelled prompt, strong biometrics, server isolation, delete, no web credential storage.`);
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
