<?php

namespace App\Domain\Booking\Observers;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Platform\Services\Analytics\AccountLinker;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Recorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **EL SELLO DE ORIGEN Y LOS HECHOS DE UN PEDIDO** (`docs/specs/analitica.md` §4.1; `DECISIONES #678`).
 *
 * Dos trabajos, y los dos sin tocar `OrderCreator`, `CheckoutOrchestrator` ni `RedsysReturnHandler`
 * (`CRITICAL_RE`): se escucha al MODELO, no al orquestador.
 *
 * 1. **El sello, en `creating`**: el contexto de la petición (`AttributionContext`) se copia en las columnas
 *    de atribución ANTES del INSERT. Cero consultas si el controlador resolvió el contexto antes de entrar
 *    en el dominio, cero UPDATE bajo el lock de aforo, y si el contexto falla el pedido nace igual, sin
 *    sello y con rastro (`analytics.seal_failed`). ⚠️ Un `created` diferido sería un UPDATE tardío con una
 *    ventana sin sello, y en el pedido manual correría con el pedido ya pagado (spec §7.1, dinero-7).
 *
 * 2. **Las transiciones, capturadas en `saving`**: cuando corre un callback diferido el modelo ya está
 *    sincronizado —`getOriginal('status')` vale el estado nuevo— y dos `save()` en una transacción (reembolsar
 *    y cancelar) dejarían un hecho sin registrar y otro doble (dinero-1). Por eso el `de → a` se lee en el
 *    evento SÍNCRONO, como escalares, y se difiere sólo la escritura.
 *
 * ⚠️ **`paid` no siempre es una compra**: la rama de incidencia de `PAY-02` (cobro tardío sin tickets)
 * escribe exactamente el mismo `forceFill`. Se distingue tras el commit por los tickets emitidos
 * (dinero-6): con tickets, `order_paid`; sin ellos, `order_paid_incident`. Y el único `save()` a `expired`
 * es `releaseAfterFailedPaymentStart()`, un fallo nuestro al abrir el cobro, no un abandono
 * (`order_payment_init_failed`); la caducidad real la escribe `ExpireOrders` por UPDATE y la registra él.
 */
final class OrderAnalyticsObserver
{
    /** @var array<int, array{from: string, to: string}> transiciones capturadas en `saving`, por instancia */
    private array $transitions = [];

    /**
     * ⚠️ El contexto y el recorder se resuelven AL USARSE, no en el constructor: `Order::observe()`
     * instancia el observador en el arranque, y una dependencia inyectada entonces sería la misma para
     * toda la vida del proceso — un test que sustituye el contexto en el contenedor no la vería, y el
     * `scoped` de la petición tampoco.
     */
    private function context(): AttributionContext
    {
        return app(AttributionContext::class);
    }

    private function recorder(): Recorder
    {
        return app(Recorder::class);
    }

    public function creating(Order $order): void
    {
        if ($order->getAttribute('attribution_channel') !== null) {
            return; // ya sellado por quien lo creó (fixtures, importaciones): un sello no se pisa.
        }

        try {
            foreach ($this->context()->seal() as $column => $value) {
                $order->setAttribute($column, $value);
            }
        } catch (Throwable $e) {
            Log::warning('analytics.seal_failed', ['error' => $e->getMessage()]);
        }
    }

    public function saving(Order $order): void
    {
        if (! $order->exists || ! $order->isDirty('status')) {
            return;
        }

        $this->transitions[spl_object_id($order)] = [
            'from' => (string) $order->getOriginal('status'),
            'to' => (string) $order->status,
        ];
    }

    public function created(Order $order): void
    {
        $this->recorder()->fact('order_created', [
            'total_cents' => (int) $order->total,
            'channel' => $order->getAttribute('attribution_channel'),
        ], $this->refs($order));
    }

