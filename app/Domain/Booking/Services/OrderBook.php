<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

/**
 * **EL LIBRO DEL PEDIDO** (`specs/desglose-libro.md` §4, `DECISIONES #305` `[DECIDIDO owner]`).
 *
 * El desglose deja de ser un balance neteado —dos ejes, cinco canales, cuatro mecanismos de frase—
 * y pasa a ser un **libro de movimientos**: cada gestión es una línea con su importe, su signo y su
 * fecha; el Total es la suma; y el SALDO entre lo que vale y lo que se ha pagado **se liquida en el
 * parque** (positivo se paga, negativo se devuelve). Nada se cobra ni se devuelve online después de
 * la reserva salvo por acción manual del operador (D2/D5).
 *
 * ## La aritmética (spec §4.1) — por línea → por reserva → por pedido
 *
 *     fila(i)        = chargedSubtotalCents()            (con signo: una línea de crédito resta)
 *     Δ(i)           = Σ hechos `edit` + `mixed` de i     (LineFacts::editDelta)
 *     nac(i)         = fila(i) − Δ(i)                     (LineFacts::birthValue — un HECHO)
 *     online_nac(i)  = nac(i) − reparto(i)  si el pedido se cobró, si no 0   («sin cobro no hay cobro»)
 *     dev(i)         = reembolsos con éxito atribuidos a i (Order::itemRefundedCents, la prorrata del total)
 *     cortesía(i)    = Σ hechos `courtesy` de i           (≤ 0)
 *
 *     Total(r)     = Σ_{líneas VIVAS} (fila + cortesía)   (la cortesía de una línea cancelada se extingue con ella)
 *     Pagado(r)    = Σ online_nac − Σ dev
 *     Liquidado(r) = Total(r) − Pagado(r), CON SIGNO   si la franja pasó, el pedido se cobró y el principal vive; si no 0
 *     Saldo(r)     = Total(r) − Pagado(r) − Liquidado(r)
 *
 * La liquidación es SIMÉTRICA (D9 bis, T4 del libro, `DECISIONES #316` `[DECIDIDO owner]`): al pasar
 * la visita, lo que quedaba por pagar se dio por cobrado en recepción («Liquidado en el parque») y lo
 * que quedaba por devolver se dio por entregado allí («Devuelto en el parque»). La inferencia CEDE
 * ante los hechos: un reembolso registrado después hace crecer `dev` y lo inferido se reduce en la
 * misma cantidad hasta desaparecer — el libro no cuenta el dinero dos veces.
 *
 * ## Las identidades, evaluadas EN EJECUCIÓN (spec §4.1)
 *
 *     I1 · nacimiento   Order.total == Σ nac(i)                  (falta un hecho, o el total se fabricó)
 *     I2 · caja         Cobrado == Σ online_nac(i)               (y un pedido `paid` tiene cobro)
 *     I3 · libro        Total == Σ líneas de valor               (el compositor no olvidó ninguna clase)
 *     I4 · columna      Order.refund_amount_cents == Σ reembolsos con éxito
 *
 * Si alguna falla, el pedido queda «en revisión» (`is_consistent = false`, {@see Balance::KIND_UNDER_REVIEW}):
 * no se afirma ningún saldo — la conducta de `#132`, intacta.
 *
 * ## Lo que NO hace, a propósito
 *
 * - **No consulta el catálogo.** Ningún importe se reconstruye desde configuración viva (la raíz
 *   del fantasma de la señal, cerrada en la T1): todo sale de filas con fecha.
 * - **No consulta la base de datos.** Lee las relaciones YA CARGADAS: `items` (con `slot` y
 *   `ticketType`), `adjustments`, `payments.refunds`. Un consumidor que las olvide paga N+1.
 * - **No nombra `Payments\Models`** (`ModuleBoundariesTest`): los pagos y reembolsos llegan como
 *   HECHOS ya traducidos por {@see Order::collectedPaymentFacts} y {@see Order::refundFacts}.
 * - **Avisa al log cuando no cuadra** (`ledger.no_cuadra`, T3·1): el parque tiene que ENTERARSE
 *   (`#132`). Va como `warning` y no como excepción porque el libro se compone al PINTAR: reventar
 *   dejaría al cliente sin pantalla por un dato que ya está mal.
 *
 * ▶ Desde la T3·4 (`DECISIONES #315`) es el ÚNICO modelo: el de dos ejes (`OrderLedger`,
 * `OrderFinancialSummary`, `ReservationFinancials`, `GateBuckets`) se retiró, y las identidades de
 * arriba son las que `OrderFinancialInvariantsTest` vigila en sus 13 escenarios (`PAY-16`/`PAY-17`).
 */
