<?php

namespace Tests\Feature\Api\V1;

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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 5 — `GET` y `PUT /api/v1/reservations/{id}/guest-form`.
 *
 * El post-form es el SEGUNDO consumidor de la API y se eligió porque obliga a resolver lo que
 * ningún otro endpoint plantea: **cómo autentica la API a un portador de firma** (spec §3c). De ahí
 * que estos tests giren alrededor de dos cosas:
 *
 *  1. **El canje.** La firma cubre la URL exacta, así que la del correo —de una ruta web— no
 *     autoriza un `PUT /api/v1/...`. La respuesta del `GET` trae `save_url`, la URL de guardar ya
 *     firmada y con la misma caducidad. Hay test de que la firma ajena NO sirve.
 *  2. **La escalada 403 → 410 → 404**, que es deliberada (spec §6.6) para no filtrar por el código
 *     de estado si una reserva existe, si está pagada o si su titular ejerció la supresión.
 */
class GuestFormTest extends ApiTestCase
{
    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function path(int|OrderItem $reservation): string
    {
        $id = $reservation instanceof OrderItem ? (int) $reservation->id : $reservation;

        return self::ROOT.'/reservations/'.$id.'/guest-form';
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

    private function paidOrder(User $user, TicketType $type, int $qty = 2, ?Slot $slot = null): Order
    {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot?->id, 'quantity' => $qty,
            'unit_price' => 1000, 'seats' => $qty, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        return $order;
    }

    private function reservation(Order $order): OrderItem
    {
        return $order->items()->whereNull('parent_item_id')->firstOrFail();
    }

    /** Una franja que YA TERMINÓ, para el modo solo-lectura. */
    private function pastSlot(): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->subDays(2)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
    }

    // ── El canje: firmar la URL de la API ─────────────────────────────────────────────────────

