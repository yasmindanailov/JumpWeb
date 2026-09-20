<?php

namespace Tests\Feature\Theme;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Services\ThemeSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA CIFRA DE UNA ZONA TIENE QUE LEERSE SOBRE EL PAPEL, Y ESO ES UNA CUENTA** (`#481`).
 *
 * ⚠️⚠️ **Es ARITMÉTICA, no marcado.** La cifra grande de cada zona se pinta con el color de esa zona, que
 * llega desde el PANEL; medido sobre esta instalación, los dos colores reales fallan como texto sobre papel
 * (lima **1,85** y cian **2,45**, contra el 3,0 que es el suelo de cualquier texto). Por eso el valor lo
 * DERIVA `ThemeSettings::zoneInk()`, y por eso hay un caso que vuelve a hacer la cuenta con los colores
 * reales: una derivación mal calibrada pinta un número ilegible **sin que nada falle**.
 *
 * ❗❗ **Estos casos ya cambiaron el diseño una vez, y lo hicieron en verde.** La primera versión de la
 * cifra usaba `color-mix` con un porcentaje FIJO: al 60 % el lima de esta instalación daba 4,23 y al 55 %
 * pasaba (4,76)… pero el `#C6FF3A` que el PRODUCTO trae por defecto para Kids se quedaba en **3,14**. *Lo
 * encontró la guarda con los datos del producto, no una relectura del CSS.* De ahí salió la derivación por
 * pasos, que se adapta al color en vez de suponerlo.
 *
 * ▶ **Vivían en `Landing/AttractionsPageTest` y se mudan aquí con la T2b** (`#657`): aquel fichero pasó a
 * afirmar sobre los DATOS que recibe la vista (`#649`), y esto no es un dato de la página —es una regla de
 * TEMA, medida contra el papel que declara la hoja del producto y contra los colores del panel—. El sujeto
 * de la mitad de extremo a extremo es ahora el **anfitrión mínimo**, que es el marcado que el producto
 * sirve sin paquete de instancia.
 */
class ZoneInkIsLegibleTest extends TestCase
{
    use RefreshDatabase;

    /** Texto normal en WCAG 2.1. La cifra es grande y le bastaría 3,0; se exige el techo a propósito. */
    private const UMBRAL_AA = 4.5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    /**
     * Se rehace la cuenta con los colores REALES: los de las zonas de la instalación contra el papel que
     * declara la hoja. Si alguien toca la derivación —o el panel recibe un color de zona más claro—, esto
     * se pone rojo antes de que un número ilegible llegue a producción.
     *
     * ⚠️ Y comprueba las DOS mitades: que el color derivado se lea **y** que el token llegue a la página.
     * Derivarlo bien y no emitirlo pinta la cifra con el respaldo y no se entera nadie.
     */
    public function test_the_zone_figure_is_legible_on_paper(): void
    {
        $this->assertStringContainsString('color: var(--zone-ink', file_get_contents(public_path('css/landing.css')),
            'la cifra de zona ya no lee `--zone-ink`: revisa este caso antes de borrarlo');

        $papel = $this->paperColour();

        $zonas = Zone::where('show_in_landing', true)->get()
            ->filter(fn (Zone $z): bool => $z->attractions()->where('is_active', true)->exists());
        $this->assertGreaterThan(0, $zonas->count(), 'el caso nace sin sujeto: ninguna zona tiene atracciones');

        foreach ($zonas as $zona) {
            $color = ThemeSettings::colorForAccent($zona->color, $zona->accent);
            $tinte = ThemeSettings::zoneInk($color);
            $ratio = $this->contrast($tinte, $papel);

            $this->assertGreaterThanOrEqual(self::UMBRAL_AA, $ratio, sprintf(
                'la cifra de «%s» sale a %.2f sobre el papel (zona %s → `--zone-ink` %s): por debajo de %.1f no se lee.',
                $zona->slug, $ratio, $color, $tinte, self::UMBRAL_AA,
            ));

            // La mitad de extremo a extremo. ⚠️ La página servida aquí es el ANFITRIÓN MÍNIMO (`#657`): la
            // de PlayJump vive en su instancia y la suite corre SIN paquete. Sigue valiendo como sujeto
            // porque el estilo compuesto lo emite el producto, no el diseño del cliente.
            $this->assertStringContainsString('--zone-ink:'.$tinte, $this->get('/atracciones')->getContent(),
                "el panel de «{$zona->slug}» no emite su `--zone-ink`");
        }
    }

    /**
     * **CONTROL POSITIVO del caso de arriba**: un color de zona claro TIENE que salir oscurecido.
     *
     * Sin esto, la guarda pasaría igual con `zoneInk()` devolviendo el color tal cual, porque los colores
     * de esta instalación podrían pasar solos algún día. Aquí se comprueba que el mecanismo **actúa**: el
     * `#C6FF3A` que el producto trae por defecto para Kids da 1,53 en crudo.
     */
    public function test_a_pale_zone_colour_is_actually_darkened(): void
    {
        $papel = $this->paperColour();

        $crudo = '#C6FF3A';
        $this->assertLessThan(3.0, $this->contrast($crudo, $papel), 'el control nace sin sujeto: ese color ya se leía');

        $tinte = ThemeSettings::zoneInk($crudo);

        $this->assertNotSame($crudo, $tinte, '`zoneInk()` devolvió el color sin tocar: el mecanismo no actúa');
        $this->assertGreaterThanOrEqual(self::UMBRAL_AA, $this->contrast($tinte, $papel));
    }

    /**
     * ⚠️ Y la derivación **reproduce la variante oscura del propio sistema**, que es lo que la hace
     * defendible: cuatro pasos sobre el Lima Bote dan el Lima 800 que el artboard escribe a mano.
     */
    public function test_the_derivation_lands_on_the_systems_own_dark_variant(): void
    {
        $this->assertSame('#627411', ThemeSettings::zoneInk('#A3C21C'));
    }

    /**
     * El PAPEL vigente en esta máquina: el que declara el producto, sobrescrito por el del paquete si existe.
     *
     * ⚠️ `client.css` está **gitignorado**, así que un caso que dependiera de él pasaría aquí y fallaría en
     * un clon limpio. Por eso el producto es el suelo y el paquete solo afina — y por eso `zoneInk()` deriva
     * contra el papel del PRODUCTO, que es el más oscuro de los dos y por tanto el lado seguro.
     */
    private function paperColour(): string
    {
        $papel = '#F4EFE3';

        foreach (['css/landing.css', 'css/client.css'] as $hoja) {
            if (! is_file(public_path($hoja))) {
                continue;
            }

            // Solo el primer `:root`: los bloques por superficie redefinen `--bg`, y aquí se mide el PAPEL,
            // que es la superficie de esta página.
            $css = file_get_contents(public_path($hoja));
            $raiz = substr($css, 0, strpos($css, '}') ?: null);

            if (preg_match('/--bg:\s*(#[0-9A-Fa-f]{6})/', $raiz, $m)) {
                $papel = $m[1];
            }
        }

        return $papel;
    }

    private function contrast(string $a, string $b): float
    {
        $x = $this->luminance($a);
        $y = $this->luminance($b);

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $lin = static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = $this->rgb($hex);

        return 0.2126 * $lin($r / 255) + 0.7152 * $lin($g / 255) + 0.0722 * $lin($b / 255);
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
