<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;

/**
 * **EL DESGLOSE, compuesto una sola vez** (`DECISIONES #127`, `specs/desglose-dinero-cliente.md` §10).
 *
 * Ocho superficies enseñan este dinero —el bloque de totales del panel, la sub-card por reserva, el
 * modal del calendario, la columna de la lista, la taquilla, la hoja PDF, los correos y el cliente— y
 * hasta ahora **cada una lo componía por su cuenta**. De ahí salían las divergencias que la tercera
 * auditoría midió: 18 de 23 gestiones del panel dejaban la columna del cliente ilegible.
 *
 * Esta clase es la composición ÚNICA. Las superficies **pintan**; no derivan, no deciden, no rotulan
 * un importe con otro nombre.
 *
 * ## DOS EJES, y no se mezclan
 *
 *     EJE VALOR   valor = pagadoOnline + pendienteOnline + pagadoPuerta + pendientePuerta + compensado
 *     EJE CAJA    retenido (= cobradoOnline − devuelto) = pagadoOnline + pendienteDevolucion
 *
 * Los dos cierran **por construcción** y los vigilan `PAY-16` y `PAY-17`. Que sean DOS es la mitad
 * del asunto: hasta ahora «Devuelto» y «Pendiente de devolución» se pintaban como restas dentro de
 * la columna del valor, de la que **no restan** — y por eso la columna dejaba de leerse.
 *
 * ⚠️ **`facturado` NO es un canal.** Es `Order.total`, lo que se facturó al reservar: pura
 * trazabilidad, y va fuera de la suma. Pintarlo como primera línea de la columna —lo que hacía el
 * «Subtotal»— apila dos bases distintas sin decirlo y es lo que hacía el bloque ilegible tras una
 * cancelación.
 */
