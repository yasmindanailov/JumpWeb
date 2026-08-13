<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Services\MaintenanceSettings;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Implementa {@see ReservationAdmission}: la política que decide si una intención de reservar —o de
 * volver a pagar— se admite.
 *
 * El código venía de `Livewire\Tickets\Purchase::blockedByReservationPause()` y
 * `::withinReservationLimits()`, con los mismos valores y el mismo orden de comprobación. Lo que
 * cambia al traerlo aquí no es la regla, es **quién la conoce**: hasta ahora solo la aplicaba el
 * sidebar, y el reintento desde «Mis pedidos» aplicaba otra distinta sin que nadie lo hubiera
 * decidido.
 *
 * **Orden de comprobación, y por qué importa**: pausa → tope → frecuencia. La pausa va primero
 * porque no es culpa del cliente y su aviso es accionable («llámanos»); gastarle una ficha del
 * limitador para luego decirle que no se puede reservar sería castigarle por un interruptor del
 * panel. El limitador va el último porque es el único con efecto: una comprobación que consume
 * debe correr solo cuando todo lo demás ya dijo que sí.
 */
class ReservationAdmissionPolicy implements ReservationAdmission
{
    /**
     * Pedidos `pending` VIVOS por titular a la vez (auditoría 2026-05-26, hallazgo E).
     *
     * Es un tope de aforo retenido sin pagar, no de compras: los pedidos cuya ventana ya venció no
     * cuentan, porque su plaza ya está de vuelta en el inventario.
     */
    public const MAX_PENDING_PER_USER = 5;

    /** Reservas CREADAS por minuto y titular. */
    public const RESERVATIONS_PER_MINUTE = 3;

    /** Ventana del limitador, en segundos. */
    private const RATE_WINDOW = 60;

    public function mayReserve(int $userId): AdmissionDecision
    {
        return $this->evaluate($userId, consume: false);
    }

    public function admitReservation(int $userId): AdmissionDecision
    {
        return $this->evaluate($userId, consume: true);
    }

    public function admitPaymentRetry(int $userId, string $orderCode): RetryAdmission
    {
        if (MaintenanceSettings::reservationsPaused()) {
            return RetryAdmission::deny(RetryAdmission::RESERVATIONS_PAUSED);
        }

        if (RateLimiter::tooManyAttempts($this->rateKey($userId), self::RESERVATIONS_PER_MINUTE)) {
            return RetryAdmission::deny(RetryAdmission::RATE_LIMITED);
        }

        $order = $this->extendHold($userId, $orderCode);

        if ($order === null) {
            return RetryAdmission::deny(RetryAdmission::NOT_RETRYABLE);
        }

        RateLimiter::hit($this->rateKey($userId), self::RATE_WINDOW);

        return RetryAdmission::allow($order);
    }

    /**
     * Las tres reglas en su orden. `$consume` decide si el limitador solo se MIRA o además se
     * incrementa; el resto de comprobaciones es idéntico, para que mirar y admitir no puedan dar
     * respuestas distintas.
     */
    private function evaluate(int $userId, bool $consume): AdmissionDecision
    {
        if (MaintenanceSettings::reservationsPaused()) {
            return AdmissionDecision::deny(AdmissionDecision::RESERVATIONS_PAUSED);
        }

        if ($this->livePendingCount($userId) >= self::MAX_PENDING_PER_USER) {
            return AdmissionDecision::deny(
                AdmissionDecision::TOO_MANY_PENDING,
                ['max' => self::MAX_PENDING_PER_USER],
            );
        }

        if (RateLimiter::tooManyAttempts($this->rateKey($userId), self::RESERVATIONS_PER_MINUTE)) {
            return AdmissionDecision::deny(AdmissionDecision::RATE_LIMITED);
        }

        if ($consume) {
            RateLimiter::hit($this->rateKey($userId), self::RATE_WINDOW);
        }

        return AdmissionDecision::allow();
    }

    /**
     * Pedidos pendientes con la retención AÚN VIVA. `expires_at` nulo cuenta como vivo: es un hold
     * sin caducidad (pedido firme del panel), no un hold vencido.
     */
    private function livePendingCount(int $userId): int
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', Order::STATUS_PENDING)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
    }

    /**
     * Extiende la ventana de retención del pedido y lo devuelve, o `null` si no había ninguno
     * reintentable para este titular.
     *
     * ⚠️ **El check y la extensión son UNA sentencia** (`PAY-04`, hallazgo L2 del origen). Separarlos
     * abre una carrera con `orders:expire`: entre leer «sigue viva» y escribir la nueva ventana, el
     * barrido puede haberla caducado y liberado la plaza a otro cliente; la extensión resucitaría
     * entonces un hold ya cedido **sin recontar aforo**, y el cobro reabierto acabaría en
     * sobreventa. El `WHERE` lleva el filtro por titular, así que también es la defensa anti-IDOR:
     * no hay forma de extender el hold de un pedido ajeno ni siquiera acertando su código.
     *
     * El `SELECT` posterior es seguro: solo se ejecuta si el `UPDATE` afectó a una fila, es decir,
     * si ese pedido acaba de quedar retenido por esta misma sentencia.
     */
    private function extendHold(int $userId, string $orderCode): ?Order
    {
        $extended = Order::query()
            ->where('user_id', $userId)
            ->where('code', $orderCode)
            ->where('status', Order::STATUS_PENDING)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['expires_at' => now()->addMinutes(PaymentSettings::holdMinutes())]);

        if ($extended === 0) {
            return null;
        }

        return Order::query()
            ->where('user_id', $userId)
            ->where('code', $orderCode)
            ->first();
    }

    /** Clave del limitador. Se conserva la del sidebar para no reiniciar los cubos en el despliegue. */
    private function rateKey(int $userId): string
    {
        return 'reservation-confirm:'.$userId;
    }
}
