<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * LA IDENTIDAD COMO SUPERFICIE · la pasada de vestido (`DECISIONES #537`).
 *
 * ⚠️ **Por qué existe.** Medido antes de la tanda con `scripts/sonda-color.mjs`: la portada tenía
 * **1,1 %** de color y las seis páginas interiores **0,0 %**, mientras el sistema del cliente
 * declara `limite.proporcion: '60/30/10'` —neutro / CIAN IDENTIDAD / naranja acción— y su masa de
 * mural da azul 46 %. El color que ES la identidad ocupaba el **0,10 %** de la página.
 *
 * ⚠️⚠️ **Lo que vigila no es un color: son las CUATRO REGLAS que hicieron el reparto correcto**,
 * porque las cuatro se descubrieron rompiéndolas y ninguna la ve una captura:
 *
 *  R1 · el color depende de la SUPERFICIE (Azul Muro sobre tinta da **2,61**; el cian, 6,85).
 *  R2 · sobre un tinte solo aguantan Tinta y Azul Muro (Humo 4,40 · Lima 800 4,18 · Amar 800 3,90).
 *  R3 · teñir es convertir en CAJA: solo se tiñe lo que tiene aire (`.before__row` llegaba a 0 px).
 *  R4 · una tarjeta con un dato de color propio NO se tiñe (las estrellas solo pasan sobre blanco).
 */
class IdentityTintTest extends TestCase
{
    private function site(): string
    {
        return (string) file_get_contents(public_path('css/site.css'));
    }

    private function landing(): string
    {
        return (string) file_get_contents(public_path('css/landing.css'));
    }

