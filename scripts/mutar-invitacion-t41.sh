#!/usr/bin/env bash
# Arnés de mutación de los CIMIENTOS de la invitación digital
# (`specs/celebracion-e-invitacion.md` §10.4.1, `DECISIONES #573`).
#
# Lo que protege: las tres políticas de borrado que la segunda revisión adversarial encontró
# AUSENTES (§7.2·R6) y que no fallan solas —una FK mal puesta no rompe nada hasta el día en que la
# purga o la poda corren en producción—, más la decisión de PRIVACIDAD del índice no único (V6), la
# lista blanca de una superficie pública (`SEC-10`) y el respaldo del normalizador.
#
# ⚠️ Cada mutante cambia una CONDUCTA, no un texto: las de la migración se ejercen porque
# `RefreshDatabase` vuelve a migrar en cada caso, así que el esquema mutado es el que se prueba.
#
# Reglas de la casa dentro: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto
# por CÓDIGO DE SALIDA · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`) · y el
# árbol byte a byte como estaba al terminar (sha1).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

MIG=database/migrations/2026_09_17_230000_create_party_invitations.php
REP=app/Domain/Booking/Models/InvitationReply.php
KEY=app/Domain/Platform/Services/PersonNameKey.php

TMP="$(mktemp -d)"
FICHEROS=("$MIG" "$REP" "$KEY")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='PersonNameKeyTest|PartyInvitationSchemaTest' >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

# ⚠️ Los CONTROLES se cuentan aparte: sumarlos a los mutantes hacía que el arnés dijera «9/10» con las
# diez líneas en verde y saliera con 1. Un instrumento que acusa cuando todo está bien es tan inútil
# como uno que absuelve — y aquí el veredicto se decide por CÓDIGO DE SALIDA, no por leer la tabla.
muerden=0; mutantes=0; controles=0; control_ok=1

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" espera="${5:-muerde}"
    if [[ "$espera" == 'control' ]]; then controles=$((controles + 1)); else mutantes=$((mutantes + 1)); fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        if [[ "$espera" == 'control' ]]; then controles=$((controles - 1)); else mutantes=$((mutantes - 1)); fi
        no_aplicados=$((${no_aplicados:-0} + 1))
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

echo '── Las tres políticas de borrado (§7.2·R6) ───────────────────────────────────────────────'

# ⚠️ Dos FKs de este fichero tienen la MISMA línea; el reemplazo es de la PRIMERA, que es la de
# `party_invitations`. Por eso el patrón lleva su línea anterior como ancla.
mutar "la invitación NO cae con su reserva (la purga se estrellaría)" "$MIG" \
  "            \$table->unsignedBigInteger('order_item_id')->unique();
            \$table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();" \
  "            \$table->unsignedBigInteger('order_item_id')->unique();
            \$table->foreign('order_item_id')->references('id')->on('order_items')->restrictOnDelete();"

mutar "las respuestas NO caen con su invitación" "$MIG" \
  "->on('party_invitations')->cascadeOnDelete();" \
  "->on('party_invitations')->restrictOnDelete();"

mutar "podar una respuesta se lleva la PRUEBA LEGAL por delante" "$MIG" \
  "->on('invitation_replies')->nullOnDelete();" \
  "->on('invitation_replies')->cascadeOnDelete();"

echo '── Privacidad y estructura ───────────────────────────────────────────────────────────────'

mutar "un nombre repetido se rechaza — y eso CONFIRMA quién va a la fiesta (V6)" "$MIG" \
  "\$table->index(['order_item_id', 'child_key']);" \
  "\$table->unique(['order_item_id', 'child_key']);"

mutar "una reserva admite DOS invitaciones (dos enlaces vivos, uno sin anular)" "$MIG" \
  "\$table->unsignedBigInteger('order_item_id')->unique();" \
  "\$table->unsignedBigInteger('order_item_id');"

echo '── Conservación y lista blanca ───────────────────────────────────────────────────────────'

mutar "las respuestas se conservan diez veces más de lo decidido (V3)" "$REP" \
  'public const RETENTION_DAYS = 14;' \
  'public const RETENTION_DAYS = 140;'

mutar "un padre puede dar por repasada su propia respuesta (SEC-10)" "$REP" \
  "    'companion',
])]" \
  "    'companion',
    'adopted_at',
])]"

mutar "…o por descartada (la otra mitad de la misma lista blanca)" "$REP" \
  "    'companion',
])]" \
  "    'companion',
    'dismissed_at',
])]"

mutar "sin respaldo, todos los nombres no latinos colisionan en la misma clave" "$KEY" \
  "\$base = self::collapse(\$ascii) !== '' ? \$ascii : \$full;" \
  "\$base = \$ascii;"

echo '── CONTROL: cambiar prosa NO puede poner nada en rojo ────────────────────────────────────'

mutar "un comentario reescrito" "$MIG" \
  '// El enlace. **Token opaco de 12 base62' \
  '// (prosa mutada por el arnés) **Token opaco de 12 base62' \
  control

echo
restaurar
SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo "✗ el árbol NO quedó byte a byte como estaba." >&2
    exit 1
fi
echo "✓ árbol restaurado byte a byte (sha1 $SHA_ANTES)"
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · sin aplicar: ${no_aplicados:-0}"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "${no_aplicados:-0}" == 0 ]] || exit 1
