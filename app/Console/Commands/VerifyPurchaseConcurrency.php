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
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
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
    /**
     * ⚠️⚠️ **El escenario `entry` NO cubre los packs, y eso estuvo sin decirse desde el principio.**
     *
     * Medido el 2026-08-25 (`DEUDA.md`): este comando sembraba **una entrada** con
     * `online_capacity = 1` y forkaba N compras. Los cumpleaños se cuentan por **otro camino
     * entero** —{@see PackAvailability}, con pool propio y **DOS** topes por franja— y **ningún
     * verificador lo ejecutaba**. Traducido: no había ninguna evidencia de que dos cumpleaños
     * simultáneos por la última plaza no se vendieran los dos.
     *
     * De ahí los tres escenarios, y son tres porque **son tres invariantes distintos**:
     *  · `entry`       — el pool de asientos de una franja (`SlotAvailability`).
     *  · `pack`        — el tope de **FIESTAS** por franja (`zones.max_per_slot`).
     *  · `pack-guests` — el tope de **NIÑOS** por franja (`zones.max_guests_per_slot`).
     *
     * El segundo y el tercero se separan a propósito: un cupo de fiestas correcto no dice nada
     * sobre el de invitados, y viceversa. Con un solo escenario mixto, el que se rompiera se
     * escondería detrás del que aguantara.
     */
    private const SCENARIOS = ['entry', 'pack', 'pack-guests'];

    protected $signature = 'purchase:verify-oversell
        {--workers=8 : Nº de compras concurrentes (procesos)}
        {--scenario=entry : Qué aforo se prueba: entry | pack | pack-guests}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que N compras simultáneas de la ÚLTIMA plaza no sobrevenden: solo una gana. Cubre los tres aforos: entradas, cupo de fiestas y cupo de invitados. Solo dev/local.';

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

        $scenario = (string) $this->option('scenario');
        if (! in_array($scenario, self::SCENARIOS, true)) {
            $this->error("Escenario '{$scenario}' desconocido. Válidos: ".implode(' · ', self::SCENARIOS));

            return self::FAILURE;
        }

        $workers = max(2, (int) $this->option('workers'));
        $resultsDir = storage_path('app/purchase-concurrency');
        File::ensureDirectoryExists($resultsDir);
        File::cleanDirectory($resultsDir);

        $this->line("Escenario <options=bold>{$scenario}</>: sembrando el último hueco + {$workers} compradores…");
        $seed = $scenario === 'entry'
            ? $this->seedEntryScenario($workers)
            : $this->seedPackScenario($workers, $scenario);

        // ⚠️⚠️ **La guarda del propio instrumento, y no es opcional.** Si el escenario está mal
        // montado —el pack no cabe en la rejilla, falta precio ese día, la franja quedó fuera de
        // horario— los N compradores son rechazados por un motivo que NO es la carrera, y el
        // verificador informaría de «0 sobreventas» sin haber probado nada. Un verificador que no
        // puede vender ni una sola vez sale verde por construcción.
        if (! $this->probeSellsOnce($seed)) {
            return self::FAILURE;
        }

        $this->line("Disparando <fg=yellow>{$workers}</> compras <options=bold>CONCURRENTES</> del último hueco sobre {$driver}…");

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
     * **La guarda del instrumento: comprobar que el escenario SÍ permite vender una vez.**
     *
     * Se ejecuta SIN concurrencia, antes de forkar, y pregunta al mismo dominio que van a usar los
     * hijos si el hueco existe. Sin esto, cualquier error de siembra —el pack no cabe en la rejilla,
     * el producto no tiene precio ese día, la hora cayó fuera del horario— produce N rechazos que se
     * leerían como «nadie sobrevendió», y el verde no significaría nada.
     *
     * ⚠️ Es el mismo modo de fallo que este comando ya pagó una vez: su primera ejecución en JumpWeb
     * falló por un precio ausente en la tarifa del día, y aquello **sí** se vio porque el invariante
     * exigía 1 compra creada. En los escenarios de pack el riesgo es mayor —hay tramo, prep y dos
     * topes—, así que la comprobación se hace explícita en vez de confiar en que el conteo la delate.
     *
     * @param  array{scenario:string, slot:Slot, type:TicketType, probe_qty:int}  $seed
     */
    private function probeSellsOnce(array $seed): bool
    {
        $qty = (int) $seed['probe_qty'];

        $available = $seed['scenario'] === 'entry'
            ? app(SlotAvailability::class)->availableFor($seed['slot'], $seed['type']->duration_min)
            : app(PackAvailability::class)->availableGuestsFor($seed['slot'], $seed['type']);

        if ($available >= $qty) {
            $this->line("<fg=gray>Guarda del instrumento: el hueco admite {$available} (se pedirán {$qty}). ✓</>");

            return true;
        }

        $this->error(
            "El escenario NO permite vender ni una vez: el dominio ofrece {$available} y cada comprador pide {$qty}.\n".
            "▶ Los N compradores serían rechazados por un motivo que NO es la carrera, y el resultado\n".
            '  se leería como «no hubo sobreventa». Revisa la siembra antes de creer ningún veredicto.'
        );

        return false;
    }

    /**
     * Franja aislada con online_capacity=1 + entrada vendible con precio + N usuarios desechables.
     *
     * @return array{zone:Zone, type:TicketType, slot:Slot, users:array<int,User>, date:string, time:string, cart:array<int,array<string,mixed>>, scenario:string, probe_qty:int, expected_winners:int}
     */
    private function seedEntryScenario(int $workers): array
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
            $scenario = 'entry';
            $probe_qty = 1;
            $expected_winners = 1;

            return compact('zone', 'type', 'slot', 'users', 'date', 'time', 'cart',
                'scenario', 'probe_qty', 'expected_winners');
        });
    }

    /**
     * **Escenario de CUMPLEAÑOS: el aforo que ningún verificador ejercitaba.**
     *
     * Un pack no consume asientos de la franja: consume **cupo** en su propio pool, con dos topes
     * que se prueban por separado ({@see self::SCENARIOS}). El escenario se aísla en una zona
     * propia porque los dos topes se pueden fijar **por zona** (`zones.max_per_slot`,
     * `zones.max_guests_per_slot`); así **no se tocan los ajustes globales** ni el resto de la BD.
     *
     * ⚠️ **`prep_blocks_cupo` se apaga a propósito.** Con el montaje y la limpieza bloqueando
     * franjas vecinas, un rechazo podría venir del tramo y no del cupo — y este verificador existe
     * para medir la CARRERA, no la aritmética del tramo (que sí cubre la suite en SQLite).
     *
     * ⚠️ La fiesta dura **una sola franja** por el mismo motivo: menos superficie donde un fallo de
     * siembra se disfrace de «no hubo sobreventa». El lock es idéntico en los dos casos —
     * `lockSlots` bloquea TODAS las franjas de la zona×fecha, no solo las de la cesta—.
     *
     * @return array{zone:Zone, type:TicketType, slot:Slot, users:array<int,User>, date:string, time:string, cart:array<int,array<string,mixed>>, scenario:string, probe_qty:int, expected_winners:int}
     */
    private function seedPackScenario(int $workers, string $scenario): array
    {
        return DB::transaction(function () use ($workers, $scenario): array {
            $rateId = (int) (RateType::firstOrCreate(
                ['key' => RateType::KEY_NORMAL],
                ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0],
            )->id);

            // Cupos POR ZONA: el escenario no toca `packs.*` global (la BD de desarrollo tiene
            // pedidos reales y sus ajustes no se alteran para medir).
            //  · `pack`        → 1 fiesta por franja, invitados de sobra: gana el tope de FIESTAS.
            //  · `pack-guests` → sin tope de fiestas, 10 niños por franja y cada uno pide 6:
            //                    dos fiestas NO caben (12 > 10) aunque el cupo de fiestas lo permita.
            $guestsPerBuyer = $scenario === 'pack-guests' ? 6 : 8;
            $zone = Zone::create([
                'slug' => 'pack-probe-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'Pack Probe'],
                'is_active' => true,
                'max_per_slot' => $scenario === 'pack-guests' ? 0 : 1,
                'max_guests_per_slot' => $scenario === 'pack-guests' ? 10 : 0,
                'prep_blocks_cupo' => false,
            ]);

            $schedule = app(OperatingSchedule::class);
            [$date, $time] = $this->firstOpenSlotMoment($schedule);

            // Varias franjas consecutivas: la fiesta cabe entera aunque la rejilla se consulte por
            // tramo, y el lock de zona×fecha las cubre todas igualmente.
            $slot = null;
            for ($h = 0; $h < 3; $h++) {
                $start = Carbon::parse($time)->addHours($h)->format('H:i:s');
                $created = Slot::create([
                    'zone_id' => $zone->id, 'date' => $date,
                    'start_time' => $start,
                    'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                    'capacity' => 100, 'online_capacity' => 100,
                ]);
                $slot ??= $created;
            }

            $type = TicketType::create([
                'name' => ['es' => 'Cumple Probe'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
                'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
                'seats_per_unit' => 1, 'position' => 1,
                'min_qty' => 1, 'max_qty' => 20,
                'prep_before_min' => 0, 'prep_after_min' => 0,
                'event_fields' => [],   // sin campos obligatorios: se mide el aforo, no la validación
            ]);

            $dayRateId = (int) app(RateResolver::class)->for(Carbon::parse($date))->id;
            $type->prices()->create(['rate_type_id' => $dayRateId, 'amount_cents' => 15000]);
            if ($dayRateId !== $rateId) {
                $type->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 15000]);
            }

            $users = [];
            for ($i = 0; $i < $workers; $i++) {
                $users[] = User::forceCreate([
                    'name' => 'Pack Buyer '.$i,
                    'email' => 'pack-buyer-'.Str::random(8).'@deleted.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
            }

            $cart = [[
                'ticket_type_id' => $type->id, 'date' => $date, 'time' => $time,
                'qty' => $guestsPerBuyer, 'event_data' => [],
            ]];

            return [
                'zone' => $zone, 'type' => $type, 'slot' => $slot->fresh('zone'), 'users' => $users,
                'date' => $date, 'time' => $time, 'cart' => $cart,
                'scenario' => $scenario,
                'probe_qty' => $guestsPerBuyer,
                'expected_winners' => 1,
            ];
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

        $scenario = $seed['scenario'] ?? 'entry';
        $winners = (int) ($seed['expected_winners'] ?? 1);

        // ⚠️ **El invariante DURO es distinto en cada escenario, y ésa es la razón de separarlos.**
        // No basta con contar cuántas compras «crearon»: lo que importa es lo que quedó ESCRITO en la
        // BD, porque una sobreventa se ve ahí aunque los outcomes parezcan correctos.
        [$label, $expected, $actual] = match ($scenario) {
            // Cupo de FIESTAS: nº de líneas de pack vivas en la franja ≤ `zones.max_per_slot`.
            'pack' => [
                'Fiestas vivas en la franja',
                (int) $seed['zone']->max_per_slot,
                $this->livePackLinesInSlot($seed['slot']),
            ],
            // Cupo de INVITADOS: suma de `quantity` de las fiestas vivas ≤ `zones.max_guests_per_slot`.
            'pack-guests' => [
                'Invitados reservados en la franja',
                (int) $seed['probe_qty'],   // solo cabe UNA fiesta de 6: dos serían 12 > 10
                $this->livePackGuestsInSlot($seed['slot']),
            ],
            default => [
                'Asientos reservados en la franja',
                (int) $seed['slot']->online_capacity,
                $reservedSeats,
            ],
        };

        $this->newLine();
        $this->table(
            ['Invariante', 'Esperado', 'Real', 'OK'],
            [
                [$label, (string) $expected, (string) $actual, $this->ok($actual === $expected)],
                ['Compras con éxito', (string) $winners, (string) $created->count(), $this->ok($created->count() === $winners)],
                ['Compras rechazadas (sold_out)', (string) ($workers - $winners), (string) $soldOut->count(), $this->ok($soldOut->count() === $workers - $winners)],
                ['Errores inesperados', '0', (string) $errors->count(), $this->ok($errors->isEmpty())],
            ]
        );

        $pass = $actual === $expected
            && $created->count() === $winners
            && $soldOut->count() === $workers - $winners
            && $errors->isEmpty();

        $this->newLine();
        if ($pass) {
            $this->info("✅ PASA [{$scenario}]: bajo {$workers} compras concurrentes del último hueco el lockSlots serializó correctamente — UNA sola ganó, SIN sobreventa. Verificado sobre InnoDB real.");
        } else {
            $this->error("❌ FALLA [{$scenario}]: SOBREVENTA o invariante roto. Revisar que lockSlots() sea la PRIMERA sentencia de la transacción de OrderCreator.");
        }

        return $pass;
    }

    /** Líneas de PACK vivas (pedido pagado o pendiente no caducado) que empiezan en esta franja. */
    private function livePackLinesInSlot(Slot $slot): int
    {
        return (int) $this->livePackLines($slot)->count();
    }

    /** Invitados (suma de `quantity`) de las fiestas vivas de esta franja. */
    private function livePackGuestsInSlot(Slot $slot): int
    {
        return (int) $this->livePackLines($slot)->sum('order_items.quantity');
    }

    /** @return Builder<OrderItem> */
    private function livePackLines(Slot $slot)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.slot_id', $slot->id)
            ->whereNull('order_items.cancelled_at')
            ->where(fn ($q) => $q->where('orders.status', Order::STATUS_PAID)
                ->orWhere(fn ($q2) => $q2->where('orders.status', Order::STATUS_PENDING)
                    ->where(fn ($q3) => $q3->whereNull('orders.expires_at')->orWhere('orders.expires_at', '>', now()))));
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
        // ⚠️ Por ZONA, no por el id de la franja sembrada: el escenario de packs crea VARIAS y
        // borrar solo la primera dejaría basura en la BD de desarrollo tras cada ejecución.
        Slot::where('zone_id', $seed['zone']->id)->delete();
        TicketType::where('id', $seed['type']->id)->delete();
        User::whereIn('id', $userIds)->delete();
        Zone::where('id', $seed['zone']->id)->delete();
        $this->line('<fg=gray>Datos de prueba borrados.</>');
    }
}
