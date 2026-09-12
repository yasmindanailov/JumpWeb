<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **LAS DOS COSTURAS QUE LAS PANTALLAS DE AUTH TRAJERON DEL MODAL** (`#566`, grietas 12 y 13).
 *
 * Hasta agosto de 2026 entrar, crear cuenta y recuperar vivían en un **modal de la cabecera**, y se
 * mudaron al cajón sin rediseñarse — que era lo correcto para mudarlas rápido. Al mirarlas dentro se
 * ven dos cosas que eran del marco anterior:
 *
 *   · **12 · el ANTETÍTULO.** Un recurso de la PORTADA, donde dice el eje de una sección. En el modal
 *     era su única jerarquía; aquí la pantalla ya se llama «Inicia sesión».
 *   · **13 · el ENLACE LEGAL dentro de su frase.** Un enlace inline mide **20 px** de alto contra el
 *     suelo de **48** que declara el propio producto.
 *
 * ⚠️⚠️ **Y el censo del canvas se quedaba corto en las DOS, medido aquí antes de tocar nada**: decía
 * «son las únicas tres pantallas con antetítulo» y el código pintaba **cinco** —su propio artboard
 * dibuja cuatro, con «Casi listo» colgando del mismo interruptor que los otros tres—; y decía «el
 * alta» cuando el aviso de privacidad lo pintan **las dos**, la de contraseña y la de Google. *Un
 * artboard describe el código del día en que se dibujó.*
 *
 * ▶ **Por qué hace falta esta guarda y no bastan las que había**: el diff de árbol
 * (`SidebarDomContractTest`) congela una FOTO —así que se regenera y acepta lo que sea—, el
 * presupuesto de textos mide bytes, y `TouchTargetTest` recorre rutas de la web pública, donde el
 * cajón no está. Ninguna pregunta **qué forma tiene** lo que se emite.
 */
class SidebarAuthScreensTest extends TestCase
{
    /**
     * Las dos claves de antetítulo que SOBREVIVEN, y quién las pinta.
     *
     * ⚠️⚠️ **No son un descuido de la limpieza: son de la WEB.** `reset` lo pinta la página de
     * restablecer contraseña y `orders` la de reintentar el pago, las dos con `<x-site.page-head>`,
     * que es del carril de la web y SÍ usa antetítulo — es una página, no una pantalla del cajón.
     * Quien «termine el trabajo» borrándolas deja dos páginas con el rótulo vacío.
     */
    private const ANTETITULOS_DE_LA_WEB = [
        'reset' => 'resources/views/auth/reset-password.blade.php',
        'orders' => 'resources/views/payments/retry-redirect.blade.php',
    ];

    /** Los cinco sitios que enseñan el texto del descargo. */
    private const DESCARGO = [
        'resources/js/sidebar/steps/RegisterForm.vue',
        'resources/js/sidebar/account/zones/GoogleSignupZone.vue',
        'resources/js/sidebar/account/zones/PrivacyZone.vue',
        'resources/js/sidebar/account/zones/DependentCard.vue',
        'resources/js/sidebar/account/zones/DependentsZone.vue',
    ];

