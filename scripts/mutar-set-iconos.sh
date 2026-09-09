#!/usr/bin/env bash
# Arnés de mutación del LOTE DE TRECE del set de iconos (`specs/rediseno-desde-canvas.md` §5.3,
# `DECISIONES #475`) — la T1h del carril de diseño.
#
# Lo que protege son tres cosas distintas, y por eso hay tres bloques: que los dibujos nuevos hablen
# el idioma del set (anatomía), que el cajón sepa pintar lo que el panel ofrece (paridad y registro)
# y que **toda opción ofrecida tenga NOMBRE** — que es el defecto preexistente que esta tanda cerró:
# cuatro de las once opciones salían en el desplegable del catálogo como su clave de traducción en
# crudo, y las dos guardas que ya existían pasaban en verde con eso puesto.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca `grep
# passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con
# `git checkout` (`#181`) · copia en RUTA FIJA que se repara al arrancar (`#448`) · `mutar()` ABORTA
# si el fichero no tiene copia (`#449`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='ProductIconSingleSourceTest|SidebarIconParityTest|IconSetAnatomyTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/set-iconos"
FICHEROS=(
    lang/es/admin.php
    app/Domain/Booking/Services/ProductIcon.php
    resources/js/sidebar/ProductIcon.vue
    resources/views/components/icons/cake.blade.php
    resources/views/components/icons/toggle.blade.php
)

restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

if [ -d "$TMP" ]; then
    sucios=0
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cmp -s "$f" "$TMP/$(basename "$f")" || sucios=$((sucios + 1))
    done
    if [ "$sucios" -gt 0 ]; then
        echo "⚠️  Una ejecución anterior murió sin restaurar: ${sucios} fichero(s) MUTADOS en el árbol."
        restaurar
        echo '✓ restaurados desde la copia. Comprueba con `git diff` antes de seguir.'
    fi
    rm -rf "$TMP"
fi

mkdir -p "$TMP"
trap 'restaurar; rm -rf "$TMP"' EXIT INT TERM
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'
echo

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))

    if [ ! -f "$TMP/$(basename "$fichero")" ]; then
        echo "✗ «$nombre» quiere mutar «$fichero», que NO está en FICHEROS: sin copia no hay" >&2
        echo "  restauración, y el veredicto de toda la tanda deja de valer. Añádelo al array." >&2
        exit 1
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
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
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

LANG_ES=lang/es/admin.php
DOM=app/Domain/Booking/Services/ProductIcon.php
VUE=resources/js/sidebar/ProductIcon.vue
CAKE=resources/views/components/icons/cake.blade.php
TOG=resources/views/components/icons/toggle.blade.php

echo '── El NOMBRE de una opción · el defecto que esta tanda cerró ──'
# ❗ Las dos guardas que ya existían (el icono existe · el cajón lo dibuja) pasaban en VERDE con
# cuatro opciones enseñando su clave de traducción en crudo al operador. Éstas son su sujeto.

mutar "una opción NUEVA se queda sin rótulo" "$LANG_ES" \
  "            'cake' => 'Tarta'," \
  "            "

mutar "una opción HEREDADA se queda sin rótulo" "$LANG_ES" \
  "            'socks' => 'Calcetines'," \
  "            "

echo
echo '── El cajón pinta lo que el panel OFRECE ──'

mutar "el cajón no sabe dibujar un complemento ofrecido" "$VUE" \
  "key === 'ice-bucket'" \
  "key === 'ice-bucket-NO'"

mutar "se ofrece una clave que no tiene dibujo en el set" "$DOM" \
  "'cake', 'ice-bucket', 'snacks', 'drink', 'clock-plus'," \
  "'cake', 'ice-bucket', 'snacks', 'drink', 'clock-plus', 'tarta-grande',"

mutar "la copia del cajón se separa del set (paridad byte a byte)" "$VUE" \
  '<circle cx="12" cy="2.2" r="1.4" />' \
  '<circle cx="12" cy="2.6" r="1.4" />'

echo
echo '── La ANATOMÍA del set · lo que hace que 63 dibujos parezcan uno ──'

mutar "un dibujo nuevo pinta con un color literal" "$CAKE" \
  'viewBox="0 0 24 24" fill="currentColor"' \
  'viewBox="0 0 24 24" fill="#101418"'

mutar "un dibujo nuevo se sale de la rejilla de 24" "$TOG" \
  'viewBox="0 0 24 24"' \
  'viewBox="0 0 20 20"'

echo
echo "── ${muerden}/${total} muerden ──"
[ "$muerden" -eq "$total" ] || exit 1

restaurar
for f in "${FICHEROS[@]}"; do
    cmp -s "$f" "$TMP/$(basename "$f")" || { echo "✗ «$f» NO quedó como estaba" >&2; exit 1; }
done
echo '✓ árbol idéntico al de partida'

# ❗❗ **RESTAURAR EL FUENTE NO BASTA CUANDO SE MUTA UN COMPONENTE DE VUE, y aquí pasó de verdad.**
# `SidebarDomContractTest` renderiza el BUNDLE, no las fuentes, así que al terminar el arnés el
# árbol estaba limpio y **35 casos salieron ROJOS** contra un bundle construido con la última
# mutación dentro. Parece un defecto del producto y es el instrumento.
# ▶ Un arnés que toca algo compilado tiene que dejar también lo compilado como lo encontró.
echo '── reconstruyendo los bundles (el fuente restaurado no arregla lo ya compilado) ──'
docker compose exec -u sail -T laravel.test npm run build >/dev/null 2>&1
docker compose exec -u sail -T laravel.test npm run build:ssr >/dev/null 2>&1
echo '✓ bundles reconstruidos desde el fuente restaurado'