    /**
     * **La pieza que el spec §4.6.5 dejó pendiente.** Sin cuenta ninguna: con el enlace firmado de
     * la API se lee el formulario, y su respuesta trae la URL de guardar ya firmada. El cliente no
     * necesita nada más.
     */
    public function test_a_signed_link_opens_the_form_and_hands_over_the_url_to_save(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $response = $this->getJson($reservation->guestFormApiUrls()['show']);

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('reservation_id', $reservation->id)
            ->assertJsonPath('product_name', 'Cumpleaños Jump')
            ->assertJsonPath('guest_count', 2)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('progress.done', 0)
            ->assertJsonPath('progress.total', 2)
            ->assertJsonPath('readonly', false)
            // El esquema viaja con el formulario: las columnas las decide cada instalación.
            ->assertJsonPath('guest_fields.0.key', 'name')
            ->assertJsonPath('guest_fields.0.label', 'Nombre')
            ->assertJsonPath('guest_fields.0.required', true)
            ->assertJsonPath('general_fields.0.key', 'adults');

        // Y ahora se guarda con la URL que acaba de entregar, sin sesión.
        $this->putJson($response->json('save_url'), [
            'guests' => [['name' => 'Ana'], ['name' => 'Luis', 'allergy' => 'frutos secos']],
            'general' => ['adults' => '4'],
        ])->assertOk()->assertValidRequest()->assertValidResponse(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('progress.done', 2);
    }

    /**
     * Una fiesta de una familia por edad (Kids 1–6 · Jump 7–99), pagada, con franja y SELLADA como
     * la deja `OrderCreator` (`specs/cumple-mixto.md` §21): es lo que hace que una edad pueda «no
     * tener producto» en las condiciones de la reserva.
     */
    private function familyReservation(User $user): OrderItem
    {
        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $make = function (string $name, int $min, int $max, int $cents) use ($rate): TicketType {
            $pack = TicketType::create([
                'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
                'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 1, 'max_qty' => 20,
                'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
                'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true,
                'position' => (int) TicketType::max('position') + 1,
                'guest_fields' => [
                    ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
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
        $make('Cumpleaños Jump', 7, 99, 2500);

        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(10)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $kids->id, 'slot_id' => $slot->id, 'quantity' => 2,
            'unit_price' => 1800, 'seats' => 2,
        ]);
        app(AgeFamilySealer::class)->seal($item, $kids, $slot->date);

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    public function test_an_age_without_a_product_keeps_the_status_pending_under_the_same_contract(): void
    {
        // `[DECIDIDO owner]` D6 (`specs/cumple-mixto.md` §22.5): la API no cambia de FORMA —`status`
        // sigue siendo `ok | pending | null`—; lo que cambia es que una ficha con todas sus columnas
        // pero con una edad sin producto NO cuenta como hecha. La app no recibe la explicación
        // (`[owner]`: la API queda fuera), y eso está anotado como deuda.
        $user = User::factory()->create();
        $reservation = $this->familyReservation($user);

        $this->actingAs($user)
            ->putJson($this->path($reservation), [
                'guests' => [['name' => 'Ana', 'edad' => '4'], ['name' => 'Bebé', 'edad' => '0']],
                'general' => ['adults' => '4'],
            ])
            ->assertOk()->assertValidRequest()->assertValidResponse(200)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('progress.done', 1)
            ->assertJsonPath('progress.total', 2);

        // El CONTROL: con las dos edades dentro de la familia, el mismo formulario queda `ok`.
        $this->actingAs($user)
            ->putJson($this->path($reservation), [
                'guests' => [['name' => 'Ana', 'edad' => '4'], ['name' => 'Bebé', 'edad' => '3']],
                'general' => ['adults' => '4'],
            ])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('progress.done', 2);
    }

    /**
     * **El motivo por el que hizo falta un canje**: la firma cubre la URL EXACTA. La del enlace del
     * correo —que apunta a la página web— no autoriza la ruta de la API por mucho que se reenvíe su
     * `signature`. Si esto pasara, el canje sobraría… y la firma no significaría nada.
     */
    public function test_the_signature_of_the_web_link_does_not_authorise_the_api(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $webUrl = $reservation->guestFormSignedUrl();
        parse_str((string) parse_url($webUrl, PHP_URL_QUERY), $query);

        $this->getJson($this->path($reservation).'?'.http_build_query($query))
            ->assertForbidden()
            ->assertValidResponse(403);
    }

    /** Y una firma de OTRA reserva tampoco abre esta. */
    public function test_the_signed_link_of_another_reservation_does_not_open_this_one(): void
    {
        $type = $this->pack();
        $mine = $this->reservation($this->paidOrder(User::factory()->create(), $type));
        $theirs = $this->reservation($this->paidOrder(User::factory()->create(), $type));

        parse_str((string) parse_url($theirs->guestFormApiUrls()['show'], PHP_URL_QUERY), $query);

        $this->getJson($this->path($mine).'?'.http_build_query($query))
            ->assertForbidden()
            ->assertValidResponse(403);
    }

    public function test_an_expired_signed_link_no_longer_opens_the_form(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $url = $reservation->guestFormApiUrls()['show'];

        // El enlace caduca con la fecha del evento + 14 días de gracia (`RGPD-03`).
        $this->travelTo(now()->addDays(20));

        $this->getJson($url)->assertForbidden()->assertValidResponse(403);
    }

    // ── El titular autenticado, sin firma ─────────────────────────────────────────────────────

    public function test_the_owner_can_use_it_with_a_session_and_no_signature(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)->getJson($this->path($reservation))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('reservation_id', $reservation->id);
    }

    public function test_a_stranger_with_a_session_is_forbidden(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $this->actingAs(User::factory()->create())->getJson($this->path($reservation))
            ->assertForbidden()
            ->assertValidResponse(403);
    }

    public function test_without_signature_and_without_session_it_is_forbidden(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $this->getJson($this->path($reservation))
            ->assertForbidden()
            ->assertValidResponse(403);
    }

    // ── La escalada 403 → 410 → 404 ───────────────────────────────────────────────────────────

    /**
     * El ORDEN es la propiedad de seguridad: sin acceso, un id inexistente y uno existente tienen
     * que responder LO MISMO. Si el 404 saliera antes que el 403 —lo que pasa con el *route model
     * binding* implícito—, cualquiera podría enumerar reservas a base de comparar códigos.
     */
    public function test_an_unknown_id_answers_the_same_403_as_an_existing_one(): void
    {
        $reservation = $this->reservation($this->paidOrder(User::factory()->create(), $this->pack()));

        $existing = $this->getJson($this->path($reservation))->assertForbidden();
        $unknown = $this->getJson($this->path(999999))->assertForbidden();

        $this->assertSame($existing->json('error.code'), $unknown->json('error.code'));
    }

    /**
     * `RGPD-03` — tras la supresión del titular, el enlace NO puede seguir abriendo ni reescribiendo
     * nombres y alergias de menores. **410 y no 403**: el recurso existió y dejó de existir a
     * propósito; y va DESPUÉS de la autorización, así que solo lo ve quien tenía acceso.
     */
    public function test_an_anonymised_owner_closes_the_channel_with_410(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $url = $reservation->guestFormApiUrls();

        $user->anonymize();

        $this->getJson($url['show'])->assertStatus(410)->assertValidResponse(410);
        $this->putJson($url['save'], ['guests' => [['name' => 'Ana']]])->assertStatus(410);

        $this->assertSame([], $reservation->refresh()->guestData(), 'no se ha reescrito ningún dato');
    }

    /** Un pedido sin pagar no tiene fiesta que preparar: 404, y solo para quien ya tenía acceso. */
    public function test_a_reservation_of_an_unpaid_order_answers_404(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user, $this->pack());
        $order->forceFill(['status' => Order::STATUS_PENDING, 'paid_at' => null])->save();
        $reservation = $this->reservation($order);

        $this->getJson($reservation->guestFormApiUrls()['show'])
            ->assertNotFound()
            ->assertValidResponse(404);
    }

    public function test_a_cancelled_reservation_answers_404(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $reservation->forceFill(['cancelled_at' => now()])->save();

        $this->getJson($reservation->guestFormApiUrls()['show'])
            ->assertNotFound()
            ->assertValidResponse(404);
    }

    // ── Guardar ───────────────────────────────────────────────────────────────────────────────

    /**
     * Todo se sanea contra el ESQUEMA y contra la cantidad ACTUAL de invitados (regla 12): una clave
     * que no existe se descarta y una ficha de más no se guarda.
     */
    public function test_the_server_sanitises_against_the_schema_and_the_guest_count(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack(), qty: 2));

        $this->actingAs($user)->putJson($this->path($reservation), [
            'guests' => [
                ['name' => 'Ana', 'allergy' => 'lactosa', 'inventado' => 'x'],
                ['name' => 'Luis'],
                ['name' => 'Sobra'],
            ],
        ])->assertOk();

        $rows = $reservation->refresh()->guestData();

        $this->assertCount(2, $rows, 'no se guardan más fichas que invitados hay');
        $this->assertArrayNotHasKey('inventado', $rows[0], 'una clave fuera del esquema se descarta');
        $this->assertSame('Ana', $rows[0]['name']);
    }

