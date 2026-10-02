#!/usr/bin/env bash
# Arnés de mutación de «UN SOLO PLAZO PARA TODA LA LISTA» (P1 de `specs/fiesta-sistema-nuevo.md` §4.20, `[DECIDIDO owner]`
# `#912`). Lo que protege: que cada complemento de venta posterior cierre con la LISTA —el ajuste
# `packs.guest_count_cutoff_hours` de `GuestCountPolicy`, en el mismo instante y con la misma comparación—, que el enganche ya
# no pueda exigir ni decidir un plazo propio, y que el plazo se diga UNA vez, en la cabecera de la lista, y no en cada tarjeta
# ni en la insignia del panel.
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento): con el filtro entero, diez mutaciones eran
# ~8 min y la verificación de una tanda dura minutos. La base verde sí se mide con la unión.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su
# ancla es ÚNICA · un CONTROL que no debe morder · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
TODOS='ExtrasDeLaFiesta|PostFormAddonsTest|AddonStageTest|BirthdayPageTest|MeReservationBeforeVisitTest|GuestCountSurfacesTest|InvitationDeclinedTest'
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Models/ProductAddon.php
    app/Domain/Booking/Services/PostFormAddons.php
    app/Http/Fiesta/ListaDeInvitados.php
    resources/views/fiesta/lista/cabecera.blade.php
    app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; docker compose exec -u sail -T laravel.test php artisan view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
if ! correr "$TODOS"; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0; control_ok=1

aplicar() {
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); n=s.count(sys.argv[2]); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1)); sys.exit(0 if n == 1 else 3)' \
        "$1" "$2" "$3"
}

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    total=$((total + 1))
    aplicar "$fichero" "$buscar" "$poner"; local unico=$?
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if [[ $unico -ne 0 ]]; then
        echo "  ⚠ «$nombre»: el ancla NO es única; se mutó la primera y el veredicto es dudoso"
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — se pone rojo por algo que no es la regla"; control_ok=0
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

PA=app/Domain/Booking/Models/ProductAddon.php
PF=app/Domain/Booking/Services/PostFormAddons.php
LI=app/Http/Fiesta/ListaDeInvitados.php
CA=resources/views/fiesta/lista/cabecera.blade.php
RM=app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php

# ── El DOMINIO: el plazo es el de la lista, y nada más ───────────────────────────────────────────
mutar "el complemento vuelve a leer SU columna de horas" "$PA" \
  "return app(GuestCountPolicy::class)->cutoffHours();" \
  "return (int) (\$this->getAttributes()['postform_cutoff_hours'] ?? 0);" \
  'BirthdayPageTest'
mutar "el cierre del complemento se separa una hora del de la lista" "$PF" \
  "return app(GuestCountPolicy::class)->deadlineFor(\$principal);" \
  "return app(GuestCountPolicy::class)->deadlineFor(\$principal)?->subHour();" \
  'PostFormAddonsTest|ExtrasDeLaFiestaListaTest'
mutar "el complemento compara con lte: en el instante del cierre sigue abierto" "$PF" \
  "return \$deadline !== null && DisplayTime::now()->lt(\$deadline);" \
  "return \$deadline !== null && DisplayTime::now()->lte(\$deadline);" \
  'PostFormAddonsTest'
mutar "vuelve la regla 9: sin plazo propio, el enganche no se guarda" "$PA" \
  "            return 'missing_max_qty';
        }" \
  "            return 'missing_max_qty';
        }
        if (\$pivot->postform_cutoff_hours === null) {
            return 'missing_cutoff';
        }" \
  'AddonStageTest'

# ── La LISTA: el plazo se dice una vez, en la cabecera ───────────────────────────────────────────
mutar "la cabecera deja de decir el plazo" "$CA" \
  "@if (\$m['plazo'] !== null)<p" \
  "@if (false)<p" \
  'ExtrasDeLaFiestaListaTest|InvitationDeclinedTest|GuestCountSurfacesTest'
mutar "la cabecera dice «puedes cambiar» con la lista cerrada" "$LI" \
  "\$abierta = app(GuestCountPolicy::class)->isWithinWindow(\$reservation);" \
  "\$abierta = true;" \
  'ExtrasDeLaFiestaListaTest|GuestCountSurfacesTest'
mutar "una tarjeta cerrada por el plazo vuelve a decir su motivo" "$LI" \
  "'motivo' => \$addon->closed && \$addon->closedReason === PostFormAddonView::REASON_SOLD_AT_BOOKING ? __('guestform.extras_closed_sold') : ''," \
  "'motivo' => \$addon->closed ? __('guestform.extras_closed_sold') : ''," \
  'ExtrasDeLaFiestaListaTest'

# ── El PANEL: la insignia ya no lleva plazo ──────────────────────────────────────────────────────
mutar "la insignia vuelve a decir «hasta 48 h antes»" "$RM" \
  "\$badges[] = __('admin.catalog.addons.badge_postform');" \
  "\$badges[] = __('admin.catalog.addons.badge_postform').' · hasta 48 h antes';" \
  'AddonStageTest'

# ── El CONTROL: tocar un comentario no puede poner nada en rojo ─────────────────────────────────
control "un comentario de la tarta" "$LI" \
  "El plazo de la tarta ES el de la lista" \
  "El plazo de la tarta es el de la lista" \
  "$TODOS"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
