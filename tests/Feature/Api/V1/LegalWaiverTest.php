<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Content\Models\Page;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Platform\Models\Setting;
use Tests\Feature\Api\ApiTestCase;

/**
 * `GET /api/v1/legal/waiver` — el texto firmable VIGENTE, en el idioma negociado
 * (`specs/waiver-probatorio.md` §4.2, §4.4). Público: quien se da de alta aún no tiene sesión.
 *
 * Lo que publica es el SNAPSHOT, nunca la página del CMS; y «no hay nada que firmar» es una
 * respuesta legítima (`document: null`), no un error.
 */
class LegalWaiverTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/legal/waiver';

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
    }

    private function publish(array $locales = ['es']): void
    {
        $texts = [];
        foreach ($locales as $locale) {
            $texts[$locale] = ['title' => "Waiver {$locale}", 'body' => [['h' => "Riesgo {$locale}", 'p' => "Texto {$locale}."]]];
        }
        app(LegalDocumentPublisher::class)->publish('waiver', $texts);
    }

    public function test_outside_internal_mode_there_is_nothing_to_sign(): void
    {
        $this->publish();

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertExactJson(['mode' => 'externo', 'document' => null]);
    }

    public function test_in_internal_mode_without_a_published_version_document_is_null(): void
    {
        $this->mode('interno');

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertExactJson(['mode' => 'interno', 'document' => null]);
    }

    public function test_it_publishes_the_current_snapshot_in_the_negotiated_locale(): void
    {
        $this->mode('interno');
        $this->publish(['es', 'fr']);
        $this->publish(['es', 'fr']); // v2: la vigente

        $response = $this->withHeader('Accept-Language', 'fr')->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200);

        $this->assertSame('interno', $response->json('mode'));
        $this->assertSame(2, $response->json('document.version'));
        $this->assertSame('fr', $response->json('document.locale'));
        $this->assertSame('Waiver fr', $response->json('document.title'));
        $this->assertSame([['h' => 'Riesgo fr', 'p' => 'Texto fr.']], $response->json('document.sections'));
        $this->assertIsInt($response->json('document.id'));
    }

    public function test_a_locale_without_its_own_text_falls_back_to_spanish(): void
    {
        $this->mode('interno');
        $this->publish(['es']);

        $response = $this->withHeader('Accept-Language', 'en')->getJson(self::PATH)->assertOk();

        $this->assertSame('es', $response->json('document.locale'));
    }

    /** §4.2 — lo que se enseña es el snapshot: la página del CMS puede decir otra cosa. */
    public function test_it_publishes_the_snapshot_not_the_cms_page(): void
    {
        $this->mode('interno');
        Page::create(['slug' => 'waiver', 'title' => ['es' => 'Página'], 'body' => ['es' => [['h' => 'Borrador', 'p' => 'Texto del CMS, sin publicar.']]], 'is_active' => true]);
        $this->publish(['es']);

        $response = $this->getJson(self::PATH)->assertOk();

        $this->assertSame('Texto es.', $response->json('document.sections.0.p'));
        $this->assertStringNotContainsString('Texto del CMS', $response->getContent());
    }
}
