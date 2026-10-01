#!/usr/bin/env bash
# Arnés de mutación de la Z6b·1 DE LA ISLA (zip (6), opción C «Da la razón» y la frase de cada pieza, `#866`;
# `specs/isla-y-landing-nueva.md` §4.27): `situacion.test.js`, `sin-saturar.test.js`, `pagina/pagina.test.js` y
# `forma.test.js` contra la tabla (`situacion.js`), el reparto «sin saturar» (`useSinSaturar.js`), el relevo del hueco
# (`useHueco.js`), la pieza que se lee (`pagina/pagina.js`) y el aire de la caja (`forma.js`); y `RazonesDeIslaTest` contra
# el dibujo de los iconos (`RazonesDeIsla.php`). Cada mutante rompe UNA regla: la razón que no sale, la que sale sin botón o
# sobre lo bloqueado, la espera que no manda o lleva acción, las dos voces a la vez, la razón antes de moverse, la de
# decisión que no vuelve, el presupuesto o la calma que no se respetan, lo elegido sin prisa, la frase que no se dice o no
# vuelve, el relevo de una etiqueta o que no se quita, la pieza del borde, la razón sin botón o sin la de la llegada, la
# frase prestada, el borde dentro de lo medido o el aire de 8, y el icono sin dibujar o leído por su ruta.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez (se cuenta en Python: hay anclas de dos líneas) · restaurar por COPIA DE SEGURIDAD (por RUTA,
# nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

NODE="docker compose exec -u sail -T laravel.test node --test resources/js/isla/situacion.test.js resources/js/isla/sin-saturar.test.js resources/js/isla/pagina/pagina.test.js resources/js/isla/forma.test.js"
PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=RazonesDeIslaTest"

SIT=resources/js/isla/situacion.js
SAT=resources/js/isla/useSinSaturar.js
HUECO=resources/js/isla/useHueco.js
PAG=resources/js/isla/pagina/pagina.js
FORMA=resources/js/isla/forma.js
DIB=app/Http/Instancia/RazonesDeIsla.php
FICHEROS=("$SIT" "$SAT" "$HUECO" "$PAG" "$FORMA" "$DIB")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

