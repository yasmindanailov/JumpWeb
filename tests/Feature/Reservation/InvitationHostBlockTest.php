<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **EL BLOQUE DE LA INVITACIÓN en el formulario del anfitrión** (T6·1,
 * `docs/specs/celebracion-e-invitacion.md` §4.7; canvas `doc/formulario.md`, turno 3a).
 *
 * Lo que vigilan estos casos no es «que se pinte»: son las cuatro propiedades por las que este bloque
 * puede romper algo que ya funcionaba.
 *
 *  · **El TESTIGO no se mueve.** Personalizar escribe SOLO `party_invitations`, y `order_items.updated_at`
 *    es lo que el post-form usa para detectar que el parque movió la reserva: si esto lo tocara, cambiar
 *    el tema de una banda dejaría obsoleta la página abierta y tumbaría la compra de los extras. El caso
 *    lleva su CONTROL —un guardado normal SÍ lo mueve—, porque una aserción que no puede fallar no prueba
 *    nada (`#553`).
 *  · **Sin nombre no se reparte** (§4.5·2) y la pantalla lo DICE, con el remedio abierto al lado.
 *  · **Un texto con enlace no se publica** (§7.2·R9) **y se dice**: el campo se queda como estaba, y
 *    callarlo dejaría al anfitrión creyendo que su frase salió.
 *  · **La misma puerta que el formulario**: un desconocido no personaliza, y el enlace firmado —que es
 *    como llega el anfitrión que no tiene sesión— sí.
 */
class InvitationHostBlockTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = Carbon::today()->addDays(10)->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true,
            'max_per_slot' => 3, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => false, 'position' => 1,
        ]);

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date,
            'start_time' => '15:00:00', 'end_time' => '17:00:00',
            'capacity' => 100, 'online_capacity' => 100,
        ]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 1,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => true,
            // ⚠️ `honoree_name` se prerrellena desde la primera columna de TEXTO de los datos de la
            // reserva y la edad desde la de tipo `celebrant_age` (§4.5·1): sin `event_fields` no hay
            // de dónde leerlas y la invitación nacería en blanco, que es otro caso.
            'event_fields' => [
                ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Quién cumple']],
                ['key' => 'age', 'type' => TicketType::FIELD_TYPE_CELEBRANT_AGE, 'required' => false, 'label' => ['es' => 'Años que cumple']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);
    }

    // ─── Lo que se ve ────────────────────────────────────────────────────────────────

    public function test_the_block_hands_over_the_link_the_deadline_as_a_date_and_the_tally(): void
    {
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();

        $invitation = PartyInvitation::query()->where('order_item_id', $item->id)->firstOrFail();

        // El enlace, ESCRITO: sin JavaScript no hay ni Web Share ni portapapeles.
        $this->assertStringContainsString(route('invitation.show', ['token' => $invitation->token]), $html, 'el anfitrión tiene que poder leer el enlace');
        $this->assertStringContainsString('data-invite-share', $html, 'falta el atajo de compartir');
        $this->assertStringContainsString('data-invite-copy', $html, 'falta el atajo de copiar');

        // El PLAZO escrito como fecha (canvas, turno 3a), y no un número de horas que haya que sumar.
        $deadline = app(GuestCountPolicy::class)->deadlineFor($item);
        $this->assertNotNull($deadline);
        $this->assertStringContainsString(
            __('guestform.invite.deadline', ['when' => DisplayTime::dayLabel($deadline)]),
            $html,
            'el plazo se escribe como FECHA'
        );

        // El resumen de §4.7, con sus tres cifras y sin ninguna respuesta todavía.
        $this->assertStringContainsString(trans_choice('guestform.invite.tally_yes', 0, ['count' => 0]), $html);
        $this->assertStringContainsString(trans_choice('guestform.invite.tally_pending', 0, ['count' => 0]), $html);
    }

    public function test_a_product_without_the_invitation_paints_nothing_and_creates_no_row(): void
    {
        $this->pack->forceFill(['guest_invitation' => false])->save();
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);

        $this->get($item->guestFormSignedUrl())
            ->assertOk()
            ->assertDontSee('gf-invite');

        $this->assertSame(0, PartyInvitation::query()->count(), 'un producto sin invitación no materializa ninguna fila');
    }

    public function test_without_a_name_it_says_what_is_missing_instead_of_handing_over_the_link(): void
    {
        // Sin `celebrant` en los datos de la reserva, `honoree_name` nace vacío: no se puede compartir.
        $item = $this->reservation(10, []);

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();
        $invitation = PartyInvitation::query()->where('order_item_id', $item->id)->firstOrFail();

        $this->assertStringNotContainsString((string) $invitation->token, $html, 'el token no puede repartirse antes de que la invitación diga de quién es la fiesta');
        $this->assertStringContainsString(__('guestform.invite.needs_name'), $html, 'la pantalla tiene que decir qué falta');
        // El remedio está DENTRO del desplegable, así que nace abierto.
        $this->assertMatchesRegularExpression('#<details class="gf-invite__custom"\s+open\s*>#', $html, 'con el nombre por escribir, «Personalizar» se abre solo');
    }

    // ─── El testigo, con su control ──────────────────────────────────────────────────

    public function test_personalising_writes_only_the_invitation_and_never_the_witness(): void
    {
        Carbon::setTestNow('2026-09-19 10:00:00');
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);
        $witness = $item->fresh()->updated_at;

        Carbon::setTestNow('2026-09-19 10:10:00');
        $this->asHost($item)->post(route('reservation.invitation.update', ['reservation' => $item]), [
            'theme' => PartyInvitation::THEME_SERENO,
            'honoree_name' => 'Lucía Serra',
            'honoree_age' => 8,
            'host_line' => 'Te invita Marta',
            'show_host_phone' => '1',
        ])->assertRedirect();

        $invitation = PartyInvitation::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(PartyInvitation::THEME_SERENO, $invitation->theme);
        $this->assertSame('Lucía Serra', $invitation->honoree_name);
        $this->assertSame(8, $invitation->honoree_age);
        $this->assertSame('Te invita Marta', $invitation->host_line);
        $this->assertTrue((bool) $invitation->show_host_phone);

        $this->assertTrue(
            $witness->equalTo($item->fresh()->updated_at),
            'personalizar movió el testigo de la reserva: la página abierta del anfitrión quedaría obsoleta'
        );

        // ▶ CONTROL del instrumento: un guardado NORMAL sí lo mueve. Sin esta mitad, la aserción de
        // arriba pasaría igual con un `updated_at` que no se moviera nunca (`#553`).
        Carbon::setTestNow('2026-09-19 10:20:00');
        $this->asHost($item)->post(route('reservation.guests.store', ['reservation' => $item]), [
            'guests' => [['name' => 'Hugo']],
        ])->assertRedirect();

        $this->assertFalse(
            $witness->equalTo($item->fresh()->updated_at),
            'el testigo no se mueve ni al guardar el formulario: entonces este caso no prueba nada'
        );

        Carbon::setTestNow();
    }

    // ─── El texto libre ──────────────────────────────────────────────────────────────

    public function test_a_text_with_a_link_is_refused_and_the_page_says_so(): void
    {
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);

        $this->asHost($item)->post(route('reservation.invitation.update', ['reservation' => $item]), [
            'honoree_name' => 'Lucía',
            'host_line' => 'Paga el regalo en playjump.es',
        ])->assertRedirect()->assertSessionHas('status', 'invitation-text-rejected');

        $invitation = PartyInvitation::query()->where('order_item_id', $item->id)->firstOrFail();
        $this->assertStringNotContainsString('playjump.es', (string) $invitation->host_line, 'un enlace no se publica bajo el dominio del parque');

        // Y el aviso se LEE en la vuelta: un rechazo silencioso deja al anfitrión creyendo que salió.
        $this->get($item->guestFormSignedUrl())
            ->assertOk()
            ->assertSee(__('guestform.invite.rejected_title'));
    }

    public function test_an_empty_line_is_not_a_refusal(): void
    {
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);

        $this->asHost($item)->post(route('reservation.invitation.update', ['reservation' => $item]), [
            'honoree_name' => 'Lucía',
            'host_line' => '',
        ])->assertRedirect()->assertSessionHas('status', 'invitation-saved');
    }

    public function test_unticking_the_phone_turns_it_off(): void
    {
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);

        $this->asHost($item)->post(route('reservation.invitation.update', ['reservation' => $item]), ['show_host_phone' => '1'])->assertRedirect();
        $this->assertTrue((bool) PartyInvitation::query()->where('order_item_id', $item->id)->value('show_host_phone'));

        // ⚠️ La casilla desmarcada no se envía: lo que llega es el `hidden` de delante, y sin él la
        // clave estaría AUSENTE y el dominio la leería como «no lo toques» (es un PATCH).
        $this->asHost($item)->post(route('reservation.invitation.update', ['reservation' => $item]), ['show_host_phone' => '0'])->assertRedirect();
        $this->assertFalse((bool) PartyInvitation::query()->where('order_item_id', $item->id)->value('show_host_phone'));

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();
        $this->assertStringContainsString('<input type="hidden" name="show_host_phone" value="0">', $html, 'sin el hidden, desmarcar no apagaría nunca');
    }

    // ─── La puerta ───────────────────────────────────────────────────────────────────

    public function test_a_stranger_cannot_personalise_it(): void
    {
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);
        // La invitación ya existe: la materializó el anfitrión al abrir su formulario. ⚠️ Se entra por
        // el enlace FIRMADO y no con `actingAs`, que dejaría la sesión puesta para el resto del caso y
        // convertiría al «desconocido» de abajo en el propio titular.
        $this->get($item->guestFormSignedUrl())->assertOk();

        // Ni de paso ni con sesión de otra cuenta: la firma cubre ESTA reserva y la sesión, a SU dueño.
        $this->post(route('reservation.invitation.update', ['reservation' => $item]), ['honoree_name' => 'Otro'])
            ->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post(route('reservation.invitation.update', ['reservation' => $item]), ['honoree_name' => 'Otro'])
            ->assertForbidden();

        $this->assertSame('Lucía', (string) PartyInvitation::query()->where('order_item_id', $item->id)->value('honoree_name'));
    }

    public function test_the_signed_url_personalises_without_a_session(): void
    {
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);

        // Así llega el anfitrión que abre el enlace del correo: sin sesión, con la firma como prueba.
        $this->post($item->invitationSignedUpdateUrl(), ['honoree_name' => 'Lucía Serra'])
            ->assertRedirect();

        $this->assertSame('Lucía Serra', (string) PartyInvitation::query()->where('order_item_id', $item->id)->value('honoree_name'));
    }

    public function test_a_party_already_held_neither_paints_the_block_nor_personalises(): void
    {
        $item = $this->reservation(10, ['celebrant' => 'Lucía']);
        // La fiesta ya pasó: el formulario entero es de solo lectura.
        $item->slot->forceFill(['date' => Carbon::today()->subDays(2)->toDateString()])->save();
        $item = $item->fresh(['ticketType', 'slot', 'order']);

        $this->get($item->guestFormSignedUrl())
            ->assertOk()
            ->assertDontSee('gf-invite');

        $this->assertSame(0, PartyInvitation::query()->count(), 'una fiesta celebrada no materializa la invitación de nadie');

        $this->post($item->invitationSignedUpdateUrl(), ['honoree_name' => 'Otro'])
            ->assertRedirect()
            ->assertSessionHas('status', 'guest-form-readonly');

        $this->assertSame(0, PartyInvitation::query()->count());
    }

    /** Como entra el anfitrión que SÍ tiene sesión: el titular del pedido. */
    private function asHost(OrderItem $item): self
    {
        return $this->actingAs($item->order->user);
    }

    /**
     * @param  array<string, string>  $eventData
     */
    private function reservation(int $guests, array $eventData): OrderItem
    {
        $user = User::factory()->create();
        $slot = Slot::where('zone_id', $this->zone->id)->firstOrFail();

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'total' => $guests * 1495, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $guests * 1495, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (500000 + $order->id), 10, '0', STR_PAD_LEFT),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot->id, 'quantity' => $guests,
            'unit_price' => 1495, 'seats' => $guests, 'event_data' => $eventData,
        ]);

        return $order->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();
    }
}
