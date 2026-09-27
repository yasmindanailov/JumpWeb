#!/usr/bin/env bash
# Arnés de mutación de `#825` (`specs/isla-y-landing-nueva.md` §4.13): «Añade a tus hijos» es TAREA solo si el PRODUCTO es
# de menores —su tramo de edad del panel con tope por debajo de la mayoría de edad (`TicketType::onlyGuestsUnder`)—; si
# puede entrar un adulto, una línea opcional. Mi cuenta (`AntesDeVenir`), la API (`minors_only`, 1.45.0) y «¡Reservado!»
# de la compra (`pantallaListo`). Lo que se ve en vivo lo juzga `scripts/sonda-cuenta.mjs` (su parte 9).
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-hijos-de-producto.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=MeReservationBeforeVisitTest|MeReservationChangeFactsTest"
JS="docker compose exec -u sail -T laravel.test node --test resources/js/isla/compra/pasos.test.js resources/js/isla/cuenta/antes.test.js resources/js/sidebar/outcome.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Models/TicketType.php
    app/Http/Cuenta/AntesDeVenir.php
    app/Http/Resources/Api/V1/OrderItemResource.php
    resources/js/isla/compra/pasos.js
    resources/js/isla/cuenta/antes.js
    resources/js/sidebar/outcome.js
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $PHP >/dev/null 2>&1 && $JS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
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

TT=app/Domain/Booking/Models/TicketType.php
AV=app/Http/Cuenta/AntesDeVenir.php
OI=app/Http/Resources/Api/V1/OrderItemResource.php
PA=resources/js/isla/compra/pasos.js
AN=resources/js/isla/cuenta/antes.js
OU=resources/js/sidebar/outcome.js

# ── 1 · El hecho del producto ────────────────────────────────────────────────────────────────────
mutar "el tope de edad, INCLUIDO en la mayoría (un «hasta 18» contaría como menores)" "$TT" "(int) \$this->guest_age_max < \$age;" "(int) \$this->guest_age_max <= \$age;"
mutar "sin tope se da por hecho que son menores" "$TT" "return \$this->guest_age_max !== null && (int)" "return (int)"

# ── 2 · Mi cuenta ────────────────────────────────────────────────────────────────────────────────
mutar "tarea en toda entrada (lo de antes de #825)" "$AV" "if (! \$tipo->onlyGuestsUnder(Dependent::ADULT_AGE)) {" "if (false) {"
mutar "la línea opcional sigue con menores en la cuenta" "$AV" "return \$conMenores ? null : [" "return ["
mutar "la línea opcional sin su pantalla en el sitio" "$AV" "'action' => ['label' => __(\$t.'boton_isla'), 'url' => \$puerta, 'via' => 'account']," "'action' => ['label' => __(\$t.'boton_isla'), 'url' => \$puerta, 'via' => 'link'],"

# ── 3 · La API ───────────────────────────────────────────────────────────────────────────────────
mutar "minors_only siempre verdadero" "$OI" "'minors_only' => \$item->ticketType?->onlyGuestsUnder(Dependent::ADULT_AGE) ?? false," "'minors_only' => true,"

# ── 4 · «¡Reservado!» y el bloque ────────────────────────────────────────────────────────────────
mutar "«¡Reservado!» pide los hijos en cualquier entrada" "$PA" "firmaDentro && deMenores ?" "firmaDentro ?"
mutar "«¡Reservado!» con una sola entrada de menores no basta" "$PA" "lineas.some((l) => l.minors_only)" "lineas.every((l) => l.minors_only)"
# El booleano se normaliza en UN sitio, la línea del pedido (`confirmationLine`): de ahí en adelante ya es estricto.
mutar "la línea del pedido deduce en vez de transportar" "$OU" "minors_only: item?.minors_only === true," "minors_only: Boolean(item?.minors_only),"
mutar "los extras también se abrirían en la cuenta" "$AN" "enLaCuenta: opcional.action?.via === 'account'," "enLaCuenta: true,"
mutar "la línea suelta sigue con su raya" "$AN" "suelta: ! porHacer.length && ! estado }" "suelta: false }"
mutar "la tarea de los hijos sin su icono" "$AN" ", dependents: 'user-round-plus' };" " };"

echo
echo "$muerden de $total mutantes muertos"
[[ "$muerden" == "$total" ]]
