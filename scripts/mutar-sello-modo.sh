#!/usr/bin/env bash
# Arnés de mutación del SELLO DEL MODO de un complemento (`specs/hora-extra.md` §12,
# `DECISIONES #448`) — la T1: se ESCRIBE el hecho y todavía no lo lee nadie.
#
# La propiedad que protege es una sola: **la unidad con la que se contó una cantidad viaja en la
# línea, no en el catálogo**. Antes de `#448`, `order_items.quantity` de una hija significaba BLOQUES
# o PERSONAS según dijera `product_addons.quantity_mode` — una columna de CONFIGURACIÓN—, así que
# cambiar el enganche reinterpretaba lo ya vendido: medido en producción, 5,00 € → 40,00 € y
# 4,00 € → 60,00 € en la primera edición de cantidad de esas fiestas.
#
# Cubre las TRES puertas que sellan, los DOS silencios legítimos, el saneo del vocabulario y los DOS
# rellenos de la migración con sus controles.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca `grep
# passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con
# `git checkout` (`#181`: un `checkout` se llevó trabajo sin commitear).
#
# ❗❗ **LA COPIA VA EN RUTA FIJA Y SE REPARA AL ARRANCAR, y eso NO es una precaución teórica**
# (`#448`): el molde de la casa —`mktemp -d` + `trap … EXIT`— **deja el árbol MUTADO si el proceso
# muere sin ejecutar el trap** (SIGKILL, la sesión que se cae, el contenedor que se va), y encima con
# la copia en un temporal que ya nadie sabe encontrar. Pasó de verdad: dos mutaciones se quedaron
# vivas en `PostFormAddons` e `ItemEditPricing`, y lo cazó la suite completa por un solo fallo.
# *Un instrumento que puede dejar el repo peor de como lo encontró no es un instrumento, es un
# riesgo.* Con la copia en sitio conocido, **la siguiente ejecución REPARA y lo dice**.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='GuestCountTest|AddonQuantityModeSealTest|AddonQuantityModeReadsTest|SoldLineUnitHasOneSourceTest|AddonQuantityModeBackfillTest|PostFormAddonsTest|MixedPartySurchargeTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

# Ruta FIJA y gitignorada (`storage/` no viaja), para que un corte sea REPARABLE.
TMP="storage/app/mutaciones/sello-modo"
FICHEROS=(
    app/Domain/Booking/Services/AddonResolver.php
    app/Domain/Booking/Services/ItemEditPricing.php
    app/Domain/Booking/Services/OrderItemEditor.php
    app/Domain/Booking/Services/PostFormAddons.php
    app/Domain/Booking/Services/MixedPartySurcharge.php
    app/Domain/Booking/Models/ProductAddon.php
    database/migrations/2026_09_08_120000_add_addon_quantity_mode_to_order_items.php
    app/Domain/Booking/Services/GuestCountAdjuster.php
    app/Domain/Booking/Models/OrderItem.php
)
# Solo restaura lo que TIENE copia: un corte a mitad del arranque deja la carpeta incompleta, y sin
# esta guarda la reparación escupe seis `cannot stat` que tapan el aviso que sí importa.
restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

# ── Reparación de un corte ANTERIOR, antes de tocar nada ────────────────────────────────────
# Si hay copia de una ejecución que no llegó a restaurar, el árbol puede estar mutado AHORA mismo.
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

    # ❗❗❗ **MUTAR UN FICHERO SIN COPIA LO DEJA MUTADO PARA SIEMPRE, y pasó de verdad** (`#449`):
    # se añadió una variable con la ruta pero se olvidó meterla en `FICHEROS`, así que no se copió,
    # el `cp` de restauración falló en silencio, **las tres mutaciones se ACUMULARON** en el árbol —y
    # la comprobación final de integridad dijo «árbol idéntico» porque solo recorre `FICHEROS`—.
    # ⚠️⚠️ Y lo peor no es el fichero sucio: es que **el veredicto deja de valer**, porque cada
    # mutación posterior corre sobre código ya mutado. Salió «25/25» y no significaba nada.
    # ▶ Por eso esto ABORTA en vez de avisar: un arnés que sigue tras perder su red da un número
    # peor que no dar ninguno.
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

