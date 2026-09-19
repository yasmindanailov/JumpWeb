#!/usr/bin/env bash
# Arnés de mutación de LA HOJA DEL PAQUETE (F4 · T4, `docs/specs/cajon-empaquetable.md` §4.3).
#
# `public/css/cajon.css` se genera desde las tres hojas del producto. Lo que se rompe sin ruido aquí no es
# «falla»: es que la hoja se quede VIEJA, que le falte una regla, o que lea un token que en la página de otro
# no existe. Nada de eso pone rojo nada por sí solo —sigue siendo CSS válido— y el precio lo paga el cliente
# que abre el cajón y lo ve desnudo. Cada mutación quita una de esas guardas y `HojaDelCajonTest` tiene que
# morir.
#
# ⚠️ El juez de que se VEA igual no está aquí: es `scripts/huella-maquetacion.mjs --cajon`, que necesita
# Chromium y el sitio en pie. Este arnés vigila al vigilante de la suite.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter='HojaDelCajonTest|TemaDeInstalacionMandaTest'"

HOJA=public/css/cajon.css
FUENTE=public/css/site.css
VUE=resources/js/sidebar/steps/AuthTabset.vue
GUARDA=tests/Feature/Architecture/HojaDelCajonTest.php
LAYOUT=resources/views/components/layout.blade.php

TMP="$(mktemp -d)"
FICHEROS=("$HOJA" "$FUENTE" "$VUE" "$GUARDA" "$LAYOUT")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

# `eval` porque el filtro lleva comillas: sin él, `--filter='A|B'` viaja como una palabra con comillas dentro
# y no casa con nada… con lo que TODO saldría verde y el arnés diría que ninguna mutación muerde.
verde() { $SAIL php artisan view:clear >/dev/null 2>&1; eval "$TESTS" >/dev/null 2>&1; }

if ! verde; then
    echo '✗ `HojaDelCajonTest` NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# ⚠️ **`mutar` cambia UNA aparición; `mutar_todas` cambia todas, y aquí hace falta la segunda.** Las guardas
# de este fichero comprueban que la clase esté vestida, no cuántas reglas la visten, así que renombrar una de
# las dos reglas de `.tabset` deja viva la del `@media` y la mutación sobrevive **sin que la guarda tenga un
# agujero**. Es una granularidad real del trinquete —no ve que falte UNA de N reglas—, y quien lo ve es el
# juez del navegador, que compara píxel a píxel.
mutar() { mutar_n 1 "$@"; }
mutar_todas() { mutar_n -1 "$@"; }

mutar_n() {
    local veces="$1" nombre="$2" fichero="$3" buscar="$4" poner="$5"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], int(sys.argv[4])))' \
        "$fichero" "$buscar" "$poner" "$veces"
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

# ── La hoja se queda vieja ─────────────────────────────────────────────────────────────────────
mutar "una fuente cambia y nadie regenera (el caso REAL: no falla nada y el cajón se queda en el diseño de ayer)" \
  "$FUENTE" "/*" "/* (retoque de la landing sin regenerar la hoja del paquete) */ /*"

mutar "la hoja pierde el sello de sus fuentes" "$HOJA" " * FUENTES " " * (sin sello) "

# ── A la hoja le falta una regla ───────────────────────────────────────────────────────────────
mutar_todas "el contenedor de las pestañas deja de viajar (el caso REAL de la T4: \`.tabset\` vivía en landing.css)" \
  "$HOJA" ".tabset {" ".zzz-tabset {"

mutar_todas "una clase que el cajón emite se cae de la hoja" "$HOJA" ".acct__btn" ".zzz-acct__btn"

# ── La hoja lee lo que en la página de otro no existe ──────────────────────────────────────────
mutar "una regla lee un token que nadie define y sin respaldo" "$HOJA" \
  "    color: var(--fg);" "    color: var(--jw-no-existe);"

mutar "un respaldo desaparece de una lectura de fuera (\`--state-size\` lo pone la instalación, o nadie)" \
  "$HOJA" "var(--state-size, 56px)" "var(--state-size)"

# ── El orden de la cascada: instalación → paquete → anfitrión (`#637`) ─────────────────────────
# ⚠️ Las dos mutaciones de este bloque son EL fallo que se coló y se cazó a mano antes de etiquetar la
# v1.2.0: ninguna rompe la landing, ninguna rompe el cajón en la máquina de quien las escribe, y las dos le
# quitan sus colores al cajón de la instalación después de desplegar.
mutar "el tema del panel se scopea al cajón (y le gana a \`client.css\` dentro del cajón)" "$LAYOUT" \
  '<style id="jj-theme">:root{' '<style id="jj-theme">:root, .sidecart{'

# ⚠️ `mutar_todas` y no `mutar`: la PRIMERA `:where(.sidecart) {` de la hoja es el suelo del paquete —color y
# fuente, que LEE tokens pero no declara ninguno—, así que cambiar solo esa no toca el reparto de la cascada y
# la mutación sobrevivía sin que la guarda tuviera un agujero. La que importa es la que declara los 193.
mutar_todas "los valores por defecto del paquete pierden el \`:where\` (y le ganan al tema de la instalación)" \
  "$HOJA" ":where(.sidecart) {" ".sidecart {"

# ── Y las guardas de la guarda ─────────────────────────────────────────────────────────────────
mutar "el escáner de selectores se queda ciego" "$GUARDA" \
  "preg_match_all('/(?:^|\\})([^{}]*)\\{/s', \$this->sinComentarios(\$css), \$m);" \
  "preg_match_all('/(?:^|\\})([^{}]*)\\{/s', '', \$m);"

# ⚠️ Ésta es la mutación que dice si el trinquete MIRA EL CAJÓN o mira cualquier cosa: una clase nueva en un
# `.vue` del cajón, que el producto viste y la hoja generada no puede tener porque se generó antes.
# ▶ El primer intento usaba `.cta-med` y **no mordía**: esa clase YA viaja en el paquete (el CTA del cajón la
# emite). Sirve una que el producto vista y el cajón no toque; `.hero__pair-slot` es de la portada.
mutar "un \`.vue\` empieza a emitir una clase que la hoja no lleva" "$VUE" \
  'class="tabset purchase__authtabs"' 'class="tabset purchase__authtabs hero__pair-slot"'

echo
echo "mutaciones: $muerden/$total muerden"
[ "$muerden" -eq "$total" ]
