<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Contracts\CustomerOrderHistory;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Booking\Services\DailyReservationsSummary;
use App\Domain\Booking\Services\EmailProductCard;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La etiqueta «MIXTA» PEGADA AL NOMBRE, y las superficies que la cogen de ahí
 * (`docs/specs/cumple-mixto.md` §13).
 *
 * `[owner, 2026-08-29]`: «¿no podemos añadir esa etiqueta al nombre y que el resto lo coja de ahí,
 * en vez de añadirlo a cada superficie?». Se hizo así — con el matiz de que el sitio único es la
 * RESERVA (`OrderItem::displayProductName()`) y no el producto, que lo comparten todas las fiestas.
 *
 * ⚠️⚠️ **Lo que más protege este fichero no es que la etiqueta salga: es que salga GRATIS.**
 * `isMixedParty()` necesita `ticketType` y `slot`, y una lista que no las traiga cargadas paga dos
 * consultas POR FILA sin que nadie lo note — el precio de «que todos lo cojan de ahí». El último
 * caso compara el nº de consultas con 2 reservas y con 6: tiene que ser el MISMO.
 */
class MixedPartyLabelSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Slot $slot;

    private TicketType $kids;

    private int $counter = 0;

    /** Fecha fija: el fixture tiene calendario (`TESTING.md` §2). */
    private const DAY = '2026-07-15';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $this->zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'color' => '#FF5B22']);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => self::DAY,
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $this->kids = $this->pack('Cumpleaños Kids', 1, 6, 1800, $rate);
        $this->pack('Cumpleaños Jump', 7, 99, 2500, $rate);
    }

    private function pack(string $name, int $min, int $max, int $cents, RateType $rate): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 1, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true,
            'duration_min' => 120,
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

    /** @param  list<int>  $ages */
    private function party(array $ages): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-ML'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 1800 * count($ages), 'total' => 1800 * count($ages), 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (300000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $this->kids->id, 'slot_id' => $this->slot->id,
            'quantity' => count($ages), 'unit_price' => 1800, 'seats' => count($ages),
        ]);
        // El sello que `OrderCreator` pone al nacer (§21): la etiqueta deriva de él.
        app(AgeFamilySealer::class)->seal($item, $this->kids, $this->slot->date);

        $rows = [];
        foreach ($ages as $i => $age) {
            $rows[] = ['name' => 'Invitado '.($i + 1), 'edad' => (string) $age];
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    private function badge(): string
    {
        return __('tickets.mixed_party_badge');
    }

    // ─── El compositor ───────────────────────────────────────────────────────────

    public function test_the_name_carries_the_badge_only_when_the_party_is_mixed(): void
    {
        $this->assertStringContainsString($this->badge(), $this->party([4, 8])->displayProductName());

        // Control: sin invitados de otro tramo, el nombre es EXACTAMENTE el del catálogo. Sin este
        // caso, un compositor que pegara la etiqueta siempre pasaría el de arriba.
        $this->assertSame('Cumpleaños Kids', $this->party([4, 6])->displayProductName());
    }

    public function test_the_catalogue_name_is_never_touched(): void
    {
        $this->party([4, 8]);

        // El producto lo comparten todas las fiestas: si la etiqueta llegara hasta aquí, el
        // catálogo y el selector de producto del editor dirían «MIXTA» para todo el mundo.
        $this->assertSame('Cumpleaños Kids', $this->kids->fresh()->tr('name'));
    }

    // ─── Las superficies que lo cogen de ahí ─────────────────────────────────────

    public function test_the_reservation_slip_carries_it(): void
    {
        $item = $this->party([4, 8]);

        $this->assertStringContainsString(
            $this->badge(),
            ReservationSlip::make($item->order, $item)->productName(),
        );
    }

    public function test_the_gate_screen_carries_it(): void
    {
        $item = $this->party([4, 8]);

        $rows = app(GateReservations::class)->forHolder(
            (int) $item->order->user_id, self::DAY, self::DAY,
        );

        $this->assertNotEmpty($rows);
        $this->assertStringContainsString($this->badge(), $rows[0]->productName);
    }

    public function test_the_daily_summary_carries_it(): void
    {
        $this->party([4, 8]);

        $rows = DailyReservationsSummary::for(self::DAY, 'all')->rows();

        $this->assertNotEmpty($rows);
        $this->assertStringContainsString($this->badge(), (string) $rows[0]['product']);
    }

    public function test_the_calendar_carries_it(): void
    {
        // ⚠️ La primera versión de este caso tenía una rama de escape —«si la ruta no da 200,
        // asevera sobre el compositor»— y eso lo dejaba CIEGO: con un usuario sin permiso pasaba
        // sin llegar a mirar el calendario. La ruta se conduce con su permiso real y sin condición.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->party([4, 8]);

        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $staff->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['calendar.view'])->pluck('id'),
        );

        $this->actingAs($staff)
            ->get('/admin/calendario/eventos?start='.self::DAY.'&end='.self::DAY)
            ->assertOk()
            ->assertSee($this->badge());
    }

    public function test_the_email_product_card_carries_it(): void
    {
        $item = $this->party([4, 8]);

        $this->assertStringContainsString($this->badge(), EmailProductCard::forItem($item));
    }

    public function test_the_customer_order_history_carries_it(): void
    {
        $item = $this->party([4, 8]);

        // `exportFor` es la composición PORTABLE del historial del cliente (RGPD art. 20) y sale
        // del mismo mapeo que «Mis pedidos»: si la etiqueta llega aquí, llega a la pantalla.
        $export = app(CustomerOrderHistory::class)->exportFor((int) $item->order->user_id);

        $this->assertStringContainsString($this->badge(), json_encode($export, JSON_UNESCAPED_UNICODE));
    }

    // ─── Y que salga GRATIS ──────────────────────────────────────────────────────

    public function test_the_label_does_not_cost_a_query_per_row(): void
    {
        // ⚠️⚠️ El riesgo real de «que todos lo cojan del nombre»: `isMixedParty()` necesita
        // `ticketType` y `slot`, y una lista sin `with()` las pide POR FILA. Se compara el nº de
        // consultas con 2 fiestas y con 6: si creciera con las filas, aquí se ve.
        $this->party([4, 8]);
        $this->party([5, 9]);

        $withTwo = $this->countQueriesRenderingTheDay();

        $this->party([4, 8]);
        $this->party([5, 9]);
        $this->party([3, 7]);
        $this->party([6, 12]);

        $withSix = $this->countQueriesRenderingTheDay();

        $this->assertSame($withTwo, $withSix, 'el coste crece con las filas: falta un eager-load');
    }

    private function countQueriesRenderingTheDay(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach (DailyReservationsSummary::for(self::DAY, 'all')->rows() as $row) {
            (string) $row['product'];
        }

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
