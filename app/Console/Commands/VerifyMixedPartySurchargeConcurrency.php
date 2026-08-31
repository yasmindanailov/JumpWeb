<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Cumpleaños MIXTO — verificador de CONCURRENCIA del suplemento
 * (`docs/specs/cumple-mixto.md` §12), hermano de `purchase:verify-oversell` y `waiver:verify-chain`.
 *
 * N procesos reales (`pcntl_fork`) guardan a la vez el MISMO post-form de la MISMA reserva contra
 * MySQL, partiendo de CERO líneas de suplemento. El invariante es **UNA sola línea y un solo
 * cargo**: el primero que entra la escribe y los N-1 restantes la encuentran bajo el lock de
 * `MixedPartySurcharge::reconcile()` y no hacen nada.
 *
 * ⚠️⚠️ **Sin el lock, todos leen «no hay línea» y todos la crean**: N suplementos sobre la misma
 * fiesta, o sea **el cargo multiplicado por N**. No falla, no avisa y el cliente se lo encuentra en
 * caja. Es exactamente la clase de carrera que `INVARIANTES §6` dice que la suite (SQLite) no puede
 * ver, y por eso vive aquí y no en un test.
 *
 * ⚠️ **Un verde solo vale si el instrumento se ha visto FALLAR** (`#147`): con el `lockForUpdate()`
 * retirado del reconciliador, este comando tiene que cazar las líneas de más. Solo dev/local.
 *
 * ⚠️ Este fichero NO entra en el `CRITICAL_RE` del `pre-push`, y es a propósito: ese gate impone los
 * dos verificadores del núcleo de compra/cobro, que no ejercitan el post-form. Meterlo ahí obligaría
 * a correr dos comandos que no prueban nada de esto — ritual sin protección. Lo que protege esta
 * carrera es este comando, y se corre al tocar el reconciliador.
 */
class VerifyMixedPartySurchargeConcurrency extends Command
{
    protected $signature = 'mixed-party:verify-concurrency
        {--workers=8 : Nº de guardados simultáneos del mismo post-form}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que N guardados simultáneos del post-form producen UNA sola línea de suplemento y un solo cargo. Solo dev/local.';

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

        $workers = max(2, (int) $this->option('workers'));
        $seed = $this->seed();
        $dir = storage_path('app/mixed-party-verify-'.Str::random(8));
        File::ensureDirectoryExists($dir);

        $this->line("Reserva #{$seed['item']->id} · {$workers} guardados simultáneos del mismo post-form.");

        // Todos arrancan a la vez: sin cita común, los forks se escalonan y la carrera no ocurre.
        $this->forkWorkers($seed, $workers, microtime(true) + 1.0, $dir);
        DB::reconnect();

        $ok = $this->evaluate($seed, $workers, $dir);

        File::deleteDirectory($dir);
        if (! $this->option('keep')) {
            $this->cleanUp($seed);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Una fiesta KIDS pagada de 4 invitados en una familia de dos regímenes, SIN datos por-niño.
     * Cada worker guardará las mismas edades, con un invitado por encima del tramo.
     *
     * @return array{item:OrderItem, order:Order, zone:Zone, packs:array<int,TicketType>, user:User}
     */
    private function seed(): array
    {
        return DB::transaction(function (): array {
            $rate = RateType::firstOrCreate(
                ['key' => RateType::KEY_NORMAL],
                ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true],
            );
            $zone = Zone::create([
                'slug' => 'verify-mixed-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'Zona del verificador'], 'accent' => 'cumpleanos',
                'position' => 900, 'is_active' => false,
            ]);
            $family = 'verify-'.Str::lower(Str::random(6));

            $make = function (string $name, int $min, int $max, int $cents) use ($zone, $family, $rate): TicketType {
                $pack = TicketType::create([
                    'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
                    'zone_id' => $zone->id, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 30,
                    'is_sellable' => false, 'is_active' => false,
                    'position' => (int) TicketType::max('position') + 1,
                    'guest_fields' => [
                        ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                        ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
                    ],
                    'guest_age_family' => $family, 'guest_age_min' => $min, 'guest_age_max' => $max,
                ]);
                Price::create([
                    'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
                    'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
                ]);

                return $pack;
            };

            $kids = $make('Verify KIDS', 1, 6, 1800);
            $jump = $make('Verify JUMP', 7, 99, 2500);

            $slot = Slot::create([
                'zone_id' => $zone->id, 'date' => now()->addDays(30)->toDateString(),
                'start_time' => '11:00:00', 'end_time' => '13:00:00',
                'capacity' => 100, 'online_capacity' => 100,
            ]);
            $user = User::create([
                'name' => 'Verificador de suplemento',
                'email' => 'mixed-party-'.Str::lower(Str::random(8)).'@verify.local',
                'password' => Str::random(32), 'locale' => 'es', 'marketing_opt_in' => false,
            ]);
            $order = Order::create([
                'user_id' => $user->id, 'code' => 'VF-'.Str::upper(Str::random(6)),
                'status' => Order::STATUS_PAID, 'paid_at' => now(),
                'subtotal' => 7200, 'total' => 7200, 'currency' => 'EUR',
            ]);
            $item = $order->items()->create([
                'ticket_type_id' => $kids->id, 'slot_id' => $slot->id,
                'quantity' => 4, 'unit_price' => 1800, 'seats' => 4,
            ]);
            // ⚠️ Sin el SELLO (`specs/cumple-mixto.md` §21) la reserva no participa —el veredicto
            // deriva del sello, no del catálogo— y ningún worker escribiría nada: el verificador
            // pasaría en verde sin verificar. En producción lo pone `OrderCreator` al nacer.
            app(AgeFamilySealer::class)->seal($item, $kids, $slot->date);

            return ['item' => $item, 'order' => $order, 'zone' => $zone, 'packs' => [$kids, $jump], 'user' => $user];
        });
    }

