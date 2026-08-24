<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\ReservationScope;
use App\Http\Api\ApiCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReservationCardResource;
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
    /**
     * Tamaño de página del historial por reserva.
     *
     * **5 por decisión de producto** (owner, 2026-08-23): es lo que cabe sin scroll infinito en un
     * panel estrecho. El techo evita que `?per_page=100000` convierta el endpoint en una descarga
     * completa, igual que en `MeOrdersController`.
     */
    private const PER_PAGE_DEFAULT = 5;

    private const PER_PAGE_MAX = 50;

    public function index(Request $request, CustomerReservations $reservations): ApiCollection
    {
        return new ApiCollection(
            collect($reservations->upcomingFor((int) $request->user()->getAuthIdentifier())),
            UpcomingReservationResource::class,
        );
    }

    /**
     * **Una página del historial de reservas, por ÁMBITO** (`specs/mis-reservas-por-reserva.md` §4.2).
     *
     * ⚠️⚠️ **Dos URLs, un solo manejador y un solo predicado.** `/upcoming` y `/past` son honestas —
     * cada pantalla del cajón pide la suya— pero el reparto NO se decide aquí: lo decide
     * `CustomerReservations::pageFor()` con `where`/`whereNot` sobre la misma expresión. Si este
     * controlador tradujera cada ruta a su propio filtro, volverían a ser dos consultas que se
     * complementan de casualidad, y una reserva podría no salir en ninguna de las dos (§3.4).
     *
     * ⚠️ **El ámbito llega como enum de RUTA, no como parámetro libre.** Un `?scope=` cualquiera
     * obligaría a validar y a decidir qué hacer con un valor desconocido; con el binding del enum,
     * `/me/reservations/loquesea` es un 404 antes de tocar la BD.
     *
     * ⚠️ **`no-store` no se pone aquí**: lo aplica por defecto el middleware de `/api/v1` a toda
     * respuesta autenticada (`RGPD-04`). Hay caso que lo asevera, porque «viene por defecto» es
     * justo el tipo de afirmación que deja de ser cierta sin que nadie lo note.
     */
    public function page(Request $request, ReservationScope $scope, CustomerReservations $reservations): ApiCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::PER_PAGE_MAX],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return new ApiCollection(
            $reservations->pageFor(
                (int) $request->user()->getAuthIdentifier(),
                $scope,
                (int) $request->integer('per_page', self::PER_PAGE_DEFAULT),
                (int) $request->integer('page', 1),
            ),
            ReservationCardResource::class,
        );
    }
}
