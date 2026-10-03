<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\CalendarFile;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * **«Añadir al calendario» de una RESERVA** (la R2 de `specs/correos-rediseno.md` §4.3): el `.ics` que enlaza el resguardo de
 * los correos de la reserva, con `CalendarFile` —el mismo de la invitación— y la misma ventana (`OrderItem::visitWindow()`:
 * la hora de PARED y la duración efectiva).
 *
 * ⚠️ Ruta FIRMADA y sin caducidad: el enlace viaja en el correo y se abre cuando se abre. Sin firma no se abre, y el fichero
 * no lleva nada de la persona —qué, cuándo y dónde—. Una reserva cancelada, o de un pedido que ya no vale, da el mismo 404.
 */
final class ReservationCalendarController
{
    public function __invoke(OrderItem $reserva): Response
    {
        $pedido = $reserva->order;
        abort_if(
            $reserva->parent_item_id !== null || $reserva->isCancelled() || $pedido === null
            || in_array($pedido->status, [Order::STATUS_PENDING, Order::STATUS_CANCELLED, Order::STATUS_EXPIRED, Order::STATUS_REFUNDED], true),
            404,
        );

        $ventana = $reserva->visitWindow();
        abort_if($ventana === null, 404);   // sin hora o sin duración no hay evento: «sin dato, sin bloque»
        [$inicio, $fin] = $ventana;

        $negocio = trim((string) Setting::businessName());
        $sitio = array_filter([
            $negocio,
            trim((string) Setting::value('address.line1')),
            trim((string) Setting::value('address.line2')),
        ], static fn (string $linea): bool => $linea !== '');

        $ics = CalendarFile::event(
            // ESTABLE: volver a descargarlo actualiza el evento en vez de duplicarlo.
            uid: 'reserva-'.$reserva->getKey().'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'jumpweb'),
            summary: (string) __('emails.calendar.summary', ['product' => (string) $reserva->ticketType?->tr('name'), 'park' => $negocio]),
            startsAt: $inicio,
            endsAt: $fin,
            timezone: DisplayTime::timezone(),
            location: implode(', ', $sitio),
        );

        return response($ics, 200, [
            'Content-Type' => CalendarFile::MIME,
            'Content-Disposition' => 'attachment; filename="reserva-'.Str::slug((string) $pedido->code).'.ics"',
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
