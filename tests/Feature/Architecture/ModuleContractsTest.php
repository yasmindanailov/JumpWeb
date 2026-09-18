<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Contracts\CartQuote;
use App\Domain\Booking\Contracts\CartQuoteLine;
use App\Domain\Booking\Contracts\CatalogProduct;
use App\Domain\Booking\Contracts\CatalogProductDetail;
use App\Domain\Booking\Contracts\CatalogZone;
use App\Domain\Booking\Contracts\CheckoutLine;
use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Contracts\CheckoutOutcome;
use App\Domain\Booking\Contracts\ComplementPlacement;
use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\GateReservation;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Contracts\GuestFormNotices;
use App\Domain\Booking\Contracts\OfferedDate;
use App\Domain\Booking\Contracts\OfferedTime;
use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\OperatingWindow;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Contracts\PaymentInitiation;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Booking\Contracts\ReservationScope;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Contracts\RetryOutcome;
use App\Domain\Booking\Contracts\SeasonWindow;
use App\Domain\Booking\Contracts\SignedInvitationReplies;
use App\Domain\Booking\Contracts\SpecialDay;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Booking\Contracts\WeeklyOpening;
use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AvailabilityReader;
use App\Domain\Booking\Services\CartPricer;
use App\Domain\Booking\Services\CatalogReader;
use App\Domain\Booking\Services\CheckoutLinesReader;
use App\Domain\Booking\Services\CheckoutOrchestrator;
use App\Domain\Booking\Services\CustomerReservationsReader;
use App\Domain\Booking\Services\GateReservationsReader;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\PartyGuestsReader;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Booking\Services\PublishableCatalogReader;
use App\Domain\Booking\Services\ZonePaletteReader;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\LandingComplementResolver;
use App\Domain\Content\Services\ScheduleDisplay;
use App\Domain\Content\Services\ThemeSettings;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Contracts\RefundResult;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\PaymentInitiator;
use App\Domain\Payments\Services\Redsys;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    /**
     * Instante en que se congela el caso del calendario de operación. Cae DENTRO de la ventana
     * «Verano» que declara su doble (`2026-07-01`…`2026-08-31`) y ANTES del día especial que
     * asevera (`2026-12-25`). Ver el docblock de ese caso: sin congelar, moría el 2026-09-01.
     */
    private const CALENDAR_FIXTURE_NOW = '2026-07-15 12:00:00';

    public function test_every_contract_resolves_to_its_legacy_implementation(): void
    {
        // Los bindings de `BookingServiceProvider`/`PaymentsServiceProvider`. Cuando las
        // implementaciones muden (pasos 5 y 6), aquí solo cambia el nombre esperado.
        $this->assertInstanceOf(Redsys::class, app(RefundGateway::class));
        $this->assertInstanceOf(CustomerReservationsReader::class, app(CustomerReservations::class));
        $this->assertInstanceOf(CheckoutLinesReader::class, app(CheckoutLines::class));
        $this->assertInstanceOf(GateReservationsReader::class, app(GateReservations::class));
        $this->assertInstanceOf(PublishableCatalogReader::class, app(PublishableCatalog::class));
        $this->assertInstanceOf(CatalogReader::class, app(ProductCatalog::class));
        $this->assertInstanceOf(OperatingSchedule::class, app(OperatingCalendar::class));
        $this->assertInstanceOf(ZonePaletteReader::class, app(ZonePalette::class));
        $this->assertInstanceOf(CartPricer::class, app(CartPricing::class));
        $this->assertInstanceOf(AvailabilityReader::class, app(AvailabilityOffer::class));
        $this->assertInstanceOf(PartyGuestsReader::class, app(PartyGuests::class));
        // Los dos puertos del checkout (cierre de Fase 3). `PaymentInitiation` es el caso raro y
        // conviene que salte a la vista: el contrato es de Booking pero lo implementa Payments, así
        // que su bind vive en `PaymentsServiceProvider` y no en el de Booking.
        $this->assertInstanceOf(CheckoutOrchestrator::class, app(ReservationCheckout::class));
        $this->assertInstanceOf(PaymentInitiator::class, app(PaymentInitiation::class));
    }

    /**
     * WEB → BOOKING: los importes del carrito los pone el dominio, no la superficie que los pinta
     * (Fase 3 · paso 4a; re-apuntado en 4.7·2b·3 al retirarse `Tickets\Purchase`).
     *
     * El doble tarifica SIEMPRE lo mismo y devuelve un producto que NO existe en base de datos: un
     * endpoint que volviera a sumar por su cuenta se quedaría sin usar el doble y devolvería los
     * importes reales de una cesta que no existe. La superficie que queda es `POST orders/quote`, de
     * la que cuelga el cajón SPA — su cesta no suma nada, pide el presupuesto—, y es lo que convierte
     * «la web y la app cobran igual» en un hecho comprobado en vez de una promesa.
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

        $this->postJson('/api/v1/orders/quote', ['items' => [[
            'product_id' => 4242, 'date' => '2099-01-01', 'time' => '10:00:00', 'quantity' => 2,
        ]]])
            ->assertOk()
            ->assertJsonPath('total_cents', 6666)
            ->assertJsonPath('online_amount_cents', 6666)
            ->assertJsonPath('lines.0.product_name', 'Línea del contrato');

        $this->assertSame(1, $pricing->calls, 'el presupuesto lo tiene que pedir al contrato');
    }

    /**
     * WEB → BOOKING: los días y las horas que ofrece el cajón los decide el dominio, no la
     * superficie que los pinta (Fase 3 · paso 4b; re-apuntado en 4.7·2b·3).
     *
     * El doble ofrece un día y una hora que NO existen en base de datos —no hay ni franjas ni
     * producto—: un endpoint que consultase `slots` por su cuenta devolvería listas VACÍAS y estas
     * aserciones caerían. Cubre además el número que de verdad importa: el máximo elegible tiene que
     * ser el que da el contrato, no uno recalculado aparte, porque `available` y `max_quantity` no
     * son el mismo número en un pack.
     */
    public function test_the_web_offer_of_days_and_times_comes_from_the_contract(): void
    {
        $offer = new class implements AvailabilityOffer
        {
            public int $calls = 0;

            public function dates(int $productId): array
            {
                $this->calls++;

                return [new OfferedDate('2099-01-01', 4242, 'special')];
            }

            public function times(int $productId, string $date, array $cart = []): array
            {
                return [new OfferedTime('07:30:00', 99, 7, true)];
            }

            public function maxQuantity(int $productId, string $date, string $time, array $cart = []): int
            {
                return 7;
            }
        };
        $this->app->instance(AvailabilityOffer::class, $offer);

        $product = $this->sellableProduct();

        $this->getJson('/api/v1/availability/'.$product->id.'/dates')
            ->assertOk()
            ->assertJsonPath('data.0.date', '2099-01-01')
            ->assertJsonPath('data.0.price_cents', 4242)
            ->assertJsonPath('data.0.rate_key', 'special');

        $this->postJson('/api/v1/availability/'.$product->id.'/times', ['date' => '2099-01-01'])
            ->assertOk()
            ->assertJsonPath('data.0.time', '07:30:00')
            // ⚠️ Los DOS números, que no son el mismo: `available` es para mostrar y `max_quantity`
            // para acotar el selector. Comprobar solo uno dejaría pasar que el endpoint los cruzase.
            ->assertJsonPath('data.0.available', 99)
            ->assertJsonPath('data.0.max_quantity', 7);

        $this->assertSame(1, $offer->calls, 'los días los tiene que pedir al contrato');
    }

    /** Producto vendible mínimo, para que el componente tenga algo que seleccionar. */
    /**
     * Producto vendible mínimo… con su tarifa.
     *
     * ⚠️ **La `RateType` no es adorno y costó un diagnóstico**: sin ninguna tarifa en base de datos,
     * `RateResolver::for()` lanza `ModelNotFoundException` y el catálogo no puede describir el
     * producto, así que `GET availability/{id}/dates` responde **404** —el guard del controlador
     * pregunta al catálogo—. El componente Livewire no lo notaba porque su calendario solo consulta
     * `AvailabilityOffer`, que en este caso está doblado. Es un ejemplo exacto de la lección de la
     * fase: dos superficies del mismo dato no piden lo mismo, así que un fixture que basta para una
     * puede no bastar para la otra.
     */
    private function sellableProduct(): TicketType
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);

        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ]);

        return TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
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

            public function guestFormNoticesFor(int $userId): GuestFormNotices
            {
                return new GuestFormNotices([new PendingGuestForm(4242, 'Pack cumpleaños')]);
            }

            /**
             * ⚠️ Mismo criterio que `pageFor`: este doble es del contexto de cuenta, no de la
             * puerta de la supresión (T5 · D8) — llegar hasta aquí sería un error, y devolver
             * `false` lo silenciaría (una cuenta se borraría en un test sin que nadie lo pidiera).
             */
            public function hasUpcomingFor(int $userId): bool
            {
                throw new \LogicException('este doble no sirve la puerta de la supresión: es del contexto de cuenta');
            }

            /**
             * ⚠️ **Este doble NO se usa para el historial, y lanzar es lo correcto.** El contrato
             * ganó `pageFor()` el 2026-08-23 (historial por reserva) y este caso solo ejerce el
             * contexto de cuenta. Devolver un paginador vacío haría que un futuro consumidor que
             * llegara hasta aquí por error viera «no tienes reservas» en vez de un fallo — que es
             * exactamente la clase de silencio que este fichero existe para impedir.
             */
            public function pageFor(int $userId, ReservationScope $scope, int $perPage, int $page): LengthAwarePaginator
            {
                throw new \LogicException('este doble no sirve el historial: es del contexto de cuenta');
            }
        });

        $context = app(CustomerAccountContext::class)->for($user);

        $this->assertSame('Ada', $context['firstName']);
        $this->assertSame(2, $context['upcomingCount']);
        // ⚠️ **La próxima reserva sale como el DTO del contrato, no como un array formateado**
        // (2026-08-23): Identity ya no compone la etiqueta de día —lo hace quien la pinta, con
        // `DisplayTime::dayLabel`— así que lo que se asevera aquí es que el DTO llega ENTERO y sin
        // recodificar. Aseverar la etiqueta desde aquí volvería a mezclar contrato y presentación,
        // que es lo que este caso existe para separar.
        $this->assertInstanceOf(UpcomingReservation::class, $context['nextReservation']);
        $this->assertSame('Pack cumpleaños', $context['nextReservation']->productName);
        $this->assertSame('10:00–12:00', $context['nextReservation']->timeWindow);
        $this->assertSame('2026-08-20', $context['nextReservation']->date, 'el contrato lleva `Y-m-d`, sin formatear');

        $this->assertTrue($context['hasPendingForm']);
        $this->assertSame(1, $context['pendingFormsCount']);
        $this->assertSame(
            route('reservation.guests', 4242),
            $context['pendingForms'][0]['url'],
            'la URL se construye en Identity a partir del ID de la reserva'
        );
    }

    /**
     * IDENTITY → BOOKING: **los «sí» de la invitación digital los sabe Booking**, y el suelo de plazas
     * los pide por el contrato en vez de ir a mirar las respuestas (T4·4, `DECISIONES #576`).
     *
     * Es la otra mitad de la misma frontera que `#401` y `#444` dibujaron: las respuestas viven en
     * Booking, las firmas en Identity, y **Booking no puede mirar a Identity** — por eso el contrato
     * publica IDS y la resta la hace `GuardianPlaces`, el único sitio donde las dos mitades coexisten.
     *
     * ⚠️ El doble devuelve tres respuestas que **no existen en base de datos**: si `GuardianPlaces`
     * consultara `InvitationReply` por su cuenta, el doble quedaría sin usar y el suelo saldría `0`.
     * Ésa es toda la prueba — y es lo que `InvitationPlacesTest`, que trabaja con datos reales, no
     * puede distinguir.
     */
    public function test_identity_asks_booking_for_the_committed_guests_through_the_contract(): void
    {
        $this->app->instance(PartyGuests::class, new class implements PartyGuests
        {
            public function committedReplyIdsIn(int $reservationId): array
            {
                return [90001, 90002, 90003];
            }

            /**
             * ⚠️ Este caso ejerce el SUELO, no la excepción del firmador (`#576`). Devolver `false`
             * silenciaría a un consumidor que llegara aquí por error: le diría «esa respuesta no es
             * de esta reserva» —y con ello le cerraría la puerta a un padre que sí dijo «sí»— en vez
             * de fallar. Justo el silencio que este fichero existe para impedir.
             */
            public function isCommittedReply(int $replyId, int $reservationId): bool
            {
                throw new \LogicException('este doble sirve el suelo de plazas, no la excepción del firmador');
            }
        });

        // Por el CONTRATO, no por el implementador: es como pregunta Booking desde `GuestCountPolicy`.
        $this->assertSame(3, app(ReservationPlacesTaken::class)->takenIn(4242));
    }

    /**
     * BOOKING → IDENTITY: **si una respuesta ya tiene justificante lo sabe Identity**, y el recibo de la
     * invitación lo pregunta por el contrato en vez de mirar las firmas (T5·5, `DECISIONES #704`).
     *
     * Es la flecha contraria a la de aquí arriba y la misma frontera: las respuestas son de Booking, las
     * firmas de Identity y **Booking no puede mirar a Identity**. Por eso el contrato lo declara Booking,
     * lo implementa `GuardianPlaces` y el binding vive en el composition root.
     *
     * ⚠️ El doble dice que SÍ de una respuesta que **no tiene ni una firma en base de datos**: si
     * `PartyInvitations` fuera a mirar por su cuenta, el doble quedaría sin usar y esto saldría `false`.
     * Ésa es toda la prueba — y es justo lo que `InvitationSigningFlowTest`, que trabaja con datos
     * reales, no puede distinguir (la trampa que pagó `#576`).
     */
    public function test_booking_asks_identity_whether_a_reply_is_already_signed(): void
    {
        $this->app->instance(SignedInvitationReplies::class, new class implements SignedInvitationReplies
        {
            public function isReplySigned(int $replyId): bool
            {
                return $replyId === 90001;
            }
        });

        $reply = (new InvitationReply)->forceFill(['id' => 90001]);
        $otra = (new InvitationReply)->forceFill(['id' => 90002]);

        $this->assertTrue(app(PartyInvitations::class)->waiverSignedFor($reply));
        $this->assertFalse(app(PartyInvitations::class)->waiverSignedFor($otra));
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
     *
     * ⚠️⚠️ **El reloj se congela, y no es una precaución genérica: sin esto este test se ponía en
     * ROJO el 2026-09-01** (`DECISIONES #162`). Su doble declara una temporada «Verano» del
     * `2026-07-01` al `2026-08-31`, y `ScheduleDisplay::seasons()` **descarta las temporadas ya
     * terminadas** (`endsOn >= hoy`) — que es correcto: filtrar lo que ya pasó es presentación, y
     * `is_current` lo sigue decidiendo Booking. Pasado agosto, `$seasons` salía vacío y el caso
     * moría con «Undefined array key 0». Medido con `scripts/audit-clock.sh`: verde el 31 de agosto,
     * rojo el 1 de septiembre.
     * ▶ La fecha elegida cae dentro de la ventana del fixture **y antes del 25-dic**, que es el día
     * especial que asevera el final del caso. Cambiar una obliga a mirar la otra.
     */
    public function test_content_gets_the_operating_calendar_through_the_contract(): void
    {
        $this->travelTo(Carbon::parse(self::CALENDAR_FIXTURE_NOW, 'UTC'));

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
     * de la superficie que lo pinta (Fase 3 · paso 1b; re-apuntado en 4.7·2b·3).
     *
     * El doble no toca la base de datos y devuelve un producto que NO existe: un endpoint que
     * listara `ticket_types` por su cuenta devolvería lista vacía. Es la prueba de que el catálogo
     * tiene fuente única — sin ella, «fuente única» sería una afirmación del docblock y no un hecho
     * comprobado.
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
                    gifts: ['Regalo A'],
                    fromPriceCents: 1234,
                    priceVaries: true,
                    depositLabel: null,
                    periodLabel: 'por persona',
                    featured: false,
                    icon: 'ticket',
                    zone: new CatalogZone(7, 'zona-del-contrato', 'Zona del contrato'),
                )];
            }

            public function product(int $id): ?CatalogProductDetail
            {
                return null;
            }
        };
        $this->app->instance(ProductCatalog::class, $catalog);

        $this->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', 4242)
            ->assertJsonPath('data.0.name', 'Entrada del contrato')
            ->assertJsonPath('data.0.zone.slug', 'zona-del-contrato');

        $this->assertSame(1, $catalog->calls, 'el catálogo se pide al contrato, una vez por petición');
    }

    /**
     * WEB → BOOKING: la ADMISIÓN de una reserva la decide el dominio, no el cajón ni el controlador
     * de «Mis pedidos» (Fase 3 · paso 2; re-apuntado en 4.7·2b·3).
     *
     * El doble deniega SIEMPRE, con un motivo que ninguna de las dos superficies podría producir
     * por su cuenta: si alguna siguiera aplicando sus propias comprobaciones —pausa, tope de
     * pendientes, limitador—, este usuario limpio pasaría de largo y crearía su pedido.
     *
     * Cubre las superficies VIVAS a la vez a propósito: el hallazgo que motivó la extracción fue
     * justamente que dos de ellas aplicaban políticas distintas sin que nadie lo hubiera decidido.
     * Eran tres; retirado el componente Livewire quedan «Mis pedidos» y los dos endpoints de API,
     * que son por donde pasa hoy el cajón.
     */
    public function test_all_purchase_surfaces_ask_booking_whether_the_reservation_is_admitted(): void
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

        // ⚠️ **La puerta WEB del reintento («Mis pedidos») se retiró en la tanda 3** con la página
        //    que la servía: `RetryPaymentController` no tenía ya ningún consumidor, porque el cajón
        //    reintenta por la API. El contador de abajo baja con ella, MEDIDO.
        //
        // 1) y 2) La API (Fase 3 · paso 4c): mismo veredicto, misma consecuencia. Se comprueba aquí
        //    y no solo en su test de endpoint porque lo que se vigila es que pregunte al CONTRATO —
        //    si volviera a comprobar los límites por su cuenta, este doble se quedaría sin usar y el
        //    usuario limpio pasaría de largo.
        $this->actingAs($user)
            ->postJson('/api/v1/orders', ['items' => [[
                'product_id' => 1, 'date' => '2026-06-08', 'time' => '10:00:00', 'quantity' => 1,
            ]]])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'too_many_pending_orders');

        $this->actingAs($user)
            ->postJson('/api/v1/orders/CUALQUIERA/payment')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'reservations_paused');

        $this->assertSame(0, Order::query()->count(), 'un veredicto denegado no puede dejar un pedido creado');
        $this->assertSame(2, $admission->calls, 'todas las superficies tienen que preguntar a la política');
    }

    /**
     * ENTREGA → BOOKING: la SECUENCIA de la compra la aplica el dominio, y **todas** las puertas de
     * entrada pasan por el mismo contrato (cierre de Fase 3; re-apuntado en 4.7·2b·3).
     *
     * ⚠️ Eran CINCO puertas, luego TRES y hoy son **DOS**: las dos del componente Livewire —comprar
     * y reintentar— se fueron con él en 4.7·2b·3, y la del reintento desde «Mis pedidos» con la
     * retirada de la página en la tanda 3 (`DECISIONES #120(u)`). El cajón SPA no añade puertas
     * nuevas porque entra por las de API que ya se contaban aquí. Los contadores están MEDIDOS.
     * ▶ Que este número solo BAJE es la señal de que la consolidación va en la dirección correcta:
     * cada superficie retirada es una copia menos del orden que podía dejarse un paso.
     *
     * El doble deniega siempre y **no crea nada**: si alguna superficie conservara su propia
     * secuencia —admitir, crear el pedido, abrir el cobro—, este usuario limpio con una cesta
     * plausible pasaría de largo y dejaría un pedido en base de datos. Que llamen TODAS se cuenta,
     * porque el hallazgo que motivó todo esto fue justamente que había cinco copias del orden y
     * bastaba con que una se dejara un paso.
     *
     * No sustituye a `test_all_purchase_surfaces_ask_booking_whether_the_reservation_is_admitted`:
     * aquel prueba que nadie reimplementa la POLÍTICA, este que nadie reimplementa el ORDEN.
     */
    public function test_all_purchase_surfaces_ask_booking_for_the_checkout_sequence(): void
    {
        $checkout = new class implements ReservationCheckout
        {
            public int $starts = 0;

            public int $retries = 0;

            public function start(User $user, array $cart, string $source): CheckoutOutcome
            {
                $this->starts++;

                return CheckoutOutcome::deny(
                    AdmissionDecision::deny(AdmissionDecision::TOO_MANY_PENDING, ['max' => 5])
                );
            }

            public function retry(User $user, string $orderCode, string $source): RetryOutcome
            {
                $this->retries++;

                return RetryOutcome::deny(RetryAdmission::deny(RetryAdmission::RESERVATIONS_PAUSED));
            }
        };
        $this->app->instance(ReservationCheckout::class, $checkout);

        $user = User::factory()->create(['email_verified_at' => now()]);

        // 1) y 2) La API: crear y reintentar. La puerta web del reintento se retiró con la página.
        $this->actingAs($user)
            ->postJson('/api/v1/orders', ['items' => [[
                'product_id' => 1, 'date' => '2026-06-08', 'time' => '10:00:00', 'quantity' => 1,
            ]]])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'too_many_pending_orders');

        $this->actingAs($user)
            ->postJson('/api/v1/orders/CUALQUIERA/payment')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'reservations_paused');

        $this->assertSame(0, Order::query()->count(), 'ninguna superficie puede crear pedidos por su cuenta');
        $this->assertSame(1, $checkout->starts, 'la superficie que compra tiene que pedir la secuencia');
        $this->assertSame(1, $checkout->retries, 'las que reintentan tienen que pedir la secuencia');
    }

    /**
     * IDENTITY → BOOKING: la asignación de entradas a menores (Fase 6 · tanda 4) ata cada asignación a
     * SU ítem por lo que `CheckoutLines` promete —las líneas principales en el orden de la cesta—, no
     * consultando `OrderItem` por su cuenta. El doble devuelve un orden INVERTIDO al de los ids: si el
     * asignador se guiara por la base de datos y no por el contrato, la asignación caería en la otra
     * línea y este caso lo vería.
     */
    public function test_identity_asks_booking_for_the_checkout_lines_through_the_contract(): void
    {
        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));

        $holder = User::factory()->create();
        $lucas = app(DependentRegistry::class)->add($holder, 'Lucas', '2017-03-12');
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-CONTRATO', 'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'expires_at' => now()->addHour(),
        ]);
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $first = $order->items()->create(['ticket_type_id' => $entry->id, 'quantity' => 2, 'unit_price' => 500, 'seats' => 2]);
        $second = $order->items()->create(['ticket_type_id' => $entry->id, 'quantity' => 2, 'unit_price' => 500, 'seats' => 2]);

        $lines = new class($first->id, $second->id) implements CheckoutLines
        {
            public int $calls = 0;

            public function __construct(private int $firstId, private int $secondId) {}

            public function forOrder(int $orderId, int $userId): array
            {
                $this->calls++;

                // Al revés que los ids: el índice 0 de la cesta es el SEGUNDO ítem creado.
                return [
                    new CheckoutLine(index: 0, orderItemId: $this->secondId, quantity: 2, isEntry: true, date: '2026-09-05'),
                    new CheckoutLine(index: 1, orderItemId: $this->firstId, quantity: 2, isEntry: true, date: '2026-09-05'),
                ];
            }
        };
        $this->app->instance(CheckoutLines::class, $lines);

        $outcome = app(DependentAssigner::class)->assign($holder, $order->id, [
            ['index' => 0, 'product_id' => $entry->id, 'date' => '2026-09-05', 'quantity' => 2, 'dependent_ids' => [$lucas->id]],
            ['index' => 1, 'product_id' => $entry->id, 'date' => '2026-09-05', 'quantity' => 2, 'dependent_ids' => []],
        ]);

        $this->assertSame(1, $lines->calls, 'el asignador tiene que pedirle las líneas a Booking por el contrato');
        $this->assertSame(1, $outcome->assigned);
        $this->assertDatabaseHas('dependent_assignments', ['order_item_id' => $second->id, 'dependent_id' => $lucas->id]);
        $this->assertDatabaseMissing('dependent_assignments', ['order_item_id' => $first->id]);
    }

    /**
     * IDENTITY → BOOKING: la FICHA DE PUERTA pide las reservas y su dinero por el contrato (Fase 6 ·
     * subsistema A, `identidad-qr-puerta.md` §9.2 A·3). El doble devuelve un pedido que NO existe en
     * base de datos: si `GateProfile` volviera a consultar `OrderItem` por su cuenta, el doble se
     * quedaría sin usar y la ficha saldría vacía.
     */
    public function test_identity_asks_booking_for_the_gate_reservations_through_the_contract(): void
    {
        $holder = User::factory()->create();
        $reservations = new class implements GateReservations
        {
            public int $calls = 0;

            public array $args = [];

            public function forHolder(int $userId, string $fromDate, string $toDate): array
            {
                $this->calls++;
                $this->args = [$userId, $fromDate, $toDate];

                return [new GateReservation(
                    orderId: 1, orderCode: 'R-DOBLE', orderItemId: 99, date: '2026-07-15', timeWindow: '10:00–11:00',
                    productName: 'Doble', isEntry: true, quantity: 2, addons: ['1 × Calcetines'], paidCents: 1234,
                    balanceKind: 'pay_at_park', balanceCents: 56, chargeMethod: 'redsys', paidAt: null, createdAt: '2026-07-01T10:00:00+00:00',
                )];
            }
        };
        $this->app->instance(GateReservations::class, $reservations);

        $profile = app(GateProfile::class)->for($holder, CarbonImmutable::parse('2026-07-15'), 2);

        $this->assertSame(1, $reservations->calls, 'la ficha tiene que pedirle las reservas a Booking por el contrato');
        $this->assertSame([$holder->id, '2026-07-13', '2026-07-17'], $reservations->args, 'la ventana ±N la calcula Identity y viaja por el contrato');
        $this->assertSame('R-DOBLE', $profile->today_reservations[0]['order_code']);
        $this->assertSame(1234, $profile->today_reservations[0]['paid_cents']);
        $this->assertSame('pay_at_park', $profile->today_reservations[0]['balance_kind'], 'la CLASE del saldo viaja por el contrato: la puerta pinta por clase');
        $this->assertSame(56, $profile->today_reservations[0]['balance_cents']);
        $this->assertSame([], $profile->today_reservations[0]['minors'], 'un ítem que no existe no tiene menores asignados');
        $this->assertSame([], $profile->window);
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
