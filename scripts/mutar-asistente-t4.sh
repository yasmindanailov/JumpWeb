#!/usr/bin/env bash
# Arnés de mutación de la T4 del asistente de «Crear pedido» (`DECISIONES #466`): el DESENLACE.
#
# Lo que protege es que la pantalla NO MIENTA. El mostrador la lee en voz alta y su afirmación más
# peligrosa es «se le ha enviado»: hay clientes sin correo, y con ellos no se envía nada. Protege
# además lo que sustituyó a la redirección —que el carrito se vacíe, o un segundo clic cobra dos
# veces— y que del desenlace no se vuelva al asistente.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='CreateManualOrderDoneTest|CreateManualOrderDependentsTest|CreateManualOrderPageTest|CreateManualOrderStepsTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Filament/Pages/CreateManualOrderPage.php
    resources/views/filament/pages/partials/manual-order-done.blade.php
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

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
V=resources/views/filament/pages/partials/manual-order-done.blade.php

# ── Que la pantalla no mienta sobre el correo ─────────────────────────────────────────────────
mutar "la pantalla da por enviada la confirmacion SIEMPRE" "$P" \
  "                'confirmation' => filled(\$email)," \
  "                'confirmation' => true,"

mutar "cuenta los formularios de invitados sin mirar si hay correo" "$P" \
  "                'guest_form' => filled(\$email) ? \$postForm->count() : 0," \
  "                'guest_form' => \$postForm->count(),"

mutar "cuenta los justificantes sin mirar si hay correo" "$P" \
  "                'guardian' => filled(\$email) ? \$guardian->count() : 0," \
  "                'guardian' => \$guardian->count(),"

# ⚠️ **No hay mutación para el filtro `needsGuestForm()` del post-form, y no es un olvido**: recién
# creado el pedido, TODA reserva con post-form está pendiente, así que filtrar o no filtrar da el
# mismo número — es una mutación EQUIVALENTE y el arnés la marcaría como «no muerde» para siempre. El
# filtro se conserva porque es literalmente la condición que aplica `ManualOrderFulfiller` al enviar,
# y la pantalla existe para decir lo que él hizo.

mutar "la pantalla se calla que no hay correo" "$V" \
  '            <p class="cmo-done__warn" data-done-no-mail>{{ __('"'"'admin.orders.create_manual.done_no_mail_body'"'"') }}</p>' \
  '            <p class="cmo-done__warn" data-done-no-mail></p>'

mutar "el bloque del correo se pinta igual sin correo" "$V" \
  '        @if ($tieneCorreo)
            <ul class="cmo-done__sent" data-done-sent>' \
  '        @if (true)
            <ul class="cmo-done__sent" data-done-sent>'

# ── Lo que sustituyó a la redirección ─────────────────────────────────────────────────────────
mutar "el carrito NO se vacia al cobrar (un segundo clic cobra dos veces)" "$P" \
  '        $this->createdOrderId = (int) $order->id;
        $this->cart = [];' \
  '        $this->createdOrderId = (int) $order->id;'

mutar "desde el desenlace se puede volver atras" "$P" \
  '    public function back(): void
    {
        if ($this->isDone()) {
            return;
        }' \
  '    public function back(): void
    {
        if (false) {
            return;
        }'

mutar "el indicador deja saltar a un paso desde el desenlace" "$P" \
  '    public function goToStep(int $step): void
    {
        if ($this->isDone()) {
            return;
        }' \
  '    public function goToStep(int $step): void
    {
        if (false) {
            return;
        }'

mutar "«otro pedido» conserva el CLIENTE del anterior" "$P" \
  '        $this->clearPhoneMatch();
        $this->form->fill();' \
  '        $this->clearPhoneMatch();'

mutar "«otro pedido» deja el desenlace puesto" "$P" \
  '        $this->createdOrderId = null;
        $this->dependentsSkipped = 0;' \
  '        $this->dependentsSkipped = 0;'

# ── Lo que la pantalla enseña del pedido ──────────────────────────────────────────────────────
mutar "el desenlace pierde el CODIGO del pedido" "$V" \
  '<p class="cmo-done__code" data-done-code>{{ $resumen['"'"'code'"'"'] }}</p>' \
  '<p class="cmo-done__code" data-done-code></p>'

mutar "los menores sin asignar dejan de decirse" "$P" \
  '        $this->dependentsSkipped = ($outcome->skipped > 0 || $outcome->abortedBecause !== null)
            ? max(1, $outcome->skipped)
            : 0;' \
  '        $this->dependentsSkipped = 0;'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
