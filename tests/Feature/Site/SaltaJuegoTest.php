<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **«Salta la ciudad» — el minijuego del hero del cierre** (`#231`).
 *
 * Lo que se fija aquí NO es que el juego «funcione» —eso lo dice el navegador, y se recorrió: 42 m
 * jugados con la barra espaciadora y cero errores de JavaScript—. Lo que se fija son las cuatro
 * decisiones que **se pueden deshacer sin que nada falle**, y que son justo las que costarían caras:
 *
 * 1. Que el motor siga **descargándose aparte**. Es lo único que impide que un juego de 12 KB se
 *    le cobre a todo visitante de la portada por algo que solo se alcanza al final del todo.
 * 2. Que el lienzo siga siendo **decoración para un lector de pantalla**, y que la parte que sí se
 *    puede describir —jugar, el marcador, el resultado— siga siendo texto de verdad.
 * 3. Que siga habiendo **suelo sin JavaScript**: el juego es un extra, y la tarjeta de cierre tiene
 *    que seguir ofreciendo comprar y llamar aunque el trozo no llegue nunca.
 * 4. Que el récord siga guardándose con **su clave**, o cada despliegue le borraría la marca a
 *    quien haya jugado.
 */
class SaltaJuegoTest extends TestCase
{
    use RefreshDatabase;

    private function motor(): string
    {
        return (string) file_get_contents(resource_path('js/site/salta.js'));
    }

    #[Test]
    public function el_motor_se_carga_aparte_y_no_engorda_el_bundle_de_la_portada(): void
    {
        $app = (string) file_get_contents(resource_path('js/app.js'));

        // ⚠️ **`import(` DINÁMICO, no `import … from`.** Es toda la diferencia: el estático mete el
        // juego en el trozo de la landing; el dinámico le da uno propio. Medido al construir: el
        // motor sale en `salta-*.js` (12 kB) y el bundle de la portada pasa de 19,4 a 21,7 kB. Con
        // un import estático habría pasado de 19,4 a ~32.
        $this->assertMatchesRegularExpression(
            "/await import\(\s*['\"]\.\/site\/salta\.js['\"]\s*\)/", $app,
            "El motor del minijuego ha dejado de cargarse aparte.\n".
            "▶ Son ~12 kB sobre un bundle de 19, y el juego solo se alcanza al FINAL del todo de la\n".
            '  portada: con un `import` estático se lo cobras a todo el que entre.',
        );

        $this->assertStringNotContainsString(
            "from './site/salta.js'", $app,
            'el motor se importa también de forma estática: eso anula el trozo aparte, porque Vite '.
            'ya no puede separarlo.',
        );
    }

    #[Test]
    public function el_juego_es_alcanzable_sin_ver_el_lienzo(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        // El dibujo NO se puede describir, y por eso se declara decorativo…
        $this->assertMatchesRegularExpression(
            '/<canvas[^>]*class="salta__lienzo"[^>]*aria-hidden="true"/', $html,
            'el lienzo del juego no se declara decorativo. Un lector de pantalla intentaría '.
            'anunciar un `<canvas>` que no puede describir.',
        );

        // …pero la ACCIÓN sí, y ésa tiene que ser texto de verdad.
        foreach ([
            'salta__invita' => 'el botón de jugar',
            'salta__hud' => 'el marcador',
            'salta__fin' => 'el resultado',
        ] as $clase => $que) {
            $this->assertStringContainsString(
                $clase, $html,
                "falta {$que}: sin él, el juego solo existe para quien ve el lienzo.",
            );
        }

        // ⚠️ El marcador se anuncia solo cuando cambia: sin `aria-live`, quien no ve el lienzo no
        // se entera de cómo va la partida hasta que se acaba.
        $this->assertMatchesRegularExpression(
            '/class="salta__hud"[^>]*aria-live="polite"/', $html,
            'el marcador no se anuncia: `aria-live` es lo único que lo cuenta a quien no lo ve.',
        );

        // Y el botón dice qué es y cómo se juega, no solo «Jugar».
        $this->assertMatchesRegularExpression(
            '/class="salta__invita"[^>]*aria-label="[^"]{20,}"/', $html,
            'el botón de jugar no explica qué hace: «Jugar», a secas, no dice a qué.',
        );
    }

    #[Test]
    public function la_tarjeta_de_cierre_sigue_ofreciendo_comprar_sin_el_juego(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $inicio = strpos($html, 'class="reserve__box"');
        $this->assertNotFalse($inicio, 'no se encuentra la tarjeta de cierre');
        $tarjeta = substr($html, $inicio, strpos($html, '</section>', $inicio) - $inicio);

        // ⚠️ **El juego es un EXTRA y esto lo fija**: se descarga aparte, así que puede no llegar
        // nunca —red mala, bloqueador, error del trozo—. Si la tarjeta de cierre dependiera de él
        // para ofrecer algo, el final de la portada se quedaría mudo por un fallo de un adorno.
        $this->assertStringContainsString(
            route('entradas'), $tarjeta,
            'la tarjeta de cierre ha perdido su botón de comprar. El minijuego es un extra: si el '.
            'cierre depende de que su trozo cargue, un fallo de red deja la última pantalla muda.',
        );
    }

    #[Test]
    public function el_record_se_guarda_con_su_clave_y_sobrevive_al_modo_privado(): void
    {
        $motor = $this->motor();

        $this->assertStringContainsString(
            "'pjp-salta-record'", $motor,
            'ha cambiado la clave del récord en `localStorage`: a quien haya jugado se le borra la '.
            'marca, y no hay forma de recuperarla.',
        );

        // ⚠️ **En modo privado `localStorage` LANZA, no devuelve null.** Sin los dos `try`, el juego
        // reventaría al arrancar en una ventana privada de Safari — y reventaría el bucle entero,
        // no solo el récord.
        $this->assertSame(
            2, substr_count($motor, 'try {'),
            'el acceso a `localStorage` ha perdido alguna de sus dos guardas. En modo privado no '.
            'devuelve null: LANZA, y se lleva por delante el arranque del juego.',
        );
    }

    #[Test]
    public function con_movimiento_reducido_no_se_descarga_ni_corre_la_demo(): void
    {
        $app = (string) file_get_contents(resource_path('js/app.js'));
        $motor = $this->motor();

        // ⚠️ **Respetar la preferencia y AUN ASÍ descargar 12 kB sería cumplirla a medias.** Quien
        // ha dicho que no quiere movimiento tampoco quiere pagar el peso de una animación que no
        // va a ver. Se trae solo si la pide pulsando.
        $this->assertMatchesRegularExpression(
            '/if \(!this\.reduce\) await this\.carga\(\)/', $app,
            'el motor se precarga también con `prefers-reduced-motion`: son 12 kB de una animación '.
            'que esa persona ha pedido no ver.',
        );

        // Y si llega a cargarse —porque alguien pulsa «Jugar»—, el fondo no corre: un fotograma.
        $this->assertMatchesRegularExpression(
            '/if \(O\.reduce\)[^}]*quieto/s', $motor,
            'con movimiento reducido el motor sigue animando el fondo. Debe pintar UN fotograma y '.
            'parar el bucle: el juego es una acción del usuario, la demo no.',
        );
    }
}
