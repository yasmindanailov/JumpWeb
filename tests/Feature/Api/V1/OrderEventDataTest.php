<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 4 · paso 4.0b·4b — `GET /api/v1/orders/{code}/event-data`.
 *
 * El endpoint existe por una decisión de MINIMIZACIÓN, no por comodidad: las respuestas del pack son
 * datos de un menor —nombre, edad y alergias, art. 9— y como campo de `OrderItem` viajarían en cada
 * página de `me/orders`. Por eso la guarda que de verdad sostiene el diseño no es la de que este
 * endpoint devuelva algo, sino la de que **los otros dos no lo devuelvan nunca**
 * ({@see test_the_answers_never_travel_in_the_order_endpoints}): si alguien los añadiera a
 * `OrderItemResource` «para ahorrar una petición», este fichero se pone en rojo y la razón de ser
 * del endpoint queda dicha en el mensaje del fallo.
 *
 * El resto de guardas cubren lo que la decisión implica: solo la fase `booking`, solo el titular,
 * `no-store`, y que la composición etiqueta/valor sale del dominio (misma que pinta la web).
 */
class OrderEventDataTest extends ApiTestCase
{
    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump', 'en' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function path(string $code): string
    {
        return self::ROOT.'/orders/'.$code.'/event-data';
    }

