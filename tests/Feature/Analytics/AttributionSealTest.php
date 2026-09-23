<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * **EL SELLO DE ORIGEN DEL PEDIDO** (`docs/specs/analitica.md` §4.1, §6; `DECISIONES #678`).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene: que el pedido NAZCA sellado —un solo INSERT, ningún
 * UPDATE después, ninguna consulta bajo el lock de aforo cuando el contexto vino resuelto— · que el
 * primer toque no directo gane al último (el anuncio de hace diez días, no el «directo» de hoy) · que los
 * identificadores viajen SOLO con `analytics` consentido · que el pedido manual lleve la fuente del
 * OPERADOR y no su navegador · y que un contexto roto nunca impida un pedido.
 */
class AttributionSealTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $code = 'JJ-SELLO'): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code,
            'status' => Order::STATUS_PENDING,
            'total' => 1500,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /** @param  array<string, mixed>  $atributos */
    private function sesion(string $visitor, array $atributos): AnalyticsSession
    {
        return AnalyticsSession::create([
            'visitor_id' => $visitor,
            'started_at' => now(),
            'last_seen_at' => now(),
            ...$atributos,
        ]);
    }

    /** Un contexto resuelto desde una petición que trae la cookie del visitante, como hace `POST /orders`. */
    private function contextoWeb(string $visitor): AttributionContext
    {
        $request = Request::create('/api/v1/orders', 'POST');
        $request->cookies->set(Visitor::COOKIE, $visitor);

        $context = app(AttributionContext::class);
        $context->resolveFrom($request);
        $context->resolve();

        return $context;
    }

    public function test_by_default_an_order_is_sealed_as_system(): void
    {
        $order = $this->order();

        $this->assertSame('system', $order->attribution_channel);
        $this->assertNull($order->attribution_source);
        $this->assertSame([], $order->attribution);
    }

    /** El pedido manual NO hereda la sesión del operador: lleva la fuente que él eligió (spec §7.1, dinero-4). */
    public function test_a_panel_order_carries_the_operators_source_and_not_his_browser(): void
    {
        // El operador navegó por la web con su propia cookie y una campaña de Google…
        $visitor = Visitor::mint();
        $this->sesion($visitor, ['utm_source' => 'google', 'utm_medium' => 'cpc']);
        $this->contextoWeb($visitor);
        // …y desde el panel teclea un pedido que entró por TELÉFONO.
        app(AttributionContext::class)->forPanel('phone', 7);

        $order = $this->order();

        $this->assertSame('panel', $order->attribution_channel);
        $this->assertSame('phone', $order->attribution_source);
        $this->assertSame('offline', $order->attribution_medium);
        $this->assertSame(['operator_id' => 7], $order->attribution);
    }

    /** Y solo una fuente DEL PANEL (T1d): un valor tecleado en el estado del asistente no llega al sello. */
    public function test_the_panel_context_refuses_a_source_that_is_not_of_the_panel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AttributionContext::class)->forPanel('fax', 7);
    }

    /**
     * ❗ **Primer toque no directo, a 30 días** (spec §7.1, medicion-3): la cesta vive en `localStorage`,
     * así que quien vuelve días después llega en una sesión «directa». El sello guarda el anuncio.
     */
    public function test_a_web_order_is_sealed_with_the_first_non_direct_touch(): void
    {
        $visitor = Visitor::mint();
        $this->sesion($visitor, ['started_at' => now()->subDays(10), 'last_seen_at' => now()->subDays(10), 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'verano']);
        $hoy = $this->sesion($visitor, ['consent' => ['analytics' => true, 'marketing' => true], 'click_ids' => ['fbclid' => 'IwAR0'], 'device' => 'mobile', 'locale' => 'es', 'entry_route' => '/entradas']);
        $this->contextoWeb($visitor);

        $order = $this->order();

        $this->assertSame('web', $order->attribution_channel);
        $this->assertSame('google', $order->attribution_source);
        $this->assertSame('cpc', $order->attribution_medium);
        $this->assertSame('verano', $order->attribution_campaign);
        $this->assertSame(['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano'], $order->attribution['first_touch']);
        $this->assertSame(['source' => 'direct', 'medium' => 'none', 'campaign' => null], $order->attribution['last_touch']);
        // Con `analytics` consentido, los identificadores viajan; con `marketing`, los click ids.
        $this->assertSame($visitor, $order->attribution['visitor_id']);
        $this->assertSame($hoy->id, $order->attribution['session_id']);
        $this->assertSame(['fbclid' => 'IwAR0'], $order->attribution['click_ids']);
        $this->assertSame('/entradas', $order->attribution['entry_route']);
    }

    /** Sin consentimiento el pedido sabe su CAMPAÑA y no sabe quién navegó (spec §7.1, rgpd-1, rgpd-5). */
    public function test_without_consent_the_seal_carries_the_campaign_but_no_identifiers(): void
    {
        $visitor = Visitor::mint();
        $this->sesion($visitor, ['utm_source' => 'meta', 'utm_medium' => 'paid_social', 'click_ids' => ['fbclid' => 'IwAR0']]);
        $this->contextoWeb($visitor);

        $order = $this->order();

        $this->assertSame('meta', $order->attribution_source);
        $this->assertSame('paid_social', $order->attribution_medium);
        $this->assertArrayNotHasKey('visitor_id', $order->attribution);
        $this->assertArrayNotHasKey('session_id', $order->attribution);
        $this->assertArrayNotHasKey('click_ids', $order->attribution);
    }

    /** `gclid` sin UTM es `google/cpc`; un `ref` es la fuente; sin nada, directo (reglas del contrato). */
    public function test_a_click_id_without_utm_is_google_cpc_and_a_ref_is_a_source(): void
    {
        $gclid = Visitor::mint();
        $this->sesion($gclid, ['click_ids' => ['gclid' => 'CjAK1']]);
        $this->contextoWeb($gclid);
        $this->assertSame(['google', 'cpc'], [$this->order('JJ-GCLID')->attribution_source, Order::where('code', 'JJ-GCLID')->sole()->attribution_medium]);

        $ref = Visitor::mint();
        $this->sesion($ref, ['ref' => 'gbp']);
        $this->contextoWeb($ref);
        $this->assertSame(['gbp', 'referral'], [$this->order('JJ-REF')->attribution_source, Order::where('code', 'JJ-REF')->sole()->attribution_medium]);
    }

    /**
     * ⚠️⚠️ **Nace sellado de verdad**: con el contexto resuelto antes, crear el pedido es UN INSERT que ya
     * lleva el sello, sin UPDATE posterior y sin consultar el libro bajo el lock (spec §7.1, dinero-7,
     * rendimiento-6).
     */
    public function test_the_seal_is_written_in_the_insert_and_never_updated(): void
    {
        $visitor = Visitor::mint();
        $this->sesion($visitor, ['utm_source' => 'google']);
        $this->contextoWeb($visitor);
        $user = User::factory()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        Order::create(['user_id' => $user->id, 'code' => 'JJ-INSERT', 'status' => Order::STATUS_PENDING, 'expires_at' => now()->addMinutes(30)]);
        DB::disableQueryLog();

        $sql = array_map(static fn (array $q): string => strtolower($q['query']), DB::getQueryLog());

        $this->assertCount(1, array_filter($sql, static fn (string $q): bool => str_starts_with($q, 'insert into "orders"')), 'el pedido no se escribió con un solo INSERT');
        $this->assertSame([], array_filter($sql, static fn (string $q): bool => str_starts_with($q, 'update "orders"')), 'el sello llegó en un UPDATE tardío');
        $this->assertSame([], array_filter($sql, static fn (string $q): bool => str_contains($q, 'analytics_sessions')), 'el sello consultó el libro dentro de la creación');
        $this->assertSame('google', Order::where('code', 'JJ-INSERT')->sole()->attribution_source);
    }

    /** ⚠️⚠️ **La analítica NUNCA impide un pedido**: un contexto que revienta deja el pedido sin sello y con rastro. */
    public function test_a_broken_context_never_stops_an_order(): void
    {
        $this->mock(AttributionContext::class, function ($mock): void {
            $mock->shouldReceive('seal')->andThrow(new RuntimeException('boom'));
            $mock->shouldReceive('consented')->andReturn(false);
            $mock->shouldReceive('visitorId')->andReturn(null);
            $mock->shouldReceive('sessionId')->andReturn(null);
        });
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => $message === 'analytics.seal_failed');
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $order = $this->order('JJ-BOOM');

        $this->assertTrue($order->exists);
        $this->assertNull($order->attribution_channel);
    }
}
