#!/usr/bin/env bash
# Arnés de mutación de F5a, EL DATO DE LA ZONA 4 (`specs/fiesta-sistema-nuevo.md` §4.11, `[DECIDIDO owner]` `#749`).
#
# Protege lo que calla al romperse: las tres listas blancas del enganche para `postform_block` (una clave olvidada se cae
# sin error), el saneo del bloque (solo venta posterior, lista cerrada), lo que la tarjeta del post-form lleva a la lista
# y a la API, el campo de adultos por TIPO y acotado, que «Guardado» no mueva el TESTIGO ni lo selle el parque, y que la
# pregunta de la tarta llegue al reconciliador como cantidades de las tartas EN PLAZO, sin comprar otra cosa.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-extras-fiesta.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='ExtrasDeLaFiestaDatoTest|ExtrasDeLaFiestaListaTest|AddonStageTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Models/TicketType.php
    app/Domain/Booking/Models/ProductAddon.php
    app/Domain/Booking/Models/OrderItem.php
    app/Domain/Booking/Services/PostFormAddons.php
    app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php
    app/Http/Controllers/GuestFormController.php
    app/Http/Controllers/Api/V1/GuestFormController.php
    app/Http/Resources/Api/V1/GuestFormResource.php
    app/Http/Fiesta/ListaDeInvitados.php
    resources/views/fiesta/lista.blade.php
    resources/views/fiesta/lista/zona-3.blade.php
    resources/views/components/fiesta/complemento.blade.php
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

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
PA=app/Domain/Booking/Models/ProductAddon.php
OI=app/Domain/Booking/Models/OrderItem.php
PFA=app/Domain/Booking/Services/PostFormAddons.php
ARM=app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php
GFW=app/Http/Controllers/GuestFormController.php
GFA=app/Http/Controllers/Api/V1/GuestFormController.php
GFR=app/Http/Resources/Api/V1/GuestFormResource.php

# ── 1 · Las tres listas blancas del enganche y su saneo ─────────────────────────────────────────
mutar "el bloque se cae de las columnas del pivote" "$TT" \
  $'        \'postform_block\',\n    ];' \
  $'    ];'
mutar "el saneo del panel no escribe el bloque" "$ARM" \
  "            'postform_block' => (\$postForm && in_array(\$data['postform_block'] ?? null, ProductAddon::POSTFORM_BLOCKS, true))" \
  "            'postform_block' => (false && in_array(\$data['postform_block'] ?? null, ProductAddon::POSTFORM_BLOCKS, true))"
mutar "el saneo del panel deja el bloque en un enganche al reservar" "$ARM" \
  "            'postform_block' => (\$postForm && in_array(" \
  "            'postform_block' => (true && in_array("
mutar "Configurar no precarga el bloque (tocar la posición lo borra)" "$ARM" \
  "                        'postform_block' => \$record->addonPivot()?->postformBlock()," \
  ""
mutar "el bloque se lee también en un enganche que se vende al reservar" "$PA" \
  $'        if (! $this->isPostFormStage()) {\n            return null;\n        }\n        $block' \
  $'        $block'
mutar "el bloque acepta cualquier valor" "$PA" \
  "return is_string(\$block) && in_array(\$block, self::POSTFORM_BLOCKS, true) ? \$block : null;" \
  "return is_string(\$block) ? \$block : null;"

# ── 2 · Lo que la tarjeta lleva a la lista y a la API ───────────────────────────────────────────
mutar "la tarjeta pierde para cuántas personas es" "$PFA" \
  "                serves: \$addon->peopleServed()," \
  "                serves: null,"
mutar "la tarjeta pierde su familia" "$PFA" \
  "                family: trim((string) (\$addon->tr('family') ?? ''))," \
  "                family: '',"
mutar "la tarjeta pierde su bloque" "$PFA" \
  "                block: \$addon->addonPivot()?->postformBlock()," \
  "                block: null,"
mutar "la tarjeta pierde su foto" "$PFA" \
  "                imageUrl: \$addon->imageUrl()," \
  "                imageUrl: null,"
