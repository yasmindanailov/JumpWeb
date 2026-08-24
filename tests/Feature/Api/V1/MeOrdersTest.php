<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 1 — `GET /api/v1/me/orders`.
 *
 * Lo que estas guardas protegen, más allá de «devuelve pedidos»:
 *  - **titularidad** (anti-IDOR): el scoping sale del guard y de ningún parámetro;
 *  - **todos los estados**, que es el hallazgo 4 de la revisión del spec: un `pending` a medio
 *    pagar es justo lo que un cliente que perdió el estado necesita recuperar;
 *  - **el estado EFECTIVO**, no la columna: un hold vencido es `expired` aunque el barrido aún no
 *    haya pasado;
 *  - y que la respuesta encaja con `openapi/v1.yaml`, campo a campo.
 */
class MeOrdersTest extends ApiTestCase
{
    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
            'currency' => 'EUR', 'paid_at' => now(),
        ], $attributes));
    }

    public function test_it_lists_the_orders_of_the_authenticated_user(): void
    {
        $user = $this->verifiedUser();
        $this->makeOrder($user, ['code' => 'R-AAA111']);

        $this->actingAs($user)->getJson(self::ROOT.'/me/orders')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.code', 'R-AAA111')
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('data.0.currency', 'EUR')
            ->assertJsonPath('data.0.ledger.invoiced_cents', 1000)
            ->assertJsonPath('meta.total', 1);
    }

    /** Anti-IDOR estructural: no hay parámetro de titular que manipular, y aun así se comprueba. */
    public function test_it_never_shows_orders_of_another_user(): void
    {
        $ada = $this->verifiedUser();
        $grace = $this->verifiedUser();
        $this->makeOrder($grace, ['code' => 'R-GRACE1']);

        $response = $this->actingAs($ada)->getJson(self::ROOT.'/me/orders');

        $response->assertOk()->assertJsonPath('meta.total', 0);
        $this->assertStringNotContainsString('R-GRACE1', (string) $response->getContent());
    }

    /**
     * El hallazgo 4 del spec §8: filtrar por «pagados» habría escondido justo lo que el cliente
     * necesita rescatar mientras su retención de aforo sigue viva.
     */
    public function test_it_includes_pending_orders_with_what_would_be_charged(): void
    {
        $user = $this->verifiedUser();
        $order = $this->makeOrder($user, [
            'code' => 'R-PEND01',
            'status' => Order::STATUS_PENDING,
            'paid_at' => null,
            'expires_at' => now()->addMinutes(20),
        ]);
        // Con línea, no un pedido vacío: `onlineDueCents()` se DERIVA de los items, así que un
        // pedido sin líneas informaría 0 y el test pasaría sin probar nada.
        $order->items()->create([
            'ticket_type_id' => $this->product()->id,
            'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/orders');

        $response->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.online_amount_cents', 1000)
            ->assertJsonPath('data.0.paid_at', null);

        $this->assertNotNull(
            $response->json('data.0.expires_at'),
            'sin `expires_at` el cliente no sabe cuánto le queda para rescatar el pedido'
        );
    }

    /** Producto mínimo vendible; los tests que necesitan importes reales cuelgan sus líneas de él. */
    private function product(): TicketType
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], [
            'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        return TicketType::create([
            'name' => ['es' => 'Salto 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);
    }

    /**
     * Estado EFECTIVO, no la columna: el barrido `orders:expire` corre cada 5 minutos, así que
     * entre medias hay pedidos `pending` en base de datos que ya no se pueden pagar. Enseñarlos
     * como pendientes mandaría al cliente a un pago imposible.
     */
    public function test_an_order_whose_hold_already_expired_is_reported_as_expired(): void
    {
        $user = $this->verifiedUser();
        $this->makeOrder($user, [
            'status' => Order::STATUS_PENDING,
            'paid_at' => null,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->getJson(self::ROOT.'/me/orders')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'expired');
    }

    /** Las líneas y sus complementos, con los datos que el dominio ya calcula para «Mis pedidos». */
    /**
     * ⚠️⚠️ **Los principales y complementos FANTASMA no se publican, y esto era un hueco real**
     * (`DECISIONES #120(u)`).
     *
     * `Order::isVoidedLeftoverItem()` —un item cancelado que nunca se cobró ni se reembolsó,
     * típicamente uno añadido en gestión por `extra_due` y sustituido después— lo aplicaban la
     * página «Mis reservas», el panel y el PDF de reserva: **las tres superficies menos la API**. El
     * cajón enseñaba líneas net-cero («0,00 € · Cancelada») que el producto decidió ocultar por
     * confusas, y el único guardián del predicado conducía la página que se retira.
     *
     * ▶ Se descubrió auditando qué tests morían con la página (`CONVENCIONES §3.quater`): *si un dato
     * viaja al cliente y su único test conduce la superficie vieja, el contrato NO lo está fijando*.
     */
    public function test_it_never_publishes_the_phantom_leftover_lines(): void
    {
        $user = $this->verifiedUser();
        $order = $this->makeOrder($user, ['code' => 'R-GHOST1']);
        $product = $this->product();

        $real = $order->items()->create([
            'ticket_type_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);

        $ghostType = TicketType::create([
            'name' => ['es' => 'Producto fantasma'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $product->zone_id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);

        // Un principal fantasma y un complemento fantasma: la página oculta los DOS.
        $order->items()->create([
            'ticket_type_id' => $ghostType->id, 'quantity' => 1, 'unit_price' => 0, 'seats' => 1,
            'cancelled_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $ghostType->id, 'parent_item_id' => $real->id,
            'quantity' => 1, 'unit_price' => 0, 'seats' => 0, 'cancelled_at' => now(),
        ]);

        // La guarda de la guarda: si el fixture dejara de producir fantasmas, lo de abajo pasaría
        // sin medir nada.
        $fresh = $order->fresh()->load('items.ticketType', 'items.children', 'adjustments', 'payments.refunds');
        $this->assertSame(
            2, $fresh->items->filter(fn ($i) => $fresh->isVoidedLeftoverItem($i))->count(),
            'el fixture ya no monta líneas fantasma: este caso no mediría nada'
        );

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk();

        $this->assertCount(1, $response->json('data.0.items'), 'la API publica un principal fantasma');
        $this->assertSame([], $response->json('data.0.items.0.addons'), 'la API publica un complemento fantasma');
        $this->assertStringNotContainsString('Producto fantasma', (string) $response->getContent());
    }

    public function test_it_serialises_lines_with_their_slot_and_addons(): void
    {
        $user = $this->verifiedUser();
        $zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-06-10',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
        $product = TicketType::create([
            'name' => ['es' => 'Salto 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'zone_id' => $zone->id, 'seats_per_unit' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);

        $order = $this->makeOrder($user);
        $line = $order->items()->create([
            'ticket_type_id' => $product->id, 'slot_id' => $slot->id,
            'quantity' => 2, 'unit_price' => 1000, 'seats' => 2,
        ]);
        $order->items()->create([
            'ticket_type_id' => $addon->id, 'parent_item_id' => $line->id,
            'quantity' => 2, 'unit_price' => 200, 'seats' => 0,
        ]);

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/orders');

        $response->assertOk()
            ->assertValidResponse(200)
            // Una sola línea principal: el complemento va anidado, no suelto en `items`.
            ->assertJsonCount(1, 'data.0.items')
            ->assertJsonPath('data.0.items.0.product_name', 'Salto 1 hora')
            ->assertJsonPath('data.0.items.0.date', '2026-06-10')
            // ⚠️ La fecha viaja CRUDA y ETIQUETADA. La etiqueta se compara contra su fuente única, no
            // contra un literal: si `DisplayTime::dayLabel()` cambia, cambian a la vez la web, los
            // correos, el post-form y esto — y este caso lo dirá (`DayLabelSingleSourceTest`).
            ->assertJsonPath('data.0.items.0.date_label', DisplayTime::dayLabel('2026-06-10'))
            ->assertJsonPath('data.0.items.0.time_window', '10:00–11:00')
            ->assertJsonPath('data.0.items.0.quantity', 2)
            ->assertJsonPath('data.0.items.0.status', 'finished')
            ->assertJsonPath('data.0.items.0.cancelled', false)
            // Una entrada no pide datos por invitado: `null` ≠ «pendiente».
            ->assertJsonPath('data.0.items.0.guest_form_status', null)
            ->assertJsonCount(1, 'data.0.items.0.addons')
            ->assertJsonPath('data.0.items.0.addons.0.product_name', 'Calcetines')
            ->assertJsonPath('data.0.items.0.addons.0.quantity', 2)
            // Una ENTRADA no admite post-form, así que no se le ofrece dónde rellenarlo.
            ->assertJsonPath('data.0.items.0.guest_form_url', null);

        // Y la etiqueta NO es la fecha cruda ni va vacía: sin esto, publicar `''` pasaría el contrato.
        $this->assertNotSame('', $response->json('data.0.items.0.date_label'));
        $this->assertNotSame('2026-06-10', $response->json('data.0.items.0.date_label'));
    }

    /**
     * **Las etiquetas de presentación del pedido, con VALOR y no solo con forma** (2026-08-22).
     *
     * ⚠️ El contrato (`assertValidResponse`) comprueba que el campo existe y es una cadena; **una
     * cadena vacía lo pasaría**. Estos casos existen porque el área de cliente pinta estas etiquetas
     * y no puede recomponerlas: `Intl` no reproduce la del día en español, y la del pedido lleva la
     * **zona horaria de la instalación**, que el navegador no conoce (`specs/area-cliente.md`).
     */
    public function test_it_publishes_the_presentation_labels_of_the_order(): void
    {
        $user = $this->verifiedUser();
        $order = $this->makeOrder($user);

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/orders');

        $response->assertOk()->assertValidResponse(200);

        $this->assertSame(
            DisplayTime::format($order->created_at),
            $response->json('data.0.created_label'),
            'la fecha del pedido no espeja a `DisplayTime::format`, que es quien aplica `display_timezone`'
        );
        $this->assertNotSame('', $response->json('data.0.created_label'));

        // Sin reembolso, la etiqueta va VACÍA y no ausente: el contrato la exige siempre presente,
        // de modo que el cliente no tiene que distinguir «no hay» de «no vino».
        $this->assertSame('', $response->json('data.0.refund.refunded_label'));
    }

    public function test_it_paginates_newest_first(): void
    {
        $user = $this->verifiedUser();
        foreach (range(1, 12) as $i) {
            // `created_at` no está en el `$fillable` de Order (`SEC-10`), y el guard estricto de
            // asignación masiva lo hace fallar en no-producción. Se fija después, que además es lo
            // honesto: el orden lo decide el timestamp real, no el `create()`.
            $this->makeOrder($user, ['code' => 'R-P'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)])
                ->forceFill(['created_at' => now()->subDays(20 - $i)])->save();
        }

        $first = $this->actingAs($user)->getJson(self::ROOT.'/me/orders');
        $first->assertOk()
            ->assertValidResponse(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            // Más reciente primero.
            ->assertJsonPath('data.0.code', 'R-P00012');

        $this->actingAs($user)->getJson(self::ROOT.'/me/orders?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_honours_a_smaller_page_size(): void
    {
        $user = $this->verifiedUser();
        foreach (range(1, 5) as $i) {
            $this->makeOrder($user, ['code' => 'R-S'.$i]);
        }

        $this->actingAs($user)->getJson(self::ROOT.'/me/orders?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    /** El techo evita que `?per_page` convierta el endpoint en una descarga completa. */
    public function test_it_rejects_a_page_size_above_the_cap(): void
    {
        $this->actingAs($this->verifiedUser())
            ->getJson(self::ROOT.'/me/orders?per_page=1000')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['per_page']]]);
    }

    public function test_it_rejects_an_anonymous_request(): void
    {
        $this->getJson(self::ROOT.'/me/orders')
            ->assertUnauthorized()
            ->assertValidResponse(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    /** `RGPD-04`: la lista lleva PII (productos, fechas, importes) y no se guarda en disco. */
    public function test_the_response_is_not_stored(): void
    {
        $response = $this->actingAs($this->verifiedUser())->getJson(self::ROOT.'/me/orders');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
