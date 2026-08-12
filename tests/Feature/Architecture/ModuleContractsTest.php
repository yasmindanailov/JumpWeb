<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Contracts\ComplementPlacement;
use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\LandingComplementResolver;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Contracts\RefundResult;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\CustomerReservationsReader;
use App\Support\PublishableCatalogReader;
use App\Support\Redsys;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
