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
        // ⚠️⚠️ **Aquí vivían DOS entradas del cajón y las dos cayeron en `#551`**, cada una por su
        // motivo, y la primera merece quedar escrita:
        //  · `.purchase__chip.is-active` estaba exceptuada como «el chip de ZONA del embudo», y
        //    medido NO LO ERA: esa clase la emite **solo** `TimeStep.vue`, o sea que es la HORA. El
        //    artboard la dibuja en tinta. ▶ *Una excepción justificada con un motivo que no describe a
        //    su sujeto sobrevive a todas las revisiones, porque quien revisa lee el motivo.*
        //  · `.btn--zone:*` desapareció con la variante: hoy es `.btn--ink`, relleno de tinta.
    ];

    /** RELLENOS de marca: un fondo, con el texto encima calculado por luminancia (`--on-brand`). No es texto de color. */
    private const RELLENO_DE_MARCA = [
        // ▶ `.cta-med:hover` SALIÓ de esta lista en `#581`, y la lista solo encoge: el CTA del armazón
        //   dejó de reposar en Azul Muro y reposa en la MARCA (`--brand`), así que su hover oscurece
        //   su propio relleno y ya no lee ningún color de zona.
        '.salta__btn:hover' => 'el botón del minijuego oscurece su relleno de marca',
        // ❗❗ Los del cajón (`#551`) reposan en tinta y acusan el paso del cursor pasando a marca, el
        // idioma que tenía el CTA del armazón hasta `#581`. No es la grieta 01 por la puerta de atrás — aquélla era
        // un valor haciendo de acción, de cifra, de enlace y de casilla a la vez; aquí hace UNA cosa.
        // ⚠️ No lo «arregles» quitándolo: sin él estos botones se quedan sin hover, y `#435` exige que
        // los controles respondan.
        // ▶ `.btn--ink:hover` SALIÓ de esta lista en `#539`, y la lista solo encoge: su secundario
        //   dejó de reposar en tinta —hoy reposa en Azul Muro—, así que el argumento «acusa el paso
        //   PASANDO a marca» murió con su premisa: un botón que ya reposa en marca no puede pasar a
        //   ella. Su hover es ahora el escalón siguiente de su propia escala.
        // ▶ `.bk-seg__item.is-current .bk-seg__bar` SALIÓ de esta lista en `#584`, y la lista solo
        //   encoge: la línea de pasos pasó al secundario (`[DECIDIDO owner]`) y su halo ya no lee
        //   `--zone-*`. Entró en `#551` al ampliar el vocabulario de estados, que sigue cubriéndolo.
    ];

    /**
     * El cajón SPA. **Pasó de OCHO a UNA en `#551`** (la grieta 01), y la que queda no es un descuido:
     * `.acct__alert` es un **aviso sobre papel**, y ese rol (`tintePapel`, punto 7 de la lista del
     * canvas) nace en otra tanda. Teñirlo de gris mientras tanto le quitaría el significado —«algo
     * falta»— sin ganar nada. Solo encoge.
     */
    private const CAJON = [
        // ▶ `#540` · SALIERON de esta lista `.bk-cta:hover`, `.cartbar:hover` y
        //   `.acct__btn--primary:hover`, y la lista solo encoge: sus tres botones dejaron de
        //   reposar en TINTA —hoy reposan en el CIAN del secundario—, así que el argumento
        //   «acusa el paso PASANDO a marca» murió con su premisa: un botón que ya reposa en
        //   marca no puede pasar a ella. Su hover es el escalón siguiente de su propia escala.
        '.acct__alert:hover',
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

    /**
     * El CONTROL del vocabulario: cada palabra de estado tiene que casar con un selector que la use, y
     * un selector sin estado tiene que quedar FUERA. Sin este caso, ampliar la lista es un gesto que
     * nadie comprueba — y recortarla pasaría en verde dejando reglas sin vigilar, que es justo lo que
     * pasó con `.is-selected` hasta `#551`.
     */
    public function test_the_state_vocabulary_sees_every_state_word(): void
    {
        $sujetos = [
            '.x:hover' => true,
            '.x:focus-visible' => true,
            '.faq__item.open .faq__q' => true,
            '.purchase__chip.is-active' => true,
            '.switch.is-on' => true,
            '.daystrip__day.is-selected' => true,
            '.bk-seg__item.is-current .bk-seg__bar' => true,
            '.zone-tab.active' => true,
            '[aria-selected="true"]' => true,
            // Y lo que NO es un estado: un selector de reposo no entra en el censo.
            '.bk-cta' => false,
            '.catalog__badge' => false,
            // ⚠️ Frontera de palabra: `.is-onboarding` NO es `.is-on` (la trampa de la subcadena, `#253`).
            '.x.is-onboarding' => false,
        ];

        foreach ($sujetos as $selector => $esEstado) {
            $this->assertSame(
                $esEstado,
                (bool) preg_match('/'.self::ESTADOS.'/', $selector),
                "el vocabulario de estados no clasifica bien `{$selector}`",
            );
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

    /**
     * El vocabulario de ESTADOS que esta guarda reconoce.
     *
     * ❗❗❗ **Nació con un hueco y lo midió `#551`**: conocía `:hover`, `:focus`, `.open`, `.is-active`,
     * `.is-on`, `.active` y `aria-selected` — y **no `.is-selected` ni `.is-current`**. Con eso,
     * `.daystrip__day.is-selected`, `.cal__day.is-selected` y `.bk-seg__item.is-current .bk-seg__bar`
     * se pintaban con el color de una zona **sin que la guarda las acusara ni las enumerara**: no
     * estaban permitidas, simplemente no se las miraba.
     *
     * ▶ *Una guarda que censa estados por una lista de palabras deja fuera los estados que no se le
     * ocurrieron a quien la escribió.* El arreglo no es adivinar mejor: es que el caso de control
     * (`test_the_state_vocabulary_sees_every_state_word`) falle si la lista pierde una.
     */
    private const ESTADOS = ':hover|:focus|\.open\b|\.is-active|\.is-on\b|\.is-selected\b|\.is-current\b|\.active\b|aria-selected';

    public function test_no_interaction_state_reads_a_zone_token(): void
    {
        $permitidos = array_merge(array_keys(self::IDENTIDAD), array_keys(self::RELLENO_DE_MARCA), self::CAJON);
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/--zone-[12]/', $body)) {
                continue;
            }
            if (! preg_match('/'.self::ESTADOS.'/', $selector)) {
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

    /**
     * ⚠️⚠️ **Se localiza por PARTE de selector, no por la clave entera de la regla** (`#488`).
     * Buscaba `.faq__item.open .faq__q` como clave exacta y se puso ROJO con el producto sano en
     * cuanto los dos estados del acordeón pasaron a compartir una regla —`.faq__item:hover .faq__q,
     * .faq__item.open .faq__q`—, que es una forma perfectamente legítima de escribir lo mismo.
     * ▶ Y **no queda más débil que la que sustituye** (la regla de `#295`): sigue exigiendo que
     * **cada uno** de los cuatro estados lea `--interactive`, solo que ahora aguanta que estén
     * escritos juntos o por separado. Un estado que pierda el token sigue poniendo esto rojo.
     */
    public function test_the_faq_and_the_menu_read_the_interaction_token(): void
    {
        $porParte = [];
        foreach ($this->rules() as $selector => $body) {
            foreach (explode(',', $selector) as $parte) {
                $parte = trim($parte);
                if ($parte !== '') {
                    $porParte[$parte] = ($porParte[$parte] ?? '').' '.$body;
                }
            }
        }

        foreach (['.faq__item.open .faq__q', '.faq__item:hover .faq__q', '.cookie__config:hover', '.lang-dd__panel a.active'] as $selector) {
            $this->assertArrayHasKey($selector, $porParte, "`{$selector}` ya no tiene ninguna regla: este caso miraría el vacío");
            $this->assertMatchesRegularExpression('/color:\s*var\(--interactive\)/', $porParte[$selector], "`{$selector}` ya no lee `--interactive`");
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
