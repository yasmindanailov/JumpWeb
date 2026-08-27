<?php

namespace App\Domain\Booking\Contracts;

/**
 * Fase 6 · subsistema A — UNA reserva vista desde la PUERTA (`docs/specs/identidad-qr-puerta.md` §4.6,
 * §4.7, §9.2 A·3): lo que el empleado necesita para resolver «¿qué le entrego?» y «¿cuánto le cobro?».
 *
 * A diferencia de `UpcomingReservation` (tres datos para el saludo del cajón), aquí viajan la cantidad,
 * los complementos, el ítem y el pedido —para cruzar con Identity los menores asignados a ESA línea— y
 * el DINERO, que sale de `Booking\Services\OrderLedger::forReservation()` y nunca se recompone (§4.7:
 * la puerta pasa a ser una superficie más del ledger). Fechas `Y-m-d` e importes en céntimos: quien
 * pinta formatea.
 */
final readonly class GateReservation
{
    /**
     * @param  list<string>  $addons  «2 × Calcetines», ya rotulados por Booking
     */
    public function __construct(
        public int $orderId,
        public string $orderCode,
        public int $orderItemId,
        /** Fecha de la franja en `Y-m-d`. */
        public string $date,
        /** Ventana horaria ya compuesta («10:00–11:00») o `null` si la franja no tiene hora. */
        public ?string $timeWindow,
        public string $productName,
        public bool $isEntry,
        public int $quantity,
        public array $addons,
        /** Pagado por web que respalda ESTA reserva (`OrderLedger::pagadoOnline`). */
        public int $paidOnlineCents,
        /** Pendiente de cobrar EN PUERTA por esta reserva (`OrderLedger::pendientePuerta`). */
        public int $pendingGateCents,
        /** `cash` · `datafono` · `redsys`… lo que el pedido diga; `null` sin cobro. */
        public ?string $chargeMethod,
        /** ISO-8601 o `null`. */
        public ?string $paidAt,
        public string $createdAt,
    ) {}
}
