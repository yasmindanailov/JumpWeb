<?php

namespace App\Console\Commands;

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
 * **La ÚLTIMA PLAZA de una invitación, disputada de verdad**
 * (`specs/celebracion-e-invitacion.md` §2.1 y §4.5·3; `DECISIONES #574`).
 *
 * Hermano de `purchase:verify-oversell` y de `waiver:verify-chain`, y existe por lo mismo: **la suite
 * es ciega a esta carrera por construcción**. Corre sobre SQLite, donde `SQLiteGrammar::compileLock()`
 * devuelve **cadena vacía** — o sea que `lockForUpdate()` no bloquea nada y el caso pasa igual con el
 * lock y sin él. Un invariante de exclusión que solo se prueba ahí es una afirmación, no un hecho.
 *
 * Lo que fuerza: N padres contestan «sí» **a la vez** por niños DISTINTOS cuando queda **una sola
 * plaza**. El invariante (D2, «no hay lista de espera») es que entre **exactamente uno** y los demás
 * reciban `full`. Sin el lock de `PartyInvitations::reply()` todos leen la misma lista, todos ven
 * hueco, todos escriben — y la fiesta acaba con más niños confirmados que plazas compradas, **sin un
 * solo error y sin que nadie se entere** hasta que se presenten en la puerta.
 *
 * ⚠️⚠️ **Un verde solo vale si el instrumento se ha visto FALLAR** (`#147`). El control de este
 * comando no es una bandera: es **retirar el `lockForUpdate()`** de `PartyInvitations::reply()` y
 * volver a correrlo — entonces tiene que cazar varios aceptados. Se hizo al escribirlo, y el arnés
 * `scripts/mutar-invitacion-t42.sh` lo repite como mutante.
 *
 * ⚠️ Un padre NO mueve aforo ni dinero: esto no bloquea `slots` ni toca `order_items`. La exclusión
 * que hace falta es entre respuestas de la MISMA invitación, y todas pasan por su fila.
 */
class VerifyInvitationPlacesConcurrency extends Command
{
    protected $signature = 'invitation:verify-places {--workers=16} {--keep}';

    protected $description = 'N «sí» simultáneos sobre la última plaza de una invitación: tiene que entrar exactamente uno.';

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
                    // ⚠️ Nombres DISTINTOS: con el mismo nombre se unirían a la misma plaza por la
                    // regla del repetido (V6) y la carrera no se ejercería.
                    $invitation = PartyInvitation::findOrFail($seed['invitation']->getKey());
                    $verdict = app(PartyInvitations::class)->reply($invitation, 'Padre Numero '.$i, true);
                    $outcome = $verdict->accepted ? 'accepted' : (string) $verdict->reason;
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

        $accepted = count(array_filter($outcomes, static fn (string $o): bool => $o === 'accepted'));
        $full = count(array_filter($outcomes, static fn (string $o): bool => $o === 'full'));
        $errors = count(array_filter($outcomes, static fn (string $o): bool => str_starts_with($o, 'EXCEPTION') || $o === 'ERROR'));
        $rows = InvitationReply::query()
            ->where('order_item_id', $seed['item']->getKey())
            ->where('attending', true)
            ->count();

        $this->newLine();
        $this->table(
            ['Invariante', 'Esperado', 'Real', 'OK'],
            [
                ['«Sí» admitidos', 1, $accepted, $accepted === 1 ? '✓' : '✗'],
                ['Rechazados por lista completa', $workers - 1, $full, $full === $workers - 1 ? '✓' : '✗'],
                ['Filas «sí» en la reserva', 1, $rows, $rows === 1 ? '✓' : '✗'],
                ['Errores inesperados', 0, $errors, $errors === 0 ? '✓' : '✗'],
            ],
        );

        $ok = $accepted === 1 && $full === $workers - 1 && $rows === 1 && $errors === 0;

        $this->newLine();
        $this->line($ok
            ? "<fg=green>✅ PASA: bajo {$workers} respuestas concurrentes a la última plaza el lock serializó — entró UNA, sin lista sobrevendida. Verificado sobre InnoDB real.</>"
            : '<fg=red>❌ FALLA: la última plaza no se disputó en exclusión. Revisa el `lockForUpdate()` de `PartyInvitations::reply()`.</>');

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
