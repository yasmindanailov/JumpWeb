<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\Money;

/**
 * **Las ETIQUETAS del libro, compuestas UNA vez** (`specs/desglose-libro.md` §4.3). Viven en
 * `tickets.journal.*` —los tres idiomas del cliente, y el chino del panel— y son **neutras de voz**
 * (sin «tu» ni «el cliente»): el mismo diccionario sirve al cliente y al operador (`[DECIDIDO
 * owner]` D1, «cliente y operador ven lo MISMO»).
 *
 * ⚠️ Cada etiqueta dice QUÉ pasó, no en qué dirección: la dirección la pone el signo del importe.
 * «Cantidad: 4 → 2» con −30,00 € se lee sola; «Bajada de cantidad» al lado de −30,00 € lo diría dos
 * veces, y una subida y una bajada que se explican con la misma frase no pueden divergir.
 *
 * ▶ Desde la T3·4 (`DECISIONES #315`) la frase de fiesta MIXTA también vive aquí ({@see mixed}):
 * llegó de `OrderAdjustment::breakdownLabel()`, que murió con el modelo de dos ejes. Sus cuatro
 * claves `tickets.gate_mixed_party_*` son las que `ClientMoneyLabelsAreTranslatedTest` vigila.
 */
final class MovementLabel
{
    public static function booking(): string
    {
        return __('tickets.journal.booking');
    }

    /**
     * La etiqueta de una gestión, desde su `context` ESTRUCTURADO (las claves que
     * `OrderItemEditor` escribe desde `#150`): una parte por cada cosa que cambió, unidas por « · »
     * —fiel y sin netear: una gestión que cambió producto y cantidad lo dice entero—.
     *
     * ⚠️ El cambio de PRECIO solo habla cuando es lo único que cambió (la re-tarifa sola, `PAY-18`):
     * con un cambio de fecha o de producto delante, el precio nuevo es consecuencia y repetirlo no
     * explica nada. ⚠️ Y el respaldo —contexto vacío, las filas anteriores a `#150`— dice «Cambios
     * en X» y no inventa una cantidad que no consta (la lección de `#131`/`#150`).
     */
    public static function edit(OrderAdjustment $row, OrderItem $line, string $currency): string
    {
        $context = is_array($row->context) ? $row->context : [];
        $changes = is_array($context['changes'] ?? null) ? $context['changes'] : [];
        $name = (string) ($line->ticketType?->tr('name') ?? '—');
        $parts = [];

        $product = $changes['product_change'] ?? null;
        if (is_array($product) && isset($product['new'])) {
            $parts[] = __('tickets.journal.product_change', ['name' => (string) $product['new']]);
        }

        $quantity = $changes['quantity_change'] ?? null;
        if (is_array($quantity) && isset($quantity['old'], $quantity['new'])) {
            $args = ['old' => (int) $quantity['old'], 'new' => (int) $quantity['new']];
            // En un COMPLEMENTO la cantidad lleva su nombre («Menú por invitado: 4 → 2»): es la
            // re-escala per-invitado que acompaña a la bajada del principal («Cantidad: 4 → 2»), y sin
            // el nombre las dos líneas dirían lo mismo debajo de importes distintos.
            $parts[] = $line->parent_item_id !== null
                ? __('tickets.journal.addon_quantity', $args + ['name' => $name])
                : __('tickets.journal.quantity', $args);
        }

        $slot = $changes['slot_change'] ?? null;
        if (is_array($slot) && isset($slot['new'])) {
            $parts[] = __('tickets.journal.slot_change', ['when' => (string) $slot['new']]);
        }

        if ($parts === []) {
            $price = $changes['unit_price_change'] ?? null;
            if (is_array($price) && isset($price['old'], $price['new'])) {
                $parts[] = __('tickets.journal.price_change', [
                    'old' => Money::format((int) $price['old'], $currency),
                    'new' => Money::format((int) $price['new'], $currency),
                ]);
            }
        }

        if ($parts === []) {
            // Complementos añadidos o subidos («+3 Calcetines»): el contexto de `addon_edit` los
            // trae en la raíz; el de una re-escala, dentro de `changes` (ya resuelta arriba por su
            // `quantity_change`). Es la lectura que `OrderAdjustment::breakdownLabel()` hacía hasta la T3·4.
            $addon = $context['addon_change'] ?? $changes['addon_change'] ?? null;
            if (is_array($addon)) {
                foreach ($addon['added'] ?? [] as $added) {
                    $qty = (int) ($added['qty'] ?? 0);
                    if ($qty > 0) {
                        $parts[] = '+'.$qty.' '.(string) ($added['name'] ?? '—');
                    }
                }
                foreach ($addon['updated'] ?? [] as $updated) {
                    $delta = (int) ($updated['new'] ?? 0) - (int) ($updated['old'] ?? 0);
                    if ($delta > 0) {
                        $parts[] = '+'.$delta.' '.(string) ($updated['name'] ?? '—');
                    }
                }
            }
        }

        if ($parts === []) {
            $parts[] = __('tickets.journal.edit_fallback', ['product' => $name]);
        }

        return implode(' · ', $parts);
    }