RES=app/Domain/Booking/Services/AddonResolver.php
PRI=app/Domain/Booking/Services/ItemEditPricing.php
EDI=app/Domain/Booking/Services/OrderItemEditor.php
PFA=app/Domain/Booking/Services/PostFormAddons.php
MIX=app/Domain/Booking/Services/MixedPartySurcharge.php
PIV=app/Domain/Booking/Models/ProductAddon.php
ITM=app/Domain/Booking/Models/OrderItem.php
MIG=database/migrations/2026_09_08_120000_add_addon_quantity_mode_to_order_items.php
GCA=app/Domain/Booking/Services/GuestCountAdjuster.php

echo '── Las tres PUERTAS que sellan ──'

# 1 · la venta pública (web, API y mostrador pasan por aquí).
mutar "la VENTA deja de sellar" "$RES" \
  "                'addon_quantity_mode' => \$pivot->quantityUnit()," \
  "                'addon_quantity_mode' => null,"

# 2 · el editor del panel — el compositor.
mutar "el COMPOSITOR del editor devuelve siempre null" "$PRI" \
  "            \$addQuantityModes[\$typeId] = \$pivot?->quantityUnit();" \
  "            \$addQuantityModes[\$typeId] = null;"

# 2 bis · el editor — la escritura.
mutar "el EDITOR no escribe el sello al crear la hija" "$EDI" \
  "                    'addon_quantity_mode' => \$addonAddQuantityModes[\$typeId] ?? null," \
  "                    'addon_quantity_mode' => null,"

# 3 · el post-form. Es el CONTROL declarado de §12.6: hoy solo puede valer `fixed`, y se sella igual
#     para que la puerta siga diciendo con qué unidad se vendió si esa prohibición cayera.
mutar "el POST-FORM deja de sellar" "$PFA" \
  "                'addon_quantity_mode' => \$addon->pivot->quantityUnit()," \
  "                'addon_quantity_mode' => null,"

