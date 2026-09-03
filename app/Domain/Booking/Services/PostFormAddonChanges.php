<?php

namespace App\Domain\Booking\Services;

/**
 * Lo que UNA pasada de {@see PostFormAddons::reconcile()} movió, y lo que NO pudo mover.
 *
 * Existe porque el reconciliador tiene dos salidas y las dos importan: lo aplicado alimenta el
 * correo y el rastro, y **lo bloqueado hay que decírselo al cliente**. Callar un extra que no se
 * pudo tocar sería el modo de fallo de esta feature: la persona cree que ha pedido las tapas y se
 * entera en la fiesta.
 */
final readonly class PostFormAddonChanges
{
    /**
     * @param  list<array{addon_id:int, name:string, from:int, to:int}>  $moves  altas, subidas, bajadas y retiradas (`to = 0`)
     * @param  list<array{addon_id:int, reason:string}>  $blocked  lo que se pidió y no se pudo mover, con su motivo
     * @param  int  $deltaCents  lo que el pedido sube (o baja) por esta pasada, con signo
     */
    public function __construct(
        public array $moves = [],
        public array $blocked = [],
        public int $deltaCents = 0,
    ) {}

    /** ¿Se movió algo de verdad? (R4: un guardado idéntico no escribe, no audita y no notifica.) */
    public function changed(): bool
    {
        return $this->moves !== [];
    }
}
