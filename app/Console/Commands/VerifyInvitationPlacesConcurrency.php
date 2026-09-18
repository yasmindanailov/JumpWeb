<?php

namespace App\Console\Commands;

use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * **El MISMO NIÑO contestando a la vez, y la plaza que no puede contarse dos veces**
 * (`specs/celebracion-e-invitacion.md` §4.5·5 y §4.5·8; `DECISIONES #574`, reorientado en `#700`).
 *
 * Hermano de `purchase:verify-oversell` y de `waiver:verify-chain`, y existe por lo mismo: **la suite
 * es ciega a esta carrera por construcción**. Corre sobre SQLite, donde `SQLiteGrammar::compileLock()`
 * devuelve **cadena vacía** — o sea que `lockForUpdate()` no bloquea nada y el caso pasa igual con el
 * lock y sin él. Un invariante de exclusión que solo se prueba ahí es una afirmación, no un hecho.
 *
 * Lo que fuerza: N padres contestan «sí» **a la vez por el MISMO niño**. El invariante (V6, regla 5)
 * es que las N respuestas se guarden pero **ocupen UNA sola plaza**: la primera la toma y las demás
 * se unen a ella. Sin el lock de `PartyInvitations::reply()` todos leen la misma lista, ninguno ve el
 * «sí» de los otros, todos creen estar estrenando plaza — y el suelo de `#444` sale contando N niños
 * donde hay uno, así que **el anfitrión no puede bajar el número de invitados** y nadie sabe por qué.
 *
 * ⚠️⚠️ **Hasta `#700` este comando medía otra cosa**: N padres por niños DISTINTOS a la última plaza,
 * con el invariante de que entrara uno y los demás recibieran `full`. Ese rechazo se retiró —era un
 * oráculo de pertenencia— y con él desapareció aquella carrera. La que queda es ésta, y es la misma
 * mecánica de leer-decidir-escribir bajo el mismo lock.
 *
 * ⚠️⚠️ **Un verde solo vale si el instrumento se ha visto FALLAR** (`#147`). El control de este
 * comando no es una bandera: es **retirar el `lockForUpdate()`** de `PartyInvitations::reply()` y
 * volver a correrlo. Medido el 2026-09-18 con 16 forks: con el lock, **1** toma plaza y 15 se unen;
 * sin él, **16 de 16** creen estrenar plaza. El arnés `scripts/mutar-invitacion-t42.sh` lo repite.
 *
 * ⚠️ **Y lo que ese fallo NO rompe, dicho para no venderlo de más**: el suelo de `#444` sale igual a 1
 * en los dos casos, porque `PartyGuestsReader` agrupa por `child_key` **al leer**. O sea que la
 * segunda red sostiene la cuenta aunque la primera falle. Lo que el lock protege de verdad es la
 * CLASIFICACIÓN de cada respuesta —quién tomó plaza y quién se unió—, que es de lo que vive el aviso
 * de «hay N respuestas que ya no caben» que ve el anfitrión (§4.7).
 *
 * ⚠️ Un padre NO mueve aforo ni dinero: esto no bloquea `slots` ni toca `order_items`. La exclusión
 * que hace falta es entre respuestas de la MISMA invitación, y todas pasan por su fila.
 */
class VerifyInvitationPlacesConcurrency extends Command
{
    protected $signature = 'invitation:verify-places {--workers=16} {--keep}';

    protected $description = 'N «sí» simultáneos del MISMO niño: tienen que ocupar exactamente una plaza.';

