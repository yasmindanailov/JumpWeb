#!/usr/bin/env bash
# Arnés de mutación de la R2a de los correos (`specs/correos-rediseno.md` §4.3): el CUERPO EN ORDEN (y que no se mezcla con la
# R1), el aire de 32 hasta un bloque con filete, los ENLACES POR NOMBRE (escapados, con su dirección en texto, y su regla en
# R1·T), «Responde» que llega al parque solo con su correo, el QR INCRUSTADO, el `.ics` de la reserva (su ventana efectiva y
# que una cancelada no se abre) y los radios como rol.
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento). Reglas de la casa dentro: verde antes de
# mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su ancla es ÚNICA · un CONTROL que no
# debe morder · restaurar por COPIA DE SEGURIDAD, guardada por la RUTA ENTERA (`#181`; `mutar-correo-r1c.sh`, 03-10).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
TODOS='MailBodyTest|MailTextsTest|ReservationCalendarTest|MailThemeTest'
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
FICHEROS=(
    app/Notifications/Support/MailDocument.php
    app/Notifications/Support/BrandedMailMessage.php
    app/Domain/Content/Services/MailTextRules.php
    resources/views/correo/html/qr.blade.php
    resources/views/correo/html/lista.blade.php
    app/Domain/Booking/Models/OrderItem.php
    app/Http/Controllers/ReservationCalendarController.php
)
copia() { printf '%s/%s' "$TMP" "${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; docker compose exec -u sail -T laravel.test php artisan view:clear >/dev/null 2>&1' EXIT
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

MD=app/Notifications/Support/MailDocument.php
BM=app/Notifications/Support/BrandedMailMessage.php
TR=app/Domain/Content/Services/MailTextRules.php
QR=resources/views/correo/html/qr.blade.php
LI=resources/views/correo/html/lista.blade.php
OI=app/Domain/Booking/Models/OrderItem.php
RC=app/Http/Controllers/ReservationCalendarController.php

# ── El CUERPO EN ORDEN ────────────────────────────────────────────────────────────────────────────
mutar "el cuerpo se ignora (vuelve el orden fijo)" "$MD" \
  "if (\$cuerpo !== []) {" \
  "if (false) {" \
  'MailBodyTest'
mutar "el cuerpo se mezcla con la R1 sin decir nada" "$MD" \
  "if (\$mezcla !== []) {" \
  "if (false) {" \
  'MailBodyTest'
mutar "el aire hasta un bloque con filete no sube a 32" "$MD" \
  "self::conRaya(\$siguiente) => 32," \
  "self::conRaya(\$siguiente) => 28," \
  'MailBodyTest'
mutar "«raya: false» no quita el filete" "$MD" \
  "&& (\$b['raya'] ?? true) !== false" \
  "" \
  'MailBodyTest'

# ── Los ENLACES POR NOMBRE ────────────────────────────────────────────────────────────────────────
mutar "la URL de un enlace sale sin escapar" "$MD" \
  "href=\"'.e(\$url).'\"" \
  "href=\"'.\$url.'\"" \
  'MailBodyTest'
mutar "en texto, el enlace pierde su dirección" "$MD" \
  "\$m[1].' ('.\$url.')';" \
  "\$m[1];" \
  'MailBodyTest'
mutar "R1·T deja quitar o inventar un enlace" "$TR" \
  "if (self::enlaces(\$texto) !== self::enlaces(\$fabrica)) {" \
  "if (false) {" \
  'MailTextsTest'

# ── «Responde», el QR, el .ics y los radios ───────────────────────────────────────────────────────
mutar "«Responde» sin correo del parque (un replyTo vacío)" "$BM" \
  "if (\$correo === '' || filter_var(\$correo, FILTER_VALIDATE_EMAIL) === false) {" \
  "if (false) {" \
  'MailBodyTest'
mutar "el QR no se incrusta (una imagen que no viaja)" "$QR" \
  "\$src = isset(\$message)" \
  "\$src = false && isset(\$message)" \
  'MailBodyTest'
mutar "el .ics cuenta la duración del producto, sin la hora extra" "$OI" \
  "\$inicio->addMinutes(\$minutos)" \
  "\$inicio->addMinutes((int) \$this->ticketType?->duration_min)" \
  'ReservationCalendarTest|InvitationSharingTest'
mutar "el .ics de una reserva cancelada se abre" "$RC" \
  "|| \$reserva->isCancelled() " \
  " " \
  'ReservationCalendarTest'
mutar "un radio escrito a mano (el punto de la lista)" "$LI" \
  "background:{{ \$t->claro('punto') }};border-radius:{{ \$t->radio('pildora') }}px;" \
  "background:{{ \$t->claro('punto') }};border-radius:4px;" \
  'MailThemeTest'

# ── CONTROL: la misma regla, escrita de otra forma, NO debe morder ────────────────────────────────
control "el cuerpo, con count()" "$MD" \
  "if (\$cuerpo !== []) {" \
  "if (count(\$cuerpo) > 0) {" \
  'MailBodyTest'

echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
