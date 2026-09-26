#!/usr/bin/env bash
# Arnés de mutación del IDIOMA de las páginas de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.10, `[DECIDIDO owner]`
# `#748`): la invitación, su recibo y la autorización eligen el idioma SOLAS, como la web (`SetLocale`), sin nada en la
# cabecera, y abajo del todo llevan una línea de texto con los tres del sitio (el que se lee sin enlace, los otros a
# `lang.switch`, cada uno con su nombre en su propio idioma).
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-idioma-fiesta.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='InvitacionPaginaTest|AutorizacionPaginaTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    resources/views/fiesta/invitacion/cabecera.blade.php
    resources/views/fiesta/invitacion/idiomas.blade.php
    resources/views/fiesta/invitacion.blade.php
    resources/views/fiesta/autorizacion.blade.php
    app/Http/Fiesta/InvitacionPagina.php
    app/Http/Middleware/SetLocale.php
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

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
    local veces
    veces=$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")
    if [[ "$veces" != "1" ]]; then
        echo "  ⚠ «$nombre»: el ancla aparece $veces veces (tiene que ser UNA): el veredicto no vale"
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

CAB=resources/views/fiesta/invitacion/cabecera.blade.php
IDI=resources/views/fiesta/invitacion/idiomas.blade.php
INV=resources/views/fiesta/invitacion.blade.php
AUT=resources/views/fiesta/autorizacion.blade.php
IP=app/Http/Fiesta/InvitacionPagina.php
SL=app/Http/Middleware/SetLocale.php

# ── 1 · Nada en la cabecera, la línea abajo ─────────────────────────────────────────────────────
mutar "el idioma vuelve a la cabecera" "$CAB" \
  $'    </div>\n</div>' \
  $'    <a href="/lang/en">EN</a></div>\n</div>'
mutar "la invitación pierde la línea del idioma" "$INV" \
  $'            @include(\'fiesta.invitacion.idiomas\')\n' \
  ''
mutar "la autorización pierde la línea del idioma" "$AUT" \
  $'            @include(\'fiesta.invitacion.idiomas\')\n' \
  ''
mutar "la línea del idioma sube arriba" "$INV" \
  $'            @include(\'fiesta.invitacion.cabecera\')\n' \
  $'            @include(\'fiesta.invitacion.idiomas\')\n            @include(\'fiesta.invitacion.cabecera\')\n'

# ── 2 · Lo que dice la línea ────────────────────────────────────────────────────────────────────
mutar "el idioma que se lee también es enlace" "$IDI" \
  "@if (\$i['clave'] === \$m['idiomas']['actual'])" \
  "@if (false)"
mutar "los enlaces dejan de cambiar el idioma" "$IP" \
  "'enlace' => route('lang.switch', ['locale' => \$clave])," \
  "'enlace' => '#',"
mutar "un idioma se nombra en el de la página, no en el suyo" "$IP" \
  "'fr' => 'Français'" \
  "'fr' => 'Francés'"

# ── 3 · Sola, como la web ───────────────────────────────────────────────────────────────────────
mutar "la primera visita deja de mirar el idioma del navegador" "$SL" \
  "if (\$request->header('Accept-Language')) {" \
  "if (false) {"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
