<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;

/**
 * EL RESGUARDO de un correo — las cuatro cosas que se buscan al abrirlo (`DECISIONES #503`;
 * artboard `Correos PJP` 1a): **cuándo, qué, dónde y con qué código**.
 *
 * Hasta hoy esos cuatro datos estaban REPARTIDOS: el día y la hora dentro de la tarjeta de
 * producto, el código dentro de una frase del cuerpo, y **la dirección del parque no aparecía en
 * ninguno de los 23 correos** (medido). Aquí suben juntos y arriba del todo, que es donde se miran.
 *
 * ▶ **Compositor ÚNICO, y por eso vive aquí y no en cada notificación**: el mismo resguardo lo
 * usan la confirmación, el formulario de invitados, la autorización de menores y el pago denegado.
 * Sus rótulos viven una sola vez en los `emails.php` de cada idioma, bajo la clave `slip`.
 * ⚠️ Y esa frase no se escribe con la ruta comodín: la barra y el asterisco CIERRAN este bloque
 *    de comentario y el fichero deja de compilar con un «unexpected token» que señala aquí.
 *
 * ⚠️ **Cada fila se pinta solo si su dato existe.** Una reserva sin franja no imprime un «Cuándo»
 * vacío, y una instalación sin dirección no imprime un «Dónde» a medias: el resguardo encoge.
 *
 * ⚠️ **El DÓNDE no va en todos.** Solo tiene sentido donde el correo habla de una VISITA — la
 * confirmación—; en el formulario de invitados o en un pago denegado, la dirección es ruido. Lo
 * decide quien llama, con `$conLugar`.
 */
class EmailSlip
{
    /** El resguardo de un PEDIDO: se compone sobre su primera reserva viva. */
    public static function forOrder(Order $order, bool $conLugar = false): array
    {
        $order->loadMissing(['items.ticketType', 'items.slot']);

        $item = $order->items
            ->whereNull('parent_item_id')
            ->reject(fn (OrderItem $i): bool => $i->isCancelled())
            ->first();

        return self::compose($item, (string) $order->code, $conLugar);
    }

    /** El resguardo de UNA reserva. */
    public static function forItem(OrderItem $item, bool $conLugar = false): array
    {
        $item->loadMissing(['ticketType', 'slot', 'order']);

        return self::compose($item, (string) ($item->order?->code ?? ''), $conLugar);
    }

    /** @return array<string,string> rótulo → valor, ya traducidos y en orden */
    private static function compose(?OrderItem $item, string $code, bool $conLugar): array
    {
        $filas = [];

        if ($item !== null && $item->slot && $item->slot->date) {
            $dia = DisplayTime::dayLabel($item->slot->date);
            $ventana = $item->displayTimeWindow();
            $filas[(string) __('emails.slip.when')] = $ventana ? $dia.' · '.$ventana : $dia;
        }

        if ($item !== null) {
            $filas[(string) __('emails.slip.what')] = self::what($item);
        }

        if ($conLugar && ($lugar = self::place()) !== null) {
            $filas[(string) __('emails.slip.where')] = $lugar;
        }

        if ($code !== '') {
            $filas[(string) __('emails.slip.order')] = $code;
        }

        return $filas;
    }

    /**
     * ⚠️ El nombre lo compone `displayProductName()`, que es el sitio ÚNICO donde vive la etiqueta
     * «MIXTA» (`specs/cumple-mixto.md` §13). Aquí no se re-compone: se lee.
     */
    private static function what(OrderItem $item): string
    {
        $nombre = $item->displayProductName();
        $qty = (int) $item->quantity;

        if ($item->ticketType?->isPack()) {
            return $nombre.' · '.__('tickets.guests_count', ['count' => $qty]);
        }

        return $qty > 1 ? $qty.'× '.$nombre : $nombre;
    }

    /** La dirección del parque, del PANEL. `null` si la instalación no la ha puesto. */
    private static function place(): ?string
    {
        $partes = array_filter([
            trim((string) Setting::value('address.line1', '')),
            trim((string) Setting::value('address.line2', '')),
        ], static fn (string $t): bool => $t !== '');

        return $partes === [] ? null : implode(', ', $partes);
    }
}