    /**
     * Los datos generales se MEZCLAN: lo que se respondió al RESERVAR no se pierde al rellenar el
     * post-form, aunque el esquema haya cambiado entre una cosa y otra.
     */
    public function test_saving_preserves_the_answers_given_when_booking(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->actingAs($user)->putJson($this->path($reservation), [
            'general' => ['adults' => '6'],
        ])->assertOk()->assertJsonPath('general.adults', '6');

        $eventData = $reservation->refresh()->event_data;

        $this->assertSame('Mara', $eventData['celebrant'] ?? null, 'el dato de la reserva sigue ahí');
        $this->assertSame('6', $eventData['adults'] ?? null);
    }

    /** El rastro de auditoría existe y NO lleva PII (`RGPD-02`). */
    public function test_saving_leaves_an_audit_trail_without_personal_data(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $this->putJson($reservation->guestFormApiUrls()['save'], [
            'guests' => [['name' => 'Ana'], ['name' => 'Luis']],
        ])->assertOk();

        $log = AuditLog::where('action', 'orders.guest_form_submitted')->latest('id')->firstOrFail();

        $this->assertSame('signed_link', $log->payload['via'] ?? null);
        $this->assertSame($reservation->id, $log->payload['order_item_id'] ?? null);
        $this->assertStringNotContainsString('Ana', json_encode($log->payload) ?: '');
    }