    /** Los tintes se DERIVAN del color del cliente: es lo que los hace white-label. */
    public function test_los_tintes_se_derivan_y_no_se_teclean(): void
    {
        $css = $this->site();

        $this->assertMatchesRegularExpression(
            '/--identity-fill:\s*var\(--strip-1,/', $css,
            'el color de identidad dejó de salir del paquete del cliente. Si se teclea un hex, la '.
            'siguiente instalación hereda el cian de ÉSTA — que es justo lo que `DECISIONES #1` impide.',
        );

        foreach (['--tint-info', '--tint-info-border', '--tint-attn', '--tint-ok'] as $token) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').':\s*color-mix\(in srgb,/', $css,
                "`{$token}` dejó de derivarse. La receta es el color al 14 % (superficie) y al 30 % ".
                '(borde) sobre `--bg`: verificado en navegador, devuelve #D5EAEE y #B3DDEB, que son '.
                'al dígito los dos valores que `tokens-pjp.js` declara como `tintePapel`.',
            );
        }
    }

    /**
     * R1 · el rótulo de sección es la pieza más repetida (una por sección y una por página, las
     * tres clases comparten regla desde `#525`), y por eso es donde entra la identidad.
     * ⚠️ El TEXTO en `--interactive` (que cambia por superficie) y el filete en relleno pleno:
     * sobre papel el cian da 2,45 y no puede ser letra, pero como barra sí.
     */
    public function test_el_rotulo_lleva_la_identidad_con_sus_dos_voces(): void
    {
        $css = $this->landing();

        $this->assertMatchesRegularExpression(
            '/\.sec-head__eyebrow,\s*\.zones__eyebrow,\s*\.page__eyebrow\s*\{[^}]*color:\s*var\(--interactive\)/s',
            $css,
            'el rótulo perdió el color de identidad. Y tiene que ser `--interactive`, que cambia con '.
            'la superficie: un valor fijo falla en la mitad de la web (Azul Muro sobre tinta, 2,61).',
        );
        $this->assertMatchesRegularExpression(
            '/\.sec-head__eyebrow::before,[^{]*\{[^}]*background:\s*var\(--identity-fill\)/s',
            $css,
            'el filete del rótulo perdió el relleno de identidad.',
        );
    }

    /**
     * R2 · lo que se pinta sobre un tinte va en TINTA (o en Azul Muro). No es criterio propio:
     * `tokens-pjp.js` ya lo trae escrito («Humo da 4,43»), y el cálculo de esta tanda devolvió esas
     * mismas cifras.
     *
     * ❗❗❗ **RE-APUNTADO EN `#549`, Y AL RE-APUNTARLO APARECIÓ EL DEFECTO QUE NO VIGILABA.** El sujeto
     * era `.addon-card__unit`, y la ficha de complemento **ya no está teñida** (`[owner]`: «quítale
     * ese color azul»), así que este caso se quedaba pasando sobre una superficie blanca donde Humo
     * es legítimo — verde para siempre, sin mirar nada.
     *
     * ▶ Al buscarle un sujeto vivo se midieron las DOS superficies teñidas que quedan, y la tarjeta de
     * canal de `/contacto` llevaba **dos textos en Humo encima del tinte**: 4,40 con el paquete de
     * esta instalación. *La regla estaba escrita, decidida y medida desde `#537`, y se incumplía en la
     * pantalla de al lado porque la guarda tenía un solo sujeto.*
     *
     * ⚠️ Se vigilan las dos superficies vivas, no una: el día que una se destiña, la otra sostiene el
     * caso — y si se destiñen las dos, el caso hay que retirarlo con la regla, no dejarlo huérfano.
     */
    public function test_lo_que_va_sobre_un_tinte_no_usa_el_gris_de_apoyo(): void
    {
        $css = $this->landing();

        // Guarda de la guarda: los sujetos SIGUEN teñidos. Sin esto, destiñe la tarjeta y el caso
        // pasa en verde vigilando dos superficies blancas.
        foreach (['.channel', '.bar-sheet'] as $tenido) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($tenido, '/').'\s*\{[^}]*background:\s*var\(--tint-info\)/s', $css,
                "`{$tenido}` ya no está teñido: este caso se quedaría sin sujeto. Si el tinte se ha ".
                'retirado a propósito, re-apunta la regla a la superficie teñida que quede.',
            );
        }

        foreach (['.channel__label', '.channel__hint'] as $encima) {
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($encima, '/').'\s*\{[^}]*color:\s*var\(--fg-mute\)/s', $css,
                "`{$encima}` va en Humo sobre el tinte de su tarjeta: da **4,40** con el paquete de ".
                'esta instalación y no llega a AA. Sobre un tinte solo aguantan Tinta (14,83) y Azul '.
                'Muro (5,68). ⚠️ Con el Humo por defecto del producto sale 4,52 y parecería sano: el '.
                'cálculo hay que hacerlo con el tema instalado.',
            );
        }
    }

    /**
     * R3 · `.before__row` es una FILA de una `<dl>` con `padding: 14px 0`. Teñirla puso el color a
     * **0 px de la letra** — el defecto que el owner vio. Ahí el color entra como TEXTO, que sobre
     * tinta sí puede (Cian 6,85).
     */
    public function test_la_fila_sin_aire_no_se_tine(): void
    {
        $css = $this->landing();

        $this->assertDoesNotMatchRegularExpression(
            '/\.before__row\s*\{[^}]*background:\s*var\(--tint-/s', $css,
            'la fila de «Antes de venir» volvió a teñirse. No tiene relleno horizontal, así que el '.
            'color llega pegado al texto: ahí la identidad entra por el TEXTO, no por la superficie.',
        );
        $this->assertMatchesRegularExpression(
            '/\.before__row-key\s*\{[^}]*color:\s*var\(--interactive\)/s', $css,
            'la clave de la fila perdió la identidad.',
        );
    }

    /**
     * R4 · la tarjeta de reseña lleva estrellas en Amarillo 800, que solo pasan sobre BLANCO
     * (4,87); sobre cualquier tinte caen a 3,89. Su color entra por el borde y por la inicial.
     */
    public function test_la_tarjeta_con_dato_de_color_propio_no_se_tine(): void
    {
        $css = $this->landing();

        $this->assertMatchesRegularExpression(
            '/\.rev__card\s*\{[^}]*background:\s*var\(--bg-card\)/s', $css,
            'la tarjeta de reseña se tiñó: sus estrellas (Amarillo 800) solo pasan sobre blanco.',
        );
        $this->assertMatchesRegularExpression(
            '/\.rev__card\s*\{[^}]*border:\s*1px solid var\(--tint-ok-border\)/s', $css,
            'la tarjeta de reseña perdió el borde de color, que es por donde recibe la identidad.',
        );
    }

    /**
     * El CTA del armazón · `[DECIDIDO owner, 2026-09-12]` (`#581`): «el reservar principal, color cian».
     *
     * ⚠️ Su relleno es la MARCA de la instalación (`--brand`, que emite el panel) — no el cian escrito
     * ni el Azul Muro del secundario, que es lo que `#537` le había puesto. Llegó a haber además un
     * azul derivado al 65 % (`--identity-deep`) y **se retiró**: era un cuarto color de botón.
     * ⚠️⚠️ El rótulo es `--on-brand`, calculado por luminancia para esa marca: sobre el Cian PJP el
     * blanco da **2,70** y la tinta **6,85**.
     */
    public function test_el_cta_del_armazon_lleva_el_relleno_de_identidad(): void
    {
        $css = $this->site();

        $this->assertMatchesRegularExpression(
            '/\.cta-med\s*\{\s*background:\s*var\(--brand\);\s*color:\s*var\(--on-brand\)/s',
            $css,
            'el CTA del armazón perdió el relleno de MARCA (`#581`). Es el color del panel, y su rótulo '.
            'va con `--on-brand` porque blanco sobre el cian da **2,70**: no lo cambies a un color '.
            'escrito ni a blanco sin reabrir la decisión.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/var\(--identity-deep/', $css,
            'ha vuelto un azul propio para el botón. El relleno azul es el Azul Muro de la paleta, '.
            'que ya existe y ya hace de fantasma: un segundo azul es un cuarto color de botón.',
        );
    }

    /**
     * ❗❗❗ **EL RÓTULO DEL CTA CONSERVA BUNGEE, Y ESTA GUARDA PROTEGE LA EXCEPCIÓN, NO LA NORMA.**
     *
     * Está escrita al revés que sus hermanas a propósito. La regla dura del sistema prohíbe Bungee
     * en un botón **dos veces**, y el artboard del canvas dibuja este mismo botón en Hanken Grotesk
     * 800 a 16 px (verificado renderizándolo: las siete apariciones de «Reservar»). Con todo eso
     * delante, `[DECIDIDO owner, 2026-09-12]` **se queda en Bungee**: el CTA es la pieza que más
     * identifica la web y en Hanken pierde el carácter del parque.
     *
     * ▶ Lo que esta guarda impide es que alguien lo «ARREGLE» leyendo la regla y creyendo que
     * encontró un defecto — que es exactamente lo que pasó hoy hasta que se preguntó. Es el mismo
     * trato que `GoogleButtonBrandingTest` da a la otra excepción declarada de esta regla.
     *
     * ⚠️ Y no se «mejora» subiendo el peso: Bungee trae UNA sola cara, así que un 800 la SINTETIZA
     * (el defecto que `#479` midió en `.rides__title`); no falla nada, solo se ve peor.
     */
    public function test_el_rotulo_del_cta_conserva_su_excepcion_de_bungee(): void
    {
        $css = $this->site();

        $this->assertMatchesRegularExpression(
            '/\.cta-med__t,\s*\.cta-ghost__t\s*\{[^}]*font-family:\s*var\(--font-display\)/s',
            $css,
            'el rótulo del CTA perdió Bungee. NO es un defecto que corregir: es una excepción '.
            'decidida por el owner con la regla y el artboard delante. Si de verdad se quiere '.
            'cambiar, se reabre la decisión y se borra esta guarda con ella.',
        );
        $this->assertMatchesRegularExpression(
            '/\.cta-med__t,\s*\.cta-ghost__t\s*\{[^}]*font-weight:\s*400/s',
            $css,
            'el rótulo del CTA subió de peso. Bungee tiene UNA sola cara: un 800 la sintetiza.',
        );
    }
}
