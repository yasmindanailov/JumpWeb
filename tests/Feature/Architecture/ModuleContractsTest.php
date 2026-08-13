<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Contracts\CartQuote;
use App\Domain\Booking\Contracts\CartQuoteLine;
use App\Domain\Booking\Contracts\CatalogProduct;
use App\Domain\Booking\Contracts\CatalogProductDetail;
use App\Domain\Booking\Contracts\CatalogZone;
use App\Domain\Booking\Contracts\ComplementPlacement;
use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\OperatingWindow;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Contracts\SeasonWindow;
use App\Domain\Booking\Contracts\SpecialDay;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Booking\Contracts\WeeklyOpening;
use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\CartPricer;
use App\Domain\Booking\Services\CatalogReader;
use App\Domain\Booking\Services\CustomerReservationsReader;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\PublishableCatalogReader;
use App\Domain\Booking\Services\ZonePaletteReader;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\LandingComplementResolver;
use App\Domain\Content\Services\ScheduleDisplay;
use App\Domain\Content\Services\ThemeSettings;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Contracts\RefundResult;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\Redsys;
use App\Livewire\Tickets\Purchase;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 2, paso 1 — los contratos de módulo están **enchufados de verdad**
 * (`docs/specs/modulos-dominio.md` §5.1, `DECISIONES #13`).
 *
 * `ModuleBoundariesTest` vigila las flechas del código; este vigila el comportamiento: sustituye
 * cada contrato por un doble y comprueba que el consumidor real cambia de conducta. Si alguien
 * vuelve a llamar a la clase legacy por debajo (`app(Redsys::class)`, una query a `OrderItem`
 * desde Identity…), el doble se queda sin usar y el test cae.
 *
 * Es la red que permite mudar las implementaciones en los pasos 5 y 6 sin tocar a sus
 * consumidores.
 */
class ModuleContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_contract_resolves_to_its_legacy_implementation(): void
    {
        // Los bindings de `BookingServiceProvider`/`PaymentsServiceProvider`. Cuando las
        // implementaciones muden (pasos 5 y 6), aquí solo cambia el nombre esperado.
        $this->assertInstanceOf(Redsys::class, app(RefundGateway::class));
        $this->assertInstanceOf(CustomerReservationsReader::class, app(CustomerReservations::class));
        $this->assertInstanceOf(PublishableCatalogReader::class, app(PublishableCatalog::class));
        $this->assertInstanceOf(CatalogReader::class, app(ProductCatalog::class));
        $this->assertInstanceOf(OperatingSchedule::class, app(OperatingCalendar::class));
        $this->assertInstanceOf(ZonePaletteReader::class, app(ZonePalette::class));
        $this->assertInstanceOf(CartPricer::class, app(CartPricing::class));
    }

    /**
     * WEB → BOOKING: los importes del carrito los pone el dominio, no el componente Livewire
     * (Fase 3 · paso 4a).
     *
     * El doble tarifica SIEMPRE lo mismo y devuelve un producto que no existe en base de datos. Si
     * `Tickets\Purchase` conservara su aritmética —la que vivía en `cartLines()`, `cartTotalCents()`
     * y `cartDepositCents()`—, con una cesta vacía en sesión el carrito saldría vacío y a cero, y
     * estas aserciones caerían. Es lo que convierte «la web y la API cobran igual» en un hecho
     * comprobado en vez de una promesa: `POST orders/quote` consume este mismo contrato.
     */
    public function test_the_web_cart_gets_its_amounts_from_the_contract(): void
    {
        $pricing = new class implements CartPricing
        {
            public int $calls = 0;

            public function quote(array $cart): CartQuote
            {
                $this->calls++;

                return new CartQuote(
                    lines: [new CartQuoteLine(
                        index: 0,
                        productId: 4242,
                        name: 'Línea del contrato',
                        isPack: false,
                        date: '2099-01-01',
                        time: '10:00:00',
                        quantity: 2,
                        unitPriceCents: 3333,
                        subtotalCents: 6666,
                        addons: [],
                        hasDeposit: false,
                        depositCents: 6666,
                        gateRemainderCents: 0,
                    )],
                    totalCents: 6666,
                    onlineAmountCents: 6666,
                );
            }
        };
        $this->app->instance(CartPricing::class, $pricing);

        $component = Livewire::test(Purchase::class);

        $this->assertSame(6666, $component->instance()->cartTotalCents());
        $this->assertSame(6666, $component->instance()->cartDepositCents());
        $this->assertSame(1, $component->instance()->cartCount());
        $this->assertGreaterThan(0, $pricing->calls, 'Purchase debe pedir los importes al contrato');
    }

    /**
     * BOOKING → PAYMENTS: el reembolso REST de `Order` pasa por el contrato, no por `Redsys`.
     *
     * Se sustituye el gateway por uno que DENIEGA sin tocar la red: si `Order` siguiera llamando
     * a `Redsys` directamente, la llamada HTTP real fallaría de otro modo y el pedido no
     * registraría este `failure_reason`.
     */
    public function test_booking_asks_payments_for_the_refund_through_the_contract(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'CONTRACT-1',
            'status' => Order::STATUS_PAID,
            'subtotal' => 2400, 'tax' => 0, 'total' => 2400, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => '0000123456',
        ]);

        $gateway = new class implements RefundGateway
        {
            public int $calls = 0;

            public ?int $amountCents = null;

            public function executeRefund(Payment $original, int $amountCents): RefundResult
            {
                $this->calls++;
                $this->amountCents = $amountCents;

                return RefundResult::gatewayDenied('0184', ['fake' => true], 'doble de contrato');
            }
        };
        $this->app->instance(RefundGateway::class, $gateway);

        $result = $order->fresh()->executeFullRefund($admin, PaymentRefund::MODE_REST, alsoCancel: false);

        $this->assertSame(1, $gateway->calls, 'Order no ha pasado por RefundGateway');
        $this->assertSame(2400, $gateway->amountCents);
        $this->assertFalse($result['ok']);
        $this->assertSame('gateway_failed', $result['reason']);
        $this->assertSame('0184', $result['gateway_response_code']);

        // El resultado del doble se persiste tal cual: el contrato transporta lo que se guarda.
        $refund = PaymentRefund::query()->latest('id')->first();
        $this->assertSame(PaymentRefund::STATUS_FAILED, $refund->status);
        $this->assertSame('gateway_denied', $refund->failure_reason);
        $this->assertNull($order->fresh()->refunded_at, 'un fallo de gateway no reembolsa el pedido');
    }

    /**
     * IDENTITY → BOOKING: `CustomerAccountContext` ya no consulta `Order`/`OrderItem`/`TicketType`.
     *
     * El doble no toca la base de datos: si Identity siguiera haciendo sus propias queries, el
     * usuario (que no tiene pedidos) daría un contexto vacío.
     */
    public function test_identity_asks_booking_for_the_reservations_through_the_contract(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->app->instance(CustomerReservations::class, new class implements CustomerReservations
        {
            public function upcomingFor(int $userId): array
            {
                return [
                    new UpcomingReservation('2026-08-20', '10:00–12:00', 'Pack cumpleaños'),
                    new UpcomingReservation('2026-08-22', null, 'Entrada'),
                ];
            }

            public function pendingGuestFormsFor(int $userId): array
            {
                return [new PendingGuestForm(4242, 'Pack cumpleaños')];
            }
        });

        $context = app(CustomerAccountContext::class)->for($user);

        $this->assertSame('Ada', $context['firstName']);
        $this->assertSame(2, $context['upcomingCount']);
        $this->assertSame('Pack cumpleaños', $context['nextReservation']['productName']);
        $this->assertSame('10:00–12:00', $context['nextReservation']['timeWindow']);
        // La ETIQUETA de fecha la compone Identity (idioma activo); el contrato solo lleva `Y-m-d`.
        $this->assertNotSame('', $context['nextReservation']['dateLabel']);

        $this->assertTrue($context['hasPendingForm']);
        $this->assertSame(1, $context['pendingFormsCount']);
        $this->assertSame(
            route('reservation.guests', 4242),
            $context['pendingForms'][0]['url'],
            'la URL se construye en Identity a partir del ID de la reserva'
        );
    }

    /**
     * CONTENT → BOOKING: la comprabilidad del complemento la decide Booking, en los DOS
     * llamantes (la comprobación unitaria del modelo y el resolver en lote de la landing).
     */
    public function test_content_asks_booking_whether_a_complement_is_purchasable(): void
    {
        $zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        // Complemento REAL pero que la regla de Booking jamás daría por comprable (ni vendible,
        // ni activo, ni enganchado a ninguna entrada, ni con precio).
        $complement = TicketType::create([
            'name' => ['es' => 'Complemento'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => false, 'is_active' => false, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $attraction = Attraction::create([
            'zone_id' => $zone->id, 'ticket_type_id' => $complement->id,
            'name' => ['es' => 'Camas'], 'position' => 1, 'is_active' => true,
        ]);

        // Prueba de que el doble manda: con la implementación real esto sería `false`.
        $this->assertFalse(app(PublishableCatalogReader::class)->isComplementPurchasable(
            new ComplementPlacement((int) $complement->id, (int) $zone->id)
        ));

        $catalog = new class implements PublishableCatalog
        {
            public int $singleCalls = 0;

            public int $batchCalls = 0;

            public function isComplementPurchasable(ComplementPlacement $placement): bool
            {
                $this->singleCalls++;

                return true;
            }

            public function purchasableComplements(array $placements): array
            {
                $this->batchCalls++;

                return $placements;
            }
        };
        $this->app->instance(PublishableCatalog::class, $catalog);

        // 1) Unitaria: el modelo de Content delega la REGLA (con la consulta antigua, embebida en
        //    el propio modelo, este complemento daría false — ver aserción de arriba).
        $this->assertTrue($attraction->complementIsPurchasable());
        $this->assertSame(1, $catalog->singleCalls);

        // 2) En lote: el resolver de la landing delega igual, con UNA sola llamada.
        $resolver = new LandingComplementResolver([$attraction]);
        $this->assertTrue($resolver->isPurchasable($attraction));
        $this->assertSame(1, $catalog->batchCalls);
    }

    /** Sin complemento o sin zona no se pregunta a Booking: la guarda de IDs se queda en Content. */
    public function test_content_does_not_ask_booking_when_there_is_nothing_to_ask(): void
    {
        $zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        $unlinked = Attraction::create([
            'zone_id' => $zone->id, 'ticket_type_id' => null,
            'name' => ['es' => 'Sin complemento'], 'position' => 1, 'is_active' => true,
        ]);

        $catalog = new class implements PublishableCatalog
        {
            public int $singleCalls = 0;

            public function isComplementPurchasable(ComplementPlacement $placement): bool
            {
                $this->singleCalls++;

                return true;
            }

            public function purchasableComplements(array $placements): array
            {
                return $placements;
            }
        };
        $this->app->instance(PublishableCatalog::class, $catalog);

        $this->assertFalse($unlinked->complementIsPurchasable());
        $this->assertSame(0, $catalog->singleCalls);
        $this->assertFalse((new LandingComplementResolver([$unlinked]))->isPurchasable($unlinked));
    }

    /** El lote devuelve SOLO los pares preguntados, no el producto cartesiano de la query. */
    public function test_batch_purchasability_never_returns_pairs_nobody_asked_for(): void
    {
        $reader = new PublishableCatalogReader;

        $this->assertSame([], $reader->purchasableComplements([]));
        // Sin datos en la BD ningún par es comprable (y la query no revienta con IDs inexistentes).
        $this->assertSame([], $reader->purchasableComplements([new ComplementPlacement(1, 2)]));
    }

    /**
     * CONTENT → BOOKING: el calendario de operación (paso 7). El doble no toca la base de datos:
     * si `ScheduleDisplay` siguiera consultando `OpeningHour`/`Season`/`SpecialDate` por su
     * cuenta, con la BD vacía no pintaría nada.
     */
    public function test_content_gets_the_operating_calendar_through_the_contract(): void
    {
        $this->app->instance(OperatingCalendar::class, new class implements OperatingCalendar
        {
            public function windowFor(CarbonInterface $date): OperatingWindow
            {
                return new OperatingWindow(true, '10:00:00', '20:00:00');
            }

            public function weeklyOpenings(): array
            {
                // Todos los días con la MISMA ventana → el agrupador debe dar UNA sola fila.
                $rows = [];
                foreach ([0, 1, 2, 3, 4, 5, 6] as $weekday) {
                    $rows[$weekday] = new WeeklyOpening($weekday, false, '11:00:00', '21:00:00');
                }

                return $rows;
            }

            public function activeSeasons(): array
            {
                return [new SeasonWindow(7, 'Verano', '2026-07-01', '2026-08-31', '12:00:00', '23:00:00', true)];
            }

            public function upcomingSpecialDays(int $limit): array
            {
                return [new SpecialDay('2026-12-25', true, null, null, null)];
            }

            public function hasSpecialDay(CarbonInterface $date): bool
            {
                return false;
            }
        });

        $schedule = app(ScheduleDisplay::class);

        $rows = $schedule->weeklyRows();
        $this->assertCount(1, $rows, 'siete días con la misma ventana se agrupan en una fila');
        $this->assertSame('11:00 – 21:00', $rows[0]['time']);

        $seasons = $schedule->seasons();
        $this->assertSame('Verano', $seasons[0]['name']);
        $this->assertSame('12:00 – 23:00', $seasons[0]['time']);
        $this->assertTrue($seasons[0]['is_current'], 'la temporada vigente la decide Booking, no Content');

        $specials = $schedule->upcomingSpecialDates();
        $this->assertTrue($specials[0]['is_closed']);
    }

    /**
     * WEB → BOOKING: el catálogo del flujo de compra sale del contrato, no de una consulta propia
     * del componente Livewire (Fase 3 · paso 1b).
     *
     * El doble no toca la base de datos y devuelve un producto que NO existe: si `Tickets\Purchase`
     * siguiera construyendo el catálogo por su cuenta, la pantalla saldría vacía. Es la prueba de
     * que la web y la API leen el mismo catálogo — sin ella, «fuente única» sería una afirmación
     * del docblock y no un hecho comprobado.
     */
    public function test_the_web_purchase_flow_gets_its_catalog_from_the_contract(): void
    {
        $catalog = new class implements ProductCatalog
        {
            public int $calls = 0;

            public function zones(): array
            {
                return [];
            }

            public function products(?string $type = null): array
            {
                $this->calls++;

                return [new CatalogProduct(
                    id: 4242,
                    type: CatalogProduct::TYPE_ENTRY,
                    name: 'Entrada del contrato',
                    badge: 'Sello',
                    features: ['Ventaja A', 'Ventaja B'],
                    fromPriceCents: 1234,
                    priceVaries: true,
                    depositLabel: null,
                    periodLabel: 'por persona',
                    featured: false,
                    zone: new CatalogZone(7, 'zona-del-contrato', 'Zona del contrato'),
                )];
            }

            public function product(int $id): ?CatalogProductDetail
            {
                return null;
            }
        };
        $this->app->instance(ProductCatalog::class, $catalog);

        Livewire::test(Purchase::class)
            ->assertSee('Entrada del contrato')
            // La vista compone: las ventajas unidas con « · » y el ancla de zona del deep-link.
            ->assertSee('Ventaja A · Ventaja B')
            ->assertSee('zone-zona-del-contrato', false);

        $this->assertSame(1, $catalog->calls, 'Purchase debe pedir el catálogo UNA vez por render');
    }

    /**
     * WEB → BOOKING: la ADMISIÓN de una reserva la decide el dominio, no el componente Livewire ni
     * el controlador de «Mis pedidos» (Fase 3 · paso 2).
     *
     * El doble deniega SIEMPRE, con un motivo que ninguna de las dos superficies podría producir
     * por su cuenta: si alguna siguiera aplicando sus propias comprobaciones —pausa, tope de
     * pendientes, limitador—, este usuario limpio pasaría de largo y crearía su pedido.
     *
     * Cubre las dos superficies a la vez a propósito: el hallazgo que motivó la extracción fue
     * justamente que aplicaban políticas distintas sin que nadie lo hubiera decidido.
     */
    public function test_both_purchase_surfaces_ask_booking_whether_the_reservation_is_admitted(): void
    {
        $admission = new class implements ReservationAdmission
        {
            public int $calls = 0;

            public function mayReserve(int $userId): AdmissionDecision
            {
                $this->calls++;

                return AdmissionDecision::deny(AdmissionDecision::TOO_MANY_PENDING, ['max' => 5]);
            }

            public function admitReservation(int $userId): AdmissionDecision
            {
                $this->calls++;

                return AdmissionDecision::deny(AdmissionDecision::TOO_MANY_PENDING, ['max' => 5]);
            }

            public function admitPaymentRetry(int $userId, string $orderCode): RetryAdmission
            {
                $this->calls++;

                return RetryAdmission::deny(RetryAdmission::RESERVATIONS_PAUSED);
            }
        };
        $this->app->instance(ReservationAdmission::class, $admission);

        $user = User::factory()->create(['email_verified_at' => now()]);

        // 1) El sidebar: ni avanza al paso de pago ni crea nada.
        Livewire::actingAs($user)->test(Purchase::class)
            ->set('step', 8)
            ->set('cart', [['ticket_type_id' => 1, 'date' => '2026-06-08', 'time' => '10:00:00', 'qty' => 1]])
            ->call('confirmReservation')
            ->assertSet('step', 4)
            ->assertHasErrors('cart');

        $this->assertSame(0, Order::query()->count(), 'un veredicto denegado no puede dejar un pedido creado');

        // 2) «Mis pedidos»: el reintento se rechaza con el aviso de pausa que dictó el dominio.
        $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => 'CUALQUIERA']))
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('status', 'order-retry-paused');

        $this->assertSame(2, $admission->calls, 'las dos superficies tienen que preguntar a la política');
    }

    /** CONTENT → BOOKING: el color de zona (paso 7). */
    public function test_content_asks_booking_for_the_zone_colour(): void
    {
        $palette = new class implements ZonePalette
        {
            public int $calls = 0;

            public function colorFor(string $accent): ?string
            {
                $this->calls++;

                return '#123456';
            }
        };
        $this->app->instance(ZonePalette::class, $palette);

        $this->assertSame('#123456', ThemeSettings::zoneColor('jump'));
        $this->assertSame(1, $palette->calls, 'ThemeSettings no debe consultar `zones` por su cuenta');
    }
}
