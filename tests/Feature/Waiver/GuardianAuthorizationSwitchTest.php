<?php

namespace Tests\Feature\Waiver;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Cart;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **EL INTERRUPTOR del justificante de un menor invitado** — tanda T5
 * (`docs/specs/waiver-por-reserva.md` §12.2, `[DECIDIDO owner, 2026-09-01]`).
 *
 * Lo que vigila, por orden de lo que puede doler:
 *
 *  1. **Que `required` no dependa del navegador.** Si la marca de la línea saliera de la casilla,
 *     una excursión de colegio se compraría sin justificantes quitando un `input` del DOM. Aquí se
 *     fija lo que el modelo promete; que el servidor lo FUERCE al crear el pedido es la T6.
 *  2. **Que un valor corrupto degrade a `none`** y no a algo que pinte casillas. Regla 12 del
 *     proyecto sobre una columna de texto libre.
 *  3. **Que `needsGuardianAuthorization()` cuente lo que debe**: principales VIVAS. Es la misma
 *     cuenta que `AuthorizableOrdersReader::capacity`, y contar de más aquí llenaría de correos a
 *     clientes con una línea cancelada.
 *  4. **Que la marca de la línea sea un HECHO**: cambiar el interruptor del producto **no** puede
 *     reescribir lo que un cliente ya compró.
 */
class GuardianAuthorizationSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    /** Contador de códigos de pedido: `mt_rand(100, 999)` era una MONEDA AL AIRE contra el
     * `UNIQUE` de `orders.code` (900 valores, dos pedidos por caso ≈ 0,11 % de colisión por
     * ejecución) — la cazó `audit-clock` por pura repetición, no por el reloj (`#411`). La
     * familia de `#337`/`#408`: lo aleatorio contra una restricción es un rojo con fecha. */
    private int $orderCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Día normal'],
            'is_special' => false, 'priority' => 0, 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function entry(string $mode = TicketType::GUARDIAN_NONE): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'guardian_authorization' => $mode,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    /** Un pedido pagado con UNA línea principal viva. La marca se pasa a mano: la pone la T6. */
    private function paidOrderWithLine(bool $flagged): Order
    {
        $type = $this->entry();
        // `firstOrCreate` y no `create`: un caso que construye DOS pedidos chocaría con el UNIQUE
        // (zona, día, hora) de `slots`, y ese choque no dice nada sobre lo que se está probando.
        $slot = Slot::firstOrCreate([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(3)->toDateString(), 'start_time' => '10:00:00',
        ], ['end_time' => '14:00:00', 'capacity' => 50, 'online_capacity' => 50]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'R-SWITCH'.str_pad((string) ++$this->orderCounter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);

        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 4, 'unit_price' => 250, 'seats' => 4,
            'guardian_authorization' => $flagged,
        ]);

        return $order;
    }

    // ─── 1 · Los tres estados, y qué promete cada uno ─────────────────────────

    public function test_only_optional_asks_the_customer_and_only_required_forces_the_line(): void
    {
        $none = $this->entry(TicketType::GUARDIAN_NONE);
        $optional = $this->entry(TicketType::GUARDIAN_OPTIONAL);
        $required = $this->entry(TicketType::GUARDIAN_REQUIRED);

        // Se PREGUNTA solo en `optional`: en `required` no hay nada que preguntar, y una casilla
        // marcada e inerte invita a intentar desmarcarla.
        $this->assertFalse($none->offersGuardianAuthorization());
        $this->assertTrue($optional->offersGuardianAuthorization());
        $this->assertFalse($required->offersGuardianAuthorization());

        // Se FUERZA solo en `required`, que es lo que impide comprar una excursión sin justificantes.
        $this->assertFalse($none->requiresGuardianAuthorization());
        $this->assertFalse($optional->requiresGuardianAuthorization());
        $this->assertTrue($required->requiresGuardianAuthorization());

        // Y «tiene algo que ver con justificantes» son los dos, que es lo que gobierna si el producto
        // aparece en las superficies del operador.
        $this->assertFalse($none->usesGuardianAuthorization());
        $this->assertTrue($optional->usesGuardianAuthorization());
        $this->assertTrue($required->usesGuardianAuthorization());
    }

    public function test_an_unknown_value_degrades_to_none(): void
    {
        $type = $this->entry();

        // Por la puerta de atrás: una importación, un seeder viejo, un `update()` a mano. El modelo
        // NO puede creerse la columna — y el estado al que cae tiene que ser el que no hace nada.
        DB::table('ticket_types')->where('id', $type->id)->update(['guardian_authorization' => 'obligatorio']);

        $fresh = $type->fresh();
        $this->assertSame(TicketType::GUARDIAN_NONE, $fresh->guardianMode());
        $this->assertFalse($fresh->offersGuardianAuthorization());
        $this->assertFalse($fresh->requiresGuardianAuthorization());

        // Control: con un valor VÁLIDO por la misma puerta, el modelo sí lo respeta — sin esto, un
        // `guardianMode()` que devolviera `none` siempre pasaría este caso igual.
        DB::table('ticket_types')->where('id', $type->id)->update(['guardian_authorization' => TicketType::GUARDIAN_REQUIRED]);
        $this->assertTrue($type->fresh()->requiresGuardianAuthorization());
    }

    // ─── 2 · A qué PEDIDO se le ofrece ────────────────────────────────────────

    public function test_an_order_needs_it_only_when_a_live_main_line_carries_the_mark(): void
    {
        $this->assertFalse($this->paidOrderWithLine(flagged: false)->needsGuardianAuthorization());
        $this->assertTrue($this->paidOrderWithLine(flagged: true)->needsGuardianAuthorization());
    }

    public function test_a_cancelled_line_stops_offering_it(): void
    {
        $order = $this->paidOrderWithLine(flagged: true);
        $this->assertTrue($order->needsGuardianAuthorization());   // control: antes de cancelar, sí

        $order->items()->first()->update(['cancelled_at' => now()]);

        // Una línea cancelada ya no trae a nadie: seguir ofreciendo el enlace sería mandar al cliente
        // a repartir un papel de una reserva que no existe.
        $this->assertFalse($order->fresh()->needsGuardianAuthorization());
    }

    public function test_a_complement_never_makes_an_order_need_it(): void
    {
        $order = $this->paidOrderWithLine(flagged: false);
        $main = $order->items()->first();

        // Un complemento (calcetines, tarta) no trae menores. Marcarlo no puede activar la feature.
        $order->items()->create([
            'parent_item_id' => $main->id,
            'ticket_type_id' => $main->ticket_type_id,
            'quantity' => 1, 'unit_price' => 100, 'seats' => 0,
            'guardian_authorization' => true,
        ]);

        $this->assertFalse($order->fresh()->needsGuardianAuthorization());
    }

    // ─── 3 · La marca de la línea es un HECHO, no una consulta al catálogo ────

    public function test_changing_the_product_switch_does_not_rewrite_what_was_sold(): void
    {
        $order = $this->paidOrderWithLine(flagged: true);
        $type = $order->items()->first()->ticketType;

        // El parque decide mañana que ese producto ya no lleva justificante.
        $type->update(['guardian_authorization' => TicketType::GUARDIAN_NONE]);

        // La reserva vendida ayer conserva lo que se acordó: es la misma regla que el sello de
        // `cumple-mixto.md` («solo cambia de condiciones lo que cambia de producto»).
        $this->assertTrue($order->fresh()->needsGuardianAuthorization());
        $this->assertTrue((bool) $order->items()->first()->guardian_authorization);
    }

    // ─── 4 · El EMBUDO: quién decide la marca de la línea (T6) ────────────────

    /**
     * Por `OrderCreator` y no escribiendo la fila a mano: es la puerta REAL de la web, la API y el
     * alta manual del panel. Si la marca dejara de ponerse ahí, todo lo demás —el correo, el panel,
     * la puerta— derivaría del silencio.
     */
    private function buy(TicketType $type, ?bool $said): Order
    {
        $slot = Slot::firstOrCreate([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(5)->toDateString(), 'start_time' => '11:00:00',
        ], ['end_time' => '14:00:00', 'capacity' => 50, 'online_capacity' => 50]);

        $line = [
            'ticket_type_id' => $type->id,
            'date' => $slot->date->toDateString(),
            'time' => '11:00:00',
            'qty' => 2,
        ];
        if ($said !== null) {
            $line['guardian_authorization'] = $said;
        }

        return app(OrderCreator::class)->createPendingOrder(User::factory()->create(), [$line]);
    }

    private function sellableEntry(string $mode): TicketType
    {
        $type = $this->entry($mode);
        Price::create([
            'priceable_type' => $type->getMorphClass(), 'priceable_id' => $type->id,
            'rate_type_id' => RateType::query()->value('id'), 'amount_cents' => 1000, 'currency' => 'EUR',
        ]);

        return $type;
    }

    public function test_a_required_product_marks_the_line_even_if_the_client_says_no(): void
    {
        // ❗ El caso que de verdad protege esto: una excursión de colegio comprada quitando la
        // casilla del DOM. La marca la pone el SERVIDOR o no la pone nadie.
        $order = $this->buy($this->sellableEntry(TicketType::GUARDIAN_REQUIRED), said: false);

        $this->assertTrue((bool) $order->items()->first()->guardian_authorization);
        $this->assertTrue($order->needsGuardianAuthorization());
    }

    public function test_an_optional_product_obeys_the_client_in_both_directions(): void
    {
        $type = $this->sellableEntry(TicketType::GUARDIAN_OPTIONAL);

        $this->assertTrue((bool) $this->buy($type, said: true)->items()->first()->guardian_authorization);
        // Control en la otra dirección: sin él, un `guardian_authorization => true` fijo pasaría.
        $this->assertFalse((bool) $this->buy($type, said: false)->items()->first()->guardian_authorization);
        // Y AUSENTE es «no», que es lo que manda todo cliente que no conozca el campo.
        $this->assertFalse((bool) $this->buy($type, said: null)->items()->first()->guardian_authorization);
    }

    public function test_a_product_that_does_not_offer_it_ignores_a_tampered_field(): void
    {
        // Un cuerpo manipulado contra un producto que no ofrece justificante: se ignora. La respuesta
        // correcta a un campo que no viene a cuento no es un error, es no hacerle caso.
        $order = $this->buy($this->sellableEntry(TicketType::GUARDIAN_NONE), said: true);

        $this->assertFalse((bool) $order->items()->first()->guardian_authorization);
        $this->assertFalse($order->needsGuardianAuthorization());
    }

    public function test_the_session_cart_whitelist_lets_the_mark_through(): void
    {
        // ⚠️ `OrderCreator::create()` pasa la cesta por `Cart::sanitize()`, que es una LISTA BLANCA:
        // una clave que no se nombre allí se cae **en silencio** camino del `create()`, y el cliente
        // vería su casilla marcada y su reserva sin marcar. Este caso vigila esa costura, que las
        // pruebas de arriba no distinguen de un fallo del propio `OrderCreator`.
        $clean = Cart::sanitize([[
            'ticket_type_id' => 1, 'date' => '2026-09-10', 'time' => '11:00:00', 'qty' => 2,
            'guardian_authorization' => true,
        ]]);

        $this->assertTrue($clean[0]['guardian_authorization']);
        $this->assertArrayHasKey('guardian_authorization', Cart::sanitize([[
            'ticket_type_id' => 1, 'date' => '2026-09-10', 'time' => '11:00:00', 'qty' => 2,
        ]])[0]);
    }

    // ─── 5 · El panel guarda los tres estados ─────────────────────────────────

    public function test_the_panel_saves_the_three_modes(): void
    {
        $type = $this->entry();

        foreach (TicketType::GUARDIAN_MODES as $mode) {
            Livewire::actingAs($this->admin())
                ->test(EditCatalog::class, ['record' => $type->id])
                ->fillForm(['guardian_authorization' => $mode])
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertSame($mode, $type->fresh()->guardianMode(), "el modo {$mode} no se guardó");
        }
    }

    public function test_the_panel_rejects_a_mode_that_is_not_in_the_list(): void
    {
        $type = $this->entry(TicketType::GUARDIAN_OPTIONAL);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $type->id])
            ->fillForm(['guardian_authorization' => 'obligatorio'])
            ->call('save')
            ->assertHasFormErrors(['guardian_authorization']);

        // Y no se guardó nada: el valor de antes sigue en su sitio.
        $this->assertSame(TicketType::GUARDIAN_OPTIONAL, $type->fresh()->guardianMode());
    }
}
