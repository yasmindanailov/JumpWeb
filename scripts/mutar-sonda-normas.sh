#!/usr/bin/env bash
# Arnés de mutación de NORMAS (T6e de `specs/isla-y-landing-nueva.md` §4.21, `DECISIONES #842`): la guarda es
# `scripts/sonda-normas.mjs` —la página en un navegador, cada dato contra su hecho de la API—, y cada mutación rompe UN
# mecanismo de la T6e: que `seguridad` OCUPE `/normas`, las normas del PANEL (todas, por sus momentos y en su orden, con su
# porqué, su nivel y su icono), el reparto `rgSplit` del diseño, la hoja con el descargo VIGENTE (su versión, y Esc que la
# cierra), el «Reservar» de la cabecera que abre el selector y la isla que le cede el suyo, la nota de Google, las alturas y
# los calcetines de los hechos, y Normas como la página actual del pie.
#
# ⚠️ Muta la INSTANCIA (`../instancias/playjump`, montada en el contenedor: el producto lee sus vistas, su modelo, sus
# textos y su `config/paginas.php` al pintar). Sin compilar nada; Blade recompila la vista por su fecha (por eso el `touch`
# al restaurar). ⚠️ Y se ESPERA 3 s tras mutar y tras restaurar: opcache revalida los ficheros cada 2 s (la trampa pagada en
# `mutar-sonda-visitanos.sh`, 28-09).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ANCHO="${1:-390}"
INSTANCIA="${INSTANCIA:-../instancias/playjump}"
RUN="docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test node scripts/sonda-normas.mjs ${ANCHO}"

CONFIG="$INSTANCIA/config/paginas.php"
MODELO="$INSTANCIA/web/seguridad/modelo.php"
CABECERA="$INSTANCIA/web/seguridad/pieza-1.blade.php"
TARJETA="$INSTANCIA/web/components/rule-card.blade.php"
REJILLA="$INSTANCIA/web/components/rule-grid.blade.php"
HOJA="$INSTANCIA/web/components/waiver-sheet.blade.php"
HERO="$INSTANCIA/web/components/video-hero.blade.php"
ENTRADAS="$INSTANCIA/web/entradas/modelo.php"
TEXTOS="$INSTANCIA/lang/es/paginas.php"
FICHEROS=("$CONFIG" "$MODELO" "$CABECERA" "$TARJETA" "$REJILLA" "$HOJA" "$HERO" "$ENTRADAS" "$TEXTOS")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; sleep 3; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

# La salida de cada corrida, para decir QUÉ comprobación cae: que muerda por la razón que la mutación rompe.
SALIDA="$TMP/salida.txt"
verde() { $RUN >"$SALIDA" 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo "✓ base verde (sonda-normas a ${ANCHO})"

muerden=0; total=0

# `SOLO=<trozo del nombre>`: solo esa mutación, y con la salida ENTERA de la sonda (para depurar una que no muerde).
SOLO="${SOLO:-}"

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    if [[ -n "$SOLO" && "$nombre" != *"$SOLO"* ]]; then return; fi
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
    sleep 3
    if verde; then
        echo "  ✗ NO muerde: $nombre"
        if [[ -n "$SOLO" ]]; then sed 's/^/                 /' "$SALIDA"; fi
    else
        echo "  ✓ muerde:    $nombre"
        grep '^✗' "$SALIDA" | head -3 | sed 's/^/                 /'
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
    sleep 3
}

# ── La página ocupa `/normas` ─────────────────────────────────────────────────────────────────────────────────────
mutar "Normas ya no OCUPA /normas (sale el tablero del producto)" "$CONFIG" \
  "'ocupa' => 'normas'," \
  ""

# ── Las normas del panel ──────────────────────────────────────────────────────────────────────────────────────────
mutar "una norma del panel se pierde" "$MODELO" \
  "\$normas = collect(\$hechos['rules']['rules'] ?? []);" \
  "\$normas = collect(array_slice(\$hechos['rules']['rules'] ?? [], 1));"

mutar "los momentos, en otro orden que el del panel" "$MODELO" \
  "\$grupos = collect(\$hechos['rules']['moments'] ?? [])" \
  "\$grupos = collect(array_reverse(\$hechos['rules']['moments'] ?? []))"

mutar "cada norma sin su porqué" "$MODELO" \
  "'text' => new HtmlString(e(trim((\$r['description'] ?? '').' '.(\$r['reason'] ?? ''))))," \
  "'text' => new HtmlString(e(trim(\$r['description'] ?? ''))),"

mutar "el nivel del panel se ignora (todas «Seguridad»)" "$TARJETA" \
  "\$nivel = in_array(\$rule['level'] ?? null, ['must', 'safety', 'forbidden', 'info'], true) ? \$rule['level'] : 'safety';" \
  "\$nivel = 'safety';"

mutar "el icono del panel se ignora (todas el escudo)" "$TARJETA" \
  ":name=\"\$rule['icon'] ?? 'shield-check'\"" \
  ":name=\"'shield-check'\""

mutar "el reparto sin equilibrar (un solo momento a la izquierda)" "$REJILLA" \
  "[\$corte, \$mejor, \$acumulado] = [1, INF, 0];" \
  "[\$corte, \$mejor, \$acumulado] = [1, -INF, 0];"

# ── El descargo ───────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la hoja sin el descargo vigente (no se monta)" "$MODELO" \
  "\$descargo = \$hechos['waiver']['document'] ?? null;" \
  "\$descargo = null;"

mutar "la hoja dice otra versión que la vigente" "$MODELO" \
  "'version' => \$descargo['version'] ?? ''," \
  "'version' => (\$descargo['version'] ?? 0) + 1,"

mutar "la hoja se abre sin modal (Esc no la cierra)" "$HOJA" \
  "{ e.preventDefault(); hoja.showModal(); }" \
  "{ e.preventDefault(); hoja.show(); }"

# ── Los «Reservar» y la isla ──────────────────────────────────────────────────────────────────────────────────────
mutar "el «Reservar» de la cabecera no abre el selector (baja a la portada)" "$CABECERA" \
  "'#reparto']\" cta-planes" \
  "'#reparto']\""

mutar "la isla no cede (la cabecera pierde su marca)" "$HERO" \
  ":href=\"\$ctaPrimary['href']\" data-isla-cta data-isla-planes>" \
  ":href=\"\$ctaPrimary['href']\" data-isla-planes>"

# ── Los hechos que la página cuenta ───────────────────────────────────────────────────────────────────────────────
mutar "la nota de Google, con otra cuenta de reseñas" "$ENTRADAS" \
  "'resenas' => \$hechos['social_proof']['rating']['count']," \
  "'resenas' => 190,"

mutar "la altura de Jump, escrita a mano" "$ENTRADAS" \
  "'escolta_altura' => isset(\$escolta['below_cm']) ? \$altura(\$escolta['below_cm']) : null," \
  "'escolta_altura' => '1,20 m',"

mutar "los calcetines, a un precio escrito a mano" "$TEXTOS" \
  "'Los compras en la puerta por :calcetines," \
  "'Los compras en la puerta por 3 €,"

# ── La navegación ─────────────────────────────────────────────────────────────────────────────────────────────────
mutar "el pie no marca Normas como la actual" "$MODELO" \
  "\$pie['nav'] = array_map(fn (array \$n): array => \$n['href'] === \$esta ? [...\$n, 'href' => '#'] : \$n, \$pie['nav']);" \
  "\$pie['nav'] = \$pie['nav'];"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