# 3 bis · el RE-SELLO al cambiar de producto (`PAY-19`: pack nuevo → sello nuevo). Una hija que
#         sobrevive al cambio la gobierna OTRA fila de pivote, que puede declarar otro modo.
mutar "cambiar de PRODUCTO no re-sella las hijas que sobreviven" "$EDI" \
  "            if (\$productChanged) {
                foreach (\$locked->children()->whereNull('cancelled_at')->get() as \$child) {" \
  "            if (false) {
                foreach (\$locked->children()->whereNull('cancelled_at')->get() as \$child) {"

# 3 ter · CONTROL del anterior: re-sellar SIEMPRE (aunque el producto no cambie) pisaría el sello de
#         una hija cuyo enganche cambió de modo por detrás — que es justo lo que el sello evita.
mutar "el re-sello se dispara aunque el producto NO cambie" "$EDI" \
  "            if (\$productChanged) {
                foreach (\$locked->children()->whereNull('cancelled_at')->get() as \$child) {" \
  "            if (true) {
                foreach (\$locked->children()->whereNull('cancelled_at')->get() as \$child) {"

echo
echo '── Los dos SILENCIOS legítimos ──'

# 4 · los portadores de fiesta mixta NO tienen enganche del que copiar el modo. Que alguien "termine
#     el trabajo" poniéndoles `fixed` afirmaría de esa línea algo que nadie midió.
mutar "un PORTADOR de fiesta mixta pasa a sellarse como fixed" "$MIX" \
  "            \$child = \$principal->children()->create([
                'order_id' => \$principal->order_id,
                'ticket_type_id' => \$carrierId," \
  "            \$child = \$principal->children()->create([
                'addon_quantity_mode' => 'fixed',
                'order_id' => \$principal->order_id,
                'ticket_type_id' => \$carrierId,"

# 5 · sin enganche, el compositor debe callar y no inventar `fixed`.
mutar "sin enganche el compositor inventa fixed en vez de callar" "$PRI" \
  "            \$addQuantityModes[\$typeId] = \$pivot?->quantityUnit();" \
  "            \$addQuantityModes[\$typeId] = \$pivot?->quantityUnit() ?? 'fixed';"

echo
echo '── T2 · LAS TRES LECTURAS ──'

# El lector: si el sello deja de mandar, todo vuelve al catálogo vivo.
mutar "el LECTOR ignora el sello y devuelve el catálogo vivo" "$RES" \
  "        \$sealed = \$child->addon_quantity_mode;" \
  "        \$sealed = null;"

# DINERO: el re-escalado vuelve a decidir con el pivote.
mutar "el re-escalado de DINERO vuelve a preguntar al pivote" "$EDI" \
  "if (\$pivot === null || ! AddonResolver::wasSoldPerGuest(\$child, \$pivot)) {" \
  "if (\$pivot === null || ! \$pivot->isPerGuest()) {"

# DINERO · la segunda mitad: decidir con el sello y CALCULAR con el catálogo (el defecto que el
# propio caso cazó: la línea se quedaba en CERO).
mutar "decide con el sello pero calcula con el catálogo" "$EDI" \
  "                    \$newChildQty = AddonResolver::effectiveQuantityForUnit(\$pivot, \$unidad, 0, \$newQty);" \
  "                    \$newChildQty = AddonResolver::effectiveQuantity(\$pivot, 0, \$newQty);"

# AFORO: los minutos vuelven al catálogo — el caso de los 900 minutos.
mutar "los MINUTOS vuelven a salir del catálogo (900 vs 60)" "$EDI" \
  "            \$minutes += AddonOccupancy::minutesForUnit(
                \$child->ticketType,
                AddonResolver::soldQuantityUnit(\$child, \$pivot),
                \$qty,
            );" \
  "            \$minutes += \$pivot !== null
                ? AddonOccupancy::extraMinutes(\$child->ticketType, \$pivot, \$qty)
                : AddonOccupancy::minutesForBlocks(\$child->ticketType, \$qty);"

# PERMISO: el bloqueo vuelve al catálogo.
mutar "el PERMISO vuelve a preguntar al pivote" "$EDI" \
  "            \$perGuest = AddonResolver::wasSoldPerGuest(\$child, \$pivot);" \
  "            \$perGuest = \$pivot?->isPerGuest() ?? false;"

# La regla de DIVERGENCIA: sin ella, la línea queda editable y SIN TECHO.
mutar "la DIVERGENCIA deja la línea sin techo" "$EDI" \
  "            if (\$divergente) {
                \$caps[] = (int) \$child->quantity;
            }" \
  "            if (false) {
                \$caps[] = (int) \$child->quantity;
            }"

echo
echo '── El SANEO del vocabulario ──'

# 6 · un valor desconocido tiene que caer a `fixed` (el lado que reserva igual o más sala), no
#     propagarse ni caer al otro lado.
mutar "un modo desconocido se lee como per_guest" "$PIV" \
  "        return in_array(\$mode, self::MODES, true) ? \$mode : self::MODE_FIXED;" \
  "        return in_array(\$mode, self::MODES, true) ? \$mode : self::MODE_PER_GUEST;"

# 7 · CONTROL del anterior: un saneo que devolviera SIEMPRE `fixed` perdería el modo real.
mutar "el saneo aplasta también los modos VÁLIDOS" "$PIV" \
  "        return in_array(\$mode, self::MODES, true) ? \$mode : self::MODE_FIXED;" \
  "        return self::MODE_FIXED;"

echo
echo '── La DIVERGENCIA se DICE ──'

# Si deja de detectarse, la línea queda acotada y nadie lo explica.
mutar "la divergencia deja de detectarse" "$ITM" \
  "        return \$pivot !== null && \$pivot->quantityUnit() !== \$sealed;" \
  "        return false;"

# CONTROL: el silencio (sin sello) NO es divergencia — si no, se marcaría toda línea vieja.
mutar "una línea SIN sello se marca como divergente" "$ITM" \
  "        if (\$this->parent_item_id === null || ! is_string(\$sealed) || \$sealed === '') {" \
  "        if (\$this->parent_item_id === null) {"

echo
echo '── T3 · el CANDADO re-apuntado ──'

# El candado vuelve a mirar TODA venta viva: se cierra para siempre otra vez (la ventana que el
# owner midió que no se abre nunca).
mutar "el candado vuelve a bloquear toda venta viva, sellada o no" "$PIV" \
  "                ->whereNull('addon_quantity_mode'))" \
  "                )"

# El espejo: si dejara de mirar el sello en la otra dirección —soltando también lo NO sellado—, el
# agujero se reabre en silencio para las líneas anteriores al despliegue.
mutar "el candado se suelta también con líneas SIN sellar" "$PIV" \
  "        foreach (\$parents as \$parent) {
            if (! \$parent->isFinishedInPractice()) {
                return true;
            }
        }" \
  "        foreach (\$parents as \$parent) {
            if (false) {
                return true;
            }
        }"

echo
echo '── `#449` · los por-invitado siguen a los INVITADOS ──'

# El post-form vuelve a no tocar las hijas: el defecto original.
mutar "el post-form deja de re-escalar los por-invitado" "$GCA" \
  "            addonRescales: \$this->rescalePerGuestChildren(\$item, \$desired)," \
  "            addonRescales: [],"

# CONTROL: re-escalar TODA hija multiplicaría cada complemento de la reserva.
mutar "re-escala también los de cantidad FIJA" "$GCA" \
  "            if (\$pivot === null || ! AddonResolver::wasSoldPerGuest(\$child, \$pivot)) {" \
  "            if (\$pivot === null) {"

# El dinero deja de atarse a su hija: el libro se queda sin saber de qué línea sale.
mutar "el dinero del re-escalado no se escribe" "$GCA" \
  "                if (\$rescale['delta'] === 0) {" \
  "                if (true) {"

echo
echo '── El RELLENO de la migración ──'

# 8 · el relleno A tiene que alcanzar SOLO a los extensores.
mutar "el relleno A sella TODO complemento, no solo los extensores" "$MIG" \
  "            ->whereIn('ticket_type_id', \$extenderIds)" \
  "            ->whereNotNull('ticket_type_id')"

# 9 · el relleno B tiene que alcanzar SOLO a lo que no pudo nacer así.
mutar "el relleno B sella TODA línea per_guest, coherente o no" "$MIG" \
  "            ->whereColumn('c.quantity', '!=', 'p.quantity')" \
  "            ->whereNotNull('c.quantity')"

# 10 · una línea cancelada ya no la re-escala nadie: tocarla es escribir sobre lo que no participa.
mutar "el relleno B alcanza también a las canceladas" "$MIG" \
  "            ->whereNull('c.cancelled_at')
            ->whereNull('c.addon_quantity_mode')" \
  "            ->whereNull('c.addon_quantity_mode')"

# 11 · idempotencia: el relleno solo escribe donde no hay nada.
mutar "el relleno A pisa un sello que ya existía" "$MIG" \
  "            ->whereNotNull('parent_item_id')
            ->whereNull('addon_quantity_mode')" \
  "            ->whereNotNull('parent_item_id')"

echo
echo "mutaciones que muerden: ${muerden}/${total}"

# ── El árbol tiene que quedar EXACTAMENTE como estaba ───────────────────────────────────────
# La otra mitad de la reparación de arriba: `mutar()` restaura tras cada vuelta, pero un patrón que
# no case, una ruta cambiada o una salida a medias podrían dejar algo tocado. Se comprueba en vez de
# suponerse — es lo que habría cazado el corte de `#448` en el acto.
tocados=0
for f in "${FICHEROS[@]}"; do
    cmp -s "$f" "$TMP/$(basename "$f")" || { echo "  ✗ QUEDÓ TOCADO: $f"; tocados=$((tocados + 1)); }
done
if [ "$tocados" -gt 0 ]; then
    echo "✗ el arnés deja ${tocados} fichero(s) distintos del original: NO commitees sin mirar." >&2
    exit 1
fi
echo '✓ árbol idéntico al de partida'
echo
echo 'Lo que este arnés NO puede mutar, y se dice (§12.6.1): que el sello del editor salga de'
echo '`ItemEditPricing` y no de `$offeredAddons`. Las dos lecturas devuelven el MISMO pivote con el'
echo 'catálogo quieto, así que la diferencia solo aparece bajo una carrera — no hay mutación'
echo 'mecánica que la distinga. Lo sostiene la revisión, no un caso.'
[ "$muerden" -eq "$total" ]
