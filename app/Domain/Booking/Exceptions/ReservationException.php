<?php

namespace App\Domain\Booking\Exceptions;

use RuntimeException;

/**
 * La reserva no se pudo crear (cesta vacía, franja cerrada/agotada, fuera de la ventana
 * del producto, fecha pasada…). El mensaje es una CLAVE de traducción (`tickets.errors.*`)
 * que el componente de compra resuelve y muestra al usuario.
 *
 * Puede llevar `context` (auditoría 2026-05-26 2ª ronda, P-13/P-14) con datos para sustituir
 * en el mensaje traducido (`:product`, `:when`, ...) y dar al usuario una pista concreta sobre
 * qué línea de la cesta tiene el problema, en lugar de un genérico "no disponible".
 */
class ReservationException extends RuntimeException
{
    /** @var array<string,mixed> */
    public array $context = [];

    /**
     * @param  array<string,mixed>  $context
     */
    public static function withContext(string $messageKey, array $context = []): self
    {
        $e = new self($messageKey);
        $e->context = $context;

        return $e;
    }
}
