<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Notifications\GuestFormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Post-form de datos por invitado (#217), INDIVIDUALIZADO POR RESERVA — cara cliente: el parámetro de
 * ruta es el `OrderItem` del pack (1 post-form por reserva, no por pedido). Cubre acceso (firma del
 * email o dueño autenticado, anti-IDOR, RGPD), guardado server-side, estado derivado, progreso y email.
 */
class GuestFormTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function pack(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
                ['key' => 'adults', 'type' => 'number', 'required' => false, 'stage' => 'postform', 'label' => ['es' => 'Adultos']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);
    }

    private function paidOrder(User $user, TicketType $type, int $qty = 2, array $eventData = ['celebrant' => 'Mara']): Order
    {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => null, 'quantity' => $qty,
            'unit_price' => 1000, 'seats' => $qty, 'event_data' => $eventData,
        ]);

        return $order;
    }

    /** La reserva (item principal de pack) de un pedido. */
    private function reservation(Order $order): OrderItem
    {
        return $order->items()->whereNull('parent_item_id')->firstOrFail();
    }

    /** Una franja que YA TERMINÓ (para probar el modo solo-lectura). */
    private function pastSlot(): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->subDays(2)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
    }

    // ─── Acceso ──────────────────────────────────────────────────────────────

    public function test_owner_can_view_the_form(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)
            ->get(route('reservation.guests', ['reservation' => $reservation]))
            ->assertOk()
            ->assertSee('Cumpleaños Jump')       // título = el producto de ESTA reserva
            ->assertSee('Adultos')               // campo general postform
            ->assertSee('Nombre')                // columna por-niño (guest_fields)
            ->assertSee('0 de 2 fichas completas') // indicador de progreso
            ->assertDontSee('Homenajeado');      // el celebrante es booking → NO se pide aquí
    }

    public function test_non_owner_is_forbidden(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $this->actingAs(User::factory()->create())
            ->get(route('reservation.guests', ['reservation' => $reservation]))
            ->assertForbidden();
    }

    public function test_guest_without_signature_is_forbidden(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $this->get(route('reservation.guests', ['reservation' => $reservation]))->assertForbidden();
    }

    public function test_signed_link_grants_access_without_login(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $this->get(URL::signedRoute('reservation.guests', ['reservation' => $reservation]))
            ->assertOk()
            ->assertSee('Cumpleaños Jump');
    }

    public function test_unpaid_order_returns_404(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user, $this->pack());
        $order->update(['status' => Order::STATUS_PENDING]);

        $this->actingAs($user)
            ->get(route('reservation.guests', ['reservation' => $this->reservation($order)]))
            ->assertNotFound();
    }

    public function test_non_pack_reservation_returns_404(): void
    {
        // La ruta solo sirve reservas de cumpleaños con post-form: una entrada (sin guest_fields) → 404.
        $entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $entry));

        $this->actingAs($user)
            ->get(route('reservation.guests', ['reservation' => $reservation]))
            ->assertNotFound();
    }

    public function test_cancelled_reservation_returns_404(): void
    {
        // Una reserva CANCELADA, aunque sea un pack con post-form, NO sirve el formulario:
        // `isGuestFormReservation()` excluye los cancelados (`cancelled_at`) → 404. Backstop de
        // servidor que respalda la ocultación del botón en «Mis reservas» (vista) para una cancelada.
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $reservation->forceFill(['cancelled_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('reservation.guests', ['reservation' => $reservation]))
            ->assertNotFound();
    }

    public function test_signed_link_of_one_reservation_does_not_open_another(): void
    {
        // Anti-IDOR: la firma cubre la URL de SU reserva; reusarla cambiando el id a otra reserva NO
        // valida → 403. Y el dueño de un pedido tampoco accede a la reserva de OTRO (sin firma) → 403.
        $pack = $this->pack();
        $a = $this->reservation($this->paidOrder(User::factory()->create(), $pack));
        $b = $this->reservation($this->paidOrder(User::factory()->create(), $pack));

        $signedA = URL::signedRoute('reservation.guests', ['reservation' => $a]);
        $forgedB = str_replace('/reserva/'.$a->id.'/', '/reserva/'.$b->id.'/', $signedA);

        $this->get($forgedB)->assertForbidden();                                  // firma de A no abre B
        $this->get(route('reservation.guests', ['reservation' => $b]))->assertForbidden(); // sin firma/sesión
    }

    public function test_anonymized_owner_blocks_guest_form_get_and_post(): void
    {
        // P5 (auditoría Fase 1 · RGPD art.17): tras anonimizar al titular, el enlace firmado NO debe
        // seguir abriendo NI re-escribiendo nombres/alergias de menores en la reserva «borrada».
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $user->anonymize();

        $this->get(URL::signedRoute('reservation.guests', ['reservation' => $reservation]))->assertStatus(410);
        $this->post(URL::signedRoute('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Re-introducido']],
        ])->assertStatus(410);

        $this->assertNull($reservation->fresh()->guest_data); // no se re-escribió nada
    }

    public function test_guest_form_response_is_not_stored_in_cache(): void
    {
        // L1 (auditoría Fase 1 · RGPD art.9): la página lleva nombres+alergias de menores → `no-store`.
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $response = $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $reservation]));
        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    // ─── Reserva ya celebrada → solo lectura ─────────────────────────────────

    public function test_finished_reservation_is_read_only(): void
    {
        // Decisión UX: una reserva cuya franja ya pasó muestra el formulario en SOLO LECTURA — sin
        // botón de guardar (el dato del post-form solo sirve para preparar la fiesta).
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $reservation->forceFill(['slot_id' => $this->pastSlot()->id])->save();

        $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $reservation]))
            ->assertOk()
            ->assertSee(__('guestform.readonly_notice'))
            ->assertDontSee(__('guestform.hint'));  // el bloque de guardar (hint incluido) no se renderiza
    }

    public function test_store_on_finished_reservation_does_not_save(): void
    {
        // Blindaje: un POST a una reserva ya celebrada (pestaña vieja / forjado) NO guarda ni
        // re-introduce datos de menores.
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $reservation->forceFill(['slot_id' => $this->pastSlot()->id])->save();

        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Tarde']],
        ])->assertRedirect();

        $this->assertNull($reservation->fresh()->guest_data);  // no se guardó (solo lectura)
    }

    // ─── Guardado ────────────────────────────────────────────────────────────

    public function test_owner_can_save_guest_data_and_marks_completed(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)
            ->post(route('reservation.guests.store', ['reservation' => $reservation]), [
                'guests' => [
                    ['name' => '  Ana ', 'allergy' => 'Gluten'],
                    ['name' => 'Leo'],
                ],
            ])
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('status', 'guest-form-saved');

        $fresh = $reservation->fresh();
        $this->assertSame([['name' => 'Ana', 'allergy' => 'Gluten'], ['name' => 'Leo']], $fresh->guest_data);
        $this->assertNotNull($fresh->guest_form_completed_at);
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_OK, $fresh->load('ticketType')->guestFormStatus());
    }

    public function test_partial_save_stays_pending(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)
            ->post(route('reservation.guests.store', ['reservation' => $reservation]), [
                'guests' => [['name' => 'Ana']],  // falta el 2.º niño
            ])
            ->assertRedirect();

        $fresh = $reservation->fresh()->load('ticketType');
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_PENDING, $fresh->guestFormStatus());
        $this->assertNull($fresh->guest_form_completed_at);
    }

    public function test_save_merges_postform_data_preserving_booking(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack(), eventData: ['celebrant' => 'Mara']));

        $this->actingAs($user)
            ->post(route('reservation.guests.store', ['reservation' => $reservation]), [
                'guests' => [['name' => 'Ana'], ['name' => 'Leo']],
                'general' => ['adults' => '4 personas'],  // number → solo dígitos
            ])
            ->assertRedirect();

        // El dato de la fase de reserva (celebrant) se PRESERVA; se añade el postform (adults).
        $this->assertSame(['celebrant' => 'Mara', 'adults' => '4'], $reservation->fresh()->event_data);
    }

    public function test_clearing_a_postform_field_removes_it_keeping_booking(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack(), eventData: ['celebrant' => 'Mara', 'adults' => '5']));

        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Ana'], ['name' => 'Leo']],
            'general' => ['adults' => ''],  // se vacía el campo postform
        ])->assertRedirect();

        // celebrant (booking) se conserva; adults (postform vaciado) desaparece.
        $this->assertSame(['celebrant' => 'Mara'], $reservation->fresh()->event_data);
    }

    public function test_save_via_signed_link_works_without_login(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $this->post(URL::signedRoute('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Ana'], ['name' => 'Leo']],
        ])->assertRedirect();  // a la recarga firmada

        $this->assertSame([['name' => 'Ana'], ['name' => 'Leo']], $reservation->fresh()->guest_data);
    }

    public function test_save_is_audited_with_the_reservation_item(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user, $this->pack());
        $reservation = $this->reservation($order);

        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Ana'], ['name' => 'Leo']],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'orders.guest_form_submitted',
            'target_id' => $order->id,
        ]);
        $log = AuditLog::where('action', 'orders.guest_form_submitted')->latest()->first();
        $this->assertSame($reservation->id, $log->payload['order_item_id']); // individualizado por reserva
    }

    // ─── Una reserva por página (individualización) ──────────────────────────

    public function test_each_pack_reservation_has_its_own_isolated_form(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-MULTI', 'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $packA = $this->pack();
        $packB = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 90, 'min_qty' => 2, 'max_qty' => 15, 'seats_per_unit' => 1, 'is_sellable' => true,
            'is_active' => true, 'position' => 10,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $a = $order->items()->create(['ticket_type_id' => $packA->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);
        $b = $order->items()->create(['ticket_type_id' => $packB->id, 'quantity' => 3, 'unit_price' => 800, 'seats' => 3]);

        // La página de la reserva A muestra SOLO la reserva A (su producto), no la B.
        $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $a]))
            ->assertOk()->assertSee('Cumpleaños Jump')->assertDontSee('Cumpleaños Kids');
        $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $b]))
            ->assertOk()->assertSee('Cumpleaños Kids')->assertDontSee('Cumpleaños Jump');

        // Guardar en A no toca B (datos independientes por reserva).
        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $a]), [
            'guests' => [['name' => 'Ana'], ['name' => 'Leo']],
        ])->assertRedirect();

        $this->assertSame([['name' => 'Ana'], ['name' => 'Leo']], $a->fresh()->guest_data);
        $this->assertNull($b->fresh()->guest_data);
    }

    public function test_progress_indicator_counts_completed_children(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack(), qty: 3));
        $reservation->forceFill(['guest_data' => [['name' => 'Ana'], ['name' => ''], ['name' => 'Leo']]])->save();

        $this->actingAs($user)->get(route('reservation.guests', ['reservation' => $reservation]))
            ->assertOk()->assertSee('2 de 3 fichas completas'); // 2 con nombre, 1 vacía
    }

    // ─── Notificación + helpers ──────────────────────────────────────────────

    public function test_notification_is_per_reservation_and_links_to_a_temporary_signed_route(): void
    {
        // Auditoría Fase 1 (A7) + individualización: el email es POR RESERVA; el enlace es un
        // temporarySignedRoute a ESA reserva (caduca según SU franja + 14d), no un signedRoute eterno.
        $order = $this->paidOrder(User::factory()->create(), $this->pack());
        $reservation = $this->reservation($order);

        $mail = (new GuestFormRequest($reservation))->toMail($order->user);
        $data = $mail->toArray();

        $this->assertStringContainsString('Cumpleaños Jump', $data['subject']); // nombra la reserva
        $this->assertStringContainsString($order->code, $data['subject']);
        $this->assertStringContainsString('/reserva/'.$reservation->id.'/', $data['actionUrl']); // a SU reserva
        $this->assertStringContainsString('signature=', $data['actionUrl']);
        $this->assertStringContainsString('expires=', $data['actionUrl'], 'el enlace DEBE llevar caducidad (A7)');
        $this->get($data['actionUrl'])->assertOk(); // el enlace recién acuñado da acceso sin sesión
    }

    public function test_expired_signed_link_is_forbidden(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $expired = URL::temporarySignedRoute('reservation.guests', now()->subDay(), ['reservation' => $reservation]);

        $this->get($expired)->assertForbidden();
    }

    public function test_reservation_link_expiry_uses_its_own_slot_date(): void
    {
        // La caducidad del enlace es la fecha de LA FRANJA de la reserva + 14 días (no un agregado del
        // pedido): cada cumpleaños caduca según su propia fecha.
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(30)->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '19:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
        $reservation->forceFill(['slot_id' => $slot->id])->save();

        $this->assertSame(
            now()->addDays(30)->endOfDay()->addDays(14)->toDateString(),
            $reservation->fresh('slot')->guestFormLinkExpiresAt()->toDateString(),
        );
    }

    public function test_order_guest_form_helpers(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user, $this->pack())->load('items.ticketType');

        $this->assertTrue($order->hasGuestForm());
        $this->assertTrue($order->needsGuestForm());
        $this->assertCount(1, $order->guestFormItems());

        // Una entrada normal no pide el form.
        $entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->assertFalse($this->paidOrder($user, $entry)->load('items.ticketType')->hasGuestForm());
    }

    // ─── Fiesta MIXTA: lo que ve el CLIENTE donde declara las edades (`specs/cumple-mixto.md` §9·7)

    /**
     * Monta la familia «cumple» (1–6 a 18,00 € · 7–99 a 25,00 €) y devuelve una reserva del pack
     * infantil con las edades dadas, ya pagada y con franja (sin fecha no hay tarifa que resolver).
     *
     * @param  list<int>  $ages
     */
    private function mixedFamilyReservation(array $ages, string $booked = 'kids'): OrderItem
    {
        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);

        $make = function (string $name, int $min, int $max, int $cents) use ($rate): TicketType {
            $pack = TicketType::create([
                'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
                'zone_id' => $this->zone->id, 'min_qty' => 1, 'max_qty' => 20,
                'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true,
                'position' => (int) TicketType::max('position') + 1,
                'guest_fields' => [
                    ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                    ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
                ],
                'guest_age_family' => 'cumple', 'guest_age_min' => $min, 'guest_age_max' => $max,
            ]);
            Price::create([
                'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
                'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
            ]);

            return $pack;
        };

        $kids = $make('Cumpleaños Kids', 1, 6, 1800);
        $jump = $make('Cumpleaños Jump', 7, 99, 2500);
        $pack = $booked === 'kids' ? $kids : $jump;

        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(10)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);

        $item = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => count($ages), 'unit_price' => (int) $pack->prices()->value('amount_cents'), 'seats' => count($ages),
            'event_data' => ['celebrant' => 'Mara'],
        ]);
        // El sello que `OrderCreator` pone al nacer (`specs/cumple-mixto.md` §21).
        app(AgeFamilySealer::class)->seal($item, $pack, $slot->date);

        // Por la puerta real: el aviso enseña lo ESCRITO en el pedido, y quien lo escribe es el
        // guardado del post-form.
        $rows = [];
        foreach ($ages as $i => $age) {
            $rows[] = ['name' => 'Invitado '.($i + 1), 'edad' => (string) $age];
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $item->fresh();
    }

    public function test_the_form_tells_the_client_when_the_party_becomes_mixed(): void
    {
        $reservation = $this->mixedFamilyReservation([4, 8]);

        $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->assertSee(__('guestform.mixed_title'))
            // ⚠️ La ARITMÉTICA, no solo el importe: sin decir qué pack le toca y a qué precio, el
            // cliente recibe una cifra sin origen (`[owner, 2026-08-29]`, §14).
            // ⚠️ Con cargo escrito la frase sale de lo ESCRITO, no del catálogo de hoy: es la
            // diferencia que se le comunicó, por invitado.
            ->assertSee(__('guestform.mixed_line_written', [
                'count' => 1, 'target' => 'Cumpleaños Jump', 'unit' => '7,00 €',
            ]))
            ->assertSee(__('guestform.mixed_surcharge', ['amount' => '7,00 €']));
    }

    public function test_the_client_reads_the_price_that_was_communicated_to_him(): void
    {
        // ⚠️⚠️ Antes, la explicación se componía con los precios de HOY y el importe con lo ESCRITO,
        // así que una subida de tarifa las hacía contradecirse: «te corresponde Jump a 30,00 € en
        // vez de Kids a 18,00 €» encima de un suplemento de 7,00 €. El cliente que hiciera la resta
        // tendría razón, y con razón desconfiaría del importe.
        $reservation = $this->mixedFamilyReservation([4, 8]);
        TicketType::where('name->es', 'Cumpleaños Jump')->first()->prices()->update(['amount_cents' => 3000]);

        $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->assertSee(__('guestform.mixed_line_written', [
                'count' => 1, 'target' => 'Cumpleaños Jump', 'unit' => '7,00 €',
            ]))
            ->assertSee(__('guestform.mixed_surcharge', ['amount' => '7,00 €']))
            // Y ni rastro del precio de hoy, que no es el suyo.
            ->assertDontSee('30,00 €');
    }

    public function test_the_form_shows_the_cheaper_direction_as_a_written_discount(): void
    {
        // ⚠️⚠️ **El caso que el owner encontró en un pedido real** (`#246`): reservó el pack CARO y
        // dos invitados corresponden al barato. Desde el espejo (`[DECIDIDO owner]` D5, §20/§24) el
        // descuento es real, y desde la T3·3 del LIBRO (D4 de `DECISIONES #305`) se escribe ENTERO
        // también en un pedido pagado 100 % online: ya no existe el «a tu favor» — es una línea de
        // descuento con su importe y, en el libro del pedido, saldo «a devolver en el parque».
        $reservation = $this->mixedFamilyReservation([9, 4, 3], booked: 'jump');

        $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->assertSee(__('guestform.mixed_title'))
            // 2 × (25,00 − 18,00) = 14,00 €, ESCRITOS: la línea del descuento con su importe…
            ->assertSee('−14,00 €', escape: false)
            // …y las DOS salidas (se descuenta de lo que pagarás; si ya estaba todo pagado, se devuelve en el parque).
            ->assertSee(__('guestform.mixed_discount_total', ['amount' => '14,00 €']))
            // Y NUNCA la frase del cargo: aquí no se debe nada.
            ->assertDontSee(__('guestform.mixed_surcharge', ['amount' => '14,00 €']));
    }

    public function test_the_form_says_nothing_when_every_guest_is_within_the_range(): void
    {
        // Control del caso anterior: sin él, un aviso que NUNCA se pinta pasaría igual de verde.
        $reservation = $this->mixedFamilyReservation([4, 6]);

        $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->assertDontSee(__('guestform.mixed_title'));
    }

    public function test_each_child_card_shows_the_regime_that_matches_its_age(): void
    {
        // `[owner, 2026-08-29]`: «en el recuadro del niño, informativo, que ponga a qué régimen
        // pertenece». Reserva KIDS con un invitado de 8: el suyo dice Kids, el mayor dice Jump.
        $reservation = $this->mixedFamilyReservation([4, 8]);

        $html = $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('gf-fiche__regime', $html);
        // ⚠️ El del mayor va MARCADO: es el que mueve el precio de la fiesta. Sin esta aserción,
        // pintar los dos iguales pasaría igual de verde.
        $this->assertStringContainsString('gf-fiche__regime is-other', $html);
        $this->assertStringContainsString('Cumpleaños Jump', $html);
    }

    // ─── Una edad SIN PRODUCTO (`#284` D6, §22.5) ────────────────────────────────

    public function test_an_age_without_a_product_keeps_the_form_pending_and_explains_it(): void
    {
        // La ficha del bebé de 0 años no se da por completa (no cuenta en el progreso, el estado
        // sigue `pending`), y el cliente lee POR QUÉ —el caso «por debajo del tramo»— con el
        // teléfono del parque para llamar. Mutación: `isGuestFormComplete()` sin mirar las edades.
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '968 22 22 22', 'group' => 'contact']);
        $reservation = $this->mixedFamilyReservation([4, 0]);

        $this->assertSame('pending', $reservation->guestFormStatus(), 'con una edad sin producto el formulario no está completo');
        $this->assertSame(['done' => 1, 'total' => 2], $reservation->guestFormProgress());
        $this->assertNull($reservation->guest_form_completed_at);

        $html = $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->assertSee(__('guestform.no_product_title'))
            ->assertSee(__('guestform.no_product_below', ['phone' => '968 22 22 22']))
            ->assertSee(__('guestform.regime_no_product'))
            ->getContent();

        $this->assertStringContainsString('data-no-product="1"', $html, 'la ficha va marcada para que el JS no la dé por lista');
    }

    public function test_the_park_can_write_its_own_text_for_each_case(): void
    {
        // Textos por instalación (§22.4): el del parque manda sobre el respaldo, y `:phone` se
        // sustituye. Mutación: leer siempre el respaldo → rojo.
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '600 11 22 33', 'group' => 'contact']);
        Setting::updateOrCreate(
            ['key' => 'mixed_party.no_product.above.es'],
            ['value' => 'Para mayores de 12 tenemos otra fiesta: llámanos al :phone.', 'group' => 'mixed_party'],
        );
        Setting::flushMemo();
        $reservation = $this->mixedFamilyReservation([4, 120]);

        $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->assertSee('Para mayores de 12 tenemos otra fiesta: llámanos al 600 11 22 33.')
            ->assertDontSee(__('guestform.no_product_above', ['phone' => '600 11 22 33']));
    }

    public function test_blank_ages_freeze_the_surcharge_and_the_form_says_so(): void
    {
        // El dinero solo se mueve al guardar con TODAS las edades (`#285` §20.6). Con cargo escrito y
        // una edad en blanco, el cliente tiene que leer que está congelado — antes el congelado era
        // silencioso (lo cazó el T0).
        $reservation = $this->mixedFamilyReservation([4, 8]);
        $reservation->submitGuestForm([['name' => 'Invitado 1'], ['name' => 'Invitado 2', 'edad' => '8']], [], 'signed_link');

        $this->actingAs($reservation->order->user)
            ->get(route('reservation.guests', $reservation->fresh()))
            ->assertOk()
            ->assertSee(__('guestform.mixed_surcharge', ['amount' => '7,00 €']))
            // `trans_choice` y en singular: «Falta 1 edad», no «Faltan 1 edades» (la lección de `#247`).
            ->assertSee(trans_choice('guestform.frozen_missing_ages', 1, ['count' => 1]))
            ->assertSee('Falta 1 edad por declarar');
    }

    public function test_a_pack_without_an_age_family_shows_no_regime_at_all(): void
    {
        // Control: el pack de siempre —sin familia ni campo de edad— no gana ningún rótulo. Sin
        // esto, un `@if` mal escrito llenaría de pastillas todos los post-forms del producto.
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)
            ->get(route('reservation.guests', $reservation))
            ->assertOk()
            ->assertDontSee('gf-fiche__regime');
    }

    /**
     * **La marca del parte: el logotipo de la instalación, o el nombre en texto** (`#334`).
     *
     * ⚠️⚠️ **Con `public/` TEMPORAL**: `client-logo.svg` es del CLIENTE y está gitignorado, así que
     * aseverar contra el `public/` real da un test que **pasa en un clon limpio y falla en la máquina
     * de quien tiene el paquete instalado** (la lección de `#302`).
     *
     * ⚠️ Aquí el logotipo va como `<img>` y NO en línea, al revés que el armazón: aquello se incrusta
     * para poder ANIMAR una pieza del dibujo (`#254`), y esta página no tiene coreografía — serían
     * ~64 KB de marcado por una imagen quieta.
     */
    public function test_the_sheet_shows_the_installation_logo_and_falls_back_to_the_name(): void
    {
        $order = $this->paidOrder(User::factory()->create(), $this->pack());
        $item = $this->reservation($order);

        $dir = sys_get_temp_dir().'/jw-gf-'.getmypid().'-'.uniqid();
        mkdir($dir.'/img', 0o777, true);
        @symlink(base_path('public/build'), $dir.'/build');
        $this->app->usePublicPath($dir);

        try {
            $url = URL::temporarySignedRoute('reservation.guests', now()->addHour(), ['reservation' => $item->id]);

            // (1) SIN logotipo: el suelo es el nombre del sitio en la fuente de rótulo.
            $sinLogo = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('gf-mark__brand', $sinLogo, 'sin logotipo tiene que quedar el nombre');
            $this->assertStringNotContainsString('gf-mark__logo', $sinLogo);

            // (2) CON logotipo: manda la imagen y el texto se retira — son alternativas.
            file_put_contents($dir.'/img/client-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>');
            $conLogo = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('gf-mark__logo', $conLogo, 'el logotipo de la instalación no llega al parte');
            $this->assertStringNotContainsString('gf-mark__brand', $conLogo, 'se pintan el logotipo Y el texto: son alternativas');
        } finally {
            @unlink($dir.'/img/client-logo.svg');
            @unlink($dir.'/build');
            @rmdir($dir.'/img');
            @rmdir($dir);
        }
    }
    // ── La escalada 403 → 410 → 404 en la WEB (T0 de `#413`) ───────────────────────────────────

    /**
     * ❗❗ **Hasta el 2026-09-03 esta propiedad solo se cumplía en la API.** El `AuthorizesGuestForm`
     * documenta que el ORDEN de la escalada es una propiedad de seguridad —autorizar ANTES de mirar
     * si el recurso existe— y el controlador de la API resuelve la reserva a mano justamente para
     * eso. La ruta web usaba *route model binding* implícito, así que Laravel respondía **404 antes
     * de que corriera nada**: medido con `curl`, un id existente daba 403 y uno inventado 404, y esa
     * diferencia le cuenta a cualquier desconocido qué reservas hay.
     *
     * El arreglo es `->missing(fn () => abort(403))` en las rutas; este caso es su red.
     */
    public function test_an_unknown_reservation_answers_403_exactly_like_an_existing_one(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $existing = $this->get(route('reservation.guests', ['reservation' => $reservation]));
        $unknown = $this->get(route('reservation.guests', ['reservation' => 999999]));

        $existing->assertForbidden();
        $unknown->assertForbidden();
        // Y el POST igual: un id inventado no puede distinguirse de uno real sin firma.
        $this->post(route('reservation.guests.store', ['reservation' => 999999]), [])->assertForbidden();
    }

    // ── La AUSENCIA de una clave: «no la toques», nunca «vacíala» (T0 de `#413`) ────────────────

    /**
     * ❗❗ El mismo defecto que la API, por la puerta de la web: `input('guests', [])` convertía un
     * cuerpo parcial en **N fichas vacías**. Aquí el formulario siempre manda las fichas, así que la
     * forma de llegar es un POST forjado o una pestaña vieja — y la respuesta correcta a un cuerpo
     * incompleto nunca puede ser destruir datos de menores.
     */
    public function test_a_post_without_guests_does_not_wipe_the_children_data(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Ana', 'allergy' => 'frutos secos'], ['name' => 'Luis']],
        ])->assertRedirect();

        // Un POST que solo trae los generales: no puede llevarse por delante lo demás.
        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'general' => ['adults' => '7'],
        ])->assertRedirect();

        $reservation->refresh();
        $this->assertSame('Ana', $reservation->guestData()[0]['name'] ?? null);
        $this->assertSame('frutos secos', $reservation->guestData()[0]['allergy'] ?? null);
        $this->assertSame('7', $reservation->event_data['adults'] ?? null);
    }

    /**
     * ⚠️ **Un cuerpo MALFORMADO se conserva, no se vacía.** La web no valida el tipo —el formulario
     * siempre manda listas—, así que un POST forjado puede traer `guests` como cadena. Ante eso, la
     * única respuesta segura es conservar: destruir los datos de ocho menores por un tipo equivocado
     * no puede ser la conducta por defecto. (En la API esta rama no se alcanza: `['sometimes','array']`
     * responde 422 antes de llegar al dominio — dos superficies, dos defensas, ninguna que borre.)
     *
     * Lo escribió una mutación que NO mordía: la regla estaba en el código y sin red.
     */
    public function test_a_malformed_body_preserves_the_data_instead_of_wiping_it(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Ana'], ['name' => 'Luis']],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => 'no soy una lista',
            'general' => 'yo tampoco',
        ])->assertRedirect();

        $reservation->refresh();
        $this->assertSame('Ana', $reservation->guestData()[0]['name'] ?? null);
        $this->assertSame('Luis', $reservation->guestData()[1]['name'] ?? null);
        $this->assertSame('Mara', $reservation->event_data['celebrant'] ?? null);
    }

    // ── Rotar el enlace (D14 de `#413`) ────────────────────────────────────────────────────────

    /**
     * El enlace del post-form es una **credencial portadora**: abre sin sesión y se reenvía. `RGPD-06`
     * no la alcanza —es HMAC, no hay fila que revocar—, así que la palanca es la VERSIÓN que viaja
     * dentro de la firma. Rotar la sube y el enlace anterior deja de abrir en el acto.
     */
    public function test_rotating_the_link_closes_the_previous_web_link(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));
        $old = $reservation->guestFormSignedUrl();

        $this->get($old)->assertOk();

        $reservation->rotateGuestFormLink();

        // Credencial retirada: 403, el mismo peldaño que «no traes firma» — nunca un 404, que diría
        // que la reserva existió.
        $this->get($old)->assertForbidden();
        $this->post($reservation->guestFormSignedStoreUrl().'&forged=1', [])->assertForbidden();
        $this->get($reservation->refresh()->guestFormSignedUrl())->assertOk();
    }

    /**
     * ⚠️ El formulario que se pinta al abrir un enlace firmado tiene que POSTear a una URL firmada
     * **con la misma versión**: si la compusiera a mano sin ella, el cliente abriría la página y no
     * podría guardar. Por eso las cuatro URLs firmadas del post-form salen del dominio.
     */
    public function test_the_form_of_a_signed_visit_posts_to_a_url_that_carries_the_version(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));
        $reservation->rotateGuestFormLink();
        $reservation->refresh();

        $html = $this->get($reservation->guestFormSignedUrl())->assertOk()->getContent();

        $this->assertStringContainsString('v=1', $html);
        // Y el POST a esa URL de verdad guarda (no basta con que el parámetro esté escrito).
        $this->post($reservation->guestFormSignedStoreUrl(), [
            'guests' => [['name' => 'Ana'], ['name' => 'Luis']],
        ])->assertRedirect();
        $this->assertSame('Ana', $reservation->refresh()->guestData()[0]['name'] ?? null);
    }
}
