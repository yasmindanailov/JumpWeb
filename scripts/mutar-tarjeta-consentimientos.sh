#!/usr/bin/env bash
# Arnés de mutación de `ConsentCardTest` + la mitad JS (`privacy.test.js`) — `#346`.
#
# Las mutaciones son los defectos REALES: el que el owner encontró (el `nowrap` sobre la línea) y los
# que estarían a un paso de volver. Reglas heredadas: **verde antes de mutar** y **veredicto por
# código de salida**, nunca buscando una cadena en la salida del runner.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=ConsentCardTest"
JS="docker compose exec -u sail -T laravel.test npx node --test resources/js/sidebar/account/privacy.test.js"

verde() { $PHP >/dev/null 2>&1 && $JS >/dev/null 2>&1; }

S=public/css/site.css
Z=resources/js/sidebar/account/zones/PrivacyZone.vue
J=resources/js/sidebar/account/privacy.js

if ! git diff --quiet -- "$S" "$Z" "$J"; then
    echo "✗ hay cambios sin commitear en los ficheros que se van a mutar: commitea antes (#181)." >&2
    exit 1
fi
if ! verde; then
    echo "✗ NO está verde antes de mutar: el veredicto no valdría nada." >&2
    exit 1
fi
echo "✓ base verde (ConsentCardTest + privacy.test.js)"

muerden=0; total=0
mutar() {
    local nombre="$1" fichero="$2" script="$3"
    total=$((total + 1))
    python3 -c "$script" || { echo "  ⚠ no se pudo aplicar: $nombre"; git checkout -- "$fichero"; return; }
    if verde; then echo "  ✗ NO muerde: $nombre"; else echo "  ✓ muerde:    $nombre"; muerden=$((muerden + 1)); fi
    git checkout -- "$fichero"
}

# ── EL DEFECTO DEL OWNER, tal cual: el `nowrap` vuelve a la LÍNEA ────────────────────────────────
mutar "el nowrap vuelve a la línea entera (el defecto de #344)" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('.account__consent-meta { display: flex; flex-wrap: wrap; align-items: baseline; color: var(--fg-mute); }','.account__consent-meta { color: var(--fg-mute); white-space: nowrap; }',1))"

mutar "el trozo deja de ser indivisible (una fecha se parte por la mitad)" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('.account__consent-part { white-space: nowrap; }','.account__consent-part { white-space: normal; }',1))"

mutar "la fila deja de envolver" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px;','display: flex; justify-content: space-between; gap: 12px;',1))"

mutar "el separador se retira de la hoja" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('.account__consent-part + .account__consent-part::before { content: \"·\"; margin: 0 6px; }','',1))"

mutar "encendido se pinta con el color de ACCIÓN" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('.switch__input:checked { background: var(--ok); border-color: var(--ok); }','.switch__input:checked { background: var(--action); border-color: var(--action); }',1))"

mutar "el input se esconde a 0×0 (el anillo de foco cae sobre nada)" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('position: relative; flex: none; width: 44px; height: 26px;','position: relative; flex: none; width: 0; height: 0;',1))"

mutar "se pierde el suelo táctil de 44" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('.switch { display: flex; align-items: center; gap: 12px; min-height: var(--tap-min);','.switch { display: flex; align-items: center; gap: 12px;',1))"

mutar "el interruptor vuelve a anunciarse como casilla" "$Z" \
  "p='$Z';s=open(p).read();open(p,'w').write(s.replace('type=\"checkbox\" role=\"switch\"','type=\"checkbox\"',1))"

# ── La mitad JS ─────────────────────────────────────────────────────────────────────────────────
mutar "los trozos se vuelven a juntar en una cadena única" "$J" \
  "p='$J';s=open(p).read();open(p,'w').write(s.replace(\"].map((part) => String(part ?? '').trim()).filter(Boolean),\",\"].map((part) => String(part ?? '').trim()).filter(Boolean).join(' · ').split('@@'),\",1))"

mutar "el colapso por tipo se retira (vuelven las cinco filas iguales)" "$J" \
  "p='$J';s=open(p).read();open(p,'w').write(s.replace('return rows.reduce((keep, consent, index) => {','return rows.reduce((keep, consent, index) => { keep.push([consent, index]); return keep; }, []).concat([]).length ? rows.map((c, i) => [c, i]) : []; return rows.reduce((keep, consent, index) => {',1))"

echo
echo "── ${muerden}/${total} mutaciones muerden ──"
verde && echo "✓ árbol restaurado y verde" || { echo "✗ el árbol NO volvió a verde" >&2; exit 1; }
[ "$muerden" -eq "$total" ] || exit 1
