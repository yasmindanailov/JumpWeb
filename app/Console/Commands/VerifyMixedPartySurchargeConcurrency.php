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
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Identity\Models\User;
use Carbon\Carbon;
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
        {--scenario=charge : Qué línea se disputa: charge (el suplemento) | credit (el descuento de la T4)}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que N guardados simultáneos del post-form producen UNA sola línea de suplemento (charge) o UNA sola de descuento (credit, T4). Solo dev/local.';

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
        if (! in_array($scenario, ['charge', 'credit'], true)) {
            $this->error("Escenario desconocido «{$scenario}»: charge | credit.");

            return self::FAILURE;
        }

        $workers = max(2, (int) $this->option('workers'));
        $seed = $this->seed($scenario);
        $dir = storage_path('app/mixed-party-verify-'.Str::random(8));
        File::ensureDirectoryExists($dir);

        if (! $this->guardTheInstrument($seed, $scenario)) {
            if (! $this->option('keep')) {
                $this->cleanUp($seed);
            }
            File::deleteDirectory($dir);

            return self::FAILURE;
        }

        $this->line("Reserva #{$seed['item']->id} · {$workers} guardados simultáneos del mismo post-form [{$scenario}].");

        // Todos arrancan a la vez: sin cita común, los forks se escalonan y la carrera no ocurre.
        $this->forkWorkers($seed, $workers, microtime(true) + 1.0, $dir, $scenario);
        DB::reconnect();

        $ok = $this->evaluate($seed, $workers, $dir, $scenario);

        File::deleteDirectory($dir);
        if (! $this->option('keep')) {
            $this->cleanUp($seed);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Una fiesta pagada de 4 invitados en una familia de dos regímenes, SIN datos por-niño.
     * `charge`: reservada KIDS, un invitado por encima → los workers se disputan el SUPLEMENTO.
     * `credit` (T4, §24.3): reservada JUMP con resto de señal (la cobertura), un invitado por
     * debajo → los workers se disputan la línea de DESCUENTO. Sin el lock, cada guardado escribiría
     * la suya, igual que le pasaba al cargo.
     *
     * @return array{item:OrderItem, order:Order, zone:Zone, packs:array<int,TicketType>, user:User}
     */
    private function seed(string $scenario): array
    {
        return DB::transaction(function () use ($scenario): array {
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

            // ⚠️⚠️ **El precio va en TODAS las tarifas activas, no solo en `normal`, y esto NO es
            // cinturón: era un DEFECTO del instrumento** (2026-09-02, `#405`). La franja nace a
            // `+30 días`, así que el día de la semana en que cae lo decide el CALENDARIO — y si ese
            // día lo gobierna otra tarifa (en la BD de desarrollo, `special` con prioridad 10 cubre
            // viernes, sábado y domingo), los packs no tienen precio bajo ella, `guestRegimes()` no
            // puede tarificar, y `MixedPartySurcharge::reconcile()` **se abstiene con razón**
            // (`#288`: «no poder tarificar es una ausencia»). Resultado: **CERO líneas y el
            // verificador en rojo con el producto perfecto, 3 de cada 7 días**.
            // ▶ *Un verificador que depende del día de la semana en que se ejecuta no verifica: sortea.*
            // Es el hermano exacto de la auditoría del reloj de `#337`, que cazó once rojos de esa
            // misma familia. El mismo importe en todas las tarifas mantiene el suplemento esperado
            // (25,00 − 18,00 = 7,00 €) sea cual sea la que mande ese día.
            $rates = RateType::query()->where('is_active', true)->get();

            $make = function (string $name, int $min, int $max, int $cents) use ($zone, $family, $rates): TicketType {
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
                foreach ($rates as $r) {
                    Price::create([
                        'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
                        'rate_type_id' => $r->id, 'amount_cents' => $cents, 'currency' => 'EUR',
                    ]);
                }

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
            $booked = $scenario === 'credit' ? $jump : $kids;
            $unit = (int) ($scenario === 'credit' ? 2500 : 1800);
            $order = Order::create([
                'user_id' => $user->id, 'code' => 'VF-'.Str::upper(Str::random(6)),
                'status' => Order::STATUS_PAID, 'paid_at' => now(),
                'subtotal' => $unit * 4, 'total' => $unit * 4, 'currency' => 'EUR',
            ]);
            $item = $order->items()->create([
                'ticket_type_id' => $booked->id, 'slot_id' => $slot->id,
                'quantity' => 4, 'unit_price' => $unit, 'seats' => 4,
            ]);
            // El escenario del DESCUENTO nació con un resto de señal porque el tope de cobertura (§20.1)
            // no dejaba escribir el crédito sin él. Desde la T3·3 del libro (`DECISIONES #312`, D4) el
            // crédito se escribe ENTERO también sin cobertura; el resto se conserva porque no cambia lo
            // que se disputa (UNA línea de −7,00 €) y mantiene el escenario comparable con el histórico.
            if ($scenario === 'credit') {
                OrderAdjustment::create([
                    'order_id' => $order->id, 'order_item_id' => $item->id,
                    'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
                    'amount_cents' => 5000, 'currency' => 'EUR', 'applied_by' => $user->id,
                ]);
            }
            // ⚠️ Sin el SELLO (`specs/cumple-mixto.md` §21) la reserva no participa —el veredicto
            // deriva del sello, no del catálogo— y ningún worker escribiría nada: el verificador
            // pasaría en verde sin verificar. En producción lo pone `OrderCreator` al nacer.
            app(AgeFamilySealer::class)->seal($item, $booked, $slot->date);

            return ['item' => $item, 'order' => $order, 'zone' => $zone, 'packs' => [$kids, $jump], 'user' => $user];
        });
    }

    /** @param  array{item:OrderItem}  $seed */
    private function forkWorkers(array $seed, int $workers, float $startAt, string $dir, string $scenario): void
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
                    // `charge` (reservada KIDS): el de 8 sube a JUMP. `credit` (reservada JUMP): el
                    // de 4 baja a KIDS y se disputa la línea de descuento.
                    $ages = $scenario === 'credit' ? ['8', '9', '8', '4'] : ['4', '5', '8', '6'];
                    $item->submitGuestForm([
                        ['name' => 'A', 'edad' => $ages[0]],
                        ['name' => 'B', 'edad' => $ages[1]],
                        ['name' => 'C', 'edad' => $ages[2]],
                        ['name' => 'D', 'edad' => $ages[3]],
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
    /**
     * Guarda del INSTRUMENTO, hermana de la de `purchase:verify-oversell` (`#147`): comprueba que el
     * escenario sembrado PUEDE producir la línea que se va a disputar, ANTES de forkear.
     *
     * ⚠️⚠️ **Nace de un rojo REAL con el producto sano** (2026-09-02, `#405`): sin precio bajo la
     * tarifa que gobierna el día de la franja, `reconcile()` se abstiene, salen CERO líneas y el
     * comando lo cantaba como «la línea se duplicó». *Un instrumento que no distingue «no pudo
     * medir» de «el invariante falló» convierte cualquier problema de fixture en un falso defecto
     * de dinero* — y aquí el falso defecto tocaba justo el camino que nadie quiere tocar a ciegas.
     *
     * Lo que comprueba es la PRECONDICIÓN exacta que faltaba: que la tarifa que manda ese día
     * tarifica los DOS regímenes de la familia. Si no, dice qué falta y se rinde en amarillo.
     *
     * @param  array{item:OrderItem, order:Order, zone:Zone, packs:array<int,TicketType>, user:User}  $seed
     */
    private function guardTheInstrument(array $seed, string $scenario): bool
    {
        $slot = $seed['item']->slot;
        $day = Carbon::parse($slot->date);
        $rate = app(RateResolver::class)->for($day);

        $unpriced = collect($seed['packs'])
            ->filter(fn (TicketType $p): bool => $p->priceCentsForRate($rate, 1) === null)
            ->map(fn (TicketType $p): string => (string) $p->id);

        if ($unpriced->isNotEmpty()) {
            $this->newLine();
            $this->error(
                "✗ FIXTURE ROTO (no es un fallo del invariante): el día {$day->toDateString()} lo gobierna la ".
                "tarifa «{$rate->key}» y los packs {$unpriced->implode(', ')} no tienen precio bajo ella, ".
                'así que `reconcile()` se abstendría y saldrían CERO líneas. Siembra el precio en esa tarifa.'
            );

            return false;
        }

        $this->line(
            "<fg=gray>Guarda del instrumento · el día {$day->toDateString()} lo gobierna la tarifa ".
            "«{$rate->key}» y los dos regímenes tarifican bajo ella. ✓</>"
        );

        return true;
    }

    private function evaluate(array $seed, int $workers, string $dir, string $scenario): bool
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
        $this->line('  líneas de fiesta mixta vivas: '.$children->count());
        $this->line('  ajustes marcados `mixed_party`: '.$marked->count());
        $this->line('  importe total marcado: '.number_format($marked->sum('amount_cents') / 100, 2, ',', '.').' €');

        if ($errors->isNotEmpty()) {
            $this->newLine();
            $this->line('<fg=yellow>Errores devueltos por los workers:</>');
            $errors->unique()->each(fn (string $e) => $this->line('  · '.$e));
        }

        // El invariante: UNA sola línea pase lo que pase — el cargo de +7,00 € (25,00 − 18,00 por
        // el invitado de 8) o el descuento de −7,00 € (T4: el de 4 en una fiesta JUMP).
        $expected = $scenario === 'credit' ? -700 : 700;
        $ok = $children->count() === 1 && $marked->count() === 1
            && (int) $marked->sum('amount_cents') === $expected
            && ($scenario !== 'credit' || $children->every(fn (OrderItem $c): bool => (bool) $c->is_credit));

        $this->newLine();
        if ($ok) {
            $this->info($scenario === 'credit'
                ? '✓ UNA sola línea de descuento de −7,00 €: el lock serializa también el espejo.'
                : '✓ UNA sola línea de suplemento y un solo cargo de 7,00 €: el lock serializa.');
        } elseif ($children->count() > 1 || $marked->count() > 1) {
            $this->error(
                '✗ La línea se DUPLICÓ ('.$children->count().' líneas · '.$marked->count().
                ' ajustes): sin serializar, cada guardado escribe la suya. Es el fallo que este comando busca.'
            );
        } elseif ($children->isEmpty() && $marked->isEmpty()) {
            // ⚠️ **Cero NO es duplicación, y decir que lo es manda a buscar el defecto al sitio
            // equivocado** (`#405`): hasta el 2026-09-02 los dos casos compartían mensaje. Con la
            // guarda del instrumento delante, llegar aquí ya significa que el fixture era capaz y
            // que quien se abstuvo fue el dominio — que es una pista muy distinta.
            $this->error(
                '✗ NO se escribió NINGUNA línea. Ojo: esto NO es la carrera que busca el comando — el '.
                'invariante ni se ha ejercitado. `MixedPartySurcharge::reconcile()` se abstuvo pese a que '.
                'la guarda del instrumento dio verde: mira el portador (`MixedPartySettings`), el sello de '.
                'la reserva y `allAgesDeclared()` antes de sospechar del lock.'
            );
        } else {
            $this->error(
                '✗ UNA línea, pero el importe no es el esperado: '.
                number_format($marked->sum('amount_cents') / 100, 2, ',', '.').' € contra '.
                number_format($expected / 100, 2, ',', '.').' € — el lock serializa; lo que falla es la aritmética.'
            );
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
