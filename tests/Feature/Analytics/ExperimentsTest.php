<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\Analytics\Experiments;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **T5a de la analítica — los experimentos: la asignación la hace el servidor** (`docs/specs/analitica.md` §4.4).
 *
 * Lo que fija: que el mismo sujeto cae siempre en la misma variante y que el reparto respeta los pesos; que el
 * sujeto es el VISITANTE cuando lo hay y el titular solo sin él (`#737`); que solo asigna un experimento VIVO y
 * bien formado; que el panel actúa al instante (la caché se olvida al guardar); y lo que hizo falta cambiar en
 * `ResolveVisitor`: que la PRIMERA vista ya trae la variante y la cookie que la va a conservar.
 */
class ExperimentsTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $overrides */
    private function shellExperiment(array $overrides = []): Experiment
    {
        return Experiment::create($overrides + [
            'key' => 'shell',
            'name' => 'Cajón o isla',
            'variants' => [['key' => 'cajon', 'weight' => 1], ['key' => 'isla', 'weight' => 1]],
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function bootPayload(string $html): array
    {
        $this->assertSame(1, preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $html, $m), 'La página no lleva el `data-boot`.');

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_same_subject_always_gets_the_same_variant_and_subjects_spread_by_weight(): void
    {
        $experiment = $this->shellExperiment(['variants' => [['key' => 'cajon', 'weight' => 90], ['key' => 'isla', 'weight' => 10]]]);
        $counts = ['cajon' => 0, 'isla' => 0];

        for ($i = 0; $i < 2000; $i++) {
            $subject = 'v:'.Visitor::mint();
            $first = Experiments::variantFor($experiment, $subject);

            $this->assertSame($first, Experiments::variantFor($experiment, $subject), 'la misma persona cae siempre en la misma variante');
            $counts[$first]++;
        }

        // 10 % de 2.000 = 200, con desviación típica ≈ 13,4: ±60 son más de cuatro sigmas. Un reparto que no
        // respetase los pesos (50/50 → 1.000) o un hash sesgado caen aquí; el azar honesto, no.
        $this->assertEqualsWithDelta(200, $counts['isla'], 60, 'el reparto no respeta los pesos 90/10');
    }

    public function test_the_visitor_is_the_subject_when_there_is_one_and_the_holder_only_without_it(): void
    {
        $experiment = $this->shellExperiment();
        $visitor = Visitor::mint();

        $this->assertSame('v:'.$visitor, Experiments::subject($visitor, 7), 'con cookie manda la cookie, aunque haya sesión');
        $this->assertSame('u:7', Experiments::subject(null, 7));
        $this->assertNull(Experiments::subject(null, null));

        $this->assertSame([], Experiments::assignments(null, null), 'sin sujeto no hay reparto');
        $this->assertSame(['shell' => Experiments::variantFor($experiment, 'u:7')], Experiments::assignments(null, 7));
        $this->assertSame(['shell' => Experiments::variantFor($experiment, 'v:'.$visitor)], Experiments::assignments($visitor, 7));
    }

    public function test_only_a_running_and_well_formed_experiment_assigns(): void
    {
        $this->shellExperiment(['key' => 'apagado', 'active' => false]);
        $this->shellExperiment(['key' => 'acabado', 'ended_at' => now()->subMinute()]);
        $this->shellExperiment(['key' => 'futuro', 'started_at' => now()->addDay()]);
        $this->shellExperiment(['key' => 'una-sola', 'variants' => [['key' => 'a', 'weight' => 1]]]);
        $this->shellExperiment(['key' => 'sin-peso', 'variants' => [['key' => 'a', 'weight' => 0], ['key' => 'b', 'weight' => -3]]]);
        $this->shellExperiment(['key' => 'mal-formado', 'variants' => [['key' => 'A B', 'weight' => 1], 'c', ['weight' => 2]]]);
        $this->shellExperiment(['key' => 'vivo', 'started_at' => now()->subDay(), 'ended_at' => now()->addDay()]);

        $this->assertSame(['vivo'], array_keys(Experiments::assignments(Visitor::mint(), null)));
    }

    public function test_saving_or_deleting_an_experiment_forgets_the_cache_so_the_panel_acts_at_once(): void
    {
        $visitor = Visitor::mint();
        $this->assertSame([], Experiments::assignments($visitor, null));

        $experiment = $this->shellExperiment();
        $this->assertArrayHasKey('shell', Experiments::assignments($visitor, null), 'crear uno lo pone en la petición siguiente');

        $experiment->update(['active' => false]);
        $this->assertSame([], Experiments::assignments($visitor, null), 'apagarlo lo quita en la petición siguiente');

        $experiment->update(['active' => true]);
        $experiment->delete();
        $this->assertSame([], Experiments::assignments($visitor, null), 'borrarlo también');
    }

    /**
     * ⚠️ La suite corre con la caché `array`, que no serializa nada: la primera versión cacheaba la COLECCIÓN de
     * modelos y en la local (`database`) volvió como objeto incompleto. Este caso pasa por un almacén que serializa
     * de verdad, y lee dos veces: la segunda viene de la caché.
     */
    public function test_the_live_rows_survive_a_cache_store_that_serializes(): void
    {
        config()->set('cache.default', 'database');
        Experiments::forget();

        $experiment = $this->shellExperiment(['started_at' => now()->subHour(), 'ended_at' => now()->addHour()]);
        $visitor = Visitor::mint();

        $first = Experiments::assignments($visitor, null);
        $second = Experiments::assignments($visitor, null);

        $this->assertSame(['shell' => Experiments::variantFor($experiment, 'v:'.$visitor)], $first);
        $this->assertSame($first, $second, 'la lectura desde la caché serializada dice lo mismo');
        $this->assertSame(['cajon' => 1, 'isla' => 1], Experiments::running()->first()?->weightedVariants(), 'las variantes vuelven como lista, no como JSON');
        $this->assertTrue(Experiments::running()->first()?->isRunning() ?? false, 'las fechas vuelven como fechas');
    }

    /**
     * ⚠️ Lo que obligó a tocar `ResolveVisitor`: hasta la T5 el id se acuñaba DESPUÉS de componer la página, así
     * que la primera vista se resolvía sin visitante y todo el mundo estrenaría la web con la variante de
     * control. Ahora la cookie de la respuesta lleva el MISMO id con el que se pintó la variante.
     */
    public function test_the_first_page_view_already_carries_the_variant_and_the_cookie_that_keeps_it(): void
    {
        $this->shellExperiment();

        $first = $this->get('/')->assertOk();
        $cookie = collect($first->headers->getCookies())->first(fn ($c): bool => $c->getName() === Visitor::COOKIE);
        $this->assertNotNull($cookie, 'la primera vista acuña la cookie del visitante');

        $variant = $this->bootPayload((string) $first->getContent())['experiments']['shell'] ?? null;
        $this->assertContains($variant, ['cajon', 'isla']);

        $this->assertSame($variant, Experiments::assignments($cookie->getValue(), null)['shell'], 'la variante pintada es la del id que la cookie conserva');

        $again = $this->withUnencryptedCookie(Visitor::COOKIE, $cookie->getValue())->get('/')->assertOk();
        $this->assertSame($variant, $this->bootPayload((string) $again->getContent())['experiments']['shell']);
        $this->assertNull(
            collect($again->headers->getCookies())->first(fn ($c): bool => $c->getName() === Visitor::COOKIE),
            'con cookie no se vuelve a acuñar: la de 13 meses no se renueva (exención AEPD)'
        );
    }

    public function test_without_a_live_experiment_the_layout_does_not_pay_the_key_and_the_api_keeps_its_shape(): void
    {
        $boot = $this->bootPayload((string) $this->get('/')->assertOk()->getContent());
        $this->assertArrayNotHasKey('experiments', $boot, 'sin experimentos la clave no viaja en cada página pública');

        $this->getJson('/api/v1/sidebar/session?lang=es')->assertOk()->assertJsonPath('experiments', []);
    }

    public function test_the_holder_without_cookie_gets_the_variant_of_the_account_through_the_session_endpoint(): void
    {
        $experiment = $this->shellExperiment();
        $holder = User::factory()->create(['email_verified_at' => now()]);

        // La API (grupo `api`) no acuña; sin cookie, el sujeto es el titular: la app con Bearer y sin `X-Visitor`.
        $this->actingAs($holder, 'web')->getJson('/api/v1/sidebar/session?lang=es')->assertOk()
            ->assertJsonPath('experiments.shell', Experiments::variantFor($experiment, 'u:'.$holder->id));
    }
}
