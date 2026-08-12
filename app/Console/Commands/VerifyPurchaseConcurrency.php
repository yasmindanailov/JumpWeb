<?php

namespace App\Console\Commands;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Verificación EMPÍRICA de NO-SOBREVENTA bajo compra concurrente (recomendación A · H2, 2026-06-15).
 *
 * Cierra el punto ciego que la auditoría Fase 1 nombró explícitamente (`AUDIT-FASE-1.md` §H2): bajo
 * `REPEATABLE READ` de InnoDB el snapshot de las lecturas consistentes se fija en la PRIMERA, así que
 * dos compras de la última plaza podrían SOBREVENDER si el recuento de aforo se leyera antes del lock.
 * El fix (`OrderCreator::lockSlots` como PRIMERA sentencia de la transacción) quedaba «razonado para
 * MySQL prod», sin verificar bajo concurrencia real (la suite corre en SQLite, que no reproduce InnoDB).
 *
 * Este comando lo verifica de VERDAD: siembra una franja con UNA sola plaza online y dispara N compras
 * en PARALELO (`pcntl_fork`) de esa última plaza contra MySQL. El invariante: exactamente UNA compra
 * tiene éxito y las (N-1) restantes son rechazadas con `sold_out`; la plaza nunca se sobrevende
 * (asientos reservados == aforo online). Solo entornos NO productivos; limpia siempre lo que crea.
 */
class VerifyPurchaseConcurrency extends Command
{
    protected $signature = 'purchase:verify-oversell
        {--workers=8 : Nº de compras concurrentes (procesos)}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que dos compras simultáneas de la ÚLTIMA plaza no sobrevenden: solo una gana. Solo dev/local.';

    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Abortado: NO ejecutar en producción (crea y borra datos).');

