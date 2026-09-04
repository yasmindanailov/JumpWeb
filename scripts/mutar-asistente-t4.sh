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
mutar "la confirmacion sale marcada como ENVIADA siempre" "$P" \
  "                    'kind' => 'confirmation',
                    'subject' => null,
                    'when' => null,
                    'sent' => filled(\$email)," \
  "                    'kind' => 'confirmation',
                    'subject' => null,
                    'when' => null,
                    'sent' => true,"

mutar "el formulario de invitados sale enviado sin mirar si hay correo" "$P" \
  "                    'kind' => 'guest_form',
                    'subject' => \$item->displayProductName(),
                    'when' => \$this->slotLabel(\$item),
                    'sent' => filled(\$email)," \
  "                    'kind' => 'guest_form',
                    'subject' => \$item->displayProductName(),
                    'when' => \$this->slotLabel(\$item),
                    'sent' => true,"

mutar "el justificante sale enviado sin mirar si hay correo" "$P" \
  "                    'kind' => 'guardian',
                    'subject' => \$item->displayProductName(),
                    'when' => \$this->slotLabel(\$item),
                    'sent' => filled(\$email)," \
  "                    'kind' => 'guardian',
                    'subject' => \$item->displayProductName(),
                    'when' => \$this->slotLabel(\$item),
                    'sent' => true,"

mutar "los entregables que NO han salido se esconden en vez de decirse" "$P" \
  "                ...\$postForm->map(fn (OrderItem \$item): array => [" \
  "                ...(filled(\$email) ? \$postForm : collect())->map(fn (OrderItem \$item): array => ["

# ⚠️ **No hay mutación para el filtro `needsGuestForm()` del post-form, y no es un olvido**: recién
# creado el pedido, TODA reserva con post-form está pendiente, así que filtrar o no filtrar da el
# mismo número — es una mutación EQUIVALENTE y el arnés la marcaría como «no muerde» para siempre. El
# filtro se conserva porque es literalmente la condición que aplica `ManualOrderFulfiller` al enviar,
# y la pantalla existe para decir lo que él hizo.

mutar "la pantalla se calla que no hay correo" "$V" \
  '                    {{ __('"'"'admin.orders.create_manual.done_no_mail_body'"'"') }}' \
  ''"''"''

mutar "el aviso de «sin correo» se pinta tambien cuando SI lo hay" "$V" \
  '        @unless ($tieneCorreo)' \
  '        @unless (false)'

mutar "«entregalo tu» tambien donde no hay nada que entregar" "$V" \
  '                            @elseif ($entrega['"'"'url'"'"'])' \
  '                            @elseif (true)'

mutar "la pastilla dice «enviado» aunque el entregable no haya salido" "$V" \
  '                            @if ($entrega['"'"'sent'"'"'])' \
  '                            @if (true)'

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
