<?php

namespace Tests\Feature\Cookies;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\LegalContent;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T3a de la analítica (`specs/analitica.md` §4.3): la política de privacidad deja de decir «ni elaboramos
 * perfiles» a secas —con la categoría `analytics` del banner la navegación SÍ se vincula a la cuenta— y dice
 * cuándo, para qué y cómo se retira; y la migración quirúrgica lo lleva a una BD ya sembrada.
 */
class PrivacyPolicyProfilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_policy_names_the_identified_analytics_and_how_to_withdraw_it(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->withSession(['locale' => 'es'])->get('/privacidad')->assertOk()
            ->assertSee('categoría «análisis»')
            ->assertSee('Mi cuenta → Privacidad')
            ->assertDontSee('significativamente, ni elaboramos perfiles');
        $this->withSession(['locale' => 'en'])->get('/privacidad')->assertOk()
            ->assertSee('«analytics» category')
            ->assertSee('My account → Privacy');
        $this->withSession(['locale' => 'fr'])->get('/privacidad')->assertOk()
            ->assertSee('catégorie «analyse»')
            ->assertSee('Mon compte → Confidentialité');
    }

    public function test_the_migration_rewrites_only_the_untouched_paragraph_and_is_idempotent(): void
    {
        $nuevo = collect(LegalContent::pages()['privacidad']['body']['es'])->firstWhere('h', 'Decisiones automatizadas y elaboración de perfiles');
        $this->assertNotNull($nuevo, 'la privacidad ya no tiene la sección de perfiles: el caso miraría el vacío');
        $this->assertSame(LegalContent::PROFILING_P['es'], $nuevo['p']);

        $viejo = ['h' => 'Decisiones automatizadas y elaboración de perfiles', 'p' => 'No tomamos decisiones automatizadas que produzcan efectos jurídicos sobre ti o te afecten significativamente, ni elaboramos perfiles con tus datos.'];
        $otra = ['h' => 'Responsable', 'p' => 'Texto que no se toca.'];
        $deLaClienta = ['h' => 'Automated decisions and profiling', 'p' => 'Rewritten by the client.'];

        $page = Page::create([
            'slug' => 'privacidad',
            'title' => LegalContent::pages()['privacidad']['title'],
            'body' => ['es' => [$otra, $viejo], 'en' => [$deLaClienta]],
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_24_120100_privacy_policy_names_identified_analytics.php');
        $migration->up();
        $page->refresh();

        $this->assertSame([$otra, $nuevo], $page->body['es']);
        $this->assertSame([$deLaClienta], $page->body['en'], 'la migración reescribe un texto que editó la clienta');

        $tras = $page->body;
        $migration->up();
        $page->refresh();
        $this->assertSame($tras, $page->body, 'la migración no es idempotente');
    }
}
