#!/usr/bin/env bash
# Arnés de mutación de la BANDA DE FASES del cajón (`DECISIONES #555`, carril del SPA · T4·4b).
#
# Las propiedades que protege, en una línea cada una:
#   · la banda vive en las CINCO pantallas del camino, y en ninguna más;
#   · una fase por PANTALLA: el contador no repite su número;
#   · el «Volver» dice a dónde vuelve, y su rótulo sale de la MISMA fila que su destino;
#   · ningún paso del embudo pinta su propio «Volver»;
#   · el rótulo de fase no gasta anchura en decoración —con cinco, no caben—;
#   · la fase del carrito llama a esa pantalla por su nombre;
#   · el contexto muere en la cesta, donde deja de haber UNA línea.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
# ⚠️ Sin comillas invertidas en los rótulos: dentro de comillas dobles bash las ejecuta (`#489`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='SidebarPhaseBandTest|SidebarProgressParityTest'
PHPRUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"
JSRUN="docker compose exec -u sail -T laravel.test npx --no-install node --test resources/js/sidebar/progress.test.js"

TMP="storage/app/mutaciones/banda-fases"
FICHEROS=(
    public/css/site.css
    resources/js/sidebar/progress.js
    resources/js/sidebar/steps/CartStep.vue
    resources/js/sidebar/sections/PurchaseSection.vue
    lang/es/tickets.php
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

# ⚠️ **Los ficheros se copian por su BASENAME**, así que dos con el mismo nombre en carpetas distintas
# se pisarían la copia y la restauración devolvería el equivocado. Aquí los cinco son distintos, y se
# comprueba en vez de suponerse.
if [ "$(printf '%s\n' "${FICHEROS[@]}" | xargs -n1 basename | sort -u | wc -l)" -ne "${#FICHEROS[@]}" ]; then
    echo '✗ dos ficheros comparten basename: la copia de seguridad se pisaría a sí misma.' >&2
    exit 1
fi

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
JS=resources/js/sidebar/progress.js
VUE=resources/js/sidebar/steps/CartStep.vue
SEC=resources/js/sidebar/sections/PurchaseSection.vue
ES=lang/es/tickets.php

echo '── Dónde vive la banda ──'

mutar "la banda vuelve a existir solo en los pasos 2 y 3" "$JS" \
  "    { step: STEPS.CART, label: 'phase_cart', back: 'back', backTo: STEPS.CATALOG, clear: 'selection' }," \
  ""

mutar "la banda se cuela en el catalogo" "$JS" \
  "    { step: STEPS.DATE, label: 'phase_date'," \
  "    { step: STEPS.CATALOG, label: 'phase_date', back: 'back', backTo: STEPS.CATALOG, clear: null },
    { step: STEPS.DATE, label: 'phase_date',"

echo
echo '── Una fase por pantalla ──'

mutar "el contador vuelve a avanzar DENTRO de la pantalla de la hora" "$JS" \
  "        active: actual + 1," \
  "        active: actual + 1 + (step === STEPS.TIME && hasTime ? 1 : 0),"

mutar "el total deja de contar las fases y se clava en tres" "$JS" \
  "        total: FASES.length," \
  "        total: 3,"

echo
echo '── El «Volver» ──'

mutar "el rotulo del Volver deja de decir a donde vuelve" "$JS" \
  "        backLabel: t(messages, FASES[actual].back)," \
  "        backLabel: t(messages, 'back'),"

mutar "el destino se separa de su rotulo" "$JS" \
  "backTo: STEPS.CART, clear: null },
    { step: STEPS.PAY" \
  "backTo: STEPS.CATALOG, clear: null },
    { step: STEPS.PAY"

mutar "el embudo vuelve a decidir el destino por su cuenta" "$SEC" \
  '    const { to, clear } = backPlan(store.step) ?? {};' \
  '    const { to, clear } = { to: 1, clear: null };'

mutar "el carrito recupera su Volver propio" "$VUE" \
  '    <h3 class="wiz__title">' \
  '    <button type="button" class="bk-back purchase__back" @click="$emit(&apos;back&apos;)"><span>x</span></button>
    <h3 class="wiz__title">'

echo
echo '── Que los cinco rótulos quepan ──'

mutar "el rotulo de fase recupera las mayusculas" "$CSS" \
  '.bk-seg__label { font-size: var(--fs-label); font-weight: var(--fw-bold); color: var(--fg-mute);' \
  '.bk-seg__label { font-size: var(--fs-label); font-weight: var(--fw-bold); text-transform: uppercase; color: var(--fg-mute);'

mutar "el rotulo de fase recupera el espaciado de letra" "$CSS" \
  '.bk-seg__label { font-size: var(--fs-label); font-weight: var(--fw-bold); color: var(--fg-mute);' \
  '.bk-seg__label { font-size: var(--fs-label); font-weight: var(--fw-bold); letter-spacing: 0.06em; color: var(--fg-mute);'

mutar "un rotulo de fase se alarga hasta recortarse" "$ES" \
  "    'phase_identify' => 'Quién eres'," \
  "    'phase_identify' => 'Quién eres tú',"

mutar "la fase del carrito deja de llamarlo por su nombre" "$ES" \
  "    'phase_cart' => 'Tu carrito'," \
  "    'phase_cart' => 'Tu cesta',"

echo
echo '── Con sesión, «Quién eres» sobra ──'

mutar "la fase de identificarse se pinta siempre, haya sesion o no" "$JS" \
  "    return FASES.filter((f) => f.step !== STEPS.IDENTIFY || pideIdentificarse || step === STEPS.IDENTIFY);" \
  "    return FASES;"

mutar "la fase NO vuelve cuando el cliente esta en ella" "$JS" \
  " || pideIdentificarse || step === STEPS.IDENTIFY);" \
  " || pideIdentificarse);"

mutar "el embudo lee la sesion VIVA en vez de la del servidor" "$SEC" \
  '    pideIdentificarse: ! props.userId,' \
  '    pideIdentificarse: ! cartStore.owner,'

echo
echo '── El contexto ──'

mutar "el contexto sobrevive a la cesta, donde ya no hay UNA linea" "$JS" \
  '    const context = step === STEPS.DATE || step === STEPS.TIME' \
  '    const context = true'

echo
echo "── veredicto: ${muerden}/${total} muerden ──"
# ⚠️⚠️ **AL SALIR, EL BUNDLE SSR QUEDA RANCIO** (`#554`): restaurar hace `touch` sobre las fuentes, así
# que `SidebarDomContractTest` —que renderiza el BUNDLE— saca 35 casos en rojo con el árbol limpio.
echo '⚠️  corre `npm run build:ssr` antes de la suite: restaurar ha dejado las fuentes más nuevas que el bundle.'
[ "$muerden" -eq "$total" ] || exit 1
