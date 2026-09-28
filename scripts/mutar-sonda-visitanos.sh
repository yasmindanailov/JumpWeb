#!/usr/bin/env bash
# Arnés de mutación de VISÍTANOS (T6d de `specs/isla-y-landing-nueva.md` §4.20): la guarda es `scripts/sonda-visitanos.mjs`
# —la página de apoyo en un navegador, cada dato contra su hecho de la API—, y cada mutación rompe UN mecanismo de la T6d:
# la isla que calla [Hoy] (la marca en el envoltorio), [Hoy] de la cabecera, las fechas especiales (solo las que cambian),
# la foto de la cafetería del panel, «aquí» que abre el selector, el correo del sitio, Visítanos como página actual y el
# plazo de los cumpleaños del PRODUCTO (no los «5 días» del brief).
#
# ⚠️ Muta la INSTANCIA (`../instancias/playjump`, montada en el contenedor: el producto lee sus vistas, su modelo y sus
# textos al pintar). Sin compilar nada; Blade recompila la vista por su fecha (por eso el `touch` al restaurar).
# ⚠️ Y se ESPERA 3 s tras mutar y tras restaurar: opcache revalida los ficheros cada 2 s (`opcache.revalidate_freq`), y
# midiendo al instante la primera mutación NO mordió en la corrida completa y sí sola (28-09): se servía la vista de antes.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ANCHO="${1:-390}"
INSTANCIA="${INSTANCIA:-../instancias/playjump}"
RUN="docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test node scripts/sonda-visitanos.mjs ${ANCHO}"

HERO="$INSTANCIA/web/components/video-hero.blade.php"
PARTES="$INSTANCIA/web/components/partes.php"
MODELO="$INSTANCIA/web/visitanos/modelo.php"
ENTRADAS="$INSTANCIA/web/entradas/modelo.php"
TEXTOS="$INSTANCIA/lang/es/paginas.php"
FICHEROS=("$HERO" "$PARTES" "$MODELO" "$ENTRADAS" "$TEXTOS")

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
echo "✓ base verde (sonda-visitanos a ${ANCHO})"

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

# ── [Hoy] y la isla ───────────────────────────────────────────────────────────────────────────────────────────────
mutar "la marca de [Hoy] se pierde del envoltorio (la isla lo repite)" "$HERO" \
  '<div class="pj-vh__hoy-grande" data-hoy-linea>' \
  '<div class="pj-vh__hoy-grande">'

mutar "la cabecera no dice [Hoy]" "$MODELO" \
  "'hoy' => \$hoy," \
  "'hoy' => null,"

# ── El horario ────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "las fechas especiales son TODAS las próximas (también las que abren como un festivo)" "$MODELO" \
  "\$d['date'] >= \$fechaHoy && ! \$comoFestivo(\$d)" \
  "\$d['date'] >= \$fechaHoy"

# ── La cafetería ──────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la cafetería sin la foto del panel" "$MODELO" \
  "'foto' => empty(\$bar['venue']['url'])" \
  "'foto' => true"

# ── Las dudas ─────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "«aquí» no abre el selector de planes" "$PARTES" \
  ":data-isla-planes=\"\$planes ? '' : false\"" \
  ":data-isla-planes=\"false\""

mutar "el plazo de los cumpleaños, el del brief («5 días») y no el del producto" "$TEXTOS" \
  "cumpleaños, :plazo_cumple, y te devolvemos la señal" \
  "cumpleaños, hasta 5 días antes, y te devolvemos la señal"

# ── El cierre y la navegación ─────────────────────────────────────────────────────────────────────────────────────
mutar "el cierre sin el correo del sitio" "$MODELO" \
  "'correo' => \$site['contact']['email'] ?? null," \
  "'correo' => null,"

mutar "Visítanos sigue apuntando a /contacto (ni actual en el pie ni activa en la isla)" "$ENTRADAS" \
  "\$visitanos = \\Illuminate\\Support\\Facades\\Route::has('instancia.visitanos') ? route('instancia.visitanos') : route('contacto');" \
  "\$visitanos = route('contacto');"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
