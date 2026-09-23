<?php

namespace Tests\Feature\Api;

use App\Domain\Content\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Las DUDAS del menú de hechos** (F5, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Lo que se vigila es lo que una landing no puede comprobar por su cuenta: que una duda **desactivada no
 * vuelva** por la puerta de atrás, que el texto llegue **en un idioma** resuelto por el servidor, que el
 * orden **no baraje** entre dos peticiones idénticas, y que **una duda a medias no se publique** —ni por
 * clave ausente ni por cadena vacía—.
 */
class FaqsFactsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function duda(array $atributos = []): Faq
    {
        return Faq::query()->create([
            'question' => ['es' => 'Una pregunta'],
            'answer' => ['es' => 'Una respuesta'],
            'is_active' => true,
            'position' => 1,
            ...$atributos,
        ]);
    }

    /**
     * @return list<string>
     */
    private function preguntas(string $lang = 'es'): array
    {
        return array_column(
            $this->getJson("/api/v1/faqs?lang={$lang}")->assertOk()->json('faqs'),
            'question',
        );
    }

    public function test_the_panel_decides_the_order(): void
    {
        $this->duda(['question' => ['es' => 'Segunda'], 'position' => 20]);
        $this->duda(['question' => ['es' => 'Primera'], 'position' => 10]);

        $this->assertSame(['Primera', 'Segunda'], $this->preguntas());
    }

    /**
     * **Dos dudas EMPATADAS no pueden barajar.** `position` no es única —el panel no lo impide—, y sin
     * desempate el orden de un empate lo decide el motor: dos peticiones idénticas podrían devolver dos
     * cuerpos distintos, y con ellos dos `ETag` distintos, sin que nadie tocara el panel. El desempate es
     * `id`, o sea el orden en que se crearon.
     */
    public function test_a_tie_in_position_is_broken_by_id_and_does_not_shuffle(): void
    {
        $primera = $this->duda(['question' => ['es' => 'Nació antes'], 'position' => 5]);
        $segunda = $this->duda(['question' => ['es' => 'Nació después'], 'position' => 5]);

        $this->assertTrue($primera->id < $segunda->id, 'el fixture no construye el empate que dice medir');

        $this->assertSame(['Nació antes', 'Nació después'], $this->preguntas());
        $this->assertSame(['Nació antes', 'Nació después'], $this->preguntas());
    }

    /**
     * **Una duda desactivada es una duda RETIRADA.** Quien la desactivó en el panel creería que ya no está.
     */
    public function test_a_deactivated_faq_does_not_come_back_through_the_api(): void
    {
        $this->duda(['question' => ['es' => 'Vigente']]);
        $this->duda(['question' => ['es' => 'Retirada'], 'is_active' => false]);

        $this->assertSame(['Vigente'], $this->preguntas());
    }

    public function test_the_text_arrives_in_one_language_resolved_by_the_server(): void
    {
        $this->duda([
            'question' => ['es' => '¿Hay taquillas?', 'en' => 'Are there lockers?'],
            'answer' => ['es' => 'Sí, en la entrada'],
        ]);

        $es = $this->getJson('/api/v1/faqs?lang=es')->assertOk();
        $es->assertJsonPath('lang', 'es');
        $es->assertJsonPath('faqs.0.question', '¿Hay taquillas?');

        $en = $this->getJson('/api/v1/faqs?lang=en')->assertOk();
        $en->assertJsonPath('faqs.0.question', 'Are there lockers?');
        // Sin traducir, el RESPALDO: el cliente no tiene que saber que faltaba.
        $en->assertJsonPath('faqs.0.answer', 'Sí, en la entrada');
    }

    /**
     * ❗❗ **EL FILTRO DE «A MEDIAS» VA DESPUÉS DEL RESPALDO, Y ÉSTE ES EL CASO QUE LO FIJA.**
     *
     * Las doce dudas de esta instalación están escritas **solo en español** y sus claves `en`/`fr` están
     * AUSENTES. Una versión que mirase si el idioma pedido está relleno **antes** de pasar por `tr()`
     * dejaría este recurso entero vacío en inglés y en francés —doce dudas dentro, cero publicadas— y el
     * JSON seguiría siendo válido, así que nada más lo cazaría.
     */
    public function test_a_faq_written_only_in_spanish_still_travels_in_english(): void
    {
        $this->duda(['question' => ['es' => 'Solo en español'], 'answer' => ['es' => 'También']]);

        $this->assertSame(['Solo en español'], $this->preguntas('en'));
        $this->assertSame(['Solo en español'], $this->preguntas('fr'));
    }

    /**
     * **Una pregunta sin respuesta no viaja**: publicaría una duda que el negocio no contesta. Y hay dos
     * formas de estar sin respuesta que el código trata distinto — la clave AUSENTE, que el respaldo
     * resuelve, y la CADENA VACÍA, que `??` entrega tal cual porque el panel la escribe cuando alguien
     * rellena y BORRA (`§4.1.ter`). Las dos tienen que quedarse fuera.
     */
    public function test_a_half_written_faq_does_not_travel_by_absence_or_by_empty_string(): void
    {
        $this->duda(['question' => ['es' => 'Publicable'], 'position' => 1]);
        $this->duda(['question' => ['es' => 'Sin respuesta'], 'answer' => [], 'position' => 2]);
        $this->duda(['question' => ['es' => 'Respuesta borrada'], 'answer' => ['es' => ''], 'position' => 3]);
        $this->duda(['question' => ['es' => '   '], 'answer' => ['es' => 'Huérfana'], 'position' => 4]);

        $this->assertSame(['Publicable'], $this->preguntas());
    }

    public function test_the_language_is_required_and_validated(): void
    {
        $this->getJson('/api/v1/faqs')->assertStatus(422);
        $this->getJson('/api/v1/faqs?lang=klingon')->assertStatus(422);
    }

    /**
     * **`updated_at` es de lo SERVIDO, no de la tabla**: una duda que no se publica no puede mover la
     * fecha de lo que sí. Sin dudas publicables es `null` y no se inventa una revisión que nadie hizo.
     */
    public function test_updated_at_covers_what_is_served_and_nothing_else(): void
    {
        $this->getJson('/api/v1/faqs?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        // Una duda a medias, y NADA MÁS: no se publica, así que tampoco fecha nada.
        $this->duda(['answer' => ['es' => '']]);
        $this->getJson('/api/v1/faqs?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        $publicable = $this->duda([]);
        $this->getJson('/api/v1/faqs?lang=es')
            ->assertOk()
            ->assertJsonPath('updated_at', $publicable->fresh()->updated_at->toIso8601String());
    }

    /** Lo que viaja de una duda son sus dos campos y nada más: el `id` y la posición son del panel. */
    public function test_a_faq_travels_as_its_two_fields(): void
    {
        $this->duda([]);

        $duda = $this->getJson('/api/v1/faqs?lang=es')->assertOk()->json('faqs.0');

        $this->assertSame(['question', 'answer'], array_keys($duda));
    }

    public function test_it_is_publicly_cacheable_per_language(): void
    {
        $this->duda([]);

        $respuesta = $this->getJson('/api/v1/faqs?lang=es')->assertOk();

        $this->assertStringContainsString('max-age=300', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $respuesta->headers->get('Cache-Control'));

        $this->assertNotSame(
            (string) $respuesta->headers->get('ETag'),
            (string) $this->getJson('/api/v1/faqs?lang=en')->assertOk()->headers->get('ETag'),
            'las dos lenguas comparten `ETag`: una caché serviría una por la otra',
        );
    }
}
