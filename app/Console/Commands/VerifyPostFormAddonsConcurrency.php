<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * **Las DOS carreras de los complementos de venta posterior** (`specs/complementos-post-reserva.md`
 * §6·5 y §6·6, T2 de `DECISIONES #413`). Dev/local: crea y borra datos.
 *
 * La suite corre en SQLite y **no reproduce los locks de InnoDB** (`SUITE-04`), así que estas dos
 * propiedades solo se pueden medir aquí:
 *
 *  · **`addons`** — N guardados simultáneos del MISMO post-form escriben **UNA** línea, no N. Es la
 *    carrera que `MixedPartySurcharge` ya tenía, sobre el otro escritor del mismo formulario.
 *
 *  · **`cross`** — un guardado del CLIENTE contra una cancelación o un reembolso del OPERADOR **no
 *    produce ningún interbloqueo**. ⚠️⚠️ **Ningún verificador del repo cruzaba dos caminos
 *    distintos**: `purchase:verify-oversell` forkea N compras o N ediciones, `mixed-party` N
 *    guardados y `redsys` N notificaciones — todos, N copias del MISMO actor. Y el interbloqueo que
 *    la revisión adversarial reprodujo solo aparece cruzándolos.
 *
 * ⚠️ **El escenario `cross` trae su CONTROL NEGATIVO** (`--control`): con él, el «cliente» toma los
 * locks en el orden VIEJO (`order_items` → `orders`) en vez del que D11 fija, y el interbloqueo
 * aparece. Un verde que no se ha visto en rojo no dice nada — y aquí el rojo hay que fabricarlo
 * fuera del producto, porque el orden correcto ya está dentro.
 */
class VerifyPostFormAddonsConcurrency extends Command
{
    protected $signature = 'postform:verify-concurrency
        {--workers=8 : Nº de procesos simultáneos}
        {--scenario=addons : addons (N guardados del mismo formulario) | cross (cliente contra operador)}
        {--control : Solo en `cross`: invierte el orden de locks del cliente para VER el interbloqueo}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica sobre MySQL real que N guardados del post-form escriben UNA línea de complemento, y que un guardado del cliente contra una cancelación del operador no se interbloquea. Solo dev/local.';

    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Abortado: NO ejecutar en producción (crea y borra datos).');

