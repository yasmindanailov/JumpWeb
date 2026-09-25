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
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **«ESCRIBIR EL RECORDATORIO»** (T6·6, `docs/specs/celebracion-e-invitacion.md` §4.7).
 *
 * ❗❗ **No envía nada, y no es una carencia: es el diseño** (§2.2). Del padre no tenemos correo y no se
 * le pide, así que lo único que el parque puede hacer es escribirle el mensaje al anfitrión para que lo
 * pegue por donde ya repartió el enlace. Por eso esta unidad no toca correos ni depende de la T7.
 *
 * Lo que tiene que garantizar, y por qué importa:
 *
 *  · **«Quien falta» se mide con la MISMA regla de emparejado que la adopción y que la puerta**: si aquí
 *    divergiera, el recordatorio le reclamaría una respuesta a una familia que ya contestó.
 *  · **Una respuesta DESCARTADA es una respuesta.** «No lo apuntes» es el anfitrión quitándose algo de
 *    la lista, no un «no me han contestado»: volver a reclamársela sería el peor desenlace del botón.
 *  · **Los nombres solo si él los pide.** Una lista de «éstos no han contestado» señala a unas familias
 *    delante de las demás, y si en su chat eso se puede hacer lo sabe él.
 *  · **No mueve el testigo de los extras**, con su CONTROL: escribe solo `party_invitations`.
 */
