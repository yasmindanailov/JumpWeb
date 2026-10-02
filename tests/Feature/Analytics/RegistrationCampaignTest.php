<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Recorder;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * **EL ALTA LLEVA LA CAMPAÑA DE SU VISITA** (TA, `docs/specs/analitica-para-decidir.md` §4.15; `#876`, la fila 8 de la lista
 * del owner: «altas en casa y en el parque»).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene: que `user_registered` lleve la fuente, el medio y la campaña de la visita en
 * la que se dio el alta AUNQUE no haya consentimiento —es la capa de campaña del sello del pedido (rgpd-5): no dice quién
 * navegó— · que la visita (`session_id`, `visitor_id`) siga viajando SOLO con «análisis» · que sin visita no se invente nada ·
 * que una campaña con pinta de dato personal no viaje (`RGPD-07`) · y, de CONTROL, que un hecho sin la marca del contrato
 * (`user_logged_in`) no la lleve: lo que la pone es el contrato, no el `Recorder` a ciegas.
 */
class RegistrationCampaignTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $atributos */
    private function visita(array $atributos): string
    {
        $visitor = Visitor::mint();
        AnalyticsSession::create(['visitor_id' => $visitor, 'started_at' => now(), 'last_seen_at' => now(), ...$atributos]);

        $request = Request::create('/api/v1/auth/register', 'POST');
        $request->cookies->set(Visitor::COOKIE, $visitor);
        app(AttributionContext::class)->resolveFrom($request);

        return $visitor;
    }

    private function alta(string $name = 'user_registered'): AnalyticsEvent
    {
        $user = User::factory()->create();
        app(Recorder::class)->fact($name, ['method' => 'password'], ['user_id' => (int) $user->id]);

        return AnalyticsEvent::query()->where('name', $name)->sole();
    }

    private const PARQUE = ['utm_source' => 'parque', 'utm_medium' => 'qr', 'utm_campaign' => 'registro'];

    public function test_a_registration_carries_the_campaign_of_its_visit_even_without_consent(): void
    {
        $this->visita(self::PARQUE);

        $event = $this->alta();

        $this->assertSame(['method' => 'password', 'source' => 'parque', 'medium' => 'qr', 'campaign' => 'registro'], $event->props);
        $this->assertNull($event->session_id, 'sin «análisis», el alta no se ata a la visita');
        $this->assertNull($event->visitor_id);
        $this->assertNotNull($event->user_id);
    }

    public function test_with_analytics_the_registration_is_also_tied_to_its_visit(): void
    {
        $visitor = $this->visita([...self::PARQUE, 'consent' => ['analytics' => true]]);

        $event = $this->alta();

        $this->assertSame(['method' => 'password', 'source' => 'parque', 'medium' => 'qr', 'campaign' => 'registro'], $event->props);
        $this->assertSame(AnalyticsSession::query()->where('visitor_id', $visitor)->value('id'), $event->session_id);
        $this->assertSame($visitor, $event->visitor_id);
    }

    /** Una visita sin campaña es «directa» (la regla del embudo): también es un origen, el de quien llegó tecleando. */
    public function test_a_direct_visit_is_an_origin_too(): void
    {
        $this->visita([]);

        $this->assertSame(['method' => 'password', 'source' => 'direct', 'medium' => 'none'], $this->alta()->props);
    }

    public function test_without_a_visit_the_registration_carries_no_campaign(): void
    {
        app(AttributionContext::class)->system();

        $this->assertSame(['method' => 'password'], $this->alta()->props);
    }

    public function test_a_campaign_that_looks_like_personal_data_does_not_travel(): void
    {
        $this->visita(['utm_source' => 'parque', 'utm_medium' => 'qr', 'utm_campaign' => 'ana@example.com']);

        $this->assertSame(['method' => 'password', 'source' => 'parque', 'medium' => 'qr'], $this->alta()->props);
    }

    /** CONTROL: entrar no pide la campaña en el contrato, así que no la lleva aunque la visita la tenga. */
    public function test_a_fact_without_the_campaign_mark_does_not_carry_it(): void
    {
        $this->visita(self::PARQUE);

        $this->assertSame(['method' => 'password'], $this->alta('user_logged_in')->props);
    }
}
