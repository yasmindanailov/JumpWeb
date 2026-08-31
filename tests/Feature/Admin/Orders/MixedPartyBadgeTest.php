<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Lo que el OPERADOR ve de una fiesta MIXTA en la ficha del pedido
 * (`docs/specs/cumple-mixto.md` §9·5-7).
 *
 * ⚠️ Este caso existe porque el veredicto en verde **no demuestra que se pinte**: entre
 * `GuestAgeMixReader` y la pantalla hay una plantilla con una clave de idioma, un `@if` y una
 * variable, y las tres se rompen en silencio. Conduce la página REAL por HTTP, como la ve el
 * empleado.
 *
 * ⚠️ Y comprueba las TRES respuestas que el owner tiene que poder distinguir: hay suplemento ·
 * no hay mezcla · el veredicto todavía es parcial porque faltan edades. Un test que solo mirase
 * la primera dejaría pasar una pantalla que grita «MIXTA» sobre datos a medias.
 */
class MixedPartyBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $kids;

    private TicketType $jump;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $this->zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños']]);

        $this->kids = $this->pack('Cumpleaños Kids', 1, 6, 1800, $rate);
        $this->jump = $this->pack('Cumpleaños Jump', 7, 99, 2500, $rate);
    }

    private function pack(string $name, int $min, int $max, int $cents, RateType $rate): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 1, 'max_qty' => 30,
            'is_sellable' => true, 'is_active' => true,
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
    }

    /** @param  list<int|null>  $ages */
    private function paidPartyWith(array $ages, ?TicketType $booked = null): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MX'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1800 * count($ages), 'tax' => 0, 'total' => 1800 * count($ages),
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (100000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => '11:00:00', 'end_time' => '11:59:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => ($booked ?? $this->kids)->id, 'slot_id' => $slot->id,
            'quantity' => count($ages), 'seats' => count($ages), 'unit_price' => 1800,
        ]);
        // El sello que `OrderCreator` pone al nacer (`specs/cumple-mixto.md` §21).
        app(AgeFamilySealer::class)->seal($item, $booked ?? $this->kids, $slot->date);

        // ⚠️ Por la puerta REAL y no escribiendo `guest_data` a mano: es el guardado del post-form
        // el que reconcilia el suplemento, y lo que la pantalla enseña es lo ESCRITO. Un fixture que
        // se saltara ese paso probaría una pantalla que en producción no existe.
        $rows = [];
        foreach ($ages as $i => $age) {
            $row = ['name' => 'Invitado '.($i + 1)];
            if ($age !== null) {
                $row['edad'] = (string) $age;
            }
            $rows[] = $row;
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $order;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(Permission::whereIn('name', ['orders.view'])->pluck('id'));

        return $u;
    }

    public function test_the_panel_shows_the_badge_and_the_proposed_surcharge(): void
    {
        $order = $this->paidPartyWith([4, 5, 8]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('tickets.mixed_party_badge'))
            ->assertSee(__('admin.orders.mixed_party.title'))
            // 25,00 − 18,00 = 7,00 € por UN invitado. El importe es lo que el operador cobra en
            // caja: si la plantilla lo perdiera, la pantalla seguiría pareciendo correcta.
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']))
            // Y no hay desfase: lo escrito y lo derivado coinciden justo después de guardar.
            ->assertDontSee(__('admin.orders.mixed_party.missing_carrier'));
    }

    public function test_a_party_within_the_range_shows_nothing(): void
    {
        $order = $this->paidPartyWith([4, 5, 6]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertDontSee(__('tickets.mixed_party_badge'))
            ->assertDontSee(__('admin.orders.mixed_party.title'));
    }

    public function test_missing_ages_are_announced_as_a_partial_verdict(): void
    {
        $order = $this->paidPartyWith([4, null, null]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            // Sin edad no se supone que nadie sea mayor: no hay etiqueta…
            ->assertDontSee(__('tickets.mixed_party_badge'))
            // …pero el operador tiene que saber que el veredicto aún puede cambiar.
            ->assertSee(__('admin.orders.mixed_party.without_age', ['count' => 2]));
    }

    public function test_the_panel_says_the_party_would_be_cheaper_without_discounting_it(): void
    {
        // El pack CARO con dos invitados que corresponden al barato: no hay cargo, pero el operador
        // tiene que saberlo — `[owner, 2026-08-29]`: «avisar de que la reserva es X € más barata,
        // sin devolver dinero automáticamente».
        $order = $this->paidPartyWith([9, 4, 3], $this->jump);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('tickets.mixed_party_badge'))
            // 2 × (25,00 − 18,00) = 14,00 €.
            ->assertSee(__('admin.orders.mixed_party.cheaper', ['amount' => '14,00 €']))
            // ⚠️ Y NUNCA como cargo: si esto apareciera, el operador cobraría lo que no se debe.
            ->assertDontSee(__('admin.orders.mixed_party.applied', ['amount' => '14,00 €']));
    }

    public function test_retiring_the_family_in_the_catalogue_changes_nothing_on_screen(): void
    {
        // Hasta el 2026-08-31 este caso probaba el HUÉRFANO por catálogo: retirar la familia dejaba
        // el cargo sin veredicto. Con el SELLO (`specs/cumple-mixto.md` §21, `DECISIONES #284` D2)
        // el catálogo ya no manda sobre una fiesta vendida: el bloque sigue diciendo lo mismo que
        // antes del cambio, con su veredicto y su importe — y sin ningún aviso, porque no hay nada
        // que avisar.
        $order = $this->paidPartyWith([4, 5, 8]);
        $this->kids->forceFill(['guest_age_family' => null])->save();

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.title'))
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']))
            ->assertDontSee(__('admin.orders.mixed_party.orphaned'))
            ->assertDontSee(__('admin.orders.mixed_party.stale_seal'));
    }

    public function test_a_charge_on_a_reservation_without_a_seal_is_still_explained(): void
    {
        // ⚠️⚠️ El bloque entero colgaba de `$mix->applies`, así que un cargo sin veredicto dejaba al
        // operador con un importe en «a cobrar en el parque» y CERO explicación en pantalla. Hoy
        // eso solo lo produce una reserva SIN sello (anterior a él, `D3`): el dinero escrito manda
        // sobre si esto se pinta, y se le dice de dónde sale y por qué no hay veredicto.
        $order = $this->paidPartyWith([4, 5, 8]);
        OrderItem::where('order_id', $order->id)->whereNull('parent_item_id')->update(['age_family_seal' => null]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']))
            ->assertSee(__('admin.orders.mixed_party.orphaned'))
            // Y sin fingir un veredicto que no se puede derivar: nada de «hay invitados de otro
            // tramo», porque sin sello el sistema no sabe decirlo.
            ->assertDontSee(__('admin.orders.mixed_party.title'));
    }

    public function test_a_seal_that_does_not_match_the_row_is_shouted_in_red(): void
    {
        // Ningún camino del producto lo produce (el editor re-sella en la misma transacción): si
        // aparece, alguien movió la fila por fuera. Es un dato inconsistente, no un estado del
        // negocio, y el operador tiene que verlo ANTES de cobrar nada.
        $order = $this->paidPartyWith([4, 5, 8]);
        OrderItem::where('order_id', $order->id)->whereNull('parent_item_id')->update(['ticket_type_id' => $this->jump->id]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.stale_seal'))
            ->assertDontSee(__('admin.orders.mixed_party.orphaned'))
            ->assertDontSee(__('admin.orders.mixed_party.title'));
    }
}
