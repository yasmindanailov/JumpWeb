<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonOfferReader;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **UN COMPLEMENTO SE TARIFICA POR EL DÍA DE LA VISITA, NO POR EL DÍA DE LA COMPRA** (`#415`).
 *
 * Hasta el 2026-09-03 los SIETE puntos que tarifican complementos pasaban `Carbon::today()` mientras
 * el producto padre se tarificaba por el día de la línea: dos relojes para la misma línea. No había
 * bug de paridad —oferta y cobro coincidían, porque los dos se equivocaban igual— y por eso la suite
 * entera pasaba con el defecto puesto: **ningún complemento del catálogo tenía precio distinto por
 * tipo de día**, así que la diferencia valía cero euros y no la veía nadie.
 *
 * Deja de valer cero con la HORA EXTRA, que el owner quiere solo los viernes, findes, vísperas y
 * festivos (`specs/hora-extra.md` §4.11, decisión revisada). Eso se expresa con el mecanismo que ya
 * existe —precio solo en el tipo de tarifa `special`—, y con el reloj de la COMPRA salía invertido
 * en los dos sentidos:
 *
 *  · un MARTES no se podía añadir a una reserva del SÁBADO — y es justo cuando se compra;
 *  · un SÁBADO sí se añadía a una reserva del MARTES — cobrando un producto que ese día no existe.
 *
 * ⚠️ **Todos los casos viajan en el tiempo a propósito.** La conducta vieja y la nueva solo se
 * distinguen cuando el día de la compra y el de la visita caen en tipos de tarifa DISTINTOS: sin
 * `travelTo` estos casos pasarían con el defecto puesto los días en que ambos coinciden, que es
 * exactamente cómo el defecto sobrevivió hasta hoy.
 */
class AddonPricingDateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ Hacen falta TRES fechas, no dos, y la primera versión de este fichero lo aprendió en rojo:
     * para comprar «un martes una visita de sábado» el martes tiene que ser ANTERIOR al sábado, y
     * para el espejo hace falta otro martes POSTERIOR. Con dos fechas, uno de los dos casos compra
     * para el pasado y `OrderCreator` lo rechaza por `past_date_line` — un rojo que parece del
     * cambio y es del fixture.
     */
    private const TUESDAY_BEFORE = '2026-09-01';

    private const SATURDAY = '2026-09-05';

    private const TUESDAY_AFTER = '2026-09-08';

    private Zone $zone;

    private TicketType $entry;

    /** El de la hora extra: precio SOLO en `special`. */
    private TicketType $weekendOnly;

    /** El control: mismo precio los dos días, así que el cambio no puede moverlo. */
    private TicketType $everyDay;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        RateType::create([
            'key' => 'special', 'label' => ['es' => 'Viernes, findes y festivos'],
            'is_special' => true, 'weekdays' => [5, 6, 0], 'priority' => 10,
        ]);

        $this->user = User::factory()->create();
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 2 horas'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->price($this->entry, 'normal', 1800);
        $this->price($this->entry, 'special', 2200);

        $this->weekendOnly = $this->addon('Hora extra', 30);
        $this->price($this->weekendOnly, 'special', 800);

        $this->everyDay = $this->addon('Calcetines', 31);
        $this->price($this->everyDay, 'normal', 300);
        $this->price($this->everyDay, 'special', 300);

        foreach ([$this->weekendOnly, $this->everyDay] as $i => $addon) {
            $this->entry->configurableAddons()->attach($addon->id, [
                'position' => $i + 1, 'quantity_mode' => ProductAddon::MODE_FIXED,
                'stage' => ProductAddon::STAGE_BOOKING,
            ]);
        }
        $this->entry->refresh();

        foreach ([self::TUESDAY_BEFORE, self::SATURDAY, self::TUESDAY_AFTER] as $date) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $date,
                'start_time' => '11:00:00', 'end_time' => '13:00:00',
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }
    }

    private function addon(string $name, int $position): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => $position,
        ]);
    }

    private function price(TicketType $type, string $rateKey, int $cents): void
    {
        $rate = RateType::where('key', $rateKey)->firstOrFail();
        Price::updateOrCreate(
            ['priceable_type' => $type->getMorphClass(), 'priceable_id' => $type->id, 'rate_type_id' => $rate->id],
            ['amount_cents' => $cents, 'currency' => 'EUR'],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function cart(string $date, TicketType $addon, int $addonQty = 1, int $qty = 2): array
    {
        return [[
            'ticket_type_id' => $this->entry->id,
            'date' => $date,
            // ⚠️ `HH:MM:SS`: `Cart::sanitize()` NO normaliza la hora —eso lo hace la capa HTTP— y la
            // clave del slot se compone con `start_time`, que es `11:00:00`. Con `'11:00'` la línea
            // no encuentra su franja y sale `unavailable_line`, que parece un rechazo del dominio.
            'time' => '11:00:00',
            'qty' => $qty,
            'addons' => [['ticket_type_id' => $addon->id, 'qty' => $addonQty]],
        ]];
    }

    /**
     * El caso que da sentido a todo: se compra ENTRE SEMANA una visita de FIN DE SEMANA. Es el gesto
     * normal —nadie reserva el cumpleaños el mismo sábado— y con el reloj de la compra era imposible.
     */
    public function test_an_addon_priced_only_on_special_days_can_be_bought_on_a_normal_day_for_a_special_day(): void
    {
        $this->travelTo(Carbon::parse(self::TUESDAY_BEFORE.' 10:00:00'));

        $quote = app(CartPricing::class)
            ->quote($this->cart(self::SATURDAY, $this->weekendOnly));

        $this->assertCount(1, $quote->lines[0]->addons, 'el complemento de finde tiene que estar en el presupuesto');
        $this->assertSame(800, $quote->lines[0]->addons[0]->subtotalCents, 'se tarifica al precio del SÁBADO');

        // Y el cobro dice lo mismo: 2 × 22,00 € (sábado) + 8,00 € del complemento.
        $order = app(OrderCreator::class)->createPendingOrder($this->user, $this->cart(self::SATURDAY, $this->weekendOnly));

        $this->assertSame(4400 + 800, (int) $order->total, 'el pedido cobra el complemento del día de la VISITA');
        $this->assertSame($quote->totalCents, (int) $order->total, 'presupuesto y cobro tienen que dar el mismo número');
    }

    /**
     * El ESPEJO, y es el que más protege: comprar un SÁBADO no puede colar un producto de fin de
     * semana en una visita de MARTES. Con el reloj de la compra se cobraban 8,00 € por algo que ese
     * día no se vende.
     */
    public function test_an_addon_without_a_price_that_day_is_refused_even_when_bought_on_a_day_it_has_one(): void
    {
        $this->travelTo(Carbon::parse(self::SATURDAY.' 10:00:00'));

        $this->expectException(ReservationException::class);
        app(OrderCreator::class)->createPendingOrder($this->user, $this->cart(self::TUESDAY_AFTER, $this->weekendOnly));
    }

    /** La OFERTA responde a la misma pregunta que el cobro, o se ofrece lo que el checkout rechaza. */
    public function test_the_offer_decides_by_the_visit_day(): void
    {
        $this->travelTo(Carbon::parse(self::TUESDAY_BEFORE.' 10:00:00'));

        $forSaturday = app(AddonOfferReader::class)->resolve(
            (int) $this->entry->id, 2, [(int) $this->weekendOnly->id => 1], [], self::SATURDAY, '11:00:00',
        );
        $this->assertContains(
            (int) $this->weekendOnly->id,
            array_column($forSaturday->singles, 'productId'),
            'un martes, la oferta del SÁBADO tiene que incluirlo',
        );

        $forTuesday = app(AddonOfferReader::class)->resolve(
            (int) $this->entry->id, 2, [(int) $this->weekendOnly->id => 1], [], self::TUESDAY_AFTER, '11:00:00',
        );
        $this->assertNotContains(
            (int) $this->weekendOnly->id,
            array_column($forTuesday->singles, 'productId'),
            'la oferta del MARTES no puede incluirlo',
        );

        // CONTROL del localizador: el complemento de precio plano SÍ sale los dos días, así que un
        // `assertNotContains` que pase por mirar una lista vacía queda descartado.
        $this->assertContains(
            (int) $this->everyDay->id,
            array_column($forTuesday->singles, 'productId'),
            'el de precio plano tiene que seguir ofreciéndose el martes',
        );
    }

    /**
     * ⚠️ **El CONTROL, y sin él los casos de arriba no demostrarían nada**: un complemento con el
     * mismo precio los dos días no se mueve. Es el estado de los DOCE complementos reales del
     * catálogo el día del cambio —medido: cero con precio distinto por tipo de día—, o sea que este
     * caso es el que dice que la migración no movió un céntimo de lo que ya se vendía.
     */
    public function test_an_addon_with_the_same_price_both_days_is_untouched(): void
    {
        foreach ([self::TUESDAY_BEFORE, self::SATURDAY] as $boughtOn) {
            $this->travelTo(Carbon::parse($boughtOn.' 10:00:00'));

            foreach ([self::SATURDAY, self::TUESDAY_AFTER] as $visitOn) {
                $quote = app(CartPricing::class)
                    ->quote($this->cart($visitOn, $this->everyDay));

                $this->assertSame(
                    300,
                    $quote->lines[0]->addons[0]->subtotalCents,
                    "comprado el {$boughtOn} para el {$visitOn}: el precio plano no puede cambiar",
                );
            }
        }
    }

    /**
     * **El POST-FORM tarifica por el día de la FIESTA**, y aquí importa más que en ningún sitio: el
     * cliente entra por un enlace días o semanas después de reservar, así que el día de la «compra»
     * y el de la visita casi nunca coinciden. Cubre las dos mitades —lo que la pantalla ENSEÑA
     * ({@see PostFormAddons::viewFor}) y lo que el reconciliador COBRA— porque si divergieran, se
     * vería un precio y se cobraría otro.
     */
    public function test_the_post_form_prices_by_the_party_day(): void
    {
        [$pack, $addon] = $this->postFormPack();

        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => self::SATURDAY,
            'start_time' => '17:00:00', 'end_time' => '19:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
        $order = Order::create([
            'user_id' => $this->user->id, 'code' => 'JJ-PFD001', 'status' => Order::STATUS_PAID,
            'subtotal' => 10000, 'tax' => 0, 'total' => 10000, 'currency' => 'EUR', 'paid_at' => Carbon::parse(self::TUESDAY_BEFORE),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 10000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::parse(self::TUESDAY_BEFORE),
            'gateway_order' => '0000991234',
        ]);
        $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => 4,
            'unit_price' => 2500, 'seats' => 4, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        // El cliente abre su formulario un MARTES para una fiesta del SÁBADO.
        $this->travelTo(Carbon::parse(self::TUESDAY_BEFORE.' 10:00:00'));
        $principal = $this->freshReservation($order->id);

        $rows = app(\App\Domain\Booking\Services\PostFormAddons::class)->viewFor($principal);
        $shown = collect($rows)->firstWhere('productId', (int) $addon->id);

        $this->assertNotNull($shown, 'un martes, el extra de finde tiene que ofrecerse para la fiesta del sábado');
        $this->assertSame(800, $shown->unitPriceCents, 'se enseña al precio del día de la FIESTA');

        // Y lo que se cobra es lo mismo que se enseñó.
        app(\App\Domain\Booking\Services\PostFormAddons::class)
            ->reconcile($this->freshReservation($order->id), [(int) $addon->id => 2], 'signed_link');

        $line = $this->freshReservation($order->id)->children->firstWhere('ticket_type_id', $addon->id);
        $this->assertNotNull($line, 'la línea del extra tiene que existir');
        $this->assertSame(800, (int) $line->unit_price, 'se cobra el precio del día de la FIESTA, no el de hoy');
    }

    /** Un pack con post-form y un enganche de venta posterior del complemento de finde. */
    private function postFormPack(): array
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $pack->configurableAddons()->attach($this->weekendOnly->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => 48, 'max_qty' => 5,
        ]);

        return [$pack->refresh(), $this->weekendOnly];
    }

    private function freshReservation(int $orderId): OrderItem
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType.addons', 'adjustments', 'payments.refunds'])
            ->findOrFail($orderId)
            ->items->firstWhere('parent_item_id', null);
    }

    /**
     * **EL MOSTRADOR ve lo que se va a cobrar.** El alta manual tiene sus propios dos puntos de
     * tarificación —el desglose del carrito y el importe estimado de la línea— y los dos vivían con
     * el reloj de la compra. Aquí importa por una razón distinta a la web: el operador está con el
     * cliente delante diciéndole un precio.
     *
     * ⚠️ Se afirma sobre `line_total_cents` y sobre `addon_display`, que son los DOS números que la
     * pantalla enseña: mutar uno solo de los dos puntos tiene que poner esto en rojo, y por eso el
     * arnés los muta por separado.
     */
    public function test_the_counter_prices_addons_by_the_visit_day(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $customer = User::factory()->create();
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        // El operador vende un MARTES una visita del SÁBADO con el extra de finde.
        $this->travelTo(Carbon::parse(self::TUESDAY_BEFORE.' 10:00:00'));

        $component = Livewire::actingAs($staff)
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.sel_product_id', $this->entry->id)
            ->set('data.sel_date', self::SATURDAY)
            ->set('data.sel_time', '11:00:00')
            ->set('data.sel_qty', 2)
            // ⚠️ La selección de complementos NO vive en `data.*`: es una propiedad pública propia
            // de la página (`selAddonQty`), que es lo que lee `effectiveAddonSelection()`.
            ->set('selAddonQty', [(int) $this->weekendOnly->id => 1])
            ->call('addLineToCart');

        $cart = $component->get('cart');
        $this->assertCount(1, $cart, 'la línea tiene que entrar en el carrito del mostrador');

        // 2 × 22,00 € (sábado) + 8,00 € del extra de finde.
        $this->assertSame(4400 + 800, (int) $cart[0]['line_total_cents'], 'el importe estimado usa el día de la VISITA');

        $display = collect($cart[0]['addon_display'] ?? []);
        $this->assertCount(1, $display, 'el desglose tiene que listar el extra');
        $this->assertSame(800, (int) $display->first()['subtotal'], 'el desglose lo tarifica al día de la VISITA');
    }
}