    public function handle(): int
    {
        if (! function_exists('pcntl_fork')) {
            $this->error('Necesita la extensión pcntl (solo CLI de dev).');

            return self::FAILURE;
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Este verificador solo dice algo sobre MySQL/InnoDB: en SQLite el lock es un no-op.');

            return self::FAILURE;
        }

        $workers = max(2, (int) $this->option('workers'));
        $seed = $this->seed();
        $dir = storage_path('app/invitation-verify-'.Str::random(8));
        File::ensureDirectoryExists($dir);

        if (! $this->guardTheInstrument($seed)) {
            File::deleteDirectory($dir);
            if (! $this->option('keep')) {
                $this->cleanUp($seed);
            }

            return self::FAILURE;
        }

        $this->line("Invitación #{$seed['invitation']->id} · UNA plaza libre · {$workers} padres simultáneos…");

        $this->forkWorkers($seed, $workers, microtime(true) + 1.0, $dir);
        DB::reconnect();

        $ok = $this->evaluate($seed, $dir, $workers);

        File::deleteDirectory($dir);
        if (! $this->option('keep')) {
            $this->cleanUp($seed);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * La reserva de prueba: **8 invitados con 7 fichas ya escritas**, así que queda una plaza.
     *
     * ⚠️ Se siembra con fichas escritas y no con `quantity = 1` a propósito: así se ejerce **la cuenta
     * de §4.5·3 entera** —fichas con nombre más «sí» pendientes— y no solo el caso degenerado.
     *
     * @return array{item: OrderItem, invitation: PartyInvitation, order: Order, type: TicketType, zone: Zone, slot: Slot, user: User}
     */
    private function seed(): array
    {
        $zone = Zone::create([
            'slug' => 'verify-inv-'.Str::random(6), 'name' => ['es' => 'Verify'], 'position' => 99, 'is_active' => true,
        ]);
        $type = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Verify pack'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 30,
            'is_sellable' => true, 'is_active' => true, 'position' => 99,
            'guest_invitation' => true,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
            ],
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '18:00:00', 'capacity' => 300, 'online_capacity' => 300,
        ]);
        $user = User::factory()->create(['name' => 'Verify Anfitrión']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-VI'.Str::upper(Str::random(4)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);

        $guests = [];
        for ($i = 1; $i <= 7; $i++) {
            $guests[] = ['name' => 'Invitado Escrito '.$i];
        }

        $item = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 8, 'unit_price' => 500, 'seats' => 8,
            'event_data' => ['celebrant' => 'Verify'],
            'guest_data' => $guests,
        ]);

        $invitation = app(PartyInvitations::class)->forReservation($item->fresh(['ticketType', 'slot', 'order']));

        return compact('item', 'invitation', 'order', 'type', 'zone', 'slot', 'user');
    }

    /** Sin esto, un fixture roto daría un verde que no mide nada (la lección de `#147`). */
    private function guardTheInstrument(array $seed): bool
    {
        if ($seed['invitation'] === null) {
            $this->error('✗ FIXTURE ROTO: el producto no ofrece invitación digital.');

            return false;
        }

        $outcome = app(PartyInvitations::class)->reply($seed['invitation'], 'Sonda Previa', true);
        if (! $outcome->accepted || $outcome->joinedExistingPlace) {
            $this->error('✗ FIXTURE ROTO: la plaza libre no se podía ocupar; el veredicto no valdría nada.');

            return false;
        }
        $outcome->reply->delete();

        $this->line('<fg=gray>Guarda del instrumento · queda exactamente UNA plaza y se puede ocupar. ✓</>');

        return true;
    }

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
                    // ⚠️⚠️ **El MISMO nombre en todos los forks, y desde `#700` es justo al revés que
                    // antes.** Mientras la lista completa rechazaba, la carrera se ejercía con nombres
                    // distintos por la última plaza. Ya no rechaza, así que la exclusión que queda —y
                    // la única que puede romperse en silencio— es la del **repetido**: N padres
                    // contestando por el mismo niño a la vez tienen que ocupar UNA plaza, no N.
                    $invitation = PartyInvitation::findOrFail($seed['invitation']->getKey());
                    $verdict = app(PartyInvitations::class)->reply($invitation, 'Hugo Ruiz Pla', true);
                    $outcome = $verdict->accepted
                        ? ($verdict->joinedExistingPlace ? 'joined' : 'took-place')
                        : (string) $verdict->reason;
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

    private function evaluate(array $seed, string $dir, int $workers): bool
    {
        $outcomes = [];
        foreach (File::files($dir) as $file) {
            $outcomes[] = trim(File::get($file->getPathname()));
        }

        $took = count(array_filter($outcomes, static fn (string $o): bool => $o === 'took-place'));
        $joined = count(array_filter($outcomes, static fn (string $o): bool => $o === 'joined'));
        $errors = count(array_filter($outcomes, static fn (string $o): bool => str_starts_with($o, 'EXCEPTION') || $o === 'ERROR'));
        $rows = InvitationReply::query()
            ->where('order_item_id', $seed['item']->getKey())
            ->where('attending', true)
            ->count();

        // El SUELO de `#444`: plazas con dueño, distintas por niño. Es la cifra que de verdad importa,
        // porque es la que decide si el anfitrión puede bajar el número de invitados.
        $plazas = count(app(PartyGuests::class)->committedReplyIdsIn((int) $seed['item']->getKey()));

        $this->newLine();
        $this->table(
            ['Invariante', 'Esperado', 'Real', 'OK'],
            [
                ['«Sí» que TOMAN plaza nueva', 1, $took, $took === 1 ? '✓' : '✗'],
                ['«Sí» que se UNEN a la del primero', $workers - 1, $joined, $joined === $workers - 1 ? '✓' : '✗'],
                ['Plazas con dueño (el suelo de #444)', 1, $plazas, $plazas === 1 ? '✓' : '✗'],
                ['Filas «sí» guardadas', $workers, $rows, $rows === $workers ? '✓' : '✗'],
                ['Errores inesperados', 0, $errors, $errors === 0 ? '✓' : '✗'],
            ],
        );

        $ok = $took === 1 && $joined === $workers - 1 && $plazas === 1 && $rows === $workers && $errors === 0;

        $this->newLine();
        $this->line($ok
            ? "<fg=green>✅ PASA: bajo {$workers} respuestas concurrentes del MISMO niño el lock serializó — UNA tomó plaza y el suelo cuenta un niño, no {$workers}. Verificado sobre InnoDB real.</>"
            : '<fg=red>❌ FALLA: las respuestas del mismo niño no se clasificaron en exclusión. Revisa el `lockForUpdate()` de `PartyInvitations::reply()`.</>');

        if (! $ok) {
            foreach (array_filter($outcomes, static fn (string $o): bool => str_starts_with($o, 'EXCEPTION')) as $e) {
                $this->line('  <fg=red>'.mb_substr($e, 0, 200).'</>');
            }
        }

        return $ok;
    }

    /** Se borra TODO lo sembrado: un verificador que deja rastro contamina la siguiente medición. */
    private function cleanUp(array $seed): void
    {
        DB::table('invitation_replies')->where('order_item_id', $seed['item']->getKey())->delete();
        DB::table('party_invitations')->where('order_item_id', $seed['item']->getKey())->delete();
        DB::table('order_items')->where('order_id', $seed['order']->getKey())->delete();
        DB::table('orders')->where('id', $seed['order']->getKey())->delete();
        DB::table('slots')->where('id', $seed['slot']->getKey())->delete();
        DB::table('ticket_types')->where('id', $seed['type']->getKey())->delete();
        DB::table('zones')->where('id', $seed['zone']->getKey())->delete();
        DB::table('users')->where('id', $seed['user']->getKey())->delete();
        $this->line('<fg=gray>Datos de prueba borrados.</>');
    }
}
