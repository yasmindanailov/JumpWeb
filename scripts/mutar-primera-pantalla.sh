#!/usr/bin/env bash
# Arnés de mutación de LA PRIMERA PANTALLA (`specs/isla-y-landing-nueva.md` §4.15, `#789`): la guarda es
# `scripts/sonda-primera-pantalla.mjs comparar kids` —el comprobador del diseño portado, contra el mockup, regla a
# regla—, y cada mutación rompe UN mecanismo de los cinco que la sostienen: el margen de la isla (producto), el aire de
# la sección de arriba (la hoja de la instancia), la llegada limpia, la medida de la foto apilada y el logotipo que cede
# tamaño en una pantalla baja (`cabecera.js`).
#
# ⚠️ Las dos de la instancia se aplican a la COPIA que sirve el producto (`public/instancia/`, ignorada por git): es lo
# que el navegador mide. Por eso la base comprueba antes que la copia es IDÉNTICA a su fuente de la instancia.
# ⚠️ La del producto obliga a recompilar la isla (`npm run build`) al mutar y al restaurar.
# ⚠️ Necesita el servidor del diseño: `php -S 127.0.0.1:8129 -t /var/www/instancias/playjump/diseno/playjump-design-system`.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

INSTANCIA="${INSTANCIA:-../instancias/playjump}"
RUN="docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test node scripts/sonda-primera-pantalla.mjs comparar kids 0,1"
BUILD="docker compose exec -u sail -T laravel.test npm run build"

PROPS=resources/js/isla/props.js
CSS=public/instancia/css/entradas.css
JS=public/instancia/js/cabecera.js
FICHEROS=("$PROPS" "$CSS" "$JS")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
compilar() { [[ "$1" == resources/js/* ]] && $BUILD >/dev/null 2>&1; return 0; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; $BUILD >/dev/null 2>&1; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

for f in "$CSS" "$JS"; do
    fuente="$INSTANCIA/publico/${f#public/}"
    if ! cmp -s "$f" "$fuente"; then
        echo "✗ la copia servida «$f» NO es su fuente ($fuente): copia antes \`publico/instancia\` a \`public/\`." >&2
        exit 1
    fi
done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde (la web, con el mismo veredicto que el mockup)'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    if [[ "$(grep -cF -- "$buscar" "$fichero")" != 1 ]]; then
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
    compilar "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
    compilar "$fichero"
}

mutar "la isla vuelve a su margen propio (4px más ancha que la cabecera en móvil)" "$PROPS" \
  "gutter: { type: String, default: 'var(--gutter)' }," \
  "gutter: { type: String, default: 'clamp(16px, 4vw, 48px)' },"

mutar "la sección de arriba vuelve a su aire viejo (no el de la retícula)" "$CSS" \
  ".pj-sec--top{padding:var(--hero-top) 0 0}" \
  ".pj-sec--top{padding:clamp(14px,2vw,20px) 0 0}"

mutar "sin la llegada limpia (lo que la isla corta se ve al llegar)" "$JS" \
  "let visto = ! activa;" \
  "let visto = true;"

mutar "sin la medida de la foto apilada (el alto de siempre)" "$JS" \
  "medio.style.height = \`\${siguiente}px\`;" \
  "medio.style.height = '';"

# El logotipo sobre la foto (el owner, `#821`): a 64px, y en una pantalla baja cede tamaño antes que el titular. Sin ceder,
# medido el 27-09: tres reglas peores que el mockup (el titular, partido en 844×340 y bajo el aviso en 1280×689).
mutar "el logotipo no cede en una pantalla baja (siempre a 64px)" "$JS" \
  "const TAMANOS_LOGO = [64, 56, 48, 40];" \
  "const TAMANOS_LOGO = [64];"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