final readonly class OrderBook
{
    /**
     * @param  list<Movement>  $movements  las líneas de VALOR, cronológicas
     * @param  list<Settlement>  $settlements  las líneas de DINERO, cronológicas
     */
    public function __construct(
        public string $currency,
        public array $movements,
        public array $settlements,
        /** `Total`: lo que vale hoy lo que sigue vivo, cortesías incluidas. */
        public int $totalCents,
        /** `Cobrado − Devuelto + Liquidado` — solo lo `succeeded` y lo liquidado por la regla. */
        public int $paidCents,
        public Balance $balance,
        /** ¿Alguna línea viva nació con reparto de señal? Un HECHO (`deposit_split`), no una configuración. */
        public bool $hasDeposit,
        /** `I1 ∧ I2 ∧ I3 ∧ I4`. Si es `false`, ninguna descomposición es cierta. */
        public bool $isConsistent,
        /** Solo tres frases sobreviven: «en revisión», «caducó», «pendiente de pago» (spec §4.3). */
        public ?string $note,
    ) {}

    /** El libro del PEDIDO entero. */
    public static function forOrder(Order $order): self
    {
        $c = self::compose($order);

        if (! $c['consistent']) {
            Log::warning('ledger.no_cuadra', [
                'order' => $order->code,
                'facturado' => (int) $order->total,
                'nacimiento' => $order->birthValueCents(),
                'cobrado' => $c['cobrado'],
                'online_al_nacer' => array_sum(array_column($c['reservations'], 'online_nac')),
                'total' => array_sum(array_column($c['reservations'], 'total')),
                'lineas_de_valor' => $c['value_lines'],
                'columna_reembolso' => (int) ($order->refund_amount_cents ?? 0),
                'devuelto' => $c['devuelto'],
                'status' => $order->status,
                'cobrado_en' => $order->paid_at?->toIso8601String(),
            ]);
        }

        $movements = [self::movement(
            Movement::KIND_BOOKING,
            MovementLabel::booking(),
            (int) $order->total,
            $order->created_at,
            null,
            rank: 0,
            seq: 0,
        )];
        foreach ($c['reservations'] as $r) {
            foreach ($r['movements'] as $m) {
                if ($c['multi']) {
                    $m['label'] = MovementLabel::inReservation($r['name'], $m['label']);
                }
                $movements[] = $m;
            }
        }

        $settlements = [];
        foreach ($c['payments'] as $p) {
            $settlements[] = self::settlement(Settlement::KIND_PAYMENT, MovementLabel::payment($p['method']), $p['amount_cents'], $p['occurred_at'], Settlement::STATUS_SUCCEEDED, $p['method'], $p['id']);
        }
        foreach ($c['refunds'] as $f) {
            $settlements[] = self::refundSettlement($f, $f['amount_cents']);
        }
        foreach ($c['reservations'] as $r) {
            if ($r['liquidado'] !== 0) {
                $settlements[] = self::gateSettlement($r);
            }
        }

        $total = array_sum(array_column($c['reservations'], 'total'));
        $paid = $c['cobrado'] - $c['devuelto'] + array_sum(array_column($c['reservations'], 'liquidado'));
        $alive = array_reduce($c['reservations'], static fn (bool $carry, array $r): bool => $carry || $r['alive'], false);
        $balance = self::balance($order, $c['collected'], $c['consistent'], $total, $paid, $alive, $order->onlineDueCents());

        return new self(
            currency: $c['currency'],
            movements: self::sortedMovements($movements),
            settlements: self::sortedSettlements($settlements),
            totalCents: $total,
            paidCents: $paid,
            balance: $balance,
            hasDeposit: array_reduce($c['reservations'], static fn (bool $carry, array $r): bool => $carry || $r['has_deposit'], false),
            isConsistent: $c['consistent'],
            note: self::note($balance, $c['currency']),
        );
    }

    /**
     * El libro de UNA reserva (principal + sus complementos), acotado a sus líneas: el nacimiento es
     * `nac(r)`, y el cobro y las devoluciones viajan ATRIBUIDOS (`online_nac`, `dev`).
     *
     * ⚠️ La consistencia es la del PEDIDO (las identidades son del pedido): se compone el pedido
     * entero para evaluarla. Es lectura pura, sin consultas.
     */
    public static function forReservation(Order $order, OrderItem $principal): self
    {
        $c = self::compose($order);
        $r = $c['reservations'][(int) $principal->id] ?? null;
        if ($r === null) {
            throw new \DomainException('The item is not a principal line of this order.');
        }

        $movements = [self::movement(
            Movement::KIND_BOOKING,
            MovementLabel::booking(),
            $r['nac'],
            $order->created_at,
            (int) $principal->id,
            rank: 0,
            seq: 0,
        ), ...$r['movements']];

        $settlements = [];
        // El cobro ATRIBUIDO: lo que estas líneas aportaron al pago online al nacer, con el método y la
        // fecha del cobro del pedido (hay UN cobro, `PAY-01`: el último con éxito manda).
        $lastPayment = $c['payments'] === [] ? null : $c['payments'][array_key_last($c['payments'])];
        if ($lastPayment !== null && $r['online_nac'] > 0) {
            $settlements[] = self::settlement(Settlement::KIND_PAYMENT, MovementLabel::payment($lastPayment['method']), $r['online_nac'], $lastPayment['occurred_at'], Settlement::STATUS_SUCCEEDED, $lastPayment['method'], $lastPayment['id']);
        }
        // Las devoluciones: las atadas a una de sus líneas, enteras; las del PEDIDO (un reembolso
        // total se escribe sin línea), por la misma prorrata con la que `itemRefundedCents` las
        // atribuye — de céntimo en céntimo y sin fuga, telescopando el acumulado por estado.
        $cumulative = [];
        foreach ($c['refunds'] as $f) {
            if ($f['order_item_id'] !== null) {
                if (in_array($f['order_item_id'], $r['line_ids'], true)) {
                    $settlements[] = self::refundSettlement($f, $f['amount_cents']);
                }

                continue;
            }
            $before = $cumulative[$f['status']] ?? 0;
            $after = $before + $f['amount_cents'];
            $cumulative[$f['status']] = $after;
            $share = self::unattributedShareForLines($order, $r['lines'], $after) - self::unattributedShareForLines($order, $r['lines'], $before);
            if ($share > 0) {
                $settlements[] = self::refundSettlement($f, $share);
            }
        }
        if ($r['liquidado'] !== 0) {
            $settlements[] = self::gateSettlement($r);
        }

        $paid = $r['online_nac'] - $r['dev'] + $r['liquidado'];
        $balance = self::balance($order, $c['collected'], $c['consistent'], $r['total'], $paid, $r['alive'], $r['online_due']);

        return new self(
            currency: $c['currency'],
            movements: self::sortedMovements($movements),
            settlements: self::sortedSettlements($settlements),
            totalCents: $r['total'],
            paidCents: $paid,
            balance: $balance,
            hasDeposit: $r['has_deposit'],
            isConsistent: $c['consistent'],
            note: self::note($balance, $c['currency']),
        );
    }

    // ▶ Hasta `#318` aquí vivía `hasHistoryToExplain()`: la condición del atajo «Ver historial» bajo el
    // libro (T5 adenda 4, D-T3·14/16). El owner retiró el atajo —el libro va plegado con su propio
    // CTA— y con él murió la condición: sin consumidor, no se conserva.

    /**
     * Lo que se le DEBE al cliente EN DINERO, en positivo: lo cobrado y no devuelto por encima de lo
     * que hoy vale, `max(0, Pagado_sin_liquidar − Total)`, con `Pagado_sin_liquidar = cobrado −
     * devuelto` (lo que de verdad entró y no ha vuelto, sin la liquidación INFERIDA). Es el importe
     * que el panel sugiere al reembolsar (D5 de `#146`), el TOPE de «devolver lo que se le debe» y la
     * base sobre la que se mide la cortesía (T4, `DECISIONES #316`), y el que nombra el aviso del
     * pedido cancelado (D-T3·15). 0 si el libro no cierra: un pedido «en revisión» no debe nada que
     * el libro pueda afirmar.
     *
     * ⚠️ NO es «el saldo cuando es de devolución» (lo fue hasta la T4), y la diferencia es D9 bis:
     * con la visita pasada el saldo queda `settled` porque lo debido se da por ENTREGADO en recepción
     * («Devuelto en el parque»), pero eso es una inferencia, y si el operador lo devuelve DESPUÉS por
     * tarjeta es porque la recepción no lo hizo — ese reembolso tiene que poder ser «devolver lo
     * debido» (sin cortesía) y hacer desaparecer lo inferido (D-T4·4: la inferencia cede ante los
     * hechos). Medido con el saldo, aquí valdría 0, el modal solo ofrecería «compensación» y el libro
     * escribiría una cortesía falsa encima de una devolución inferida que nunca ocurrió: el dinero
     * contado dos veces (D-T4·6, `specs/desglose-libro.md` §6.4.1).
     */
    public function owedToCustomerCents(): int
    {
        if (! $this->isConsistent) {
            return 0;
        }

        return max(0, ($this->paidCents - $this->settledAtGateCents()) - $this->totalCents);
    }

    /**
     * El MÉTODO del cobro (`web` · `desk` · `card` · `manual`), o `null` sin cobro: el de la primera
     * liquidación de clase «cobro» (hay UN cobro por pedido, `PAY-01`). La puerta lo dice junto a lo
     * pagado; nadie lo vuelve a deducir del proveedor (`DECISIONES #128`).
     */
    public function paymentMethod(): ?string
    {
        foreach ($this->settlements as $s) {
            if ($s->kind === Settlement::KIND_PAYMENT) {
                return $s->method;
            }
        }

        return null;
    }

    /** `Σ amount_cents` de las líneas de valor: la mitad izquierda de `I3`. */
    public function movementsSumCents(): int
    {
        return array_sum(array_map(static fn (Movement $m): int => $m->amountCents, $this->movements));
    }

    /** Lo liquidado en el parque, CON SIGNO: la Σ de las líneas `gate` (+ cobrado allí · − devuelto allí, D9 bis). */
    public function settledAtGateCents(): int
    {
        $sum = 0;
        foreach ($this->settlements as $s) {
            if ($s->kind === Settlement::KIND_GATE) {
                $sum += $s->amountCents;
            }
        }

        return $sum;
    }

    // ─── Composición ───────────────────────────────────────────────────────────────────────

    /**
     * Los HECHOS del pedido, una vez: por reserva y en conjunto. Los dos constructores públicos
     * leen de aquí, así que las identidades y las cifras salen de la misma pasada.
     *
     * @return array{
     *   currency:string, collected:bool, cancelled:bool, multi:bool, consistent:bool,
     *   cobrado:int, devuelto:int,
     *   payments:list<array{id:int, amount_cents:int, method:string, occurred_at:DateTimeInterface}>,
     *   refunds:list<array{id:int, amount_cents:int, status:string, method:string, order_item_id:?int, occurred_at:DateTimeInterface}>,
     *   reservations:array<int, array{
     *     name:string, lines:list<OrderItem>, line_ids:list<int>, movements:list<array<string,mixed>>,
     *     total:int, nac:int, online_nac:int, dev:int, liquidado:int, online_due:int,
     *     alive:bool, has_deposit:bool, principal:OrderItem
     *   }>
     * }
     */
    private static function compose(Order $order): array
    {
        $currency = (string) ($order->currency ?? 'EUR');
        // «Sin cobro no hay cobro» (`DECISIONES #127`): `paid_at` es el predicado, lo escriben los DOS
        // canales reales (pasarela y taquilla) y sobrevive a la cancelación y al reembolso.
        $collected = $order->paid_at !== null;
        // Un pedido cancelado no tiene valor vivo: sus líneas cuentan como canceladas aunque su
        // `cancelled_at` esté vacío (la segunda capa de `#127`).
        $orderCancelled = $order->status === Order::STATUS_CANCELLED;
        $rowsByLine = self::rowsByLine($order);

        $reservations = [];
        $principals = $order->items->whereNull('parent_item_id')->sortBy('id')->values();
        foreach ($principals as $principal) {
            $reservations[(int) $principal->id] = self::reservation($order, $principal, $rowsByLine, $collected, $orderCancelled, $currency);
        }

        $payments = $order->collectedPaymentFacts();
        $refunds = $order->refundFacts();
        $cobrado = array_sum(array_column($payments, 'amount_cents'));
        $devuelto = 0;
        foreach ($refunds as $f) {
            if ($f['status'] === Settlement::STATUS_SUCCEEDED) {
                $devuelto += $f['amount_cents'];
            }
        }

        // Las CUATRO identidades. `I3` suma las líneas de valor tal como se publican —con el
        // nacimiento del PEDIDO, `Order.total`—: si una clase de movimiento faltara, o `Order.total`
        // no fuera lo que las líneas dicen que nació, esto no cierra.
        $total = array_sum(array_column($reservations, 'total'));
        $valueLines = (int) $order->total;
        $onlineNac = 0;
        foreach ($reservations as $r) {
            $valueLines += array_sum(array_column($r['movements'], 'amount_cents'));
            $onlineNac += $r['online_nac'];
        }
        $consistent = (int) $order->total === $order->birthValueCents()                       // I1
            && $cobrado === $onlineNac                                                        // I2
            && ($order->status !== Order::STATUS_PAID || $collected)                          // I2 · un `paid` sin cobro
            && $total === $valueLines                                                         // I3
            && (int) ($order->refund_amount_cents ?? 0) === $devuelto;                        // I4

        return [
            'currency' => $currency,
            'collected' => $collected,
            'cancelled' => $orderCancelled,
            'multi' => count($reservations) > 1,
            'consistent' => $consistent,
            'cobrado' => $cobrado,
            'devuelto' => $devuelto,
            'value_lines' => $valueLines,
            'payments' => $payments,
            'refunds' => $refunds,
            'reservations' => $reservations,
        ];
    }

    /**
     * Los hechos de UNA reserva: sus líneas de valor (sin el nacimiento, que pone cada constructor)
     * y sus sumas.
     *
     * @param  array<int, list<OrderAdjustment>>  $rowsByLine
     * @return array<string,mixed>
     */
    private static function reservation(Order $order, OrderItem $principal, array $rowsByLine, bool $collected, bool $orderCancelled, string $currency): array
    {
        $lines = [$principal, ...$order->items->where('parent_item_id', $principal->id)->sortBy('id')->values()->all()];
        $principalCancelled = $principal->isCancelled() || $orderCancelled;
        $finished = $principal->isFinishedInPractice();

        $movements = [];
        $total = 0;
        $nac = 0;
        $onlineNac = 0;
        $dev = 0;
        $onlineDue = 0;
        $hasDeposit = false;
        $lineIds = [];

        foreach ($lines as $line) {
            $lineIds[] = (int) $line->id;
            $facts = LineFacts::forItem($order, $line);
            // Un complemento HEREDA la cancelación de su principal (el editor los cancela en cascada;
            // esto lo hace cierto también sobre filas anteriores a la cascada).
            $cancelled = $line->isCancelled() || $principalCancelled;
            $courtesy = 0;

            foreach ($rowsByLine[(int) $line->id] ?? [] as $row) {
                $amount = (int) $row->amount_cents;
                if ($row->isDepositSplit()) {
                    if ($amount > 0 && ! $cancelled) {
                        $hasDeposit = true;
                    }

                    continue; // reparte, no mueve (spec §4.2)
                }
                if ($amount === 0) {
                    continue; // una línea de 0 no dice nada
                }
                if ($row->isCourtesy()) {
                    $courtesy += $amount;
                    // El MOTIVO viaja en la línea solo para el panel (D-T4·1): `LedgerResource` no lo transcribe.
                    $context = is_array($row->context) ? $row->context : [];
                    $note = is_string($context['note'] ?? null) && $context['note'] !== '' ? $context['note'] : null;
                    $movements[] = self::movement(Movement::KIND_COURTESY, MovementLabel::courtesy(), $amount, $row->created_at, (int) $principal->id, rank: 1, seq: (int) $row->id, note: $note);
                } elseif ($row->isMixed()) {
                    // La línea VIVA: su fecha es la del último importe que se le escribió, no la del
                    // primero — es lo que hoy vale y desde cuándo (su historia, en «Ver historial»).
                    $movements[] = self::movement(Movement::KIND_MIXED, MovementLabel::mixed($row), $amount, $row->updated_at ?? $row->created_at, (int) $principal->id, rank: 1, seq: (int) $row->id);
                } elseif ($row->isEdit()) {
                    $movements[] = self::movement(Movement::KIND_EDIT, MovementLabel::edit($row, $line, $currency), $amount, $row->created_at, (int) $principal->id, rank: 1, seq: (int) $row->id);
                }
            }

            $fila = $line->chargedSubtotalCents();
            if ($cancelled && $fila + $courtesy === 0) {
                // Una línea fantasma (cancelada sin valor: el complemento a 0 € de un cambio de menú)
                // no retira nada, y una línea de 0 no dice nada — nombrarla solo enseña un producto
                // que ninguna otra superficie enseña (`Order::isVoidedLeftoverItem`).
            } elseif ($cancelled) {
                // Lo que la cancelación retira: la línea CON su cortesía, que se extingue con ella. La
                // fecha es la de la cancelación de la línea; si un pedido cancelado dejó una línea sin
                // la suya (filas anteriores a la cascada), la del pedido.
                $movements[] = self::movement(
                    Movement::KIND_CANCEL,
                    MovementLabel::cancel($line),
                    -($fila + $courtesy),
                    $line->cancelled_at ?? $order->refunded_at ?? $order->updated_at,
                    (int) $principal->id,
                    rank: 2,
                    seq: (int) $line->id,
                );
            } else {
                $total += $fila + $courtesy;
                $onlineDue += $facts->onlineNow();
            }

            $nac += $facts->birthValue();
            $onlineNac += $collected ? $facts->onlineAtBirth() : 0;
            $dev += $order->itemRefundedCents($line);
        }

        // La liquidación implícita, SIMÉTRICA (D9 de la T5 + D9 bis de la T4 del libro, `DECISIONES
        // #316`): franja pasada, pedido cobrado y principal vivo. Lo que quedara por pagar al terminar la
        // visita se dio por cobrado en recepción; lo que quedara por devolver, por entregado allí. Con
        // signo: positivo «Liquidado», negativo «Devuelto» en el parque. ⚠️ Hasta la T4 iba con
        // `max(0, …)` y una bajada cuya visita ya pasó quedaba «pendiente de devolución» para siempre,
        // exigiendo una acción del operador que el owner no quiere exigir.
        $resolved = $collected && ! $principalCancelled && $finished;
        $liquidado = $resolved ? $total - ($onlineNac - $dev) : 0;

        return [
            'principal' => $principal,
            'name' => (string) ($principal->ticketType?->tr('name') ?? '—'),
            'lines' => $lines,
            'line_ids' => $lineIds,
            'movements' => $movements,
            'total' => $total,
            'nac' => $nac,
            'online_nac' => $onlineNac,
            'dev' => $dev,
            'liquidado' => $liquidado,
            'online_due' => $onlineDue,
            'alive' => ! $principalCancelled && ! $finished,
            'has_deposit' => $hasDeposit,
        ];
    }

    /**
     * Las filas de hechos POR LÍNEA, en el orden en que ocurrieron (`created_at`, `id`): dos
     * gestiones pueden caer en el mismo segundo y el orden de una relación sin `orderBy` no es un
     * contrato.
     *
     * @return array<int, list<OrderAdjustment>>
     */
    private static function rowsByLine(Order $order): array
    {
        $rows = $order->adjustments->all();
        usort($rows, static function (OrderAdjustment $a, OrderAdjustment $b): int {
            $ta = $a->created_at?->getTimestamp() ?? 0;
            $tb = $b->created_at?->getTimestamp() ?? 0;

            return $ta <=> $tb ?: (int) $a->id <=> (int) $b->id;
        });

        $byLine = [];
        foreach ($rows as $row) {
            if ($row->order_item_id === null) {
                continue;
            }
            $byLine[(int) $row->order_item_id][] = $row;
        }

        return $byLine;
    }

    /**
     * La parte de un reembolso SIN línea que le toca a un conjunto de líneas: la prorrata de
     * {@see Order::unattributedRefundShareFor}, línea a línea.
     *
     * @param  list<OrderItem>  $lines
     */
    private static function unattributedShareForLines(Order $order, array $lines, int $unattributed): int
    {
        $share = 0;
        foreach ($lines as $line) {
            $share += $order->unattributedRefundShareFor($line, $unattributed);
        }

        return $share;
    }

    // ─── El saldo y su frase ───────────────────────────────────────────────────────────────

    /**
     * Las reglas de `balance.kind` (spec §4.4), en el orden en que mandan: primero que las
     * identidades cierren, después si hubo cobro, y solo entonces el signo del saldo.
     */
    private static function balance(Order $order, bool $collected, bool $consistent, int $total, int $paid, bool $alive, int $onlineDue): Balance
    {
        if (! $consistent) {
            return new Balance(Balance::KIND_UNDER_REVIEW, 0);
        }

        if (! $collected) {
            if ($order->displayStatus() === Order::STATUS_EXPIRED) {
                return new Balance(Balance::KIND_EXPIRED, 0);
            }
            if ($order->status === Order::STATUS_PENDING) {
                return new Balance(Balance::KIND_PAY_ONLINE, $onlineDue, max(0, $total - $onlineDue));
            }

            // Cancelado antes de cobrarse: nada entró y nada se debe.
            return new Balance(Balance::KIND_SETTLED, 0);
        }

        $saldo = $total - $paid;
        if ($saldo > 0) {
            return new Balance(Balance::KIND_PAY_AT_PARK, $saldo);
        }
        if ($saldo < 0) {
            return new Balance($alive ? Balance::KIND_REFUND_AT_PARK : Balance::KIND_REFUND_PENDING, $saldo);
        }

        return new Balance(Balance::KIND_SETTLED, 0);
    }

    /** Las TRES frases que sobreviven al modelo de dos ejes (spec §4.3), con su voz de hoy. */
    private static function note(Balance $balance, string $currency): ?string
    {
        return match ($balance->kind) {
            Balance::KIND_UNDER_REVIEW => __('tickets.ledger_note.under_review'),
            Balance::KIND_EXPIRED => __('tickets.ledger_note.expired'),
            Balance::KIND_PAY_ONLINE => __('tickets.ledger_note.pending_payment', ['amount' => Money::format($balance->cents, $currency)]),
            default => null,
        };
    }

    // ─── Constructores de líneas y orden ───────────────────────────────────────────────────

    /** @return array<string,mixed> una línea de valor todavía sin ordenar */
    private static function movement(string $kind, string $label, int $amountCents, DateTimeInterface|string|null $at, ?int $reservationId, int $rank, int $seq, ?string $note = null): array
    {
        $when = self::instant($at);

        return [
            'kind' => $kind,
            'label' => $label,
            'amount_cents' => $amountCents,
            'at' => $when,
            'reservation_id' => $reservationId,
            'rank' => $rank,
            'seq' => $seq,
            'note' => $note,
        ];
    }

    /**
     * Cronológico ascendente; en el mismo instante, primero el nacimiento, después los hechos por
     * su `id` y al final la cancelación —un reembolso con «también cancelar» escribe la cortesía y
     * la cancelación en el mismo segundo, y la cancelación se lee después de lo que se lleva—.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return list<Movement>
     */
    private static function sortedMovements(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => $a['at']->getTimestamp() <=> $b['at']->getTimestamp()
            ?: $a['rank'] <=> $b['rank']
            ?: $a['seq'] <=> $b['seq']);

        return array_map(static fn (array $m): Movement => new Movement(
            kind: $m['kind'],
            label: $m['label'],
            amountCents: $m['amount_cents'],
            occurredAt: $m['at']->toIso8601String(),
            occurredLabel: DisplayTime::format($m['at'], 'd/m/Y'),
            reservationId: $m['reservation_id'],
            note: $m['note'] ?? null,
        ), $rows);
    }

    /** @return array<string,mixed> */
    private static function settlement(string $kind, string $label, int $amountCents, DateTimeInterface|string|null $at, string $status, ?string $method, int $seq, ?string $occurredLabel = null): array
    {
        $when = self::instant($at);

        return [
            'kind' => $kind,
            'label' => $label,
            'amount_cents' => $amountCents,
            'at' => $when,
            'occurred_label' => $occurredLabel ?? DisplayTime::format($when, 'd/m/Y'),
            'status' => $status,
            'method' => $method,
            'seq' => $seq,
        ];
    }

    /**
     * @param  array{id:int, amount_cents:int, status:string, method:string, order_item_id:?int, occurred_at:DateTimeInterface}  $fact
     * @return array<string,mixed>
     */
    private static function refundSettlement(array $fact, int $amountCents): array
    {
        return self::settlement(Settlement::KIND_REFUND, MovementLabel::refund($fact['method'], $fact['status']), -$amountCents, $fact['occurred_at'], $fact['status'], $fact['method'], $fact['id']);
    }

    /**
     * La liquidación, fechada al FIN de la franja del principal, con el rótulo de su SIGNO: lo que
     * quedaba por pagar, «Liquidado en el parque»; lo que quedaba por devolver, «Devuelto en el
     * parque» (D9 bis). La etiqueta de fecha es la fecha CIVIL de la franja: convertir un día de
     * calendario a la zona del parque lo desplazaría (`DisplayTime::dayLabel`).
     *
     * @param  array<string,mixed>  $r
     * @return array<string,mixed>
     */
    private static function gateSettlement(array $r): array
    {
        /** @var OrderItem $principal */
        $principal = $r['principal'];
        $slot = $principal->slot;
        $end = CarbonImmutable::parse($slot->date->format('Y-m-d').' '.$slot->end_time);
        $label = $r['liquidado'] < 0 ? MovementLabel::gateRefund() : MovementLabel::gate();

        return self::settlement(Settlement::KIND_GATE, $label, $r['liquidado'], $end, Settlement::STATUS_SUCCEEDED, null, (int) $principal->id, $slot->date->format('d/m/Y'));
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return list<Settlement>
     */
    private static function sortedSettlements(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => $a['at']->getTimestamp() <=> $b['at']->getTimestamp()
            ?: $a['seq'] <=> $b['seq']);

        return array_map(static fn (array $s): Settlement => new Settlement(
            kind: $s['kind'],
            label: $s['label'],
            amountCents: $s['amount_cents'],
            occurredAt: $s['at']->toIso8601String(),
            occurredLabel: $s['occurred_label'],
            status: $s['status'],
            method: $s['method'],
        ), $rows);
    }

    /** Un instante inmutable a partir de lo que traiga la fila; sin fecha, el origen de los tiempos (y se nota). */
    private static function instant(DateTimeInterface|string|null $at): CarbonImmutable
    {
        if ($at instanceof DateTimeInterface) {
            return CarbonImmutable::instance($at);
        }
        if (is_string($at) && $at !== '') {
            return CarbonImmutable::parse($at);
        }

        return CarbonImmutable::createFromTimestampUTC(0);
    }
}
