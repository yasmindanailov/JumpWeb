<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Jobs\SendConversionToPlatforms;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Contracts\ConsentLedger;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Conversion;
use App\Domain\Platform\Services\Analytics\ConversionSender;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **La compra comunicada a los anunciantes desde el servidor** (`specs/analitica.md` §4.3, T3b·2): el job
 * relee el consentimiento VIVO del visitante (su última decisión) y solo con `marketing` manda la compra a
 * Meta y a TikTok con el código del pedido como id, el importe pagado, las cookies de los píxeles del sello y
 * el correo y el teléfono HASHEADOS; sin token, sin píxel, sin visitante o sin decisión viva, nada. ⚠️ Todo con
 * `Http::fake`: aquí no se habla con nadie.
 */
class SendConversionToPlatformsTest extends TestCase
{
    use RefreshDatabase;

    private const VISITOR = '01HZX8K4N2P7Q9R3S5T6V8W0YA';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => Pixels::KEY_META_PIXEL_ID], ['value' => '1234567890123456', 'group' => 'marketing']);
        Setting::updateOrCreate(['key' => Pixels::KEY_TIKTOK_PIXEL_ID], ['value' => 'C9ABCDEFGHIJKLMNOPQR', 'group' => 'marketing']);
        Setting::flushMemo();
        config(['services.meta.access_token' => 'meta-token', 'services.meta.api_version' => 'v21.0', 'services.tiktok.access_token' => 'tiktok-token']);
    }

    /** Un pedido pagado con su sello: visitante, cookies de los píxeles y el clic de TikTok. */
    private function paidOrder(array $attribution = [], ?User $user = null, string $code = 'JJ-CONV'): Order
    {
        $user ??= User::factory()->create(['phone' => '600111222']);

        $order = Order::create([
            'user_id' => $user->id,
            'code' => $code,
            'status' => Order::STATUS_PAID,
            'paid_at' => now()->subMinute(),
            'total' => 4800,
            'currency' => 'EUR',
            'expires_at' => now()->addMinutes(30),
        ]);

        // El sello no es asignable en masa (se escribe en el insert desde el contexto): el fixture lo pone
        // en silencio, como si la compra hubiera venido de esa navegación.
        return $this->sealed($order, $attribution);
    }

    /** @param  array<string, mixed>  $attribution */
    private function sealed(Order $order, array $attribution = []): Order
    {
        $order->forceFill(['attribution' => array_filter($attribution + [
            'visitor_id' => self::VISITOR,
            'browser_ids' => ['fbp' => 'fb.1.1727170000000.1234567890', 'fbc' => 'fb.1.1727170000000.IwAR0abc'],
            'click_ids' => ['ttclid' => 'E.C.P.abc123'],
        ], static fn ($v): bool => $v !== null)])->saveQuietly();

        return $order->fresh();
    }

    private function decision(bool $marketing, ?string $visitor = self::VISITOR, ?string $at = null): CookieConsentLog
    {
        return CookieConsentLog::create([
            'visitor_id' => $visitor,
            'categories' => ['maps' => false, 'social' => false, 'analytics' => false, 'marketing' => $marketing],
            'version' => CookieConsent::POLICY_VERSION,
            'accepted_at' => $at ?? now(),
        ]);
    }

    public function test_there_is_no_job_without_a_pixel_with_a_server_api_or_without_a_visitor_in_the_seal(): void
    {
        $order = $this->paidOrder();
        $this->assertInstanceOf(SendConversionToPlatforms::class, SendConversionToPlatforms::forOrder($order, 2550));

        $this->assertNull(SendConversionToPlatforms::forOrder($this->paidOrder(['visitor_id' => null], code: 'JJ-SIN'), 2550), 'sin visitante no hay decisión viva que releer');

        Setting::where('key', Pixels::KEY_META_PIXEL_ID)->delete();
        Setting::where('key', Pixels::KEY_TIKTOK_PIXEL_ID)->delete();
        Setting::updateOrCreate(['key' => Pixels::KEY_GOOGLE_ADS_ID], ['value' => 'AW-123456789', 'group' => 'marketing']);
        Setting::flushMemo();
        $this->assertNull(SendConversionToPlatforms::forOrder($order, 2550), 'Google Ads no tiene API de servidor aquí');
    }

    public function test_with_live_marketing_consent_the_purchase_reaches_meta_and_tiktok_hashed_and_deduplicated(): void
    {
        Http::fake();
        $this->decision(marketing: true);
        $order = $this->paidOrder(user: User::factory()->create(['email' => 'Ada@Example.test ', 'phone' => '600111222']));

        (new SendConversionToPlatforms((int) $order->id, 2550))->handle(app(ConsentLedger::class), app(ConversionSender::class));

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'graph.facebook.com/v21.0/1234567890123456/events')) {
                return false;
            }
            $event = $request['data'][0];

            return $request['access_token'] === 'meta-token'
                && $event['event_name'] === 'Purchase'
                && $event['event_id'] === 'JJ-CONV'
                && $event['action_source'] === 'website'
                && $event['custom_data'] === ['currency' => 'EUR', 'value' => 25.5]
                && $event['user_data']['em'] === [hash('sha256', 'ada@example.test')]
                && $event['user_data']['ph'] === [hash('sha256', '34600111222')]
                && $event['user_data']['fbp'] === 'fb.1.1727170000000.1234567890'
                && $event['user_data']['fbc'] === 'fb.1.1727170000000.IwAR0abc';
        });
        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== ConversionSender::TIKTOK_EVENTS) {
                return false;
            }
            $event = $request['data'][0];

            return $request->hasHeader('Access-Token', 'tiktok-token')
                && $request['event_source_id'] === 'C9ABCDEFGHIJKLMNOPQR'
                && $event['event'] === 'CompletePayment'
                && $event['event_id'] === 'JJ-CONV'
                && $event['user']['email'] === hash('sha256', 'ada@example.test')
                && $event['user']['phone'] === hash('sha256', '34600111222')
                && $event['user']['ttclid'] === 'E.C.P.abc123'
                && $event['properties'] === ['currency' => 'EUR', 'value' => 25.5, 'content_type' => 'product'];
        });

        // Ni el correo ni el teléfono en claro salen de aquí.
        Http::assertSent(fn (Request $request): bool => ! str_contains($request->body(), 'example.test') && ! str_contains($request->body(), '600111222'));
    }

    /** La ÚLTIMA decisión manda: retirar después de comprar deja la compra sin comunicar, y al revés. */
    public function test_the_latest_decision_of_the_visitor_wins(): void
    {
        Http::fake();
        $order = $this->paidOrder();
        $job = new SendConversionToPlatforms((int) $order->id, 2550);
        $ledger = app(ConsentLedger::class);
        $sender = app(ConversionSender::class);

        $job->handle($ledger, $sender);
        Http::assertNothingSent();   // sin ninguna decisión viva

        $this->decision(marketing: true, at: now()->subHour());
        $this->decision(marketing: false, at: now()->subMinutes(5));
        $job->handle($ledger, $sender);
        Http::assertNothingSent();   // retiró después

        $this->decision(marketing: true, at: now()->subMinute());
        $job->handle($ledger, $sender);
        Http::assertSentCount(2);   // volvió a dar
    }

    public function test_another_visitors_decision_does_not_count(): void
    {
        Http::fake();
        $this->decision(marketing: true, visitor: Visitor::mint());
        $order = $this->paidOrder();

        (new SendConversionToPlatforms((int) $order->id, 2550))->handle(app(ConsentLedger::class), app(ConversionSender::class));

        Http::assertNothingSent();
    }

    public function test_without_a_token_the_platform_is_skipped_and_logged_and_the_other_still_gets_it(): void
    {
        Http::fake();
        Log::spy();
        config(['services.meta.access_token' => '']);
        $this->decision(marketing: true);
        $order = $this->paidOrder();

        $sent = app(ConversionSender::class)->send(new Conversion('JJ-CONV', time(), 25.5, 'EUR', null, null));

        $this->assertSame([Pixels::TIKTOK], $sent);
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'analytics.conversion_skipped' && $context['platform'] === 'meta')->once();
    }

    public function test_an_anonymized_holder_sends_the_sale_without_a_person(): void
    {
        Http::fake();
        $this->decision(marketing: true);
        $user = User::factory()->create(['email' => 'deleted_9@'.User::ANONYMIZED_EMAIL_DOMAIN, 'phone' => null]);
        $order = $this->paidOrder(user: $user);

        (new SendConversionToPlatforms((int) $order->id, 2550))->handle(app(ConsentLedger::class), app(ConversionSender::class));

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'facebook') && ! isset($request['data'][0]['user_data']['em']) && ! isset($request['data'][0]['user_data']['ph']) && $request['data'][0]['user_data']['fbp'] === 'fb.1.1727170000000.1234567890');
    }

    public function test_the_hashes_are_of_the_normalized_email_and_phone(): void
    {
        $this->assertSame('ada@example.test', Conversion::normalizeEmail(' Ada@Example.TEST '));
        $this->assertNull(Conversion::normalizeEmail('sin-arroba'));
        $this->assertSame('34600111222', Conversion::normalizePhone('600 111 222'));
        $this->assertSame('34600111222', Conversion::normalizePhone('+34 600 111 222'));
        $this->assertSame('33612345678', Conversion::normalizePhone('33612345678'), 'un número ya prefijado se respeta');
        $this->assertSame('33612345678', Conversion::normalizePhone('0033 612 345 678'), 'el «00» internacional no es parte del número');
        $this->assertNull(Conversion::normalizePhone('12345'));
        $this->assertSame(hash('sha256', 'ada@example.test'), Conversion::hash('ada@example.test'));
        $this->assertNull(Conversion::hash(null));
    }

    /**
     * El observador encola el job en la transición a PAGADO de una compra de verdad (con tickets), y no en una
     * incidencia de cobro tardío sin tickets. Nunca tumba el cobro.
     */
    public function test_the_paid_transition_of_a_real_purchase_queues_the_job_and_an_incident_does_not(): void
    {
        Bus::fake([SendConversionToPlatforms::class]);

        $order = $this->sealed(Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-PAGO', 'status' => Order::STATUS_PENDING, 'total' => 4000,
            'expires_at' => now()->addMinutes(30),
        ]));
        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'redsys',
            'amount' => 1000, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'gateway_order' => Str::random(10),
        ]);
        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $slot = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(7)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50]);
        $type = TicketType::create(['name' => ['es' => 'Jump'], 'zone_id' => $zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        Ticket::create(['order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'qr_token' => Str::random(32), 'status' => Ticket::STATUS_PURCHASED]);

        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        Bus::assertDispatched(SendConversionToPlatforms::class, fn (SendConversionToPlatforms $job): bool => $job->orderId === (int) $order->id && $job->paidCents === 1000);

        // Un cobro tardío SIN tickets es la incidencia de `PAY-02`, no una compra: nada que comunicar.
        $incident = $this->sealed(Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-TARDE', 'status' => Order::STATUS_PENDING, 'total' => 4000,
            'expires_at' => now()->addMinutes(30),
        ]));
        $incident->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        Bus::assertDispatchedTimes(SendConversionToPlatforms::class, 1);
    }
}
