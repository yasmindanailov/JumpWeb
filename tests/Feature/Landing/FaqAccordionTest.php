<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL ACORDEÓN DE LA FAQ NO TIENE TOPE** (auditoría de diseño M8, `DECISIONES #434`).
 *
 * Se animaba `max-height: 0 → 240px`: anima layout y además es un TOPE de contenido. Hoy las
 * respuestas miden 24–48 px, pero las escribe el panel y la primera larga se habría cortado sin
 * que fallara nada. La receta que anima sin tope es la rejilla `grid-template-rows: 0fr → 1fr`, y
 * necesita DOS envoltorios: el recorte no puede llevar relleno (estirado a 0 px conservaría su
 * relleno y asomarían 14 px), así que el aire va en el párrafo de dentro.
 *
 * Lo que este fichero vigila: que `.faq__a` anime la rejilla y no `max-height`, que el estado
 * abierto no vuelva a declarar un tope, y que el marcado lleve el envoltorio que recorta.
 */
class FaqAccordionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_answer_animates_grid_rows_and_not_max_height(): void
    {
        $rules = $this->rules();

        $this->assertArrayHasKey('.faq__a', $rules, 'la regla `.faq__a` ya no está en `landing.css`');
        $this->assertArrayHasKey('.faq__item.open .faq__a', $rules, 'la regla del estado abierto ya no está');

        $cerrada = $rules['.faq__a'];
        $abierta = $rules['.faq__item.open .faq__a'];

        $this->assertMatchesRegularExpression('/display:\s*grid/', $cerrada, '`.faq__a` ya no es una rejilla');
        $this->assertMatchesRegularExpression('/grid-template-rows:\s*0fr/', $cerrada, '`.faq__a` no arranca en `0fr`');
        $this->assertMatchesRegularExpression('/grid-template-rows:\s*1fr/', $abierta, 'abierta no llega a `1fr`');
        $this->assertMatchesRegularExpression('/transition:[^;]*grid-template-rows/', $cerrada, 'la transición ya no anima la rejilla');

        foreach (['.faq__a' => $cerrada, '.faq__item.open .faq__a' => $abierta] as $selector => $body) {
            $this->assertDoesNotMatchRegularExpression(
                '/max-height/',
                $body,
                "`{$selector}` vuelve a usar `max-height`: anima layout y pone un tope a respuestas que ".
                'escribe el panel (auditoría M8).',
            );
        }

        $this->assertArrayHasKey('.faq__a-in', $rules, 'el envoltorio que recorta no tiene regla');
        $this->assertMatchesRegularExpression('/overflow:\s*hidden/', $rules['.faq__a-in'], 'el envoltorio no recorta');
        $this->assertDoesNotMatchRegularExpression(
            '/padding/',
            $rules['.faq__a-in'],
            'el envoltorio que recorta lleva relleno: estirado a 0 px lo conserva y asoma cerrado.',
        );
    }

    public function test_every_answer_in_the_home_carries_the_clipping_wrapper(): void
    {
        Faq::query()->create(['question' => ['es' => '¿Hay parking?'], 'answer' => ['es' => 'Sí, dos horas.'], 'position' => 1, 'is_active' => true]);
        Faq::query()->create(['question' => ['es' => '¿Y si llueve?'], 'answer' => ['es' => 'Mejor: es interior.'], 'position' => 2, 'is_active' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $respuestas = preg_match_all('/class="faq__a"/', $html);
        $envoltorios = preg_match_all('/class="faq__a-in"/', $html);
        $parrafos = preg_match_all('/class="faq__a-p"/', $html);

        $this->assertGreaterThanOrEqual(2, $respuestas, 'la portada no pinta las respuestas sembradas');
        $this->assertSame($respuestas, $envoltorios, 'una respuesta va sin su envoltorio de recorte: el texto asomará cerrada');
        $this->assertSame($respuestas, $parrafos, 'una respuesta va sin el párrafo que lleva el aire');
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        $css = (string) file_get_contents(base_path('public/css/landing.css'));
        $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $blind, $matches, PREG_SET_ORDER);

        $out = [];
        foreach ($matches as $rule) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }
            $out[$selector] = ($out[$selector] ?? '').' '.trim($rule[2]);
        }

        return $out;
    }
}
