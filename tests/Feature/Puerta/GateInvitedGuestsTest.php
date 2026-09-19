<?php

namespace Tests\Feature\Puerta;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Contracts\GateProfileData;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **LA PUERTA VE A LOS NIÑOS DE LA FIESTA** (T6·4,
 * `docs/specs/celebracion-e-invitacion.md` §4.8 y §4.5·10).
 *
 * ❗❗ Antes de esta tanda, un niño que había dicho que viene y **aún no tenía firma no existía para el
 * mostrador**: la puerta solo listaba justificantes firmados, así que el operador se enteraba de que
 * faltaba media fiesta con las familias delante. Lo que se vigila aquí:
 *
 *  · la lista son **las fichas con nombre + los «sí» que el anfitrión todavía no ha apuntado**;
 *  · cada niño dice **en cuál de los tres estados llega**, y una firma emparejada POR NOMBRE cuenta;
 *  · la cuenta «8 de 12» mide sobre lo **contratado**, así que una lista a medias no esconde a nadie;
 *  · y el **presupuesto** no crece con los niños —la puerta es la pantalla que más se mira— ni paga
 *    una consulta cuando la reserva de hoy no es una fiesta con invitación.
 */
class GateInvitedGuestsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-05';

    private Zone $zone;

    private TicketType $pack;

    private TicketType $entry;

    private ?LegalDocumentVersion $version = null;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::TODAY.' 09:00:00', 'Europe/Madrid'));

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            // ⚠️ `optional`: con `required` el producto NO puede ofrecer invitación (guard de `#575`).
            'guardian_authorization' => TicketType::GUARDIAN_OPTIONAL,
            'guest_invitation' => true,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Quién cumple']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ],
        ]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);

        $this->mode('interno');
    }

    // ─── Quién sale y en qué estado ──────────────────────────────────────────────────

    public function test_the_gate_lists_the_cards_and_the_yeses_the_host_has_not_written_down(): void
    {
        [, $item] = $this->party(4, [['name' => 'Ana'], ['name' => 'Mateo']]);
        // Uno de los dos que ya estaban apuntados contesta, y contesta también una que no estaba.
        $this->reply($item, 'Mateo Ruiz');
        $this->reply($item, 'Martina Serra');

        $names = array_column($this->profile($item->order->user)->guestMinors, 'name');

        // Tres niños y **una vez cada uno**: la respuesta de Mateo no lo duplica.
        sort($names);
        $this->assertSame(['Ana', 'Martina Serra', 'Mateo'], $names);
    }

    public function test_each_child_says_in_which_of_the_three_states_it_arrives(): void
    {
        [$order, $item] = $this->party(4, [['name' => 'Ana']]);
        $conAdulto = $this->reply($item, 'Martina Serra', companion: InvitationReply::COMPANION_WITH_ADULT);
        $firmado = $this->reply($item, 'Hugo Ruiz', companion: InvitationReply::COMPANION_ALONE);
        $this->signFor($order, $item, 'Hugo', 'Ruiz Pla', $firmado->id);

        $byName = collect($this->profile($order->user)->guestMinors)->keyBy('name');

        $this->assertSame('signed', $byName['Hugo']['entry'], 'una firma atada a su respuesta es «firmado»');
        $this->assertSame('with_adult', $byName['Martina Serra']['entry'], '«voy con él» no pide firma (D4)');
        // ⚠️ «Sin resolver» NO es un error: es trabajo que se hará en el mostrador si nadie lo adelanta.
        $this->assertSame('unresolved', $byName['Ana']['entry']);
        // Sin firma no hay fecha de nacimiento, así que tampoco edad: un «0 años» sería inventado.
        $this->assertNull($byName['Ana']['age']);
        $this->assertSame(9, $byName['Hugo']['age']);
    }

    public function test_a_signature_matched_by_name_counts_even_without_the_tie(): void
    {
        // El anfitrión pegó la lista con nombres de pila; el padre firma con apellidos y SIN pasar por
        // la invitación (el enlace del correo). Es el caso que `PersonNameKey::cardMatches()` resuelve.
        [$order, $item] = $this->party(4, [['name' => 'Mateo']]);
        $this->signFor($order, $item, 'Mateo', 'Ruiz Pla');

        $rows = $this->profile($order->user)->guestMinors;

        $this->assertCount(1, $rows, 'la firma y la ficha son el MISMO niño: no pueden salir dos veces');
        $this->assertSame('signed', $rows[0]['entry']);
    }

    public function test_a_no_never_reaches_the_gate(): void
    {
        [$order, $item] = $this->party(4, []);
        $this->reply($item, 'Pablo Ortiz', attending: false);

        $this->assertSame([], $this->profile($order->user)->guestMinors, 'un «no» no cuenta en la puerta (§4.8)');
    }

    public function test_a_signature_that_matches_nobody_still_shows(): void
    {
        // La lista de siempre (`specs/waiver-por-reserva.md` §4.11): un padre puede firmar por un niño
        // que el anfitrión no apuntó nunca, y ese niño viene igual.
        [$order, $item] = $this->party(4, [['name' => 'Ana']]);
        $this->signFor($order, $item, 'Lucía', 'Gómez Ruiz');

        $names = array_column($this->profile($order->user)->guestMinors, 'name');

        sort($names);
        $this->assertSame(['Ana', 'Lucía'], $names);
    }

    // ─── La cuenta ───────────────────────────────────────────────────────────────────

    public function test_the_count_measures_against_what_was_contracted(): void
    {
        [$order, $item] = $this->party(12, [['name' => 'Ana']]);
        $firmado = $this->reply($item, 'Hugo Ruiz');
        $this->signFor($order, $item, 'Hugo', 'Ruiz Pla', $firmado->id);

        // ❗ 1 de 12, no «1 de 2»: el operador necesita saber cuántos niños se esperan, y una lista a
        // medias no puede esconder a los que faltan.
        $this->assertSame(['signed' => 1, 'expected' => 12], $this->profile($order->user)->guestMinorsCount);
    }

    public function test_without_a_party_today_there_is_no_count(): void
    {
        $holder = User::factory()->create(['email_verified_at' => now()]);
        $this->paidOrder($holder, [[$this->entry, 2, self::TODAY]]);

        $this->assertNull($this->profile($holder)->guestMinorsCount, 'una cuenta de «0 de 0» sería ruido');
    }

    public function test_the_states_only_exist_when_the_waiver_is_internal(): void
    {
        [$order, $item] = $this->party(4, [['name' => 'Ana']]);
        $this->reply($item, 'Martina Serra', companion: InvitationReply::COMPANION_WITH_ADULT);

        $this->mode('externo');
        foreach ($this->profile($order->user)->guestMinors as $row) {
            $this->assertNull($row['entry'], 'sin justificante interno no hay estado que pintar (§4.5·10)');
        }

        // ▶ CONTROL del instrumento: con el waiver interno SÍ los hay (si no, este caso no prueba nada).
        $this->mode('interno');
        $this->assertNotNull($this->profile($order->user)->guestMinors[0]['entry']);
    }

    // ─── El presupuesto (§7.2·R16) ───────────────────────────────────────────────────

    public function test_the_budget_does_not_grow_with_the_children(): void
    {
        [$pocos, $itemPocos] = $this->party(12, [['name' => 'Ana']]);
        $this->reply($itemPocos, 'Hugo Ruiz');

        [$muchos, $itemMuchos] = $this->party(12, array_map(
            static fn (int $i): array => ['name' => 'Niño '.$i],
            range(1, 8),
        ));
        foreach (['Uno Uno', 'Dos Dos', 'Tres Tres', 'Cuatro Cuatro'] as $name) {
            $this->reply($itemMuchos, $name);
        }

        $count = function (User $holder): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->profile($holder);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $forFew = $count($pocos->user);
        $forMany = $count($muchos->user);

        $this->assertSame($forFew, $forMany, "la puerta no puede pagar por niño ({$forFew} frente a {$forMany})");
        $this->assertLessThanOrEqual(28, $forFew, 'el techo de la puerta es 28 consultas (§7.2·R16)');
    }

    public function test_a_reservation_without_the_invitation_asks_nothing_about_replies(): void
    {
        $holder = User::factory()->create(['email_verified_at' => now()]);
        $this->paidOrder($holder, [[$this->entry, 2, self::TODAY]]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->profile($holder);
        $queries = array_map(static fn (array $q): string => (string) $q['query'], DB::getQueryLog());
        DB::disableQueryLog();

        // ⚠️ Ni una consulta a `invitation_replies`: un escaneo normal no paga por una feature que ese
        // producto no ofrece, y eso es lo que mantiene el presupuesto de la puerta donde estaba.
        $this->assertSame([], array_values(array_filter(
            $queries,
            static fn (string $q): bool => str_contains($q, 'invitation_replies'),
        )));
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    private function profile(User $holder): GateProfileData
    {
        return app(GateProfile::class)->for($holder, CarbonImmutable::parse(self::TODAY), 1);
    }

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    private function version(): LegalDocumentVersion
    {
        return $this->version ??= app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function signFor(Order $order, OrderItem $item, string $name, string $surname, ?int $replyId = null): void
    {
        app(GuardianAuthorizationSigner::class)->sign(
            $order->user,
            (int) $item->getKey(),
            $this->version(),
            [
                'minor_name' => $name, 'minor_surname' => $surname, 'minor_born_on' => '2017-01-01',
                'guardian_name' => 'Padre', 'guardian_surname' => 'Apellido', 'guardian_relationship' => 'father',
                'guardian_email' => strtolower($name).'@example.com', 'guardian_phone' => '600000000',
            ],
            WaiverSignatureRequest::web('10.0.0.1', 'UA'),
            $replyId,
        );
    }

    /**
     * ⚠️⚠️ **Se contesta TRES DÍAS ANTES, y no es un adorno del fixture**: el plazo de respuestas es el
     * mismo que el del número de invitados (D14), así que **el día de la fiesta ya está cerrado** —el
     * primer intento de este caso murió con `cutoff`—. La puerta lee justamente eso: lo que se
     * contestó antes, el día en que las familias llegan.
     */
    private function reply(OrderItem $item, string $childName, bool $attending = true, ?string $companion = null): InvitationReply
    {
        $this->travelTo(Carbon::parse(self::TODAY.' 09:00:00', 'Europe/Madrid')->subDays(3));

        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);

        $outcome = app(PartyInvitations::class)->reply($invitation, $childName, $attending, $companion);
        $this->assertTrue($outcome->accepted, 'el fixture no pudo contestar: '.($outcome->reason ?? '—'));

        $this->travelTo(Carbon::parse(self::TODAY.' 09:00:00', 'Europe/Madrid'));

        return $outcome->reply;
    }

    /**
     * Una fiesta PAGADA de hoy con sus fichas escritas.
     *
     * @param  list<array<string, string>>  $guests
     * @return array{0: Order, 1: OrderItem}
     */
    private function party(int $quantity, array $guests): array
    {
        $holder = User::factory()->create(['email_verified_at' => now()]);
        [$order, $items] = $this->paidOrder($holder, [[$this->pack, $quantity, self::TODAY]]);
        $items[0]->forceFill(['guest_data' => $guests, 'event_data' => ['celebrant' => 'Lucía']])->save();

        return [$order->fresh(), $items[0]->fresh(['ticketType', 'slot', 'order'])];
    }

    /**
     * @param  list<array{0: TicketType, 1: int, 2: string}>  $lines
     * @return array{0: Order, 1: list<OrderItem>}
     */
    private function paidOrder(User $holder, array $lines): array
    {
        $total = array_sum(array_map(static fn (array $l): int => 1000 * $l[1], $lines));
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-GATE'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $total, 'tax' => 0, 'total' => $total,
            'currency' => 'EUR', 'paid_at' => now()->subDay(),
        ]);
        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'cash',
            'amount' => $total, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now()->subDay(),
        ]);

        $items = [];
        foreach ($lines as [$type, $quantity, $date]) {
            $slot = Slot::firstOrCreate(
                ['zone_id' => $this->zone->id, 'date' => $date, 'start_time' => '10:00:00'],
                ['end_time' => '11:00:00', 'capacity' => 40, 'online_capacity' => 40],
            );
            $items[] = $order->items()->create([
                'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => $quantity,
                'unit_price' => 1000, 'seats' => $quantity,
            ]);
        }

        return [$order->fresh(), $items];
    }
}
