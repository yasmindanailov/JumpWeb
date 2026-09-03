<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **UN SOLO BOTÓN — la T9 del idioma visual.**
 *
 * La auditoría de diseño del 2026-09-01 midió que la misma función se dibujaba de TRES maneras
 * (`.btn` en ~15 superficies · `.cta-*` en el armazón · `.bd-btn` en el editor de invitaciones) y
 * con DOS rellenos de acción: en `/precios` convivían en la misma pantalla un CTA cian
 * (`.btn--zone`, que es MARCA) y dos naranjas (`--action`). `[DECIDIDO owner]`: la acción es
 * `--action` en toda la web y la familia del contenido es UNA, `.btn`.
 *
 * Lo que este fichero vigila, y por qué cada cosa:
 *
 *  1. **Que ningún Blade use una variante retirada** (`bd-btn`, `btn--zone`). El cian de
 *     `.btn--zone` es color de ZONA pintando ACCIÓN — la confusión de rol exacta que
 *     `ActionFillTest` documenta. ⚠️ El CAJÓN Vue queda EXENTO a propósito: el SPA está aparcado
 *     (`[DECIDIDO owner, 2026-09-01]`) y su contrato (`SidebarDomContractTest`) emite `btn--zone`;
 *     por eso la variante sigue DECLARADA en el CSS y este test solo mira `*.blade.php`.
 *  2. **Que el hover no salte y su texto siga al rol.** La física la fijaron el CTA del armazón
 *     (`#217` §9.4) y la pegatina (`#303`): responde el color, no la posición. El texto en
 *     `--on-action-hover` retiró la excepción que `ActionFillTest::EXCEPTIONS` enumeraba y su
 *     ficha de `DEUDA.md` — si alguien lo devuelve a `--fg`, con una marca oscura el rótulo
 *     desaparece al pasar el cursor y no falla nada.
 *  3. **Que los estados existan**: la pisada (`:active`), el deshabilitado y el estado de carga
 *     (`.btn__loading`, con su consumidor real en el restablecimiento de contraseña). Son los que
 *     hoy tienen consumidor — error/éxito nacerán CON el suyo, no antes (la regla de `#287`:
 *     una pieza nace en el mismo cambio que su consumidor).
 *
 * ⚠️ Los comentarios se RETIRAN antes de escanear, en Blade (`{{-- --}}`) y en CSS (`/* … *​/`):
 * el banner de cookies CITA `.btn--zone` en un comentario legal (AEPD) que es verdadero y debe
 * poder seguir ahí sin poner esto en rojo — un escáner que lee comentarios acusa a la prosa.
 */
class SingleButtonFamilyTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **El escaneo ve el corpus** — sin esto, un glob roto deja todo verde sin mirar nada. */
    public function test_the_scan_sees_the_corpus(): void
    {
        $blades = $this->blades();

        $this->assertGreaterThan(
            60, count($blades),
            'el escaneo ve '.count($blades).' blades y son más de sesenta: el glob se ha roto y '.
            'todo lo de abajo pasaría sin mirar.',
        );

        // Control positivo: el patrón de variantes retiradas CAZA lo que dice cazar.
        $this->assertSame(1, preg_match($this->retiredPattern(), 'class="btn btn--zone btn--lg"'));
        $this->assertSame(1, preg_match($this->retiredPattern(), 'class="bd-btn bd-btn--solid"'));

        // Y el desnudador de comentarios Blade desnuda de verdad.
        $this->assertSame(0, preg_match($this->retiredPattern(), $this->stripBladeComments(
            'hola {{-- aquí se cita `.btn--zone` y no cuenta --}} adiós',
        )));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · Ningún Blade usa una variante retirada
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_no_blade_uses_a_retired_button_variant(): void
    {
        $offenders = [];

        foreach ($this->blades() as $path) {
            $clean = $this->stripBladeComments((string) file_get_contents($path));

            if (preg_match($this->retiredPattern(), $clean, $m)) {
                $offenders[] = str_replace(base_path().'/', '', $path).' → '.$m[0];
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Estos blades usan una variante de botón RETIRADA en la T9:'],
            array_map(fn (string $o): string => '  · '.$o, $offenders),
            ['',
                '▶ La familia del contenido es UNA: `.btn` (+ `--ghost`/`--sm`/`--lg`). La acción',
                '  es `--action` en toda la web; `btn--zone` era MARCA pintando acción.',
                '▶ `bd-btn` se absorbió en `.btn` — el par del editor no perdió nada.',
                '▶ El cajón Vue está EXENTO (SPA aparcado): esto solo mira `*.blade.php`.'],
        )));
    }

    /** Y la familia absorbida tampoco sigue DECLARADA en las hojas del producto. */
    public function test_the_absorbed_family_is_not_in_the_sheets(): void
    {
        foreach (self::SHEETS as $sheet) {
            $css = $this->strippedCss($sheet);

            $this->assertDoesNotMatchRegularExpression(
                '/\.bd-btn(?![\w-])/', $css,
                "`{$sheet}` vuelve a declarar `.bd-btn`: la familia se absorbió en `.btn` (T9) y ".
                'resucitarla es volver a tres anatomías para la misma función.',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · El hover no salta y su texto sigue al rol
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_button_hover_does_not_jump_and_its_text_follows_the_role(): void
    {
        $body = $this->hoverBody();

        // Control: la regla existe y hace algo — si el localizador se rompe, esto para primero.
        $this->assertStringContainsString('background', $body, 'el localizador de `.btn:hover` ya no ve la regla');

        $flat = (string) preg_replace('/\s+/', '', $body);

        $this->assertStringNotContainsString(
            'transform:', $flat,
            'el hover del botón vuelve a SALTAR. La física del sistema la fijaron `#217` §9.4 (el '.
            'CTA del armazón ya no salta) y `#303` (la pegatina responde con el color, no con la '.
            'posición): el único transform del botón es la pisada de `:active`.',
        );

        $this->assertStringNotContainsString(
            'box-shadow:', $flat,
            'el hover del botón vuelve a levitar con sombra: la elevación no es un estado del botón.',
        );

        $this->assertStringContainsString(
            'color:var(--on-action-hover)', $flat,
            'el texto del hover ya no sigue al rol. Con una marca OSCURA, un `--fg` aquí es tinta '.
            'sobre tinta: el rótulo desaparece al pasar el cursor y no falla nada. Esta conversión '.
            'retiró la excepción de `ActionFillTest` y su ficha de `DEUDA.md` — no la deshagas sin '.
            'devolver las dos.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · Los estados con consumidor existen
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_button_states_with_a_consumer_exist(): void
    {
        $landing = $this->strippedCss('public/css/landing.css');
        $site = $this->strippedCss('public/css/site.css');

        $this->assertMatchesRegularExpression(
            '/\.btn:active[^{]*\{[^}]*translateY\(1px\)/', $landing,
            'la pisada del botón (`:active` → `translateY(1px)`) ha desaparecido: es el único '.
            'transform legítimo del botón y el tacto compartido con el resto del sistema.',
        );

        $this->assertMatchesRegularExpression(
            '/\.btn:disabled[^{]*\{[^}]*opacity/', $landing,
            'el estado deshabilitado del botón ha desaparecido: los envíos con `wire:loading` '.
            'volverían a responder al cursor mientras cargan.',
        );

        $this->assertStringContainsString(
            '.btn__loading', $site,
            'el estado de carga del botón (`.btn__loading`) ha desaparecido de site.css.',
        );

        $this->assertStringContainsString(
            'btn__loading',
            (string) file_get_contents(base_path('resources/views/livewire/auth/reset-password.blade.php')),
            'el consumidor real de `.btn__loading` (restablecer contraseña) ya no lo usa: si el '.
            'estado se queda sin consumidor, retíralo con él en vez de dejarlo muerto (regla de `#287`).',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Herramientas
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function blades(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($it as $file) {
            if (str_ends_with($file->getPathname(), '.blade.php')) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    private function retiredPattern(): string
    {
        return '/(?<![\w-])(bd-btn|btn--zone)(?![\w-])/';
    }

    private function stripBladeComments(string $blade): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
    }

    /** CSS con los comentarios blanqueados conservando longitud (la trampa de `#193`). */
    private function strippedCss(string $sheet): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            (string) file_get_contents(base_path($sheet)),
        );
    }

    /** El cuerpo de la regla `.btn:hover` (exacta: no la de su `svg`) en landing.css. */
    private function hoverBody(): string
    {
        preg_match(
            '/(?:^|\})\s*\.btn:hover\s*\{([^}]*)\}/s',
            $this->strippedCss('public/css/landing.css'),
            $m,
        );

        return $m[1] ?? '';
    }
}
