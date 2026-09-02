<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsSiteStylesheets;
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
    use ReadsSiteStylesheets;
    use RefreshDatabase;

    private function motor(): string
    {
        return (string) file_get_contents(resource_path('js/site/salta.js'));
    }

    /**
     * El cuerpo del componente `saltaJuego`, **acotado**.
     *
     * ⚠️ `app.js` monta una docena de componentes y varios usan `IntersectionObserver`: aseverar
     * contra el fichero entero daría verde con el observador de OTRO. Es la lección de §12.4 del
     * tema —`assertSee('cta-prime')` casaba con `cta-prime__ico`—: *acota al elemento antes de
     * creerte un test verde.*
     */
    private function componenteDelJuego(): string
    {
        $app = (string) file_get_contents(resource_path('js/app.js'));

        $ini = strpos($app, "Alpine.data('saltaJuego'");
        $this->assertNotFalse($ini, 'no se encuentra el componente `saltaJuego` en `app.js`');

        $fin = strpos($app, "Alpine.data('cierreChoreo'", $ini);
        $this->assertNotFalse($fin, 'no se encuentra dónde acaba `saltaJuego`: el corte sería a ciegas');

        return substr($app, $ini, $fin - $ini);
    }

    /** Un método del componente del juego, acotado por conteo de llaves. */
    private function metodoDelJuego(string $nombre): string
    {
        $cuerpo = $this->componenteDelJuego();

        // ⚠️ La DEFINICIÓN, no la llamada: `this._observa()` aparece antes en el fichero, y
        // cortar desde ahí devolvería el bloque equivocado sin que nada avisara.
        $this->assertSame(
            1,
            preg_match('/(?<![\w.$])(?:async\s+)?'.preg_quote($nombre, '/').'\s*\([^)]*\)\s*\{/', $cuerpo, $m, PREG_OFFSET_CAPTURE),
            "el componente del juego ya no define `{$nombre}()`",
        );

        $abre = $m[0][1] + strlen($m[0][0]) - 1;
        $n = 0;

        for ($i = $abre, $len = strlen($cuerpo); $i < $len; $i++) {
            if ($cuerpo[$i] === '{') {
                $n++;
            } elseif ($cuerpo[$i] === '}' && --$n === 0) {
                return substr($cuerpo, $abre, $i - $abre + 1);
            }
        }

        $this->fail("no se puede acotar `{$nombre}()`: las llaves no cierran");
    }

    /** El relleno de la tarjeta de cierre, tal y como está escrito. */
    private function declaracionDeRelleno(): string
    {
        $cuerpos = array_map(
            fn (array $r) => $r['body'],
            array_filter($this->siteRules(), fn (array $r) => $r['selector'] === '.reserve__box'),
        );

        $this->assertNotEmpty($cuerpos, 'no se encuentra la regla `.reserve__box`');

        $this->assertSame(
            1,
            preg_match('/(?<![-\w])padding\s*:([^;]+);/', implode(' ', $cuerpos), $m),
            'no se encuentra el relleno de la tarjeta de cierre, que es donde vive el hueco del juego',
        );

        return $m[1];
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

    /**
     * **EL TOQUE ALCANZA TODO EL HERO, Y UN ARRASTRE SIGUE SIN EMPEZAR PARTIDA** (`#352`).
     *
     * `[owner, 2026-09-02]`: *«darle tap a cualquier parte del hero empieza a jugar y puede saltar,
     * no solo en la parte inferior»*. El lienzo es una tira pegada al borde inferior de la tarjeta
     * —medido a 390×844 con el cierre abierto: la tarjeta va de 10 a 834 y el lienzo empieza en
     * **684**—, así que antes solo respondían los últimos 150 px de una pantalla entera.
     *
     * ⚠️⚠️ **Y la mitad de `#253` que sigue viva se comprueba AQUÍ, porque es la que se pierde sin
     * enterarse.** Aquella tanda decidió que tocar el lienzo NO empezara la partida, y el motivo
     * sigue siendo cierto: *«sin ella el primer arrastre para desplazar arranca un juego que nadie
     * pidió»*. Lo que cambia `#352` es **dónde** se puede tocar, no **qué cuenta como toque**: con el
     * dedo el arranque se decide al LEVANTAR, y solo si no hubo desplazamiento ni pulsación larga.
     * ▶ Verificado en navegador con CONTROL: un arrastre de 120 px deja la fase en `listo`; un toque
     * en el mismo punto la pone en `jugando`.
     */
    #[Test]
    public function el_toque_alcanza_todo_el_hero_y_un_arrastre_no_empieza_partida(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // 1 · El oyente vive en la TARJETA. Sin esto, el resto de este caso vigilaría un método que
        //     no llama nadie desde donde importa.
        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="reserve__box"[^>]*@pointerdown="toca\(\$event\)"/s',
            $html,
            "El hero del cierre ha dejado de escuchar el toque en la TARJETA.\n".
            '▶ `[owner]`: tocar cualquier parte del hero tiene que empezar y hacer saltar, no solo la tira de abajo.'
        );
        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="reserve__box"[^>]*@pointerup="sueltaTap\(\$event\)"/s',
            $html,
            'la tarjeta ya no escucha el `pointerup`: con el dedo, el arranque se decide al LEVANTAR.'
        );

        // 2 · Y NO se queda además en el lienzo: dos oyentes para el mismo gesto dan un salto doble.
        $this->assertSame(
            1, preg_match_all('/@pointerdown="toca\(/', $html),
            'hay más de un oyente del mismo toque: el de la tarjeta ya cubre el lienzo por burbujeo, '.
            'y con los dos un solo toque saltaría dos veces.'
        );

        // 3 · El toque con el dedo se distingue de un arrastre. Se aseveran los DOS umbrales: sin el
        //     de distancia, desplazar empieza partida; sin el de tiempo, una pulsación larga también.
        $suelta = $this->metodoDelJuego('sueltaTap');

        $this->assertStringContainsString(
            'Math.hypot', $suelta,
            "`sueltaTap` ya no mide cuánto se movió el dedo.\n".
            '▶ Es lo único que separa un TOQUE de un arrastre para desplazar (`#253`).'
        );
        $this->assertMatchesRegularExpression('/>\s*12\b/', $suelta, 'se ha perdido el umbral de distancia del toque');
        $this->assertMatchesRegularExpression('/>\s*700\b/', $suelta, 'se ha perdido el umbral de tiempo del toque');

        // 4 · Y la OTRA mitad de `#253`, que es la que de verdad encerraba al visitante: en táctil no
        //    se cancela el gesto del navegador.
        $toca = $this->metodoDelJuego('toca');

        // ⚠️ Se cuenta la GUARDA, no se busca el `preventDefault()` en una cadena multilínea: esa
        // forma ataba el caso a la indentación exacta del fichero y se ponía roja al reformatear.
        $this->assertSame(
            2, substr_count($toca, '! tactil'),
            "`toca` ha dejado de separar el puntero grueso del fino.\n".
            "▶ `preventDefault()` sobre un `pointerdown` táctil **cancela el gesto de desplazar**, y\n".
            '  sobre una tarjeta a pantalla completa eso deja al visitante encerrado (`#253`).'
        );

        // 5 · Un botón o un enlace se activa SOLO. Sin esto, tocar «Reservar» abriría el cajón Y
        //    empezaría una partida — el mismo defecto que el manejador de teclado ya evitaba.
        foreach (['toca', 'sueltaTap'] as $metodo) {
            $this->assertStringContainsString(
                "closest('button, a')", $this->metodoDelJuego($metodo),
                "`{$metodo}` ya no deja que los botones y enlaces del hero se activen solos: un toque ".
                'sobre «Reservar» haría las dos cosas a la vez.'
            );
        }
    }

    /**
     * **LA DEMO ARRANCA CUANDO EL LIENZO SE VE, NO CUANDO LA TARJETA LLENA LA PANTALLA** (`#256`).
     *
     * `[DECIDIDO owner]`: «que la animación del juego esté activada en su punto estático». Es lo
     * que hace el mockup —su bucle corre en modo demo siempre que el lienzo está a la vista, y
     * `q > 0,985` allí solo decide si se puede JUGAR— y lo que aquí no se hacía: el motor no se
     * descargaba hasta ese umbral, así que en reposo la tira estaba **en blanco**.
     *
     * ⚠️⚠️ **Y el ANCLAJE no vale como señal, aunque sea el nombre del punto estático.** Medido en
     * navegador: en ese punto la tarjeta está anclada a 1366, 1280 y 390 px, pero **no** a 1440 ni
     * a 1920 —ahí la composición cabe con la sección todavía 20 px por debajo del tope—. Una guarda
     * escrita sobre `cierre--anclado` habría pasado en verde con el juego en blanco justo en las
     * pantallas grandes.
     */
    #[Test]
    public function la_demo_arranca_al_ver_el_lienzo_y_no_al_llenar_la_pantalla(): void
    {
        // ⚠️ **Primero, que alguien lo LLAME.** Un método perfecto que no invoca nadie deja la
        // tira en blanco y pasa cualquier guarda escrita solo sobre su cuerpo.
        $this->assertStringContainsString(
            '_observa()', $this->metodoDelJuego('init'),
            'el componente ya no registra el observador al montarse: el método puede seguir '.
            'entero y la demo no arrancar nunca.',
        );

        $observa = $this->metodoDelJuego('_observa');

        $this->assertStringContainsString(
            'new IntersectionObserver(', $observa,
            "El minijuego ha dejado de mirar cuándo asoma su lienzo.\n".
            "▶ Sin eso el motor solo llega por `cierre:abierto` (`q > 0,985`), que es cuando la\n".
            '  tarjeta YA llena la pantalla: en el punto estático la tira se queda en blanco.',
        );

        $this->assertStringContainsString(
            '$refs.lienzo', $observa,
            'lo que hay que observar es el LIENZO, no la tarjeta: la tarjeta entra en la ventana '.
            'mucho antes que la tira, y traerse 12 kB entonces es cobrárselos de más.',
        );

        $this->assertStringContainsString(
            'this.carga()', $observa,
            'observar el lienzo sin traer el motor no enciende nada: la demo la pinta el motor.',
        );

        // ⚠️ **Y la fase NO puede cambiar aquí.** Poner `listo` en el punto estático enseñaría la
        // invitación en una tarjeta que todavía no es el juego y, peor, el ESPACIO dejaría de
        // desplazar la página para arrancar una partida que nadie ha pedido: `_onTecla` solo se
        // aparta con `fase === 'off'`. Verificado en navegador (el espacio siguió desplazando).
        $this->assertStringNotContainsString(
            'this.fase', $observa,
            'la vista del lienzo enciende la ANIMACIÓN, no el juego: si aquí se toca la fase, en el '.
            'punto estático el espacio deja de desplazar la página y empieza una partida.',
        );
    }

    /**
     * **LA TIRA DEL JUEGO TIENE SU SITIO TAMBIÉN EN REPOSO** (`#256`), que es la otra mitad.
     *
     * `#253` hizo que el `padding-bottom` de la tarjeta interpolara con `--cierre-p` —24 px en
     * reposo, la tira entera al abrirse— y era lo correcto **entonces**: el juego no existía en
     * reposo, así que apartarle 156 px era aire muerto que además dejaba al pie sin caber debajo.
     * Con la demo corriendo desde el punto estático la premisa se invierte, y el mockup lo escribe
     * sin interpolar (`padding: … clamp(122px,14vw,156px)`).
     *
     * ⚠️ El modo de fallo que esto caza es MUDO: con la reserva interpolada la demo corre igual,
     * pero **por detrás del párrafo y de los dos CTA**. No falla nada y no lo enseña una captura
     * del estado abierto, que es el que se suele mirar.
     */
    #[Test]
    public function la_tira_del_juego_tiene_su_sitio_tambien_en_reposo(): void
    {
        $padding = $this->declaracionDeRelleno();

        $this->assertStringNotContainsString(
            'var(--cierre-p)', $padding,
            "El hueco del minijuego ha vuelto a interpolar con el progreso del cierre.\n".
            "▶ Desde `#256` la demo corre YA en el punto estático, así que en reposo la tira\n".
            "  existe: con la reserva a 24 px el muñeco corre por detrás del texto y de los CTA.\n".
            '▶ El mockup lo escribe constante.',
        );

        $this->assertStringContainsString(
            'var(--salta-hueco)', $padding,
            'el hueco del minijuego tiene que salir de su token: es lo que permite que una '.
            'instalación lo mueva, y lo que el bloque de `prefers-reduced-motion` anula cuando no '.
            'hay tira que meter dentro.',
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

        // ⚠️⚠️ **Y desde `#256` hay una SEGUNDA puerta de descarga.** La primera guarda seguiría
        // verde con la preferencia rota, porque mira la puerta vieja: *lo que un gate declara que
        // no mira es un hueco con nombre* (`TESTING.md` §2.quater).
        $this->assertStringContainsString(
            'if (this.reduce ||', $this->metodoDelJuego('_observa'),
            'la puerta nueva —traer el motor al ver el lienzo— no respeta `prefers-reduced-motion`: '.
            'son los mismos 12 kB por la otra puerta.',
        );

        // Y sin tira que enseñar, tampoco se le aparta sitio: serían 122–156 px de vacío
        // permanente al pie de la tarjeta, en la única configuración donde nadie los va a llenar.
        $this->assertStringContainsString(
            '--salta-hueco: 24px',
            implode("\n", $this->siteSheets()),
            'con movimiento reducido el motor no se carga, así que la tarjeta reservaría 122–156 px '.
            'para una tira que nunca aparece.',
        );

        // Y si llega a cargarse —porque alguien pulsa «Jugar»—, el fondo no corre: un fotograma.
        $this->assertMatchesRegularExpression(
            '/if \(O\.reduce\)[^}]*quieto/s', $motor,
            'con movimiento reducido el motor sigue animando el fondo. Debe pintar UN fotograma y '.
            'parar el bucle: el juego es una acción del usuario, la demo no.',
        );
    }
}
