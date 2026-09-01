<?php

namespace Tests\Feature\Landing;

use Tests\TestCase;

/**
 * **LA ANATOMÍA DE LAS TARJETAS DE LA PORTADA** (`[DECIDIDO owner, 2026-09-01]`): su icono, su
 * unidad de precio y el tope de dos líneas de sus descripciones.
 *
 * ⚠️ Nació como `CardTextIsCappedTest`, solo del tope, y se renombró en el mismo commit al cubrir
 * también los iconos: un nombre que describe la mitad de lo que un fichero vigila es la forma más
 * barata de que la otra mitad se pierda cuando alguien busque «dónde se prueba esto».
 *
 * ── EL TOPE DE DOS LÍNEAS ──
 *
 * El encargo fue «limpiamos un poco los textos, descripción máximo 2 líneas», y se resolvió con
 * **los dos mecanismos a la vez**: el copy se reescribió para que quepa, y el corte se dejó puesto
 * como red.
 *
 * ▶ **Los dos, y no uno, porque cada uno solo falla en un sentido**: reescribir sin tope deja la
 * tarjeta a merced del siguiente que edite el texto —y las normas las edita el PANEL, o sea gente
 * que no ve este diseño—; poner el tope sin reescribir **esconde texto que alguien decidió
 * escribir**, y en una norma de seguridad eso puede ser justo la mitad que importa.
 *
 * ⚠️⚠️ **Y hay una asimetría deliberada entre las dos familias, que es lo que esta guarda protege
 * de verdad**: en `rules-peek` cortar es seguro porque esas cuatro normas son un ADELANTO y su CTA
 * lleva a `/normas`, donde están enteras; en `rules-must` no hay segunda casa, y por eso ahí el
 * texto se recortó de verdad en los `landing.php` de cada idioma en vez de fiarlo al corte.
 * ▶ Si alguien retira el `line-clamp` de `rules-peek` «porque esconde información», estará
 * quitando la red del sitio donde la información SÍ tiene dónde leerse entera.
 *
 * ⚠️ Se asevera el MECANISMO en la hoja y no el número de líneas pintadas: contar líneas exige un
 * navegador, y este gate no tiene ninguno. Lo que un navegador midió una vez (3 líneas en
 * `rules-must__text`, 3 en dos de las cuatro `rules-peek__desc`) es lo que motivó la regla; lo que
 * se vigila aquí es que la regla siga escrita.
 */
class CardAnatomyTest extends TestCase
{
    private const SHEET = 'public/css/landing.css';

    /**
     * Las descripciones de tarjeta que declaran tope, con el motivo por el que lo llevan.
     *
     * La lista **solo crece con su consumidor**: una entrada aquí sin regla en la hoja deja la
     * guarda vigilando la nada, que es el fallo que este repo ya ha pagado cuatro veces.
     *
     * @var array<string, string>
     */
    private const CAPPED = [
        '.rules-must__text' => 'la norma destacada: su texto se reescribió para caber, y el tope es la red',
        '.rules-peek__desc' => 'el adelanto de normas: cortar es seguro porque `/normas` las tiene enteras',
    ];

    /** **La hoja se lee y el localizador encuentra reglas.** Sin esto, lo de abajo puede pasar en vacío. */
    public function test_the_reader_sees_the_stylesheet(): void
    {
        $css = $this->sheet();

        // ⚠️ El suelo sale de MEDIR la hoja (656 bloques el 2026-09-01), no de una cifra redonda: la
        // primera versión de este caso puso 1000 a ojo y salió ROJA con el producto sano. Un umbral
        // inventado convierte la guarda-de-la-guarda en ruido, que es lo contrario de su propósito.
        $this->assertGreaterThan(400, substr_count($css, '{'), 'la hoja de la landing se lee vacía o ha cambiado de sitio');

        foreach (array_keys(self::CAPPED) as $selector) {
            $this->assertNotSame('', $this->rule($selector), "`{$selector}` ya no existe en la hoja: la entrada de la lista se quedó sin sujeto");
        }
    }

    public function test_every_capped_description_declares_two_lines(): void
    {
        foreach (self::CAPPED as $selector => $porque) {
            $cuerpo = $this->rule($selector);

            $this->assertMatchesRegularExpression(
                '/line-clamp:\s*2\b/', $cuerpo,
                "`{$selector}` ha perdido su tope de DOS líneas ({$porque}).\n".
                "⚠️ Sin él, un texto más largo descuadra la fila de tarjetas y nadie se entera hasta\n".
                'que alguien mira la página: ninguna otra guarda de este repo cuenta líneas.',
            );

            // ⚠️ `-webkit-box` no es un prefijo olvidado: es el ÚNICO mecanismo con soporte universal
            // para `line-clamp`. Sin él la propiedad no hace nada — y no falla, solo no corta.
            //
            // ⚠️⚠️ **Se asevera `display: -webkit-box` ENTERO, y la primera versión no lo hacía**:
            // buscaba la subcadena `-webkit-box` a secas, que **la cumple `-webkit-box-orient`** —una
            // propiedad que sigue ahí aunque el `display` cambie—. Mutado a `display: block`, la
            // guarda pasó en VERDE con el tope muerto. Es la misma trampa por SUBCADENA que este repo
            // ya pagó con `.nav-cta-med` dentro de `.nav-cta-med-NO` y con `width` dentro de
            // `stroke-width`.
            $this->assertMatchesRegularExpression(
                '/display:\s*-webkit-box\b/', $cuerpo,
                "`{$selector}` declara `line-clamp` pero no el `display: -webkit-box` que lo activa: ".
                'el tope está escrito y no corta nada.',
            );
            $this->assertStringContainsString(
                'overflow: hidden', $cuerpo,
                "`{$selector}` recorta las líneas pero no esconde el resto: el texto se sale.",
            );
        }
    }