    /**
     * Pasada la fiesta, el formulario se consulta pero no se edita. **409 y no 403**: el permiso no
     * ha cambiado, ha cambiado el momento — y el enlace sigue abriendo para poder consultar.
     */
    public function test_a_finished_reservation_is_read_only(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack(), slot: $this->pastSlot()));

        $this->actingAs($user)->getJson($this->path($reservation))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('readonly', true);

        $this->actingAs($user)->putJson($this->path($reservation), ['guests' => [['name' => 'Tarde']]])
            ->assertStatus(409)
            ->assertValidResponse(409)
            ->assertJsonPath('error.code', 'guest_form_closed');

        $this->assertSame([], $reservation->refresh()->guestData(), 'no se ha escrito nada');
    }

    // ── PII y caché ───────────────────────────────────────────────────────────────────────────

    /**
     * `RGPD-04` — la respuesta lleva nombres y alergias de menores. Como la ruta es accesible SIN
     * sesión, el `no-store` por defecto de la superficie autenticada no la cubriría: va explícito.
     */
    public function test_the_response_with_minors_data_is_never_stored(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $response = $this->getJson($reservation->guestFormApiUrls()['show'])->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /** El formulario solo devuelve los campos generales de SU fase, no los de la reserva. */
    public function test_it_does_not_echo_the_answers_of_the_booking_stage(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        $response = $this->actingAs($user)->getJson($this->path($reservation))->assertOk();

        $this->assertArrayHasKey('adults', $response->json('general'));
        $this->assertArrayNotHasKey('celebrant', $response->json('general'));
    }

    // ── La AUSENCIA de una clave: «no la toques», nunca «vacíala» (T0 de `#413`) ────────────────

    /**
     * ❗❗ **El defecto que esto cierra borraba datos de MENORES, y respondía 200.**
     *
     * El controlador hacía `$validated['guests'] ?? []`, y `[]` no significa «no me lo mandes»:
     * `TicketType::sanitizeGuestData([], N)` devuelve **N filas VACÍAS**. Medido sobre una reserva
     * real de 8 invitados antes del arreglo: un `PUT` que solo traía `general` dejaba las ocho fichas
     * a cero —nombres, edades y alergias— sin un solo error. Y el contrato de la API lo documentaba
     * como una virtud: «un formulario de invitados se guarda a trozos». *Guardar a trozos borraba el
     * otro trozo.*
     */
    public function test_a_body_without_guests_does_not_wipe_the_children_data(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $save = $this->getJson($reservation->guestFormApiUrls()['show'])->json('save_url');

        $this->putJson($save, [
            'guests' => [['name' => 'Ana', 'allergy' => 'frutos secos'], ['name' => 'Luis']],
            'general' => ['adults' => '4'],
        ])->assertOk();

        // Segundo guardado: SOLO los generales, como haría un cliente que actualiza una sola parte.
        $this->putJson($save, ['general' => ['adults' => '6']])
            ->assertOk()->assertValidRequest()->assertValidResponse(200);

        $reservation->refresh();
        $this->assertSame('Ana', $reservation->guestData()[0]['name'] ?? null);
        $this->assertSame('frutos secos', $reservation->guestData()[0]['allergy'] ?? null);
        $this->assertSame('Luis', $reservation->guestData()[1]['name'] ?? null);
        // Y lo que SÍ venía en el cuerpo se guardó.
        $this->assertSame('6', $reservation->event_data['adults'] ?? null);
    }

    /**
     * El CONTROL de la anterior, y lo que hace que «ausente» signifique algo: mandar la lista
     * explícitamente VACÍA sí vacía. Sin este caso, la regla sería «guests nunca se toca», que es
     * otra cosa.
     */
    public function test_an_explicit_empty_guests_list_does_wipe_it(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $save = $this->getJson($reservation->guestFormApiUrls()['show'])->json('save_url');

        $this->putJson($save, ['guests' => [['name' => 'Ana'], ['name' => 'Luis']]])->assertOk();
        $this->putJson($save, ['guests' => []])->assertOk();

        $reservation->refresh();
        $this->assertSame([[], []], $reservation->guestData());
    }

    /** La misma regla para los generales, que es donde se midió el defecto por primera vez. */
    public function test_a_body_without_general_does_not_wipe_the_general_answers(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $save = $this->getJson($reservation->guestFormApiUrls()['show'])->json('save_url');

        $this->putJson($save, ['guests' => [['name' => 'Ana'], ['name' => 'Luis']], 'general' => ['adults' => '9']])->assertOk();
        $this->putJson($save, ['guests' => [['name' => 'Ana'], ['name' => 'Luis']]])->assertOk();

        $reservation->refresh();
        $this->assertSame('9', $reservation->event_data['adults'] ?? null);
        // Y lo de la fase de RESERVA sigue intacto, como siempre.
        $this->assertSame('Mara', $reservation->event_data['celebrant'] ?? null);
    }

    // ── El sello deja de mover el token optimista del operador (T0 de `#413`) ──────────────────

    /**
     * `guest_form_completed_at` se re-estampaba con `now()` en CADA guardado completo, y `updated_at`
     * del ítem es el token optimista de **cinco** puertas del operador: un cliente repasando su
     * formulario le invalidaba los modales abiertos con `stale_item_version` sin haber cambiado nada.
     *
     * ⚠️ **El `travel()` no es adorno**: sin él los dos guardados caen en el mismo segundo y el test
     * pasaría con el defecto puesto — la trampa del valor trivial de `CONVENCIONES §3.quater`.
     */
    public function test_an_identical_save_does_not_move_the_optimistic_token(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $save = $this->getJson($reservation->guestFormApiUrls()['show'])->json('save_url');
        $body = ['guests' => [['name' => 'Ana'], ['name' => 'Luis']], 'general' => ['adults' => '4']];

        $this->putJson($save, $body)->assertOk();
        $token = $reservation->refresh()->updated_at;

        $this->travel(120)->seconds();
        $this->putJson($save, $body)->assertOk();

        $this->assertTrue($token->equalTo($reservation->refresh()->updated_at));
    }

    /** El CONTROL: un guardado que SÍ cambia algo mueve el token, que es lo que el operador espera. */
    public function test_a_save_that_changes_something_does_move_the_token(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $save = $this->getJson($reservation->guestFormApiUrls()['show'])->json('save_url');

        $this->putJson($save, ['guests' => [['name' => 'Ana'], ['name' => 'Luis']]])->assertOk();
        $token = $reservation->refresh()->updated_at;

        $this->travel(120)->seconds();
        $this->putJson($save, ['guests' => [['name' => 'Ana'], ['name' => 'Marta']]])->assertOk();

        $this->assertTrue($reservation->refresh()->updated_at->greaterThan($token));
    }

    // ── Rotar el enlace: la palanca que a esta credencial le faltaba (D14 de `#413`) ────────────

    /**
     * El enlace del post-form abre sin sesión y se reenvía: es una credencial portadora. `RGPD-06`
     * dice que invalidar el acceso de un titular tiene un solo sitio y alcanza a TRES credenciales —
     * ésta no está ni puede estar, porque es HMAC y no hay fila que borrar. Rotar sube la versión que
     * viaja DENTRO de la firma, y con eso el enlace anterior deja de abrir en el acto.
     */
    public function test_rotating_the_link_closes_the_previous_one_in_both_surfaces(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));
        $old = $reservation->guestFormApiUrls();

        $this->getJson($old['show'])->assertOk();

        $reservation->rotateGuestFormLink();

        // El viejo es una credencial retirada: 403, el mismo peldaño que «no traes firma».
        $this->getJson($old['show'])->assertForbidden();
        $this->putJson($old['save'], ['guests' => [['name' => 'Ana']]])->assertForbidden();

        // Y el nuevo abre.
        $this->getJson($reservation->refresh()->guestFormApiUrls()['show'])->assertOk();
    }

    /**
     * ⚠️ **Ningún enlace ya emitido se rompe al desplegar**: los que viajan SIN el parámetro de
     * versión se leen como versión 0, que es el default de la columna. La versión solo separa cuando
     * alguien rota — sin este caso, el arreglo habría invalidado de golpe todos los enlaces vivos.
     */
    public function test_a_link_issued_before_the_version_existed_still_opens(): void
    {
        $user = User::factory()->create();
        $reservation = $this->reservation($this->paidOrder($user, $this->pack()));

        // Un enlace firmado SIN `v`, exactamente como los emitidos antes de D14.
        $legacy = URL::temporarySignedRoute(
            'api.v1.reservations.guest-form.show',
            $reservation->guestFormLinkExpiresAt(),
            ['reservation' => $reservation->id],
        );

        $this->getJson($legacy)->assertOk();

        // Y deja de abrir en cuanto el operador rota, que es lo que se espera de él.
        $reservation->rotateGuestFormLink();
        $this->getJson($legacy)->assertForbidden();
    }
}