    /** @param  array{item:OrderItem}  $seed */
    private function forkWorkers(array $seed, int $workers, float $startAt, string $dir): void
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
                    $item = OrderItem::with(['ticketType', 'slot', 'order'])->findOrFail($seed['item']->getKey());
                    // Las MISMAS edades en todos: la propiedad es que solo UNO escriba la línea.
                    $item->submitGuestForm([
                        ['name' => 'A', 'edad' => '4'],
                        ['name' => 'B', 'edad' => '5'],
                        ['name' => 'C', 'edad' => '8'],
                        ['name' => 'D', 'edad' => '6'],
                    ], [], 'signed_link');
                    $outcome = 'saved';
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

    /** @param  array{item:OrderItem, order:Order}  $seed */
    private function evaluate(array $seed, int $workers, string $dir): bool
    {
        $outcomes = collect(File::files($dir))->map(fn ($f): string => trim(File::get($f->getPathname())));
        $errors = $outcomes->reject(fn (string $o): bool => $o === 'saved');

        $children = OrderItem::where('parent_item_id', $seed['item']->getKey())
            ->whereNull('cancelled_at')->get();
        $marked = OrderAdjustment::where('order_id', $seed['order']->getKey())->get()
            ->filter(fn (OrderAdjustment $a): bool => is_array($a->context) && isset($a->context['mixed_party']));

        $this->newLine();
        $this->line('<options=bold>Resultado de los guardados simultáneos:</>');
        $this->line('  '.$outcomes->filter(fn (string $o): bool => $o === 'saved')->count()."/{$workers} guardaron sin error");
        $this->line('  líneas de suplemento vivas: '.$children->count());
        $this->line('  cargos marcados `mixed_party`: '.$marked->count());
        $this->line('  importe total del suplemento: '.number_format($marked->sum('amount_cents') / 100, 2, ',', '.').' €');

        if ($errors->isNotEmpty()) {
            $this->newLine();
            $this->line('<fg=yellow>Errores devueltos por los workers:</>');
            $errors->unique()->each(fn (string $e) => $this->line('  · '.$e));
        }

        // El invariante: UNA línea de 7,00 € (25,00 − 18,00 por el invitado de 8), pase lo que pase.
        $ok = $children->count() === 1 && $marked->count() === 1 && (int) $marked->sum('amount_cents') === 700;

        $this->newLine();
        if ($ok) {
            $this->info('✓ UNA sola línea de suplemento y un solo cargo de 7,00 €: el lock serializa.');
        } else {
            $this->error('✗ El suplemento se duplicó: sin serializar, cada guardado escribe el suyo.');
        }

        return $ok;
    }

    /** @param  array{item:OrderItem, order:Order, zone:Zone, packs:array<int,TicketType>, user:User}  $seed */
    private function cleanUp(array $seed): void
    {
        DB::transaction(function () use ($seed): void {
            OrderAdjustment::where('order_id', $seed['order']->getKey())->delete();
            OrderItem::where('order_id', $seed['order']->getKey())->delete();
            $seed['order']->delete();
            $seed['user']->delete();
            foreach ($seed['packs'] as $pack) {
                Price::where('priceable_type', $pack->getMorphClass())->where('priceable_id', $pack->id)->delete();
                $pack->delete();
            }
            Slot::where('zone_id', $seed['zone']->getKey())->delete();
            $seed['zone']->delete();
        });
    }
}
