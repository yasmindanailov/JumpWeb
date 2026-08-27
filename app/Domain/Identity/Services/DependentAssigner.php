<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\CheckoutLine;
use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Identity\Contracts\AssignmentOutcome;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fase 6 · menores a cargo, tanda 4 — el ÚNICO escritor de `dependent_assignments`: qué entradas de un
 * pedido son para qué menores (`docs/specs/menores-a-cargo.md` §4.7–§4.10, §9.9.3 D3; `DECISIONES #202`).
 *
 * **Dos fases, y la primera va ANTES del dinero** (D3):
 *  - {@see check()} — sin lock, antes de `ReservationCheckout::start()`. Cualquier id que no pueda
 *    asignarse se devuelve por CAMPO (`items.{i}.dependent_ids.{j}`) para que el cliente lo enseñe en
 *    su línea, y el pedido NO se crea ni se consume la ficha de admisión: fail-closed.
 *  - {@see assign()} — DESPUÉS de que el pedido exista y su cobro se haya abierto, bajo el lock de la
 *    fila del titular (**el mismo que `DependentRegistry::remove()` y `WaiverSigner`**: una retirada
 *    concurrente no se cruza con una asignación), re-validando las mismas reglas —el estado pudo
 *    cambiar entre las dos fases— y escribiendo idempotente. Si falla, **el pedido sigue en pie** y
 *    la línea se queda sin asignar (§4.10): es una etiqueta de puerta, no dinero.
 *
 * **Las reglas, las mismas en las dos fases** (`[DECIDIDO owner]` `#202`·2 y D3/D13):
 *  - el menor es SUYO y está ACTIVO — ajeno, inexistente y retirado responden igual (§4.9, anti-IDOR);
 *  - es MENOR EN LA FECHA DE LA VISITA, no hoy: un menor de 17 años y 364 días visita como adulto;
 *  - tiene la exención FIRMADA Y VIGENTE cuando el waiver se gestiona dentro (`interno`): «un menor no
 *    se puede asignar si no firma la exención; sin eso, asignar menores no sirve de nada». Fuera del
 *    modo interno no existe firma que comprobar y la regla no aplica (reversible, spec §9.9.2·2);
 *  - nunca más menores que unidades, y solo en ENTRADAS (§4.7: los packs ya piden a sus invitados).
 *
 * **La correlación cesta ↔ ítem la promete Booking** ({@see CheckoutLines}, D2): esta clase no adivina
 * nada por posición; si el pedido no tiene exactamente las líneas que la cesta tenía, no escribe NADA y
 * lo dice en el log — mejor sin asignar que asignado a otra línea.
 *
 * `RGPD-02`: la auditoría lleva ids, nunca el nombre. Y esta clase NO es dinero ni aforo: está
 * declarada control negativo en `CriticalPathGateTest`.
 */
final class DependentAssigner
{
    public const REASON_NOT_YOURS = 'not_yours';

    public const REASON_NOT_MINOR_ON_DATE = 'not_minor_on_date';

    public const REASON_WAIVER_UNSIGNED = 'waiver_unsigned';

    public const REASON_TOO_MANY = 'too_many';

    public const REASON_ENTRIES_ONLY = 'entries_only';

    public function __construct(
        private readonly CheckoutLines $lines,
        private readonly ProductCatalog $catalog,
    ) {}

    /**
     * FASE 1 — antes del dinero. Devuelve los rechazos por campo, ya traducidos; vacío = todo asignable.
     *
     * @param  list<array{index:int, product_id:int, date:string, quantity:int, dependent_ids:list<int>}>  $requested  TODAS las líneas de la cesta (las sin ids se ignoran)
     * @return array<string, list<string>>
     */
    public function check(User $holder, array $requested): array
    {
        $withIds = self::withIds($requested);
        if ($withIds === []) {
            return [];
        }

        $dependents = $this->dependentsOf($holder, self::allIds($withIds));
        $statuses = [];
        $errors = [];

        foreach ($withIds as $line) {
            $key = "items.{$line['index']}.dependent_ids";

            // Solo entradas (§4.7). Un producto que el catálogo no ofrece no es cosa de esta clase:
            // `OrderCreator` lo rechazará con su propio código de negocio.
            $product = $this->catalog->product((int) $line['product_id']);
            if ($product !== null && $product->product->isPack()) {
                $errors[$key][] = __('api.dependents.'.self::REASON_ENTRIES_ONLY);

                continue;
            }

            foreach ($this->rejections($line['dependent_ids'], (int) $line['quantity'], (string) $line['date'], $dependents, $statuses) as $field => $reason) {
                $errors[$field === '' ? $key : "{$key}.{$field}"][] = __('api.dependents.'.$reason);
            }
        }

        return $errors;
    }

