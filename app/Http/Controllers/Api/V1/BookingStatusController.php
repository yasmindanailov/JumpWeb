<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BookingStatusResource;

/**
 * Fase 4 · paso 4.0b — `GET /api/v1/booking/status`: ¿se puede reservar online ahora mismo?
 *
 * Hasta este paso, la pausa de reservas (#218) solo existía por API como el **código de error de un
 * 409**, es decir: el cliente se enteraba **después** de intentar crear el pedido. En la web pasa lo
 * contrario desde siempre — el cajón sustituye el flujo entero por un aviso con el teléfono del
 * negocio—, así que sin esto la SPA habría perdido una conducta que hoy existe.
 *
 * **Público**: el aviso hay que enseñarlo a quien abre el cajón, tenga cuenta o no. Al ser ruta
 * pública, el `throttle:api` del grupo sí la cuenta.
 *
 * **Es ESTADO, no configuración**, y de ahí sale su forma: se relee. Un cliente lo pide al abrir el
 * cajón y otra vez cuando un 409 le dice `reservations_paused`, porque entre las dos cosas la dueña
 * puede haber accionado el interruptor. Por eso no viaja con `GET /config`, que es estático.
 */
class BookingStatusController extends Controller
{
    public function __invoke(): BookingStatusResource
    {
        return new BookingStatusResource;
    }
}