verde() { $NODE >/dev/null 2>&1 && $PHP >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    if [[ "$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")" != 1 ]]; then
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
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

# ── La tabla: la razón, la espera y una sola voz ─────────────────────────────────────────────────────────────────────
mutar "la razón no sale nunca (secundaria como en la Z6a)" "$SIT" \
  "    if (s.calm && p.reason && ! s.locked && ! s.bn) s.bn = { type: 'razon', ...p.reason };" \
  "    if (false) s.bn = { type: 'razon', ...p.reason };"
mutar "la razón sale SIN botón de la página a la vista" "$SIT" \
  "    if (s.calm && p.reason && ! s.locked && ! s.bn) s.bn" \
  "    if (p.reason && ! s.locked && ! s.bn) s.bn"
mutar "la razón tapa lo BLOQUEADO (la compra, el pago fallido)" "$SIT" \
  "    if (s.calm && p.reason && ! s.locked && ! s.bn) s.bn" \
  "    if ((s.calm || s.locked) && p.reason && ! s.bn) s.bn"
mutar "la razón pisa su tipo (la viva pierde su punto)" "$SIT" \
  "s.bn = { type: 'razon', ...p.reason };" \
  "s.bn = { ...p.reason, type: 'razon' };"
mutar "la ESPERA no manda (sin su fila)" "$SIT" \
  "    { id: 'espera', when: (p) => p.waiting," \
  "    { id: 'espera', when: () => false,"
mutar "la espera lleva acción" "$SIT" \
  "bn: { type: 'espera', ...p.waiting }, locked: true, action: null }) }," \
  "bn: { type: 'espera', ...p.waiting }, locked: true }) },"
mutar "dos voces: la frase sigue con el banner" "$SIT" \
  "    const hasLine = Boolean(s.line) && !inCheckout && !menuOpen && !(s.bn && !isOpen);" \
  "    const hasLine = Boolean(s.line) && !inCheckout && !menuOpen;"

# ── Sin saturar ──────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la razón sale AL LLEGAR (sin esperar al primer desplazamiento)" "$SAT" \
  "    const razonClave = computed(() => (movido.value ? textoDe(reason()) : null));" \
  "    const razonClave = computed(() => textoDe(reason()));"
mutar "40 px ya cuentan como desplazarse" "$SAT" \
  "        if (win.scrollY <= DESPLAZADO) return;" \
  "        if (win.scrollY < DESPLAZADO) return;"
mutar "la de DECISIÓN no vuelve (cuenta como vista)" "$SAT" \
  "        if (! siempre && (gasto.vistas.has(clave) || gasto.resto.razon >= 1)) { razonOk.value = null; return; }" \
  "        if (gasto.vistas.has(clave)) { razonOk.value = null; return; }
        gasto.vistas.add(clave);"
mutar "sin presupuesto: una razón por pieza, no por visita" "$SAT" \
  "        if (! siempre && (gasto.vistas.has(clave) || gasto.resto.razon >= 1)) { razonOk.value = null; return; }" \
  "        if (! siempre && gasto.vistas.has(clave)) { razonOk.value = null; return; }"
mutar "sin calma entre cambios" "$SAT" \
  "    const calma = () => Math.max(ESPERA_DECISION, gasto.ultimo + CALMA - ahora());" \
  "    const calma = () => ESPERA_DECISION;"
mutar "lo elegido, sin prisa (1,2 s como las demás)" "$SAT" \
  "        }, chosen() ? ESPERA_ELEGIDO : siempre ? ESPERA_DECISION : calma());" \
  "        }, siempre ? ESPERA_DECISION : calma());"
mutar "la frase de DECISIÓN entra en el presupuesto" "$SAT" \
  "        if (! siempre && (gasto.frases.has(texto) || gasto.resto.frase >= 1)) return;" \
  "        if (gasto.frases.has(texto) || gasto.resto.frase >= 1) return;"
mutar "la última frase dicha NO vuelve al momento (se olvida al irse)" "$SAT" \
  "        if (! texto || dicha.value === texto) return;" \
  "        if (! texto) { dicha.value = null; return; }
        if (dicha.value === texto) return;"

# ── El relevo del hueco ──────────────────────────────────────────────────────────────────────────────────────────────
mutar "un cambio de ETIQUETA también releva el hueco" "$HUECO" \
  "        if (previo && (ahora?.clave ?? '') !== previo.clave) {" \
  "        if (previo && JSON.stringify(ahora) !== JSON.stringify(previo)) {"
mutar "lo que se va NO se quita" "$HUECO" \
  "            reloj = setTimeout(() => { sale.value = null; }, RELEVO_MS);" \
  "            reloj = null;"

# ── La pieza que se lee ──────────────────────────────────────────────────────────────────────────────────────────────
mutar "la pieza cuyo borde de abajo toca la mitad cuenta" "$PAG" \
  "    return secciones.find((s) => s && s.top <= media && s.bottom > media)?.zona ?? '';" \
  "    return secciones.find((s) => s && s.top <= media && s.bottom >= media)?.zona ?? '';"
mutar "la razón, también SIN botón a la vista" "$PAG" \
  "    if (! conBoton || ! razones) return null;" \
  "    if (! razones) return null;"
mutar "sin la razón de la llegada" "$PAG" \
  "    return razones[zona] ?? razones.llegada ?? null;" \
  "    return razones[zona] ?? null;"
mutar "una pieza sin frase toma la de la llegada" "$PAG" \
  "    return (frases && frases[zona]) || null;" \
  "    return (frases && (frases[zona] || frases.llegada || Object.values(frases)[0])) || null;"

# ── El aire de dentro, igual en los cuatro lados (el owner, al verlo) ────────────────────────────────────────────────
mutar "el borde otra vez DENTRO de lo medido (2px recortados abajo y a la derecha)" "$FORMA" \
  "        width: row ? (box.w ? \`\${box.w + 2}px\` : 'max-content') : '100%'," \
  "        width: row ? (box.w ? \`\${box.w}px\` : 'max-content') : '100%',"
mutar "el medidor con 8px de aire (la caja de fuera crece 2px)" "$FORMA" \
  "        padding: '7px'," \
  "        padding: '8px',"

# ── El dibujo de los iconos (PHP) ────────────────────────────────────────────────────────────────────────────────────
mutar "el icono NO se dibuja" "$DIB" \
  "                \$isla['razones'][\$zona]['svg'] = \$svg;" \
  "                \$isla['razones'][\$zona]['svg'] = null;"
mutar "el nombre del icono se lee SIN VALIDAR (una ruta de disco)" "$DIB" \
  "is_string(\$razon['icon'] ?? null) && (\$svg = Lucide::svg(\$razon['icon'])) !== ''" \
  "is_string(\$razon['icon'] ?? null) && (\$svg = Lucide::transform((string) @file_get_contents(resource_path('icons/lucide/icons/'.\$razon['icon'].'.svg')))) !== ''"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