final readonly class OrderLedger
{
    public function __construct(
        // ── EJE VALOR ──────────────────────────────────────────────────────────────
        /** Lo que vale hoy lo que sigue vivo. Es la suma de los cinco canales de abajo. */
        public int $valor,
        /** Canal WEB cobrado, neto de compensación. `0` mientras el pedido no se haya cobrado. */
        public int $pagadoOnline,
        /** Canal WEB pendiente. `0` en cuanto el pedido se cobra. Excluyente con el anterior. */
        public int $pendienteOnline,
        /** Canal PUERTA ya cobrado (reserva disfrutada de un pedido cobrado). */
        public int $pagadoPuerta,
        /** Canal PUERTA pendiente de cobrar. */
        public int $pendientePuerta,
        /** Dinero devuelto SIN que desapareciera producto. Ni canal de cobro ni bajada de valor. */
        public int $compensado,

        // ── EJE CAJA ───────────────────────────────────────────────────────────────
        /** Lo REALMENTE cobrado por web. El ancla: es lo que el cliente puede cotejar con su banco. */
        public int $cobradoOnline,
        /** Lo ya devuelto. */
        public int $devuelto,
        /** Lo que el parque retiene: `cobradoOnline − devuelto`. */
        public int $retenido,
        /** Lo retenido que ya no respalda producto y hay que devolver. */
        public int $pendienteDevolucion,
        /**
         * **CÓMO se cobró**: `'web'` (pasarela), `'desk'` (taquilla) o `null` si no se ha cobrado.
         *
         * ⚠️ Llega como CADENA desde `Order::chargeMethod()` —la frontera de módulos prohíbe que
         * `Booking\Services` nombre `Payments\Models\Payment`—, igual que la intención del reembolso.
         * Es un ENUM y no un rótulo a propósito: el panel habla en tercera persona y el cliente en
         * segunda (§10.4), así que la voz la pone cada superficie.
         */
        public ?string $cobroMetodo,
        /** **CUÁNDO se cobró**, ya formateado. Sin fecha, el ancla no se puede cotejar con el banco. */
        public ?string $cobroFecha,

        // ── Trazabilidad y contexto ────────────────────────────────────────────────
        /** `Order.total`: lo facturado al reservar. **Fuera de la suma**, y solo se enseña si difiere. */
        public int $facturado,
        /** El desglose ↳ de lo pendiente en puerta, con sus etiquetas ya compuestas. */
        public array $gateLines,
        /** ¿El pedido llevaba señal? No se deduce de que quede algo pendiente. */
        public bool $hasDeposit,
        /**
         * La FRASE que explica el estado, o `null` si no hay nada que explicar.
         *
         * ⚠️⚠️ **Un número no explica.** Era el encargo del owner: «trazabilidad y explicación ante
         * cualquier situación, y que el cliente esté informado». La compone el DOMINIO —como ya hace
         * con las etiquetas del desglose de puerta (`DECISIONES #120(j)`)— porque decide qué caso es,
         * y eso es regla, no presentación.
         */
        public ?string $nota,
    ) {}

    /** ¿Hay algo más que el valor plano? Si no, la columna es una sola línea. */
    public function hasBreakdown(): bool
    {
        return $this->pendienteOnline > 0 || $this->pagadoPuerta > 0 || $this->pendientePuerta > 0
            || $this->compensado > 0 || $this->devuelto > 0 || $this->pendienteDevolucion > 0
            || $this->facturado !== $this->valor;
    }

    /**
     * ¿El eje de caja tiene algo que contar? **Sí en cuanto el parque ha cobrado algo.**
     *
     * ⚠️⚠️ **Éste era el defecto `L1`, y tenía DOS mitades** (`DECISIONES #128`,
     * `specs/desglose-dinero-cliente.md` §17.1):
     *
     *  1. **le faltaba el primer término.** Sin `cobradoOnline`, en un pedido normal —sin
     *     devoluciones— el bloque no se pintaba y **el cliente nunca veía cuánto había salido de su
     *     banco**. Es lo único que puede cotejar con su extracto, y es lo que convierte el desglose
     *     en algo VERIFICABLE en vez de solo legible: en `R-L6UTIA` habría puesto «cobrado por web
     *     30,00 €» al lado de «pagado por web 114,00 €» y el dato roto salta a la vista;
     *  2. **nadie lo llamaba.** El predicado existía aquí y las superficies lo re-derivaban por su
     *     cuenta —el cliente en JavaScript, el panel en su blade—, que es exactamente la forma de
     *     divergencia que esta clase existe para cerrar. Medido el 2026-08-24 sobre los 58 pedidos:
     *     el panel enseñaba el ancla en **28** de 38 pedidos sanos y el cliente en **9**; divergían
     *     en **19**. Ahora los dos preguntan aquí.
     *
     * ⚠️ En el desglose POR RESERVA `cobradoOnline` es 0 a propósito (el cobro es del PEDIDO), así
     * que este predicado sigue valiendo lo mismo que antes allí: cambia el pedido, no la reserva.
     *
     * ## ⚠️⚠️ 2026-08-24 · SEGUNDA VUELTA: «cuando ha habido un cobro» era demasiado (`#130`)
     *
     * `#128` lo dejó en `cobradoOnline > 0`, y el owner leyó la pantalla y no la entendió: en un
     * pedido corriente **el mismo importe salía dos veces**, como «Pagado por web 30,00 €» en el eje
     * del valor y como «Cobrado por web 30,00 €» aquí. Para quien lo lee son la misma frase con las
     * palabras cambiadas de orden, y un bloque entero que repite lo de arriba **no se lee como una
     * reconciliación: se lee como ruido**, y enseña a saltarse el bloque que sí importa.
     *
     * ▶ **La regla correcta es «¿dice algo que el eje del valor NO diga ya?»**, y son tres cosas:
     * que se haya devuelto dinero, que se deba devolver, o **que lo cobrado no coincida con lo
     * pagado**. Ese tercer término es el que conserva entero lo que `#128` vino a arreglar: en un
     * pedido SANO los dos importes coinciden por construcción (`PAY-17`), así que solo difieren
     * cuando el dato está roto — y ahí el bloque aparece y la contradicción se ve. Medido sobre
     * `R-L6UTIA`: 30,00 € cobrados contra 114,00 € «pagados», y el bloque sale.
     *
     * ▶ **Y lo verificable NO se pierde**: la fecha del cobro se muda a la línea del eje del valor
     * («Pagado por web · 24/08/2026»), que es exactamente lo que el PANEL ya hacía desde `P1/P10`.
     * Las dos superficies convergen otra vez, ahora en la forma buena.
     */
    public function hasCash(): bool
    {
        return $this->devuelto > 0
            || $this->pendienteDevolucion > 0
            || $this->cobradoOnline !== $this->pagadoOnline;
    }

    /** El desglose de un PEDIDO entero. */
    public static function forOrder(Order $order): self
    {
        $s = $order->financialSummary();

        return new self(
            valor: $s->totalFinalNeto(),
            pagadoOnline: $s->pagadoOnline(),
            pendienteOnline: $s->pendienteOnline(),
            pagadoPuerta: $s->cobradoPuerta(),
            pendientePuerta: $s->pendingAtGate(),
            compensado: $s->compensado(),
            cobradoOnline: $s->grossPaidOnline,
            devuelto: $s->effectiveRefunded(),
            retenido: $s->retenidoOnline(),
            pendienteDevolucion: $s->pendienteDevolucion(),
            cobroMetodo: $order->chargeMethod(),
            cobroFecha: $order->chargedAtLabel(),
            facturado: $s->totalOriginal,
            gateLines: array_map(
                fn (array $l): array => ['label' => $l['label'], 'amount_cents' => (int) $l['amount']],
                $order->gateBreakdownLines(),
            ),
            hasDeposit: $s->depositRemainder > 0,
            nota: self::noteFor($order, $s),
        );
    }

    /**
     * El desglose de UNA reserva (principal + sus complementos).
     *
     * ⚠️ El eje de CAJA no existe por reserva: el cobro y la devolución son del PEDIDO —hay un solo
     * `Payment`—. Lo que sí es de la reserva es su parte devuelta y su pendiente, que viajan aquí
     * para que la sub-card y el PDF los pinten sin recomponer nada.
     */
    public static function forReservation(Order $order, OrderItem $principal): self
    {
        $rf = ReservationFinancials::make($order, $principal);

        return new self(
            valor: $rf->valor,
            pagadoOnline: $rf->pagadoOnline,
            pendienteOnline: $rf->pendienteOnline,
            pagadoPuerta: $rf->cobradoPuerta,
            pendientePuerta: $rf->aCobrarPuerta,
            compensado: $rf->compensado,
            cobradoOnline: 0,
            devuelto: $rf->devuelto,
            retenido: 0,
            pendienteDevolucion: $rf->pendienteReembolso,
            // El MÉTODO sí viaja por reserva aunque el importe no: es un hecho del pedido, y la
            // tarjeta de la reserva rotula con él lo pagado por adelantado («Pagado por web 30,00 €
            // · 90,00 € en el parque»). Sin él, esa nota afirmaría «por web» en un pedido cobrado en
            // taquilla. La FECHA no viaja: sin importe al lado no explica nada.
            cobroMetodo: $order->chargeMethod(),
            cobroFecha: null,
            facturado: $rf->valor,          // sin base propia: la reserva no se factura por separado
            gateLines: array_map(
                fn (array $l): array => ['label' => $l['label'], 'amount_cents' => (int) $l['amount']],
                $order->reservationGateLines($principal),
            ),
            hasDeposit: $principal->ticketType?->hasDeposit() ?? false,
            nota: null,
        );
    }

    /**
     * La frase de estado, en el orden en que importa: primero lo que el cliente tiene que saber.
     *
     * ⚠️ **El orden de los casos ES la regla.** Un pedido cancelado con dinero pendiente de devolver
     * cumple varias condiciones a la vez, y anunciar «caducó» o «te devolvimos» antes que «tenemos
     * pendiente devolverte» le escondería lo único que le importa.
     */
    private static function noteFor(Order $order, OrderFinancialSummary $s): ?string
    {
        $cancelado = $order->status === Order::STATUS_CANCELLED;
        $fechaCancel = DisplayTime::format($order->refunded_at ?? $order->updated_at, 'd/m/Y');
        $importe = fn (int $c): string => Money::amount($c).' '.($order->currency === 'EUR' ? '€' : (string) $order->currency);

        // 1 · Se le debe dinero. Va primero SIEMPRE: es lo único que el cliente necesita saber.
        if ($s->pendienteDevolucion() > 0) {
            return __($cancelado ? 'tickets.ledger_note.cancelled_owing' : 'tickets.ledger_note.owing', [
                'amount' => $importe($s->pendienteDevolucion()),
                'date' => $fechaCancel,
            ]);
        }

        // 2 · Cancelado y ya saldado.
        if ($cancelado) {
            return $s->effectiveRefunded() > 0
                ? __('tickets.ledger_note.cancelled_refunded', [
                    'amount' => $importe($s->effectiveRefunded()),
                    'date' => DisplayTime::format($order->refunded_at, 'd/m/Y'),
                ])
                : __('tickets.ledger_note.cancelled', ['date' => $fechaCancel]);
        }

        // 3 · Caducó sin llegar a cobrarse. «No se te ha cobrado nada» es la mitad del mensaje.
        if ($order->displayStatus() === Order::STATUS_EXPIRED) {
            return __('tickets.ledger_note.expired');
        }

        // 4 · Todavía sin pagar, y aún se puede.
        if ($s->pendienteOnline() > 0) {
            return __('tickets.ledger_note.pending_payment', ['amount' => $importe($s->pendienteOnline())]);
        }

        // 5 · Se le devolvió dinero y conserva su reserva. La INTENCIÓN decide qué se le dice: sin
        //     ella solo se podría decir «te devolvimos X», que no responde a «¿debo algo?».
        if ($s->compensado() > 0) {
            // ⚠️ La clave llega como CADENA desde `Order`: la frontera de módulos prohíbe que
            // `Booking` nombre un modelo de `Payments`, y `ModuleBoundariesTest` lo dijo en cuanto
            // se intentó. `default` cubre las filas anteriores a `#127(c)`, donde no consta.
            return match ($order->lastRefundIntent()) {
                'paid_in_person' => __('tickets.ledger_note.refunded_pay_in_person', [
                    'amount' => $importe($s->compensado()),
                ]),
                'compensation' => __('tickets.ledger_note.compensated', [
                    'amount' => $importe($s->compensado()),
                ]),
                default => __('tickets.ledger_note.refunded_still_booked', [
                    'amount' => $importe($s->compensado()),
                ]),
            };
        }

        // 6 · Queda algo por pagar en recepción.
        if ($s->pendingAtGate() > 0) {
            return __('tickets.ledger_note.pending_at_gate', ['amount' => $importe($s->pendingAtGate())]);
        }

        // 7 · Nada que explicar. Una frase de relleno enseña a ignorar las que sí importan.
        return null;
    }
}