    /** «Cancelado: Cumpleaños Jump · 8 invitados» — el nombre y la cantidad, con su sustantivo. */
    public static function cancel(OrderItem $line): string
    {
        return __('tickets.journal.cancel', [
            'name' => (string) ($line->ticketType?->tr('name') ?? '—'),
            'quantity' => $line->displayQuantityLabel(),
        ]);
    }

    /**
     * Las dos frases de fiesta MIXTA (`specs/cumple-mixto.md` §12 y §24.5): el SUPLEMENTO
     * («Suplemento por 3 invitados que corresponden a Jump») y su espejo, el DESCUENTO. Leen la
     * MARCA `mixed_party` del `context` del ajuste, con el nombre del pack destino tal y como se
     * GUARDÓ al comunicarlo (no se resuelve al leer: así la frase no cambia si el producto se
     * renombra después).
     *
     * ⚠️ `trans_choice` y no `__`: con un solo invitado la frase decía «Suplemento por 1 invitadoS».
     * Un desglose de dinero que no concuerda en número se lee como descuidado justo donde más
     * confianza hace falta. Los cargos escritos antes de que el contexto llevara el nombre caen a
     * la frase sin nombre, que sigue siendo cierta; el descuento con varios destinos también (el
     * destino viaja en `targets`, y con varios no se inventa un reparto).
     */
    public static function mixed(OrderAdjustment $row): string
    {
        $context = is_array($row->context) ? $row->context : [];
        $mixed = is_array($context['mixed_party'] ?? null) ? $context['mixed_party'] : [];
        $guests = (int) ($mixed['guests'] ?? 0);

        if (($mixed['credit'] ?? false) === true) {
            $targets = is_array($mixed['targets'] ?? null) ? $mixed['targets'] : [];
            $target = count($targets) === 1 ? ($targets[0]['name'] ?? null) : null;

            return $target !== null && $target !== ''
                ? trans_choice('tickets.gate_mixed_party_credit_line_named', $guests, ['count' => $guests, 'target' => (string) $target])
                : trans_choice('tickets.gate_mixed_party_credit_line', $guests, ['count' => $guests]);
        }

        $target = $mixed['target_name'] ?? null;

        return $target !== null && $target !== ''
            ? trans_choice('tickets.gate_mixed_party_line_named', $guests, ['count' => $guests, 'target' => (string) $target])
            : trans_choice('tickets.gate_mixed_party_line', $guests, ['count' => $guests]);
    }

    public static function courtesy(): string
    {
        return __('tickets.journal.courtesy');
    }

    /** @param  Settlement::METHOD_WEB|Settlement::METHOD_DESK  $method */
    public static function payment(string $method): string
    {
        return $method === Settlement::METHOD_DESK
            ? __('tickets.journal.paid_desk')
            : __('tickets.journal.paid_online');
    }

    /**
     * «Devuelto a la tarjeta» / «Devuelto en el parque (registrado)» para lo hecho; «Devolución en
     * curso» / «Devolución fallida» para lo que no ha vuelto todavía — en pasado solo lo que pasó.
     *
     * @param  Settlement::METHOD_CARD|Settlement::METHOD_MANUAL  $method
     */
    public static function refund(string $method, string $status): string
    {
        return match ($status) {
            Settlement::STATUS_PENDING => __('tickets.journal.refund_pending'),
            Settlement::STATUS_FAILED => __('tickets.journal.refund_failed'),
            default => $method === Settlement::METHOD_MANUAL
                ? __('tickets.journal.refund_manual')
                : __('tickets.journal.refund_card'),
        };
    }

    /** «Liquidado en el parque»: D9 de la T5 de mixtos — nadie registra el cobro, así que no dice «pagado». */
    public static function gate(): string
    {
        return __('tickets.journal.gate');
    }

    /** En un pedido con más de una reserva, cada línea de valor lleva delante la suya (spec §4.3). */
    public static function inReservation(string $reservation, string $label): string
    {
        return __('tickets.journal.with_reservation', ['reservation' => $reservation, 'label' => $label]);
    }
}