    public function saved(Order $order): void
    {
        $key = spl_object_id($order);
        $transition = $this->transitions[$key] ?? null;
        unset($this->transitions[$key]);

        if ($transition === null || $transition['from'] === $transition['to']) {
            return;
        }

        $refs = $this->refs($order);
        $channel = $order->getAttribute('attribution_channel');
        $orderId = (int) $order->id;
        $totalCents = (int) $order->total;
        // El régimen IDENTIFICADO (`specs/analitica.md` §4.3, T3a·3): «al identificarse O COMPRAR». La oposición
        // de la cuenta se lee aquí —`User` es kernel compartido— y viaja al enlazador de Platform por parámetro.
        $userId = (int) $order->user_id;
        $optedOut = (bool) ($order->user?->getAttribute('analytics_opt_out') ?? false);

        switch ($transition['to']) {
            case Order::STATUS_PAID:
                // Con o sin tickets solo se sabe tras el commit: `TicketIssuer` corre después del `save()`.
                DB::afterCommit(function () use ($orderId, $totalCents, $channel, $refs, $userId, $optedOut): void {
                    try {
                        $fulfilled = Ticket::query()->where('order_id', $orderId)->exists();
                        $paidCents = $this->paidCents($orderId);
                    } catch (Throwable $e) {
                        Log::warning('analytics.record_failed', ['event' => 'order_paid', 'error' => $e->getMessage()]);

                        return;
                    }

                    $fulfilled
                        ? $this->recorder()->fact('order_paid', ['paid_cents' => $paidCents, 'total_cents' => $totalCents, 'channel' => $channel], $refs)
                        : $this->recorder()->fact('order_paid_incident', ['kind' => 'late_capture', 'paid_cents' => $paidCents, 'channel' => $channel], $refs);

                    // Y la navegación que trajo la compra se ata a la cuenta, solo con la categoría `analytics`
                    // de esta petición y sin la oposición de la cuenta. Nunca tumba el cobro.
                    if ($userId > 0) {
                        try {
                            app(AccountLinker::class)->link($userId, $this->context(), $optedOut);
                        } catch (Throwable $e) {
                            Log::warning('analytics.account_link_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                        }
                    }
                });
                break;
            case Order::STATUS_CANCELLED:
                $this->recorder()->fact('order_cancelled', ['channel' => $channel], $refs);
                break;
            case Order::STATUS_EXPIRED:
                $this->recorder()->fact('order_payment_init_failed', ['channel' => $channel], $refs);
                break;
        }
    }

    /**
     * Lo que entró por la pasarela para este pedido: el último cobro confirmado. Se lee por la relación
     * del pedido, sin nombrar al modelo de Payments (la costura Booking↔Payments es de `Order`).
     */
    private function paidCents(int $orderId): int
    {
        $order = Order::query()->find($orderId);

        if ($order === null) {
            return 0;
        }

        $payment = $order->payments()
            ->whereIn('status', ['paid', 'authorized'])
            ->orderByDesc('id')
            ->first();

        return (int) ($payment?->getAttribute('amount') ?? 0);
    }

    /**
     * Las referencias del hecho: el pedido siempre; visitante, sesión y usuario solo si el sello los
     * lleva, y el sello solo los lleva con la categoría `analytics` consentida (régimen identificado).
     *
     * @return array{order_id: int, visitor_id: ?string, session_id: ?int, user_id: ?int}
     */
    private function refs(Order $order): array
    {
        $seal = $order->getAttribute('attribution');
        $seal = is_string($seal) ? (array) json_decode($seal, true) : (is_array($seal) ? $seal : []);
        $visitorId = isset($seal['visitor_id']) && is_string($seal['visitor_id']) ? $seal['visitor_id'] : null;

        return [
            'order_id' => (int) $order->id,
            'visitor_id' => $visitorId,
            'session_id' => isset($seal['session_id']) && is_int($seal['session_id']) ? $seal['session_id'] : null,
            'user_id' => $visitorId === null ? null : (int) $order->user_id,
        ];
    }
}
