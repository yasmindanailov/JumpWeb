#!/usr/bin/env bash
# Arnés de mutación de la R2b de los correos (`specs/correos-rediseno.md` §4.3): la RESERVA HECHA con sus tres caras (el 1, el
# 1b y el 2), el dinero del resguardo SOLO del libro de la reserva, quién firma (`#875`), «Antes de venir», el plazo de cambio,
# los pasos de una fiesta (que ya no sale aparte, `#915`) y el libro entero solo cuando al pedido le pasó algo.
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento). Reglas de la casa dentro: verde antes de
# mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su ancla es ÚNICA · un CONTROL que no
# debe morder · restaurar por COPIA DE SEGURIDAD, guardada por la RUTA ENTERA (`#181`; `mutar-correo-r1c.sh`, 03-10).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
TODOS='ReservationMailTest|RedsysReturnHandlerTest|DepositSurfacesTest|EmailBookBlockTest'
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
FICHEROS=(
    app/Notifications/Support/MailReservation.php
    app/Notifications/OrderConfirmation.php
)
copia() { printf '%s/%s' "$TMP" "${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
[[ $(find "$TMP" -type f | wc -l) -eq ${#FICHEROS[@]} ]] || { echo '✗ hay copias que se pisan: el árbol no se podría restaurar' >&2; exit 1; }
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
    if cmp -s "$fichero" "$(copia "$fichero")"; then
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
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — se pone rojo por algo que no es la regla"; control_ok=0
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

MR=app/Notifications/Support/MailReservation.php
OC=app/Notifications/OrderConfirmation.php

# ── Las CARAS ─────────────────────────────────────────────────────────────────────────────────────
mutar "una fiesta se lee como unas entradas" "$MR" \
  "\$r->isGuestFormReservation()) => self::FIESTA," \
  "false) => self::FIESTA," \
  'ReservationMailTest'
mutar "un grupo se lee como unas entradas" "$MR" \
  "\$r->ticketType?->isPack() === true) => self::GRUPO," \
  "false) => self::GRUPO," \
  'ReservationMailTest'
mutar "el asunto de la fiesta olvida a quien cumple" "$OC" \
  "\$quien !== '' =>" \
  "false =>" \
  'ReservationMailTest'

# ── El DINERO, solo del libro ─────────────────────────────────────────────────────────────────────
mutar "un libro que no cuadra afirma un importe" "$MR" \
  "if (! \$libro->isConsistent) {" \
  "if (false) {" \
  'ReservationMailTest'
mutar "el libro entero en un pedido recién pagado" "$MR" \
  "return \$historia ? EmailBookBlock::forOrder(\$this->pedido) : null;" \
  "return EmailBookBlock::forOrder(\$this->pedido);" \
  'DepositSurfacesTest|EmailBookBlockTest'

# ── Quién firma y «Antes de venir» ────────────────────────────────────────────────────────────────
mutar "quién firma, sin mirar si la instalación firma dentro" "$MR" \
  "if (\$cara === self::ENTRADAS && WaiverSettings::isInternal()) {" \
  "if (\$cara === self::ENTRADAS) {" \
  'ReservationMailTest'
mutar "un menor desvinculado cuenta como uno a su cargo" "$MR" \
  "->every(static fn (Dependent \$d): bool => \$d->removed_at !== null)" \
  "->isEmpty()" \
  'ReservationMailTest'
mutar "la hora de llegada con varias reservas" "$MR" \
  "if ((\$r = \$this->unica()) !== null) {" \
  "if ((\$r = \$this->reservas->first()) !== null) {" \
  'ReservationMailTest'

# ── El plazo, los pasos y responder ───────────────────────────────────────────────────────────────
mutar "devuelve la señal sin haberla cobrado" "$MR" \
  " && \$this->libroDe(\$r)->hasDeposit;" \
  ";" \
  'ReservationMailTest'
mutar "fuera de plazo, se sigue ofreciendo cambiar" "$MR" \
  "if (! Carbon::now()->lessThan(\$limite)) {" \
  "if (false) {" \
  'ReservationMailTest'
mutar "la fiesta, sin sus pasos (el formulario se pierde)" "$OC" \
  "if ((\$pasos = \$reserva->pasos(\$r)) !== null) {" \
  "if (false && (\$pasos = \$reserva->pasos(\$r)) !== null) {" \
  'ReservationMailTest|RedsysReturnHandlerTest'
mutar "«Responde a este correo» también en una fiesta" "$OC" \
  "if (! \$fiesta) {" \
  "if (true) {" \
  'ReservationMailTest'

# ── CONTROL: la misma regla, escrita de otra forma, NO debe morder ────────────────────────────────
control "la hora de llegada, sin el ayudante" "$MR" \
  "['time' => self::hora(\$r)]" \
  "['time' => substr((string) \$r->slot?->start_time, 0, 5)]" \
  'ReservationMailTest'

echo "$muerden/$total muerden · control $([[ $control_ok -eq 1 ]] && echo verde || echo ROJO)"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