    /**
     * FASE 2 — después del `allow`. Idempotente; nunca lanza: un fallo deja el pedido en pie.
     *
     * @param  list<array{index:int, product_id:int, date:string, quantity:int, dependent_ids:list<int>}>  $requested  TODAS las líneas de la cesta, en su orden
     */
    public function assign(User $holder, int $orderId, array $requested): AssignmentOutcome
    {
        $withIds = self::withIds($requested);
        if ($withIds === []) {
            return AssignmentOutcome::nothingRequested();
        }
        $requestedIds = count(self::allIds($withIds, unique: false));

        try {
            return DB::transaction(function () use ($holder, $orderId, $requested, $withIds, $requestedIds): AssignmentOutcome {
                // ⚠️ PRIMERA sentencia de la transacción: el lock de la fila del titular. Es el punto de
                // serialización de todo lo que toca a sus menores (`DependentRegistry`, `WaiverSigner`).
                $locked = User::query()->whereKey($holder->getKey())->lockForUpdate()->firstOrFail();

                $lines = $this->lines->forOrder($orderId, (int) $locked->getKey());

                // La guarda de correlación (D2): el pedido tiene que tener EXACTAMENTE las líneas que
                // la cesta tenía. Si no, nadie sabe qué ítem es qué línea y no se escribe nada.
                if (count($lines) !== count($requested)) {
                    Log::warning('dependents.assign_skipped', [
                        'order_id' => $orderId, 'reason' => 'line_mismatch',
                        'cart_lines' => count($requested), 'order_lines' => count($lines),
                    ]);

                    return AssignmentOutcome::aborted('line_mismatch', $requestedIds);
                }

                /** @var array<int, CheckoutLine> $byIndex */
                $byIndex = [];
                foreach ($lines as $line) {
                    $byIndex[$line->index] = $line;
                }

                $dependents = $this->dependentsOf($locked, self::allIds($withIds));
                $statuses = [];
                $assigned = 0;
                $skipped = 0;

                foreach ($withIds as $request) {
                    $line = $byIndex[$request['index']] ?? null;
                    if ($line === null || ! $line->isEntry) {
                        $skipped += count($request['dependent_ids']);
                        Log::warning('dependents.assign_skipped', ['order_id' => $orderId, 'index' => $request['index'], 'reason' => $line === null ? 'no_line' : self::REASON_ENTRIES_ONLY]);

                        continue;
                    }

                    $date = $line->date ?? DisplayTime::today()->toDateString();
                    $rejections = $this->rejections($request['dependent_ids'], $line->quantity, $date, $dependents, $statuses);

                    if (isset($rejections[''])) {
                        $skipped += count($request['dependent_ids']);
                        Log::warning('dependents.assign_skipped', ['order_id' => $orderId, 'index' => $request['index'], 'reason' => $rejections['']]);

                        continue;
                    }

                    foreach (array_values($request['dependent_ids']) as $j => $dependentId) {
                        if (isset($rejections[(string) $j])) {
                            $skipped++;
                            Log::warning('dependents.assign_skipped', ['order_id' => $orderId, 'index' => $request['index'], 'dependent_id' => $dependentId, 'reason' => $rejections[(string) $j]]);

                            continue;
                        }

                        // Idempotente por el único `(order_item_id, dependent_id)`: una segunda pasada
                        // no escribe ni audita dos veces.
                        $written = DependentAssignment::query()->insertOrIgnore([
                            'dependent_id' => $dependentId,
                            'order_item_id' => $line->orderItemId,
                            'created_at' => now(),
                        ]);

                        if ($written > 0) {
                            $assigned++;
                            AuditLogger::log('dependents.assigned', $locked, [
                                'dependent_id' => $dependentId,
                                'order_item_id' => $line->orderItemId,
                                'order_id' => $orderId,
                            ]);
                        }
                    }
                }

                return new AssignmentOutcome($assigned, $skipped);
            });
        } catch (Throwable $e) {
            // §4.10: el pedido ya existe y retiene aforo; esto es una etiqueta y no puede tirarlo.
            Log::warning('dependents.assign_failed', ['order_id' => $orderId, 'exception' => $e]);

            return AssignmentOutcome::aborted('failed', $requestedIds);
        }
    }