    /**
     * Un pack con las tres clases de campo que importan: uno de RESERVA con etiqueta traducible,
     * otro de reserva no obligatorio, y uno de POST-FORM — que es el que no debe salir.
     */
    private function pack(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking',
                    'label' => ['es' => 'Homenajeado', 'en' => 'Birthday child']],
                ['key' => 'age', 'type' => 'number', 'required' => false, 'stage' => 'booking',
                    'label' => ['es' => 'Edad']],
                ['key' => 'allergies', 'type' => 'textarea', 'required' => false, 'stage' => 'postform',
                    'label' => ['es' => 'Notas (alergias…)']],
            ],
        ]);
    }

    private function ticket(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'min_qty' => 1, 'max_qty' => 10,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    private function slot(): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
    }

    private function order(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $eventData */
    private function line(Order $order, TicketType $type, array $eventData = [], ?Slot $slot = null): OrderItem
    {
        return $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot?->id, 'quantity' => 2,
            'unit_price' => 500, 'seats' => 2, 'event_data' => $eventData,
        ]);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    // ── La razón de ser del endpoint ──────────────────────────────────────────────────────────

    /**
     * **La guarda que hace falsable la decisión de diseño** (`sidebar-spa.md` §4.4.1, hueco 4).
     *
     * Separar las respuestas en su propio endpoint solo significa algo si el pedido no las lleva.
     * Añadirlas a `OrderItemResource` sería una línea, ahorraría una petición y pondría datos de
     * salud de un menor en cada página del historial: por eso la prohibición se comprueba en el
     * CUERPO entero de las dos respuestas, no campo a campo — un nombre nuevo para el mismo dato
     * seguiría cayendo aquí.
     */
    public function test_the_answers_never_travel_in_the_order_endpoints(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $this->line($order, $this->pack(), ['celebrant' => 'Mara', 'age' => '7'], $this->slot());

        foreach ([self::ROOT.'/me/orders', self::ROOT.'/orders/'.$order->code] as $url) {
            $response = $this->actingAs($user)->getJson($url);

            $response->assertOk()->assertValidResponse(200);

            $this->assertStringNotContainsString(
                'Mara', $response->getContent() ?: '',
                "«{$url}» está devolviendo las respuestas del pack. Son datos de un menor (art. 9) y ".
                'por eso viven en `orders/{code}/event-data`: ahí se piden a propósito, aquí viajarían '.
                'en cada página del historial.'
            );
        }
    }

    // ── Lo que sí devuelve ────────────────────────────────────────────────────────────────────

    public function test_it_returns_the_booking_answers_paired_with_their_labels(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $reservation = $this->line($order, $this->pack(), ['celebrant' => 'Mara', 'age' => '7'], $this->slot());

        $this->actingAs($user)->getJson($this->path($order->code))
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('order_code', $order->code)
            ->assertJsonPath('reservations.0.reservation_id', $reservation->id)
            ->assertJsonPath('reservations.0.answers.0.key', 'celebrant')
            ->assertJsonPath('reservations.0.answers.0.label', 'Homenajeado')
            ->assertJsonPath('reservations.0.answers.0.value', 'Mara')
            ->assertJsonPath('reservations.0.answers.1.key', 'age')
            ->assertJsonPath('reservations.0.answers.1.value', '7');
    }

    /**
     * El orden lo pone el ESQUEMA, no el orden en que el cliente contestó. Si saliera en el orden
     * del JSON persistido, dos pedidos del mismo pack se pintarían distinto.
     */
    public function test_the_answers_come_in_the_order_of_the_schema(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        // Persistidas al revés que el esquema.
        $this->line($order, $this->pack(), ['age' => '7', 'celebrant' => 'Mara'], $this->slot());

        $this->actingAs($user)->getJson($this->path($order->code))
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('reservations.0.answers.0.key', 'celebrant')
            ->assertJsonPath('reservations.0.answers.1.key', 'age');
    }

    /** Las etiquetas viven en BD, así que se resuelven al idioma negociado, no a `lang/`. */
    public function test_the_labels_come_in_the_negotiated_locale(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $this->line($order, $this->pack(), ['celebrant' => 'Mara'], $this->slot());

        $this->actingAs($user)
            ->getJson($this->path($order->code), ['Accept-Language' => 'en'])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('reservations.0.answers.0.label', 'Birthday child');
    }

    /**
     * Una reserva sin respuestas SALE, con la lista vacía: es la diferencia entre «este pack no
     * pedía nada» y «esta línea no venía en la respuesta», y el cliente necesita distinguirlas para
     * saber si la petición cubrió su pedido entero.
     */
    public function test_a_reservation_without_answers_is_listed_with_an_empty_list(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $reservation = $this->line($order, $this->ticket(), [], $this->slot());

        $this->actingAs($user)->getJson($this->path($order->code))
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('reservations.0.reservation_id', $reservation->id)
            ->assertJsonPath('reservations.0.answers', []);
    }

    // ── La fase: solo `booking` ───────────────────────────────────────────────────────────────

    /**
     * **La decisión de la fase, hecha falsable.** `event_data` guarda juntas las respuestas de las
     * dos fases; las del post-form ya tienen endpoint propio —y se abre con FIRMA—, así que
     * publicarlas también aquí sería un segundo camino hacia el mismo dato del art. 9.
     */
    public function test_the_postform_answers_do_not_travel_here(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $this->line($order, $this->pack(), [
            'celebrant' => 'Mara',
            'allergies' => 'frutos secos',
        ], $this->slot());

        $response = $this->actingAs($user)->getJson($this->path($order->code));

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonCount(1, 'reservations.0.answers')
            ->assertJsonPath('reservations.0.answers.0.key', 'celebrant');

        $this->assertStringNotContainsString(
            'frutos secos', $response->getContent() ?: '',
            'las respuestas del post-form tienen su propio endpoint (`reservations/{id}/guest-form`), '.
            'que además se abre con firma: duplicarlas aquí amplía la superficie de un dato del art. 9.'
        );
    }

    /**
     * Si el pack se editó tras la compra, lo contestado a un campo retirado no sale: sin campo no
     * hay etiqueta ni `stage` que emparejar, y una respuesta sin fase no se puede acotar a
     * `booking`. La hoja de sala del panel sí las enseña —su lector es el operador—; la divergencia
     * está anotada en `DEUDA.md`.
     */
    public function test_an_answer_whose_field_no_longer_exists_is_left_out(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $this->line($order, $this->pack(), [
            'celebrant' => 'Mara',
            'retired_field' => 'lo que se contestó antes',
        ], $this->slot());

        $response = $this->actingAs($user)->getJson($this->path($order->code));

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonCount(1, 'reservations.0.answers');

        $this->assertStringNotContainsString('lo que se contestó antes', $response->getContent() ?: '');
    }

    // ── Alcance de la respuesta ───────────────────────────────────────────────────────────────

    /** Un complemento nace con `event_data` a `null`: traerlo sería recorrer filas que no aportan. */
    public function test_addons_are_not_listed_as_reservations(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $slot = $this->slot();
        $reservation = $this->line($order, $this->pack(), ['celebrant' => 'Mara'], $slot);

        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'zone_id' => $this->zone->id, 'duration_min' => 0, 'min_qty' => 1, 'max_qty' => 10,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 0, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);
        $order->items()->create([
            'ticket_type_id' => $addon->id, 'parent_item_id' => $reservation->id,
            'quantity' => 2, 'unit_price' => 300, 'seats' => 0, 'event_data' => null,
        ]);

        $this->actingAs($user)->getJson($this->path($order->code))
            ->assertOk()->assertValidResponse(200)
            ->assertJsonCount(1, 'reservations')
            ->assertJsonPath('reservations.0.reservation_id', $reservation->id);
    }

    /**
     * Una línea CANCELADA sigue saliendo. El pedido la publica igual (con su `cancelled`), y
     * ocultarla aquí dejaría al cliente sin poder emparejar por `reservation_id` una línea que sí
     * tiene delante — que es justo el vínculo entre los dos endpoints.
     */
    public function test_a_cancelled_reservation_is_still_listed(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $reservation = $this->line($order, $this->pack(), ['celebrant' => 'Mara'], $this->slot());
        $reservation->markCancelled($user);

        $this->actingAs($user)->getJson($this->path($order->code))
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('reservations.0.reservation_id', $reservation->id)
            ->assertJsonPath('reservations.0.answers.0.value', 'Mara');
    }

    // ── Quién puede pedirlo ───────────────────────────────────────────────────────────────────

    /**
     * Un código ajeno responde 404, no 403. Aquí el matiz pesa más que en el resto de la superficie:
     * un 403 le confirmaría a un desconocido que ese pedido existe, y por lo que pregunta es por los
     * datos de un menor.
     */
    public function test_the_order_of_another_user_is_a_404_and_not_a_403(): void
    {
        $owner = $this->verifiedUser();
        $order = $this->order($owner);
        $this->line($order, $this->pack(), ['celebrant' => 'Mara'], $this->slot());

        $this->actingAs($this->verifiedUser())->getJson($this->path($order->code))
            ->assertNotFound()
            ->assertValidResponse(404);
    }

    public function test_an_unknown_code_is_a_404(): void
    {
        $this->actingAs($this->verifiedUser())->getJson($this->path('R-NOEXISTE'))
            ->assertNotFound()
            ->assertValidResponse(404);
    }

    public function test_it_requires_authentication(): void
    {
        $order = $this->order($this->verifiedUser());

        $this->getJson($this->path($order->code))
            ->assertUnauthorized()
            ->assertValidResponse(401);
    }

    /**
     * `RGPD-01`: la supresión del titular vacía `event_data` de TODAS sus líneas, así que este
     * endpoint —que es hoy la única vía por la que esos datos salen del servidor hacia el cliente—
     * queda sin nada que devolver. Se comprueba aquí y no solo en `PrivacyTest` porque el invariante
     * dice «cualquier PII nueva que se persista debe añadirse a `anonymize()`»: una vía de LECTURA
     * nueva merece la comprobación simétrica, que es que la purga la alcance.
     */
    public function test_after_the_owner_is_anonymised_there_is_nothing_left_to_return(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $reservation = $this->line($order, $this->pack(), ['celebrant' => 'Mara', 'age' => '7'], $this->slot());

        $user->anonymize();

        $response = $this->actingAs($user->fresh())->getJson($this->path($order->code));

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('reservations.0.reservation_id', $reservation->id)
            ->assertJsonPath('reservations.0.answers', []);

        $this->assertStringNotContainsString('Mara', $response->getContent() ?: '');
    }

    /**
     * `RGPD-04`: los bytes no pueden quedarse en la caché en disco del navegador de un dispositivo
     * compartido. Lo pone `NoStoreWhenAuthenticated` por defecto, y se comprueba porque el defecto
     * es justo lo que nadie vuelve a mirar.
     */
    public function test_the_response_is_never_stored(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user);
        $this->line($order, $this->pack(), ['celebrant' => 'Mara'], $this->slot());

        $response = $this->actingAs($user)->getJson($this->path($order->code));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