class InvitationReminderTest extends TestCase
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

    // ─── Quién falta ─────────────────────────────────────────────────────────────────

    public function test_the_ones_we_are_missing_are_the_cards_nobody_has_answered_for(): void
    {
        $item = $this->reservation(3, [['name' => 'Ana Soler'], ['name' => 'Pablo Ortiz'], ['name' => 'Iris Vela']]);
        $this->reply($item, 'Pablo Ortiz');

        $this->assertSame(
            ['Ana Soler', 'Iris Vela'],
            app(PartyInvitations::class)->awaitingNamesIn($item),
            'quien ya contestó no falta, y quien no aparece con el nombre que escribió el anfitrión',
        );
    }

    public function test_a_card_with_only_a_first_name_is_matched_by_the_full_name_the_parent_wrote(): void
    {
        // El caso REAL: el anfitrión pega la lista de la clase («Mateo») y al padre se le piden nombre
        // y apellidos. Con una regla propia aquí, Mateo saldría como «no ha contestado» para siempre.
        $item = $this->reservation(2, [['name' => 'Mateo'], ['name' => 'Iris Vela']]);
        $this->reply($item, 'Mateo Ruiz');

        $this->assertSame(['Iris Vela'], app(PartyInvitations::class)->awaitingNamesIn($item));
    }

    public function test_a_reply_the_host_took_off_the_list_still_counts_as_answered(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);
        $reply = $this->reply($item, 'Ana Soler');

        app(PartyInvitations::class)->dismiss($item, (int) $reply->getKey());

        $this->assertSame(
            ['Iris Vela'],
            app(PartyInvitations::class)->awaitingNamesIn($item),
            '«No lo apuntes» es el anfitrión quitándose algo de la lista, no un «no me han contestado»',
        );
    }

    public function test_a_card_without_a_name_is_nobody(): void
    {
        $item = $this->reservation(3, [['name' => 'Ana Soler'], [], ['name' => '']]);

        $this->assertSame(['Ana Soler'], app(PartyInvitations::class)->awaitingNamesIn($item));
    }

    // ─── El texto ────────────────────────────────────────────────────────────────────

    public function test_the_text_carries_the_link_and_names_nobody_unless_asked(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);
        $url = app(PartyInvitations::class)->shareUrlFor($invitation);
        $this->assertNotNull($url);

        $plain = app(PartyInvitations::class)->reminderTextFor($item, withNames: false);
        $named = app(PartyInvitations::class)->reminderTextFor($item, withNames: true);

        // ⚠️ El enlace va DENTRO, al revés que `share_text`: esto se pega de una pieza en un chat.
        $this->assertStringContainsString($url, $plain);
        $this->assertStringContainsString($url, $named);
        // Quien cumple, que es de quien va la fiesta.
        $this->assertStringContainsString('Lucía', $plain);

        $this->assertStringNotContainsString('Ana Soler', $plain, 'sin la casilla, nadie va señalado');
        $this->assertStringNotContainsString('Iris Vela', $plain);
        $this->assertStringContainsString('Ana Soler', $named);
        $this->assertStringContainsString('Iris Vela', $named);
    }

    public function test_with_nobody_missing_the_text_names_nobody_even_if_asked(): void
    {
        $item = $this->reservation(1, [['name' => 'Ana Soler']]);
        $this->reply($item, 'Ana Soler');

        $text = app(PartyInvitations::class)->reminderTextFor($item, withNames: true);

        $this->assertStringNotContainsString('Ana Soler', $text);
        $this->assertStringNotContainsString(__('guestform.invite.reminder_names', ['names' => 'Ana Soler']), $text);
    }

    // ─── El gesto ────────────────────────────────────────────────────────────────────

    public function test_writing_the_reminder_records_when_and_how_many_times(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);
        $this->assertNull($invitation->reminded_at, 'nadie escribía estas columnas hasta esta unidad');
        $this->assertSame(0, (int) $invitation->reminded_count);

        $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.remind', ['reservation' => $item]))
            ->assertSessionHas('reminder_text');

        $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.remind', ['reservation' => $item]))
            ->assertSessionHas('reminder_text');

        $invitation->refresh();
        $this->assertNotNull($invitation->reminded_at);
        $this->assertSame(2, (int) $invitation->reminded_count, 'la suma la hace la BD: dos avisos son dos');
    }

    public function test_writing_the_reminder_does_not_move_the_witness_of_the_extras(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);
        $before = $item->fresh()?->updated_at;
        $this->assertNotNull($before);

        Carbon::setTestNow(now()->addMinutes(5));

        $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.remind', ['reservation' => $item]))
            ->assertSessionHas('reminder_text');

        $this->assertEquals(
            $before,
            $item->fresh()?->updated_at,
            'escribe SOLO `party_invitations`: mover el testigo le tumbaría los extras de la página abierta',
        );

        // ⚠️ El CONTROL, sin el cual la aserción de arriba pasaría aunque el testigo no se moviera
        // NUNCA: el guardado de siempre SÍ tiene que moverlo.
        $item->submitGuestForm([['name' => 'Ana Soler'], ['name' => 'Iris Vela']], null, 'account');
        $this->assertNotEquals($before, $item->fresh()?->updated_at, 'el control: guardar sí mueve el testigo');

        Carbon::setTestNow();
    }

    public function test_the_text_comes_back_on_the_screen_and_never_in_the_url(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);

        $response = $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.remind', ['reservation' => $item]), ['with_names' => '1']);

        // Lleva nombres de menores: por la sesión, nunca por un `?texto=` que acabaría en el historial
        // del navegador y en cualquier referer.
        $response->assertRedirect(route('reservation.guests', ['reservation' => $item]));
        $response->assertSessionHas('reminder_text');

        $html = $this->actingAs($item->order->user)
            ->get(route('reservation.guests', ['reservation' => $item]))
            ->assertOk()->getContent();

        // ▶ Desde `#743` (la lista del sistema nuevo) el texto vuelve escrito en su panel, listo para copiar.
        $this->assertStringContainsString('data-recordatorio-texto', $html, 'el texto tiene que estar en la pantalla');
        $this->assertStringContainsString('Ana Soler', $html);
    }

    public function test_the_seal_says_how_many_times_and_when(): void
    {
        // ⏰ **A las 00:30 de Madrid en UTC es todavía AYER**, y por eso la hora está congelada ahí: es
        // el único momento en que la conversión de zona se nota, y sin ella el sello fecharía el aviso
        // el día anterior. La trampa que ya cobró dos sesiones (`#426`, y la fecha de una decisión).
        Carbon::setTestNow(Carbon::parse(Carbon::today()->addDays(1)->toDateString().' 00:30', DisplayTime::timezone()));
        $this->assertNotSame(
            DisplayTime::now()->toDateString(),
            now()->toDateString(),
            'el instrumento primero: si el contenedor no fuera UTC, este caso no mediría nada',
        );

        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);

        $this->actingAs($item->order->user)->post(route('reservation.invitation.remind', ['reservation' => $item]));

        $html = $this->actingAs($item->order->user)
            ->get(route('reservation.guests', ['reservation' => $item]))
            ->assertOk()->getContent();

        $when = DisplayTime::dayLabel(DisplayTime::now());
        $this->assertStringContainsString(
            e(trans_choice('guestform.invite.remind_last', 1, ['count' => 1, 'when' => $when])),
            $html,
            'la fecha se escribe en la zona del PARQUE: `dayLabel()` no convierte, y la columna sale en UTC',
        );

        Carbon::setTestNow();
    }

    public function test_two_tabs_of_the_same_host_do_not_write_the_same_number_twice(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);
        $service = app(PartyInvitations::class);
        $service->forReservation($item);

        // Las dos pestañas, leídas ANTES de que ninguna escriba: las dos creen que la cuenta va por 0.
        $first = $service->existingFor($item);
        $second = $service->existingFor($item);
        $this->assertInstanceOf(PartyInvitation::class, $first);
        $this->assertInstanceOf(PartyInvitation::class, $second);

        $service->remind($first);
        $service->remind($second);

        $this->assertSame(
            2,
            (int) $first->refresh()->reminded_count,
            'la suma la hace la BD: con `$modelo->reminded_count + 1` la segunda pestaña escribiría otra vez el 1',
        );
    }

    public function test_the_counter_does_not_overflow_its_column(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);

        PartyInvitation::query()->whereKey($invitation->getKey())
            ->update(['reminded_count' => PartyInvitations::REMINDED_COUNT_MAX]);

        $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.remind', ['reservation' => $item]))
            ->assertSessionHas('reminder_text');

        $this->assertSame(
            PartyInvitations::REMINDED_COUNT_MAX,
            (int) $invitation->refresh()->reminded_count,
            'pasarse del techo de la columna haría que MySQL rechazara el UPDATE y se perdiera el texto',
        );
        $this->assertNotNull($invitation->reminded_at, 'la fecha sí se refresca');
    }

    // ─── Cuándo NO se ofrece ─────────────────────────────────────────────────────────

    public function test_the_reminder_is_not_offered_once_the_replies_are_closed(): void
    {
        // Recordar que contesten cuando el plazo ya pasó no es un recordatorio, es un engaño. La
        // fiesta es HOY a las 15:00 y son las 14:00: el enlace se sigue compartiendo (§7.2·R8) pero
        // las respuestas están cerradas desde ayer.
        Carbon::setTestNow(Carbon::parse(Carbon::today()->toDateString().' 14:00', DisplayTime::timezone()));

        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']], Carbon::today());

        $this->assertFalse(app(PartyInvitations::class)->repliesOpenFor($item));

        $html = $this->actingAs($item->order->user)
            ->get(route('reservation.guests', ['reservation' => $item]))
            ->assertOk()->getContent();

        // ▶ Desde `#743` (la lista del sistema nuevo): el gesto es «Escribir el recordatorio» (`data-recordatorio-escribir`).
        $this->assertStringNotContainsString('data-recordatorio-escribir', $html);
        // El control de este caso: con la fiesta a diez días, el botón SÍ está.
        Carbon::setTestNow();
        $open = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]);
        $this->assertStringContainsString(
            'data-recordatorio-escribir',
            $this->actingAs($open->order->user)->get(route('reservation.guests', ['reservation' => $open]))->getContent(),
        );
    }

    public function test_a_party_already_celebrated_writes_nothing(): void
    {
        $item = $this->reservation(2, [['name' => 'Ana Soler'], ['name' => 'Iris Vela']], Carbon::today()->subDays(3));
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);

        $this->actingAs($item->order->user)
            ->post(route('reservation.invitation.remind', ['reservation' => $item]))
            ->assertSessionMissing('reminder_text');

        $this->assertNull($invitation->refresh()->reminded_at);
        $this->assertSame(0, (int) $invitation->reminded_count);
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
    private function reservation(int $quantity, array $guests, ?Carbon $day = null): OrderItem
    {
        $user = User::factory()->create();
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => ($day ?? Carbon::today()->addDays(10))->toDateString(),
            'start_time' => '15:00:00', 'end_time' => '17:00:00', 'capacity' => 100, 'online_capacity' => 100,
        ]);

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