            return self::FAILURE;
        }
        if (! function_exists('pcntl_fork')) {
            $this->error('Necesita la extensión pcntl (solo CLI de dev).');

            return self::FAILURE;
        }

        $scenario = (string) $this->option('scenario');
        if (! in_array($scenario, ['addons', 'cross'], true)) {
            $this->error("Escenario desconocido «{$scenario}»: addons | cross.");

            return self::FAILURE;
        }

        $workers = max(2, (int) $this->option('workers'));
        $seed = $this->seed();
        $dir = storage_path('app/postform-verify-'.Str::random(8));
        File::ensureDirectoryExists($dir);

        if (! $this->guardTheInstrument($seed)) {
            if (! $this->option('keep')) {
                $this->cleanUp($seed);
            }
            File::deleteDirectory($dir);

            return self::FAILURE;
        }

        $control = (bool) $this->option('control');
        $this->line(
            "Reserva #{$seed['item']->id} · {$workers} procesos simultáneos [{$scenario}]"
            .($control ? ' <fg=yellow>· CONTROL: el cliente invierte el orden de locks</>' : '')
        );

        $this->forkWorkers($seed, $workers, microtime(true) + 1.0, $dir, $scenario, $control);
        DB::reconnect();

        $ok = $scenario === 'addons'
            ? $this->evaluateAddons($seed, $dir)
            : $this->evaluateCross($dir, $control);

        File::deleteDirectory($dir);
        if (! $this->option('keep')) {
            $this->cleanUp($seed);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Una fiesta PAGADA de 4 invitados con un complemento de venta posterior enganchado y en plazo.
     *
     * @return array{item:OrderItem, order:Order, zone:Zone, pack:TicketType, addon:TicketType, user:User}
     */
    private function seed(): array
    {
        $rate = RateType::firstOrCreate(
            ['key' => RateType::KEY_NORMAL],
            ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true],
        );

        $zone = Zone::create([
            'slug' => 'pf-'.Str::lower(Str::random(6)), 'name' => ['es' => 'Verify post-form'],
            'is_active' => false, 'position' => 900,
        ]);

        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños (verify post-form)'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 901,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        Price::create([
            'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
            'rate_type_id' => $rate->id, 'amount_cents' => 2500, 'currency' => 'EUR',
        ]);

        $addon = TicketType::create([
            'name' => ['es' => 'Cubo de refrescos (verify)'], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 902,
        ]);
        Price::create([
            'priceable_type' => $addon->getMorphClass(), 'priceable_id' => $addon->id,
            'rate_type_id' => $rate->id, 'amount_cents' => 1200, 'currency' => 'EUR',
        ]);

        $pack->configurableAddons()->attach($addon->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 2, 'max_qty' => 10,
        ]);

        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => Carbon::today()->addDays(10)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);

        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-PF'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 10000, 'tax' => 0, 'total' => 10000, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 10000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) random_int(100000, 999999), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => 4,
            'unit_price' => 2500, 'seats' => 4, 'event_data' => ['celebrant' => 'Verify'],
        ]);

        return compact('item', 'order', 'zone', 'pack', 'addon', 'user');
    }

    /**
     * Guarda del INSTRUMENTO, hermana de la de `mixed-party:verify-concurrency` (`#405`): comprueba
     * que el escenario sembrado PUEDE producir la línea que se va a disputar, **antes** de forkear.
     * Sin ella, un fixture sin precio o fuera de plazo daría cero líneas y el comando lo cantaría
     * como «el invariante falló».
     *
     * @param  array{item:OrderItem, addon:TicketType}  $seed
     */
    private function guardTheInstrument(array $seed): bool
    {
        $offerable = app(PostFormAddons::class)->offerableFor($seed['item']->fresh(['ticketType.addons', 'slot', 'order']));

        if (! $offerable->has($seed['addon']->getKey())) {
            $this->newLine();
            $this->error(
                '✗ FIXTURE ROTO (no es un fallo del invariante): el complemento sembrado NO es ofrecible '
                .'en el post-form de esa reserva — revisa la fase, el tope, el plazo o el precio del día.'
            );

            return false;
        }

        $this->line('<fg=gray>Guarda del instrumento · el complemento es ofrecible en el post-form de la reserva. ✓</>');

        return true;
    }

    /** @param  array{item:OrderItem, order:Order, addon:TicketType}  $seed */
    private function forkWorkers(array $seed, int $workers, float $startAt, string $dir, string $scenario, bool $control): void
    {
        DB::disconnect(); // el socket MySQL del padre NO debe compartirse entre forks

        $pids = [];
        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->error('pcntl_fork falló.');
                break;
            }
            if ($pid === 0) {
                DB::reconnect();
                $wait = (int) (($startAt - microtime(true)) * 1_000_000);
                if ($wait > 0) {
                    usleep($wait);
                }

                $outcome = 'ERROR';
                try {
                    // En `cross`, los IMPARES hacen de OPERADOR (cancelar / reembolsar): toman
                    // `orders` y después el ítem, que es el orden de los cuatro caminos reales.
                    $isOperator = $scenario === 'cross' && $i % 2 === 1;

                    if ($isOperator) {
                        $this->actLikeOperator($seed);
                    } elseif ($scenario === 'cross' && $control) {
                        $this->actLikeCustomerWithInvertedLocks($seed);
                    } else {
                        $item = OrderItem::with(['ticketType.addons', 'slot', 'order', 'children'])
                            ->findOrFail($seed['item']->getKey());
                        app(PostFormAddons::class)->reconcile($item, [$seed['addon']->getKey() => 2], 'signed_link');
                    }
                    $outcome = 'ok';
                } catch (\Throwable $e) {
                    $outcome = 'EXCEPTION: '.$e->getMessage();
                }
                File::put($dir.'/'.$i.'.txt', $outcome);
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    /**
     * El OPERADOR: el orden de locks que usan `OrderItemCanceller::cancel()`, las dos transacciones
     * de `Order::executePartialRefund()` y la acción «Cancelar pedido» — **`orders` primero**. Se
     * reproduce aquí en vez de arrastrar la maquinaria del reembolso porque lo que se mide es el
     * ORDEN, no el reembolso.
     *
     * @param  array{item:OrderItem, order:Order}  $seed
     */
    private function actLikeOperator(array $seed): void
    {
        DB::transaction(function () use ($seed): void {
            Order::query()->lockForUpdate()->find($seed['order']->getKey());
            usleep(random_int(1_000, 15_000));
            $item = OrderItem::query()->lockForUpdate()->findOrFail($seed['item']->getKey());
            $item->touch();
        });
    }

    /**
     * El CONTROL NEGATIVO: el cliente con el orden VIEJO (`order_items` → `orders`), que es el que la
     * spec daba por seguro antes de que la revisión reprodujera el interbloqueo. Con esto el `cross`
     * tiene que salir en ROJO — si no, el escenario no está midiendo nada.
     *
     * @param  array{item:OrderItem, order:Order}  $seed
     */
    private function actLikeCustomerWithInvertedLocks(array $seed): void
    {
        DB::transaction(function () use ($seed): void {
            OrderItem::query()->lockForUpdate()->findOrFail($seed['item']->getKey());
            usleep(random_int(1_000, 15_000));
            Order::query()->lockForUpdate()->find($seed['order']->getKey());
        });
    }

    /** @param  array{item:OrderItem, addon:TicketType}  $seed */
    private function evaluateAddons(array $seed, string $dir): bool
    {
        $outcomes = collect(File::files($dir))->map(fn ($f): string => trim(File::get($f->getPathname())));
        $errors = $outcomes->reject(fn (string $o): bool => $o === 'ok');

        $lines = OrderItem::where('parent_item_id', $seed['item']->getKey())
            ->where('ticket_type_id', $seed['addon']->getKey())
            ->whereNull('cancelled_at')
            ->get();
        $adjustments = OrderAdjustment::where('order_id', $seed['item']->order_id)->count();

        $this->newLine();
        $this->line("  líneas vivas del complemento: {$lines->count()}");
        $this->line('  cantidad escrita: '.$lines->sum('quantity'));
        $this->line("  hechos (`order_adjustments`): {$adjustments}");

        if ($errors->isNotEmpty()) {
            $this->error('✗ Hubo procesos con excepción: '.$errors->first());

            return false;
        }
        if ($lines->count() !== 1 || (int) $lines->sum('quantity') !== 2 || $adjustments !== 1) {
            $this->error(
                '✗ FALLA: se esperaba UNA línea de 2 unidades y UN hecho; sin el lock cada guardado '
                .'escribe la suya y el pedido acaba con N líneas del mismo complemento.'
            );

            return false;
        }

        $this->newLine();
        $this->info('✅ PASA [addons]: N guardados simultáneos del mismo post-form dejan UNA sola línea y UN solo hecho. El lock serializa.');

        return true;
    }

    private function evaluateCross(string $dir, bool $control): bool
    {
        $outcomes = collect(File::files($dir))->map(fn ($f): string => trim(File::get($f->getPathname())));
        $deadlocks = $outcomes->filter(fn (string $o): bool => str_contains($o, '40001') || str_contains($o, 'Deadlock'));
        $others = $outcomes->reject(fn (string $o): bool => $o === 'ok')->diff($deadlocks);

        $this->newLine();
        $this->line("  interbloqueos: {$deadlocks->count()} de {$outcomes->count()} procesos");

        if ($others->isNotEmpty()) {
            $this->error('✗ Hubo excepciones que NO son interbloqueos: '.$others->first());

            return false;
        }

        if ($control) {
            if ($deadlocks->isEmpty()) {
                $this->error(
                    '✗ EL CONTROL NO REPRODUJO EL INTERBLOQUEO: sin rojo, el verde del escenario normal '
                    .'no demuestra nada. Sube `--workers` o revisa que la ventana entre los dos locks siga abierta.'
                );

                return false;
            }
            $this->newLine();
            $this->info("✅ CONTROL [cross]: con el orden de locks INVERTIDO aparecen {$deadlocks->count()} interbloqueos. El instrumento ve el rojo.");

            return true;
        }

        if ($deadlocks->isNotEmpty()) {
            $this->error(
                '✗ FALLA: un guardado del cliente y una cancelación del operador se interbloquearon. '
                .'El orden `orders → order_items → hijas` (D11) es la regla que lo evita.'
            );

            return false;
        }

        $this->newLine();
        $this->info('✅ PASA [cross]: cliente y operador sobre el mismo pedido, CERO interbloqueos. El orden de locks aguanta.');

        return true;
    }

    /** @param  array{item:OrderItem, order:Order, zone:Zone, pack:TicketType, addon:TicketType, user:User}  $seed */
    private function cleanUp(array $seed): void
    {
        $orderId = $seed['order']->getKey();
        OrderAdjustment::where('order_id', $orderId)->delete();
        OrderItem::where('order_id', $orderId)->delete();
        Payment::where('payable_id', $orderId)->where('payable_type', $seed['order']->getMorphClass())->delete();
        Order::whereKey($orderId)->delete();
        Slot::where('zone_id', $seed['zone']->getKey())->delete();
        DB::table('product_addons')->where('product_id', $seed['pack']->getKey())->delete();
        // ⚠️ El complemento NO tiene zona (la trampa que `specs/hora-extra.md` §8.6 dejó escrita: un
        // barrido por zona lo dejaría en la base de desarrollo tras cada ejecución).
        Price::where('priceable_id', $seed['addon']->getKey())->where('priceable_type', $seed['addon']->getMorphClass())->delete();
        Price::where('priceable_id', $seed['pack']->getKey())->where('priceable_type', $seed['pack']->getMorphClass())->delete();
        TicketType::whereKey([$seed['pack']->getKey(), $seed['addon']->getKey()])->delete();
        Zone::whereKey($seed['zone']->getKey())->delete();
        $seed['user']->forceDelete();

        $this->line('Datos de prueba borrados.');
    }
}
