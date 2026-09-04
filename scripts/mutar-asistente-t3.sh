#!/usr/bin/env bash
# Arnés de mutación de la T3 del asistente de «Crear pedido» (`docs/specs/asistente-crear-pedido.md`,
# `DECISIONES #464`): la CESTA a la oferta y el calendario del paso «Cuándo».
#
# Lo que protege es de dos naturalezas y por eso van juntas en un mismo arnés: una mitad es DOMINIO
# —que el panel descuente los ocupantes que su propia cesta retiene, con la cuenta ÚNICA de
# `CartOccupants` y no con una copia— y la otra es la PANTALLA —que el calendario solo deje elegir
# días que la oferta admite, que el servidor lo vuelva a comprobar y que elegir día olvide la hora—.
#
# Reglas de la casa construidas dentro:
#   · VERDE antes de mutar (si no, el veredicto no vale nada);
#   · veredicto por CÓDIGO DE SALIDA, nunca buscando texto en la salida (la trampa de `#317`);
#   · comprobar que la mutación SE APLICÓ (una que no casa da un falso «no muerde», la de `#463`);
#   · restaurar con COPIA DE SEGURIDAD y no con `git checkout` — que se llevó trabajo sin commitear
#     dos veces (`#181`, `#296`), y así el arnés corre también con el árbol sucio.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='CreateManualOrderPageTest|CreateManualOrderCartAvailabilityTest|CreateManualOrderCalendarTest|CreateManualOrderTabletTest|ManualOrderIgnoresMinAdvanceTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
trap 'restaurar_todo; rm -rf "$TMP"' EXIT

FICHEROS=(
    app/Filament/Pages/CreateManualOrderPage.php
    app/Domain/Booking/Services/CartOccupants.php
    resources/views/filament/pages/partials/manual-order-calendar.blade.php
)

restaurar_todo() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] && cp "$TMP/$(basename "$f")" "$f" && touch "$f"
    done
}

for f in "${FICHEROS[@]}"; do
    [ -f "$f" ] || { echo "✗ falta $f" >&2; exit 1; }
    cp "$f" "$TMP/$(basename "$f")"
done

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

P=app/Filament/Pages/CreateManualOrderPage.php
C=app/Domain/Booking/Services/CartOccupants.php
V=resources/views/filament/pages/partials/manual-order-calendar.blade.php

# ── DOMINIO · la cesta entra en la oferta ─────────────────────────────────────────────────────
mutar "el panel vuelve a ofrecer SIN su cesta (el defecto que cierra la T3)" "$P" \
  '            $occupants['"'"'entries'"'"'],
            $occupants['"'"'packs'"'"'],' \
  '            [],
            [],'

mutar "solo se olvidan las PLAZAS retenidas" "$P" \
  '            $occupants['"'"'entries'"'"'],' \
  '            [],'

mutar "solo se olvida el CUPO de fiestas retenido" "$P" \
  '            $occupants['"'"'packs'"'"'],' \
  '            [],'

mutar "el memo de las horas deja de ver la cesta (sirve las plazas de antes)" "$P" \
  '.$this->cartFingerprint();' \
  ';'

mutar "la huella de la cesta se queda en el numero de lineas" "$P" \
  '        return md5(json_encode(array_map(static fn (array $line): array => [' \
  '        return (string) count($this->cart); /* '

mutar "los ocupantes de PLAZAS dejan de filtrar por dia" "$C" \
  '        foreach ($cart as $i => $line) {
            if (($line['"'"'date'"'"'] ?? null) !== $date) {
                continue;
            }' \
  '        foreach ($cart as $i => $line) {'

mutar "el cupo de fiestas deja de filtrar por dia" "$C" \
  '        foreach ($cart as $line) {
            if (($line['"'"'date'"'"'] ?? null) !== $date) {
                continue;
            }' \
  '        foreach ($cart as $line) {'

mutar "una ENTRADA cuenta como fiesta en el cupo de packs" "$C" \
  '            if (! $type || (int) $type->zone_id !== $zoneId || ! $type->isPack()) {' \
  '            if (! $type || (int) $type->zone_id !== $zoneId) {'

# ── PANTALLA · el calendario del paso «Cuándo» ────────────────────────────────────────────────
mutar "el calendario deja pulsables los dias que la oferta NO admite" "$V" \
  '@disabled(! $dia['"'"'offerable'"'"'])' \
  '@disabled(false)'

mutar "el servidor acepta cualquier dia que le manden (AFORO-02)" "$P" \
  '        if (! in_array($ymd, $this->offerableDates(), true)) {
            return;
        }' \
  '        if (false) {
            return;
        }'

# ⚠️ Estas dos mutaciones tocan la MISMA línea escrita en tres sitios: se acotan con su contexto,
# porque `str.replace(…, 1)` muta la PRIMERA ocurrencia y el veredicto sería sobre otro sujeto — que
# fue justo lo que pasó la primera vez que corrió este arnés (y destapó que el reinicio tras añadir
# al carrito no tenía guarda).
mutar "elegir dia CONSERVA la hora del dia anterior" "$P" \
  "        \$this->data['sel_date'] = \$ymd;" \
  "        \$this->data['sel_date'] = \$ymd; return;"

mutar "anadir al carrito deja la hora de la linea anterior puesta" "$P" \
  "        \$this->data['sel_date'] = null;
        \$this->data['sel_time'] = null;" \
  "        \$this->data['sel_date'] = null;"

mutar "el lector se traga un mes sin oferta escrito desde el navegador" "$P" \
  '        if ($this->calMonth !== null && in_array($this->calMonth, $meses, true)) {' \
  '        if ($this->calMonth !== null) {'

mutar "el calendario pierde la marca del dia elegido" "$V" \
  "'is-selected' => \$dia['selected']," \
  "'is-selected' => false,"

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
