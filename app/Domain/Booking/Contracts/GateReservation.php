<?php

namespace App\Domain\Booking\Contracts;

/**
 * Fase 6 · subsistema A — UNA reserva vista desde la PUERTA (`docs/specs/identidad-qr-puerta.md` §4.6,
 * §4.7, §9.2 A·3): lo que el empleado necesita para resolver «¿qué le entrego?» y «¿cuánto le cobro?».
 *
 * A diferencia de `UpcomingReservation` (tres datos para el saludo del cajón), aquí viajan la cantidad,
 * los complementos, el ítem y el pedido —para cruzar con Identity los menores asignados a ESA línea— y
 * el DINERO, que sale del LIBRO de la reserva (`Booking\Services\OrderBook::forReservation()`,
 * `DECISIONES #305`; T3·2) y nunca se recompone (§4.7: la puerta es una superficie más del libro):
 * lo pagado y el SALDO con su clase. Fechas `Y-m-d` e importes en céntimos: quien pinta formatea.
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
        /** Lo PAGADO de esta reserva según su libro (`OrderBook::paidCents`: cobrado − devuelto + liquidado). */
        public int $paidCents,
        /** La CLASE del saldo de la reserva (`Balance::KIND_*`): la puerta pinta por clase, nunca por signo. */
        public string $balanceKind,
        /** El saldo CON SIGNO (`Balance::cents`): positivo se cobra, negativo se devuelve, 0 nada pendiente. */
        public int $balanceCents,
        /** `cash` · `datafono` · `redsys`… lo que el pedido diga; `null` sin cobro. */
        public ?string $chargeMethod,
        /** ISO-8601 o `null`. */
        public ?string $paidAt,
        public string $createdAt,
        /**
         * Lo ESCRITO del suplemento de fiesta MIXTA (`specs/cumple-mixto.md` §23.2): una línea por
         * pack de destino, con el nombre GUARDADO al comunicarlo y la diferencia por invitado.
         * Se enseña lo escrito y no el veredicto porque es lo que se cobra (`PAY-19`); sale de
         * `MixedPartySurcharge::written()` sobre relaciones ya cargadas — coste cero en consultas.
         *
         * @var list<array{name:string, count:int, unit_cents:int}>
         */
        public array $mixedPartyLines = [],
        /**
         * El NETO escrito de fiesta mixta (céntimos, CON SIGNO desde la T4: cargo − descuento).
         * `0` sin nada escrito. Ya incluido en `pendingGateCents`.
         */
        public int $mixedPartySurchargeCents = 0,
        /**
         * El DESCUENTO escrito (T4, §24.5): su frase —compuesta por el dominio, la misma en todas
         * las superficies— y su importe en positivo. `null` sin descuento.
         *
         * @var array{label:string, cents:int}|null
         */
        public ?array $mixedPartyCredit = null,
        /**
         * **Las fichas CON NOMBRE del formulario de invitados** (T6·4, §4.8), ya normalizadas:
         * `{name, key}`. Vacío si el producto no ofrece la invitación digital.
         *
         * ⚠️⚠️ **Viajan aquí y no en una lectura propia porque salen GRATIS**: este contrato ya carga
         * la línea y su producto, así que componerlas cuesta cero consultas — y la puerta tiene un
         * presupuesto medido que no admite una lectura por fiesta (§7.2·R16).
         *
         * @var list<array{name: string, key: string}>
         */
        public array $partyGuests = [],
        /**
         * ¿El producto de esta reserva ofrece la invitación digital?
         *
         * ⚠️ Es lo que permite a la puerta **no preguntar** por las respuestas cuando no hay ninguna
         * fiesta con invitación entre las de hoy: sin esta bandera, el presupuesto pagaría una
         * consulta en cada escaneo de un cliente normal.
         */
        public bool $invitationOffered = false,
        /**
         * ¿El producto OFRECE justificante de menor invitado (su modo no es `none`)?
         *
         * ⚠️ Lo pide §4.5·10: los tres estados de puerta **solo existen** con el justificante en
         * juego. Sin él, la puerta enseña quién viene y no pinta ningún estado — un punto que no
         * puede cambiar nada sería ruido en la pantalla que más se mira.
         */
        public bool $waiverOffered = false,
    ) {}
}
