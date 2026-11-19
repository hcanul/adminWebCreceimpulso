#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
if [ "$(uname -s)" != Darwin ]; then
  echo 'Este empaquetado debe ejecutarse en macOS.' >&2
  exit 1
fi
architecture=$(uname -m)
[ "$architecture" != x86_64 ] || architecture=x64
app_path="dist/electron/Packaged/Crece Impulso Admin-darwin-$architecture/Crece Impulso Admin.app"
if [ ! -d "$app_path" ]; then
  echo 'Primero ejecuta pnpm desktop:build.' >&2
  exit 1
fi
output_dir=${1:-"$HOME/Downloads/CreceImpulso-Desktop-$(date +%F)"}
mkdir -p "$output_dir"
output_dir=$(cd "$output_dir" && pwd)
staging_dir=$(mktemp -d /private/tmp/crece-desktop.XXXXXX)
trap 'rm -rf "$staging_dir"' EXIT HUP INT TERM
# Preparar fuera de carpetas sincronizadas para evitar metadatos de File Provider.
ditto --norsrc --noextattr "$app_path" "$staging_dir/Crece Impulso Admin.app"
xattr -cr "$staging_dir/Crece Impulso Admin.app"
codesign --force --deep --sign - "$staging_dir/Crece Impulso Admin.app"
codesign --verify --deep --strict "$staging_dir/Crece Impulso Admin.app"
mkdir -p "$staging_dir/Licencias"
cp "$(dirname "$app_path")/LICENSE" "$(dirname "$app_path")/LICENSES.chromium.html" "$staging_dir/Licencias/"
ln -s /Applications "$staging_dir/Aplicaciones"
cp DESKTOP.md "$staging_dir/LEEME-Instalacion.md"
cp DESKTOP.md "$output_dir/LEEME-Instalacion.md"
hdiutil create -volname 'Crece Impulso Admin' -srcfolder "$staging_dir" -format UDZO -ov "$output_dir/CreceImpulso-Admin-macOS-$architecture.dmg"
(cd "$output_dir" && shasum -a 256 "CreceImpulso-Admin-macOS-$architecture.dmg" > SHA256.txt)
printf 'Instalador: %s/CreceImpulso-Admin-macOS-%s.dmg\n' "$output_dir" "$architecture"
