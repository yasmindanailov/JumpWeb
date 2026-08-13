<?php

namespace App\Http\Api;

use App\Domain\Booking\Contracts\AdmissionDecision;

/**
 * Fase 4 · paso 4.0b — el veredicto de admisión del dominio ↔ el código público de la API.
 *
 * Es la misma indirección que `ReservationErrorMap` hace con `ReservationException`, y por el mismo
 * motivo: **el contrato público no puede ser una constante interna**. Y aquí no es teórico —
 * `AdmissionDecision::TOO_MANY_PENDING` vale `'too_many_pending'` mientras el código que ven los
 * clientes es `'too_many_pending_orders'`. Los dos nombres existen a propósito y hay que traducir.
 *
 * Nace al añadir `GET me/reservation-eligibility`, que es el **segundo** consumidor: el `match`
 * vivía privado en `Api\V1\OrdersController`, y duplicarlo habría dejado dos sitios donde decidir
 * qué se le dice al cliente sobre lo mismo.
 *
 * ⚠️ **Sin `default`, a propósito.** Un motivo nuevo en `AdmissionDecision` hace que esto lance en
 * vez de degradar a «demasiadas peticiones», que es lo que hacía el `match` original y lo que
 * convertiría un motivo sin traducir en un 429 mentiroso. `AdmissionCodeMapTest` lo comprueba
 * leyendo las constantes del dominio: si añades una y no la mapeas, el test la nombra.
 */
final class AdmissionCodeMap
{
    /** El código público estable de un veredicto denegado. */
    public static function codeFor(string $reason): ApiErrorCode
    {
        return match ($reason) {
            AdmissionDecision::RESERVATIONS_PAUSED => ApiErrorCode::ReservationsPaused,
            AdmissionDecision::TOO_MANY_PENDING => ApiErrorCode::TooManyPendingOrders,
            AdmissionDecision::RATE_LIMITED => ApiErrorCode::TooManyRequests,
        };
    }

    /**
     * El estado HTTP con el que se rechaza una CREACIÓN por ese motivo.
     *
     * **409 y no 422**: la petición está bien formada y lo que lo impide es el estado del sistema o
     * del titular, no los datos enviados. La excepción es el límite de frecuencia, que sí es un
     * **429** — es lo único de los tres que se arregla esperando, que es exactamente lo que ese
     * código significa.
     *
     * No lo usa el endpoint de elegibilidad: allí un «no puedes» es una consulta que salió BIEN y
     * responde 200 con el motivo dentro.
     */
    public static function statusFor(string $reason): int
    {
        return $reason === AdmissionDecision::RATE_LIMITED ? 429 : 409;
    }
}
