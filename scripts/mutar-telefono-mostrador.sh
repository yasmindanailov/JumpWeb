#!/usr/bin/env bash
# Arnés de mutación del TELÉFONO del cliente en el mostrador (`DECISIONES #440`).
#
# Lo que protege, en orden de daño:
#  · que la puerta esté en las CUATRO capas y no solo en la navegación — `addLineToCart()` es
#    público y no mira el paso del cliente, y ése era el bypass reproducido por la revisión;
#  · que el «tiene teléfono» lo decida UNA autoridad (`CheckoutDuties`) y no tres redacciones;
#  · ❗ que `create()` **avise pero NO bloquee** la venta (`[DECIDIDO owner]`): la mutación que lo
#    convierte en un `return` tiene que MORDER, o alguien «terminará el trabajo» algún día.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca `grep
# passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA y no con `git checkout`
# (`#181`: un `git checkout` para deshacer una mutación se llevó trabajo sin commitear).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='CreateManualOrderCustomerPhoneTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Filament/Pages/CreateManualOrderPage.php
    app/Domain/Identity/Services/CheckoutDuties.php
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
        restaurar
        return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    restaurar
}

P=app/Filament/Pages/CreateManualOrderPage.php
C=app/Domain/Identity/Services/CheckoutDuties.php

# ── 1 · La puerta del paso 1 ──────────────────────────────────────────────────────────────────
mutar "el paso 1 deja de retener al cliente sin teléfono" "$P" \
  '            self::STEP_CUSTOMER => filled($this->data['"'"'customer_id'"'"'] ?? null)
                && (! $this->customerNeedsPhone() || filled($this->data['"'"'customer_phone'"'"'] ?? null)),' \
  '            self::STEP_CUSTOMER => filled($this->data['"'"'customer_id'"'"'] ?? null),'

mutar "retiene aunque el operador YA lo haya escrito (botón muerto)" "$P" \
  '                && (! $this->customerNeedsPhone() || filled($this->data['"'"'customer_phone'"'"'] ?? null)),' \
  '                && ! $this->customerNeedsPhone(),'

# ── 2 · La autoridad única ────────────────────────────────────────────────────────────────────
mutar "el predicado se redacta a mano en vez de preguntar a CheckoutDuties" "$P" \
  '        return $customer !== null && app(CheckoutDuties::class)->pendingFor($customer)['"'"'phone'"'"'];' \
  '        return $customer !== null && $customer->phone === null;'

mutar "el rótulo del cliente vuelve a su tercera redacción" "$P" \
  '        $hasPhone = ! app(CheckoutDuties::class)->pendingFor($u)['"'"'phone'"'"'];' \
  '        $hasPhone = (string) ($u->phone ?? '"'"''"'"') !== '"'"''"'"';'

# ── 3 · La escritura ──────────────────────────────────────────────────────────────────────────
mutar "next() deja de saldar el teléfono escrito" "$P" \
  '        if ($this->step === self::STEP_CUSTOMER) {
            $this->saveMissingPhone();
        }' \
  '        if (false) {
            $this->saveMissingPhone();
        }'

mutar "el teléfono escrito NO deja rastro en la auditoría" "$P" \
  '            AuditLogger::log('"'"'orders.manual_customer_phone_added'"'"', $customer);' \
  '            $customer->exists;'

mutar "recordPhone() PISA un teléfono que ya existía" "$C" \
  '        if (! $this->pendingFor($user)['"'"'phone'"'"'] || ! is_string($phone) || trim($phone) === '"'"''"'"') {' \
  '        if (! is_string($phone) || trim($phone) === '"'"''"'"') {'

mutar "recordPhone() dice que escribió y no escribe" "$C" \
  '        $user->forceFill(['"'"'phone'"'"' => trim($phone)])->save();

        return true;' \
  '        return true;'

# ── 4 · El bypass de aguas abajo (el que la revisión reprodujo) ───────────────────────────────
mutar "addLineToCart() deja de mirar el teléfono (el bypass del paso 1)" "$P" \
  '        if ($this->customerNeedsPhone()) {
            Notification::make()->warning()
                ->title(__('"'"'admin.orders.create_manual.customer_phone_required'"'"'))
                ->send();

            return;
        }' \
  '        if (false) {
            return;
        }'

# ── 5 · ❗ Y la decisión del owner: avisar, NO bloquear ───────────────────────────────────────
mutar "create() BLOQUEA la venta (lo que el owner decidió que no)" "$P" \
  '            AuditLogger::log('"'"'orders.manual_created_without_phone'"'"', $customer);
        }' \
  '            AuditLogger::log('"'"'orders.manual_created_without_phone'"'"', $customer);

            return;
        }'

mutar "vender sin teléfono deja de dejar rastro" "$P" \
  '        if (app(CheckoutDuties::class)->pendingFor($customer)['"'"'phone'"'"']) {
            Notification::make()->warning()' \
  '        if (false) {
            Notification::make()->warning()'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