            return self::FAILURE;
        }
        if (! \extension_loaded('pcntl')) {
            $this->error('Falta la extensión pcntl: no se puede forkar para concurrencia real.');

            return self::FAILURE;
        }
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->warn("⚠ Conexión '{$driver}': SQLite NO reproduce los locks de InnoDB. Ejecuta contra MySQL para una prueba VÁLIDA.");
        }

        $workers = max(2, (int) $this->option('workers'));
        $resultsDir = storage_path('app/purchase-concurrency');
        File::ensureDirectoryExists($resultsDir);
        File::cleanDirectory($resultsDir);

        $this->line('Sembrando una franja con <fg=yellow>1 sola plaza</> online + '.$workers.' compradores…');
        $seed = $this->seedScenario($workers);

        $this->line("Disparando <fg=yellow>{$workers}</> compras <options=bold>CONCURRENTES</> de la última plaza sobre {$driver}…");

        try {
            $this->forkWorkers($seed, microtime(true) + 0.5, $resultsDir);

            DB::reconnect();
            $verdict = $this->evaluate($seed, $workers, $resultsDir);
        } finally {
            DB::reconnect();
            if ($this->option('keep')) {
                $this->warn("--keep: datos de prueba NO borrados (zona #{$seed['zone']->id}).");
            } else {
                $this->cleanup($seed);
                File::deleteDirectory($resultsDir);
            }
        }

        return $verdict ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Franja aislada con online_capacity=1 + entrada vendible con precio + N usuarios desechables.
     *
     * @return array{zone:Zone, type:TicketType, slot:Slot, users:array<int,User>, date:string, time:string, cart:array<int,array<string,mixed>>}
     */
    private function seedScenario(int $workers): array
    {
        return DB::transaction(function () use ($workers): array {
            $rateId = (int) (RateType::firstOrCreate(
                ['key' => RateType::KEY_NORMAL],
                ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0],
            )->id);

            $zone = Zone::create([
                'slug' => 'h2-probe-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'H2 Probe'],
                'is_active' => true,
            ]);

            // La hora debe caer DENTRO del horario efectivo del parque ese día (si no, OrderCreator
            // rechaza con `outside_window_line`, que NO es lo que queremos probar). Buscamos el primer
            // día ABIERTO a partir de hoy+3 y elegimos una hora segura (apertura + 1 h).
            $schedule = app(OperatingSchedule::class);
            [$date, $time] = $this->firstOpenSlotMoment($schedule);
            $end = Carbon::parse($time)->addHour()->format('H:i:s');

            $slot = Slot::create([
                'zone_id' => $zone->id, 'date' => $date,
                'start_time' => $time, 'end_time' => $end,
                'capacity' => 1, 'online_capacity' => 1, // UNA sola plaza online
            ]);

            $type = TicketType::create([
                'name' => ['es' => 'Entrada H2'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
                'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
            ]);
            // El precio debe existir para la tarifa REAL del día elegido: en una BD sembrada,
            // `firstOpenSlotMoment` puede caer en festivo/finde con tarifa `special` y un precio
            // solo-normal haría que OrderCreator rechace por «producto sin precio ese día»
            // (falso negativo del verificador, no sobreventa — cazado 2026-08-12, primera
            // ejecución de este comando en el repo JumpWeb).
            $dayRateId = (int) app(RateResolver::class)->for(Carbon::parse($date))->id;
            $type->prices()->create(['rate_type_id' => $dayRateId, 'amount_cents' => 1000]);
            if ($dayRateId !== $rateId) {
                $type->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1000]);
            }

            $users = [];
            for ($i = 0; $i < $workers; $i++) {
                $users[] = User::forceCreate([
                    'name' => 'H2 Buyer '.$i,
                    'email' => 'h2-buyer-'.Str::random(8).'@deleted.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
            }

            $cart = [['ticket_type_id' => $type->id, 'date' => $date, 'time' => $time, 'qty' => 1]];

            return compact('zone', 'type', 'slot', 'users', 'date', 'time', 'cart');
        });
    }

    /**
     * Primer día ABIERTO (desde hoy+3, dentro del horizonte de compra) y una hora segura dentro de
     * su horario efectivo (apertura + 1 h, acotada a `[open, close)`). Robusto al día de la semana en
     * que se ejecute (evita el rechazo `outside_window_line`, que no es lo que se prueba aquí).
     *
     * @return array{0:string,1:string} [date, time]
     */
    private function firstOpenSlotMoment(OperatingSchedule $schedule): array
    {
        for ($d = 3; $d <= 30; $d++) {
            $date = Carbon::today()->addDays($d);
            $hours = $schedule->effectiveFor($date);
            if (! ($hours['is_open'] ?? false) || ($hours['open'] ?? null) === null || ($hours['close'] ?? null) === null) {
                continue;
            }
            $open = Carbon::parse($hours['open']);
            $close = Carbon::parse($hours['close']);
            $start = $open->copy()->addHour();
            if ($start >= $close) {
                $start = $open;
            }

            return [$date->toDateString(), $start->format('H:i:s')];
        }

        throw new \RuntimeException('No se encontró un día abierto en los próximos 30 días para la prueba.');
    }

    /**
     * Forka N procesos; cada uno intenta comprar la última plaza al mismo instante. Cada hijo
     * escribe su outcome (created / sold_out / error) a un fichero.
     *
     * @param  array{users:array<int,User>, cart:array<int,array<string,mixed>>}  $seed
     */
    private function forkWorkers(array $seed, float $startAt, string $resultsDir): void
    {
        DB::disconnect(); // el socket MySQL del padre NO debe compartirse entre forks

        $pids = [];
        foreach ($seed['users'] as $i => $user) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->error('pcntl_fork falló.');
                break;
            }
            if ($pid === 0) {
                // ---- HIJO ----
                DB::reconnect();
                $wait = (int) (($startAt - microtime(true)) * 1_000_000);
                if ($wait > 0) {
                    usleep($wait);
                }
                $outcome = 'ERROR';
                try {
                    $buyer = User::find($user->id);
                    $order = app(OrderCreator::class)->createPendingOrder($buyer, $seed['cart']);
                    $outcome = 'created:'.$order->id;
                } catch (ReservationException $e) {
                    $outcome = 'sold_out:'.$e->getMessage();
                } catch (\Throwable $e) {
                    $outcome = 'EXCEPTION: '.$e->getMessage();
                }
                File::put($resultsDir.'/'.$i.'.txt', $outcome);
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    /**
     * @param  array{zone:Zone, type:TicketType, slot:Slot, users:array<int,User>}  $seed
     */
    private function evaluate(array $seed, int $workers, string $resultsDir): bool
    {
        $outcomes = collect(File::files($resultsDir))
            ->map(fn ($f): string => trim(File::get($f->getPathname())));

        $created = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'created:'));
        $soldOut = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'sold_out:'));
        $errors = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'EXCEPTION') || $o === 'ERROR');

        // Asientos REALMENTE reservados en la franja (pedidos pending no caducados): la prueba dura
        // de no-sobreventa. Debe ser exactamente el aforo online (1), pase lo que pase con los outcomes.
        $reservedSeats = (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.slot_id', $seed['slot']->id)
            ->whereNull('order_items.cancelled_at')
            ->where(fn ($q) => $q->where('orders.status', Order::STATUS_PAID)
                ->orWhere(fn ($q2) => $q2->where('orders.status', Order::STATUS_PENDING)
                    ->where(fn ($q3) => $q3->whereNull('orders.expires_at')->orWhere('orders.expires_at', '>', now()))))
            ->sum('order_items.seats');

        $this->newLine();
        $this->line('<options=bold>Resultados de los compradores concurrentes:</>');
        $this->line('  '.$created->count().'× compra creada');
        $this->line('  '.$soldOut->count().'× rechazada (sold_out)');
        if ($errors->isNotEmpty()) {
            $this->line('  <fg=red>'.$errors->count().'× error inesperado</>');
            $errors->each(fn ($e) => $this->line('     '.$e));
        }

        $capacity = (int) $seed['slot']->online_capacity;
        $this->newLine();
        $this->table(
            ['Invariante', 'Esperado', 'Real', 'OK'],
            [
                ['Asientos reservados en la franja', (string) $capacity, (string) $reservedSeats, $this->ok($reservedSeats === $capacity)],
                ['Compras con éxito', '1', (string) $created->count(), $this->ok($created->count() === 1)],
                ['Compras rechazadas (sold_out)', (string) ($workers - 1), (string) $soldOut->count(), $this->ok($soldOut->count() === $workers - 1)],
                ['Errores inesperados', '0', (string) $errors->count(), $this->ok($errors->isEmpty())],
            ]
        );

        $pass = $reservedSeats === $capacity
            && $created->count() === 1
            && $soldOut->count() === $workers - 1
            && $errors->isEmpty();

        $this->newLine();
        if ($pass) {
            $this->info('✅ PASA: bajo '.$workers.' compras concurrentes de la última plaza el lockSlots serializó correctamente — UNA sola ganó, SIN sobreventa. Invariante anti-H2 verificado sobre InnoDB real.');
        } else {
            $this->error('❌ FALLA: SOBREVENTA o invariante roto. Revisar que lockSlots() sea la PRIMERA sentencia de la transacción de OrderCreator.');
        }

        return $pass;
    }

    private function ok(bool $b): string
    {
        return $b ? '<fg=green>✓</>' : '<fg=red>✗</>';
    }

    /**
     * @param  array{zone:Zone, type:TicketType, slot:Slot, users:array<int,User>}  $seed
     */
    private function cleanup(array $seed): void
    {
        $userIds = collect($seed['users'])->pluck('id')->all();
        $orderIds = Order::whereIn('user_id', $userIds)->pluck('id')->all();

        OrderItem::whereIn('order_id', $orderIds)->delete();
        Order::whereIn('id', $orderIds)->delete();
        $seed['type']->prices()->delete();
        Slot::where('id', $seed['slot']->id)->delete();
        TicketType::where('id', $seed['type']->id)->delete();
        User::whereIn('id', $userIds)->delete();
        Zone::where('id', $seed['zone']->id)->delete();
        $this->line('<fg=gray>Datos de prueba borrados.</>');
    }
}
