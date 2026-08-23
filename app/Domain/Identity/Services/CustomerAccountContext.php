<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Identity\Models\User;

/**
 * Contexto de cuenta del cliente para la web pública (#221): saludo, próxima reserva, número de
 * reservas próximas y formularios de reserva (#217) pendientes. Lo consumen el icono de cuenta
 * del nav (puntito de aviso) y el bloque del sidebar de compra (avatar + sub-línea + contador).
 * Se registra como SINGLETON con memoización por usuario → una sola pasada aunque varias vistas
 * lo pidan en la misma petición.
 *
 * Módulo **Identity**. Los datos de reservas se piden al contrato
 * `App\Domain\Booking\Contracts\CustomerReservations` (Fase 2, paso 1): aquí solo queda lo de
 * Identity (el nombre) y **la única presentación que sigue siendo suya**: la URL del formulario de
 * invitados, que se compone con `route()` y por tanto es del servidor.
 *
 * ⚠️ **La etiqueta de fecha YA NO se compone aquí** (2026-08-23): la próxima reserva sale como el DTO
 * del contrato, sin formatear. El porqué, en el docblock de `for()`.
 *
 * Defensivo por diseño (convención de helpers del panel): ante cualquier fallo devuelve un
 * contexto vacío seguro. Una cortesía de UI nunca debe tumbar una página.
 */
class CustomerAccountContext
{
    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    public function __construct(private readonly CustomerReservations $reservations) {}

    /**
     * ⚠️ **`nextReservation` viaja como el DTO del contrato, SIN formatear** (2026-08-23). Antes se
     * devolvía un array con la etiqueta de día ya compuesta, y eso era un atajo que contradecía al
     * propio contrato: el docblock de `UpcomingReservation` dice que **«la FORMATEA quien la
     * muestra — el contrato no decide idioma ni formato de fecha»**, y por eso `date` viaja en
     * `Y-m-d`. Formatear aquí obligaba además a que `Http\Resources\Api\V1\AccountContextResource`
     * recompusiera la forma de `UpcomingReservationResource` en un segundo sitio, que es como dos
     * formas del mismo dato acaban divergiendo (`specs/account-context-vue.md` §4.4).
     *
     * ▶ Quien pinta la etiqueta llama a `Platform\Services\DisplayTime::dayLabel()`, que es su fuente
     * única y lo vigila `DayLabelSingleSourceTest`.
     *
     * @return array{firstName: string, upcomingCount: int, nextReservation: ?UpcomingReservation, pendingForms: list<array{productName: string, url: string}>, pendingFormsCount: int, hasPendingForm: bool}
     */
    public function for(User $user): array
    {
        return $this->cache[$user->id] ??= $this->build($user);
    }

    /** @return array<string, mixed> */
    private function build(User $user): array
    {
        $context = [
            'firstName' => $user->firstName(),
            'upcomingCount' => 0,
            'nextReservation' => null,
            'pendingForms' => [],
            'pendingFormsCount' => 0,
            'hasPendingForm' => false,
        ];

        try {
            $upcoming = $this->reservations->upcomingFor((int) $user->id);
            $context['upcomingCount'] = count($upcoming);
            $context['nextReservation'] = $upcoming[0] ?? null;

            $pending = array_map(
                fn (PendingGuestForm $form): array => [
                    'productName' => $form->productName,
                    'url' => route('reservation.guests', $form->reservationId),
                ],
                $this->reservations->pendingGuestFormsFor((int) $user->id),
            );
            $context['pendingForms'] = $pending;
            $context['pendingFormsCount'] = count($pending);
            $context['hasPendingForm'] = $pending !== [];
        } catch (\Throwable $e) {
            // Cortesía de UI: nunca rompemos la página por el contexto de cuenta.
            report($e);
        }

        return $context;
    }
}
