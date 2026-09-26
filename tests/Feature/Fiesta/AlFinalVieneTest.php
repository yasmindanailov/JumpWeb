<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **«AL FINAL VIENE»** (F3c de `specs/fiesta-sistema-nuevo.md` §4.8, `[DECIDIDO owner]` `#747`).
 *
 * La familia dijo que no, cambió de opinión y se lo dijo al anfitrión, que la vuelve a contar. Como el `volver()` del
 * diseño, la respuesta pasa a «sí» —con el rastro de que la cambió el anfitrión— y queda adoptada en su ficha: la de su
 * nombre si ya estaba apuntado, o la primera ficha libre de invitado. Lo que se vigila:
 *
 *  1. ❗ Solo un «no» PENDIENTE de ESTA reserva: el id llega de un formulario público y no se cree.
 *  2. ❗ Sin ficha libre NO entra y se dice: hasta F4 la lista no pasa del número.
 *  3. ❗ Cuenta: ocupa su plaza en el suelo, como un «sí».
 *  4. La ficha de quien cumple no es candidata; la web y la API hacen lo mismo.
 */
class AlFinalVieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_no_that_the_host_had_written_comes_back_on_his_own_card(): void
    {
        [$reservation, $invitation] = $this->party();
        $reservation->submitGuestForm([['name' => 'Mateo'], ['name' => 'Irene']], null, 'account');
        $no = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Irene Castillo', false);

        $r = app(PartyInvitations::class)->rejoin($reservation->fresh(['ticketType', 'order']) ?? $reservation, [$no->getKey()]);

        $this->assertSame(['rejoined' => 1, 'full' => 0], $r);
        $no = $no->fresh();
        $this->assertTrue($no?->attending, 'la respuesta no pasó a «sí»');
        $this->assertNotNull($no?->host_rejoined_at, 'no queda el rastro de que la cambió el anfitrión');
        $this->assertSame(PersonNameKey::for('Irene'), $no?->adopted_name_key, 'no se adoptó en SU ficha');
        $this->assertSame([['name' => 'Mateo'], ['name' => 'Irene']], array_slice($reservation->fresh()?->guestData() ?? [], 0, 2), 'se tocaron las fichas');
        $this->assertSame(1, AuditLog::query()->where('action', 'orders.invitation_replies_rejoined')->count());
    }

    public function test_a_no_without_a_card_takes_the_first_free_one(): void
    {
        [$reservation, $invitation] = $this->party();
        $reservation->submitGuestForm([['name' => 'Mateo']], null, 'account');
        $no = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Irene Castillo', false);

        app(PartyInvitations::class)->rejoin($reservation->fresh(['ticketType', 'order']) ?? $reservation, [$no->getKey()]);

        $this->assertSame(['name' => 'Irene Castillo'], $reservation->fresh()?->guestData()[1] ?? null, 'no entró en la primera ficha libre');
        $this->assertTrue($no->fresh()?->attending);
    }

    /** La ficha de quien cumple no es una ficha libre aunque todavía no tenga nombre (`#747`). */
    public function test_the_honoree_card_is_never_taken(): void
    {
        [$reservation, $invitation] = $this->party(honoreeRow: true);
        $reservation->submitGuestForm([[], ['name' => 'Mateo']], null, 'account');
        $no = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Irene Castillo', false);

        app(PartyInvitations::class)->rejoin($reservation->fresh(['ticketType', 'order']) ?? $reservation, [$no->getKey()]);

        $rows = $reservation->fresh()?->guestData() ?? [];
        $this->assertNotSame('Irene Castillo', $rows[0]['name'] ?? null, 'entró en la ficha de quien cumple');
        $this->assertSame('Irene Castillo', $rows[2]['name'] ?? null);
    }

    public function test_without_a_free_card_it_does_not_come_back_and_says_so(): void
    {
        [$reservation, $invitation] = $this->party(quantity: 2);
        $reservation->submitGuestForm([['name' => 'Mateo'], ['name' => 'Lola']], null, 'account');
        $no = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Irene Castillo', false);

        $r = app(PartyInvitations::class)->rejoin($reservation->fresh(['ticketType', 'order']) ?? $reservation, [$no->getKey()]);

        $this->assertSame(['rejoined' => 0, 'full' => 1], $r);
        $this->assertFalse($no->fresh()?->attending, 'volvió sin ficha: una plaza que nadie ve');
    }

    /** ❗ El id no se cree: un «sí», una respuesta ya resuelta o la de otra fiesta se ignoran. */
    public function test_only_a_pending_no_of_this_reservation_comes_back(): void
    {
        [$reservation, $invitation] = $this->party();
        [$otra, $otraInvitacion] = $this->party();
        $si = $this->reply($reservation, $invitation, 'Hugo Ruiz', true);
        $ajena = $this->reply($otra, $otraInvitacion, 'Irene Castillo', false);
        $descartada = $this->reply($reservation, $invitation, 'Pablo Gil', false);
        $descartada->forceFill(['dismissed_at' => now()])->save();

        $r = app(PartyInvitations::class)->rejoin($reservation->fresh(['ticketType', 'order']) ?? $reservation, [$si->getKey(), $ajena->getKey(), $descartada->getKey()]);

        $this->assertSame(['rejoined' => 0, 'full' => 0], $r);
        $this->assertNull($si->fresh()?->host_rejoined_at);
        $this->assertFalse($ajena->fresh()?->attending, 'volvió una respuesta de OTRA fiesta');
        $this->assertFalse($descartada->fresh()?->attending, 'volvió una respuesta descartada');
    }

    /** ❗ AFORO: el que vuelve ocupa su plaza en el suelo, como un «sí». */
    public function test_the_one_who_comes_back_takes_a_place(): void
    {
        [$reservation, $invitation] = $this->party();
        $no = $this->reply($reservation, $invitation, 'Irene Castillo', false);
        $antes = app(ReservationPlacesTaken::class)->takenIn((int) $reservation->getKey());

        app(PartyInvitations::class)->rejoin($reservation->fresh(['ticketType', 'order']) ?? $reservation, [$no->getKey()]);

        $this->assertSame($antes + 1, app(ReservationPlacesTaken::class)->takenIn((int) $reservation->getKey()), 'el que vuelve no ocupa plaza');
    }

    /**
     * La lista: cada «no» lleva «Al final viene» como botón de ENVÍO con su id (sin JavaScript, guarda y lo vuelve a
     * contar); guardar con `rejoin[]` lo vuelve a contar, y sin sitio se DICE.
     */
    public function test_the_list_offers_it_and_saving_brings_them_back(): void
    {
        [$reservation, $invitation, $host] = $this->party(quantity: 2);
        $reservation->submitGuestForm([['name' => 'Mateo']], null, 'account');
        $no = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Irene Castillo', false);
        $lista = route('reservation.guests', ['reservation' => $reservation]);

        $html = (string) $this->actingAs($host)->get($lista)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<button[^>]*type="submit"[^>]*name="rejoin\[\]"[^>]*value="'.$no->getKey().'"#', $html, '«Al final viene» no es un botón de envío con su id');

        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Mateo'], []], 'rejoin' => [$no->getKey()],
        ])->assertRedirect()->assertSessionHas('status', 'guest-form-saved');
        $this->assertTrue($no->fresh()?->attending);
        $this->assertSame('Irene Castillo', $reservation->fresh()?->guestData()[1]['name'] ?? null);

        // Sin sitio (2 de 2 y uno más que vuelve): desde F4 se PARA antes de escribir nada y se pregunta por el número.
        $otro = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Pablo Gil', false);
        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Mateo'], ['name' => 'Irene Castillo']], 'rejoin' => [$otro->getKey()],
        ])->assertRedirect()->assertSessionHas('status', 'guest-count-unconfirmed');
        $this->assertFalse($otro->fresh()?->attending);
        // Si aun así una vuelta no cabe (dos pestañas a la vez), se dice.
        $this->assertStringContainsString('ya no cabe nadie más', (string) $this->actingAs($host)->withSession(['status' => 'invitation-rejoin-full'])->get($lista)->getContent());
    }

    /** El «no» que empareja con una ficha que el anfitrión escribió también lo ofrece, con SU id. */
    public function test_a_no_on_a_card_the_host_wrote_offers_it_too(): void
    {
        [$reservation, $invitation, $host] = $this->party();
        $reservation->submitGuestForm([['name' => 'Mateo'], ['name' => 'Irene']], null, 'account');
        $no = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Irene Castillo', false);

        $html = (string) $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#data-indice="1".*?<button[^>]*name="rejoin\[\]"[^>]*value="'.$no->getKey().'"#s', $html, 'la ficha del «no» no ofrece «Al final viene»');
    }

    /**
     * ⚠️ F4 (§4.9): un «no» que empareja con una ficha del anfitrión SIGUE siendo su ficha —viaja y cuenta en el número—,
     * porque el emparejado por nombre no es seguro: su «Irene» puede no ser la «Irene Castillo» que dijo que no. Lo decide
     * el anfitrión (T6·3, «nadie se quita solo»). Y volverla a contar no pide otra plaza: ya tiene su ficha.
     */
    public function test_a_no_on_a_card_keeps_its_card_and_coming_back_needs_no_new_place(): void
    {
        [$reservation, $invitation, $host] = $this->party(quantity: 2);
        $reservation->submitGuestForm([['name' => 'Mateo'], ['name' => 'Irene']], null, 'account');
        $no = $this->reply($reservation->fresh() ?? $reservation, $invitation, 'Irene Castillo', false);

        $pagina = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk();
        $this->assertSame(2, $pagina->viewData('m')['numero']['en_lista'], 'la ficha del «no» dejó de contar');
        $this->assertStringContainsString('name="guests[1][name]" value="Irene"', (string) $pagina->getContent(), 'la ficha del «no» dejó de viajar');

        // Con la reserva llena (2 de 2), volverla a contar cabe: no pide ficha nueva, la guarda no la cuenta dos veces.
        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Mateo'], ['name' => 'Irene']], 'rejoin' => [$no->getKey()],
        ])->assertRedirect()->assertSessionHas('status', 'guest-form-saved');
        $this->assertTrue($no->fresh()?->attending, 'la vuelta sobre su propia ficha no entró');
    }

    /** API-first: la app lo hace con el mismo guardado (`PUT` del formulario, `rejoin`, contrato 1.36.0). */
    public function test_the_api_brings_them_back_too(): void
    {
        [$reservation, $invitation] = $this->party();
        $no = $this->reply($reservation, $invitation, 'Irene Castillo', false);

        $this->putJson($reservation->guestFormApiUrls()['save'], ['rejoin' => [$no->getKey()]])->assertOk();

        $this->assertTrue($no->fresh()?->attending, 'la API no lo vuelve a contar');
    }

    // ── Fixture ──────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: PartyInvitation, 2: User} */
    private function party(bool $honoreeRow = false, int $quantity = 6): array
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(
            ['zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'position' => 1],
            [
                'name' => ['es' => 'Pack Kids'], 'duration_min' => 120, 'seats_per_unit' => 1,
                'min_qty' => 1, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true, 'guest_invitation' => true,
                'guest_fields' => [
                    ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                    ['key' => 'age', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => false, 'label' => ['es' => 'Edad']],
                ],
                'event_fields' => [
                    ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Quién cumple']],
                ],
            ],
        );
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(), 'start_time' => '17:00:00'],
            ['end_time' => '19:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );
        $host = User::factory()->create(['name' => 'Marta Anfitriona', 'phone' => '600111222']);
        $order = Order::create([
            'user_id' => $host->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $reservation = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'unit_price' => 500, 'seats' => $quantity,
            'event_data' => ['celebrant' => 'Lucía'], 'honoree_row' => $honoreeRow,
        ]);
        $reservation = $reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation;
        $invitation = app(PartyInvitations::class)->forReservation($reservation);
        $this->assertNotNull($invitation);

        return [$reservation, $invitation, $host];
    }

    private function reply(OrderItem $reservation, PartyInvitation $invitation, string $child, bool $attending): InvitationReply
    {
        return InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $reservation->getKey(),
            'attending' => $attending,
            'child_name' => $child,
            'child_key' => PersonNameKey::for($child),
        ]);
    }
}
