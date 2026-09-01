<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;

/**
 * Resumen financiero canónico de un Order (sub-fase 7.2e cimientos).
 *
 * Encapsula los 7 cálculos que el panel y "Mis pedidos" pueden necesitar mostrar.
 * Una única fuente de verdad — los blades deciden qué dimensiones renderizan
 * según relevancia operativa (helpers `has*()`).
 *
 * Conceptos (acordados en sesión 7.2e con la clienta):
 *  - `totalOriginal()`     → `Order.total` (lo cobrado online vía Redsys al pagar).
 *  - `totalRefunded()`     → suma de `PaymentRefund.succeeded` (refunds totales + parciales,
 *                            REST + manual). Es lo que SALIÓ del parque hacia el cliente.
 *  - `netOnline()`         → `totalOriginal − totalRefunded`. Cuánto dinero retiene
 *                            efectivamente el parque del cobro online.
 *  - `extraDue()`          → suma de `OrderAdjustment.extra_due` cuyos items NO están
 *                            cancelados. Importes nuevos que el cliente debe al parque
 *                            por ediciones que subieron precio. Los ajustes de un item
 *                            CANCELADO se ANULAN (se excluyen): el cargo era por algo
 *                            que se quitó antes de cobrarlo. Incluye items finalizados.
 *  - `extraDueResolved()`  → subset de `extraDue` cuyos items YA FINALIZARON — se
 *                            considera implícitamente cobrado en puerta (el paso del
 *                            slot cierra la cuenta; decisión clienta sesión 7.2e). Los
 *                            CANCELADOS no entran aquí: ya se anularon en `extraDue`.
 *  - `pendingAtGate()`     → `extraDue − extraDueResolved`. Pendiente real de cobrar.
 *  - `totalWithChanges()`  → `totalOriginal + extraDue − reembolso efectivo`. Lo que
 *                            el cliente terminará pagando si saldó todo. El reembolso
 *                            efectivo es legacy-safe ({@see effectiveRefunded}).
 *
 * **Construcción** vía `Order::financialSummary()`. El value object es inmutable
 * y trivialmente serializable. Para evitar N+1 en blades, el caller debe
 * eager-load `payments.refunds` + `adjustments` + `items.slot` (este último para
 * resolver `isFinishedInPractice()` sin queries extra).
 */
