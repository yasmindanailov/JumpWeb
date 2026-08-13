<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Fase 4 · paso 4.0c — **la tokenización del sidebar solo puede SUBIR**
 * (`docs/specs/sidebar-spa.md` §4.3 y §4.3.bis, criterio CE-3).
 *
 * El owner decidió que cada instalación tendrá su propia hoja de estilos sobre tokens comunes. Eso
 * solo sirve si hay tokens a los que agarrarse: medido el 2026-08-13, de las declaraciones
 * TEMATIZABLES del sidebar solo el 43% usaba `var(--…)` — con lo cual una instalación podía
 * recolorear y poco más, que no es lo que se pidió.
 *
 * Es un test-presupuesto, hermano de `ApiOverheadTest`: no persigue un ideal, **impide que
 * empeore**. Si un cambio lo rompe, es una regresión del white-label.
 *
 * ⚠️ **Se mide sobre las propiedades TEMATIZABLES, no sobre todas.** El sidebar tiene 75
 * `display`, 28 `flex-direction` y 26 `align-items` que son ESTRUCTURA: un `display: flex` no se
 * tematiza, y contarlos premiaría convertirlos en tokens, que sería absurdo. La lista de abajo es
 * la frontera entre «cómo se ve» y «cómo se coloca».
 */
class SidebarTokenBudgetTest extends TestCase
{
    /**
     * Propiedades que un cliente podría querer cambiar sin tocar el marcado. Todo lo demás
     * (`display`, `position`, `flex`, `overflow`, `cursor`…) es estructura y queda fuera.
     *
     * @var list<string>
     */
    private const THEMABLE = [
        'font-size', 'font-weight', 'line-height', 'letter-spacing', 'font-family',
        'gap', 'row-gap', 'column-gap',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'border-radius', 'box-shadow', 'transition', 'transition-duration',
        'color', 'background', 'background-color', 'border', 'border-color',
        'border-top', 'border-bottom',
    ];

    /** Suelo medido tras el paso 4.0c. **Solo puede subir.** */
    private const MIN_TOKENISED_PERCENT = 49;

    /**
     * Colores CRUDOS que quedan en el sidebar (`#rrggbb`, `rgba(...)`). **Solo puede bajar**: son
     * los que impiden que una instalación cambie de paleta de verdad.
     *
     * Eran 13 y quedan 3. Los diez convertidos coincidían EXACTAMENTE con un token existente
     * —seis alfa de `--fg`, dos `--bg-soft`, uno `--err`, uno `--warn`—, comprobado por aritmética
     * RGB antes de tocar nada, así que el resultado renderizado es el mismo.
     * Los tres que quedan no tienen token que los represente y convertirlos CAMBIARÍA el color:
     * un velo blanco al 55%, un `#fff` puro, y un `rgba(20,19,15,0.22)` que vive en `landing.css`
     * —la copia del mockup, compartida con toda la landing— y por tanto fuera del alcance de esta
     * fase.
     */
    private const MAX_RAW_COLOURS = 3;

    /** @var ?list<array{property: string, value: string}> */
    private ?array $declarations = null;

    public function test_the_scan_actually_sees_the_sidebar_rules(): void
    {
        $this->assertNotEmpty($this->sidebarClasses(), 'no se ha detectado ninguna clase del sidebar');
        $this->assertGreaterThan(100, count($this->sidebarDeclarations()), 'el escaneo ve muy pocas reglas: ¿ha cambiado el CSS de sitio?');
    }