mutar "«para cuántas» acepta cero" "$TT" \
  "return is_numeric(\$serves) && (int) \$serves >= 1 ? (int) \$serves : null;" \
  "return is_numeric(\$serves) ? (int) \$serves : null;"

# ── 3 · Los adultos, por tipo y acotados ────────────────────────────────────────────────────────
mutar "el campo de adultos se busca por otro tipo" "$TT" \
  "            if (\$field['type'] === self::FIELD_TYPE_ADULTS) {" \
  "            if (in_array(\$field['type'], [self::FIELD_TYPE_ADULTS, self::FIELD_TYPE_NUMBER], true)) {"
mutar "los adultos sin tope" "$TT" \
  $'            if ($adults > self::ADULTS_MAX) {\n                return null;\n            }' \
  ""

# ── 4 · «Guardado» y «Sin tarta» ────────────────────────────────────────────────────────────────
mutar "«Guardado» mueve el testigo (update de Eloquent)" "$OI" \
  "static::query()->whereKey(\$this->getKey())->toBase()->update(['guest_form_saved_at' => \$now]);" \
  "static::query()->whereKey(\$this->getKey())->update(['guest_form_saved_at' => \$now]);"
mutar "lo que guarda el parque cuenta como «Guardado»" "$OI" \
  $'        if ($by === null) {\n            $this->markGuestFormSaved();' \
  $'        if (true) {\n            $this->markGuestFormSaved();'
mutar "con una tarta puesta, «Sin tarta» no se borra" "$OI" \
  $'        if ($hasCake) {\n            $this->declineCake(false);' \
  $'        if (false) {\n            $this->declineCake(false);'
mutar "«Sin tarta» se publica con una tarta en el pedido" "$GFR" \
  "            'cake_declined' => \$item->cakeDeclined(\$addons)," \
  "            'cake_declined' => \$item->cake_declined_at !== null,"
mutar "la API no aplica cake_declined" "$GFA" \
  "            array_key_exists('cake_declined', \$validated) ? (bool) \$validated['cake_declined'] : null," \
  "            null,"

# ── 5 · La pregunta de la tarta en la web ───────────────────────────────────────────────────────
mutar "la pregunta de la tarta compra lo que no es una tarta" "$GFW" \
  "        if (\$cakes === [] || (! \$none && ! in_array(\$chosen, array_map(static fn (PostFormAddonView \$a): int => \$a->productId, \$cakes), true))) {" \
  "        if (\$cakes === [] && false) {"
mutar "la pregunta de la tarta toca tartas fuera de plazo" "$GFW" \
  "static fn (PostFormAddonView \$a): bool => \$a->block === ProductAddon::BLOCK_CAKE && ! \$a->closed," \
  "static fn (PostFormAddonView \$a): bool => \$a->block === ProductAddon::BLOCK_CAKE,"
mutar "«Añadir otra tarta» no sube la cantidad" "$GFW" \
  "'quantity' => \$cake->productId === \$chosen ? \$quantity : 0];" \
  "'quantity' => \$cake->productId === \$chosen ? 1 : 0];"
mutar "la web no guarda «Sin tarta»" "$GFW" \
  "        return ['rows' => \$rows, 'declined' => \$none];" \
  "        return ['rows' => \$rows, 'declined' => null];"
mutar "la respuesta de la tarta no llega al reconciliador" "$GFW" \
  $'        if ($cake[\'rows\'] !== []) {\n            $desired = array_merge($desired ?? [], $cake[\'rows\']);' \
  $'        if (false) {\n            $desired = array_merge($desired ?? [], $cake[\'rows\']);'

LDI=app/Http/Fiesta/ListaDeInvitados.php
LISTA=resources/views/fiesta/lista.blade.php
Z3=resources/views/fiesta/lista/zona-3.blade.php
COMP=resources/views/components/fiesta/complemento.blade.php

# ── 6 · F5b: la zona 4 como el mockup ───────────────────────────────────────────────────────────
mutar "la tarta pierde «Sin tarta»" "$LDI" \
  "            \$opciones[] = ['value' => 'none', 'title' => __('fiesta.lista.tarta.sin'), 'description' => '', 'price' => '', 'disabled' => ! \$abierta];" \
  ""
