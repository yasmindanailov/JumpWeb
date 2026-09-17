#!/usr/bin/env bash
# Arnés de mutación del CATÁLOGO Y EL EMBUDO de la invitación digital
# (`specs/celebracion-e-invitacion.md` §4.8 y §10.4.3, `DECISIONES #575`).
#
# Lo que protege, y son dos familias que fallan CALLANDO:
#
#  · **El guard del interruptor**, que vive en el modelo y no en el formulario porque los seeders, una
#    importación y un `update()` a mano escriben por debajo del panel. Sin él, un producto podría
#    ofrecer una invitación que no puede funcionar —sin columna de nombre no hay con qué emparejar—.
#  · **Las CUATRO puertas del pivote** (`#413` §4.7·ter): la lista de columnas, el saneo, la precarga
#    del formulario y el propio campo. Cada una calla al olvidarse, y la tercera es la peor: apaga la
#    casilla **sola**, semanas después, al tocar cualquier otro campo del enganche.
#
# ⚠️ El predicado del embudo es una OFERTA, no un permiso (`#400`): `OrderCreator` sigue leyendo el modo
# real y **no se toca**. Por eso hay un mutante que comprueba que el modo del dominio NO cambia.
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO
# DE SALIDA · restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

TYP=app/Domain/Booking/Models/TicketType.php
CAT=app/Domain/Booking/Services/CatalogReader.php
ADD=app/Filament/Resources/Catalog/RelationManagers/AddonsRelationManager.php

TMP="$(mktemp -d)"
FICHEROS=("$TYP" "$CAT" "$ADD")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='InvitationCatalogTest|AddonStageTest' >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; mutantes=0; controles=0; control_ok=1; no_aplicados=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" espera="${5:-muerde}"
    if [[ "$espera" == 'control' ]]; then controles=$((controles + 1)); else mutantes=$((mutantes + 1)); fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        if [[ "$espera" == 'control' ]]; then controles=$((controles - 1)); else mutantes=$((mutantes - 1)); fi
        no_aplicados=$((no_aplicados + 1))
        return
    fi
    touch "$fichero"
    if verde; then
        if [[ "$espera" == 'control' ]]; then
            echo "  ✓ CONTROL en verde, como debe: $nombre"
        else
            echo "  ✗ NO muerde: $nombre"
        fi
    else
        if [[ "$espera" == 'control' ]]; then
            echo "  ✗ CONTROL EN ROJO — el arnés acusa a lo que no cambia conducta: $nombre"
            control_ok=0
        else
            echo "  ✓ muerde:    $nombre"
            muerden=$((muerden + 1))
        fi
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

echo '── El GUARD del interruptor (la autoridad está en el MODELO) ─────────────────────────────'

mutar "un producto con justificante OBLIGATORIO puede ofrecer invitación" "$TYP" \
  '            if ($type->guardianMode() === self::GUARDIAN_REQUIRED) {' \
  '            if (false) {'

mutar "una ENTRADA suelta puede ofrecer una invitación de fiesta" "$TYP" \
  '            if (! $type->isPack()) {' \
  '            if (false) {'

mutar "un pack SIN columna de nombre puede ofrecerla (nada con qué emparejar)" "$TYP" \
  '            if ($type->guestNameFieldKey() === null) {' \
  '            if (false) {'

mutar "el guard no mira la fila que YA existe (solo las nuevas)" "$TYP" \
  '            if (! $type->guest_invitation) {
                return;
            }' \
  '            if (! $type->guest_invitation || $type->exists) {
                return;
            }'

echo '── El EMBUDO ────────────────────────────────────────────────────────────────────────────'

mutar "el embudo sigue preguntando por el justificante con invitación encendida" "$TYP" \
  '        return $this->offersGuestInvitation() ? self::GUARDIAN_NONE : $this->guardianMode();' \
  '        return $this->guardianMode();'

mutar "la invitación se enciende en cualquier tipo de producto" "$TYP" \
  '        return (bool) $this->guest_invitation
            && $this->isPack()
            && $this->guestNameFieldKey() !== null;' \
  '        return (bool) $this->guest_invitation
            && $this->guestNameFieldKey() !== null;'

mutar "el CAJÓN recibe el modo del producto en vez del modo del embudo" "$CAT" \
  '            guardianAuthorization: $product->funnelGuardianMode(),' \
  '            guardianAuthorization: $product->guardianMode(),'

echo '── Las CUATRO puertas del pivote (cada una calla al olvidarse) ──────────────────────────'

mutar "1ª puerta · la columna se cae al ENGANCHAR" "$TYP" \
  "        'show_in_invitation',
    ];" \
  '    ];'

mutar "2ª puerta · el saneo no la escribe al CONFIGURAR" "$ADD" \
  "            'show_in_invitation' => (bool) (\$data['show_in_invitation'] ?? false)," \
  ''

mutar "3ª puerta · la precarga la apaga SOLA al tocar otro campo" "$ADD" \
  "                        'show_in_invitation' => \$record->pivot?->showsInInvitation() ?? false," \
  ''

mutar "4ª puerta · no hay nada que marcar en el formulario" "$ADD" \
  "            Toggle::make('show_in_invitation')" \
  "            Toggle::make('show_in_invitation_MUTADO')"

echo '── CONTROL: cambiar prosa NO puede poner nada en rojo ────────────────────────────────────'

mutar "un comentario reescrito" "$TYP" \
  '¿El embudo le PREGUNTA al cliente por el justificante?' \
  '(prosa mutada por el arnés) el embudo pregunta' \
  control

echo
restaurar
SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo "✗ el árbol NO quedó byte a byte como estaba." >&2
    exit 1
fi
echo "✓ árbol restaurado byte a byte (sha1 $SHA_ANTES)"
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · sin aplicar: $no_aplicados"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "$no_aplicados" == 0 ]] || exit 1
