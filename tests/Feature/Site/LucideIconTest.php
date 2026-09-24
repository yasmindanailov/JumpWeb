<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Services\Lucide;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * **Los iconos del sistema nuevo salen IGUAL que en el diseño** (`DECISIONES #683`, `#686`).
 *
 * El diseño los pinta con su `Icon.jsx`; el producto, con `<x-lucide>` y {@see Lucide}. Lo que aquí se
 * prueba es lo que decide el píxel: que el set versionado es exactamente el paquete de npm que dice el
 * manifiesto, que las sustituciones son las del diseño y solo en su primera aparición, y que el componente
 * no mete ni un espacio alrededor —junto a un texto, un salto de línea se pinta como un espacio—.
 * La comparación de píxeles contra el diseño la hace `scripts/pixel.mjs` (`isla-y-landing-nueva.md` §4.8).
 */
class LucideIconTest extends TestCase
{
    protected function tearDown(): void
    {
        Lucide::forget();
        parent::tearDown();
    }

    public function test_the_vendored_set_is_exactly_what_its_manifest_says(): void
    {
        $dir = resource_path('icons/lucide');
        $manifiesto = json_decode((string) file_get_contents($dir.'/MANIFIESTO.json'), true);

        $this->assertSame(Lucide::VERSION, $manifiesto['version'], 'La versión del código y la del set versionado no pueden ir por separado.');

        $nombres = array_values(array_filter(scandir($dir.'/icons') ?: [], fn (string $f) => str_ends_with($f, '.svg')));
        sort($nombres, SORT_STRING);
        $this->assertCount($manifiesto['iconos'], $nombres);

        // La misma huella que calcula `scripts/traer-lucide.py`: nombre y sha256 de cada SVG, en orden.
        $h = hash_init('sha256');
        foreach ($nombres as $n) {
            hash_update($h, $n."\0".hash_file('sha256', $dir.'/icons/'.$n, true));
        }
        $this->assertSame($manifiesto['sha256_del_set'], hash_final($h), 'Un SVG del set no es el del paquete de npm: se corre otra vez `scripts/traer-lucide.py`, no se edita a mano.');

        $this->assertStringContainsString('ISC', (string) file_get_contents($dir.'/LICENSE'), 'La licencia viaja con el set.');
    }

    public function test_it_replaces_only_the_first_occurrence_as_the_design_does(): void
    {
        $svg = '<svg width="24" height="24" fill="none"><rect width="24" height="24" fill="none"/></svg>';

        $this->assertSame(
            '<svg width="100%" height="100%" fill="none"><rect width="24" height="24" fill="none"/></svg>',
            Lucide::transform($svg),
        );
        $this->assertSame(
            '<svg width="100%" height="100%" fill="currentColor"><rect width="24" height="24" fill="none"/></svg>',
            Lucide::transform($svg, fill: true),
        );
    }

    public function test_a_real_icon_comes_out_sized_by_its_box(): void
    {
        $svg = Lucide::svg('ticket');

        $this->assertStringContainsString('class="lucide lucide-ticket"', $svg);
        $this->assertStringContainsString('width="100%"', $svg);
        $this->assertStringContainsString('height="100%"', $svg);
        $this->assertStringNotContainsString('width="24"', $svg);
        $this->assertStringContainsString('fill="none"', $svg, 'Sin `fill`, el dibujo sigue siendo de trazo.');
        $this->assertStringContainsString('fill="currentColor"', Lucide::svg('star', fill: true));
    }

    public function test_an_invalid_or_missing_name_renders_nothing(): void
    {
        foreach (['../../.env', 'Ticket', 'icons/ticket', 'ticket.svg', '', 'no-existe-este-icono'] as $nombre) {
            $this->assertSame('', Lucide::svg($nombre), "«{$nombre}» no puede salir de la carpeta ni pintar nada.");
        }
    }

    public function test_the_component_leaves_no_whitespace_around_the_icon(): void
    {
        $html = Blade::render('<p>a<x-lucide name="ticket" :size="18" />b</p>');

        $this->assertStringStartsWith('<p>a<span', $html);
        $this->assertStringEndsWith('</span>b</p>', $html);
    }

    public function test_the_component_renders_the_design_icon_box(): void
    {
        $html = Blade::render('<x-lucide name="ticket" :size="18" />');

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringNotContainsString('role="img"', $html);
        $this->assertStringContainsString('display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; width: 18px; height: 18px; color: currentColor; line-height: 0;', $html);
    }

    public function test_a_label_makes_it_a_named_image(): void
    {
        $html = Blade::render('<x-lucide name="star" fill label="4,9 en Google" />');

        $this->assertStringContainsString('role="img" aria-label="4,9 en Google"', $html);
        $this->assertStringNotContainsString('aria-hidden', $html);
        $this->assertStringContainsString('fill="currentColor"', $html);
    }

    public function test_a_given_style_adds_to_the_base_one_like_object_assign(): void
    {
        $html = Blade::render('<x-lucide name="ticket" style="margin: 2px" class="x" />');

        $this->assertStringContainsString('width: 20px; height: 20px; color: currentColor; line-height: 0; margin: 2px', $html);
        $this->assertStringContainsString('class="x"', $html);
    }

    public function test_stroke_box_wraps_it_in_a_square_chip_twice_its_size(): void
    {
        $html = Blade::render('<x-lucide name="ticket" :size="16" stroke-box />');

        $this->assertStringStartsWith('<span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--r-md); background: var(--bg-muted);"><span', $html);
        $this->assertStringEndsWith('</span></span>', $html);
    }
}
