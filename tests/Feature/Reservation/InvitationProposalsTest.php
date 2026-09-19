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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **LAS RESPUESTAS PROPUESTAS Y SU ADOPCIÓN en la web** (T6·2,
 * `docs/specs/celebracion-e-invitacion.md` §4.7 y §4.5·4).
 *
 * El dominio es de la T4·6 y ya tiene sus casos; lo que se vigila aquí es la mitad que faltaba —la
 * PANTALLA del anfitrión— y las tres propiedades por las que puede hacer daño:
 *
 *  · **El INTERCALADO**: se pinta, llega un «sí» nuevo, se guarda → la respuesta que llegó después
 *    **sigue pendiente** y no se borra nada. Es el caso que §6 pide por su nombre, y el que separa
 *    «adoptar lo que se enseñó» de «adoptar todo lo que haya».
 *  · **Lo que el anfitrión escribió MANDA**: se prerrellenan solo los campos vacíos.
 *  · **La marca viaja FUERA de la fila** (§7.2·R3), y una ficha que él vacía **no adopta nada**.
 */
class InvitationProposalsTest extends TestCase
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

    // ─── Lo que se pinta ─────────────────────────────────────────────────────────────

    public function test_a_pending_yes_lands_on_a_card_and_fills_only_what_is_empty(): void
    {
        // El anfitrión ya escribió a Hugo, sin alergia. El padre de Hugo contesta con apellidos y una
        // alergia, y contesta también el de una niña que él no tenía apuntada.
        $item = $this->reservation(4, [['name' => 'Hugo'], [], [], []]);
        $this->reply($item, 'Hugo Ruiz', ['allergy' => 'Sin gluten']);
        $this->reply($item, 'Martina Serra', []);

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();

        // Su nombre se QUEDA como él lo escribió: la propuesta rellena lo vacío, no corrige.
        $this->assertStringContainsString('name="guests[0][name]" value="Hugo"', $html, 'la propuesta pisó el nombre que escribió el anfitrión');
        $this->assertStringContainsString('name="guests[0][allergy]" value="Sin gluten"', $html, 'la alergia que llegó no se propuso en la ficha vacía');
        // La niña que no estaba apuntada cae en la primera ficha VACÍA, con su nombre completo.
        $this->assertStringContainsString('name="guests[1][name]" value="Martina Serra"', $html, 'un «sí» sin ficha propia cae en la primera vacía');
    }

    public function test_the_adoption_mark_travels_outside_the_row(): void
    {
        $item = $this->reservation(2, [[], []]);
        $reply = $this->reply($item, 'Martina Serra', []);

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="adopt[]" value="'.$reply->id.'">', $html, 'el id de la respuesta tiene que viajar en su propia lista');
        // `guests[i]` es un mapa ABIERTO de columnas del panel: meter ahí el id chocaría con una
        // columna que se llamara igual (§7.2·R3).
        $this->assertStringNotContainsString('guests[0][reply_id]', $html);
        $this->assertStringContainsString(__('guestform.invite.badge'), $html, 'falta la chapa de origen');
    }

    public function test_a_no_is_not_painted_over_any_card(): void
    {
        $item = $this->reservation(2, [[], []]);
        $this->reply($item, 'Martina Serra', [], attending: false);

        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();

        // ⚠️ Lo que NO puede pasar es que caiga en una FICHA. Desde la T6·3 su nombre sí sale, pero en
        // el grupo «No vienen», que es otra cosa: ahí no se apunta a nadie (§4.7).
        $this->assertStringNotContainsString('value="Martina Serra"', $html, 'un «no» no se propone sobre ninguna ficha');
        $this->assertStringNotContainsString('name="adopt[]"', $html);
    }

    // ─── Lo que se guarda ────────────────────────────────────────────────────────────

    public function test_saving_adopts_what_was_painted_with_the_key_of_the_card(): void
    {
        $item = $this->reservation(2, [['name' => 'Hugo'], []]);
        $reply = $this->reply($item, 'Hugo Ruiz', ['allergy' => 'Sin gluten']);

        $this->post($item->guestFormSignedStoreUrl(), [
            'guests' => [['name' => 'Hugo', 'allergy' => 'Sin gluten'], []],
            'adopt' => [$reply->id],
        ])->assertRedirect();

        $reply->refresh();
        $this->assertNotNull($reply->adopted_at, 'la respuesta pintada tenía que quedar adoptada');
        // ❗ La clave es la de la FICHA («hugo»), no la del nombre que escribió el padre («hugo ruiz»):
        // con la del padre, `reconcileAdopted()` la daba por huérfana en el mismo guardado (`#579`).
        $this->assertSame('hugo', $reply->adopted_name_key);
    }

    /**
     * ❗❗ El caso que §6 pide por su nombre.
     *
     * ⚠️⚠️ **La que llega tarde EMPAREJA con una ficha escrita, y eso es lo que hace el caso**: con
     * una ficha vacía, la respuesta tardía no se adoptaría ni queriendo —no hay nombre con el que
     * marcarla— y el caso pasaría también si el servidor adoptara TODO. Lo enseñó el arnés: la primera
     * versión de esta prueba tenía un superviviente, y el defecto estaba en ella, no en el código.
     */
    public function test_a_yes_that_arrives_after_painting_stays_pending_and_nothing_is_deleted(): void
    {
        // El anfitrión ya tenía apuntado a Hugo; Martina no estaba.
        $item = $this->reservation(3, [[], ['name' => 'Hugo'], []]);
        $pintada = $this->reply($item, 'Martina Serra', []);

        // El anfitrión pinta la pantalla (aquí, el `adopt[]` que le sale es el de esa respuesta)…
        $this->get($item->guestFormSignedUrl())->assertOk();

        // …y MIENTRAS él revisa, contesta el padre de Hugo, que SÍ tiene ficha con nombre.
        $tarde = $this->reply($item, 'Hugo Ruiz', []);

        // Guarda lo que vio: solo el id de la primera.
        $this->post($item->guestFormSignedStoreUrl(), [
            'guests' => [['name' => 'Martina Serra'], ['name' => 'Hugo'], []],
            'adopt' => [$pintada->id],
        ])->assertRedirect();

        $this->assertNotNull($pintada->fresh()->adopted_at);
        $tarde->refresh();
        $this->assertNull($tarde->adopted_at, 'la que llegó después de pintar NO puede adoptarse sola');
        $this->assertNull($tarde->dismissed_at, 'y tampoco puede descartarse: no se borra nada');
        $this->assertSame(2, InvitationReply::query()->count(), 'ninguna respuesta desaparece al guardar');

        // Y sale sola en el siguiente render, sobre la primera ficha libre.
        $html = $this->get($item->guestFormSignedUrl())->assertOk()->getContent();
        $this->assertStringContainsString('name="adopt[]" value="'.$tarde->id.'"', $html);
    }

    public function test_a_card_the_host_empties_adopts_nothing(): void
    {
        $item = $this->reservation(2, [[], []]);
        $reply = $this->reply($item, 'Martina Serra', []);

        // Vio la propuesta y la borró: no la quiere. El `adopt[]` sigue viajando —es marcado, no una
        // decisión—, y es el dominio quien mira si quedó ficha con nombre.
        $this->post($item->guestFormSignedStoreUrl(), [
            'guests' => [[], []],
            'adopt' => [$reply->id],
        ])->assertRedirect();

        $reply->refresh();
        $this->assertNull($reply->adopted_at, 'una ficha vacía no adopta a nadie');
        $this->assertNull($reply->dismissed_at, 'y la respuesta sigue viva hasta que él la quite');
    }

    public function test_an_adopted_reply_whose_name_disappears_is_discarded(): void
    {
        $item = $this->reservation(2, [[], []]);
        $reply = $this->reply($item, 'Martina Serra', []);

        $this->post($item->guestFormSignedStoreUrl(), [
            'guests' => [['name' => 'Martina Serra'], []],
            'adopt' => [$reply->id],
        ])->assertRedirect();
        $this->assertNotNull($reply->fresh()->adopted_at);

        // El anfitrión la quita de su lista en un guardado posterior.
        $this->post($item->guestFormSignedStoreUrl(), ['guests' => [[], []]])->assertRedirect();

        $this->assertNotNull($reply->fresh()->dismissed_at, 'una adoptada sin ficha se esconde para siempre y sigue ocupando plaza');
    }

    public function test_an_adopt_from_another_party_adopts_nothing(): void
    {
        $mine = $this->reservation(2, [[], []]);
        $theirs = $this->reservation(2, [[], []]);
        $ajena = $this->reply($theirs, 'Martina Serra', []);

        $this->post($mine->guestFormSignedStoreUrl(), [
            'guests' => [['name' => 'Martina Serra'], []],
            'adopt' => [$ajena->id],
        ])->assertRedirect();

        $this->assertNull($ajena->fresh()->adopted_at, 'un id de otra fiesta no adopta nada');
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $data
     */
    private function reply(OrderItem $item, string $childName, array $data, bool $attending = true): InvitationReply
    {
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);

        $outcome = app(PartyInvitations::class)->reply($invitation, $childName, $attending, null, $data);
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
