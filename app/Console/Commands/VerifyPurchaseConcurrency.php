<?php

namespace App\Console\Commands;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountAdjuster;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * De ahí los escenarios, y son varios porque **son invariantes distintos**:
     *  · `entry`       — el pool de asientos de una franja (`SlotAvailability`).
     *  · `pack`        — el tope de **FIESTAS** por franja (`zones.max_per_slot`).
     *  · `pack-guests` — el tope de **NIÑOS** por franja (`zones.max_guests_per_slot`).
     *  · `pack-prep`   — el mismo tope de fiestas pero con la fiesta **abarcando varias franjas**
     *                    (duración + montaje + limpieza) y compradores pidiendo **horas DISTINTAS**
     *                    que se pisan. ⚠️ Es la configuración **por defecto en producción**, y hasta
     *                    el 2026-08-25 se medía siempre con la preparación APAGADA.
     *  · `mixed`       — una entrada y un cumpleaños **compitiendo a la vez** en la misma zona y
     *                    franja. ⚠️ **Su nº de ganadores VARÍA entre ejecuciones y es correcto**:
     *                    mide TOPES, no ganadores (ver {@see self::evaluateMixed()}).
     *  · `panel-edit`  — N EDICIONES DE PANEL concurrentes (`OrderItemEditor::changeSlot()`, la
     *                    operación del panel desde la extracción 4b) moviendo ítems multi-franja a
     *                    DOS destinos distintos cuyas ventanas pisan una franja intermedia con UNA
     *                    plaza. Es el hueco que `AFORO-05` documenta («el ALCANCE zona/día del lock
     *                    no tiene assert») y el instrumento que la extracción 4 del desmontaje de
     *                    `ViewOrder` exige (spec §6·4): con `ZoneDaySlotLock` real (tomado por
     *                    `withZoneDayLock`, el punto ÚNICO de lock del editor) gana UNO; sin él, o
     *                    con un lock de solo-la-fila-destino, los dos destinos no comparten fila y
     *                    la franja intermedia se sobrevende.
     *
     *  · `extra-hour`  — la HORA EXTRA (`specs/hora-extra.md` §6·1): la última plaza en disputa es
     *                    la de una franja que la mitad de los compradores quiere como ENTRADA
     *                    directa y la otra mitad como **hija que OCUPA** (un complemento con
     *                    `occupies_after_parent` colgado de una entrada de la franja anterior).
     *                    Con la validación de la hija bajo el lock gana UNO, sea del bando que sea;
     *                    sin ella, el comprador de hora extra ESCRIBE sin comprobar y la franja se
     *                    sobrevende — **incluso sin carrera**, que es lo que obligó a verlo fallar
     *                    antes de construir (§7·D1).
     *
     * Se separan a propósito: un cupo de fiestas correcto no dice nada sobre el de invitados, y
     * viceversa. En un escenario único, el que se rompiera se escondería detrás del que aguantara.
     */
    private const SCENARIOS = ['entry', 'pack', 'pack-guests', 'pack-prep', 'mixed', 'panel-edit', 'extra-hour', 'stay-extension', 'stay-extension-per-guest', 'guest-count'];

    /**
     * Los DOS escenarios de la hora extra de un pack: el mismo aforo con las dos unidades de
     * cantidad que el enganche puede declarar (`specs/hora-extra.md` §11, `#443`).
     *
     * ⚠️⚠️ **El de por-invitado no es una variante cosmética**: ahí la cantidad de la hija son
     * PERSONAS y los minutos salen de `AddonOccupancy::blocksFor()`, así que es el único que mide
     * bajo carrera que **el precio escala con los invitados y la ventana NO**. Si esa derivación se
     * rompiera, la fiesta ocuparía `invitados × 60` minutos y la sala se cerraría sola — verde en la
     * suite, porque SQLite no ejerce el lock.
     *
     * @var list<string>
     */
    private const STAY_SCENARIOS = ['stay-extension', 'stay-extension-per-guest'];

    protected $signature = 'purchase:verify-oversell
        {--workers=8 : Nº de compras concurrentes (procesos)}
        {--scenario=entry : Qué aforo se prueba: entry | pack | pack-guests | pack-prep | mixed | panel-edit | extra-hour | stay-extension | stay-extension-per-guest | guest-count}
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
        $seed = match ($scenario) {
            'entry' => $this->seedEntryScenario($workers),
            'mixed' => $this->seedMixedScenario($workers),
            'panel-edit' => $this->seedPanelEditScenario($workers),
            'guest-count' => $this->seedGuestCountScenario($workers),
            'extra-hour' => $this->seedExtraHourScenario($workers),
            default => $this->seedPackScenario($workers, $scenario),
        };

        try {
            // ⚠️⚠️ **La guarda del propio instrumento, y no es opcional.** Si el escenario está mal
            // montado —el pack no cabe en la rejilla, falta precio ese día, la franja quedó fuera de
            // horario— los N compradores son rechazados por un motivo que NO es la carrera, y el
            // verificador informaría de «0 sobreventas» sin haber probado nada. Un verificador que no
            // puede vender ni una sola vez sale verde por construcción.
            // ⚠️ Va DENTRO del try: hasta el 2026-08-26 una guarda fallida salía por `return` ANTES
            // del finally y FUGABA la siembra entera a la BD de desarrollo (medido: una zona, un rol
            // y nueve pedidos huérfanos tras un fallo de sonda del escenario `panel-edit`).
            if (! $this->probeSellsOnce($seed)) {
                return self::FAILURE;
            }

            $this->line("Disparando <fg=yellow>{$workers}</> compras <options=bold>CONCURRENTES</> del último hueco sobre {$driver}…");

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
        $scenario = $seed['scenario'];

        // El escenario de PANEL no compra: MUEVE. Su guarda es distinta — el camino ENTERO de la
        // edición (permiso, validación, lock zona/día, save) tiene que poder actuar una vez, y
        // deshacerse, antes de forkar. Si no puede, los N workers serían bloqueados por siembra
        // (permiso ausente, hora fuera de ventana…) y el «no hubo sobreventa» no mediría nada.
        if ($scenario === 'panel-edit') {
            return $this->probePanelEditActs($seed);
        }

        // La hora extra recorre un camino que `availableFor` a secas no cubre (resolver el
        // complemento → componer la hija → validarla → escribirla): su guarda ejecuta ese camino
        // ENTERO una vez y lo deshace, como hace la del panel.
        if ($scenario === 'extra-hour') {
            return $this->probeExtraHourActs($seed);
        }

        // La HORA EXTRA DE UN PACK mide una AUSENCIA —que nadie pueda comprar—, así que su guarda
        // tiene que demostrar que el hueco EXISTÍA antes de alargar la fiesta. Es el control del
        // escenario, y sin él «12 rechazos» se leería como éxito con la siembra rota.
        if ($scenario === 'stay-extension') {
            return $this->probeStayExtensionActs($seed);
        }

        // ⚠️ Se comprueba CADA pool que el escenario pone en juego, no «el» hueco. En `mixed` hay
        // dos —asientos y cupo de fiestas— y con uno solo verificado el otro podría estar cerrado
        // sin que se notara: sus compradores serían rechazados por siembra, no por la carrera.
        $checks = [];
        if ($scenario === 'entry' || $scenario === 'mixed') {
            $checks['entrada'] = [
                app(SlotAvailability::class)->availableFor($seed['slot'], $seed['type']->duration_min),
                1,
            ];
        }
        if ($scenario !== 'entry') {
            $pack = $seed['pack'] ?? $seed['type'];
            $checks['cumpleaños'] = [
                app(PackAvailability::class)->availableGuestsFor($seed['slot'], $pack),
                $scenario === 'mixed' ? 8 : (int) $seed['probe_qty'],
            ];
        }

        // ⚠️ Y en `pack-prep` los compradores piden DOS horas distintas: si la segunda no vendiera
        // (rejilla corta, fuera de horario), la mitad de los workers serían rechazados por siembra
        // y el resultado —«1 ganador»— saldría verde por el motivo equivocado.
        if ($scenario === 'pack-prep' || $scenario === 'stay-extension') {
            $second = Carbon::parse($seed['time'])->addHour()->format('H:i:s');
            $slot2 = Slot::where('zone_id', $seed['zone']->id)
                ->where('date', $seed['date'])->where('start_time', $second)->first();
            $checks['cumpleaños @'.$second] = [
                $slot2 === null ? 0 : app(PackAvailability::class)->availableGuestsFor($slot2->fresh('zone'), $seed['type']),
                (int) $seed['probe_qty'],
            ];
        }

        $failed = [];
        foreach ($checks as $what => [$available, $needed]) {
            if ($available >= $needed) {
                $this->line("<fg=gray>Guarda del instrumento · {$what}: el hueco admite {$available} (se pedirán {$needed}). ✓</>");
            } else {
                $failed[] = "{$what}: ofrece {$available}, se piden {$needed}";
            }
        }

        if ($failed === []) {
            return true;
        }

        $this->error(
            "El escenario NO permite vender ni una vez en:\n  · ".implode("\n  · ", $failed)."\n".
            "▶ Esos compradores serían rechazados por un motivo que NO es la carrera, y el resultado\n".
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
            $carts = array_fill(0, $workers, $cart);   // todos pujan por la misma plaza
            $scenario = 'entry';
            $probe_qty = 1;
            $expected_winners = 1;

            return compact('zone', 'type', 'slot', 'users', 'date', 'time', 'cart', 'carts',
                'scenario', 'probe_qty', 'expected_winners');
        });
    }

    /**
     * **Escenario de la HORA EXTRA** (`specs/hora-extra.md` §6·1): dos franjas consecutivas — S1
     * holgada y S2 con **UNA** plaza — y dos bandos pujando por la de S2: los pares la quieren como
     * ENTRADA directa; los impares compran la entrada de S1 con el complemento «hora extra», cuya
     * línea HIJA ocupa S2 (`occupies_after_parent`, la franja siguiente al tramo del padre).
     *
     * El invariante es UNO: gane quien gane, en S2 queda exactamente 1 plaza escrita. El comprador
     * de hora extra rechazado sale con `addon_occupancy_line` y el directo con `sold_out_line`; a
     * este verificador le dan igual los motivos — mira lo ESCRITO.
     *
     * ⚠️ La franja de la HIJA la valida `OrderCreator` bajo el mismo lock zona/día que la del padre
     * (`ZoneDaySlotLock` trae la zona/día enteros). Sin esa validación, la hija se ESCRIBE sin
     * comprobar S2 y la sobreventa ni siquiera necesita carrera — se vio fallar así antes de
     * construir la validación (§7·D1).
     *
     * @return array{zone:Zone, type:TicketType, addon:TicketType, slot:Slot, users:array<int,User>, date:string, time:string, cart:array<int,array<string,mixed>>, carts:array<int,array<int,array<string,mixed>>>, scenario:string, probe_qty:int, expected_winners:int}
     */
    private function seedExtraHourScenario(int $workers): array
    {
        return DB::transaction(function () use ($workers): array {
            $rateId = (int) (RateType::firstOrCreate(
                ['key' => RateType::KEY_NORMAL],
                ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0],
            )->id);

            $zone = Zone::create([
                'slug' => 'xh-probe-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'Hora Extra Probe'],
                'is_active' => true,
            ]);

            $schedule = app(OperatingSchedule::class);
            [$date, $time] = $this->firstOpenSlotMoment($schedule);
            $secondTime = Carbon::parse($time)->addHour()->format('H:i:s');

            // S1 holgada (los padres siempre caben: la disputa tiene que ser SOLO por S2).
            Slot::create([
                'zone_id' => $zone->id, 'date' => $date,
                'start_time' => $time, 'end_time' => $secondTime,
                'capacity' => 100, 'online_capacity' => 100,
            ]);
            // S2 con UNA plaza: el último hueco en disputa.
            $disputed = Slot::create([
                'zone_id' => $zone->id, 'date' => $date,
                'start_time' => $secondTime, 'end_time' => Carbon::parse($secondTime)->addHour()->format('H:i:s'),
                'capacity' => 1, 'online_capacity' => 1,
            ]);

            $type = TicketType::create([
                'name' => ['es' => 'Entrada XH'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
                'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
            ]);
            $addon = TicketType::create([
                'name' => ['es' => 'Hora extra XH'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => null,
                'duration_min' => 60, 'occupies_after_parent' => true,
                'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
            ]);
            $type->addons()->attach($addon->id, [
                'position' => 1, 'is_included' => false, 'included_quantity' => 1,
                'is_mandatory' => false, 'quantity_mode' => ProductAddon::MODE_FIXED,
                'allow_extra' => true, 'choice_group' => null, 'max_qty' => null, 'requires_addon_id' => null,
            ]);

            // Precios: la ENTRADA a la tarifa del día de la visita; el COMPLEMENTO además a la de
            // HOY, porque `AddonResolver` tarifica a `Carbon::today()` (límite aceptado y escrito,
            // `specs/hora-extra.md` §4.11 — sin ese precio la sonda fallaría por siembra).
            $dayRateId = (int) app(RateResolver::class)->for(Carbon::parse($date))->id;
            $todayRateId = (int) app(RateResolver::class)->for(Carbon::today())->id;
            foreach (array_unique([$dayRateId, $rateId]) as $r) {
                $type->prices()->create(['rate_type_id' => $r, 'amount_cents' => 1000]);
            }
            foreach (array_unique([$todayRateId, $dayRateId, $rateId]) as $r) {
                $addon->prices()->create(['rate_type_id' => $r, 'amount_cents' => 500]);
            }

            $users = [];
            for ($i = 0; $i < $workers; $i++) {
                $users[] = User::forceCreate([
                    'name' => 'XH Buyer '.$i,
                    'email' => 'xh-buyer-'.Str::random(8).'@deleted.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
            }

            $directCart = [['ticket_type_id' => $type->id, 'date' => $date, 'time' => $secondTime, 'qty' => 1]];
            $extraCart = [[
                'ticket_type_id' => $type->id, 'date' => $date, 'time' => $time, 'qty' => 1,
                'addons' => [['ticket_type_id' => $addon->id, 'qty' => 1]],
            ]];
            $carts = [];
            for ($i = 0; $i < $workers; $i++) {
                $carts[$i] = $i % 2 === 0 ? $directCart : $extraCart;
            }

            return [
                'zone' => $zone, 'type' => $type, 'addon' => $addon, 'slot' => $disputed->fresh('zone'),
                'users' => $users, 'date' => $date, 'time' => $time,
                'cart' => $extraCart, 'carts' => $carts,
                'scenario' => 'extra-hour', 'probe_qty' => 1, 'expected_winners' => 1,
            ];
        });
    }

    /**
     * **La guarda del instrumento para `extra-hour`: el camino ENTERO de la hora extra, una vez y
     * deshecho.** Un `availableFor` a secas no lo cubre — el rechazo de un comprador impar puede
     * venir de resolver el complemento (sin precio HOY, pivote mal sembrado, guard del modelo) y
     * ningún conteo lo distinguiría de «no hubo sobreventa». Se compra de verdad la cesta con hora
     * extra dentro de una transacción EXTERNA (la interna de `OrderCreator` se vuelve savepoint) y
     * se comprueba que la hija nació con la franja en disputa y su plaza; el rollback lo deshace.
     *
     * @param  array{slot:Slot, users:array<int,User>, carts:array<int,array<int,array<string,mixed>>>}  $seed
     */
    /**
     * **Guarda del instrumento de `stay-extension`, y a la vez su CONTROL** (`specs/hora-extra.md`
     * §10): la segunda hora tiene que estar VENDIBLE con la fiesta anterior sin alargar, y dejar de
     * estarlo en cuanto se le compra la hora extra. Las dos mitades importan — la primera dice que
     * la siembra no está rota, y la segunda que lo que cierra la franja es la EXTENSIÓN y no otra
     * cosa; sin ella, «los 12 rechazados» saldría verde con el cupo mal configurado.
     *
     * @param  array<string, mixed>  $seed
     */
    private function probeStayExtensionActs(array $seed): bool
    {
        $second = Carbon::parse($seed['time'])->addHour()->format('H:i:s');
        $slot2 = Slot::where('zone_id', $seed['zone']->id)
            ->where('date', $seed['date'])->where('start_time', $second)->first()?->fresh('zone');
        $needed = (int) $seed['probe_qty'];

        $antes = $slot2 === null ? 0 : app(PackAvailability::class)->availableGuestsFor($slot2, $seed['type']);
        if ($antes < $needed) {
            $this->error("Guarda del instrumento · la segunda hora ofrece {$antes} y se pedirán {$needed} ANTES de alargar la fiesta: siembra rota.");

            return false;
        }
        $this->line("<fg=gray>Guarda del instrumento · la segunda hora admite {$antes} con la fiesta anterior sin alargar. ✓</>");

        // Se alarga la fiesta ya vendida. Se escribe el HECHO directamente porque esto es siembra y
        // no una venta: el camino de compra ya lo cubren los casos de `PackStayExtensionTest`, y el
        // editor del panel rechaza tocar extensiones hasta la T3 a propósito.
        $seed['seeded_item']->forceFill([
            'extra_minutes' => (int) $seed['extender']->duration_min,
        ])->save();

        $despues = app(PackAvailability::class)->availableGuestsFor($slot2, $seed['type']);
        if ($despues !== 0) {
            $this->error(
                "Guarda del instrumento · tras comprar la hora extra la segunda hora sigue ofreciendo {$despues}.\n".
                '▶ El escenario NO mide la extensión: los 12 rechazos vendrían de otra cosa.'
            );

            return false;
        }
        $this->line('<fg=gray>Guarda del instrumento · con la hora extra comprada, la segunda hora cierra (0). ✓</>');

        return true;
    }

    private function probeExtraHourActs(array $seed): bool
    {
        $direct = app(SlotAvailability::class)->availableFor($seed['slot'], 60);
        if ($direct < 1) {
            $this->error("Guarda del instrumento · la franja en disputa ofrece {$direct} y hace falta 1: siembra rota.");

            return false;
        }
        $this->line("<fg=gray>Guarda del instrumento · entrada directa: la franja en disputa admite {$direct}. ✓</>");

        DB::beginTransaction();
        try {
            $buyer = User::find($seed['users'][1]->id);
            $order = app(OrderCreator::class)->createPendingOrder($buyer, $seed['carts'][1]);
            /** @var OrderItem|null $child */
            $child = $order->items()->whereNotNull('parent_item_id')->first();
            $ok = $child !== null
                && (int) $child->slot_id === (int) $seed['slot']->id
                && (int) $child->seats === 1;
        } catch (\Throwable $e) {
            $this->error('Guarda del instrumento · la compra con hora extra no se pudo crear ni una vez: '.$e->getMessage());

            return false;
        } finally {
            DB::rollBack();
        }

        if (! $ok) {
            $this->error('Guarda del instrumento · la hija NO nació ocupando la franja en disputa (slot/seats): siembra o composición rotas.');

            return false;
        }
        $this->line('<fg=gray>Guarda del instrumento · hora extra: la hija nace con la franja en disputa y su plaza, y se deshizo. ✓</>');

        return true;
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
            //  · `pack-prep`   → 1 fiesta, y la fiesta ABARCA VARIAS FRANJAS: 120 min de duración
            //                    más 60 de montaje y 60 de limpieza. Ver el docblock del método.
            $isGuests = $scenario === 'pack-guests';
            $isPrep = $scenario === 'pack-prep';
            //  · `stay-extension` → LA HORA EXTRA DE UN PACK (`specs/hora-extra.md` §10). Es el
            //    gemelo de `pack-prep` con otro mecanismo: los compradores piden HORAS DISTINTAS que
            //    **solo colisionan por la extensión** —la fiesta de las 11:00 con una hora extra
            //    ocupa hasta las 13:00 y pisa a la de las 12:00—. ⚠️⚠️ Sin la extensión las dos
            //    caben (60 min cada una, franjas contiguas y disjuntas), así que un solo ganador
            //    aquí demuestra que la ventana ALARGADA entra en el cupo bajo el lock. Es el
            //    escenario que la revisión adversarial pidió (`#423` · A9) acotado a lo que esta
            //    tanda hace vendible: el cruce con el PANEL llegará con la T3, porque hoy el editor
            //    rechaza tocar una extensión a propósito.
            $isStay = $scenario === 'stay-extension';
            //  · `stay-extension-per-guest` → LA HORA EXTRA COBRADA POR INVITADO (§11, `#443`), y
            //    **NO es una variante cosmética del anterior: mide otra cosa y por otro camino**.
            //    Aquí NO hay fiesta sembrada: los 12 compran la MISMA sala **con** su hora extra,
            //    en modo por-invitado, y **uno solo debe ganar**. Lo que lo hace valioso es el modo
            //    de fallo: si los minutos volvieran a salir de la cantidad, cada compra pediría
            //    `invitados × 60` = 480 min, la ventana no cabría en la rejilla y **ganaría CERO**.
            //    Un escenario que distingue «uno gana» de «no gana nadie» mide la derivación a
            //    través del checkout y bajo el lock, que es donde la suite es ciega (SQLite).
            $isStayPerGuest = $scenario === 'stay-extension-per-guest';
            $needsExtender = $isStay || $isStayPerGuest;

            $guestsPerBuyer = $isGuests ? 6 : 8;
            $zone = Zone::create([
                'slug' => 'pack-probe-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'Pack Probe'],
                'is_active' => true,
                'max_per_slot' => $isGuests ? 0 : 1,
                'max_guests_per_slot' => $isGuests ? 10 : 0,
                // ⚠️ `true` en `pack-prep` porque **es el valor por defecto en producción**: hasta
                // ahora se medía siempre con la preparación APAGADA, o sea con una configuración
                // que ningún parque usa.
                'prep_blocks_cupo' => $isPrep,
            ]);

            $schedule = app(OperatingSchedule::class);
            [$date, $time] = $this->firstOpenSlotMoment($schedule);

            // Franjas consecutivas suficientes para que la fiesta quepa entera. Con preparación la
            // ventana se extiende una hora ANTES del inicio, así que la rejilla empieza antes.
            $firstHour = $isPrep ? -1 : 0;
            $lastHour = $isPrep ? 5 : 2;
            $slot = null;
            for ($h = $firstHour; $h <= $lastHour; $h++) {
                $start = Carbon::parse($time)->addHours($h)->format('H:i:s');
                $created = Slot::create([
                    'zone_id' => $zone->id, 'date' => $date,
                    'start_time' => $start,
                    'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                    'capacity' => 100, 'online_capacity' => 100,
                ]);
                if ($start === $time) {
                    $slot = $created;
                }
            }

            $type = TicketType::create([
                'name' => ['es' => 'Cumple Probe'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
                'duration_min' => $isPrep ? 120 : 60, 'is_sellable' => true, 'is_active' => true,
                'seats_per_unit' => 1, 'position' => 1,
                'min_qty' => 1, 'max_qty' => 20,
                'prep_before_min' => $isPrep ? 60 : 0, 'prep_after_min' => $isPrep ? 60 : 0,
                'event_fields' => [],   // sin campos obligatorios: se mide el aforo, no la validación
            ]);

            $dayRateId = (int) app(RateResolver::class)->for(Carbon::parse($date))->id;
            $type->prices()->create(['rate_type_id' => $dayRateId, 'amount_cents' => 15000]);
            if ($dayRateId !== $rateId) {
                $type->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 15000]);
            }

            $extender = null;
            if ($needsExtender) {
                $extender = TicketType::create([
                    'name' => ['es' => 'Hora extra Probe'], 'type' => TicketType::TYPE_ADDON,
                    'duration_min' => 60, 'extends_parent_stay' => true,
                    'is_sellable' => true, 'is_active' => true, 'position' => 2,
                ]);
                $extender->prices()->create(['rate_type_id' => $dayRateId, 'amount_cents' => 5000]);
                if ($dayRateId !== $rateId) {
                    $extender->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 5000]);
                }
                $type->addons()->attach($extender->id, [
                    'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
                    'quantity_mode' => $isStayPerGuest ? ProductAddon::MODE_PER_GUEST : ProductAddon::MODE_FIXED,
                    'allow_extra' => true,
                    'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
                    'max_qty' => $isStayPerGuest ? null : 1,
                ]);
            }

            $users = [];
            for ($i = 0; $i < $workers; $i++) {
                $users[] = User::forceCreate([
                    'name' => 'Pack Buyer '.$i,
                    'email' => 'pack-buyer-'.Str::random(8).'@deleted.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
            }

            $line = function (string $at, bool $withExtension = false) use ($type, $date, $guestsPerBuyer, $extender): array {
                $row = [
                    'ticket_type_id' => $type->id, 'date' => $date, 'time' => $at,
                    'qty' => $guestsPerBuyer, 'event_data' => [],
                ];
                if ($withExtension && $extender !== null) {
                    $row['addons'] = [['ticket_type_id' => $extender->id, 'qty' => 1]];
                }

                return [$row];
            };

            // ⚠️⚠️ **En `pack-prep` los compradores piden HORAS DISTINTAS.** Una fiesta de las 11:00
            // ocupa de 10:00 a 14:00 (montaje + 2 h + limpieza) y otra de las 12:00 ocuparía de 11:00
            // a 15:00: **se pisan sin compartir hora de inicio**. Es el caso que ningún escenario
            // anterior podía expresar, porque todos los workers compartían una única cesta.
            $secondTime = Carbon::parse($time)->addHour()->format('H:i:s');
            $carts = [];
            for ($i = 0; $i < $workers; $i++) {
                // ⚠️⚠️ En `stay-extension` **todos** pujan por la SEGUNDA hora, y la primera ya está
                // vendida con su extensión (se siembra abajo). La primera versión repartía los
                // workers entre las dos horas y **no medía nada**: el que compraba la extensión hace
                // más trabajo —resolver el complemento y su precio— y llegaba SIEMPRE tarde al lock,
                // así que con el defecto puesto salía verde **4 de 4 veces**. Un escenario cuyo
                // veredicto depende de quién gane la carrera no es un escenario: es una moneda.
                $carts[$i] = match (true) {
                    $isStay => $line($secondTime),
                    // Todos a la MISMA sala y a la misma hora, cada uno con su hora extra.
                    $isStayPerGuest => $line($time, withExtension: true),
                    default => $line($isPrep && $i % 2 === 1 ? $secondTime : $time),
                };
            }

            // La fiesta que YA está vendida en la primera hora, con su hora extra. Se siembra sin
            // extensión y se alarga después (`probeStayExtensionActs`), para poder medir el hueco
            // ANTES y DESPUÉS: ése es el control que demuestra que lo que cierra la franja es la
            // extensión y no la siembra.
            $seededItem = null;
            if ($isStay) {
                $seededOrder = Order::create([
                    'user_id' => $users[0]->id,
                    'code' => 'PROBE-'.Str::upper(Str::random(6)),
                    'status' => Order::STATUS_PAID,
                    'total' => 15000,
                ]);
                $seededItem = $seededOrder->items()->create([
                    'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
                    'quantity' => $guestsPerBuyer, 'unit_price' => 15000,
                    'seats' => $guestsPerBuyer, 'extra_minutes' => 0,
                ]);
            }

            return [
                'zone' => $zone, 'type' => $type, 'slot' => $slot->fresh('zone'), 'users' => $users,
                'date' => $date, 'time' => $time, 'cart' => $line($time), 'carts' => $carts,
                'scenario' => $scenario,
                'probe_qty' => $guestsPerBuyer,
                'seeded_item' => $seededItem,
                'extender' => $extender,
                // ⚠️ En `stay-extension` NADIE debe ganar: la sala está ocupada por la extensión de
                // la fiesta anterior. El «alguien vendió» que exigen los demás escenarios lo aporta
                // aquí la guarda del instrumento, que mide el hueco ANTES de alargar la fiesta.
                'expected_winners' => $isStay ? 0 : 1,
            ];
        });
    }

    /**
     * **Escenario MIXTO: los DOS pools compitiendo en la misma tanda.**
     *
     * Es el caso más parecido a la realidad y el único que ninguno de los otros cuatro puede
     * expresar: en la misma zona, la misma franja y el mismo instante, la mitad de los compradores
     * pujan por **la última plaza de ENTRADA** y la otra mitad por **el último hueco de FIESTA**.
     *
     * ⚠️⚠️ **El invariante aquí tiene DOS ganadores, no uno**, y ésa es exactamente la propiedad que
     * se mide: los pools son independientes —un cumpleaños no resta plazas de entrada ni al revés—,
     * así que **debe entrar una de cada**. Un solo ganador significaría que un pool está robando
     * cupo al otro; tres o más, que alguno se sobrevendió.
     *
     * ⚠️ Los dos productos comparten **zona y franja** a propósito. Con zonas separadas el `lockSlots`
     * bloquearía conjuntos distintos y la carrera no llegaría a existir: no habría nada que medir.
     *
     * @return array{zone:Zone, type:TicketType, pack:TicketType, slot:Slot, users:array<int,User>, date:string, time:string, cart:array<int,array<string,mixed>>, carts:array<int,array<int,array<string,mixed>>>, scenario:string, probe_qty:int, expected_winners:int}
     */
    private function seedMixedScenario(int $workers): array
    {
        return DB::transaction(function () use ($workers): array {
            $rateId = (int) (RateType::firstOrCreate(
                ['key' => RateType::KEY_NORMAL],
                ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0],
            )->id);

            $zone = Zone::create([
                'slug' => 'mixed-probe-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'Mixed Probe'],
                'is_active' => true,
                'max_per_slot' => 1,            // UN cumpleaños
                'max_guests_per_slot' => 0,
                'prep_blocks_cupo' => false,
            ]);

            $schedule = app(OperatingSchedule::class);
            [$date, $time] = $this->firstOpenSlotMoment($schedule);

            $slot = null;
            for ($h = 0; $h <= 2; $h++) {
                $start = Carbon::parse($time)->addHours($h)->format('H:i:s');
                $created = Slot::create([
                    'zone_id' => $zone->id, 'date' => $date,
                    'start_time' => $start,
                    'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                    'capacity' => 1, 'online_capacity' => 1,   // UNA plaza de entrada
                ]);
                if ($start === $time) {
                    $slot = $created;
                }
            }

            $entry = TicketType::create([
                'name' => ['es' => 'Entrada Mixed'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
                'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
                'seats_per_unit' => 1, 'position' => 1,
            ]);
            $pack = TicketType::create([
                'name' => ['es' => 'Cumple Mixed'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
                'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
                'seats_per_unit' => 1, 'position' => 2,
                'min_qty' => 1, 'max_qty' => 20,
                'prep_before_min' => 0, 'prep_after_min' => 0,
                'event_fields' => [],
            ]);

            $dayRateId = (int) app(RateResolver::class)->for(Carbon::parse($date))->id;
            foreach ([[$entry, 1000], [$pack, 15000]] as [$product, $cents]) {
                $product->prices()->create(['rate_type_id' => $dayRateId, 'amount_cents' => $cents]);
                if ($dayRateId !== $rateId) {
                    $product->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => $cents]);
                }
            }

            $users = [];
            for ($i = 0; $i < $workers; $i++) {
                $users[] = User::forceCreate([
                    'name' => 'Mixed Buyer '.$i,
                    'email' => 'mixed-buyer-'.Str::random(8).'@deleted.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
            }

            // Pares → entrada · impares → cumpleaños. Con `--workers` par, mitad y mitad.
            $carts = [];
            for ($i = 0; $i < $workers; $i++) {
                $carts[$i] = $i % 2 === 0
                    ? [['ticket_type_id' => $entry->id, 'date' => $date, 'time' => $time, 'qty' => 1]]
                    : [['ticket_type_id' => $pack->id, 'date' => $date, 'time' => $time, 'qty' => 8, 'event_data' => []]];
            }

            return [
                'zone' => $zone, 'type' => $entry, 'pack' => $pack, 'slot' => $slot->fresh('zone'),
                'users' => $users, 'date' => $date, 'time' => $time,
                'cart' => $carts[0], 'carts' => $carts,
                'scenario' => 'mixed',
                'probe_qty' => 1,
                'expected_winners' => 2,   // ⚠️ UNA entrada Y UN cumpleaños: dos pools, dos ganadores
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
     * Forka N procesos; cada uno intenta comprar el último hueco al mismo instante. Cada hijo
     * escribe su outcome (created / sold_out / error) a un fichero.
     *
     * ⚠️ **Cada worker lleva SU PROPIA cesta** (`$seed['carts'][$i]`), no una compartida. Los tres
     * primeros escenarios reparten N cestas idénticas —todos pujan por el mismo hueco—, pero hay dos
     * que no podrían existir con una sola:
     *  · `pack-prep` — los compradores piden **horas DISTINTAS** que se solapan por el montaje y la
     *    limpieza. Con una cesta común no habría forma de expresar «11:00 contra 12:00».
     *  · `mixed`     — mitad compra una entrada y mitad un cumpleaños, para que **dos pools
     *    distintos** compitan dentro de la misma tanda.
     *
     * @param  array{users:array<int,User>, carts:array<int,array<int,array<string,mixed>>>}  $seed
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
                    if (($seed['scenario'] ?? '') === 'panel-edit') {
                        $outcome = $this->panelEditMove($seed, $i);
                    } elseif (($seed['scenario'] ?? '') === 'guest-count') {
                        $outcome = $this->guestCountRaise($seed, $i);
                    } else {
                        $buyer = User::find($user->id);
                        $order = app(OrderCreator::class)->createPendingOrder($buyer, $seed['carts'][$i]);
                        $outcome = 'created:'.$order->id;
                    }
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
        if (($seed['scenario'] ?? '') === 'panel-edit') {
            return $this->evaluatePanelEdit($seed, $workers, $resultsDir);
        }

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

        // ⚠️⚠️ **`mixed` no tiene un número fijo de ganadores, y descubrirlo fue el hallazgo.**
        // Medido: dos ejecuciones idénticas dieron 2 y 1 ganadores. No es un fallo — es que los dos
        // pools **se estorban en una dirección**: `SlotAvailability::occupancyMap` suma los `seats`
        // de TODOS los items de la franja, packs incluidos, así que una fiesta de 8 invitados llena
        // una franja de 1 plaza. Si la entrada llega primero, entran las dos; si llega primero el
        // cumpleaños, la entrada se queda fuera.
        // ▶ Pedirle «exactamente 2 ganadores» sería exigir determinismo a una carrera legítima. Lo
        //   que SÍ debe cumplirse siempre es que **ningún tope se supere** y que **alguien venda**
        //   (si no vendiera nadie, el escenario no habría medido nada).
        if ($scenario === 'mixed') {
            return $this->evaluateMixed($seed, $workers, $created, $soldOut, $errors);
        }

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
            // ⚠️ Con preparación, las fiestas se pisan SIN compartir hora de inicio: contar solo la
            // franja sembrada dejaría fuera a la ganadora de la hora siguiente y el invariante daría
            // verde con DOS fiestas vendidas. Se cuentan las de todo el DÍA en la zona.
            'pack-prep' => [
                'Fiestas vivas en el día (se pisan por el montaje)',
                1,
                $this->livePackLinesInZoneDay($seed['zone']->id, $seed['date']),
            ],
            // ⚠️⚠️ Mismo razonamiento que `pack-prep`, con el otro mecanismo: aquí las fiestas se
            // pisan **por la EXTENSIÓN** y tampoco comparten hora de inicio, así que contar solo la
            // franja sembrada dejaría fuera a la ganadora de la hora siguiente y el invariante daría
            // verde con DOS fiestas vendidas — que es exactamente el hueco de `specs/hora-extra.md`
            // §10.1. Se cuentan las del DÍA en la zona.
            'stay-extension', 'stay-extension-per-guest' => [
                'Fiestas vivas en el día (la sembrada, y ninguna más)',
                1,
                $this->livePackLinesInZoneDay($seed['zone']->id, $seed['date']),
            ],
            // ❗❗ El CLIENTE subiendo invitados desde su post-form (`#444`): el mismo cupo que
            // `pack-guests`, pero movido por otra puerta. Con 12 fiestas de 2 y el cupo en 30 quedan
            // 6 plazas: cada worker pide +6 sobre SU reserva y **solo una puede caber**.
            'guest-count' => [
                'Invitados vivos en la franja',
                (int) $seed['zone']->max_guests_per_slot,
                $this->livePackGuestsInSlot($seed['slot']),
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

    /**
     * **Evaluación del escenario MIXTO: se miden TOPES, no un número de ganadores.**
     *
     * ⚠️⚠️ Nació de una medida que contradijo el diseño inicial: dos ejecuciones idénticas dieron
     * **2 y 1 ganadores**. Investigado, la causa está en `SlotAvailability::occupancyMap`, que suma
     * los `seats` de **todos** los items de la franja —los packs también—, así que un cumpleaños de
     * 8 invitados llena una franja de 1 plaza. El orden de llegada decide.
     *
     * ▶ **Y eso contradice lo que el propio dominio afirma**: el docblock de `PackAvailability` dice
     * «POOL PROPIO … un cumpleaños **no resta plazas de entrada** ni viceversa». Medido en frío
     * (10 plazas → una fiesta de 8 → quedan 2): **sí las resta**. La afirmación es falsa en esa
     * dirección. Si es lo deseado —los niños ocupan sitio real— hay que decirlo en la doc; si no, es
     * un defecto. **Es decisión de producto, no del verificador**, y por eso aquí solo se mide.
     *
     * @param  Collection<int,string>  $created
     * @param  Collection<int,string>  $soldOut
     * @param  Collection<int,string>  $errors
     */
    private function evaluateMixed(array $seed, int $workers, $created, $soldOut, $errors): bool
    {
        $entries = $this->liveEntryLinesInSlot($seed['slot']);
        $parties = $this->livePackLinesInSlot($seed['slot']);
        $maxEntries = (int) $seed['slot']->online_capacity;
        $maxParties = (int) $seed['zone']->max_per_slot;

        $this->newLine();
        $this->table(
            ['Invariante', 'Esperado', 'Real', 'OK'],
            [
                ['Entradas vivas en la franja', '≤ '.$maxEntries, (string) $entries, $this->ok($entries <= $maxEntries)],
                ['Fiestas vivas en la franja', '≤ '.$maxParties, (string) $parties, $this->ok($parties <= $maxParties)],
                ['Alguien vendió (el escenario midió algo)', '≥ 1', (string) $created->count(), $this->ok($created->count() >= 1)],
                ['Creadas + rechazadas = compradores', (string) $workers, (string) ($created->count() + $soldOut->count()), $this->ok($created->count() + $soldOut->count() === $workers)],
                ['Errores inesperados', '0', (string) $errors->count(), $this->ok($errors->isEmpty())],
            ]
        );

        $pass = $entries <= $maxEntries
            && $parties <= $maxParties
            && $created->count() >= 1
            && $created->count() + $soldOut->count() === $workers
            && $errors->isEmpty();

        $this->newLine();
        if ($pass) {
            $this->info("✅ PASA [mixed]: con {$workers} compradores pujando a la vez por DOS pools distintos, ninguno de los dos topes se superó ({$entries} entrada(s), {$parties} fiesta(s)).");
            $this->line('<fg=gray>⚠ El nº de ganadores VARÍA entre ejecuciones y es correcto: una fiesta ocupa asientos '.
                'de la franja, así que si llega primero deja fuera a la entrada. Ver `DECISIONES #148`.</>');
        } else {
            $this->error('❌ FALLA [mixed]: se ha superado alguno de los dos topes, o alguien terminó con un error inesperado.');
        }

        return $pass;
    }

    /** Líneas de PACK vivas (pedido pagado o pendiente no caducado) que empiezan en esta franja. */
    private function livePackLinesInSlot(Slot $slot): int
    {
        return (int) $this->liveLines($slot, TicketType::TYPE_PACK)->count();
    }

    /** Líneas de ENTRADA vivas que empiezan en esta franja. */
    private function liveEntryLinesInSlot(Slot $slot): int
    {
        return (int) $this->liveLines($slot, TicketType::TYPE_ENTRY)->count();
    }

    /** Invitados (suma de `quantity`) de las fiestas vivas de esta franja. */
    private function livePackGuestsInSlot(Slot $slot): int
    {
        return (int) $this->liveLines($slot, TicketType::TYPE_PACK)->sum('order_items.quantity');
    }

    /**
     * Fiestas vivas de TODA la zona en un día, sin importar en qué franja empiecen.
     *
     * ⚠️ Es el contador que exige `pack-prep`: con la preparación bloqueando cupo, dos fiestas se
     * pisan **sin compartir hora de inicio**. Contar solo la franja sembrada dejaría fuera a la de
     * la hora siguiente y el invariante saldría verde con dos cumpleaños vendidos.
     */
    private function livePackLinesInZoneDay(int $zoneId, string $date): int
    {
        return (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('slots', 'slots.id', '=', 'order_items.slot_id')
            ->where('slots.zone_id', $zoneId)
            ->where('slots.date', $date)
            ->whereNull('order_items.cancelled_at')
            ->where(fn ($q) => $q->where('orders.status', Order::STATUS_PAID)
                ->orWhere(fn ($q2) => $q2->where('orders.status', Order::STATUS_PENDING)
                    ->where(fn ($q3) => $q3->whereNull('orders.expires_at')->orWhere('orders.expires_at', '>', now()))))
            ->count();
    }

    /**
     * Líneas VIVAS de un TIPO concreto en una franja.
     *
     * ⚠️⚠️ **El filtro por `ticket_types.type` no es cosmético.** Sin él, en `mixed` el contador daba
     * **2** donde debía dar **1**: la entrada y el cumpleaños viven en la misma franja, y sumarlos
     * mezcla los dos pools que ese escenario existe justamente para separar. Se vio en la primera
     * ejecución, y el número —11— parecía una sobreventa siendo un defecto del instrumento.
     *
     * @return Builder<OrderItem>
     */
    private function liveLines(Slot $slot, string $type)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->where('order_items.slot_id', $slot->id)
            ->where('ticket_types.type', $type)
            ->whereNull('order_items.cancelled_at')
            ->where(fn ($q) => $q->where('orders.status', Order::STATUS_PAID)
                ->orWhere(fn ($q2) => $q2->where('orders.status', Order::STATUS_PENDING)
                    ->where(fn ($q3) => $q3->whereNull('orders.expires_at')->orWhere('orders.expires_at', '>', now()))));
    }

    // ─── Escenario `guest-count`: el CLIENTE subiendo invitados bajo carrera (AFORO-01) ───────
    // Instrumento exigido por `specs/invitados-en-post-form.md` §6 (`#444`). Es la PRIMERA puerta por
    // la que el cliente mueve aforo, y la suite corre en SQLite: sin esto, la revalidación bajo el
    // lock no la ejerce nadie (`SUITE-04`).

    /**
     * Siembra: **W fiestas VIVAS de 2 invitados en la MISMA franja**, con el cupo de invitados de la
     * zona en `2·W + 6`. Cada worker sube LA SUYA en +6 desde el post-form, así que **solo una cabe**.
     *
     * ⚠️⚠️ **La geometría es lo que hace que el escenario mida algo**: si cada uno subiera «hasta el
     * tope» la carrera la ganaría quien llegara primero y el resto se rechazaría por su propia
     * siembra. Pidiendo todos el MISMO hueco de 6, un solo ganador demuestra que la revalidación
     * corre **dentro** del lock — sin ella, los doce leen «6 libres» a la vez y suben los doce.
     *
     * ⚠️ La fecha va a **+7 días** y no a la primera franja abierta: el ajuste tiene un PLAZO (24 h
     * por defecto) y con una franja de hoy los doce serían rechazados por el corte, no por la
     * carrera — el escenario saldría «verde» sin haber probado nada, que es justo lo que la guarda
     * del instrumento existe para impedir.
     *
     * @return array<string, mixed>
     */
    private function seedGuestCountScenario(int $workers): array
    {
        return DB::transaction(function () use ($workers): array {
            $rateId = (int) RateType::query()->orderBy('priority')->value('id');

            $zone = Zone::create([
                'slug' => 'gc-probe-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'GuestCount Probe'],
                'is_active' => true,
                'max_per_slot' => 0,                       // el cupo bajo prueba es el de INVITADOS
                'max_guests_per_slot' => 2 * $workers + 6, // 6 plazas libres: cabe UNA subida de +6
                'prep_blocks_cupo' => false,
            ]);

            $schedule = app(OperatingSchedule::class);
            [$date, $time] = $this->firstOpenSlotMoment($schedule);
            $date = Carbon::parse($date)->addDays(7)->toDateString(); // fuera del plazo de corte

            $slot = null;
            foreach ([0, 1] as $h) {
                $start = Carbon::parse($time)->addHours($h)->format('H:i:s');
                $created = Slot::create([
                    'zone_id' => $zone->id, 'date' => $date,
                    'start_time' => $start,
                    'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                    'capacity' => 500, 'online_capacity' => 500,
                ]);
                if ($h === 0) {
                    $slot = $created;
                }
            }

            $type = TicketType::create([
                'name' => ['es' => 'Cumple GC Probe'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
                'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
                'seats_per_unit' => 1, 'position' => 1,
                'min_qty' => 1, 'max_qty' => 20,
                'prep_before_min' => 0, 'prep_after_min' => 0,
                'event_fields' => [],
                // Sin `guest_fields` la reserva NO tiene post-form y el ajuste responde `closed`:
                // el escenario mediría el cierre, no la carrera.
                'guest_fields' => [
                    ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ],
            ]);
            $dayRateId = (int) app(RateResolver::class)->for(Carbon::parse($date))->id;
            $type->prices()->create(['rate_type_id' => $dayRateId, 'amount_cents' => 1500]);
            if ($dayRateId !== $rateId) {
                $type->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1500]);
            }

            $users = [];
            $itemIds = [];
            for ($i = 0; $i < $workers; $i++) {
                $buyer = User::forceCreate([
                    'name' => 'GC Holder '.$i,
                    'email' => 'gc-holder-'.Str::random(8).'@deleted.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
                $order = Order::create([
                    'user_id' => $buyer->id,
                    'code' => 'GC-'.Str::upper(Str::random(6)),
                    'status' => Order::STATUS_PAID,
                    'subtotal' => 3000, 'total' => 3000, 'currency' => 'EUR',
                    'paid_at' => now(),
                ]);
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'parent_item_id' => null,
                    'ticket_type_id' => $type->id,
                    'slot_id' => $slot->id,
                    'quantity' => 2, 'seats' => 2, 'unit_price' => 1500,
                ]);
                $users[] = $buyer;
                $itemIds[] = $item->id;
            }

            return [
                'scenario' => 'guest-count',
                'zone' => $zone->fresh(), 'type' => $type, 'slot' => $slot->fresh('zone'),
                'date' => $date, 'time' => $time,
                'users' => $users, 'item_ids' => $itemIds,
                'probe_qty' => 6,
                'target' => 8,
                'extra_user_ids' => [],
            ];
        });
    }

    /**
     * El ACTO: el cliente sube SU reserva a `target` invitados por la misma puerta que el post-form.
     *
     * ⚠️ Se conduce el SERVICIO real y no una consulta a mano: lo que se mide es que la revalidación
     * viva **dentro** del lock que él toma, no que una consulta suelta sepa contar.
     *
     * @param  array<string, mixed>  $seed
     */
    private function guestCountRaise(array $seed, int $i): string
    {
        $item = OrderItem::with(['ticketType', 'slot', 'order'])->find($seed['item_ids'][$i]);
        if ($item === null) {
            return 'EXCEPTION: la reserva sembrada no existe';
        }

        $change = app(GuestCountAdjuster::class)->adjust($item, (int) $seed['target'], 'signed_link');

        return $change->applied
            ? 'created:'.$item->id
            : 'sold_out:'.($change->reason ?? '—');
    }

    // ─── Escenario `panel-edit`: el lock zona/día de las EDICIONES bajo carrera (AFORO-05) ────
    // Instrumento exigido por la extracción 4 del desmontaje de `ViewOrder`
    // (`docs/specs/desmontar-view-order.md` §6·4): los otros escenarios conducen `OrderCreator`,
    // y el lock que la extracción mudó (hoy `ZoneDaySlotLock`, tomado por
    // `OrderItemEditor::withZoneDayLock`) no lo ejecutaba NINGÚN verificador.

    /**
     * Siembra del escenario de EDICIÓN DE PANEL concurrente.
     *
     * Geometría (entradas de 120 min → la ventana de un ítem cubre DOS franjas horarias):
     *
     *     T (cap 10) ── destino A          T+3h (parking, cap N+1) ── ítems aparcados
     *     T+1h (cap 1) ─ el MEDIO, y también destino B
     *     T+2h (cap 10) ─ cola de la ventana de B
     *
     * Los workers pares mueven su ítem al destino A (ventana [T, T+2h): PISA el medio) y los
     * impares al MEDIO mismo (ventana [T+1h, T+3h)). El medio admite UNA plaza: con el lock
     * zona/día real solo UN movimiento puede comprometerla; con un lock de solo-la-fila-destino,
     * A y B no comparten fila bloqueada, los dos revalidan contra el mismo hueco y el medio acaba
     * con DOS — la sobreventa exacta que motivó el lock de zona/día del panel (L3, `AFORO-05`).
     *
     * ⚠️ El personal es un usuario DESECHABLE con un rol DESECHABLE que lleva el permiso
     * `orders.edit_item` EXISTENTE: no se toca el rol `staff` global ni se crean permisos.
     */
    private function seedPanelEditScenario(int $workers): array
    {
        return DB::transaction(function () use ($workers): array {
            $permissionId = Permission::where('name', 'orders.edit_item')->value('id');
            if ($permissionId === null) {
                throw new \RuntimeException('La BD no tiene el permiso `orders.edit_item` (¿PermissionSeeder sin correr?): el escenario no puede actuar.');
            }

            $zone = Zone::create([
                'slug' => 'pe-probe-'.Str::lower(Str::random(6)),
                'name' => ['es' => 'PanelEdit Probe'],
                'is_active' => true,
            ]);

            $schedule = app(OperatingSchedule::class);
            [$date, $time] = $this->firstOpenSlotMoment($schedule);
            $hour = fn (int $n): string => Carbon::parse($time)->addHours($n)->format('H:i:s');

            $mk = fn (string $start, int $cap): Slot => Slot::create([
                'zone_id' => $zone->id, 'date' => $date,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => $cap, 'online_capacity' => $cap,
                'online_sales_open' => true, 'status' => Slot::STATUS_OPEN,
            ]);

            $slotA = $mk($time, 10);          // destino A: su ventana [T, T+2h) pisa el medio
            $mid = $mk($hour(1), 1);          // el MEDIO con UNA plaza — y también destino B
            $mk($hour(2), 10);                // cola de la ventana de B (sin ella, B no cabría nunca)
            $parking = $mk($hour(3), $workers + 1); // aparcamiento: su ventana no toca el medio
            $mk($hour(4), $workers + 1);            // cola de la ventana del parking (los ítems son de 120 min)

            $type = TicketType::create([
                'name' => ['es' => 'Entrada PE 120'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
                'duration_min' => 120, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
            ]);

            $role = Role::create([
                'name' => 'pe-probe-'.Str::lower(Str::random(6)),
                'label' => 'PanelEdit Probe (desechable)',
            ]);
            $role->permissions()->sync([$permissionId]);
            $staff = User::forceCreate([
                'name' => 'PE Staff',
                'email' => 'pe-staff-'.Str::random(8).'@deleted.local',
                'password' => bcrypt(Str::random(32)),
            ]);
            $staff->roles()->sync([$role->id]);

            $users = [];
            $itemIds = [];
            $mkHolder = function (int $i) use ($type, $parking): array {
                $buyer = User::forceCreate([
                    'name' => 'PE Holder '.$i,
                    'email' => 'pe-holder-'.Str::random(8).'@deleted.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
                $order = Order::create([
                    'user_id' => $buyer->id,
                    'code' => 'PE-'.Str::upper(Str::random(6)),
                    'status' => Order::STATUS_PAID,
                    'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
                    'paid_at' => now(),
                ]);
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'parent_item_id' => null,
                    'ticket_type_id' => $type->id,
                    'slot_id' => $parking->id,
                    'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
                ]);

                return [$buyer, $item];
            };

            for ($i = 0; $i < $workers; $i++) {
                [$buyer, $item] = $mkHolder($i);
                $users[] = $buyer;
                $itemIds[] = $item->id;
            }
            [$probeBuyer, $probeItem] = $mkHolder($workers); // el ítem SONDA de la guarda

            return [
                'scenario' => 'panel-edit',
                'zone' => $zone, 'type' => $type,
                'slot' => $mid, 'slot_a_id' => $slotA->id, 'mid_id' => $mid->id,
                'parking_id' => $parking->id, 'parking_time' => $hour(3),
                'date' => $date, 'time' => $time,
                'dests' => [
                    ['date' => $date, 'time' => $time],     // A: pisa el medio desde la franja anterior
                    ['date' => $date, 'time' => $hour(1)],  // B: el medio mismo
                ],
                'users' => $users, 'item_ids' => $itemIds,
                'staff_id' => $staff->id, 'role_id' => $role->id,
                'extra_user_ids' => [$probeBuyer->id, $staff->id],
                'probe_item_id' => $probeItem->id,
                'probe_qty' => 1, 'expected_winners' => 1,
            ];
        });
    }

    /** Un movimiento de panel REAL — el camino entero de `executeItemSlotChange` — del worker $i. */
    private function panelEditMove(array $seed, int $i): string
    {
        return $this->panelEditMoveItem(
            $seed,
            OrderItem::query()->findOrFail($seed['item_ids'][$i]),
            $seed['dests'][$i % 2],
        );
    }

    /**
     * Ejecuta `OrderItemEditor::changeSlot()` de verdad (permiso, capas de validación, lock
     * zona/día, revalidación bajo lock, save) contra `$dest`, como lo haría el operador desde el
     * panel — desde la extracción 4b (`#187`) la operación vive en el dominio y se invoca por su
     * contrato, sin reflexión ni página.
     *
     * @param  array{date:string, time:string}  $dest
     */
    private function panelEditMoveItem(array $seed, OrderItem $item, array $dest): string
    {
        // El hijo no debe encolar nada que sobreviva al escenario (email del cambio → array).
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        $from = (int) $item->slot_id;
        $order = Order::query()->findOrFail($item->order_id);

        app(OrderItemEditor::class)->changeSlot(
            $order,
            $item,
            $dest['date'],
            $dest['time'],
            (string) ($item->updated_at?->getTimestamp() ?? ''),
            null,
            User::findOrFail($seed['staff_id']),
        );

        $now = (int) $item->fresh()->slot_id;

        return $now !== $from ? 'moved:'.$now : 'blocked:'.$now;
    }

    /**
     * La guarda del instrumento para `panel-edit`: el camino del panel tiene que poder MOVER una
     * vez (sonda → destino A) y DESHACER (sonda → parking), dejando el medio VACÍO antes de la
     * carrera. Si no puede, los N workers serían bloqueados por siembra —permiso, ventana del
     * producto, horario— y el «no hubo sobreventa» no habría medido nada.
     */
    private function probePanelEditActs(array $seed): bool
    {
        $probe = OrderItem::query()->findOrFail($seed['probe_item_id']);

        $in = $this->panelEditMoveItem($seed, $probe, $seed['dests'][0]);
        if (! str_starts_with($in, 'moved:')) {
            $this->error(
                "La guarda del instrumento no pudo MOVER ni una vez (resultado: {$in}).\n".
                '▶ Los workers serían bloqueados por un motivo que NO es la carrera. Revisa la siembra.'
            );

            return false;
        }

        $back = $this->panelEditMoveItem($seed, $probe->fresh(), ['date' => $seed['date'], 'time' => $seed['parking_time']]);
        if (! str_starts_with($back, 'moved:')) {
            $this->error("La sonda no pudo VOLVER al parking (resultado: {$back}): el medio arrancaría ocupado y la carrera no mediría nada.");

            return false;
        }

        $this->line('<fg=gray>Guarda del instrumento · el camino del panel MUEVE y DESHACE. ✓</>');

        return true;
    }

    /**
     * Evaluación de `panel-edit`. La prueba DURA es la ocupación REAL de la franja intermedia:
     * un ítem de 120 min la pisa si empieza en ella o en la anterior, así que se suman los
     * asientos vivos de las DOS franjas. Con el lock correcto: exactamente 1.
     */
    private function evaluatePanelEdit(array $seed, int $workers, string $resultsDir): bool
    {
        $outcomes = collect(File::files($resultsDir))
            ->map(fn ($f): string => trim(File::get($f->getPathname())));

        $moved = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'moved:'));
        $blocked = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'blocked:'));
        $errors = $outcomes->reject(fn (string $o): bool => str_starts_with($o, 'moved:') || str_starts_with($o, 'blocked:'));

        $midSeats = $this->liveSeatsInSlots([$seed['slot_a_id'], $seed['mid_id']]);

        $this->newLine();
        $this->line('<options=bold>Resultados de los operadores concurrentes:</>');
        $this->line('  '.$moved->count().'× movimiento comprometido');
        $this->line('  '.$blocked->count().'× bloqueado (sin plaza al revalidar)');
        if ($errors->isNotEmpty()) {
            $this->line('  <fg=red>'.$errors->count().'× error inesperado</>');
            $errors->each(fn ($e) => $this->line('     '.$e));
        }

        $winners = (int) $seed['expected_winners'];

        $this->newLine();
        $this->table(
            ['Invariante', 'Esperado', 'Real', 'OK'],
            [
                ['Asientos vivos pisando la franja intermedia (cap 1)', '1', (string) $midSeats, $this->ok($midSeats === 1)],
                ['Movimientos comprometidos', (string) $winners, (string) $moved->count(), $this->ok($moved->count() === $winners)],
                ['Movimientos bloqueados', (string) ($workers - $winners), (string) $blocked->count(), $this->ok($blocked->count() === $workers - $winners)],
                ['Errores inesperados', '0', (string) $errors->count(), $this->ok($errors->isEmpty())],
            ]
        );

        $pass = $midSeats === 1
            && $moved->count() === $winners
            && $blocked->count() === $workers - $winners
            && $errors->isEmpty();

        $this->newLine();
        if ($pass) {
            $this->info("✅ PASA [panel-edit]: bajo {$workers} ediciones de panel concurrentes hacia la última plaza, `ZoneDaySlotLock` serializó — UNA se comprometió, SIN sobreventa de la franja intermedia. Verificado sobre InnoDB real.");
        } else {
            $this->error('❌ FALLA [panel-edit]: SOBREVENTA o invariante roto. Revisar que `OrderItemEditor::withZoneDayLock` tome `ZoneDaySlotLock` de TODA la zona/día como PRIMERA sentencia de la transacción de la edición.');
        }

        return $pass;
    }

    /** Asientos vivos (mismo criterio de vida que el resto de contadores) en un conjunto de franjas. */
    private function liveSeatsInSlots(array $slotIds): int
    {
        return (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.slot_id', $slotIds)
            ->whereNull('order_items.cancelled_at')
            ->where(fn ($q) => $q->where('orders.status', Order::STATUS_PAID)
                ->orWhere(fn ($q2) => $q2->where('orders.status', Order::STATUS_PENDING)
                    ->where(fn ($q3) => $q3->whereNull('orders.expires_at')->orWhere('orders.expires_at', '>', now()))))
            ->sum('order_items.seats');
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
        // `panel-edit` crea además la sonda, el staff, un rol desechable y audit logs del panel
        // (cambios y bloqueos): todo se borra ANTES del barrido general, con los ids aún vivos.
        if (($seed['scenario'] ?? '') === 'panel-edit') {
            $userIds = array_merge($userIds, $seed['extra_user_ids']);

            $peOrderIds = Order::whereIn('user_id', $userIds)->pluck('id')->all();
            $peItemIds = OrderItem::whereIn('order_id', $peOrderIds)->pluck('id')->all();
            AuditLog::query()
                ->where(fn ($q) => $q
                    ->where(fn ($q2) => $q2->where('target_type', (new Order)->getMorphClass())->whereIn('target_id', $peOrderIds))
                    ->orWhere(fn ($q2) => $q2->where('target_type', (new OrderItem)->getMorphClass())->whereIn('target_id', $peItemIds)))
                ->delete();

            $role = Role::find($seed['role_id']);
            if ($role !== null) {
                $role->permissions()->detach();
                $role->users()->detach();
                $role->delete();
            }
        }
        $orderIds = Order::whereIn('user_id', $userIds)->pluck('id')->all();

        OrderItem::whereIn('order_id', $orderIds)->delete();
        Order::whereIn('id', $orderIds)->delete();

        // `extra-hour` y `stay-extension` crean además un COMPLEMENTO, que tiene zona NULA a
        // propósito (la hija hereda la de la franja que ocupa, y un extensor ni siquiera tiene
        // franja): el barrido por zona de abajo no lo vería y quedaría en la BD de desarrollo tras
        // cada ejecución, con su precio y su enganche.
        //
        // ⚠️⚠️ **Se recorren las DOS claves**, y esa lista es la que hay que ampliar al añadir un
        // escenario con complemento: `stay-extension` guardaba el suyo en `extender` y esta limpieza
        // solo miraba `addon`, así que dejó **12 «Hora extra Probe» huérfanos** en la BD local antes
        // de que nadie lo notara. *Una limpieza que enumera claves a mano se queda corta en silencio.*
        foreach (['addon', 'extender'] as $clave) {
            if (! isset($seed[$clave])) {
                continue;
            }
            $addon = TicketType::find($seed[$clave]->id);
            if ($addon !== null) {
                $addon->prices()->delete();
                DB::table('product_addons')->where('addon_id', $addon->id)->delete();
                $addon->delete();
            }
        }

        // ⚠️ TODOS los productos de la zona, no solo `type`: el escenario `mixed` crea DOS (una
        // entrada y un pack) y borrar uno dejaría el otro huérfano con su precio.
        $products = TicketType::where('zone_id', $seed['zone']->id)->get();
        foreach ($products as $product) {
            $product->prices()->delete();
        }
        // ⚠️ Por ZONA, no por el id de la franja sembrada: los escenarios de packs crean VARIAS y
        // borrar solo la primera dejaría basura en la BD de desarrollo tras cada ejecución.
        Slot::where('zone_id', $seed['zone']->id)->delete();
        TicketType::whereIn('id', $products->pluck('id'))->delete();
        User::whereIn('id', $userIds)->delete();
        Zone::where('id', $seed['zone']->id)->delete();
        $this->line('<fg=gray>Datos de prueba borrados.</>');
    }
}