    public function test_the_themable_properties_stay_tokenised(): void
    {
        $themable = array_filter(
            $this->sidebarDeclarations(),
            fn (array $d): bool => in_array($d['property'], self::THEMABLE, true)
        );

        $this->assertNotEmpty($themable);

        $tokenised = count(array_filter($themable, fn (array $d): bool => str_contains($d['value'], 'var(--')));
        $percent = (int) floor($tokenised * 100 / count($themable));

        $this->assertGreaterThanOrEqual(
            self::MIN_TOKENISED_PERCENT, $percent,
            "La tokenización del sidebar ha BAJADO a {$percent}% (suelo: ".self::MIN_TOKENISED_PERCENT."%).\n".
            'Cada instalación tiene su propia hoja de estilos sobre estos tokens: un literal nuevo '.
            "es una cosa que un cliente ya no puede cambiar.\n".
            'Si has subido el ratio, sube también el suelo — es un presupuesto, no un objetivo.'
        );
    }

    public function test_raw_colours_in_the_sidebar_only_shrink(): void
    {
        $raw = 0;

        foreach ($this->sidebarDeclarations() as $declaration) {
            $raw += preg_match_all('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', $declaration['value']);
        }

        $this->assertLessThanOrEqual(
            self::MAX_RAW_COLOURS, $raw,
            "El sidebar tiene {$raw} colores crudos (tope: ".self::MAX_RAW_COLOURS.").\n".
            'Un color escrito a mano no lo puede cambiar ninguna instalación. Los alfa sobre el '.
            'texto salen con `color-mix(in srgb, var(--fg) X%, transparent)`.'
        );
    }

    /**
     * Las clases que emite el sidebar: las de su propia vista más la CARCASA del cajón, que vive en
     * el layout (`sidecart*`) y es donde están las reglas de más valor — la que lo abre, entre
     * ellas. Olvidarla fue uno de los agujeros que la revisión del spec encontró.
     *
     * @return list<string>
     */
    private function sidebarClasses(): array
    {
        $classes = [];

        foreach ([
            ['path' => resource_path('views/livewire/tickets/purchase.blade.php'), 'prefix' => null],
            ['path' => resource_path('views/components/layout.blade.php'), 'prefix' => 'sidecart'],
        ] as $source) {
            $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($source['path']));

            preg_match_all('/class="([^"]*)"/', $blade, $matches);

            foreach ($matches[1] as $attribute) {
                foreach (preg_split('/\s+/', $attribute) ?: [] as $token) {
                    if (preg_match('/^[a-zA-Z][\w-]*$/', $token) !== 1) {
                        continue;   // interpolaciones y expresiones: no son nombres de clase
                    }
                    if ($source['prefix'] !== null && ! str_starts_with($token, $source['prefix'])) {
                        continue;
                    }

                    $classes[$token] = true;
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * Declaraciones de las reglas cuyo selector toca alguna clase del sidebar, en los DOS ficheros:
     * `site.css` y también `landing.css`, donde viven `btn--lg`, `btn--ghost` y `zone-tab`.
     *
     * @return list<array{property: string, value: string}>
     */
    private function sidebarDeclarations(): array
    {
        // Memo de INSTANCIA, no `static`: un `static` dentro de un método sobrevive al objeto y al
        // test siguiente del mismo proceso, que es justo lo que `SUITE-02` documenta como fuente de
        // fallos fantasma. Aquí el contenido no cambiaría, pero la forma sí importa.
        if ($this->declarations !== null) {
            return $this->declarations;
        }

        $classes = $this->sidebarClasses();
        $declarations = [];

        foreach (['site.css', 'landing.css'] as $file) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(public_path('css/'.$file)));

            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                if (! $this->selectorTouchesSidebar($rule[1], $classes)) {
                    continue;
                }

                foreach (explode(';', $rule[2]) as $declaration) {
                    if (! str_contains($declaration, ':')) {
                        continue;
                    }

                    [$property, $value] = explode(':', $declaration, 2);
                    $declarations[] = ['property' => trim($property), 'value' => trim($value)];
                }
            }
        }

        return $memo = $declarations;
    }

    /** @param  list<string>  $classes */
    private function selectorTouchesSidebar(string $selector, array $classes): bool
    {
        foreach ($classes as $class) {
            if (preg_match('/\.'.preg_quote($class, '/').'(?![\w-])/', $selector) === 1) {
                return true;
            }
        }

        return false;
    }
}
