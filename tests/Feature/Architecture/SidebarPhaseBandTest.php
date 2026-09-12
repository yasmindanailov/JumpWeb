<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **LA BANDA DE FASES DEL CAJÓN** (`DECISIONES #555`, T4·4b del carril del SPA).
 *
 * La banda vive en las **cinco pantallas del camino** —día · hora · carrito · quién eres · pagar— y
 * dice dos cosas: en cuál estás y por dónde se vuelve. Esta guarda vigila lo que, si alguien lo
 * deshace, **no rompe nada**: la compra sigue funcionando y el cliente deja de saber dónde está.
 *
 * ⚠️ **Lo que esta guarda NO puede ver es el ANCHO del texto renderizado**, y ahí estuvo el defecto
 * real de la tanda: con cinco fases el carril de cada una mide ~64 px a 390, y «Your basket» pedía
 * **66**. Lo cazó `scripts/sonda-armazon.mjs` midiendo la TINTA con un `Range` — `scrollWidth` no
 * sirve, porque se capa al `clientWidth` cuando el texto cabe y devuelve el mismo número para un
 * rótulo holgado y para uno al borde. Aquí queda su aproximación por caracteres, que es lo único que
 * un test sin navegador puede hacer.
 */
class SidebarPhaseBandTest extends TestCase
{
    /** Los idiomas del embudo público. `zh_CN` solo cubre el panel, así que no entra. */
    private const IDIOMAS = ['es', 'en', 'fr'];

    /** Las cinco fases, en el orden del camino. */
    private const FASES = ['phase_date', 'phase_time', 'phase_cart', 'phase_identify', 'phase_pay'];

    /**
     * El tope por rótulo, DERIVADO de lo medido y no elegido a ojo.
     *
     * A 390 px el carril de cada fase mide **64,4**, y los rótulos reales dan ~5,8 px por carácter
     * (medido: «Quién eres» y «Ton panier», 10 caracteres, **58 px**). 11 caracteres serían ~64 — el
     * borde exacto—, así que el tope es **10**, que deja 6,4 px de margen.
     *
     * ⚠️ **Es una APROXIMACIÓN y hay que saberlo**: los caracteres no miden píxeles, y diez letras
     * anchas pasarían esta guarda y se recortarían igual. La medida que manda es la de la sonda.
     */
    private const TOPE = 10;

    public function test_the_five_phases_exist_in_every_public_language(): void
    {
        foreach (self::IDIOMAS as $locale) {
            foreach (self::FASES as $clave) {
                $valor = __('tickets.'.$clave, [], $locale);

                $this->assertNotSame(
                    'tickets.'.$clave,
                    $valor,
                    "Falta «{$clave}» en «{$locale}».\n".
                    '⚠️ `t()` del cajón devuelve CADENA VACÍA cuando una clave falla, así que una fase '.
                    'sin rótulo no se ve rota: se ve como un hueco (`#333`).',
                );

                $this->assertNotSame('', trim($valor), "«{$clave}» está en blanco en «{$locale}»");
            }
        }
    }

    /** Con cinco fases en 390 px, un rótulo largo no desborda: se RECORTA con puntos suspensivos. */
    public function test_no_phase_label_is_long_enough_to_be_clipped(): void
    {
        foreach (self::IDIOMAS as $locale) {
            foreach (self::FASES as $clave) {
                $valor = __('tickets.'.$clave, [], $locale);

                $this->assertLessThanOrEqual(
                    self::TOPE,
                    mb_strlen($valor),
                    "«{$valor}» ({$locale}) pasa de ".self::TOPE." caracteres.\n".
                    "▶ A 390 px el carril de una fase mide 64,4 y el texto va `nowrap` con elipsis: un\n".
                    "rótulo más largo se corta, y una fase que no se puede leer no dice en qué paso estás.\n".
                    '▶ Si de verdad tiene que ser más largo, MÍDELO con `scripts/sonda-armazon.mjs` antes.',
                );
            }
        }
    }

    /**
     * **El rótulo de la fase del carrito es el NOMBRE de la pantalla a la que lleva.**
     *
     * Nació como «Tu cesta» mientras la pantalla se llamaba «Tu carrito» — dos palabras para la misma
     * cosa, en la misma compra. Una fase nombra una pantalla: si no la llama por su nombre, el cliente
     * no sabe que ya ha estado ahí.
     */
    public function test_the_cart_phase_calls_the_cart_screen_by_its_name(): void
    {
        foreach (self::IDIOMAS as $locale) {
            $this->assertSame(
                __('tickets.cart_title', [], $locale),
                __('tickets.phase_cart', [], $locale),
                "La fase del carrito y el título de esa pantalla dicen cosas distintas en «{$locale}».",
            );
        }
    }

