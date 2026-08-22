<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Content\Services\ThemeSettings;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\HtmlString;

/**
 * Render de la SUBCARD de producto para los correos transaccionales (mejora visual #251).
 *
 * Centraliza el HTML de la tarjeta (vista `emails.partials.product-card`, maquetada con tablas
 * + estilos inline = email-safe) para que cada notificación la inyecte con UNA sola línea —
 * `->line(EmailProductCard::forItem($item))` — SIN reescribir el correo ni tocar su lógica de
 * textos condicionales (señal/total, mención del formulario, tipos de cambio, etc.). Devuelve un
 * {@see HtmlString}: el `e()` de Laravel no escapa los `Htmlable`, así que la tabla llega cruda
 * al slot, y el parser Markdown la deja pasar como bloque HTML (verificado empíricamente).
 *
 * Defensivo por diseño: una cortesía visual NUNCA debe tumbar el envío de un correo → ante
 * cualquier fallo devuelve una cadena vacía (el correo sale igual, solo sin la tarjeta).
 *
 * Icono: emoji dentro de un badge de color (NO SVG — Gmail/Outlook los eliminan). 🎂 para packs
 * de cumpleaños, 🎟️ para entradas. El color del badge/borde superior es el de la ZONA (data-driven).
 */
final class EmailProductCard
{
    /**
     * Tarjetas de las reservas principales de un pedido.
     *
     * - `$includeCancelled = false` (defecto, p. ej. CONFIRMACIÓN): solo las reservas ACTIVAS.
     * - `$includeCancelled = true` (p. ej. REEMBOLSO/CANCELACIÓN del pedido): TAMBIÉN las canceladas
     *   (marcadas «Cancelado»), porque el reembolso del pedido cubre productos que pudieron cancelarse
     *   antes sin devolver — omitirlos hacía que el correo «devolución del total» mostrara de menos.
     */
    public static function forOrder(Order $order, bool $withPrice = true, bool $includeCancelled = false): HtmlString
    {
        try {
            $order->loadMissing(['items.ticketType.zone', 'items.slot', 'items.children.ticketType']);

            $items = $order->items->whereNull('parent_item_id');
            if (! $includeCancelled) {
                $items = $items->reject(fn (OrderItem $i): bool => $i->isCancelled());
            }

            $html = $items->reduce(fn (string $acc, OrderItem $item): string => $acc.self::renderItem($item, $withPrice), '');

            return new HtmlString(self::collapse($html));
        } catch (\Throwable $e) {
            report($e);

            return new HtmlString('');
        }
    }

    /** Tarjeta de UNA reserva concreta (p. ej. formulario de invitados, item modificado/reembolsado). */
    public static function forItem(OrderItem $item, bool $withPrice = false): HtmlString
    {
        try {
            $item->loadMissing(['ticketType.zone', 'slot', 'children.ticketType']);

            return new HtmlString(self::collapse(self::renderItem($item, $withPrice)));
        } catch (\Throwable $e) {
            report($e);

            return new HtmlString('');
        }
    }

    private static function renderItem(OrderItem $item, bool $withPrice): string
    {
        $type = $item->ticketType;
        $isPack = $type?->isPack() ?? false;
        $name = (string) ($type?->tr('name') ?? '');
        $qty = (int) $item->quantity;

        // Entrada → «3× Entrada 1h» (cantidad delante, como en «Mis pedidos»); pack → solo el nombre
        // (el nº de invitados va en la sub-línea).
        $title = $isPack ? $name : ($qty > 1 ? $qty.'× '.$name : $name);

        $meta = null;
        if ($item->slot && $item->slot->date) {
            $date = DisplayTime::dayLabel($item->slot->date);
            $window = $item->displayTimeWindow();
            $meta = $window ? $date.' · '.$window : $date;
        }

        $sub = $isPack ? __('tickets.guests_count', ['count' => $qty]) : null;

        $addons = $item->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->map(function (OrderItem $c): string {
                $n = (string) ($c->ticketType?->tr('name') ?? '');

                return (int) $c->quantity > 1 ? $n.' ×'.(int) $c->quantity : $n;
            })->values()->all();

        return view('emails.partials.product-card', [
            'emoji' => $isPack ? '🎂' : '🎟️',
            'color' => $type?->zone?->color ?: ThemeSettings::brand(),
            'title' => $title,
            'meta' => $meta,
            'sub' => $sub,
            'addons' => $addons,
            'price' => $withPrice ? number_format($item->chargedSubtotalCents() / 100, 2, ',', '.').' €' : null,
            'cancelled' => $item->isCancelled(),
        ])->render();
    }

    /** Quita SOLO el espacio en blanco entre etiquetas (no toca el texto) → una línea, sin líneas en
     *  blanco que el parser Markdown interpretaría como fin del bloque HTML. */
    private static function collapse(string $html): string
    {
        return trim((string) preg_replace('/>\s+</', '><', $html));
    }
}
