<?php

namespace App\Support;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Contexto de cuenta del cliente para la web pública (#221): saludo, próxima reserva, número de
 * reservas próximas y formularios de reserva (#217) pendientes. Lo consumen el icono de cuenta
 * del nav (puntito de aviso) y el bloque del sidebar de compra (avatar + sub-línea + contador).
 * Se registra como SINGLETON con memoización por usuario → una sola pasada aunque varias vistas
 * lo pidan en la misma petición.
 *
 * Módulo **Identity**. Los datos de reservas se piden al contrato
 * `App\Domain\Booking\Contracts\CustomerReservations` (Fase 2, paso 1): aquí solo queda lo de
 * Identity (el nombre) y la PRESENTACIÓN (etiqueta de fecha localizada, URL del formulario).
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
     * @return array{firstName: string, upcomingCount: int, nextReservation: ?array{dateLabel: string, timeWindow: ?string, productName: string}, pendingForms: list<array{productName: string, url: string}>, pendingFormsCount: int, hasPendingForm: bool}
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
            $context['nextReservation'] = $this->formatReservation($upcoming[0] ?? null);

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

    /**
     * @return ?array{dateLabel: string, timeWindow: ?string, productName: string}
     */
    private function formatReservation(?UpcomingReservation $next): ?array
    {
        if ($next === null) {
            return null;
        }

        return [
            'dateLabel' => Str::ucfirst(
                Carbon::parse($next->date)->locale(app()->getLocale())->isoFormat('ddd D MMM')
            ),
            'timeWindow' => $next->timeWindow,
            'productName' => $next->productName,
        ];
    }
}
