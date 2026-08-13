<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Http\Api\ApiCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UpcomingReservationResource;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 1 — «mis reservas»: las próximas del cliente autenticado.
 *
 * El controlador **no consulta**: pide al contrato `Booking\Contracts\CustomerReservations`, que es
 * el mismo que usa el sidebar de la web desde Fase 2. Qué cuenta como «próxima» —ítem principal, de
 * pedido pagado, no cancelado, con franja y no terminado— es una regla de Booking y vive allí. Si
 * la API escribiera aquí ese filtro, tendríamos dos definiciones de «reserva próxima» destinadas a
 * divergir; es exactamente el error que §4.6 enumera para las otras cuatro extracciones.
 *
 * Solo lectura y scoping por el guard: el `userId` sale del usuario autenticado, nunca de la
 * petición, así que no hay superficie de IDOR.
 */
class MeReservationsController extends Controller
{
    public function index(Request $request, CustomerReservations $reservations): ApiCollection
    {
        return new ApiCollection(
            collect($reservations->upcomingFor((int) $request->user()->getAuthIdentifier())),
            UpcomingReservationResource::class,
        );
    }
}
