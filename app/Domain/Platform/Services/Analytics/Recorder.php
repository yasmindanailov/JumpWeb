<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\AnalyticsEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **LOS HECHOS DEL SERVIDOR** (`docs/specs/analitica.md` §4.1): quien sabe que ocurrió algo lo dice aquí con
 * ESCALARES —ids e importes—, nunca con un modelo.
 *
 * ⚠️⚠️ **Nunca tumba a quien lo llama.** El pago, el alta, el correo: ninguno puede fallar porque la
 * analítica falle. Por eso {@see fact()} difiere la escritura a después del commit de la transacción en
 * curso (`DB::afterCommit`: fuera del lock de un pago, `PAY-05` corolario) y **traga cualquier error dentro
 * del callback** con un `Log`. Lo segundo no es redundante con lo primero: un callback `afterCommit` que
 * lanza propaga a la petición con el pago YA confirmado (spec §7.1, dinero-5), que es peor que perder un
 * evento. Sin transacción abierta, `afterCommit` corre en el acto.
 *
 * ⚠️ Solo admite nombres de SERVIDOR del contrato: un hecho de cliente («drawer_opened») escrito desde
 * PHP sería una manera de saltarse la ingesta y su validación.
 *
 * ⚠️ El visitante y la sesión se cogen del contexto de la petición SOLO si el visitante consintió
 * `analytics`: atar un hecho con `user_id` a la navegación de una cookie es el régimen identificado.
 */
class Recorder
{
    public function __construct(private readonly AttributionContext $context) {}

    /**
     * @param  array<string, scalar|null>  $props  solo las claves que el contrato permite para `$name`
     * @param  array{order_id?: ?int, payment_id?: ?int, refund_id?: ?int, user_id?: ?int, visitor_id?: ?string, session_id?: ?int}  $refs
     */
    public function fact(string $name, array $props = [], array $refs = []): void
    {
        if (! Contract::isServer($name)) {
            Log::warning('analytics.record_refused', ['event' => $name, 'reason' => 'not a server fact']);

            return;
        }

        $this->write($name, $this->allowed($name, $props), [
            'session_id' => $refs['session_id'] ?? ($this->linksToVisitor() ? $this->context->sessionId() : null),
            'visitor_id' => $refs['visitor_id'] ?? ($this->linksToVisitor() ? $this->context->visitorId() : null),
            'user_id' => $refs['user_id'] ?? null,
            'order_id' => $refs['order_id'] ?? null,
            'payment_id' => $refs['payment_id'] ?? null,
            'refund_id' => $refs['refund_id'] ?? null,
        ]);
    }

    /**
     * **Un hecho de la RESERVA** (`docs/specs/analitica-fiesta.md` §4.1): lo que hace un invitado en el post-form,
     * el justificante o la invitación se anota contra el PEDIDO y nada más —sin visitante, sin sesión y sin
     * titular—, porque **el invitado no es un visitante**: en esas páginas no hay cookie de medición.
     *
     * ⚠️ No mira el contexto de atribución A PROPÓSITO: aunque el navegador trajera la cookie de otra visita, el
     * hecho no se ata a ella. Un invitado que vino antes a la web y otro que no, iguales en el libro.
     *
     * @param  array<string, scalar|null>  $props  solo las claves que el contrato permite para `$name`
     */
    public function factOfOrder(string $name, int $orderId, array $props = []): void
    {
        if (! Contract::isServer($name)) {
            Log::warning('analytics.record_refused', ['event' => $name, 'reason' => 'not a server fact']);

            return;
        }

        $this->write($name, $this->allowed($name, $props), [
            'session_id' => null,
            'visitor_id' => null,
            'user_id' => null,
            'order_id' => $orderId,
            'payment_id' => null,
            'refund_id' => null,
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $props
     * @return array<string, scalar>
     */
    private function allowed(string $name, array $props): array
    {
        $allowed = array_flip(Contract::allowedProps($name));

        return array_filter(array_intersect_key($props, $allowed), static fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, scalar>  $props
     * @param  array{session_id: ?int, visitor_id: ?string, user_id: ?int, order_id: ?int, payment_id: ?int, refund_id: ?int}  $refs
     */
    private function write(string $name, array $props, array $refs): void
    {
        $row = [
            'event_id' => Visitor::mint(),
            'session_id' => $refs['session_id'],
            'visitor_id' => $refs['visitor_id'],
            'user_id' => $refs['user_id'],
            'name' => $name,
            'route' => null,
            'props' => $props === [] ? null : $props,
            'occurred_at' => now(),
            'received_at' => now(),
            'order_id' => $refs['order_id'],
            'payment_id' => $refs['payment_id'],
            'refund_id' => $refs['refund_id'],
        ];

        DB::afterCommit(static function () use ($row): void {
            try {
                AnalyticsEvent::query()->create($row);
            } catch (Throwable $e) {
                Log::warning('analytics.record_failed', ['event' => $row['name'], 'error' => $e->getMessage()]);
            }
        });
    }

    private function linksToVisitor(): bool
    {
        try {
            return $this->context->consented('analytics');
        } catch (Throwable $e) {
            Log::warning('analytics.context_failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
