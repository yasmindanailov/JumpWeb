#!/usr/bin/env bash
# Arnés de mutación de la T1 de `docs/specs/complementos-post-reserva.md` (`DECISIONES #413`):
# el EJE de fase, sus guards, su cinturón y las tres listas blancas del panel.
#
# Por qué hace falta: los dos bloqueantes de esta tanda **pasan en verde con el defecto puesto**.
# Antes de la T1, la suite entera (583 casos del entorno de catálogo, oferta y landing) pasaba
# mientras un `postform` se habría vendido en el embudo, y las tres listas blancas del panel callan
# las tres al olvidarse. Un verde que no se ha visto en rojo no dice nada.
#
# ⚠️⚠️ Las tres reglas de la casa, construidas dentro:
#   · **exige VERDE antes de mutar**;
#   · **veredicto por CÓDIGO DE SALIDA**, sin tuberías delante (`#412`);
#   · **comprueba que la mutación SE APLICÓ**: un patrón que no casa deja el fichero intacto y el
#     veredicto diría «no muerde» sobre un defecto que nunca se introdujo.
#
# El restaurado es `git checkout` del fichero mutado: **el árbol tiene que estar commiteado** (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='AddonStageTest|CatalogAddonsRelationManagerTest|ExtraHourConfigTest|ModuleBoundariesTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

T=app/Domain/Booking/Models/TicketType.php
P=app/Domain/Booking/Models/ProductAddon.php
R=app/Domain/Booking/Services/AddonResolver.php
O=app/Domain/Booking/Services/AddonOfferReader.php
C=app/Domain/Booking/Services/CatalogReader.php
M=app/Filament/Pages/CreateManualOrderPage.php
A=app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php

verde() { $RUN >/dev/null 2>&1; }

if ! git diff --quiet -- "$T" "$P" "$R" "$O" "$C" "$M" "$A"; then
    echo "✗ hay cambios sin commitear en los ficheros que se van a mutar: commitea antes (#181)." >&2
    exit 1
fi

if ! verde; then
    echo "✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada." >&2
    exit 1
fi
echo "✓ base verde con el filtro de la T1"

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner" \
        || { echo "  ⚠ la mutación «$nombre» no se pudo aplicar"; git checkout -- "$fichero"; return; }
    if git diff --quiet -- "$fichero"; then
        echo "  ⚠ la mutación «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    git checkout -- "$fichero"
    touch "$fichero"   # `#219`: restaurar sin tocar la fecha deja servir el compilado mutado
}

# ── Las TRES listas blancas del panel ──────────────────────────────────────────────────────────
mutar "el attach BORRA la fase (falta en ADDON_PIVOT_COLUMNS)" "$T" \
  "        'stage',
        'postform_cutoff_hours',
    ];" \
  "        'postform_cutoff_hours',
    ];"

mutar "«Configurar» no puede cambiar la fase (falta en el saneo)" "$A" \
  "            'stage' => \$stage,
" ""

mutar "la fase REVIERTE al tocar otro campo (falta en el fillForm)" "$A" \
  "                        'stage' => \$record->pivot?->saleStage() ?? ProductAddon::STAGE_BOOKING,
" ""

mutar "el plazo se pierde al reconfigurar (falta en el fillForm)" "$A" \
  "                        'postform_cutoff_hours' => \$record->pivot?->postformCutoffHours(),
" ""

# ── El eje y su cinturón ──────────────────────────────────────────────────────────────────────
mutar "el eje deja de filtrar (todo se ofrece en todas partes)" "$R" \
  '            if (! $pivot instanceof ProductAddon || $pivot->saleStage() !== $stage) {
                return false;
            }' \
  '            if (! $pivot instanceof ProductAddon) {
                return false;
            }'

mutar "el CINTURÓN desaparece (una fila torcida se ofrece y se vende)" "$R" \
  '            return $stage !== ProductAddon::STAGE_POSTFORM
                || ProductAddon::postFormProblem($pivot, $addon) === null;' \
  '            return true;'

# ── Las superficies que VENDEN ────────────────────────────────────────────────────────────────
mutar "el EMBUDO deja de filtrar" "$O" \
  '$offered = AddonResolver::forStage($product->addons, ProductAddon::STAGE_BOOKING);' \
  '$offered = $product->addons;'

mutar "la FICHA PÚBLICA deja de filtrar" "$C" \
  '        $offered = AddonResolver::forStage(
            $product->addons()->with('"'"'prices.rateType'"'"')->get(),
            ProductAddon::STAGE_BOOKING,
        );' \
  "        \$offered = \$product->addons()->with('prices.rateType')->get();"

mutar "la LANDING deja de filtrar" "$T" \
  '        return AddonResolver::forStage($this->addons, ProductAddon::STAGE_BOOKING);' \
  '        return $this->addons;'

mutar "el ALTA MANUAL deja de filtrar (el mostrador vende venta posterior)" "$M" \
  '            $this->addonsMemo = AddonResolver::forStage(
                $type->addons()->with('"'"'prices.rateType'"'"')->get(),
                ProductAddon::STAGE_BOOKING,
            );' \
  "            \$this->addonsMemo = \$type->addons()->with('prices.rateType')->get();"

# ── Los guards, en las dos direcciones ────────────────────────────────────────────────────────
mutar "cae el guard del tope obligatorio" "$P" \
  '        if ($pivot->max_qty === null || (int) $pivot->max_qty < 1) {
            return '"'"'missing_max_qty'"'"';
        }' ''

mutar "cae el guard del plazo obligatorio" "$P" \
  '        if ($pivot->postformCutoffHours() === null) {
            return '"'"'missing_cutoff'"'"';
        }' ''

mutar "cae el guard del obligatorio (la deuda que se auto-inyecta)" "$P" \
  '        if ($pivot->is_mandatory) {
            return '"'"'is_mandatory'"'"';
        }' ''

mutar "cae la dirección INVERSA del guard de ocupación" "$T" \
  "                        ->orWhere('stage', ProductAddon::STAGE_POSTFORM))" \
  '                        )'

mutar "cae el guard del requisito cruzado entre fases" "$P" \
  '                if ($required !== null && ! $required->isPostFormStage()) {' \
  '                if (false) {'

# ── Lo que el operador VE ─────────────────────────────────────────────────────────────────────
mutar "la insignia de fase desaparece de la lista" "$A" \
  '        if ($pivot->isPostFormStage()) {' \
  '        if (false) {'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
