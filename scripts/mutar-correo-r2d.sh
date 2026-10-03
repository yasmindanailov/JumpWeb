#!/usr/bin/env bash
# Arnés de mutación de la R2d de los correos (`specs/correos-rediseno.md` §4.3): LA VÍSPERA —el 3, «Mañana os esperamos», a
# toda reserva que no es una fiesta y SIEMPRE (`#915`, a), o con «Hoy» dos horas antes; el 4, «Un repaso antes de mañana», a una
# fiesta solo si le queda algo (`#714`)—, solo a pedidos PAGADOS, y lo que dice cada uno.
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento). Reglas de la casa dentro: verde antes de
# mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su ancla es ÚNICA · un CONTROL que no
# debe morder · restaurar por COPIA DE SEGURIDAD, guardada por la RUTA ENTERA (`#181`; `mutar-correo-r1c.sh`, 03-10).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
TODOS='VisitReminderNoticeTest|VisitEveNoticeTest'
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
FICHEROS=(
    app/Console/Commands/SendVisitEveNotices.php
    app/Notifications/VisitReminderNotice.php
    app/Notifications/VisitEveNotice.php
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

CM=app/Console/Commands/SendVisitEveNotices.php
V3=app/Notifications/VisitReminderNotice.php
V4=app/Notifications/VisitEveNotice.php

# ── Cuándo sale y a quién ─────────────────────────────────────────────────────────────────────────
mutar "un pedido sin pagar también recibe la víspera" "$CM" \
  "->whereHas('order', fn (\$q) => \$q->where('status', Order::STATUS_PAID))" \
  "" \
  'VisitReminderNoticeTest|VisitEveNoticeTest'
mutar "una fiesta sin nada pendiente también recibe el 4" "$CM" \
  "if (\$party && ! \$work->any()) {" \
  "if (false) {" \
  'VisitReminderNoticeTest|VisitEveNoticeTest'
mutar "una fiesta de hoy recibe el «Hoy»" "$CM" \
  "return ! \$r->isGuestFormReservation() && \$start->greaterThan(\$now)" \
  "return \$start->greaterThan(\$now)" \
  'VisitReminderNoticeTest'
mutar "el «Hoy», tres horas antes y no dos" "$CM" \
  "public const SAME_DAY_HOURS = 2;" \
  "public const SAME_DAY_HOURS = 3;" \
  'VisitReminderNoticeTest'
mutar "una reserva ya empezada recibe el «Hoy»" "$CM" \
  " && \$start->greaterThan(\$now)" \
  "" \
  'VisitReminderNoticeTest'
mutar "la fiesta recibe el 3 y no el 4" "$CM" \
  "return \$party ? new VisitEveNotice(\$reservation, \$work) : new VisitReminderNotice(\$reservation, \$work, \$today);" \
  "return new VisitReminderNotice(\$reservation, \$work, \$today);" \
  'VisitEveNoticeTest'

# ── Lo que dicen ──────────────────────────────────────────────────────────────────────────────────
mutar "el aviso de los menores también en un grupo" "$V3" \
  "if (! \$grupo && \$reserva->faltanMenores()) {" \
  "if (\$reserva->faltanMenores()) {" \
  'VisitReminderNoticeTest'
mutar "el enlace de autorizaciones sin que el producto las pida" "$V3" \
  "\$r->ticketType?->guardianMode() !== TicketType::GUARDIAN_NONE ? \$r->guardianAuthorizationSignedUrl() : null" \
  "\$r->guardianAuthorizationSignedUrl()" \
  'VisitReminderNoticeTest'
mutar "el QR del 4, principal (le quita el sitio al botón)" "$V4" \
  "secundario: true" \
  "secundario: false" \
  'VisitEveNoticeTest'
mutar "el 4 nombra las fichas aunque estén todas" "$V4" \
  "if (\$p->guestsMissing() > 0) {" \
  "if (true) {" \
  'VisitEveNoticeTest'

# ── CONTROL: la misma regla, escrita de otra forma, NO debe morder ────────────────────────────────
control "las autorizaciones, el máximo al revés" "$V3" \
  "\$total = max((int) \$r->quantity, \$this->pending->minorsUnresolved);" \
  "\$total = max(\$this->pending->minorsUnresolved, (int) \$r->quantity);" \
  'VisitReminderNoticeTest'

echo "$muerden/$total muerden · control $([[ $control_ok -eq 1 ]] && echo verde || echo ROJO)"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
