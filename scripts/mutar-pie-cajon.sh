#!/usr/bin/env bash
# Arnés de mutación del PIE DEL CAJÓN (`DECISIONES #554`, carril del SPA · T4·4a).
#
# Las propiedades que protege, en una línea cada una:
#   · el CTA ocupa la fila entera, debajo del total, y no comparte fila con él;
#   · mide EXACTAMENTE el alto grande del sistema (56), y el número sale de sus partes;
#   · el paso 08 ancla en lo que SE COBRA, no en el total, y el total sube a la banda;
#   · la banda dice «Total» y «A pagar en el parque», no «Pagas ahora»;
#   · sin señal el rótulo no insinúa un resto que no existe;
#   · el MOTIVO de que no se pueda avanzar vive en el rótulo, no dentro del botón apagado;
#   · la banda del pago es otra superficie que el pie, y entre las dos no hay línea.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
# ⚠️ Sin comillas invertidas en los rótulos: dentro de comillas dobles bash las ejecuta (`#489`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='SidebarFootShapeTest|SidebarCartParityTest|SidebarActionRoleTest'
PHPRUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"
JSRUN="docker compose exec -u sail -T laravel.test npx --no-install node --test resources/js/sidebar/foot.test.js"

TMP="storage/app/mutaciones/pie-cajon"
FICHEROS=(
    public/css/site.css
    resources/js/sidebar/foot.js
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
        echo '✓ restaurados desde la copia. Comprueba con git diff antes de seguir.'
    fi
    rm -rf "$TMP"
fi

mkdir -p "$TMP"
trap 'restaurar; rm -rf "$TMP"' EXIT INT TERM
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

# ⚠️ El veredicto son las DOS redes: la de PHP mira la hoja y los importes contra el diccionario, y la
# de JS la conducta del módulo. Una mutación de `foot.js` que solo mordiera en JS quedaría fuera del
# veredicto si aquí solo se corriera PHP — y al revés.
verde() { $PHPRUN >/dev/null 2>&1 && $JSRUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde (PHP + JS)'
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

CSS=public/css/site.css
JS=resources/js/sidebar/foot.js

echo '── El CTA a fila completa ──'

mutar "el total y el CTA vuelven a compartir fila" "$CSS" \
  '.bk-foot__row { display: flex; flex-direction: column; align-items: stretch;' \
  '.bk-foot__row { display: flex; flex-direction: row; align-items: center;'

mutar "el CTA recupera su flex y se reparte el ancho" "$CSS" \
  '.bk-cta { display: inline-flex; width: 100%;' \
  '.bk-cta { display: inline-flex; flex: 1; width: auto;'

echo
echo '── El alto grande del sistema ──'

mutar "el alto declarado baja al objetivo tactil" "$CSS" \
  'min-height: 56px; align-items: center; justify-content: center; gap: 9px;' \
  'min-height: 48px; align-items: center; justify-content: center; gap: 9px;'

mutar "el relleno vuelve al que no es ningun tamano declarado" "$CSS" \
  'padding: var(--sp-18) 34px; line-height: 1.25;' \
  'padding: var(--sp-15) 34px; line-height: 1.25;'

# ⚠️ La que de verdad cuesta ver: sin `line-height` propio la línea heredada mide 21 y el botón sale
# a 57 px. El `min-height` sigue diciendo 56 y nadie lo nota.
mutar "el CTA pierde su interlineado y el 56 deja de ser cierto" "$CSS" \
  'padding: var(--sp-18) 34px; line-height: 1.25; border: 0;' \
  'padding: var(--sp-18) 34px; border: 0;'

mutar "el CTA baja al peso del secundario de la web" "$CSS" \
  'font-weight: var(--fw-black); font-size: var(--fs-button); transition: transform var(--dur-estado)' \
  'font-weight: var(--fw-bold); font-size: var(--fs-button); transition: transform var(--dur-estado)'

echo
echo '── El total y su cifra ──'

mutar "el rotulo y la cifra dejan de ir a los extremos" "$CSS" \
  '.bk-foot__total { display: flex; align-items: baseline; justify-content: space-between;' \
  '.bk-foot__total { display: flex; align-items: baseline; justify-content: flex-start;'

mutar "las dos tallas se alinean por su centro" "$CSS" \
  '.bk-foot__total { display: flex; align-items: baseline;' \
  '.bk-foot__total { display: flex; align-items: center;'

echo
echo '── La banda del pago ──'

mutar "la banda vuelve a la superficie del pie" "$CSS" \
  '.bk-paybreakdown { flex: none; display: flex; flex-direction: column; gap: var(--sp-6); padding: var(--sp-12) var(--sp-20); background: var(--bg-soft);' \
  '.bk-paybreakdown { flex: none; display: flex; flex-direction: column; gap: var(--sp-6); padding: var(--sp-12) var(--sp-20); background: var(--bg);'

mutar "la banda recupera la linea de mas contra el pie" "$CSS" \
  'background: var(--bg-soft); border-top: 1px solid var(--line); }' \
  'background: var(--bg-soft); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }'

echo
echo '── El ancla del paso 08 ──'

mutar "el paso 08 vuelve a anclar en el TOTAL" "$JS" \
  '        amount: money(cartOnlineCents),
        disabled: false,
        icon: '"'"'card'"'"',' \
  '        amount: money(cartTotalCents),
        disabled: false,
        icon: '"'"'card'"'"','

mutar "la banda anuncia lo que se cobra en vez del total" "$JS" \
  '                    { label: t(messages, '"'"'total'"'"'), value: money(cartTotalCents) },' \
  '                    { label: t(messages, '"'"'footer_pay_now'"'"'), value: money(cartTotalCents) },'

mutar "la segunda fila de la banda pierde su verbo" "$JS" \
  '{ label: t(messages, '"'"'footer_park_total'"'"'), value: money(parkCents(state)) },' \
  '{ label: t(messages, '"'"'pay_at_park'"'"'), value: money(parkCents(state)) },'

mutar "sin senal el rotulo insinua un resto que no existe" "$JS" \
  "        label: t(messages, conDesglose ? 'footer_pay_now' : 'total')," \
  "        label: t(messages, 'footer_pay_now'),"

echo
echo '── El motivo en el rótulo ──'

mutar "el dia sin elegir deja de decir por que" "$JS" \
  "            label: state.hasDate ? t(messages, 'total') : t(messages, 'footer_pick_day')," \
  "            label: t(messages, 'total'),"

mutar "la hora sin elegir deja de decir por que" "$JS" \
  "        label: hasTime ? t(messages, 'total') : t(messages, 'footer_pick_time')," \
  "        label: t(messages, 'total'),"

echo
echo "── veredicto: ${muerden}/${total} muerden ──"
[ "$muerden" -eq "$total" ] || exit 1