    /**
     * **El copy de la norma destacada CABE en dos líneas sin ayuda del tope.**
     *
     * ⚠️ Es la otra mitad de la decisión, y la que el `line-clamp` no puede garantizar: ahí no hay
     * página con el texto entero, así que lo que el tope escondiera se perdería de verdad. El número
     * sale de medir en navegador el ancho real de la tarjeta con el cuerpo de 15 px.
     */
    public function test_the_must_rule_copy_still_fits_without_being_clipped(): void
    {
        foreach (['es', 'en', 'fr'] as $locale) {
            foreach (['register_text', 'socks_text'] as $clave) {
                $texto = (string) __("landing.rules.{$clave}", [], $locale);

                $this->assertLessThanOrEqual(
                    120, mb_strlen($texto),
                    "`landing.rules.{$clave}` en «{$locale}» mide ".mb_strlen($texto)." caracteres y no cabe en dos líneas.\n".
                    "⚠️ Aquí el tope de CSS lo escondería en vez de descuadrar, que es peor: esta tarjeta\n".
                    'no tiene una página donde leer el texto entero.',
                );
            }
        }
    }

    /**
     * **La tarjeta de tarifa lleva el MARCADOR del producto, el que elige el panel.**
     *
     * ⚠️⚠️ **No es un icono decorativo que decida esta vista**: sale de `ticket_types.icon` (`#259`),
     * que hasta ahora tenía **un solo consumidor, el cajón**. Con este segundo, el operador tiene por
     * fin una razón para rellenarlo — medido con los datos reales, usaba **2 claves de las 11**.
     *
     * ⚠️ Se asevera el PUENTE (`iconKey()`), no una clave concreta: qué dibujo lleva cada producto es
     * dato del panel, y clavar aquí `ticket` convertiría una elección del operador en un requisito
     * del gate.
     */
    public function test_the_price_card_paints_the_product_marker(): void
    {
        $vista = (string) file_get_contents(resource_path('views/components/site/price-card.blade.php'));

        $this->assertStringContainsString(
            // ⚠️ Comillas SIMPLES: en dobles, PHP interpolaría `$ticket` y la aguja buscaría otra cosa.
            '\'icons.\'.$ticket->iconKey()', $vista,
            "la tarjeta de tarifa ha dejado de pintar el marcador del producto, o ha dejado de\n".
            'resolverlo por el puente que ya usa la banda de cumpleaños.',
        );
        $this->assertStringContainsString(
            'price__ico', $vista,
            'el marcador perdió su envoltorio: `.price__ico` es lo que le da la caja y el fondo.',
        );
    }

    /**
     * **Y la unidad del precio NO vive dentro del número.**
     *
     * ⚠️⚠️ Ahí estuvo, y era un defecto VISIBLE: `.price__num` va a **80 px con `line-height: 0.85`**,
     * así que «POR PERSONA» heredaba esa caja y **se partía en dos con 68 px de hueco en medio**,
     * con el símbolo del euro montado sobre los céntimos. Medido con control el 2026-09-01: el
     * defecto era anterior a esa tanda.
     * ▶ Una unidad no es parte del número: es la línea de debajo.
     */
    public function test_the_price_unit_is_a_sibling_of_the_number_not_a_child(): void
    {
        $vista = (string) file_get_contents(resource_path('views/components/site/price-card.blade.php'));

        $this->assertDoesNotMatchRegularExpression(
            '/<div class="price__num">(?:(?!<\/div>).)*price__per/s', $vista,
            "la unidad del precio ha vuelto DENTRO de `.price__num`.\n".
            '⚠️ Ahí hereda 80 px de cuerpo y `line-height: 0.85`, y se parte en dos.',
        );
    }

    private function sheet(): string
    {
        return (string) file_get_contents(base_path(self::SHEET));
    }

    /** El cuerpo de la primera regla de un selector. Vacío si no existe. */
    private function rule(string $selector): string
    {
        // ⚠️ Se exige que el selector vaya SOLO delante de la llave —o al final de una lista—: sin
        // los límites, `.rules-must__text` casaría dentro de un `.rules-must__text--algo`, que es el
        // fallo que este repo pagó con `width` dentro de `stroke-width` (`#257`).
        preg_match(
            '/(?:^|[{}])[^{}]*?(?<![-\w])'.preg_quote($selector, '/').'(?![-\w])[^{}]*?\{([^{}]*)\}/m',
            $this->sheet(), $hit,
        );

        return $hit[1] ?? '';
    }
}