    /** Las dos altas, que son las que enseñan la política de privacidad. */
    private const ALTAS = [
        'resources/js/sidebar/steps/RegisterForm.vue',
        'resources/js/sidebar/account/zones/GoogleSignupZone.vue',
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Grieta 12 · el cajón titula y punto
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Ninguna pantalla del cajón lleva antetítulo. */
    public function test_no_drawer_screen_carries_an_eyebrow(): void
    {
        $culpables = [];

        foreach ($this->plantillasDelCajon() as $ruta) {
            if (str_contains((string) file_get_contents($ruta), 'class="eyebrow"')) {
                $culpables[] = str_replace(base_path().'/', '', $ruta);
            }
        }

        $this->assertSame(
            [],
            $culpables,
            "Estas pantallas del cajón han recuperado el antetítulo:\n  ".implode("\n  ", $culpables).
            "\n\nEn las 25 el cajón titula y punto (`#566`, `[DECIDIDO owner]`). El antetítulo es un ".
            'recurso de la PORTADA, donde dice el eje de una sección; aquí la pantalla ya se llama.'
        );
    }

    /**
     * **El instrumento ve las plantillas.**
     *
     * Sin este control el caso de arriba pasaría en verde sobre una lista vacía, que es como nacen
     * ciegas las guardas de este repo — cuatro veces contadas.
     */
    public function test_the_scan_sees_the_drawer_templates(): void
    {
        $plantillas = $this->plantillasDelCajon();

        $this->assertGreaterThan(25, count($plantillas), 'el barrido ve muy pocas plantillas del cajón');

        $rutas = array_map(fn (string $r): string => str_replace(base_path().'/', '', $r), $plantillas);

        $this->assertContains('resources/js/sidebar/steps/LoginForm.vue', $rutas);
        $this->assertContains('resources/js/sidebar/account/zones/ForgotZone.vue', $rutas);
    }

    /** Los dos antetítulos de la WEB siguen vivos y con quien los pinta. */
    public function test_the_two_web_eyebrows_survive_with_their_painter(): void
    {
        foreach (self::ANTETITULOS_DE_LA_WEB as $grupo => $vista) {
            foreach (['es', 'en', 'fr'] as $idioma) {
                // ⚠️⚠️ **Con `__()` esto pasaba en VERDE con la clave borrada, y lo dijo el arnés**:
                // Laravel devuelve **la propia clave** cuando falta, así que `!== ''` se cumple
                // siempre. Es el mismo hueco que `#508` pagó con `Lang::has()` y su tercer
                // parámetro. *Una aserción que no puede fallar no vigila nada.*
                $this->assertTrue(
                    \Illuminate\Support\Facades\Lang::has("account.{$grupo}.eyebrow", $idioma, false),
                    "`account.{$grupo}.eyebrow` se ha borrado en `{$idioma}`: NO era del cajón, la pinta la web."
                );
            }

            $this->assertStringContainsString(
                "account.{$grupo}.eyebrow",
                (string) file_get_contents(base_path($vista)),
                "`{$vista}` ha dejado de pintar su antetítulo: entonces la clave sobra y el rótulo se ".
                'quedaría en blanco — que es el mismo hueco que falla hacia invisible de `#333`.'
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Grieta 13 · un documento legal es un control, no una palabra subrayada
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Los cinco sitios del descargo usan UNA pieza.**
     *
     * ⚠️ Lo que esto impide no es que desaparezca el componente: es que alguien vuelva a escribir el
     * `<details><summary>` a mano «solo en esta pantalla». Ese día hay dos descargos, uno con la fila
     * de 48 y otro con el triángulo del navegador a 20, y nada falla.
     */
    public function test_the_five_waiver_screens_share_one_piece(): void
    {
        foreach (self::DESCARGO as $vista) {
            $fuente = (string) file_get_contents(base_path($vista));

            $this->assertStringContainsString(
                '<WaiverDoc',
                $fuente,
                "`{$vista}` ya no usa la pieza compartida del descargo (`WaiverDoc.vue`)."
            );

            $this->assertStringNotContainsString(
                '<summary>',
                $fuente,
                "`{$vista}` escribe su propio `<summary>` a pelo: vuelve el marcador del navegador y ".
                'los 20 px de alto que la grieta 13 cerró.'
            );
        }
    }

    /** La fila del descargo lleva la receta compartida y el CHEVRON, porque despliega aquí. */
    public function test_the_waiver_row_uses_the_shared_recipe_with_a_chevron(): void
    {
        $fuente = (string) file_get_contents(base_path('resources/js/sidebar/WaiverDoc.vue'));

        $this->assertStringContainsString(
            'class="cal-more legal-more"',
            $fuente,
            'la fila del descargo ha dejado de compartir la receta de «Ver más fechas».'
        );

        // ⚠️⚠️ **Con `assertStringContainsString` esto pasaba en verde renombrando la clase a
        // `cal-more__chevron`, y lo dijo el arnés**: el nombre nuevo CONTIENE al viejo, así que la
        // aserción se cumple mientras el CSS ya no casa con nada. Es la trampa de la subcadena que
        // este repo lleva pagada cuatro veces (`#253`, `#257`, `#264`, `#295`) — y la pagué otra vez.
        $this->assertMatchesRegularExpression(
            '/cal-more__chev(?![\w-])/',
            $fuente,
            "la fila del descargo ha perdido su chevron.\n".
            'El icono es lo que distingue las dos filas-puerta (`#562`): chevron cuando despliega AQUÍ, '.
            'flecha cuando SALE a otra página.'
        );
    }

    /**
     * **El aviso de privacidad de las dos altas no lleva el enlace dentro.**
     *
     * ⚠️⚠️ Y se comprueban las DOS cosas, porque el defecto tiene dos formas: que el literal recupere
     * su `<a>` —y entonces se vería el marcado ESCRITO en pantalla, porque ya no hay `v-html`— y que
     * la plantilla recupere el `v-html` —y entonces vuelven los 20 px—.
     */
    public function test_neither_signup_puts_the_privacy_link_inside_its_sentence(): void
    {
        $this->assertStringNotContainsString(
            '<a ',
            (string) __('account.register.privacy_notice'),
            "El aviso de privacidad ha recuperado su enlace dentro.\n".
            'Desde `#566` se pinta como TEXTO: un `<a>` aquí saldría literal en pantalla, dentro de un '.
            'texto legal, y para el diff de árbol y para la suite eso es texto.'
        );

        foreach (self::ALTAS as $vista) {
            $fuente = (string) file_get_contents(base_path($vista));

            $this->assertStringNotContainsString(
                'v-html="a(\'register.privacy_notice\')"',
                $fuente,
                "`{$vista}` vuelve a inyectar el aviso de privacidad como HTML: el enlace vive en su "
                .'propia fila desde `#566`.'
            );

            $this->assertStringContainsString(
                'register.privacy_read',
                $fuente,
                "`{$vista}` ha perdido la fila que abre la política.\n".
                'Es el único sitio donde las dos altas la enseñan (`#350`): sin ella el aviso informa '.
                'de un documento que no se puede leer.'
            );
        }
    }

    /** @return list<string> las plantillas de Vue del cajón */
    private function plantillasDelCajon(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/js/sidebar'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($it as $fichero) {
            if ($fichero->isFile() && $fichero->getExtension() === 'vue') {
                $out[] = $fichero->getPathname();
            }
        }

        sort($out);

        return $out;
    }
}
