#!/usr/bin/env bash
# Arnés de mutación de F5a, EL DATO DE LA ZONA 4 (`specs/fiesta-sistema-nuevo.md` §4.11, `[DECIDIDO owner]` `#749`).
#
# Protege lo que calla al romperse: las tres listas blancas del enganche para `postform_block` (una clave olvidada se cae
# sin error), el saneo del bloque (solo venta posterior, lista cerrada), lo que la tarjeta del post-form lleva a la lista
# y a la API, el campo de adultos por TIPO y acotado, y que «Guardado» no mueva el TESTIGO ni lo selle el parque.
# ▶ La tarta como PREGUNTA de una respuesta (su radio, «¿Cuántas tartas?», la traducción del controlador) se RETIRÓ en K2
#   (§4.17, `#807`: varias a la vez) con sus 12 mutaciones; lo de K2 lo muerde `scripts/mutar-complementos-k2.sh`.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-extras-fiesta.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='ExtrasDeLaFiestaDatoTest|ExtrasDeLaFiestaListaTest|AddonStageTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"
RUNJS="docker compose exec -u sail -T laravel.test node --test resources/js/fiesta/logica.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Models/TicketType.php
    app/Domain/Booking/Models/ProductAddon.php
    app/Domain/Booking/Models/OrderItem.php
    app/Domain/Booking/Services/PostFormAddons.php
    app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php
    app/Http/Controllers/Api/V1/GuestFormController.php
    app/Http/Resources/Api/V1/GuestFormResource.php
    app/Http/Fiesta/ListaDeInvitados.php
    resources/views/fiesta/lista.blade.php
    resources/views/fiesta/lista/zona-3.blade.php
    resources/views/components/fiesta/complemento.blade.php
    resources/js/fiesta/logica.js
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $RUN >/dev/null 2>&1; }
verdejs() { $RUNJS >/dev/null 2>&1; }

if ! verde || ! verdejs; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# mutar <nombre> <fichero> <buscar> <poner> [js]
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" juez="${5:-php}"
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
    local sigue=1
    if [[ "$juez" == js ]]; then verdejs || sigue=0; else verde || sigue=0; fi
    if [[ $sigue -eq 1 ]]; then
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

# ── 5 · (la pregunta de la tarta en la web: retirada en K2 con su sujeto; ver la cabecera) ───────

LDI=app/Http/Fiesta/ListaDeInvitados.php
LISTA=resources/views/fiesta/lista.blade.php
Z3=resources/views/fiesta/lista/zona-3.blade.php
COMP=resources/views/components/fiesta/complemento.blade.php

# ── 6 · F5b: la zona 4 como el mockup ───────────────────────────────────────────────────────────
# (La tarta como pregunta —«Sin tarta» entre sus opciones, «no llega» en la opción, la elegida, «¿Cuántas tartas?», su
#  tope y su cuenta, y su línea cerrada— se retiró en K2 con sus mutaciones: ver la cabecera.)
mutar "la tarta viaja también como tarjeta suelta" "$LDI" \
  "fn (PostFormAddonView \$a): bool => \$a->block === \$bloque));" \
  "fn (PostFormAddonView \$a): bool => \$bloque === null || \$a->block === \$bloque));"
LJ=resources/js/fiesta/logica.js
mutar "el importe cobrado sin sus dos decimales" "$LJ" \
  "String(abs % 100).padStart(2, '0')" \
  "String(abs % 100)" js
mutar "los adultos se preguntan dos veces (siguen en los generales)" "$LDI" \
  "fn (array \$g): bool => \$g['key'] !== \$adultos))," \
  "fn (array \$g): bool => true)),"
mutar "los adultos se preguntan con lo de los padres cerrado" "$LDI" \
  "'adultos' => \$clave !== null && \$abierto ? [" \
  "'adultos' => \$clave !== null ? ["
mutar "las familias no agrupan" "$LDI" \
  "\$familias[\$a->family][] = \$tarjeta(" \
  "\$familias[''][] = \$tarjeta("
# Retiradas con su sujeto (§3.quater, `#912` P1·b): las tres del aviso de la tarta («sigue con la tarta decidida», «sale
# aunque cierre lejos», «la página no lo pinta»): el aviso ya no existe; que no vuelva lo dice
# `ExtrasDeLaFiestaListaTest::test_the_cake_has_no_notice_nor_deadline_of_its_own_even_closing_tomorrow`.
mutar "la línea de los padres sigue con algo pedido" "$Z3" \
  " && \$m['extras']['padres']['nada_guardado'])" \
  ")"
mutar "«Ver…» no nombra las familias" "$LDI" \
  "__('fiesta.lista.padres.ver', ['familias' => self::enumera(array_map(fn (string \$f): string => Str::lower(\$f), \$titulos))])" \
  "__('fiesta.lista.padres.ver_generico')"
# Retiradas con su sujeto (§3.quater, `#912` P1): «el pie no dice hasta cuándo» y «el pie no reconoce «el mismo día»» — el
# pie ya no lleva plazos; el de la lista lo guarda `scripts/mutar-plazo-unico.sh`.
# K1 de §4.17 (`#806`/`#807`): los sueltos son de «Para los niños» y su chapa se cuenta en niños (fuera `para_personas`).
mutar "los sueltos pierden «para cuántos niños»" "$LDI" \
  "\$a->serves === null ? '' : trans_choice('fiesta.lista.ninos.para'" \
  "true ? '' : trans_choice('fiesta.lista.ninos.para'"
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