mutar "la opción nunca dice que no llega" "$LDI" \
  ": (\$sois > (int) \$raciones ? __('fiesta.lista.tarta.no_llega'" \
  ": (false ? __('fiesta.lista.tarta.no_llega'"
mutar "la tarta viaja también como tarjeta" "$LDI" \
  "fn (PostFormAddonView \$a): bool => \$a->block === \$bloque));" \
  "fn (PostFormAddonView \$a): bool => \$bloque === null || \$a->block === \$bloque));"
mutar "la tarta guardada vuelve sin marcar" "$LDI" \
  "                'elegida' => \$elegida," \
  "                'elegida' => null,"
mutar "la tarta guardada vuelve con una sola" "$LDI" \
  "\$cantidad = \$elegidaVista !== null ? \$elegidaVista->quantity : 1;" \
  "\$cantidad = 1;"
mutar "los adultos se preguntan dos veces (siguen en los generales)" "$LDI" \
  "fn (array \$g): bool => \$g['key'] !== \$adultos))," \
  "fn (array \$g): bool => true)),"
mutar "los adultos se preguntan con lo de los padres cerrado" "$LDI" \
  "'adultos' => \$clave !== null && \$abierto ? [" \
  "'adultos' => \$clave !== null ? ["
mutar "las familias no agrupan" "$LDI" \
  "\$familias[\$a->family][] = \$tarjeta(" \
  "\$familias[''][] = \$tarjeta("
mutar "el aviso de la tarta sigue con la tarta decidida" "$LDI" \
  "'urgente' => \$pronto && \$elegida === null," \
  "'urgente' => \$pronto,"
mutar "el aviso de la tarta sale aunque cierre lejos" "$LDI" \
  "&& (\$cierra->isSameDay(DisplayTime::today()) || \$cierra->isSameDay(DisplayTime::today()->addDay()))," \
  ","
mutar "la página no pinta el aviso de la tarta" "$LISTA" \
  "            @include('fiesta.lista.aviso-tarta')" \
  ""
mutar "la línea de los padres sigue con algo pedido" "$Z3" \
  " && \$m['extras']['padres']['nada_guardado'])" \
  ")"
mutar "«Ver…» no nombra las familias" "$LDI" \
  "__('fiesta.lista.padres.ver', ['familias' => self::enumera(array_map(fn (string \$f): string => Str::lower(\$f), \$titulos))])" \
  "__('fiesta.lista.padres.ver_generico')"
mutar "el pie no dice hasta cuándo" "$LDI" \
  "(\$partes === [] ? '' : ' '.Str::ucfirst(implode('; ', \$partes)).'.')" \
  "''"
mutar "el pie no reconoce «el mismo día»" "$LDI" \
  "\$fiesta !== null && \$c->isSameDay(\$fiesta)" \
  "false"
mutar "la rejilla pierde «para cuántas personas»" "$LDI" \
  "\$a->serves === null ? '' : trans_choice('fiesta.lista.extras.para_personas'" \
  "true ? '' : trans_choice('fiesta.lista.extras.para_personas'"
mutar "sin foto, la tarjeta pinta el hueco del diseño" "$COMP" \
  "@if (\$image || \$imageNote !== '')<div" \
  "@if (true)<div"

# ── 7 · F5c: «Guardado hoy a las…» ──────────────────────────────────────────────────────────────
mutar "la barra olvida que el titular guardó" "$LDI" \
  "'estado' => \$status === 'guest-form-saved' || \$guardadoEn !== null ? 'saved' : 'clean'," \
  "'estado' => \$status === 'guest-form-saved' ? 'saved' : 'clean',"
mutar "«Guardado» sin la hora" "$LDI" \
  "'guardado' => \$guardadoEn === null ? __('fiesta.lista.guardar.guardado') : __('fiesta.lista.guardar.guardado_el'," \
  "'guardado' => true ? __('fiesta.lista.guardar.guardado') : __('fiesta.lista.guardar.guardado_el',"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
