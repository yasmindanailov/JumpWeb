#!/usr/bin/env bash
# Arnés de mutación de la CONTRASEÑA DEL PANEL, la A5a (`docs/specs/acceso-con-codigo.md` §4.12, `DECISIONES #870`).
#
# Tras la A5 los clientes no tienen contraseña y el personal sí: la del panel, que le llega por un enlace que un
# administrador envía desde su ficha. Lo que la acota son reglas pequeñas que se caen sin ruido: que el botón solo salga
# en cuentas del panel, vivas y ajenas, y solo a quien da los roles; que el DOMINIO tampoco se lo mande a un cliente; que
# el enlace vaya firmado a la página del panel y no quede en la copia del registro; que la página siga la política del
# producto; y que el comando del primer administrador conozca los tres roles del panel. Cada mutación quita una y un test
# tiene que morir.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD —por RUTA completa, nunca por nombre— y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=SendPanelPasswordActionTest|CreateAdminTest"

FICHA=app/Filament/Resources/Users/Pages/ViewUser.php
LINKS=app/Domain/Identity/Services/PanelPasswordLinks.php
CORREO=app/Notifications/PanelPasswordLink.php
PAGINA=app/Filament/Auth/PanelPassword.php
PANEL=app/Providers/Filament/AdminPanelProvider.php
COMANDO=app/Console/Commands/CreateAdmin.php

TMP="$(mktemp -d)"
FICHEROS=("$FICHA" "$LINKS" "$CORREO" "$PAGINA" "$PANEL" "$COMANDO")
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $TESTS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ los tests de la contraseña del panel NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# $1 nombre · $2 fichero · $3 buscar · $4 poner · $5 veces (1 por defecto; 0 = todas)
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" veces="${5:-1}"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; n=int(sys.argv[4]); s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], n if n > 0 else -1))' \
        "$fichero" "$buscar" "$poner" "$veces"
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

# ── El botón de la ficha (`ViewUser`) ────────────────────────────────────────────────────────────
mutar "el botón sale también en cuentas de cliente" "$FICHA" \
  "            && \$record->isTeamMember()
" ""

# ⚠️ `hasPermission('access.manage')` sale TRES veces en la ficha (los roles la usan dos): el ancla lleva la línea siguiente.
mutar "el botón lo ve quien solo ve fichas (users.manage)" "$FICHA" \
  "hasPermission('access.manage') ?? false)
            && \$record->isTeamMember()" \
  "hasPermission('users.manage') ?? false)
            && \$record->isTeamMember()"

mutar "el botón sale en una cuenta anonimizada" "$FICHA" \
  "            && ! \$record->isAnonymized()
            && \$record->getKey() !== auth()->id();" \
  "            && \$record->getKey() !== auth()->id();"

mutar "el botón sale en la cuenta propia" "$FICHA" \
  "            && ! \$record->isAnonymized()
            && \$record->getKey() !== auth()->id();" \
  "            && ! \$record->isAnonymized();"

mutar "el envío no deja rastro" "$FICHA" \
  "                AuditLogger::logSensitive('users.panel_password_link_sent', (string) \$record->email, \$record);
" ""

# ── El dominio (`PanelPasswordLinks`) ────────────────────────────────────────────────────────────
mutar "el dominio se lo manda a un cliente" "$LINKS" \
  "! \$member->isTeamMember() || " ""

mutar "el dominio se lo manda a una cuenta anonimizada" "$LINKS" \
  "\$member->isAnonymized() || " ""

mutar "el dominio no mira si hay correo (el broker busca «email IS NULL»)" "$LINKS" \
  " || blank(\$member->email)" ""

mutar "el segundo del minuto se da por enviado" "$LINKS" \
  "            Password::RESET_THROTTLED => self::THROTTLED," \
  "            Password::RESET_THROTTLED => self::SENT,"

# ── El correo (`PanelPasswordLink`) ──────────────────────────────────────────────────────────────
mutar "el enlace queda en la copia del registro" "$CORREO" \
  "        return [\$this->token];" "        return [];"

mutar "el enlace va sin firma" "$CORREO" \
  "URL::signedRoute(self::ROUTE, [" "route(self::ROUTE, ["

# ── La página del panel (`PanelPassword`, `AdminPanelProvider`) ──────────────────────────────────
mutar "la página no sigue la política del producto" "$PAGINA" \
  "            ->rules(PasswordPolicy::rules())
" ""

mutar "la página abre sin la firma del enlace" "$PANEL" \
  "->middleware('signed')->name('auth.panel-password')" "->name('auth.panel-password')"

# ── El comando del primer administrador (`CreateAdmin`) ──────────────────────────────────────────
mutar "el comando no conoce el rol «puerta»" "$COMANDO" \
  "        if (! in_array(\$roleName, User::PANEL_ROLES, true)) {" \
  "        if (! in_array(\$roleName, ['admin', 'staff'], true)) {"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
