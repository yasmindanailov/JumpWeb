<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountAnalytics;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Services\Analytics\AccountLinker;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * **El enlace sesión↔cuenta, el régimen IDENTIFICADO** (`specs/analitica.md` §4.1 y §4.3, T3a·3): al entrar con
 * la categoría `analytics`, las sesiones del visitante de los últimos 90 días y sus hechos se atan a la cuenta,
 * la primera atribución se escribe UNA vez y queda la prueba en `consents`; sin la categoría, nada; con la
 * oposición de la cuenta, nada; una sesión de OTRA cuenta no cambia de dueño; el equipo no se ata.
 */
class AccountLinkTest extends TestCase
{
    use RefreshDatabase;

    private const VISITOR = '01HZX8K4N2P7Q9R3S5T6V8W0YA';

    /** ⚠️ Tampoco `session()`: el `TestCase` de Laravel lo tiene público (la trampa de la T2c, otra vez). */
    private function visit(string $visitor, string $startedAt, array $extra = []): AnalyticsSession
    {
        return AnalyticsSession::query()->create([
            'visitor_id' => $visitor, 'started_at' => $startedAt, 'last_seen_at' => $startedAt,
            'is_bot' => false, 'is_internal' => false,
        ] + $extra);
    }

    private function event(AnalyticsSession $session, string $name): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'event_id' => Visitor::mint(), 'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
            'name' => $name, 'occurred_at' => $session->started_at, 'received_at' => $session->started_at,
        ]);
    }

    /** Una petición de la web con la cookie del visitante y la categoría `analytics` (o no) consentida en la sesión del libro. */
    private function request(bool $analytics = true): AttributionContext
    {
        $request = Request::create('/', 'GET', [], [Visitor::COOKIE => self::VISITOR, CookieConsent::COOKIE_NAME => CookieConsent::encode(['analytics' => $analytics])]);
        $context = app(AttributionContext::class);
        $context->resolveFrom($request);

        return $context;
    }

    /** ⚠️ No se llama `seed()`: el `TestCase` de Laravel ya tiene uno público, y redefinirlo privado es un fatal. */
    private function fixture(bool $analytics = true): array
    {
        // La sesión de HOY, con la foto del consentimiento tal como la ingesta la guarda; una de hace 30 días con
        // campaña (el primer toque); una de hace 100 días (fuera de los 90); y una de OTRO visitante.
        $today = $this->visit(self::VISITOR, now()->subMinutes(5), ['consent' => ['analytics' => $analytics, 'marketing' => false]]);
        $campaign = $this->visit(self::VISITOR, now()->subDays(30), ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'verano']);
        $old = $this->visit(self::VISITOR, now()->subDays(100), ['utm_source' => 'meta', 'utm_medium' => 'paid_social']);
        $other = $this->visit(Visitor::mint(), now()->subDay());
        $this->event($today, 'page_viewed');
        $this->event($campaign, 'drawer_opened');
        $this->event($old, 'page_viewed');

        return compact('today', 'campaign', 'old', 'other');
    }

    public function test_the_linker_ties_the_visitors_sessions_of_the_last_90_days_and_their_events(): void
    {
        $s = $this->fixture();
        $user = User::factory()->create();

        $result = app(AccountLinker::class)->link((int) $user->id, $this->request());

        $this->assertSame(2, $result['visits']);
        $this->assertSame(['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano'], $result['first_touch']);
        $this->assertSame($user->id, $s['today']->fresh()->user_id);
        $this->assertSame($user->id, $s['campaign']->fresh()->user_id);
        $this->assertNull($s['old']->fresh()->user_id, 'fuera de los 90 días');
        $this->assertNull($s['other']->fresh()->user_id, 'otro visitante');
        $this->assertSame(2, AnalyticsEvent::query()->where('user_id', $user->id)->count());
    }

    public function test_without_the_category_nothing_is_tied_and_the_book_stays_aggregate(): void
    {
        $s = $this->fixture(analytics: false);
        $user = User::factory()->create();

        $this->assertNull(app(AccountLinker::class)->link((int) $user->id, $this->request(analytics: false)));
        $this->assertNull($s['today']->fresh()->user_id);
        $this->assertSame(0, AnalyticsEvent::query()->whereNotNull('user_id')->count());
    }

    /**
     * La foto de la sesión del libro se toma al abrirla; quien acepta «análisis» después y entra antes de que
     * llegue otro lote tiene la cookie decidida y la foto vieja: la COOKIE manda (Identity la lee y la pasa).
     */
    public function test_the_cookie_of_the_request_wins_over_a_stale_session_snapshot(): void
    {
        $s = $this->fixture(analytics: false);   // la foto dice «no»
        $user = User::factory()->create();

        $this->assertNull(app(AccountLinker::class)->link((int) $user->id, $this->request(analytics: false)), 'sin cookie decidida manda la foto');
        $this->assertNotNull(app(AccountLinker::class)->link((int) $user->id, $this->request(analytics: false), false, consentedNow: true), 'la cookie decidida manda');
        $this->assertSame($user->id, $s['today']->fresh()->user_id);

        // Y al revés: una cookie decidida en «no» no enlaza aunque la foto de la sesión dijera «sí».
        $t = $this->fixture(analytics: true);
        $other = User::factory()->create();
        $this->assertNull(app(AccountLinker::class)->link((int) $other->id, $this->request(), false, consentedNow: false));
        $this->assertNull($t['today']->fresh()->user_id);
    }

    public function test_a_session_already_tied_to_another_account_keeps_its_owner(): void
    {
        $s = $this->fixture();
        $other = User::factory()->create();
        $s['campaign']->forceFill(['user_id' => $other->id])->save();
        $user = User::factory()->create();

        $result = app(AccountLinker::class)->link((int) $user->id, $this->request());

        $this->assertSame(1, $result['visits']);
        $this->assertSame($other->id, $s['campaign']->fresh()->user_id);
        $this->assertSame($user->id, $s['today']->fresh()->user_id);
    }

    public function test_the_account_side_writes_the_first_attribution_once_and_the_proof_once(): void
    {
        $this->fixture();
        $user = User::factory()->create();
        $this->request();

        $this->assertTrue(app(AccountAnalytics::class)->linkIfConsented($user, '10.0.0.1'));

        $user->refresh();
        $this->assertSame(['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano'], $user->first_attribution);
        $proof = $user->consents()->where('type', Consent::TYPE_ANALYTICS)->get();
        $this->assertCount(1, $proof);
        $this->assertSame('10.0.0.1', $proof[0]->ip);
        $this->assertSame(CookieConsent::POLICY_VERSION, $proof[0]->version);
        $this->assertNull($proof[0]->revoked_at);

        // Un segundo enlace (otra visita, otra campaña): ni la atribución ni la prueba se duplican.
        $this->visit(self::VISITOR, now()->subMinute(), ['utm_source' => 'tiktok', 'utm_medium' => 'paid_social', 'consent' => ['analytics' => true]]);
        $this->assertTrue(app(AccountAnalytics::class)->linkIfConsented($user->fresh(), '10.0.0.2'));
        $this->assertSame('google', $user->fresh()->first_attribution['source'], 'la primera atribución es inmutable');
        $this->assertSame(1, $user->consents()->where('type', Consent::TYPE_ANALYTICS)->count());
    }

    public function test_an_account_that_opposed_is_never_tied(): void
    {
        $s = $this->fixture();
        $user = User::factory()->create(['analytics_opt_out' => true]);
        $this->request();

        $this->assertFalse(app(AccountAnalytics::class)->linkIfConsented($user, '10.0.0.1'));
        $this->assertNull($s['today']->fresh()->user_id);
        $this->assertNull(app(AccountLinker::class)->link((int) $user->id, $this->request(), optedOut: true));
    }

    public function test_logging_in_ties_the_navigation_unless_it_is_the_team(): void
    {
        $s = $this->fixture();
        $this->request();   // el contexto de ESTA petición lleva el visitante y la categoría
        $cliente = User::factory()->create();
        $operador = User::factory()->create();
        $operador->roles()->attach(Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']));

        Auth::login($cliente);
        $this->assertSame($cliente->id, $s['today']->fresh()->user_id);
        $this->assertSame(1, $cliente->consents()->where('type', Consent::TYPE_ANALYTICS)->count());

        Auth::logout();
        $s['today']->forceFill(['user_id' => null])->save();
        Auth::login($operador);
        $this->assertNull($s['today']->fresh()->user_id, 'un operador que entra no es un cliente que vuelve');
    }

    public function test_unlinking_returns_everything_to_the_aggregate(): void
    {
        $s = $this->fixture();
        $user = User::factory()->create();
        app(AccountLinker::class)->link((int) $user->id, $this->request());

        $this->assertSame(2, app(AccountLinker::class)->unlink((int) $user->id));
        $this->assertNull($s['today']->fresh()->user_id);
        $this->assertSame(0, AnalyticsEvent::query()->whereNotNull('user_id')->count());
    }
}
