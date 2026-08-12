<?php

namespace App\Domain\Booking\Contracts;

/**
 * Una reserva FUTURA del cliente, vista desde fuera de Booking (Fase 2, paso 1).
 *
 * Solo los tres datos que el consumidor real (`CustomerAccountContext`, de Identity)
 * pinta hoy: la fecha, la ventana horaria ya compuesta por Booking y el nombre del
 * producto traducido. La FORMATEA quien la muestra — el contrato no decide idioma ni
 * formato de fecha, por eso `date` viaja como `Y-m-d` y no como etiqueta.
 *
 * `date` en `Y-m-d` (naive, convención de fechas del dominio — ver `INVARIANTES` §2)
 * mantiene el DTO serializable tal cual para la API v1 de Fase 3.
 */
final readonly class UpcomingReservation
{
    public function __construct(
        /** Fecha de la franja en `Y-m-d`. */
        public string $date,
        /** Ventana horaria ya compuesta («10:00–12:00», «10:00 – sin límite») o null si la franja no tiene hora. */
        public ?string $timeWindow,
        /** Nombre del producto en el idioma activo (cadena vacía si el producto ya no existe). */
        public string $productName,
    ) {}
}