    /**
     * Lectura por pedido — la de `event-data` y la del export: `[order_item_id => list<Dependent>]`, en
     * orden de asignación, incluidas las personas ya desvinculadas (la reserva fue para ellas).
     *
     * ⚠️ La coherencia con la cantidad se DERIVA aquí (D4): si la línea bajó de unidades desde el
     * panel, se enseñan las primeras `quantity`; si subió, los huecos que faltan son adultos.
     *
     * @param  list<int>  $orderItemIds
     * @param  array<int, int>  $quantityByItem  unidades actuales por ítem; sin él no se recorta
     * @return array<int, list<Dependent>>
     */
    public function forOrderItems(array $orderItemIds, array $quantityByItem = []): array
    {
        if ($orderItemIds === []) {
            return [];
        }

        $byItem = [];
        DependentAssignment::query()
            ->whereIn('order_item_id', $orderItemIds)
            ->with('dependent')
            ->orderBy('id')
            ->get()
            ->each(function (DependentAssignment $assignment) use (&$byItem): void {
                if ($assignment->dependent !== null) {
                    $byItem[(int) $assignment->order_item_id][] = $assignment->dependent;
                }
            });

        foreach ($byItem as $itemId => $list) {
            if (isset($quantityByItem[$itemId])) {
                $byItem[$itemId] = array_slice($list, 0, max(0, (int) $quantityByItem[$itemId]));
            }
        }

        return $byItem;
    }

    // ─── Las reglas, escritas UNA vez ────────────────────────────────────────

    /**
     * Los rechazos de una línea: `''` para la línea entera, `"{j}"` para el id en esa posición.
     *
     * @param  list<int>  $ids
     * @param  Collection<int, Dependent>  $dependents  los del titular, activos, por id
     * @param  array<int, bool>  $statuses  caché de «firma vigente» por id, compartida entre líneas
     * @return array<string, string>
     */
    private function rejections(array $ids, int $quantity, string $date, Collection $dependents, array &$statuses): array
    {
        if (count($ids) > $quantity) {
            return ['' => self::REASON_TOO_MANY];
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC') ?: DisplayTime::today();
        $rejections = [];

        foreach (array_values($ids) as $j => $id) {
            /** @var Dependent|null $dependent */
            $dependent = $dependents->get((int) $id);

            if ($dependent === null) {
                $rejections[(string) $j] = self::REASON_NOT_YOURS;
            } elseif (! $dependent->isMinorOn($day)) {
                $rejections[(string) $j] = self::REASON_NOT_MINOR_ON_DATE;
            } elseif (! ($statuses[$dependent->getKey()] ??= self::hasCurrentWaiver($dependent))) {
                $rejections[(string) $j] = self::REASON_WAIVER_UNSIGNED;
            }
        }

        return $rejections;
    }

    /**
     * `[DECIDIDO owner]` (`#202`·2): en modo interno, firma VIGENTE o no se asigna. Fuera de ese modo no
     * hay firma que comprobar (`WaiverStatus::forDependent()` no pregunta) y la regla no aplica.
     */
    private static function hasCurrentWaiver(Dependent $dependent): bool
    {
        if (! WaiverSettings::isInternal()) {
            return true;
        }

        $status = WaiverStatus::forDependent($dependent);

        return $status->signed && ! $status->isOutdated();
    }

    /**
     * Las personas a cargo ACTIVAS del titular entre los ids pedidos, por id. Un id ajeno, inexistente o
     * retirado simplemente no está (§4.9).
     *
     * @param  list<int>  $ids
     * @return Collection<int, Dependent>
     */
    private function dependentsOf(User $holder, array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Dependent::query()
            ->where('user_id', $holder->getKey())
            ->active()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Dependent $dependent): int => (int) $dependent->getKey());
    }

    /**
     * Solo las líneas que piden algo, con sus ids como enteros.
     *
     * @param  list<array<string, mixed>>  $requested
     * @return list<array{index:int, product_id:int, date:string, quantity:int, dependent_ids:list<int>}>
     */
    private static function withIds(array $requested): array
    {
        $lines = [];
        foreach ($requested as $line) {
            $ids = array_values(array_map('intval', is_array($line['dependent_ids'] ?? null) ? $line['dependent_ids'] : []));
            if ($ids === []) {
                continue;
            }
            $lines[] = [
                'index' => (int) ($line['index'] ?? 0),
                'product_id' => (int) ($line['product_id'] ?? 0),
                'date' => (string) ($line['date'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'dependent_ids' => $ids,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{dependent_ids:list<int>}>  $lines
     * @return list<int>
     */
    private static function allIds(array $lines, bool $unique = true): array
    {
        $ids = array_merge(...array_map(static fn (array $line): array => $line['dependent_ids'], $lines ?: [[]]));

        return $unique ? array_values(array_unique($ids)) : array_values($ids);
    }
}
