// Package the approved square icon into the platform-required sizes.
import { spawnSync } from 'node:child_process';
import { readdirSync, writeFileSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const root=fileURLToPath(new URL('../',import.meta.url));
const master=`${root}resources/app-icon.png`;
function resize(size,path) {
 const r=spawnSync('/usr/bin/sips',['-z',String(size),String(size),master,'--out',`${root}${path}`],{stdio:'pipe'});
 if(r.status!==0) throw new Error(r.stderr.toString());
}
resize(1024,'src-capacitor/ios/App/App/Assets.xcassets/AppIcon.appiconset/AppIcon-512@2x.png');
for(const [density,size,foreground] of [['mdpi',48,108],['hdpi',72,162],['xhdpi',96,216],['xxhdpi',144,324],['xxxhdpi',192,432]]) {
 const base=`src-capacitor/android/app/src/main/res/mipmap-${density}`;
 resize(size,`${base}/ic_launcher.png`);
 resize(size,`${base}/ic_launcher_round.png`);
 resize(foreground,`${base}/ic_launcher_foreground.png`);
}
for(const name of readdirSync(`${root}public/icons`)) {
 const size=name.match(/(\d+)x\d+/)?.[1];
 if(size) resize(Number(size),`public/icons/${name}`);
}
// ICO container with a PNG image (supported by modern browsers).
const png=readFileSync(`${root}public/icons/favicon-32x32.png`);
const header=Buffer.alloc(22);header.writeUInt16LE(1,2);header.writeUInt16LE(1,4);header[6]=32;header[7]=32;header.writeUInt16LE(1,10);header.writeUInt16LE(32,12);header.writeUInt32LE(png.length,14);header.writeUInt32LE(22,18);
writeFileSync(`${root}public/favicon.ico`,Buffer.concat([header,png]));
console.log('Iconos iOS, Android y web preparados.');
