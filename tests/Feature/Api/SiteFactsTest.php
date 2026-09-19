<?php

namespace Tests\Feature\Api;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **`GET /api/v1/site` — el primer plato del MENÚ DE HECHOS** (F5 · T1,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1; `#631` el principio, `#639` las redes).
 *
 * Lo que este fichero vigila es lo que hace útil al menú, y que es fácil romper sin que nada falle:
 * que **lo que la instalación no ha rellenado NO viaje**, que los bloques sean SIEMPRE objetos
 * —aunque estén vacíos—, y que las dos direcciones no se confundan.
 */
class SiteFactsTest extends TestCase
{
    use RefreshDatabase;

    private function ajuste(string $clave, string $valor, string $grupo = 'business'): void
    {
        Setting::query()->updateOrCreate(['key' => $clave], ['value' => $valor, 'group' => $grupo]);
        Setting::flushMemo();
    }

    public function test_it_serves_the_identity_and_contact_of_the_installation(): void
    {
        $this->ajuste('business.name', 'Parque de Prueba');
        $this->ajuste('business.nif', 'B12345678');
        $this->ajuste('contact.email', 'hola@parque.test', 'contact');
        $this->ajuste('contact.instagram', 'https://instagram.com/parque', 'social');

        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('identity.name', 'Parque de Prueba')
            ->assertJsonPath('identity.nif', 'B12345678')
            ->assertJsonPath('contact.email', 'hola@parque.test')
            ->assertJsonPath('social.instagram', 'https://instagram.com/parque');
    }

    /**
     * **Lo que no está, no viaja** — el «todo es opcional» de `#631` llevado al JSON.
     *
     * ⚠️ Y un valor de solo espacios cuenta como no rellenado: en el panel es exactamente igual de fácil
     * dejar un campo vacío que dejarlo con un espacio, y una landing que pinte `«Síguenos en »` por eso
     * no tiene forma de defenderse.
     */
    public function test_what_is_not_filled_does_not_travel(): void
    {
        $this->ajuste('business.name', 'Parque de Prueba');
        $this->ajuste('contact.tiktok', '   ', 'social');

        $respuesta = $this->getJson('/api/v1/site')->assertOk();

        $respuesta->assertJsonMissingPath('social.tiktok');
        $respuesta->assertJsonMissingPath('contact.whatsapp');
        $respuesta->assertJsonMissingPath('identity.legal_name');
    }

    /**
     * **Los seis bloques van SIEMPRE, y son objetos aunque lleguen vacíos.**
     *
     * ⚠️⚠️ La trampa que esto cubre ya se pagó en la T1 de F4: un `array` de PHP vacío sale en JSON como
     * `[]`, no como `{}`. Con una instalación recién sembrada, `seo` llegaba como LISTA y el contrato decía
     * objeto — o sea que el tipo de un campo dependía de si alguien había rellenado el panel.
     */
    public function test_every_block_is_always_an_object(): void
    {
        $datos = $this->getJson('/api/v1/site')->assertOk()->json();

        foreach (['identity', 'address', 'contact', 'social', 'legal', 'seo'] as $bloque) {
            $this->assertArrayHasKey($bloque, $datos, "falta el bloque «{$bloque}»");
        }

        // `json()` convierte los dos a array, así que se mira el JSON CRUDO: es donde se ve la diferencia.
        $crudo = $this->getJson('/api/v1/site')->getContent();

        $this->assertStringContainsString('"seo":{}', (string) $crudo, 'un bloque vacío sale como lista, no como objeto');
    }

    /**
     * **La dirección del parque y el domicilio fiscal son cosas distintas.**
     *
     * El panel lo dice —«puede diferir de la del parque»— y en la instalación real, medido, son municipios
     * distintos. La primera versión de este recurso sirvió el fiscal dentro de `address`: una landing que
     * pintara su bloque de contacto habría mandado a sus clientas a la gestoría.
     */
    public function test_the_venue_address_is_not_the_fiscal_one(): void
    {
        $this->ajuste('address.line1', 'Ctra. del parque, km 1', 'contact');
        $this->ajuste('business.address', 'Calle de la gestoría, 13');

        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('address.line1', 'Ctra. del parque, km 1')
            ->assertJsonPath('legal.fiscal_address', 'Calle de la gestoría, 13')
            ->assertJsonMissingPath('address.fiscal_address');
    }

    /**
     * **Se cachea en público y revalida con `ETag`** (`PERF-02`): no depende de quién mira, así que una
     * landing que lo pida en cada visita no tiene que pagar la consulta cada vez.
     */
    public function test_it_is_publicly_cacheable_and_revalidates(): void
    {
        $primera = $this->getJson('/api/v1/site')->assertOk();

        $cache = (string) $primera->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=300', $cache);

        $etag = (string) $primera->headers->get('ETag');
        $this->assertNotSame('', $etag, 'sin `ETag` no hay revalidación posible');

        $this->getJson('/api/v1/site', ['If-None-Match' => $etag])->assertStatus(304);
    }

    /**
     * **No depende de quién mira**: la misma respuesta para un anónimo y para alguien con sesión. Si algún
     * día dejara de serlo, dejaría de poder cachearse en público — y la caché se enteraría tarde.
     */
    public function test_it_does_not_depend_on_who_is_looking(): void
    {
        $this->ajuste('business.name', 'Parque de Prueba');

        $anonimo = $this->getJson('/api/v1/site')->assertOk()->json();

        $this->be(User::factory()->create());

        $this->assertSame($anonimo, $this->getJson('/api/v1/site')->assertOk()->json());
    }
}
