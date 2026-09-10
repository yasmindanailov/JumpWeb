<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Models\Testimonial;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL ORDEN DE LAS OCHO SECCIONES DE LA PORTADA** (`DECISIONES #495`,
 * `specs/rediseno-desde-canvas.md` §5.1).
 *
 * ❗❗❗ **El orden lo manda el mockup, y está verificado contra DOS fuentes independientes del canvas**
 * (2026-09-10):
 *
 *  1. **`Portada PJP`**, que es el entregable: sus ocho rótulos salen en este orden de documento, y
 *     se comprobó con control que **ningún `position: absolute` los recoloca** —o sea que el orden
 *     de documento ES el orden visual—.
 *  2. **`Marco Portada PJP`**, que lleva la numeración en datos: `01 Zonas · 03 Qué hay dentro ·
 *     05 Antes de venir · 07 Visítanos · 08 Dudas`. Las tres que faltan (02, 04, 06) son las que ese
 *     artboard excluye a propósito, porque Tarifas y Cumpleaños son **sección y página** y desde el
 *     menú apuntan a la página.
 *
 * ⚠️ **`Landing PJP Modos` NO cuenta como fuente de orden** aunque tenga su propia numeración
 * (`01 Entradas · 02 Zonas · 03 Cumpleaños…`): `[owner]` lo dejó dicho —*«de esa maqueta solo
 * sacaremos la sección de reseñas»*— y `idioma-visual-heredado.md` lo registra. Mirarla y creerle es
 * la forma de reordenar mal la portada con una fuente en la mano.
 *
 * ⚠️⚠️ **Y esto REVIERTE el orden de `#314`** (`[DECIDIDO owner, 2026-09-01]`: «entradas →
 * cumpleaños → el parque → ubicación → normas → dudas»). No es una contradicción: aquella decisión
 * es del carril de diseño ANTERIOR y `#469` adoptó el canvas entero. Está escrito para que nadie lea
 * el cambio como un descuido **ni lo «arregle» devolviéndolo**.
 *
 * ❗❗ **POR QUÉ ESTA GUARDA EXISTE.** Reordenar secciones **no rompe nada**: los anclas siguen
 * existiendo, `--hero-air` cuelga de `.hero + .section` y se muda solo, y las demás guardas acotan
 * por `id` —así que la suite entera pasó en verde con el orden viejo y pasa con el nuevo—. Lo único
 * que puede cazar un bloque descolocado es una guarda que sepa cuál es el orden bueno.
 */
class HomeSectionOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El orden del mockup, de arriba abajo. La clave es el `id` de la sección y el valor su rótulo,
     * para que el mensaje de fallo diga de qué sección habla y no solo un identificador.
     */
    private const ORDEN = [
        'zones' => '01 · Para quién',
        'pricing' => '02 · Cuánto',
        'rides-section' => '03 · Qué hay dentro',
        'events' => '04 · Cumpleaños',
        'before' => '05 · Antes de venir',
        'reviews' => '06 · Reseñas',
        'info' => '07 · Visítanos',
        'faq' => '08 · Dudas',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);

        // ⚠️⚠️ **La 06 «Reseñas» NO se pinta sin opiniones, y el seeder no las siembra A PROPÓSITO**
        // (`#490`: alimenta también el arranque en frío de producción, y ahí unas opiniones
        // inventadas se publicarían como reales). Sin sembrarlas aquí, este fichero compararía
        // **siete** secciones y llamaría «en orden» a una portada a la que le falta una.
        Testimonial::query()->create([
            'text' => ['es' => 'Una opinión para que la sección exista.'],
            'author' => 'Persona',
            'rating' => 5,
            'published_at' => now()->subMonth(),
            'position' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * **Guarda de la guarda.** Sin esto, un cambio de marcado que dejara de emitir `<section id=…>`
     * haría que el escáner devolviera una lista vacía y el caso de abajo pasaría **comparando nada
     * con nada** — que es como este repo perdió `#113`.
     */
    public function test_the_scan_finds_the_sections(): void
    {
        $encontradas = $this->ordenServido();

        $this->assertNotEmpty($encontradas, 'el escáner no ve ninguna sección: vigilaría el vacío');
        $this->assertContains('faq', $encontradas, 'el escáner no reconoce una sección conocida');
    }

    /**
     * ❗❗❗ **LAS OCHO, EN EL ORDEN DEL MOCKUP.**
     *
     * ⚠️ Se compara la **secuencia completa**, no «que zones vaya antes que pricing». Con
     * comprobaciones por pares, mover un bloque dos sitios más abajo puede seguir cumpliendo todas
     * las parejas que alguien se acordó de escribir.
     */
    public function test_the_eight_sections_are_in_the_mockups_order(): void
    {
        $esperado = array_keys(self::ORDEN);
        $servido = array_values(array_filter($this->ordenServido(), fn ($id) => isset(self::ORDEN[$id])));

        $this->assertSame(
            $esperado,
            $servido,
            "El orden de las secciones de la portada ya no es el del mockup.\n".
            '▶ Esperado: '.$this->conRotulos($esperado)."\n".
            '▶ Servido:  '.$this->conRotulos($servido)."\n".
            "▶ Verificado contra `Portada PJP` y `Marco Portada PJP` (`#495`). Si el canvas ha\n".
            '  cambiado, actualiza `ORDEN` diciendo de dónde sale el orden nuevo.'
        );
    }

    /**
     * **Las ocho se pintan**, y no solo van en orden.
     *
     * ⚠️ Es la otra mitad: una sección que desaparece **también** deja el resto «en orden», así que
     * sin este caso el de arriba pasaría en verde con media portada fuera.
     */
    public function test_every_section_is_actually_rendered(): void
    {
        $servido = $this->ordenServido();

        foreach (self::ORDEN as $id => $rotulo) {
            $this->assertContains($id, $servido,
                "La sección «{$rotulo}» (`#{$id}`) ha desaparecido de la portada.");
        }
    }

    /**
     * ❗❗ **`#zones` es la primera, y de eso depende el aire bajo el hero.**
     *
     * `--hero-air` se aplica con `.hero + .section`, así que **se muda solo** al reordenar — pero
     * solo si la nueva primera sigue siendo una `.section` hermana del hero. Si alguien la envuelve
     * en un `<div>` o le cambia la clase, el aire se pierde y **no falla nada**: la portada solo
     * queda apretada bajo el hero, que es justo lo que `#303` tardó tres opciones en calibrar.
     */
    public function test_the_first_section_is_the_one_that_gets_the_hero_air(): void
    {
        $html = $this->home();

        $hero = strpos($html, 'class="hero');
        $this->assertNotFalse($hero, 'no se encuentra el hero: este caso vigilaría el vacío');

        $primera = $this->ordenServido()[0] ?? null;

        $this->assertSame('zones', $primera,
            "La primera sección de la portada ya no es `#zones`.\n".
            '▶ `--hero-air` cuelga de `.hero + .section`: se muda solo, pero el aire bajo el hero '.
            'está calibrado (`#303`) para lo que venga primero.');

        // Y sigue siendo `.section`, que es lo que hace que la regla la alcance.
        $this->assertMatchesRegularExpression(
            '~<section id="zones"[^>]*class="[^"]*\bsection\b~', $html,
            '`#zones` ha dejado de ser `.section`: `.hero + .section` ya no la alcanza y el aire bajo '.
            'el hero desaparece sin que falle nada.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Los `id` de las secciones de primer nivel, en el orden en que se sirven.
     *
     * ⚠️ **Se lee del marcado servido y no de la plantilla**: lo que ordena la página es lo que sale,
     * y una sección puede estar dentro de un `@if` que no entra.
     *
     * @return list<string>
     */
    private function ordenServido(): array
    {
        preg_match_all('/<section id="([a-z-]+)"/', $this->home(), $m);

        return $m[1];
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    /** @param  list<string>  $ids */
    private function conRotulos(array $ids): string
    {
        return implode(' → ', array_map(fn ($id) => self::ORDEN[$id] ?? $id, $ids));
    }
}
