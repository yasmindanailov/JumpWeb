#!/usr/bin/env bash
# Arnés de mutación de `#429`: **un producto sin precio para la tarifa del día NO se ofrece.**
#
# Lo que protege es que la oferta y el cobro digan lo mismo. Antes, «Kids · Ilimitada» —sin precio en
# la tarifa de fin de semana— ofrecía sus 10 horas del sábado y el checkout las rechazaba con un
# `unavailable` sin explicación: `AFORO-02` por la puerta del precio. La oferta de COMPLEMENTOS ya
# funcionaba así desde `#410`; esto lo extiende al producto base.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='AvailabilityReaderTest|SlotOfferTest|SlotOfferPathParityTest|ApiOverheadTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
F=app/Domain/Booking/Services/SlotOffer.php
cp "$F" "$TMP/SlotOffer.php"
trap 'cp "$TMP/SlotOffer.php" "$F"; touch "$F"; rm -rf "$TMP"' EXIT

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0
mutar() {
    local nombre="$1" buscar="$2" poner="$3"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' "$F" "$buscar" "$poner"
    if cmp -s "$F" "$TMP/SlotOffer.php"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$F"
    if verde; then echo "  ✗ NO muerde: $nombre"; else echo "  ✓ muerde:    $nombre"; muerden=$((muerden + 1)); fi
    cp "$TMP/SlotOffer.php" "$F"; touch "$F"
}

mutar "la oferta deja de mirar el precio del día" \
  "        if (! \$this->hasPriceOn(\$type, \$ymd)) {
            return false;
        }" \
  ""

mutar "el atajo del caso normal se traga TODO (nunca comprueba)" \
  "        return array_diff(\$this->activeRateIds, \$this->sellableRates[\$id]) !== [];" \
  "        return false;"

mutar "los TRAMOS de cantidad dejan de contar como precio" \
  "                \$type->priceTiers()->distinct()->pluck('rate_type_id')->all()," \
  "                []," 

mutar "las tarifas del horizonte se resuelven UNA A UNA (el coste que #465 quitó)" \
  "        if (\$this->needsPriceCheck(\$type)) {
            \$this->primeRates(\$this->daysBetween(\$from, \$to));
        }" \
  ""

echo
echo "mutaciones que muerden: ${muerden}/${total}"
[ "$muerden" -eq "$total" ]
