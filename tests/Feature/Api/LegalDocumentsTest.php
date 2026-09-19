<?php

namespace Tests\Feature\Api;

use App\Domain\Content\Models\Page;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Los TEXTOS LEGALES del menú de hechos** (F5 · T4, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Dos cosas que una landing no puede comprobar por su cuenta y que aquí se clavan: que el cuerpo llegue
 * **con los marcadores resueltos** —o publicaría «El responsable es :legal_name» en su política de
 * privacidad— y que lo que viaja sea **la página**, con la versión firmada al lado como dato y no en su
 * lugar.
 */
class LegalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function pagina(string $slug, array $cuerpo, bool $activa = true): Page
    {
        return Page::query()->create([
            'slug' => $slug,
            'title' => ['es' => 'Título de '.$slug],
            'body' => ['es' => $cuerpo],
            'is_active' => $activa,
        ]);
    }

    /**
     * **El cuerpo viaja INTERPOLADO.** Es el caso que justifica todo lo demás.
     */
    public function test_the_body_arrives_with_the_placeholders_resolved(): void
    {
        Setting::query()->updateOrCreate(['key' => 'business.legal_name'], ['value' => 'Parque de Prueba S.L.', 'group' => 'business']);
        Setting::query()->updateOrCreate(['key' => 'business.nif'], ['value' => 'B99999999', 'group' => 'business']);
        Setting::flushMemo();

        $this->pagina('privacidad', [
            ['h' => 'Responsable', 'p' => 'El responsable es :legal_name, con NIF :legal_nif.'],
        ]);

        $seccion = $this->getJson('/api/v1/legal/documents/privacidad?lang=es')->assertOk()->json('sections.0');

        $this->assertSame('El responsable es Parque de Prueba S.L., con NIF B99999999.', $seccion['p']);
        $this->assertStringNotContainsString(':legal_', $seccion['p']);
    }

    /**
     * **Viaja la PÁGINA, y la versión firmada va al lado como dato.**
     *
     * ⚠️ Medido en la instalación real: el justificante tiene **once** secciones como página —explica— y
     * **una** como versión publicada —el texto al que alguien se obliga—. Servir la versión como si fuera
     * la página quitaría diez secciones de explicación; servir la página como si fuera lo firmado
     * publicaría texto que nadie ha firmado.
     */
    public function test_the_page_travels_and_the_signed_version_only_as_a_fact(): void
    {
        $this->pagina('condiciones', [
            ['h' => 'Uno', 'p' => 'Lo que dice la PÁGINA hoy.'],
            ['h' => 'Dos', 'p' => 'Y una segunda sección que la versión no tiene.'],
        ]);

        LegalDocumentVersion::query()->create([
            'slug' => 'condiciones',
            'locale' => 'es',
            'version' => 3,
            'title' => 'Condiciones',
            'body' => [['h' => 'Uno', 'p' => 'Lo que se FIRMÓ en su día.']],
            'body_hash' => str_repeat('a', 64),
            'published_at' => now(),
        ]);

        $documento = $this->getJson('/api/v1/legal/documents/condiciones?lang=es')->assertOk();

        $documento->assertJsonPath('sections.0.p', 'Lo que dice la PÁGINA hoy.');
        $documento->assertJsonCount(2, 'sections');
        $documento->assertJsonPath('signed_version.version', 3);
    }

    public function test_a_document_without_a_published_version_says_nothing_about_it(): void
    {
        $this->pagina('cookies', [['h' => 'Cookies', 'p' => 'Texto.']]);

        $this->getJson('/api/v1/legal/documents/cookies?lang=es')
            ->assertOk()
            ->assertJsonMissingPath('signed_version');
    }

    /** Una página desactivada es una página retirada: no vuelve por la API. */
    public function test_a_deactivated_page_is_not_served(): void
    {
        $this->pagina('aviso-legal', [['p' => 'Texto.']], activa: false);

        $this->getJson('/api/v1/legal/documents/aviso-legal?lang=es')->assertStatus(404);
        $this->getJson('/api/v1/legal/documents?lang=es')->assertOk()->assertJsonCount(0, 'documents');
    }

    /**
     * **El índice no lleva el cuerpo.** Es lo que hace útil tener dos rutas: un pie con cinco enlaces no
     * necesita descargarse cinco documentos legales enteros.
     */
    public function test_the_index_carries_titles_and_not_bodies(): void
    {
        $this->pagina('privacidad', [['p' => 'Un texto larguísimo.']]);

        $indice = $this->getJson('/api/v1/legal/documents?lang=es')->assertOk();

        $indice->assertJsonPath('documents.0.key', 'privacidad');
        $indice->assertJsonPath('documents.0.title', 'Título de privacidad');
        $indice->assertJsonMissingPath('documents.0.sections');
    }

    public function test_an_unknown_key_is_a_404(): void
    {
        $this->getJson('/api/v1/legal/documents/lo-que-sea?lang=es')->assertStatus(404);
    }

    public function test_the_language_is_required(): void
    {
        $this->getJson('/api/v1/legal/documents')->assertStatus(422);
        $this->getJson('/api/v1/legal/documents/privacidad')->assertStatus(422);
    }

    /**
     * ⚠️ **`/legal/waiver` sigue diciendo lo suyo.** Es el RÉGIMEN del justificante para el cajón, no un
     * documento del menú; por eso los documentos cuelgan de `/legal/documents` y no de `/legal/{clave}`,
     * donde la ruta genérica lo habría ensombrecido.
     */
    public function test_the_waiver_regime_route_is_untouched(): void
    {
        $respuesta = $this->getJson('/api/v1/legal/waiver')->assertOk();

        $this->assertArrayHasKey('mode', $respuesta->json());
    }
}
