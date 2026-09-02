<?php

namespace Tests\Feature\Waiver;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ManualOrderFulfiller;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\GuardianAuthorizationRequest;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **LA ENTREGA del enlace del justificante** — tanda T7
 * (`docs/specs/waiver-por-reserva.md` §12.1, §12.3, §12.6).
 *
 * ❗❗ **El defecto que motiva este fichero lo encontró el OWNER probándolo**, y la suite estaba
 * verde: *«En el panel del cliente no me sale nada del enlace. Ni de los que han firmado o no.»*
 *
 * La sección del panel era `->visible(countFor(...) > 0)` **con el botón del enlace dentro**, así que
 * solo aparecía cuando ya había un justificante firmado — y para que hubiera uno hacía falta el
 * enlace. *Una condición de visibilidad escrita para lo que se LEE acabó escondiendo lo que se HACE*,
 * y ninguna guarda lo veía porque todas sembraban un justificante antes de mirar.
 *
 * ▶ Por eso los casos de aquí parten del pedido **sin firmar nada**, que es el estado en el que esta
 * feature tiene que servir para algo.
 */
class GuardianLinkDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El reloj CONGELADO, por la lección de `#337`: los casos siembran una franja y el dominio se
     * niega a autorizar sobre una visita terminada, así que cerca de medianoche se ponían rojos solos.
     * Mediodía en Madrid y el mismo día natural en las dos zonas.
     */
    private const FROZEN_NOW = '2026-06-15 09:00:00';

    private Zone $zone;

    private RateType $rate;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::FROZEN_NOW);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        $this->rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'is_special' => false, 'priority' => 0, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function product(string $mode): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'guardian_authorization' => $mode,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        Price::create([
            'priceable_type' => $type->getMorphClass(), 'priceable_id' => $type->id,
            'rate_type_id' => $this->rate->id, 'amount_cents' => 1000, 'currency' => 'EUR',
        ]);

        return $type;
    }

    private function slot(): Slot
    {
        return Slot::firstOrCreate([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(5)->toDateString(), 'start_time' => '11:00:00',
        ], ['end_time' => '14:00:00', 'capacity' => 50, 'online_capacity' => 50]);
    }

    /** Un pedido PAGADO por la puerta del MOSTRADOR, que es el «caso 3» del owner. */
    private function sellAtCounter(TicketType $type, bool $said): Order
    {
        $customer = User::factory()->create(['email' => 'cliente'.mt_rand(1, 9999).'@example.test']);
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return app(ManualOrderFulfiller::class)->fulfill($customer, [[
            'ticket_type_id' => $type->id,
            'date' => $this->slot()->date->toDateString(),
            'time' => '11:00:00',
            'qty' => 3,
            'guardian_authorization' => $said,
        ]], ManualOrderFulfiller::METHOD_CASH);
    }

    // ─── 1 · El correo al PAGAR ───────────────────────────────────────────────

    public function test_the_link_is_emailed_when_the_order_was_bought_with_the_mark(): void
    {
        Notification::fake();

        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_OPTIONAL), said: true);

        $reservation = $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail();

        Notification::assertSentTo(
            $order->user,
            GuardianAuthorizationRequest::class,
            // ⚠️ **UNO por RESERVA desde `#401`**, no uno por pedido: cada visita tiene su enlace y su
            // fecha. Se asevera CUÁL, no solo que llegó uno.
            fn (GuardianAuthorizationRequest $n): bool => (int) $n->reservation->id === (int) $reservation->id,
        );
        Notification::assertSentToTimes($order->user, GuardianAuthorizationRequest::class, 1);
    }

    public function test_a_normal_order_gets_no_guardian_email(): void
    {
        Notification::fake();

        // Control del caso de arriba: sin él, un envío incondicional pasaría igual de verde. Y es la
        // conducta que protege al 99 % de los pedidos de recibir un correo que no les toca.
        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_NONE), said: true);

        // ⚠️ `assertNotSentTo` y NO `assertNothingSentTo`: el mostrador manda además la confirmación
        // de compra, así que «no le llegó nada» sería falso y el caso saldría rojo con el producto
        // sano. Lo que se afirma es que no le llegó ESTE correo.
        Notification::assertNotSentTo($order->user, GuardianAuthorizationRequest::class);
    }

    public function test_the_counter_wizard_carries_the_operators_tick_all_the_way_to_the_line(): void
    {
        // ⚠️ **La costura que este caso vigila es un MAPEO, no una regla**: el asistente del pedido
        // manual guarda su propia forma de línea y la traduce a la del dominio en `cartToOrderCart()`.
        // Una clave que no se copie ahí se cae **en silencio** —el operador marca la casilla y la
        // reserva nace sin marca— exactamente como pasa con la lista blanca de `Cart::sanitize()`.
        $type = $this->product(TicketType::GUARDIAN_OPTIONAL);
        $customer = User::factory()->create(['email' => 'mostrador@example.test']);
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        $page = new CreateManualOrderPage;
        $page->cart = [[
            'ticket_type_id' => $type->id,
            'date' => $this->slot()->date->toDateString(),
            'time' => '11:00:00',
            'qty' => 2,
            'guardian_authorization' => true,
        ]];

        $method = new \ReflectionMethod(CreateManualOrderPage::class, 'cartToOrderCart');
        $method->setAccessible(true);
        $cart = $method->invoke($page);

        $this->assertTrue($cart[0]['guardian_authorization'], 'la marca no cruzó del asistente al dominio');

        // Y de punta a punta: la línea nace marcada y el pedido pide su enlace.
        $order = app(ManualOrderFulfiller::class)->fulfill($customer, $cart, ManualOrderFulfiller::METHOD_CASH);
        $this->assertTrue($order->needsGuardianAuthorization());
    }

    // ─── 2 · El PANEL: el huevo y la gallina ──────────────────────────────────

    public function test_the_panel_offers_the_link_before_anyone_has_signed(): void
    {
        // ❗ EL CASO DEL OWNER. Pedido marcado, CERO justificantes: la sección tiene que existir y
        // llevar el botón del enlace. Con la condición vieja (`countFor > 0`) esto salía en blanco.
        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_REQUIRED), said: false);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertSee(__('admin.orders.guest_minors.section'))
            ->assertSee(__('admin.orders.guest_minors.copy_link'))
            // Y el estado vacío DICE QUÉ HACER, que es la mitad útil de la sección hasta que firman.
            ->assertSee(__('admin.orders.guest_minors.empty'));
    }

    public function test_a_plain_order_still_has_no_section(): void
    {
        // La parte BUENA de la regla vieja se conserva: la ficha del pedido ya es larga y un pedido
        // normal —que son casi todos— no gana una sección vacía.
        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_NONE), said: false);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertDontSee(__('admin.orders.guest_minors.section'));
    }

    // ─── 3 · La salida para «el cliente no sabía que hacía falta» ─────────────

    public function test_every_paid_order_can_resend_the_guardian_link(): void
    {
        // El caso 2 del owner: un pedido que NADIE marcó. El operador tiene que poder mandárselo, así
        // que el tipo de reenvío no mira la marca — solo que esté pagado, que es lo que el enlace
        // público exige.
        $unmarked = $this->sellAtCounter($this->product(TicketType::GUARDIAN_NONE), said: false);

        $this->assertContains(Order::RESEND_TYPE_GUARDIAN, $unmarked->availableResendEmailTypes());
        $this->assertTrue($unmarked->canResend(Order::RESEND_TYPE_GUARDIAN));
    }

    public function test_an_unpaid_order_cannot_resend_it(): void
    {
        // Control: ofrecerlo antes de pagar mandaría al padre a una pantalla que le dice que no
        // (`GuardianAuthorizationRefusedException::notPaid`).
        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_OPTIONAL), said: true);
        $order->forceFill(['status' => Order::STATUS_PENDING])->save();

        $this->assertFalse($order->fresh()->canResend(Order::RESEND_TYPE_GUARDIAN));
    }

    public function test_sending_the_link_from_the_order_page_actually_sends_it(): void
    {
        Notification::fake();

        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_NONE), said: false);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('sendGuardianLink', arguments: [
                'item' => $order->items()->whereNull('parent_item_id')->orderBy('id')->value('id'),
            ]);

        Notification::assertSentTo($order->user, GuardianAuthorizationRequest::class);
    }

    // ─── 4 · Ninguna clave sin traducir llega a la pantalla ───────────────────

    /**
     * ❗❗ **Lo cazó el owner leyendo `admin.orders.guest_minors.assigned` EN PANTALLA** (`#402`).
     *
     * La clave se usó y nunca se declaró, y **ningún test lo vio**: solo se pinta cuando la reserva
     * tiene menores a cargo asignados, y ninguna guarda montaba ese caso. Laravel no falla ante una
     * clave ausente — devuelve la clave —, así que el defecto es **mudo salvo que alguien mire**.
     *
     * ▶ Esta guarda es general a propósito: no comprueba una clave concreta, sino que **el
     * identificador de ningún grupo de idioma aparezca en el HTML**. Sirve para la siguiente.
     */
    public function test_no_untranslated_key_reaches_the_order_page(): void
    {
        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_REQUIRED), said: false);
        $item = $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail();

        // El caso que nadie montaba: una reserva con un menor a cargo asignado Y un justificante.
        // Es el único que pinta la línea de «de ellas para un menor a tu cargo».
        $holder = $order->user;
        DependentAssignment::create([
            'dependent_id' => Dependent::create([
                'user_id' => $holder->id, 'name' => 'Hija', 'surname' => 'Del Titular',
                'born_on' => now()->subYears(8)->toDateString(), 'relationship' => 'father',
            ])->id,
            'order_item_id' => $item->id,
        ]);
        GuardianAuthorization::create([
            'order_item_id' => $item->id,
            'minor_name' => 'Nora', 'minor_surname' => 'Invitada Uno',
            'minor_key' => GuardianAuthorization::keyFor('Nora', 'Invitada Uno'),
            'minor_born_on' => '2016-04-02',
            'guardian_name' => 'Elena', 'guardian_surname' => 'Familia Invitada',
            'guardian_relationship' => 'mother',
        ]);

        $html = Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->html();

        // CONTROL: la sección se está pintando de verdad. Sin él, una página que no la incluyera
        // pasaría este test en blanco — que es el escalón de `#161`.
        $this->assertStringContainsString(__('admin.orders.guest_minors.section'), $html);
        $this->assertStringContainsString('Nora Invitada Uno', $html);

        // Y ninguna clave en crudo. `admin.` y `guardian.` son los dos grupos que esta sección usa.
        foreach (['admin.orders.', 'guardian.relationships.', 'tickets.'] as $prefijo) {
            $this->assertStringNotContainsString(
                $prefijo, $html,
                "la ficha del pedido enseña «{$prefijo}…» en crudo: hay una clave de idioma sin declarar",
            );
        }
    }

    // ─── 5 · Más justificantes que plazas (§12.6) ─────────────────────────────

    public function test_the_panel_warns_when_there_are_more_authorizations_than_places(): void
    {
        $order = $this->sellAtCounter($this->product(TicketType::GUARDIAN_REQUIRED), said: false);

        // Un padre firma. La fila se escribe a mano a propósito: lo que se está probando es la
        // ARITMÉTICA de la pantalla, no el firmador —que tiene sus propias guardas y arrastraría todo
        // el aparato del documento legal a un caso que no habla de él—.
        GuardianAuthorization::create([
            'order_item_id' => $order->items()->whereNull('parent_item_id')->orderBy('id')->value('id'),
            'minor_name' => 'Ana', 'minor_surname' => 'Gómez Ruiz',
            'minor_key' => GuardianAuthorization::keyFor('Ana', 'Gómez Ruiz'),
            'minor_born_on' => '2016-04-02',
            'guardian_name' => 'Marta', 'guardian_surname' => 'Ruiz Díaz',
            'guardian_relationship' => 'mother',
        ]);

        // CONTROL: 1 justificante y 3 plazas. No se avisa — sin este caso, un aviso incondicional
        // pasaría igual de verde y nadie lo notaría hasta que el panel gritara en todos los pedidos.
        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertSee(trans_choice('admin.orders.guest_minors.capacity', 3, ['count' => 3]))
            ->assertDontSee(__('admin.orders.guest_minors.overflow', ['count' => 1, 'capacity' => 0]));

        // Y ahora el caso REAL del owner, con sus palabras: *«¿qué pasa si hay 50 justificantes y bajo
        // la cantidad de entradas a 40?»*. Se baja la línea por debajo de lo firmado. El justificante
        // NO se borra —es una firma con valor probatorio, no un cupo— así que la reserva queda con un
        // papel y cero plazas. Antes esto no lo decía nadie y la hoja de sala lo imprimía tan tranquila.
        //
        // ⚠️ Se baja la CANTIDAD y no se cancela la línea: una reserva cancelada deja de ser una
        // visita, así que sale de esta sección entera (§13.4). Lo que aquí se prueba es la reserva
        // VIVA que se quedó pequeña.
        $order->items()->whereNull('parent_item_id')->first()->update(['quantity' => 0]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertSee(__('admin.orders.guest_minors.overflow', ['count' => 1, 'capacity' => 0]));
    }
}
