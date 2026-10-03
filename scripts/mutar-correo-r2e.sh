#!/usr/bin/env bash
# Arnés de mutación de la R2e de los correos (`specs/correos-rediseno.md` §4.3): el 5, «El pago no ha salido» —Bizum solo si el
# parque lo tiene (`#915`, c), hasta qué hora sigue guardada (en la zona del parque, y solo si no ha pasado), la ayuda solo con
# WhatsApp— y el 6, «Tu hora se ha liberado» —por dónde escribir, en su orden, y su titular con varias reservas—.
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento). Reglas de la casa dentro: verde antes de
# mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su ancla es ÚNICA · un CONTROL que no
# debe morder · restaurar por COPIA DE SEGURIDAD, guardada por la RUTA ENTERA (`#181`; `mutar-correo-r1c.sh`, 03-10).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
TODOS='PaymentMailsTest|OrderNotificationsTest'
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
FICHEROS=(
    app/Notifications/OrderPaymentDeclined.php
    app/Notifications/OrderExpiredWithoutPayment.php
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

C5=app/Notifications/OrderPaymentDeclined.php
C6=app/Notifications/OrderExpiredWithoutPayment.php

# ── El 5 ──────────────────────────────────────────────────────────────────────────────────────────
mutar "Bizum aunque el parque no lo tenga" "$C5" \
  "\$bizum = \$this->conBizum ?? in_array('bizum', MarcasDePago::elegidas(), true);" \
  "\$bizum = \$this->conBizum ?? true;" \
  'OrderNotificationsTest'
mutar "la hora guardada, aunque ya haya pasado" "$C5" \
  "Carbon::now()->lessThan(\$this->order->expires_at)" \
  "true" \
  'PaymentMailsTest'
mutar "la hora guardada, en UTC y no en la del parque" "$C5" \
  "DisplayTime::format(\$this->order->expires_at, 'H:i')" \
  "\$this->order->expires_at->format('H:i')" \
  'PaymentMailsTest'
mutar "la ayuda por WhatsApp sin WhatsApp" "$C5" \
  "if (MailPie::current()->whatsapp !== null) {" \
  "if (true) {" \
  'PaymentMailsTest'

# ── El 6 ──────────────────────────────────────────────────────────────────────────────────────────
mutar "el WhatsApp deja de ser el primer canal" "$C6" \
  "\$pie->whatsapp !== null => 'https://wa.me/'.\$pie->whatsapp," \
  "" \
  'PaymentMailsTest'
mutar "con varias reservas, el titular de una" "$C6" \
  "\$una !== null ? 'headline' : 'headline_varias'" \
  "'headline'" \
  'PaymentMailsTest'
mutar "la frase de escribir, sin ningún canal" "$C6" \
  "if (self::contacto() !== null) {" \
  "if (true) {" \
  'PaymentMailsTest'

# ── CONTROL: la misma regla, escrita de otra forma, NO debe morder ────────────────────────────────
control "el reintento, con sus parámetros vacíos" "$C5" \
  "\$retry = route('account.orders');" \
  "\$retry = route('account.orders', []);" \
  'PaymentMailsTest|OrderNotificationsTest'

echo "$muerden/$total muerden · control $([[ $control_ok -eq 1 ]] && echo verde || echo ROJO)"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
