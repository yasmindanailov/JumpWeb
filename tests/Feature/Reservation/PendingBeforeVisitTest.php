<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Booking\Services\PendingBeforeVisit;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **QUÉ LE QUEDA POR HACER A UNA RESERVA ANTES DE LA VISITA** (T7·2a,
 * `docs/specs/celebracion-e-invitacion.md` §4.9; `DECISIONES #714`).
 *
 * Es el lector del que colgará el aviso de la víspera, y se cierra **antes** de que exista el correo
 * a propósito: así el día que el aviso salga mal se sabe de qué mitad es la culpa.
 *
 * Lo que estos casos garantizan:
 *
 *  · **las cuatro cifras se miden por separado** y ninguna anula a otra — una reserva puede tener las
 *    fichas listas y deber dinero, o estar pagada y sin una sola ficha;
 *  · **una fiesta cancelada o ya celebrada no tiene nada pendiente**, aunque sus cifras digan que sí:
 *    pedirle a alguien que rellene la fiesta de ayer es el peor correo posible;
 *  · **sin justificante en el producto no hay menores sin resolver** — si no, el aviso reclamaría 20
 *    papeles que nadie ha pedido nunca;
 *  · y **una devolución pendiente no es trabajo del cliente**: solo cuenta el saldo a pagar.
 */
class PendingBeforeVisitTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true,
            'max_per_slot' => 3, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => false, 'position' => 1,
        ]);
    }

    // ─── Las fichas ──────────────────────────────────────────────────────────────────

    public function test_it_counts_the_cards_the_screen_counts(): void
    {
        $item = $this->reservation(quantity: 4, guests: [
            ['name' => 'Ana', 'age' => '7'],
            ['name' => 'Pablo', 'age' => '8'],
            ['name' => 'Iris'],
            [],
        ]);

        $pending = app(PendingBeforeVisit::class)->forReservation($item);

        // ⚠️ La MISMA cifra que el post-form pinta en su medidor: si aquí se recontara, el aviso
        // diría «4 de 4» donde la pantalla dice «2 de 4», que es cómo se pierde la confianza.
        $this->assertSame($item->guestFormProgress(), ['done' => $pending->guestsDone, 'total' => $pending->guestsTotal]);
        $this->assertSame(2, $pending->guestsMissing());
        $this->assertTrue($pending->any());
    }

    public function test_with_everything_filled_and_nothing_owed_there_is_nothing_to_say(): void
    {
        $item = $this->reservation(quantity: 2, guests: [
            ['name' => 'Ana', 'age' => '7'],
            ['name' => 'Pablo', 'age' => '8'],
        ], paid: true);

        $pending = app(PendingBeforeVisit::class)->forReservation($item);

        $this->assertSame(0, $pending->guestsMissing());
        $this->assertSame(0, $pending->balanceAtParkCents);
        $this->assertFalse($pending->any(), 'un correo que dice «no tienes que hacer nada» enseña a ignorar los correos');
    }

    // ─── Las respuestas ──────────────────────────────────────────────────────────────

    public function test_the_replies_to_review_only_count_with_the_invitation_on(): void
    {
        // ⚠️ PAGADO: contestar exige reserva viva y pagada (`GuestCountPolicy::isOpenFor`), así que
        // un fixture pendiente de pago deja la invitación `closed` y el caso no mediría nada.
        $item = $this->reservation(quantity: 4, guests: [[], [], [], []], invitation: true, paid: true);
        $this->reply($item, 'Martina Serra');
        $this->reply($item, 'Pablo Ortiz', attending: false);

        // Las DOS clases: un «no» también hay que verlo, porque lleva a bajar el número de invitados.
        $this->assertSame(2, app(PendingBeforeVisit::class)->forReservation($item)->repliesToReview);

        // El CONTROL: el mismo escenario con el producto sin invitación no cuenta ninguna.
        $sin = $this->reservation(quantity: 4, guests: [[], [], [], []], invitation: false, paid: true);
        $this->assertSame(0, app(PendingBeforeVisit::class)->forReservation($sin)->repliesToReview);
    }

    public function test_replies_stop_counting_when_the_switch_is_turned_off(): void
    {
        // ⚠️⚠️ **El caso que el control de arriba NO ejercía**: una reserva sin invitación tampoco
        // tiene respuestas, así que quitar la guarda del producto daba el mismo 0 y la mutación
        // sobrevivía. El escenario real es éste —V5: el interruptor se apaga con invitaciones ya
        // repartidas y las que llegaron siguen ahí—, y es el único que distingue las dos conductas.
        $item = $this->reservation(quantity: 4, guests: [[], [], [], []], invitation: true, paid: true);
        $this->reply($item, 'Martina Serra');
        $this->assertSame(1, app(PendingBeforeVisit::class)->forReservation($item)->repliesToReview);

        // Por el constructor de consultas: es como se apaga de verdad, y el guard del modelo no
        // tiene nada que decir aquí.
        TicketType::query()->whereKey($item->ticket_type_id)->update(['guest_invitation' => false]);

        $apagada = $item->fresh()->loadMissing(['ticketType', 'slot', 'order']);
        $this->assertFalse($apagada->ticketType?->offersGuestInvitation());
        $this->assertSame(0, app(PendingBeforeVisit::class)->forReservation($apagada)->repliesToReview);
    }

    // ─── Los menores ─────────────────────────────────────────────────────────────────

    public function test_a_product_without_a_waiver_has_no_minors_to_resolve(): void
    {
        // ⚠️⚠️ Sin esta guarda la resta daría la cantidad ENTERA: el aviso le reclamaría al cliente
        // 20 justificantes que este producto no pide por diseño.
        $item = $this->reservation(quantity: 20, guests: [], guardian: TicketType::GUARDIAN_NONE);

        $this->assertSame(0, app(PendingBeforeVisit::class)->forReservation($item)->minorsUnresolved);
    }

    public function test_with_a_waiver_the_unresolved_places_are_the_ones_without_an_owner(): void
    {
        $item = $this->reservation(quantity: 4, guests: [[], [], [], []], guardian: TicketType::GUARDIAN_OPTIONAL, invitation: true, paid: true);

        $this->assertSame(4, app(PendingBeforeVisit::class)->forReservation($item)->minorsUnresolved);

        // Un «sí» de la invitación es una plaza CON DUEÑO desde `#576`: baja el número sin que nadie
        // haya firmado nada. Es el sumando que el contrato de `#444` ganó con la T4·4.
        $this->reply($item, 'Martina Serra');

        $this->assertSame(3, app(PendingBeforeVisit::class)->forReservation($item->fresh(['ticketType', 'order', 'slot'])->loadMissing('ticketType'))->minorsUnresolved);
    }

    // ─── El dinero ───────────────────────────────────────────────────────────────────

    public function test_what_is_left_to_pay_at_the_park_counts(): void
    {
        // Con SEÑAL: se cobró online todo menos 2000, que se pagan en el parque. Es el escenario real
        // —un cobro parcial a pelo rompería las identidades del libro y saldría `under_review`—.
        $item = $this->reservation(quantity: 4, guests: [], paid: true, atParkCents: 2000);

        // ⚠️ EL INSTRUMENTO PRIMERO: sin esto, un `under_review` —que también da 0— dejaría este caso
        // verde por el motivo contrario al que dice medir.
        $this->assertSame(Balance::KIND_PAY_AT_PARK, OrderBook::forReservation($item->order, $item)->balance->kind);

        $pending = app(PendingBeforeVisit::class)->forReservation($item);

        $this->assertSame(2000, $pending->balanceAtParkCents);
        $this->assertTrue($pending->any());
    }

    public function test_a_refund_owed_to_the_customer_is_not_their_homework(): void
    {
        // El valor BAJA después de cobrar —el parque le quitó un invitado—, así que el libro debe
        // dinero al cliente. ⚠️ Se monta con el gesto real (`recordEdit` + la bajada): un cobro de
        // más a pelo rompe las identidades y el saldo sale `under_review`, que no es este caso.
        $item = $this->reservation(quantity: 3, guests: [
            ['name' => 'Ana', 'age' => '7'],
            ['name' => 'Pablo', 'age' => '8'],
        ], paid: true);
        $order = $item->order;
        $this->assertNotNull($order);

        $order->recordEdit($item, -1495, $order->user, 'item_edit_reduction', [
            'changes' => ['quantity_change' => ['old' => 3, 'new' => 2]],
        ]);
        $item->forceFill(['quantity' => 2, 'seats' => 2])->save();

        $item = $item->fresh()->loadMissing(['ticketType', 'slot', 'order']);
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, OrderBook::forReservation($item->order, $item)->balance->kind);

        $pending = app(PendingBeforeVisit::class)->forReservation($item);

        $this->assertSame(0, $pending->balanceAtParkCents, 'una devolución pendiente no es una tarea del cliente');
        $this->assertFalse($pending->any());
    }

    public function test_what_is_owed_online_is_not_money_to_bring_to_the_park(): void
    {
        // ⚠️⚠️ **El caso que hacía sobrevivir la mutación, y el defecto que evita**: con el pedido sin
        // cobrar el libro dice `pay_online` y su cifra es POSITIVA —lo que falta por pagar por web—.
        // Sin comprobar la clase, el aviso le diría al cliente que lleve ese dinero al parque, cuando
        // lo que tiene que hacer es pagarlo online. El `max(0, …)` tapaba la devolución, no esto.
        $item = $this->reservation(quantity: 4, guests: [], paid: false);

        $balance = OrderBook::forReservation($item->order, $item)->balance;
        $this->assertSame(Balance::KIND_PAY_ONLINE, $balance->kind);
        $this->assertGreaterThan(0, $balance->cents, 'el instrumento: la cifra de `pay_online` es positiva');

        $this->assertSame(0, app(PendingBeforeVisit::class)->forReservation($item)->balanceAtParkCents);
    }

    public function test_the_money_alone_is_enough_to_have_something_pending(): void
    {
        // ⚠️ El caso que hacía sobrevivir la mutación de `any()`: con las fichas a medias, «¿falta
        // algo?» decía que sí por las fichas y daba igual lo demás. Aquí lo único que falta es el
        // dinero — fichas completas, sin invitación y sin justificante.
        $item = $this->reservation(quantity: 2, guests: [
            ['name' => 'Ana', 'age' => '7'],
            ['name' => 'Pablo', 'age' => '8'],
        ], paid: true, atParkCents: 2000);

        $pending = app(PendingBeforeVisit::class)->forReservation($item);

        $this->assertSame(0, $pending->guestsMissing());
        $this->assertSame(0, $pending->repliesToReview);
        $this->assertSame(0, $pending->minorsUnresolved);
        $this->assertSame(2000, $pending->balanceAtParkCents);
        $this->assertTrue($pending->any(), 'deber dinero es algo que hacer, aunque todo lo demás esté listo');
    }

    // ─── Lo que ya no se puede hacer ─────────────────────────────────────────────────

    public function test_a_party_already_celebrated_has_nothing_pending(): void
    {
        $item = $this->reservation(quantity: 4, guests: [[], [], [], []], day: Carbon::today()->subDays(2));

        // El instrumento primero: que la reserva es de verdad una fiesta pasada.
        $this->assertTrue($item->isFinishedInPractice());

        $pending = app(PendingBeforeVisit::class)->forReservation($item);

        $this->assertFalse($pending->any());
        $this->assertSame(0, $pending->guestsTotal, 'lo que ya pasó no se rellena');
    }

    public function test_a_cancelled_reservation_has_nothing_pending(): void
    {
        $item = $this->reservation(quantity: 4, guests: [[], [], [], []]);
        $item->forceFill(['cancelled_at' => now()])->save();

        $this->assertTrue($item->isCancelled());
        $this->assertFalse(app(PendingBeforeVisit::class)->forReservation($item->fresh()->loadMissing(['ticketType', 'order', 'slot']))->any());
    }

    public function test_the_four_figures_do_not_cancel_each_other(): void
    {
        // Fichas a medias, una respuesta por repasar, plazas sin dueño y dinero a pagar: las cuatro
        // a la vez. Un único «¿falta algo?» no podría decir QUÉ falta, que es lo que hace útil el aviso.
        $item = $this->reservation(
            quantity: 4,
            guests: [['name' => 'Ana', 'age' => '7'], [], [], []],
            invitation: true,
            guardian: TicketType::GUARDIAN_OPTIONAL,
            paid: true,
            atParkCents: 2000,
        );
        $this->reply($item, 'Martina Serra');

        $pending = app(PendingBeforeVisit::class)->forReservation($item->fresh()->loadMissing(['ticketType', 'order', 'slot']));

        $this->assertSame(3, $pending->guestsMissing());
        $this->assertSame(1, $pending->repliesToReview);
        $this->assertSame(3, $pending->minorsUnresolved);
        $this->assertGreaterThan(0, $pending->balanceAtParkCents);
        $this->assertTrue($pending->any());
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    private function reply(OrderItem $item, string $childName, bool $attending = true): void
    {
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);

        $outcome = app(PartyInvitations::class)->reply($invitation, $childName, $attending);
        $this->assertTrue($outcome->accepted, 'el fixture no pudo contestar: '.($outcome->reason ?? '—'));
    }

    /**
     * @param  list<array<string, string>>  $guests
     */
    private function reservation(
        int $quantity,
        array $guests,
        bool $invitation = false,
        string $guardian = TicketType::GUARDIAN_NONE,
        bool $paid = false,
        int $atParkCents = 0,
        ?Carbon $day = null,
    ): OrderItem {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => $invitation,
            'guardian_authorization' => $guardian,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Quién cumple']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'age', 'type' => 'age', 'required' => true, 'label' => ['es' => 'Edad']],
            ],
        ]);

        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => ($day ?? Carbon::today()->addDays(10))->toDateString(),
            'start_time' => sprintf('%02d:00:00', 9 + Slot::query()->count()),
            'end_time' => sprintf('%02d:00:00', 11 + Slot::query()->count()),
            'capacity' => 100, 'online_capacity' => 100,
        ]);

        $user = User::factory()->create();
        $total = $quantity * 1495;
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $paid ? Order::STATUS_PAID : Order::STATUS_PENDING,
            'paid_at' => $paid ? now() : null,
            'total' => $total, 'currency' => 'EUR',
        ]);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => $quantity,
            'unit_price' => 1495, 'seats' => $quantity, 'event_data' => ['celebrant' => 'Lucía'],
            'guest_data' => $guests,
        ]);

        if ($paid) {
            // ⚠️ «A pagar en el parque» se monta con la SEÑAL, que es como nace de verdad
            // (`OrderAdjustment` de tipo `deposit_split`): dice cuánto del valor de la línea NO se
            // cobró online. Un cobro parcial a pelo rompe las identidades del libro y el saldo sale
            // `under_review`, que no es el escenario que este fichero quiere medir.
            if ($atParkCents > 0) {
                OrderAdjustment::create([
                    'order_id' => $order->id, 'order_item_id' => $line->id,
                    'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => $atParkCents,
                    'currency' => 'EUR', 'reason' => 'deposit_split', 'applied_by' => $order->user_id,
                ]);
            }

            Payment::create([
                'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
                'amount' => $total - $atParkCents, 'currency' => 'EUR', 'provider' => 'redsys',
                'status' => Payment::STATUS_PAID, 'paid_at' => now(),
                'gateway_order' => str_pad((string) (500000 + $order->id), 10, '0', STR_PAD_LEFT),
            ]);
        }

        return $order->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();
    }
}
