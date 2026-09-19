<?php

namespace Tests\Feature\Api;

use App\Domain\Content\Models\VenueRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Las NORMAS del menú de hechos** (F5 · T3, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Lo que se vigila es lo que una landing no puede comprobar por su cuenta: que el **orden sea el del
 * recorrido de una visita** y no el de la tabla, que una norma **desactivada no vuelva a la web** por la
 * puerta de atrás, y que el texto llegue **en un idioma**, resuelto por el servidor.
 */
class RulesFactsTest extends TestCase
{
    use RefreshDatabase;

    private function norma(array $atributos): VenueRule
    {
        return VenueRule::query()->create([
            'name' => ['es' => 'Una norma'],
            'is_active' => true,
            'position' => 1,
            ...$atributos,
        ]);
    }

    /**
     * **El orden es el del RECORRIDO, no el de la tabla.**
     *
     * ⚠️⚠️ La primera versión ordenaba con `sortBy([cierre, cierre])` y salía **al revés**: `inside`
     * primero y `before` al final, o sea la visita contada de atrás adelante. El JSON era válido, las
     * normas estaban todas y no falló nada — lo vio una llamada real. Por eso el orden se comprueba por
     * NOMBRE y no por «hay diez normas».
     */
    public function test_the_order_is_the_visit_not_the_table(): void
    {
        $this->norma(['name' => ['es' => 'Dentro'], 'moment' => 'inside', 'position' => 1]);
        $this->norma(['name' => ['es' => 'Antes'], 'moment' => 'before', 'position' => 2]);
        $this->norma(['name' => ['es' => 'Puerta'], 'moment' => 'gate', 'position' => 3]);
        $this->norma(['name' => ['es' => 'Sin situar'], 'moment' => null, 'position' => 0]);

        $nombres = array_column($this->getJson('/api/v1/rules?lang=es')->assertOk()->json('rules'), 'name');

        $this->assertSame(['Antes', 'Puerta', 'Dentro', 'Sin situar'], $nombres);
    }

    public function test_within_a_moment_the_panel_decides(): void
    {
        $this->norma(['name' => ['es' => 'Segunda'], 'moment' => 'gate', 'position' => 20]);
        $this->norma(['name' => ['es' => 'Primera'], 'moment' => 'gate', 'position' => 10]);

        $nombres = array_column($this->getJson('/api/v1/rules?lang=es')->assertOk()->json('rules'), 'name');

        $this->assertSame(['Primera', 'Segunda'], $nombres);
    }

    /**
     * **Una norma desactivada es una norma RETIRADA.** Publicarla por la API la devolvería a la web por la
     * puerta de atrás, y quien la desactivó en el panel creería que ya no está.
     */
    public function test_a_deactivated_rule_does_not_come_back_through_the_api(): void
    {
        $this->norma(['name' => ['es' => 'Vigente']]);
        $this->norma(['name' => ['es' => 'Retirada'], 'is_active' => false]);

        $nombres = array_column($this->getJson('/api/v1/rules?lang=es')->assertOk()->json('rules'), 'name');

        $this->assertSame(['Vigente'], $nombres);
    }

    /**
     * **El texto llega en UN idioma y lo resuelve el servidor**, con su cadena de respaldo. El cliente
     * recibe una cadena, no un mapa de idiomas que tendría que aprender a recorrer.
     */
    public function test_the_text_arrives_in_one_language_resolved_by_the_server(): void
    {
        $this->norma(['name' => ['es' => 'Calcetines', 'en' => 'Socks'], 'reason' => ['es' => 'Por higiene']]);

        $es = $this->getJson('/api/v1/rules?lang=es')->assertOk();
        $es->assertJsonPath('lang', 'es');
        $es->assertJsonPath('rules.0.name', 'Calcetines');
        $es->assertJsonPath('rules.0.reason', 'Por higiene');

        $en = $this->getJson('/api/v1/rules?lang=en')->assertOk();
        $en->assertJsonPath('rules.0.name', 'Socks');
        // ⚠️ Sin traducir, el RESPALDO: el cliente no tiene que saber que faltaba.
        $en->assertJsonPath('rules.0.reason', 'Por higiene');
    }

    public function test_the_language_is_required_and_validated(): void
    {
        $this->getJson('/api/v1/rules')->assertStatus(422);
        $this->getJson('/api/v1/rules?lang=klingon')->assertStatus(422);
    }

    /**
     * **`updated_at` es la norma tocada más recientemente, no la fecha de hoy**: escribir hoy afirmaría una
     * revisión que nadie ha hecho. Sin normas es `null` y no se inventa.
     */
    public function test_updated_at_is_the_last_touched_rule_or_nothing(): void
    {
        $this->getJson('/api/v1/rules?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        $norma = $this->norma([]);

        $this->getJson('/api/v1/rules?lang=es')
            ->assertOk()
            ->assertJsonPath('updated_at', $norma->fresh()->updated_at->toIso8601String());
    }

    /** Lo que la instalación no escribió no viaja, igual que en `/site`. */
    public function test_what_is_not_written_does_not_travel(): void
    {
        $this->norma(['name' => ['es' => 'Pelada'], 'moment' => null]);

        $norma = $this->getJson('/api/v1/rules?lang=es')->assertOk()->json('rules.0');

        $this->assertSame(['name'], array_keys($norma));
    }

    public function test_it_is_publicly_cacheable_per_language(): void
    {
        $respuesta = $this->getJson('/api/v1/rules?lang=es')->assertOk();

        $this->assertStringContainsString('max-age=300', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $respuesta->headers->get('Cache-Control'));

        // El idioma va en la URL justamente para esto: dos idiomas, dos respuestas cacheables distintas.
        $this->assertNotSame(
            (string) $respuesta->headers->get('ETag'),
            (string) $this->getJson('/api/v1/rules?lang=en')->assertOk()->headers->get('ETag'),
            'las dos lenguas comparten `ETag`: una caché serviría una por la otra',
        );
    }
}
