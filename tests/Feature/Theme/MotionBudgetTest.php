<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **EL PRESUPUESTO DE BUCLES, EJECUTABLE** (auditoría de diseño M10, `[DECIDIDO owner, 2026-09-03]` D6,
 * `DECISIONES #435`; el presupuesto es el de `#279`).
 *
 * `#279` midió el movimiento por vista y decidió un techo; nadie lo vigilaba, y la auditoría midió la
 * portada en 13 bucles (2 del latido del CTA doble, 8 del icono de calcetines, 3 del spinner) y
 * `/cumpleanos` en 17 (once banderitas y una estrella). Una guarda de navegador no cabe en la suite,
 * así que aquí se vigila lo que SÍ es estático y basta: **cada `infinite` de las hojas está
 * enumerado con su motivo**. Un bucle nuevo pone la suite en rojo hasta que alguien lo decida.
 *
 * Además de la lista, tres hechos concretos de la tanda:
 *  · las banderitas y la estrella de la invitación no llevan `infinite` (D6: quietas);
 *  · el spinner se PAUSA con el cajón cerrado (`visibility: hidden` no detiene animaciones);
 *  · los bucles del icono de calcetines se pausan fuera de pantalla (`.is-onscreen` lo pone `app.js`).
 */
class MotionBudgetTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css', 'public/css/spinner.css'];

    /** Todo `infinite` de las tres hojas, con su motivo. Añadir aquí es DECIDIR un bucle. */
    private const BUCLES_DECLARADOS = [
        '.nav__period-dot' => 'el punto del nombre de marca en texto — solo sin logotipo',
        // ⚠️ El destello de la atracción destacada se fue en `#482` con el carrusel, y con él su
        // `@keyframes jj-shine-sweep`: un bucle declarado sin regla que lo invoque es ruido.
        '.price--feat .price__badge' => 'la chapa «destacado» de la tarifa (#279)',
        '.map-pin' => 'el pin del mapa sin inserción (#279)',
        '.brand-band__track' => 'la cinta del eslogan, AMBIENTAL (`--dur-cinta`)',
        '.catalog__item--feat .catalog__badge' => 'cajón: la chapa del catálogo',
        '.ic-b1 svg .flame' => 'cajón: la llama del icono b1',
        '.ic-b7 svg .pop' => 'cajón: el icono b7',
        '.ic-b7 svg .c1, .ic-b7 svg .c2, .ic-b7 svg .c3' => 'cajón: los trocitos del icono b7',
        '.ic-s1 svg .grip' => 'calcetines: pausado fuera de pantalla (`#435`)',
        '.ic-s1 svg .sock-a' => 'calcetines: pausado fuera de pantalla (`#435`)',
        '.ic-s1 svg .sock-b' => 'calcetines: pausado fuera de pantalla (`#435`)',
        '.cta-pair--invita .cta-pair__alt > .cta-ghost' => 'el latido del CTA doble (#205), AMBIENTAL',
        '.cta-pair--invita .cta-pair__alt-ring' => 'el aro del CTA doble (#205), AMBIENTAL',
        '.cta-pair--account.cta-pair--invita > .cta-med' => 'el latido del CTA doble, mitad cuenta',
        '.cta-pair--account.cta-pair--invita > .cta-med::after' => 'el aro del CTA doble, mitad cuenta',
        '.offw-gift.gm-1 .all' => 'el lanzador de ofertas en reposo',
        '.offw-gift.gm-1 .lid' => 'la tapa del lanzador de ofertas',
        '.jj-spinner' => 'el cargador (900 ms, el único bucle que el sistema admite), pausado con el cajón cerrado',
        '.jj-spinner::before, .jj-spinner::after' => 'las dos piezas del cargador',
    ];

    public function test_every_infinite_loop_is_declared_with_its_reason(): void
    {
        $encontrados = $this->infiniteSelectors();

        $this->assertNotEmpty($encontrados, 'el escaneo no ve ningún `infinite`: el localizador se ha roto');

        $sinDeclarar = array_values(array_diff($encontrados, array_keys(self::BUCLES_DECLARADOS)));
        $this->assertSame(
            [],
            $sinDeclarar,
            'Bucles SIN FIN nuevos, fuera del presupuesto de `#279` (auditoría M10). Decídelos y enumera aquí '.
            "cada uno con su motivo:\n  ".implode("\n  ", $sinDeclarar),
        );

        $sinSujeto = array_values(array_diff(array_keys(self::BUCLES_DECLARADOS), $encontrados));
        $this->assertSame(
            [],
            $sinSujeto,
            "Estas entradas de la lista ya no tienen un `infinite` detrás — retíralas, la lista solo encoge:\n  ".
            implode("\n  ", $sinSujeto),
        );
    }

    public function test_the_invitation_flags_and_star_are_still(): void
    {
        foreach ($this->infiniteSelectors() as $selector) {
            $this->assertDoesNotMatchRegularExpression(
                '/bd-card__bunting|bd-card__star/',
                $selector,
                "`{$selector}` vuelve a mover las banderitas o la estrella de la invitación en bucle (D6: quietas).",
            );
        }
    }

    public function test_the_spinner_is_paused_while_the_drawer_is_closed(): void
    {
        $rules = $this->rules();
        $this->assertArrayHasKey('.sidecart:not(.is-open) .jj-spinner, .sidecart:not(.is-open) .jj-spinner::before, .sidecart:not(.is-open) .jj-spinner::after', $rules, 'la regla que pausa el spinner con el cajón cerrado ya no está');
        $this->assertMatchesRegularExpression(
            '/animation-play-state:\s*paused/',
            $rules['.sidecart:not(.is-open) .jj-spinner, .sidecart:not(.is-open) .jj-spinner::before, .sidecart:not(.is-open) .jj-spinner::after'],
            'el spinner vuelve a girar oculto en las doce vistas (auditoría M10)',
        );
    }

    public function test_the_socks_icon_loops_are_paused_off_screen(): void
    {
        $rules = $this->rules();
        $key = '.ic-s1:not(.is-onscreen) svg .grip, .ic-s1:not(.is-onscreen) svg .sock-a, .ic-s1:not(.is-onscreen) svg .sock-b';
        $this->assertArrayHasKey($key, $rules, 'la regla que pausa el icono de calcetines fuera de pantalla ya no está');
        $this->assertMatchesRegularExpression('/animation-play-state:\s*paused/', $rules[$key]);

        $js = (string) file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString("classList.toggle('is-onscreen'", $js, 'el JS ya no enciende `is-onscreen`: el icono se quedaría siempre pausado');
    }

    /** @return list<string> */
    private function infiniteSelectors(): array
    {
        $out = [];
        foreach ($this->rules() as $selector => $body) {
            if (preg_match('/animation(?:-iteration-count)?\s*:\s*[^;]*(?<![-\w])infinite(?![-\w])/', $body)) {
                $out[] = $selector;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados y los `@media` abiertos */
    private function rules(): array
    {
        $out = [];

        foreach (self::SHEETS as $sheet) {
            $css = (string) file_get_contents(base_path($sheet));
            $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
            // Los `@keyframes` se retiran enteros (sus pasos no son reglas); los `@media` se abren.
            $blind = (string) preg_replace_callback('/@keyframes[^{]*\{((?:[^{}]*\{[^{}]*\})*)\s*\}/s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $blind);
            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $blind, $matches, PREG_SET_ORDER);

            foreach ($matches as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
                $selector = (string) preg_replace('/^@media[^{]*\{\s*/', '', $selector);
                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }
                $out[$selector] = ($out[$selector] ?? '').' '.trim($rule[2]);
            }
        }

        return $out;
    }
}
