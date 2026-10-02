#!/usr/bin/env bash
# Arnés de mutación de las CONTRASEÑAS DE LOS CLIENTES QUE YA EXISTEN, la A5d (`docs/specs/acceso-con-codigo.md` §4.12,
# `DECISIONES #848`/`#869`).
#
# Tras la A5b ningún cliente entra con contraseña, y la A5d borra las que quedan: una migración que corre UNA vez en
# producción, la noche de la v2.0.0, sin vuelta atrás. Lo que la acota son reglas pequeñas que se caen sin ruido: que
# borre de verdad; que la frontera sea la del panel, leída del modelo —con otra lista, el personal de la puerta se
# quedaría sin entrar—; que no se haga pasar por un cambio de la cuenta; que deje su cifra en el registro solo cuando
# borra; que deshacerla no devuelva nada; y que `anonymize()` tampoco guarde ya un hash. Cada mutación quita una y un test
# tiene que morir.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD —por RUTA completa, nunca por nombre— y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=CustomerPasswordsErasedMigrationTest|PrivacyTest|AnonymizeCoversEveryUserColumnTest"

MIGRACION=database/migrations/2026_10_02_210000_erase_customer_passwords.php
USER=app/Domain/Identity/Models/User.php

TMP="$(mktemp -d)"
FICHEROS=("$MIGRACION" "$USER")
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $TESTS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ los tests de las contraseñas de los clientes NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
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

# ── La migración ─────────────────────────────────────────────────────────────────────────────────
mutar "la migración no borra ninguna" "$MIGRACION" \
  "\$borradas = User::query()->customers()->whereNotNull('password')->toBase()->update(['password' => null]);" \
  "\$borradas = 0;"

mutar "la migración borra también las del personal (sin la frontera del panel)" "$MIGRACION" \
  "User::query()->customers()->whereNotNull('password')" \
  "User::query()->whereNotNull('password')"

mutar "la migración copia los roles del panel y se deja \`puerta\` (la copia vieja de \`app:create-admin\`)" "$MIGRACION" \
  "User::query()->customers()->whereNotNull('password')" \
  "User::query()->whereDoesntHave('roles', fn (\$r) => \$r->whereIn('name', ['admin', 'staff']))->whereNotNull('password')"

mutar "la migración se hace pasar por un cambio de la cuenta (\`updated_at\`)" "$MIGRACION" \
  "->toBase()->update(['password' => null]);" \
  "->update(['password' => null]);"

mutar "la migración no deja su cifra en el registro" "$MIGRACION" \
  "            Log::info('users.customer_passwords_erased', ['count' => \$borradas]);" \
  ""

mutar "la migración escribe en el registro también cuando no borra nada" "$MIGRACION" \
  "        if (\$borradas > 0) {" \
  "        if (true) {"

mutar "deshacer la migración devuelve una contraseña" "$MIGRACION" \
  "        // Sin vuelta atrás, a propósito:" \
  "        User::query()->toBase()->update(['password' => 'devuelta']); // Sin vuelta atrás, a propósito:"

# ── La supresión (`RGPD-01`) ────────────────────────────────────────────────────────────────────
mutar "anonymize() vuelve a guardar un hash aleatorio" "$USER" \
  "                'password' => null," \
  "                'password' => Str::random(60),"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
