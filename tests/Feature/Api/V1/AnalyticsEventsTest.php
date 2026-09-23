<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\Feature\Api\ApiTestCase;

/**
 * **`POST /api/v1/events` — la ingesta del libro** (`docs/specs/analitica.md` §4.1, §6; `DECISIONES #678`).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene: que un lote se valide EVENTO A EVENTO (un rótulo mal
 * escrito no tira la campaña que viaja a su lado) · que un hecho de SERVIDOR no entre por aquí (nadie
 * fabrica un `order_paid` con `curl`) · que ni un token de URL ni un correo lleguen a una tabla de 25
 * meses · que reenviar un lote no duplique · que la cookie de 13 meses se acuñe UNA vez y no se renueve ·
 * que un rastreador y la sonda se guarden APARTE · que la ruta sea stateless y tenga su propio limitador,
 * y que ese limitador no toque el cubo del embudo.
 *
 * ⚠️ Los valores esperados van tecleados; las respuestas se validan contra el contrato con Spectator.
 */
class AnalyticsEventsTest extends ApiTestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    /** @param  array<int, array<string, mixed>>  $events */
    private function lote(array $events, array $meta = [], array $headers = [], ?string $visitor = null): TestResponse
    {
        $body = ['events' => $events] + ($meta === [] ? [] : ['meta' => $meta]);
        // ⚠️ `postJson` solo adjunta cookies con `withCredentials()`: sin esto cada lote parece un
        // visitante nuevo y la cookie «no se lee», que fue lo primero que pareció fallar.
        $request = $this->withCredentials()->withHeaders($headers + ['User-Agent' => self::IPHONE]);

        if ($visitor !== null) {
            $request = $request->withUnencryptedCookie(Visitor::COOKIE, $visitor);
        }

        return $request->postJson(self::ROOT.'/events', $body);
    }

    /** @param  array<string, mixed>  $props */
    private function event(string $name, array $props = [], ?string $route = null, ?string $id = null): array
    {
        return array_filter([
            'event_id' => $id ?? (string) Str::ulid(),
            'name' => $name,
            'route' => $route,
            'props' => $props === [] ? null : $props,
        ], static fn ($v): bool => $v !== null);
    }

    private function firstView(): array
    {
        return $this->event('page_viewed', [
            'entry' => '/entradas',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'verano',
            'gclid' => 'CjAK1',
            'device' => 'mobile',
            'locale' => 'es',
        ], '/entradas?utm_source=google&utm_medium=cpc&gclid=CjAK1&foo=bar');
    }

    public function test_a_batch_is_accepted_and_opens_a_session_with_its_attribution(): void
    {
        $response = $this->lote([$this->firstView(), $this->event('drawer_opened', ['product' => 395, 'reason' => 'user'], '/entradas')])
            ->assertStatus(202)
            ->assertValidRequest()
            ->assertValidResponse(202);

        $this->assertSame(['accepted' => 2, 'rejected' => []], $response->json());

        $session = AnalyticsSession::query()->sole();
        $this->assertSame('google', $session->utm_source);
        $this->assertSame('cpc', $session->utm_medium);
        $this->assertSame('verano', $session->utm_campaign);
        $this->assertSame('/entradas', $session->entry_route);
        $this->assertSame('mobile', $session->device);
        $this->assertSame('es', $session->locale);
        $this->assertFalse($session->is_bot);
        // Sin la categoría `marketing`, el click id NO se guarda.
        $this->assertNull($session->click_ids);
        // El libro exento no lleva persona.
        $this->assertNull($session->user_id);

        $events = AnalyticsEvent::query()->orderBy('id')->get();
        $this->assertCount(2, $events);
        // La query se cae entera: en el libro solo queda el patrón de la ruta.
        $this->assertSame('/entradas', $events[0]->route);
        $this->assertSame(['product' => 395, 'reason' => 'user'], $events[1]->props);
        $this->assertSame($session->id, $events[1]->session_id);
        $this->assertNull($events[1]->user_id);
    }

    /**
     * **Por evento, no por lote** (spec §7.1, producto-3): lo válido entra, lo inválido vuelve con su
     * índice y su motivo, y el `page_viewed` con la campaña sobrevive a un rótulo mal escrito a su lado.
     */
    public function test_the_batch_is_validated_event_by_event(): void
    {
        $response = $this->lote([
            $this->firstView(),
            $this->event('foo_bar'),
            $this->event('order_paid', ['paid_cents' => 99999, 'total_cents' => 99999]),
            $this->event('call_clicked', ['email' => 'a@b.com']),
            $this->event('step_entered', ['from' => 1, 'to' => 2, 'ignored' => 'x']),
            $this->event('section_viewed', ['section' => 'hero'], null, 'no-es-un-ulid'),
        ])->assertStatus(202)->assertValidResponse(202);

        $this->assertSame(2, $response->json('accepted'));
        $this->assertSame([
            ['index' => 1, 'reason' => 'unknown_event'],
            ['index' => 2, 'reason' => 'server_only'],
            ['index' => 3, 'reason' => 'pii'],
            ['index' => 5, 'reason' => 'bad_event_id'],
        ], $response->json('rejected'));

        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'order_paid')->count(), 'un hecho de servidor entró por el cliente');
        $this->assertSame(['from' => 1, 'to' => 2], AnalyticsEvent::query()->where('name', 'step_entered')->sole()->props, 'una prop fuera del contrato viajó');
        $this->assertSame('google', AnalyticsSession::query()->sole()->utm_source, 'la campaña se perdió por un rótulo mal escrito');
    }

    /**
     * ⚠️⚠️ Un token de invitación, una firma HMAC o el correo de un enlace de recuperación viajan en la URL
     * (spec §7.1, seguridad-2). En el libro solo queda el PATRÓN, y un correo en cualquier valor rechaza.
     */
    public function test_a_token_or_a_signature_in_the_url_never_reaches_the_book(): void
    {
        $this->lote([
            $this->event('page_viewed', [], '/invitacion/abcdefghijklmnopqrstuvwxyz123456'),
            $this->event('page_viewed', [], '/reservas/42/invitados?signature=deadbeefcafe&expires=1700000000'),
            $this->event('page_viewed', [], 'https://localhost/restablecer-contrasena/9f8e7d6c5b4a39281706f5e4d3c2b1a0?email=ana@example.com'),
            $this->event('section_viewed', ['section' => 'llama a 600 123 456']),
        ])->assertStatus(202);

        $routes = AnalyticsEvent::query()->where('name', 'page_viewed')->orderBy('id')->pluck('route')->all();

        $this->assertSame(['/invitacion/{token}', '/reservas/{n}/invitados', '/restablecer-contrasena/{token}'], $routes);
        $this->assertStringNotContainsString('ana@', json_encode(AnalyticsEvent::all()->toArray()));
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'section_viewed')->count(), 'un teléfono en una prop se guardó');
    }

    /** `sendBeacon` más un reintento son el MISMO lote dos veces: una fila, no dos (spec §7.1, medicion-7). */
    public function test_the_same_batch_twice_is_one_row(): void
    {
        $visitor = Visitor::mint();
        $batch = [$this->firstView(), $this->event('drawer_opened', ['reason' => 'user'])];

        $this->lote($batch, visitor: $visitor)->assertStatus(202);
        $second = $this->lote($batch, visitor: $visitor)->assertStatus(202);

        $this->assertSame(0, $second->json('accepted'));
        $this->assertSame(2, AnalyticsEvent::query()->count());
        $this->assertSame(1, AnalyticsSession::query()->count());
    }

    /**
     * La cookie es EXENTA solo si dura ≤ 13 meses **y no se renueva** en cada visita (guía AEPD 2024):
     * se acuña una vez, `HttpOnly`, y con ella puesta ninguna respuesta la vuelve a escribir.
     */
    public function test_the_visitor_cookie_is_minted_once_and_never_renewed(): void
    {
        $first = $this->lote([$this->firstView()])->assertStatus(202);

        $cookie = collect($first->headers->getCookies())->first(fn ($c): bool => $c->getName() === Visitor::COOKIE);
        $this->assertNotNull($cookie, 'el 202 no acuñó la cookie del visitante');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue(Visitor::isValid($cookie->getValue()));
        // 13 meses, con un día de margen por el calendario.
        $this->assertEqualsWithDelta(now()->addMonths(13)->getTimestamp(), $cookie->getExpiresTime(), 60 * 60 * 24 * 2);

        $second = $this->lote([$this->event('drawer_opened', ['reason' => 'user'])], visitor: $cookie->getValue())->assertStatus(202);
        $again = collect($second->headers->getCookies())->first(fn ($c): bool => $c->getName() === Visitor::COOKIE);
        $this->assertNull($again, 'la cookie se renovó en la segunda visita');
        $this->assertSame($cookie->getValue(), AnalyticsSession::query()->sole()->visitor_id);
    }

    /** Un rastreador, la sonda y el equipo se guardan APARTE del tráfico real (spec §7.1, medicion-6). */
    public function test_crawlers_automation_and_internal_traffic_are_kept_apart(): void
    {
        $this->lote([$this->firstView()], headers: ['User-Agent' => self::GOOGLEBOT], visitor: Visitor::mint())->assertStatus(202);
        $this->lote([$this->firstView()], meta: ['webdriver' => true], visitor: Visitor::mint())->assertStatus(202);
        $this->lote([$this->firstView()], meta: ['internal' => true], visitor: Visitor::mint())->assertStatus(202);
        $this->lote([$this->firstView()], visitor: Visitor::mint())->assertStatus(202);

        $sessions = AnalyticsSession::query()->orderBy('id')->get();
        $this->assertSame([true, true, false, false], $sessions->pluck('is_bot')->all());
        $this->assertSame([false, false, true, false], $sessions->pluck('is_internal')->all());
    }

    /** `X-Visitor` solo cuenta con un Bearer (la app); en el mismo origen manda la cookie (spec §7.1, seguridad-4). */
    public function test_the_visitor_header_only_counts_with_a_bearer(): void
    {
        $header = (string) Str::uuid();

        $this->lote([$this->firstView()], headers: ['X-Visitor' => $header])->assertStatus(202);
        $this->assertNotSame($header, AnalyticsSession::query()->sole()->visitor_id, 'una cabecera sin Bearer se atribuyó la sesión');

        AnalyticsSession::query()->delete();
        AnalyticsEvent::query()->delete();

        $app = $this->lote([$this->firstView()], headers: ['X-Visitor' => $header, 'Authorization' => 'Bearer un-token-de-la-app'])->assertStatus(202);
        $this->assertSame($header, AnalyticsSession::query()->sole()->visitor_id);
        $this->assertSame('app', AnalyticsSession::query()->sole()->surface);
        $this->assertNull(collect($app->headers->getCookies())->first(fn ($c): bool => $c->getName() === Visitor::COOKIE), 'a la app se le acuñó una cookie');
    }

    /** El reloj del cliente solo ordena dentro de la sesión: se acota a ±5 min del servidor (spec §7.1, faltas-7). */
    public function test_the_client_clock_is_clamped_to_the_server(): void
    {
        $event = $this->event('drawer_opened', ['reason' => 'user']) + ['occurred_at' => now()->addHours(3)->getTimestampMs()];

        $this->lote([$event])->assertStatus(202);

        $row = AnalyticsEvent::query()->sole();
        $this->assertEqualsWithDelta($row->received_at->getTimestamp() + 300, $row->occurred_at->getTimestamp(), 2);
    }

    /** Los click ids son dato de plataforma: solo con `marketing` consentido (spec §7.1, rgpd-5). */
    public function test_click_ids_are_kept_only_with_marketing_consent(): void
    {
        $this->lote([$this->firstView()], meta: ['consent' => ['marketing' => true, 'analytics' => false]], visitor: Visitor::mint())->assertStatus(202);
        $this->lote([$this->firstView()], meta: ['consent' => ['marketing' => false]], visitor: Visitor::mint())->assertStatus(202);

        $sessions = AnalyticsSession::query()->orderBy('id')->get();
        $this->assertSame(['gclid' => 'CjAK1'], $sessions[0]->click_ids);
        $this->assertSame(['marketing' => true, 'analytics' => false], $sessions[0]->consent);
        $this->assertNull($sessions[1]->click_ids);
    }

    /** Una vista con OTRA campaña abre sesión nueva aunque la anterior siga viva (spec §4.1). */
    public function test_a_new_campaign_opens_a_new_session(): void
    {
        $visitor = Visitor::mint();

        $this->lote([$this->firstView()], visitor: $visitor)->assertStatus(202);
        $this->lote([$this->event('drawer_opened', ['reason' => 'user'])], visitor: $visitor)->assertStatus(202);
        $this->lote([$this->event('page_viewed', ['utm_source' => 'meta', 'utm_medium' => 'paid_social'])], visitor: $visitor)->assertStatus(202);

        $sessions = AnalyticsSession::query()->orderBy('id')->get();
        $this->assertSame(['google', 'meta'], $sessions->pluck('utm_source')->all());
        $this->assertSame([2, 1], [
            AnalyticsEvent::query()->where('session_id', $sessions[0]->id)->count(),
            AnalyticsEvent::query()->where('session_id', $sessions[1]->id)->count(),
        ]);
    }

    /**
     * ❗❗ **La ruta es STATELESS y su limitador SUSTITUYE al del grupo** (spec §7.1, producto-1,
     * seguridad-5): sin cabecera CSRF desde el propio origen responde 202 —`sendBeacon` no puede
     * mandarla—, y agotar su cubo no toca el del embudo: tras el 429 de los eventos, el catálogo sigue
     * en 200 desde la misma IP.
     */
    public function test_the_route_is_stateless_and_its_limiter_does_not_eat_the_funnel(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.events.store');
        $this->assertNotNull($route);
        $this->assertContains('throttle:api', $route->excludedMiddleware());
        $this->assertContains(EnsureFrontendRequestsAreStateful::class, $route->excludedMiddleware());
        $this->assertContains('throttle:events', $route->middleware());
        $this->assertContains('no-store', $route->middleware());

        // Desde el propio origen, sin `X-XSRF-TOKEN`: lo que hace un `sendBeacon`.
        $beacon = $this->lote([$this->event('drawer_closed', ['step' => 4])], headers: ['Referer' => config('app.url').'/entradas', 'Origin' => config('app.url')])
            ->assertStatus(202);
        $this->assertStringContainsString('no-store', (string) $beacon->headers->get('Cache-Control'));

        $visitor = Visitor::mint();
        $limit = (int) config('api.events.per_minute');
        $statuses = [];
        for ($i = 0; $i <= $limit; $i++) {
            $statuses[] = $this->lote([$this->event('drawer_opened', ['reason' => 'user'])], visitor: $visitor)->getStatusCode();
        }

        $this->assertSame(202, $statuses[$limit - 1], 'el cubo se agotó antes de su techo');
        $this->assertSame(429, $statuses[$limit], 'el limitador propio no frena');
        // Y el cubo del embudo, intacto: la misma IP sigue leyendo el catálogo.
        $this->getJson(self::ROOT.'/catalog/zones')->assertOk();
    }

    /** La ruta que falló es una ruta (T1b): se enmascara como la del evento, o cada pedido sería un valor distinto. */
    public function test_the_failed_route_is_masked_like_any_route(): void
    {
        $this->lote([
            $this->event('request_failed', ['route' => '/orders/R-L6UTIA9Z8X7W6V5U4T3S', 'status' => 0, 'offline' => true]),
            $this->event('request_failed', ['route' => '/availability/395/2026-09-23', 'status' => 500, 'offline' => false]),
        ])->assertStatus(202)->assertJsonPath('accepted', 2);

        $routes = AnalyticsEvent::query()->where('name', 'request_failed')->orderBy('event_id')->get()->map(fn (AnalyticsEvent $e): array => [$e->props['route'], $e->props['status'], $e->props['offline']])->all();

        $this->assertEqualsCanonicalizing([['/orders/{token}', 0, true], ['/availability/{n}/2026-09-23', 500, false]], $routes);
    }
}
