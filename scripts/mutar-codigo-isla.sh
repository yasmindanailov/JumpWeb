#!/usr/bin/env bash
# Arnés de mutación del CÓDIGO EN LA ISLA, la Z6g·1 (`docs/specs/isla-y-landing-nueva.md` §4.27; el owner, `#867`).
#
# Las seis casillas del zip (6) entran con la sexta cifra; lo que las acota son reglas pequeñas, puras y probadas con
# `node --test`: que «Entrar» espere a las SEIS cifras (en la compra y en Mi cuenta), que el rótulo de Mi cuenta sea el
# titular de cada paso, que las casillas se vacíen SOLO con un «no» del código —no con el limitador ni con la red—, y cómo
# se pinta cada casilla (el «no» manda; el anillo, en la del cursor). Cada mutación quita una y una prueba tiene que morir.
# Lo que no se prueba aquí —que la sexta haga la acción de la banda— es cableado de los componentes: lo ve el owner.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD —por RUTA completa, nunca por nombre— y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

TESTS="docker compose exec -u sail -T laravel.test node --test resources/js/isla/compra/datos.test.js resources/js/isla/compra/pasos.test.js resources/js/isla/cuenta/vista.test.js resources/js/isla/ui/codigo.test.js"
# La pista que dice quién manda el código viaja ya puesta en el arranque (servidor).
TESTS_PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=SidebarBootTest"

ACCESO=resources/js/isla/compra/acceso.js
PASOS=resources/js/isla/compra/pasos.js
VISTA=resources/js/isla/cuenta/vista.js
CASILLA=resources/js/isla/ui/codigo.js
BOOT=app/Http/Sidebar/SidebarBoot.php

TMP="$(mktemp -d)"
FICHEROS=("$ACCESO" "$PASOS" "$VISTA" "$CASILLA" "$BOOT")
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $TESTS >/dev/null 2>&1 && $TESTS_PHP >/dev/null 2>&1; }

if ! verde; then
    echo '✗ las pruebas del código de la isla NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# $1 nombre · $2 fichero · $3 buscar · $4 poner
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
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

# ── Las casillas se vacían solo con un «no» del código ─────────────────────────────────────────
mutar "un código mal escrito (422 en «code») no vacía las casillas" "$ACCESO" \
  "return e?.code === INVALID_CREDENTIALS || Boolean(e?.fields?.code);" \
  "return e?.code === INVALID_CREDENTIALS;"

mutar "cualquier «no» las vacía (el limitador y la red, también)" "$ACCESO" \
  "return e?.code === INVALID_CREDENTIALS || Boolean(e?.fields?.code);" \
  "return Boolean(e) || r?.offline === true;"

# ── «Entrar» espera a las seis cifras ──────────────────────────────────────────────────────────────
mutar "la compra: «Entrar» con cualquier cifra (como antes)" "$PASOS" \
  "disabled: conCodigo ? codeDigits(entrada.codigo).length < CODE_LENGTH" \
  "disabled: conCodigo ? ! String(entrada.codigo ?? '').trim()"

mutar "Mi cuenta: «Entrar» con cualquier cifra (como antes)" "$VISTA" \
  "conCodigo ? codeDigits(e.entrada?.codigo).length === CODE_LENGTH" \
  "conCodigo ? Boolean(String(e.entrada?.codigo ?? '').trim())"

mutar "Mi cuenta: el rótulo vuelve a «Entra» en los dos pasos" "$VISTA" \
  "step: t(conCodigo ? 'compra.entrar.codigo_titular' : 'compra.entrar.titular_cuenta')" \
  "step: t('compra.entrar.titular')"

# ── Cómo se pinta cada casilla ─────────────────────────────────────────────────────────────────────
mutar "la casilla del cursor manda sobre el «no» (el rojo se pierde)" "$CASILLA" \
  "error ? 'var(--border-danger)' : activa || llena ? 'var(--control-border-strong)' : 'var(--control-border)'" \
  "activa || llena ? 'var(--control-border-strong)' : error ? 'var(--border-danger)' : 'var(--control-border)'"

mutar "la casilla del cursor sin anillo" "$CASILLA" \
  "boxShadow: activa ? 'var(--ring)' : 'none'" \
  "boxShadow: 'none'"

# ── Quién manda el código (el arranque) ──────────────────────────────────────────────────────────
mutar "la pista nombra a un negocio escrito a mano, no al del ajuste" "$BOOT" \
  "['remitente' => Setting::businessName()]" \
  "['remitente' => 'Play Jump Park']"

mutar "la pista viaja sin poner (con su «:remitente»)" "$BOOT" \
  "        Arr::set(\$isla, 'compra.entrar.codigo_pista', __('isla.compra.entrar.codigo_pista', ['remitente' => Setting::businessName()]));" \
  ""

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