final readonly class OrderFinancialSummary
{
    /**
     * @param  int  $totalOriginal  céntimos
     * @param  int  $totalRefunded  céntimos (suma de payment_refunds.succeeded)
     * @param  int  $extraDue  céntimos (suma de order_adjustments.extra_due)
     * @param  int  $extraDueResolved  céntimos (subset cuyos items finalizaron o se cancelaron)
     * @param  int  $productsValue  céntimos — valor ACTUAL de los productos (Σ
     *                              `chargedSubtotalCents` de los items NO cancelados,
     *                              principales + complementos). Es lo que el cliente
     *                              acaba pagando NETO ("Total final") y lo que suman las
     *                              cards de producto.
     * @param  int  $refundColumn  céntimos — `Order.refund_amount_cents` (agregado
     *                             LEGACY-SAFE del reembolso). Hay refunds pre-#142 que
     *                             solo viven aquí (sin fila `payment_refunds`); se usa
     *                             para que el reembolso efectivo no se pierda.
     * @param  int  $depositRemainder  céntimos (#225) — el cubo del RESTO DE LA SEÑAL, derivado por
     *                                 línea (`GateBuckets`) de items NO cancelados: a cobrar en
     *                                 el parque, conocido desde la creación. Es parte del VALOR
     *                                 (ya dentro de `Order.total`/`productsValue`), NO un delta
     *                                 de ediciones → suma a `pendingAtGate` pero NUNCA a
     *                                 `extraDue`/`totalWithChanges` (eso sería doble-conteo).
     * @param  int  $depositRemainderResolved  céntimos — subset cuyos items YA FINALIZARON
     *                                         (cobrado en puerta implícitamente, mismo criterio que
     *                                         `extraDueResolved`).
     */
    public function __construct(
        public string $currency,
        public int $totalOriginal,
        public int $totalRefunded,
        public int $extraDue,
        public int $extraDueResolved,
        public int $productsValue = 0,
        public int $refundColumn = 0,
        public int $depositRemainder = 0,
        public int $depositRemainderResolved = 0,
        public int $grossPaidOnline = 0,
        public int $onlineBacking = 0,
        /**
         * ¿Ha entrado dinero por este pedido alguna vez? (`paid_at !== null`). Reparte
         * {@see $onlineBacking} entre {@see pagadoOnline()} y {@see pendienteOnline()}, y decide si
         * las cestas de puerta pueden darse por cobradas.
         */
        public bool $collected = false,
    ) {}

    /**
     * Construir desde un Order. NO toca BD si las relaciones están eager-loaded
     * (`payments.refunds` + `adjustments` + `items.slot`). En caso contrario
     * las collections de Eloquent las cargarán perezosamente.
     */
    public static function fromOrder(Order $order): self
    {
        // ⚠️ «Sin cobro no hay cobro» (`DECISIONES #127`). `paid_at` es el predicado, no el `status`:
        // lo escriben los DOS canales de cobro reales —`RedsysReturnHandler` (web) y
        // `ManualOrderFulfiller` (taquilla)— y sobrevive a la cancelación y al reembolso, que es
        // justo lo que hace falta para no borrar la historia contable de un pedido cancelado.
        $orderCollected = $order->paid_at !== null;
        // Un pedido CANCELADO no tiene valor vivo ni cargos de puerta que cobrar (`DECISIONES #127`).
        // Segunda capa: la cascada al cancelar deja el dato explícito, esto lo hace cierto también
        // sobre filas anteriores a la cascada.
        $orderCancelled = $order->status === Order::STATUS_CANCELLED;

        $totalRefunded = 0;
        foreach ($order->payments as $payment) {
            foreach ($payment->refunds as $refund) {
                if ($refund->status === PaymentRefund::STATUS_SUCCEEDED) {
                    $totalRefunded += (int) $refund->amount_cents;
                }
            }
        }

        $extraDue = 0;
        $extraDueResolved = 0;
        $depositRemainder = 0;          // #225: resto de la señal a cobrar en puerta
        $depositRemainderResolved = 0;
        // T1 del libro: los dos cubos de puerta se DERIVAN por línea desde sus hechos
        // (`GateBuckets` replica la cascada que hasta la T1 se escribía), no se suman de filas por
        // tipo. Mismas cifras; lo que cambia es que el hecho es ahora el delta entero.
        foreach ($order->items as $item) {
            // Item CANCELADO → sus cubos quedan ANULADOS (mismo criterio para AMBOS buckets de
            // puerta). El cargo era por algo que se quitó ANTES de cobrarlo (p. ej. un complemento
            // añadido en una edición y luego sustituido en un cambio de menú, o el resto de la señal
            // de una línea cancelada): nunca llegó a cobrarse en puerta, así que NO cuenta ni en el
            // total con cambios ni en lo pendiente. Distinto de un item FINALIZADO (abajo), que sí
            // se asume cobrado.
            if ($item->isCancelled() || $orderCancelled) {
                continue;
            }

            $buckets = GateBuckets::forItem($order, $item);

            // Item FINALIZADO (pasó su franja) → cobrado en puerta implícitamente
            // (decisión clienta): cuenta en el total con cambios, pero ya NO en lo pendiente.
            //
            // ⚠️⚠️ **Y el pedido tiene que haberse COBRADO** (`DECISIONES #127`): que la franja haya
            // pasado no cobra nada en el parque si nadie llegó a pagar el pedido. Sin esta condición
            // un checkout abandonado cuya franja pasa declara «Pagado en el parque X €» de dinero que
            // no existe — medido en staging (`R-VYXKRD`) y reproducido en local con `orders:expire`.
            // Es la MISMA condición que {@see ReservationFinancials::showsDepositNote} ya aplicaba
            // tres líneas más abajo en la misma clase hermana.
            $resolved = $item->isFinishedInPractice() && $orderCollected;

            // Delta de EDICIONES (sube `totalWithChanges`).
            $extraDue += $buckets->extraDue;
            // Resto de la SEÑAL (#225): parte del VALOR base no cobrada online. Suma al bucket de
            // puerta, pero NO a `extraDue` (no infla `totalWithChanges`).
            $depositRemainder += $buckets->depositRemainder;
            if ($resolved) {
                $extraDueResolved += $buckets->extraDue;
                $depositRemainderResolved += $buckets->depositRemainder;
            }
        }

        $productsValue = 0;
        $onlineBacking = 0;
        foreach ($order->items as $item) {
            if ($item->isCancelled() || $orderCancelled) {
                continue;
            }
            // Suma TODOS los items no cancelados (principales + complementos: la
            // relación `items` es plana sobre order_id). Cada `chargedSubtotalCents`
            // ya descuenta las unidades incluidas gratis (`free_quantity`).
            $productsValue += $item->chargedSubtotalCents();
            // Lo cobrado ONLINE que AÚN respalda producto (= la señal/lo pagado online de las
            // líneas vivas). Base DEPOSIT-AWARE de «pendiente de devolución» (#225): un pack del
            // que solo se cobró la señal aporta la SEÑAL aquí, no su valor pleno.
            $onlineBacking += $order->itemCollectedCents($item);
        }

        // Lo realmente cobrado por WEB (Σ pagos pagados). Ancla de caja para «pendiente de
        // devolución» (#225): el antiguo `Order.total` era el VALOR pleno, no lo cobrado online
        // → con señal sobreestimaba el dinero a devolver (bug del pedido JJ-VDHXYH).
        $grossPaidOnline = 0;
        foreach ($order->payments as $payment) {
            if ($payment->status === Payment::STATUS_PAID) {
                $grossPaidOnline += (int) $payment->amount;
            }
        }

        return new self(
            currency: (string) ($order->currency ?? 'EUR'),
            totalOriginal: (int) $order->total,
            totalRefunded: $totalRefunded,
            extraDue: $extraDue,
            extraDueResolved: $extraDueResolved,
            productsValue: $productsValue,
            refundColumn: (int) ($order->refund_amount_cents ?? 0),
            depositRemainder: $depositRemainder,
            depositRemainderResolved: $depositRemainderResolved,
            grossPaidOnline: $grossPaidOnline,
            onlineBacking: $onlineBacking,
            collected: $orderCollected,
        );
    }

    /**
     * Dinero que ENTRÓ por web y **ya no respalda producto**: `cobrado − lo que respalda`. Es la
     * medida de cuánta devolución está justificada por una pérdida de valor (una cancelación, una
     * bajada, un cambio a producto más barato).
     */
    public function unbackedOnline(): int
    {
        return max(0, $this->grossPaidOnline - $this->onlineBacking);
    }

    /**
     * **EJE VALOR · COMPENSACIÓN** — dinero devuelto SIN que desapareciera producto
     * (`DECISIONES #127`): la cortesía, o un reembolso sin cancelar. No baja el valor y no es un
     * canal de cobro: es su propio término, y por eso la «ley de caja» arrastraba una excepción que
     * en realidad era un término que faltaba.
     *
     * ⚠️⚠️ **Está anclado a CAJA, no reconstruido por línea, y eso es deliberado.** La versión
     * por-línea sobre-reporta cuando la bajada no se puede reconstruir desde el ítem —exactamente el
     * caso que `#225` arregló anclando `pendienteDevolucion` a lo realmente cobrado: un cambio a
     * producto más barato deja `unit_price` nuevo, así que `cantidad_original × unit_price` miente—.
     * Medido: definirlo por línea rompía `test_pendiente_devolucion_cleared_by_succeeded_refund`,
     * que es justo un caso de valor perdido sin huella en el ítem.
     */
    public function compensado(): int
    {
        return max(0, $this->effectiveRefunded() - $this->unbackedOnline());
    }

    /**
     * **EJE VALOR · canal WEB COBRADO**: lo cobrado por web que respalda producto vivo, NETO de
     * compensación. `0` mientras el pedido no se haya cobrado — su importe está entonces en
     * {@see pendienteOnline()}.
     */
    public function pagadoOnline(): int
    {
        return $this->collected ? $this->onlineBacking - $this->compensado() : 0;
    }

    /**
     * **EJE VALOR · canal WEB PENDIENTE**: lo que falta por cobrar POR WEB. `0` en cuanto el pedido
     * se cobra.
     *
     * ⚠️ Es el importe que la API publicaba como `online_amount_cents` y la pantalla leía **en
     * pasado** («Pagado online 11,90 €» en un pedido que nadie ha pagado). Separar cobrado de
     * pendiente mata ese defecto de raíz.
     */
    public function pendienteOnline(): int
    {
        return $this->collected ? 0 : $this->onlineBacking;
    }

    /** **EJE VALOR · canal PUERTA COBRADA**: los dos buckets ya resueltos. */
    public function cobradoPuerta(): int
    {
        return $this->extraDueResolved + $this->depositRemainderResolved;
    }

    /**
     * **EJE CAJA · lo RETENIDO**: el dinero del cliente que sigue en la caja del parque.
     * `cobrado por web − devuelto`. Es el ancla que el cliente puede cotejar con su banco.
     */
    public function retenidoOnline(): int
    {
        return $this->grossPaidOnline - $this->effectiveRefunded();
    }

    public function netOnline(): int
    {
        return $this->totalOriginal - $this->totalRefunded;
    }

    /**
     * Reembolsado EFECTIVO legacy-safe: el mayor entre la suma de
     * `payment_refunds.succeeded` ({@see $totalRefunded}) y la columna agregada
     * `Order.refund_amount_cents` ({@see $refundColumn}). Los refunds pre-#142 solo
     * viven en la columna (sin fila `payment_refunds`); usar solo `totalRefunded`
     * los perdería y haría que "Total con cambios" y "Pendiente de devolución"
     * DOBLE-CONTARAN un reembolso parcial legacy (que sí aparece como "Devuelto"
     * desde la columna). Coherente con `Order::totalWithChangesCents()`.
     */
    public function effectiveRefunded(): int
    {
        return max($this->totalRefunded, $this->refundColumn);
    }

    /**
     * ¿La columna agregada dice algo que las filas no? **Nunca debería.**
     *
     * ⚠️⚠️ Es lo que sustituye a la «legacy-safety» como mecanismo (`DECISIONES #127`). Medido: los
     * DOS únicos escritores de `Order.refund_amount_cents` —`executeFullRefund` y
     * `executePartialRefund`— la derivan de `totalRefundedCents()`, y no hay ninguno en
     * `app/Filament` ni en `app/Http`. Con la decisión del owner de que **JumpWeb solo instala
     * limpio**, ninguna base tiene datos pre-#142, así que el `max()` de {@see effectiveRefunded} es
     * código muerto: no protege de nada que pueda ocurrir.
     *
     * No se retira el `max()` —quitar un cinturón de dinero no compra nada—, se **vigila**: el test
     * de invariantes asevera que esto es siempre `false`. El día que alguien escriba la columna a
     * mano, la guarda lo dice en vez de que la divergencia viva escondida en las tarjetas, como pasó
     * durante toda la vida del reembolso total.
     */
    public function refundColumnDivergesFromRows(): bool
    {
        return $this->refundColumn !== $this->totalRefunded;
    }

    public function pendingAtGate(): int
    {
        // Ambos buckets de puerta: el delta de ediciones (`extraDue`) y el resto de la
        // señal (#225, `depositRemainder`). El cobrado-en-puerta (items finalizados) se
        // descuenta de cada uno.
        return max(0, ($this->extraDue + $this->depositRemainder)
            - ($this->extraDueResolved + $this->depositRemainderResolved));
    }

    public function totalWithChanges(): int
    {
        return $this->totalOriginal + $this->extraDue - $this->effectiveRefunded();
    }

    /**
     * **Total final (neto)** = valor ACTUAL de los productos = lo que el cliente
     * acaba pagando una vez saldado todo (puerta cobrada + devoluciones hechas).
     * Coincide con la suma de los "Total del producto" de cada card → reconcilia
     * el bloque de totales del pedido con las sub-cards. Es `totalWithChanges`
     * MENOS lo que aún se debe devolver ({@see pendienteDevolucion}).
     */
    public function totalFinalNeto(): int
    {
        return $this->productsValue;
    }

    /**
     * **Pendiente de devolución** = dinero pagado ONLINE que ya no tiene producto
     * detrás (una reducción de cantidad o una cancelación) y que AÚN no se ha
     * devuelto. Cubre tanto cancelaciones como reducciones, y los reembolsos que
     * fallaron o están pendientes de reintento.
     *
     * **Fuente DEPOSIT-AWARE de CAJA (#225):** `dinero ONLINE retenido − lo que aún respalda
     * producto` = `max(0, grossPaidOnline − reembolso efectivo) − onlineBacking`, donde
     * `onlineBacking = Σ itemCollectedCents(no cancelados)`. Antes era `totalWithChanges −
     * productsValue`, que usaba `Order.total` (el VALOR pleno) como proxy de lo cobrado online:
     * correcto en pago completo, pero SOBREESTIMABA con señal (p. ej. el pedido JJ-VDHXYH: pagó
     * 30 € online de un pack de 180/202 € y, tras subir y bajar invitados, mostraba 36 € fantasma
     * a devolver cuando solo había cobrado 30 € → riesgo de reembolsar de más). Anclar la fórmula
     * al dinero REAL cobrado por web la hace correcta para señal, bajada, cancelación Y cambio a
     * producto más barato (donde el `Σ itemPendingRefundCents` por-línea no puede reconstruir el
     * online original). `effectiveRefunded` mantiene la robustez legacy del reembolso por columna.
     *
     * En producción, al reducir/cancelar el reembolso se procesa con éxito y esto vuelve a 0
     * (aparece como "Devuelto"); queda >0 solo mientras un reembolso está pendiente de completarse.
     */
    public function pendienteDevolucion(): int
    {
        // ⚠️ Se descuenta el PAGADO ONLINE NETO, no `onlineBacking` en crudo (`DECISIONES #127`): un
        // reembolso de cortesía sobre producto vivo baja lo retenido **y** baja lo que ese producto
        // tiene pagado, así que no deja nada pendiente. Restar el bruto lo contaría dos veces.
        return max(0, $this->retenidoOnline() - $this->pagadoOnline());
    }

    public function hasPendienteDevolucion(): bool
    {
        return $this->pendienteDevolucion() > 0;
    }

    public function hasRefunds(): bool
    {
        return $this->totalRefunded > 0;
    }

    public function hasExtraDue(): bool
    {
        return $this->extraDue > 0;
    }

    public function hasPendingAtGate(): bool
    {
        return $this->pendingAtGate() > 0;
    }

    /**
     * ¿El pedido está completamente saldado? Sin pendientes en puerta. Vale tanto
     * para el caso trivial "sin cambios ni refunds" como para "todo cerrado tras
     * ediciones". El blade de la card Resumen muestra "✓ Cuentas cerradas" en
     * este estado cuando hubo actividad (refunds o extras).
     */
    public function isFullyResolved(): bool
    {
        return ! $this->hasPendingAtGate();
    }

    /**
     * ¿Hubo CUALQUIER actividad financiera (refund o extra)? Útil para decidir
     * si la card Resumen muestra solo "Total" (estado 0) o el desglose
     * extendido (estados 1-4).
     */
    public function hasActivity(): bool
    {
        // Un pedido de SOLO señal (sin extra_due ni refunds) también tiene desglose: el
        // resto a cobrar en el parque. Si no, caería en el caso simple «solo Total» y lo
        // ocultaría (#225).
        return $this->hasRefunds() || $this->hasExtraDue() || $this->depositRemainder > 0;
    }
}