    /**
     * **Ni mayúsculas ni espaciado de letra en el rótulo, y no es gusto: con cinco fases NO CABEN.**
     *
     * Costaban ~9 px por rótulo —justo los que le faltaban a «Quién eres»— y el artboard ya los dibuja
     * capitalizados y sin espaciado. La regla vieja venía de cuando eran tres rótulos cortos.
     */
    public function test_the_phase_label_spends_no_width_on_decoration(): void
    {
        $css = (string) file_get_contents(base_path('public/css/site.css'));

        $this->assertTrue(
            (bool) preg_match('/^\.bk-seg__label\s*\{([^}]*)\}/m', $css, $m),
            'no se encuentra la regla de `.bk-seg__label`: sin sujeto este caso no vigila nada',
        );

        foreach (['text-transform', 'letter-spacing'] as $propiedad) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?:^|;)\s*'.preg_quote($propiedad, '/').'\s*:/',
                $m[1],
                "El rótulo de fase ha recuperado `{$propiedad}`.\n".
                '▶ Medido: con mayúsculas y `letter-spacing: 0.06em`, «Quién eres» pedía 73 px en un '.
                'carril de 64,4 y salía con puntos suspensivos.',
            );
        }
    }

    /**
     * **UN «Volver» por pantalla, y es el de la banda.**
     *
     * Los pasos 4, 5 y 8 traían el suyo propio porque no tenían banda; con la banda en las cinco
     * pantallas, conservarlo dejaba **dos** en la misma pantalla. Que vuelva no rompe nada: solo pone
     * dos flechas seguidas, y por eso hace falta esta guarda.
     */
    public function test_no_funnel_step_paints_its_own_back_button(): void
    {
        foreach (['CartStep', 'IdentifyStep', 'PayStep'] as $paso) {
            $fuente = (string) file_get_contents(resource_path('js/sidebar/steps/'.$paso.'.vue'));
            // ⚠️ Se desnudan los comentarios: los tres explican POR QUÉ ya no lo tienen, y buscar la
            // clase sobre el fichero entero absolvería al marcado con su propia nota (la trampa de
            // `#553`, donde un comentario salvaba a la mutación que retiraba el atributo).
            $marcado = (string) preg_replace(['#<!--.*?-->#s', '#/\*.*?\*/#s', '#//[^\n]*#'], '', $fuente);

            $this->assertStringNotContainsString(
                'bk-back',
                $marcado,
                "«{$paso}» ha recuperado su «Volver» propio.\n".
                '▶ La banda de progreso ya lo trae para las cinco pantallas del camino: con los dos, '.
                'la pantalla enseña dos flechas seguidas que hacen lo mismo.',
            );
        }
    }

    /**
     * **Con sesión, «Quién eres» no se pinta — y la señal es la del SERVIDOR, no la sesión de ahora.**
     *
     * Medido en vivo antes de tocarlo: con sesión el carrito manda DIRECTO al pago, así que esa
     * pantalla no se visita nunca. La banda la pintaba igual y hacía tres cosas mal a la vez —prometía
     * dos pantallas donde quedaba una, el contador saltaba del 3 al 5, y la fase salía HECHA sin que
     * el cliente hubiera estado en ella—.
     *
     * ⚠️ **`props.userId` viene del HTML** (`auth()->id()` al pintar la página) y ahí está la gracia:
     * no cambia dentro del embudo. Con el estado vivo, quien entra sin sesión y se identifica por el
     * camino vería desaparecer la fase **justo después de completarla**.
     */
    public function test_the_identify_phase_follows_the_session_the_page_was_painted_with(): void
    {
        $seccion = (string) file_get_contents(resource_path('js/sidebar/sections/PurchaseSection.vue'));

        $this->assertStringContainsString(
            'pideIdentificarse: ! props.userId',
            $seccion,
            "La señal de la fase de identificación ha dejado de salir del HTML.\n".
            '▶ Con el estado vivo de sesión, quien se identifica DENTRO del embudo ve desaparecer esa '.
            'fase justo al completarla — y esa pantalla sí la visitó.',
        );

        $progress = (string) file_get_contents(resource_path('js/sidebar/progress.js'));

        // ⚠️ La segunda mitad de la regla, y sin ella la sesión caída en otra pestaña deja al cliente
        // en una pantalla que la banda no reconoce: `buildProgress` devolvería `null`.
        $this->assertStringContainsString(
            'step === STEPS.IDENTIFY',
            $progress,
            'La fase de identificación ha dejado de volver cuando el cliente ESTÁ en ella: con la '.
            'sesión caída, la banda no reconocería esa pantalla.',
        );
    }

    /**
     * **A dónde vuelve y cómo se llama salen de LA MISMA fila.**
     *
     * Con el rótulo en un sitio y el destino en otro, cambiar uno sin el otro deja un botón que dice
     * «Volver al carrito» y lleva a otra parte — **sin que nada falle**. Aquí se comprueba que el
     * embudo no ha vuelto a decidir el destino por su cuenta.
     */
    public function test_the_funnel_applies_the_back_plan_instead_of_deciding_it(): void
    {
        $seccion = (string) file_get_contents(resource_path('js/sidebar/sections/PurchaseSection.vue'));

        $this->assertStringContainsString(
            'backPlan(store.step)',
            $seccion,
            'El embudo ha dejado de aplicar el plan de `progress.js` y vuelve a decidir a dónde va.',
        );

        $progress = (string) file_get_contents(resource_path('js/sidebar/progress.js'));

        foreach (self::FASES as $clave) {
            $this->assertMatchesRegularExpression(
                "/label: '".preg_quote($clave, '/')."'.*?backTo:/",
                $progress,
                "La fila de «{$clave}» ha perdido su destino: el rótulo y el sitio al que lleva tienen ".
                'que viajar juntos.',
            );
        }
    }
}
