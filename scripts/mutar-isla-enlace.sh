#!/usr/bin/env bash
# Arnés de mutación de LA ISLA DE LAS PÁGINAS DE ENLACE (`#814`; `fiesta-sistema-nuevo.md` §4.18; `LinkIsland` del zip (6)).
# L1 · la invitación: las reglas de la isla (`logica.js`: cuándo se aparta, si la página ya enseña lo que diría, su hueco),
# la respuesta DENTRO de la isla como cara suya y tras el confeti, la línea pasado el plazo, su hueco al final del contenido
# y, en el recibo de un «sí», «Añadir al calendario» con la fecha corta, que se esconde mientras la tarjeta lo enseña, se va
# al tocarlo y no sale sin JavaScript. (L2, la lista, y L3, la autorización, se añaden aquí con su tanda.)
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
# ⚠️ Lo que NO juzga (es navegador; lo mira `scripts/sonda-isla-enlace.mjs`, a 390 y 1280): `fiesta/isla.js` —fija abajo, el
# hueco medido, llegar tras el confeti, apartarse al escribir o con el vídeo abierto, esconderse con lo que diría a la vista,
# irse al tocar el calendario— y la hoja (`.fi-isla*`, sin JavaScript en el flujo).
#
#   bash scripts/mutar-isla-enlace.sh                   (todas)
#   SOLO='calendario' bash scripts/mutar-isla-enlace.sh (las que llevan eso en su nombre)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

EXEC="docker compose exec -u sail -T laravel.test"
JS="$EXEC node --test resources/js/fiesta/logica.test.js"
PHP='InvitacionPaginaTest|FiestaModeloTest'

TMP="$(mktemp -d)"
FICHEROS=(
    resources/js/fiesta/logica.js
    app/Http/Fiesta/InvitacionPagina.php
    resources/views/fiesta/invitacion.blade.php
    resources/views/fiesta/invitacion/invitacion.blade.php
    resources/views/fiesta/invitacion/recibo.blade.php
    resources/views/components/fiesta/isla.blade.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; $EXEC php artisan view:clear >/dev/null 2>&1; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde_js()  { $JS >/dev/null 2>&1; }
verde_php() { $EXEC php artisan view:clear >/dev/null 2>&1; $EXEC php artisan test --filter="$PHP" >/dev/null 2>&1; }

if ! verde_js || ! verde_php; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde (node y PHP)'

muerden=0; total=0

# mutar <juez: js|php> <nombre> <fichero> <buscar> <poner>
mutar() {
    local juez="$1" nombre="$2" fichero="$3" buscar="$4" poner="$5"
    [[ -n "${SOLO:-}" && "$nombre" != *"$SOLO"* ]] && return
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
        echo "  ⚠ «$nombre» NO SE APLICÓ: el veredicto no vale"
        return
    fi
    touch "$fichero"
    if "verde_$juez"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

LOGICA=resources/js/fiesta/logica.js
MODELO=app/Http/Fiesta/InvitacionPagina.php
PAGINA=resources/views/fiesta/invitacion.blade.php
INV=resources/views/fiesta/invitacion/invitacion.blade.php
RECIBO=resources/views/fiesta/invitacion/recibo.blade.php
ISLA=resources/views/components/fiesta/isla.blade.php

# ── Las reglas de la isla (`logica.js`) ─────────────────────────────────────────────────────────────────
mutar js "la isla está a la vista antes de que caiga el confeti" "$LOGICA" \
  "return !llegada || vista || sin || capa || (escribiendo && !suya);" "return vista || sin || capa || (escribiendo && !suya);"
mutar js "la isla tapa el vídeo abierto" "$LOGICA" \
  "return !llegada || vista || sin || capa || (escribiendo && !suya);" "return !llegada || vista || sin || (escribiendo && !suya);"
mutar js "la isla se aparta también al escribir en su campo" "$LOGICA" \
  "return !llegada || vista || sin || capa || (escribiendo && !suya);" "return !llegada || vista || sin || capa || escribiendo;"
mutar js "la isla repite lo que la página ya enseña" "$LOGICA" \
  "return !llegada || vista || sin || capa || (escribiendo && !suya);" "return !llegada || sin || capa || (escribiendo && !suya);"
mutar js "«se ve» cuenta también la franja de la isla" "$LOGICA" \
  "    const franja = alto - 96;" "    const franja = alto;"
mutar js "«se ve» cuenta lo escondido" "$LOGICA" \
  "r.height > 0 && r.bottom > 8 && r.top < franja" "r.bottom > 8 && r.top < franja"
mutar js "«se ve» cuenta lo que ya pasó por arriba" "$LOGICA" \
  "r.height > 0 && r.bottom > 8 && r.top < franja" "r.height > 0 && r.top < franja"
mutar js "el hueco olvida el borde de la isla" "$LOGICA" \
  "\`calc(\${alto + 2}px" "\`calc(\${alto}px"
mutar js "el hueco se queda cuando la isla ya no tiene nada que hacer" "$LOGICA" \
  "    return sin || alto === null || alto === undefined" "    return alto === null || alto === undefined"

# ── La invitación: la respuesta en la isla, la línea y el hueco ─────────────────────────────────────────
mutar php "la respuesta no es cara suya (la isla se apartaría al escribir el nombre)" "$INV" \
  ":cara=\"\$m['respuestas']['abiertas'] ? 'respuesta' : 'linea'\"" ":cara=\"'barra'\""
mutar php "la isla llega sin esperar al confeti" "$INV" \
  ':delay="1150"' ':delay="0"'
mutar php "la isla se queda sin nombre para el lector de pantalla" "$INV" \
  ":label=\"\$m['isla']['respuesta']\"" ":label=\"''\""
mutar php "la página pierde el hueco de la isla" "$PAGINA" \
  "            <x-fiesta.isla-hueco />" ""
mutar php "la isla pierde su marca (el JavaScript no la encuentra)" "$ISLA" \
  "data-isla-enlace data-delay=" "data-delay="

# ── El recibo: «Añadir al calendario» ────────────────────────────────────────────────────────────────────
mutar php "el calendario de la isla sale también tras un «no»" "$MODELO" \
  "\$recibo !== null && \$recibo['si'] && (string)" "\$recibo !== null && (string)"
mutar php "el calendario de la isla dice la fecha con sus puntos («Sáb. 3 oct.»)" "$MODELO" \
  "trim(str_replace('.', '', DisplayTime::dayLabel(\$date))" "trim(DisplayTime::dayLabel(\$date)"
mutar php "el calendario de la isla repite la tarjeta (no se esconde con ella a la vista)" "$RECIBO" \
  ' ocultaSi="[data-receipt] .inv-enlaces"' ''
mutar php "el calendario de la isla sale sin JavaScript (y repetiría la tarjeta)" "$RECIBO" \
  'class="fi-isla--solo-js"' 'class=""'
mutar php "el calendario de la isla no se va al tocarlo" "$RECIBO" \
  ' data-isla-hecho data-receipt-island-calendar' ' data-receipt-island-calendar'

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
