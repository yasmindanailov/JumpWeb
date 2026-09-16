#!/usr/bin/env bash
# Copia previa de la base de datos de una instalación, ANTES de desplegar (`docs/ENTORNOS.md` §6).
#
#   ssh jumpweb-prod bash -s < scripts/copia-bd-remota.sh              # etiqueta por defecto: predeploy
#   ssh jumpweb-prod bash -s -- pre571 < scripts/copia-bd-remota.sh    # etiqueta propia
#
# Se ejecuta EN el servidor. El volcado se queda en ~/backups y NO se baja a local: un volcado de
# producción lleva datos personales (la lección del 2026-09-13, `#590`).
#
# ⚠️ Las credenciales las escribe LARAVEL en un fichero de opciones 0600 que se borra al salir, y
# `mysqldump` las lee con `--defaults-extra-file`. No se parsean del `.env` en la shell ni se pasan por
# `MYSQL_PWD`: medido el 2026-09-16 en producción, el cliente `mariadb` del servidor IGNORA `MYSQL_PWD`
# («Access denied» con la contraseña correcta, mismo sha1 que la que lee Laravel) y el fichero de
# opciones conecta. Requiere `laravel/tinker`, que el producto lleva en producción.
set -euo pipefail
etiqueta="${1:-predeploy}"
cd "$HOME/public_html"
cnf="$HOME/.jw-dump.cnf"
trap 'rm -f "$cnf"' EXIT
php artisan tinker --execute='$c = config("database.connections.mysql"); file_put_contents(getenv("HOME")."/.jw-dump.cnf", "[client]\nuser=\"".$c["username"]."\"\npassword=\"".$c["password"]."\"\nhost=".$c["host"]."\n"); chmod(getenv("HOME")."/.jw-dump.cnf", 0600);' >/dev/null
[[ -s "$cnf" ]] || { echo '✗ no se pudo escribir el fichero de opciones'; exit 1; }
db=$(php artisan tinker --execute='echo config("database.connections.mysql.database");' 2>/dev/null | tr -d '\r\n')
[[ -n "$db" ]] || { echo '✗ base de datos vacía en la configuración'; exit 1; }
mkdir -p "$HOME/backups"
f="$HOME/backups/${db}_${etiqueta}-$(date +%Y%m%d-%H%M%S).sql.gz"
mysqldump --defaults-extra-file="$cnf" --single-transaction "$db" 2>/dev/null | gzip > "$f"
gzip -t "$f"
echo "copia: $f · $(du -h "$f" | cut -f1) · tablas: $(zcat "$f" | grep -c '^CREATE TABLE')"
