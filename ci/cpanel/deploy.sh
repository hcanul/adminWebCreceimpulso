#!/bin/bash
set -euo pipefail
umask 077
base=/home/creceimp/jenkins-creceimpulso-pruebas
public=/home/creceimp/pruebas.creceimpulso.com
php=/opt/alt/php83/usr/bin/php
exec >>"$base/deploy.log" 2>&1
cd "$base"
"$php" "$base/deploy-support.php" ready
exec 9>"$base/deploy.lock"
/usr/bin/flock -n 9 || exit 0
if [ -f "$base/admin-bootstrap.env" ] && [ -f "$base/current/backend/vendor/autoload.php" ]; then
    "$php" "$base/bootstrap-admin.php"
fi
[ -f request.json ] || exit 0
# Una petición interrumpida requiere revisión; no sobrescribirla silenciosamente.
[ ! -e processing.json ] || exit 1
mv request.json processing.json
mkdir -p backups requests shared/storage/app/private shared/storage/app/public shared/storage/framework/cache/data shared/storage/framework/sessions shared/storage/framework/views shared/storage/logs
phase=prepare
finish() {
    code=$?
    trap - EXIT
    rm -f "$base/mysql-client.cnf"
    if [ "$code" -ne 0 ]; then "$php" "$base/deploy-support.php" report failure "$phase" || true; fi
    mv "$base/processing.json" "$base/requests/$(date -u +%Y%m%dT%H%M%SZ)-$code.json"
    exit "$code"
}
trap finish EXIT
"$php" "$base/deploy-support.php" prepare
release_id=$(cat "$base/release-id")
[[ "$release_id" =~ ^ci-[0-9]+-[a-f0-9]{12}$ ]]
release="$base/releases/$release_id"
ln -s "$base/shared.env" "$release/backend/.env"
ln -s "$base/shared/storage" "$release/backend/storage"
mkdir -p "$release/backend/bootstrap/cache"
phase=backup
/usr/bin/mysqldump --defaults-extra-file="$base/mysql-client.cnf" --single-transaction --no-tablespaces --skip-lock-tables --skip-triggers --hex-blob creceimp_pruebas > "$base/backups/$release_id.sql"
test -s "$base/backups/$release_id.sql"
"$php" "$base/deploy-support.php" compress-backup
rm -f "$base/mysql-client.cnf"
phase=prepare-public
mkdir -p "$public/.releases/$release_id"
chmod 755 "$public/.releases" "$public/.releases/$release_id"
for pair in admin-web:admin client-app:clientes staff-app:personal; do
    app=${pair%:*}; destination=${pair#*:}
    cp -R "$release/web/$app" "$public/.releases/$release_id/$destination"
    if [ -d "$public/.releases/current/$destination/assets" ]; then
        cp -Rn "$public/.releases/current/$destination/assets/." "$public/.releases/$release_id/$destination/assets/"
    fi
done
find "$public/.releases/$release_id" -type d -exec chmod 755 {} +
find "$public/.releases/$release_id" -type f -exec chmod 644 {} +
phase=migrate
# El mantenimiento permanece activo si falla una migración o la comprobación.
touch "$public/.jenkins-maintenance"
cd "$release/backend"
"$php" "$base/deploy-support.php" repair-empty-initial-schema
"$php" artisan package:discover --no-interaction
"$php" artisan migrate --force --no-interaction
"$php" artisan config:cache --no-interaction
phase=activate
"$php" "$base/deploy-support.php" public-config
for destination in admin clientes personal; do
    if [ ! -e "$public/$destination" ] && [ ! -L "$public/$destination" ]; then
        ln -s ".releases/current/$destination" "$public/$destination"
    elif [ ! -L "$public/$destination" ] || [ "$(readlink "$public/$destination")" != ".releases/current/$destination" ]; then
        printf 'Carpeta pública existente sin gestionar: %s\n' "$destination" >&2
        exit 1
    fi
done
if [ ! -e "$public/crece-impulso-logo.png" ] && [ ! -L "$public/crece-impulso-logo.png" ]; then
    ln -s '.releases/current/admin/crece-impulso-logo.png' "$public/crece-impulso-logo.png"
elif [ ! -L "$public/crece-impulso-logo.png" ] || [ "$(readlink "$public/crece-impulso-logo.png")" != '.releases/current/admin/crece-impulso-logo.png' ]; then
    printf '%s\n' 'Hay un logotipo público ajeno al despliegue.' >&2
    exit 1
fi
ln -s "$release" "$base/current.next"
mv -Tf "$base/current.next" "$base/current"
ln -s "$release_id" "$public/.releases/current.next"
mv -Tf "$public/.releases/current.next" "$public/.releases/current"
phase=health
"$php" "$base/deploy-support.php" health
rm -f "$public/.jenkins-maintenance"
"$php" "$base/deploy-support.php" report success complete
