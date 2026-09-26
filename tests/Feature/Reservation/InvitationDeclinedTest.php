<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **LOS QUE NO VIENEN, LOS QUE NO CABEN Y «NO LO APUNTES»** (T6·3,
 * `docs/specs/celebracion-e-invitacion.md` §4.7, §7.1·3 y §7.2·R11).
 *
 * Las tres cosas que esta unidad tiene que garantizar, y por qué importan:
 *
 *  · **Un «no» se VE**, con su nombre y —si ese niño estaba en la lista— con su chapa en la ficha. Sin
 *    verlo, el anfitrión paga plazas de gente que ya le ha dicho que no va.
 *  · **«No lo apuntes» le desatasca**: desde `#576` un «sí» pendiente sube el suelo por debajo del cual
 *    no puede bajar el número de invitados, así que sin este gesto una respuesta que no quiere le deja
 *    ATRAPADO. El caso lo mide sobre el suelo, que es lo que duele, y no sobre la pantalla.
 *  · **Retirar NO borra** (V3) y **no mueve el testigo** de los extras, con su control.
 */
class InvitationDeclinedTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true,
            'max_per_slot' => 3, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => false, 'position' => 1,
        ]);

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => Carbon::today()->addDays(10)->toDateString(),
            'start_time' => '15:00:00', 'end_time' => '17:00:00', 'capacity' => 100, 'online_capacity' => 100,
        ]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => true,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Quién cumple']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);
    }

    // ─── Los que no vienen ───────────────────────────────────────────────────────────

    public function test_a_no_shows_in_its_own_group_and_says_until_when_the_count_can_drop(): void
    {
        $item = $this->reservation(4, [[], [], [], []]);
        $this->reply($item, 'Pablo Ortiz', attending: false);

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();

        $this->assertStringContainsString('Pablo Ortiz', $html, 'un «no» tiene que VERSE: es lo que lleva a bajar invitados');
        // La fila del «no» y la frase de D3 son las de la lista del sistema nuevo (`fiesta.php`): la T4 retiró las
        // claves viejas, que este test mantenía vivas porque su texto coincidía letra a letra con el nuevo.
        $this->assertStringContainsString(__('fiesta.fila.no'), $html);
        // La frase de D3 lleva la fecha, no un plazo en horas.
        $deadline = app(GuestCountPolicy::class)->deadlineFor($item);
        $this->assertNotNull($deadline);
        $this->assertStringContainsString(
            __('fiesta.lista.la_lista.no_vienen_baja', ['plazo' => DisplayTime::dayLabel($deadline)]),
            $html,
        );
    }

    public function test_a_no_that_matches_a_written_card_marks_that_card(): void
    {
        // El anfitrión tenía apuntado a «Pablo»; su padre contesta con apellidos que no viene.
        $item = $this->reservation(3, [['name' => 'Ana'], ['name' => 'Pablo'], []]);
        $this->reply($item, 'Pablo Ortiz', attending: false);

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();

        // ▶ Desde `#743` (la lista del sistema nuevo) la ficha que empareja se pinta como «No puede venir»
        // (`data-respuesta="no"`), y solo ella.
        $this->assertSame(1, substr_count($html, 'data-respuesta="no"'), 'el «no» va en la ficha que empareja, y solo en ella');
        $this->assertMatchesRegularExpression('#data-fila="g1"[^>]*data-respuesta="no"#', $html);
        // Y no se quita a nadie solo: la lista es suya, y su ficha sigue viajando en el formulario.
        $this->assertStringContainsString('name="guests[1][name]" value="Pablo"', $html);
    }

    // ─── «No lo apuntes» ─────────────────────────────────────────────────────────────

    public function test_removing_a_pending_yes_lowers_the_floor_that_was_blocking_the_host(): void
    {
        $item = $this->reservation(6, [[], [], [], [], [], []]);
        $reply = $this->reply($item, 'Martina Serra');
        $otra = $this->reply($item, 'Hugo Ruiz');

        // ❗ El suelo es lo que duele: con dos «sí» no puede bajar de 2 invitados.
        $this->assertSame(2, app(GuestCountPolicy::class)->assignedFloorFor($item->fresh()));

        $this->post($item->invitationSignedDismissUrl(), ['reply' => $reply->id])
            ->assertRedirect()
            ->assertSessionHas('status', 'invitation-dismissed');

        $this->assertNotNull($reply->fresh()->dismissed_at);
        $this->assertNull($otra->fresh()->dismissed_at, 'retirar una no puede llevarse a las demás');
        $this->assertSame(1, app(GuestCountPolicy::class)->assignedFloorFor($item->fresh()), 'el suelo tiene que bajar, que es para lo que existe el gesto');
        // Retirar NO borra (V3): la respuesta sigue en su tabla hasta que la poda se la lleve.
        $this->assertSame(2, InvitationReply::query()->count());
    }

    public function test_removing_a_no_takes_it_off_the_list(): void
    {
        $item = $this->reservation(3, [[], [], []]);
        $reply = $this->reply($item, 'Pablo Ortiz', attending: false);

        $this->post($item->invitationSignedDismissUrl(), ['reply' => $reply->id])->assertRedirect();

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();
        $this->assertStringNotContainsString('Pablo Ortiz', $html);
    }

    public function test_the_gesture_is_idempotent_and_never_says_what_it_did_not_do(): void
    {
        $item = $this->reservation(3, [[], [], []]);
        $reply = $this->reply($item, 'Martina Serra');

        $this->post($item->invitationSignedDismissUrl(), ['reply' => $reply->id])->assertRedirect();
        // Dos pestañas del mismo anfitrión: el segundo envío contesta igual, sin distinguir «ya estaba».
        $this->post($item->invitationSignedDismissUrl(), ['reply' => $reply->id])
            ->assertRedirect()
            ->assertSessionHas('status', 'invitation-dismissed');
        // Y un id inventado tampoco cambia el desenlace: no se dice qué respuestas existen.
        $this->post($item->invitationSignedDismissUrl(), ['reply' => 999999])
            ->assertRedirect()
            ->assertSessionHas('status', 'invitation-dismissed');
    }

    public function test_dismissing_does_not_move_the_witness_of_the_extras(): void
    {
        Carbon::setTestNow('2026-09-19 10:00:00');
        $item = $this->reservation(3, [[], [], []]);
        $reply = $this->reply($item, 'Martina Serra');
        $witness = $item->fresh()->updated_at;

        Carbon::setTestNow('2026-09-19 10:10:00');
        $this->post($item->invitationSignedDismissUrl(), ['reply' => $reply->id])->assertRedirect();
        $this->assertTrue($witness->equalTo($item->fresh()->updated_at), 'retirar una respuesta movió el testigo de la reserva');

        // ▶ CONTROL del instrumento: un guardado normal SÍ lo mueve (`#553`).
        Carbon::setTestNow('2026-09-19 10:20:00');
        $this->post($item->guestFormSignedStoreUrl(), ['guests' => [['name' => 'Hugo'], [], []]])->assertRedirect();
        $this->assertFalse($witness->equalTo($item->fresh()->updated_at), 'el testigo no se mueve ni guardando: el caso no prueba nada');

        Carbon::setTestNow();
    }

    public function test_a_reply_of_another_party_is_not_touched(): void
    {
        $mine = $this->reservation(3, [[], [], []]);
        $theirs = $this->reservation(3, [[], [], []]);
        $ajena = $this->reply($theirs, 'Martina Serra');

        $this->post($mine->invitationSignedDismissUrl(), ['reply' => $ajena->id])->assertRedirect();

        $this->assertNull($ajena->fresh()->dismissed_at, 'un id de otra fiesta no se toca aunque llegue en esta URL');
    }

    public function test_a_stranger_cannot_remove_anything(): void
    {
        $item = $this->reservation(3, [[], [], []]);
        $reply = $this->reply($item, 'Martina Serra');

        $this->post(route('reservation.invitation.dismiss', ['reservation' => $item]), ['reply' => $reply->id])
            ->assertForbidden();

        $this->assertNull($reply->fresh()->dismissed_at);
    }

    /** Tras el gesto se vuelve AL FORMULARIO, no a «Mis pedidos» (defecto de la T6·1, arreglado aquí). */
    public function test_the_host_stays_on_the_form_after_the_gesture(): void
    {
        $item = $this->reservation(3, [[], [], []]);
        $reply = $this->reply($item, 'Martina Serra');

        $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.dismiss', ['reservation' => $item]), ['reply' => $reply->id])
            ->assertRedirect(route('reservation.guests', ['reservation' => $item]));

        $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.update', ['reservation' => $item]), ['honoree_name' => 'Lucía'])
            ->assertRedirect(route('reservation.guests', ['reservation' => $item]));
    }

    // ─── Los que no caben ────────────────────────────────────────────────────────────

    public function test_the_notice_says_how_many_replies_no_longer_fit(): void
    {
        // Dos invitados, las dos fichas escritas: el tercer «sí» ya no tiene dónde caer.
        $item = $this->reservation(2, [['name' => 'Ana'], ['name' => 'Pablo']]);
        $this->reply($item, 'Martina Serra');

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();

        $this->assertStringContainsString(__('guestform.invite.overflow_title'), $html);
        $this->assertStringContainsString(trans_choice('guestform.invite.overflow', 1, ['count' => 1]), $html);
        // No se rechaza a nadie: la respuesta está aceptada y cuenta en el resumen.
        $this->assertSame(1, InvitationReply::query()->whereNull('dismissed_at')->count());
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    private function reply(OrderItem $item, string $childName, bool $attending = true): InvitationReply
    {
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);

        $outcome = app(PartyInvitations::class)->reply($invitation, $childName, $attending);
        $this->assertTrue($outcome->accepted, 'el fixture no pudo contestar: '.($outcome->reason ?? '—'));

        return $outcome->reply;
    }

    /**
     * @param  list<array<string, string>>  $guests
     */
    private function reservation(int $quantity, array $guests): OrderItem
    {
        $user = User::factory()->create();
        $slot = Slot::where('zone_id', $this->zone->id)->firstOrFail();

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'total' => $quantity * 1495, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $quantity * 1495, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (500000 + $order->id), 10, '0', STR_PAD_LEFT),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot->id, 'quantity' => $quantity,
            'unit_price' => 1495, 'seats' => $quantity, 'event_data' => ['celebrant' => 'Lucía'],
            'guest_data' => $guests,
        ]);

        return $order->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();
    }
}
