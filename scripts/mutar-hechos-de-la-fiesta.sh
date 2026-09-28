#!/usr/bin/env bash
# Arnés de mutación de LOS HECHOS DE LA FIESTA (T6b·2 de `specs/isla-y-landing-nueva.md` §4.18, `#834`): lo que ALARGA
# una fiesta en `/prices` (`stay_extensions`: por su FORMA, con su precio por tarifa, solo si se vende), los próximos
# días de fin de semana con hueco de cada zona de packs (`availability_weekends` de `PageFacts`: fin de semana, dentro
# del horizonte, con hora a la venta, cuatro como mucho, guardado por día) y la configuración pública como hecho.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-hechos-de-la-fiesta.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=PricesFactsTest|InstancePagesTest"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Http/Resources/Api/V1/PricesFactsResource.php
    app/Http/Controllers/Api/V1/PricesFactsController.php
    app/Http/Instancia/PageFacts.php
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $PHP >/dev/null 2>&1; }

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

PR=app/Http/Resources/Api/V1/PricesFactsResource.php
PC=app/Http/Controllers/Api/V1/PricesFactsController.php
PF=app/Http/Instancia/PageFacts.php

# Lo que alarga una fiesta, en `/prices`.
mutar "/prices no publica las extensiones" "$PR" "                'stay_extensions' => \$this->extensiones(\$producto),
" ""
mutar "un complemento que solo SE LLAMA hora extra vale" "$PR" "if (! AddonOccupancy::sellableStayExtension(\$complemento) || \$precios === []) {" "if (\$precios === []) {"
mutar "un extensor sin precio se anuncia" "$PR" "if (! AddonOccupancy::sellableStayExtension(\$complemento) || \$precios === []) {" "if (! AddonOccupancy::sellableStayExtension(\$complemento)) {"
mutar "todo se cobra por invitado" "$PR" "'per_guest' => \$complemento->addonPivot()?->isPerGuest() ?? false," "'per_guest' => true,"
mutar "siempre alarga una hora" "$PR" "'minutes' => (int) \$complemento->duration_min," "'minutes' => 60,"
mutar "las extensiones sin precargar" "$PC" ", 'addons.prices', 'addons.priceTiers'])" "])"
# Los fines de semana con hueco, en `PageFacts`.
mutar "un día entre semana cuenta" "$PF" "CarbonImmutable::parse(\$dia->date)->isWeekend()" "true"
mutar "sin horizonte" "$PF" "if (\$dia->date <= \$hasta && " "if ("
mutar "sin tope de cuatro" "$PF" "if (count(\$conHueco) === self::FINDE_DIAS) {" "if (false) {"
mutar "un día que se vende cuenta aunque no tenga hueco" "$PF" "if (! \$hayHueco) {" "if (false) {"
# ⚠️ Sin mutante, y medido: «una hora que no está a la venta cuenta» es EQUIVALENTE hoy —para un pack, `SlotOffer` salta
# las franjas bajo el mínimo y pone `'sellable' => true` en las demás—. La comprobación de `sellable` se queda para el
# día que el motor devuelva horas de pack que no se venden; un mutante que no puede morir aquí solo sería ruido.
mutar "lo guardado vale para siempre" "$PF" "'instancia:hechos:availability_weekends:'.\$hoy->toDateString()" "'instancia:hechos:availability_weekends'"
# La configuración pública, como hecho.
mutar "config no es un hecho" "$PF" "        'config' => [ConfigController::class, '__invoke', false],
" ""

echo
echo "$muerden de $total mutantes muertos"
[[ "$muerden" == "$total" ]]
