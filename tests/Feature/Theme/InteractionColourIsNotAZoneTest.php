<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **LA IDENTIDAD DE ZONA NO ES EL COLOR DE LA INTERACCIÓN** (auditoría de diseño C2 ·
 * `[DECIDIDO owner, 2026-09-03]` D1 · `DECISIONES #436`; la regla quedó escrita en el `design.md` de la
 * raíz, hoy `docs/archivo/design-producto-2026-09-03.md` §3 — archivado por `#452`, la regla sigue).
 *
 * `--zone-1`/`--zone-2` no son colores: son la identidad de la zona que se está mirando, y los repinta
 * el servidor por elemento desde `zones.color`. Un token cuyo valor depende del contexto pintaba el
 * hover de la FAQ, el destino enfocado del menú y el enlace del banner de cookies — sitios donde no
 * hay zona— y por eso su contraste no lo garantizaba nadie: con el cian del 2.º cliente, la pregunta
 * abierta daba **2,45** sobre papel. La auditoría contó 27 usos dentro de selectores interactivos.
 *
 * Nace `--interactive` (tinta por defecto, re-declarado en las dos superficies; el paquete pone su par
 * medido, Azul Muro / cian). Lo que este fichero vigila:
 *  1. Que el token exista en el `:root` del producto y en las DOS superficies.
 *  2. Que ninguna regla de ESTADO de interacción (`:hover` · `:focus` · `.open` · `.active` ·
 *     `.is-active` · `.is-on`) lea `--zone-*`, salvo lo enumerado con su porqué: lo que IDENTIFICA
 *     una zona (sus pestañas y chips), los RELLENOS de marca (un fondo con texto calculado por
 *     luminancia, no un texto de color) y el cajón SPA, aparcado. Las tres listas **solo encogen**.
 *
 * ⚠️ Los comentarios se blanquean conservando longitud (la trampa de `#193`).
 */
class InteractionColourIsNotAZoneTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /** Lo que IDENTIFICA una zona y por eso lleva su color también en su estado activo. */
    private const IDENTIDAD = [
        // ⚠️ La pestaña de zona de la portada se fue en `#482` con el selector. La de
        // `/atracciones` es `.tabset__tab`, que NO se tiñe con el color de zona: su activa se
        // levanta a blanco, que es lo que el sistema declara — así que no necesita excepción.
        '.zone-tab.active' => 'la pestaña de zona de tarifas y /servicios',
        '.purchase__chip.is-active' => 'cajón: el chip de zona del embudo',
        '.btn--zone:disabled:hover, .btn--zone[aria-disabled="true"]:hover' => 'cajón: el botón de zona, prohibido en Blade (#321) y vivo en el CSS a propósito',
    ];

    /** RELLENOS de marca: un fondo, con el texto encima calculado por luminancia (`--on-brand`). No es texto de color. */
    private const RELLENO_DE_MARCA = [
        '.cta-med:hover' => 'el CTA del armazón pasa a marca al pasar, como el mockup (#217)',
        '.salta__btn:hover' => 'el botón del minijuego oscurece su relleno de marca',
    ];

    /** El cajón SPA, aparcado (`[DECIDIDO owner, 2026-09-01]`). Solo encoge. */
    private const CAJON = [
        '.bk-foot__info-btn:hover',
        '.purchase__add-more:hover',
        '.catalog__item:hover .catalog__ico',
        '.catalog__item:hover .catalog__go',
        '.catalog-acc__head:hover .catalog-acc__icon',
        '.catalog-acc__head:hover .catalog-acc__icon .ic-e5 svg .occ',
        '.acct__alert:hover',
        '.acc-tile:hover .acc-tile__ico svg',
    ];

    public function test_the_scan_sees_the_corpus_and_every_exception_has_a_subject(): void
    {
        $rules = $this->rules();
        $this->assertGreaterThan(1500, count($rules), 'el localizador de reglas se ha roto');

        foreach (array_merge(array_keys(self::IDENTIDAD), array_keys(self::RELLENO_DE_MARCA), self::CAJON) as $selector) {
            $this->assertArrayHasKey($selector, $rules, "`{$selector}` ya no existe: retíralo de su lista, que solo encoge.");
            $this->assertMatchesRegularExpression('/--zone-[12]/', $rules[$selector], "`{$selector}` ya no lee `--zone-*`: retíralo de su lista, que solo encoge.");
        }
    }

    public function test_the_interaction_token_exists_in_root_and_both_surfaces(): void
    {
        $rules = $this->rules();
        foreach ([':root', '[data-surface="ink"]', '[data-surface="paper"]'] as $scope) {
            $this->assertArrayHasKey($scope, $rules, "no hay bloque `{$scope}` en `landing.css`");
            $this->assertMatchesRegularExpression('/--interactive:/', $rules[$scope], "`{$scope}` no declara `--interactive`: en esa superficie el rol vuelve a caer al valor de otra (`#436`).");
        }
    }

    public function test_no_interaction_state_reads_a_zone_token(): void
    {
        $permitidos = array_merge(array_keys(self::IDENTIDAD), array_keys(self::RELLENO_DE_MARCA), self::CAJON);
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/--zone-[12]/', $body)) {
                continue;
            }
            if (! preg_match('/:hover|:focus|\.open\b|\.is-active|\.is-on\b|\.active\b|aria-selected/', $selector)) {
                continue;
            }
            if (in_array($selector, $permitidos, true)) {
                continue;
            }
            $culpables[] = $selector;
        }

        $this->assertSame(
            [],
            $culpables,
            'Un estado de INTERACCIÓN vuelve a pintarse con la identidad de una zona (auditoría C2). El rol es '.
            "`--interactive`; `--zone-*` solo identifica una zona:\n  ".implode("\n  ", $culpables),
        );
    }

    public function test_the_faq_and_the_menu_read_the_interaction_token(): void
    {
        $rules = $this->rules();
        foreach (['.faq__item.open .faq__q', '.faq__item:hover .faq__q', '.cookie__config:hover', '.lang-dd__panel a.active'] as $selector) {
            $this->assertArrayHasKey($selector, $rules);
            $this->assertMatchesRegularExpression('/color:\s*var\(--interactive\)/', $rules[$selector], "`{$selector}` ya no lee `--interactive`");
        }
    }

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados */
    private function rules(): array
    {
        $out = [];

        foreach (self::SHEETS as $sheet) {
            $css = (string) file_get_contents(base_path($sheet));
            $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
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
