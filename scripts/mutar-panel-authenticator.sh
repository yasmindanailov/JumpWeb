#!/usr/bin/env bash
# Arnés de mutación de EL AUTHENTICATOR DE LOS ADMINISTRADORES (P3 de `docs/specs/panel-a-salvo.md` §4.3, `#851`):
# `PanelAppAuthenticationTest` y el censo de RGPD-01 (`AnonymizeCoversEveryUserColumnTest`) contra el panel (obligatorio y
# con NUESTRO middleware), el middleware (solo `admin`), las rutas del personal (`panel_mfa`), el modelo (cifrado, oculto,
# borrado al anonimizar) y el comando de emergencia (su rastro).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez · restaurar por COPIA DE SEGURIDAD (por RUTA) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=PanelAppAuthenticationTest|AnonymizeCoversEveryUserColumnTest"

PANEL=app/Providers/Filament/AdminPanelProvider.php
MW=app/Http/Middleware/RequiresAdminAppAuthentication.php
RUTAS=routes/web.php
USER=app/Domain/Identity/Models/User.php
CMD=app/Console/Commands/RemovePanelAuthenticator.php
FICHEROS=("$PANEL" "$MW" "$RUTAS" "$USER" "$CMD")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    if [[ "$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")" != 1 ]]; then
        echo "  ⚠ «$nombre» NO APLICA: el ancla no está UNA vez en $fichero"
        return
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

mutar "el panel no lo exige (isRequired apagado)" "$PANEL" \
  "->multiFactorAuthentication([AppAuthentication::make()->recoverable()->codeWindow(2)], isRequired: true)" \
  "->multiFactorAuthentication([AppAuthentication::make()->recoverable()->codeWindow(2)], isRequired: false)"

mutar "el middleware de Filament en vez del nuestro (lo exige a todos)" "$PANEL" \
  "->multiFactorAuthenticationRequiredMiddlewareName(RequiresAdminAppAuthentication::class)" \
  ""

mutar "el middleware no mira el rol (mostrador y puerta, también)" "$MW" \
  "|| ! \$user instanceof User || ! \$user->hasRole('admin') ||" \
  "|| ! \$user instanceof User ||"

mutar "el middleware deja pasar a un administrador sin authenticator" "$MW" \
  "filled(\$user->getAppAuthenticationSecret())) {" \
  "true) {"

mutar "una ruta del personal (el calendario) sin el authenticator" "$RUTAS" \
  "['web', 'auth:admin', 'panel_role', 'panel_mfa', SetAdminLocale::class, 'no-store']" \
  "['web', 'auth:admin', 'panel_role', SetAdminLocale::class, 'no-store']"

mutar "anonymize() deja el secreto" "$USER" \
  "                'app_authentication_secret' => null,             // el authenticator del panel (\`#851\`): una credencial más" \
  ""

mutar "el secreto, sin cifrar" "$USER" \
  "'app_authentication_secret' => 'encrypted'," \
  "'app_authentication_secret' => 'string',"

mutar "el secreto, a la vista (toArray)" "$USER" \
  "#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]" \
  "#[Hidden(['password', 'remember_token', 'app_authentication_recovery_codes'])]"

mutar "el comando de emergencia no deja rastro" "$CMD" \
  "AuditLogger::logSystem('panel.app_authentication_removed', \$user);" \
  ""

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
