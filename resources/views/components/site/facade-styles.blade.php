{{-- ══ FACHADA · el andamio de las variantes ══════════════════════════════════════════════════
     Se emite UNA vez y solo con variante activa (`?fachada=1|2` en `local`). Sin ella, la portada
     no lleva ni este `<style>` ni una sola regla de más — que es la condición para que un prototipo
     pueda vivir dentro de la vista de producción sin ensuciarla.

     ⚠️⚠️ **`isolation` es lo que hace que la pieza se vea.** `.section` es `position: relative` sin
     `z-index`, así que NO crea contexto de apilamiento: una pieza a `z-index: -1` dentro se hunde
     por detrás del fondo de la página y desaparece —sin fallar, que es como este defecto se cuela—.
     La regla va aquí y no en `landing.css` porque afecta a una declaración que comparten las doce
     vistas, y crear un contexto de apilamiento puede mover cualquier `z-index` de dentro.

     ⚠️ El recorte es de la SECCIÓN (`inset: 0` + `overflow: hidden`): una figura más alta que su
     caja se corta por el borde en vez de empujar el alto de la página. Las piezas marcadas como
     `escapa` lo apagan en su propio `style`. --}}
@php($v = app()->environment('local') ? (int) request()->query('fachada', 0) : 0)

@if ($v === 1 || $v === 2)
    <style>
        /* Solo lo que recibe pieza: no se toca el apilamiento de nada más.
           ⚠️ En el cierre el contexto lo crea la TARJETA y no la sección, porque la pieza vive
           dentro de ella — y `.reserve__box` pinta su propio fondo de tinta, así que sin esto la
           figura se hunde por detrás de ese fondo y desaparece. */
        #zones, #events, #before, #info { isolation: isolate; }
        #reserve .reserve__box { isolation: isolate; }

        .fac-slot { position: absolute; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .fac-p { position: absolute; height: auto; }

        /* La capa de ENCIMA: sobre el contenido, no detrás. La necesita la figura que se apoya en
           una tarjeta, porque la tarjeta es opaca y en la capa de fondo la entierra.
           ⚠️ `pointer-events: none` lo hereda de `.fac-slot`: una figura decorativa por encima de
           una tarjeta que es un ENLACE no puede robarle el clic. */
        .fac-slot--encima { z-index: 2; }

        /* ── EL ARCO DE REBOTE (`F3`) ──────────────────────────────────────────────────────
           ⚠️ Va sobre la banda que queda entre las pestañas y las tarjetas, no dentro de ellas.
           ⚠️ La curva se estira al ancho disponible (`preserveAspectRatio="none"`) y las figuras
           se alinean por ABAJO para que los pies caigan sobre ella. */
        .arc { position: relative; height: 132px; margin: 0 auto var(--sp-20); max-width: 560px; }
        .arc__curve { position: absolute; inset: 0; width: 100%; height: 100%; }
        .arc__curve path { stroke: var(--line-strong); }
        .arc__row {
            position: absolute; inset: 0; display: flex; align-items: flex-end;
            justify-content: space-between; padding: 0 6px;
        }
        .arc__fig { display: block; flex: 0 0 auto; color: var(--strip-1); }
        .arc__fig .ilu { --ilu-fg: var(--strip-1); }
        /*
         * ⚠️⚠️ **Los pies van sobre la curva, y las cuatro alturas están CALCULADAS, no puestas a
         * ojo.** La primera versión lo estaba (0 · 42 · 62 · 16) y se veía: la figura de la
         * izquierda flotaba por debajo del trazo y la de la derecha por encima.
         * ▶ Se evalúa la propia parábola —`M6 134 Q170 -16 334 122`, una Bézier cuadrática— en las
         * cuatro fracciones donde `space-between` coloca a las figuras (0 · ⅓ · ⅔ · 1):
         *
         *     f      y de la curva      margen = (150 − y) / 150 × 132
         *     0      134                14
         *     ⅓       66,4              74
         *     ⅔       61,4              78
         *     1      122                25
         *
         * ⚠️ `x(t)` es casi lineal aquí porque el punto de control (170) cae en el medio del tramo
         * (6→334): por eso la fracción de ancho vale como `t` sin corregir. Si alguien mueve ese
         * control, esta equivalencia deja de valer y hay que volver a despejar.
         */
        .arc__fig:nth-child(1) { margin-bottom: 14px; }
        .arc__fig:nth-child(2) { margin-bottom: 74px; }
        .arc__fig:nth-child(3) { margin-bottom: 78px; }
        .arc__fig:nth-child(4) { margin-bottom: 25px; }

        /* Las tarjetas de tarifa, CENTRADAS (`[DECIDIDO owner]`): con dos productos el carril las
           dejaba pegadas a la izquierda y 395 px muertos a la derecha. */
        .rates__rail { justify-content: center; }

        /* ── EL TRÍO DE NIÑOS (`G4`), al lado del titular de Cumpleaños ────────────────────
           ⚠️ Su cabecera deja **512 px libres** a la derecha del titular (medido): el trío cabe
           ahí sin empujar nada. Va en la capa de fondo —no tapa texto— y los pies en la misma
           línea, que es la regla de la pieza. */
        .trio { display: flex; align-items: flex-end; }
        .trio__n { display: block; flex: 0 0 auto; }
        .trio--events {
            position: absolute; z-index: -1; pointer-events: none;
            right: 8px; top: 30px; opacity: .9;
        }
        /*
         * ⚠️⚠️ **EN MÓVIL EL TRÍO SE MUDA A LA TARJETA DEL RELOJ** (`[DECIDIDO owner]`: «ponemos las
         * siluetas de los niños encima de la card 2h»). Y no es el mismo sitio más pequeño: en
         * estrecho el titular ocupa el ancho entero y **no hay lateral donde vivir**, así que el
         * trío estaba al fondo de la sección, a 1.498 px del titular y sin nada que acompañar.
         * ▶ Se APOYA en el canto superior de esa tarjeta, por la derecha: los pies caen dentro y el
         * cuerpo queda en el aire de encima. Y **por ENCIMA** (`z-index`), porque la tarjeta es opaca
         * y en la capa de fondo quedaría enterrada — la lección que ya pagó la figura de esta misma
         * sección en escritorio.
         *
         * ⚠️⚠️ **El pie de la tarjeta NO está libre, aunque el número lo pareciera.** Medí «contenido
         * hasta 432, tarjeta hasta 520 → 88 px de aire» y ahí viven los tres chips (Saltos ·
         * Merienda · Tarta): el trío les caía encima. *Segunda vez en esta tanda que un hueco
         * calculado entre dos cotas resulta estar ocupado.*
         * ⚠️⚠️ **Y `transform-origin: right bottom` lo bajaba 120 px sin que nada fallara**: `scale`
         * encoge el DIBUJO pero no la caja, así que con el origen abajo el visual se queda pegado al
         * borde inferior de una caja que sigue midiendo lo de antes. Con `right top` el dibujo se
         * queda donde dice `top`, que es lo que uno supone al escribirlo.
         */
        @media (max-width: 900px) {
            .trio--events {
                z-index: 2; right: 6px; top: 196px; bottom: auto;
                scale: .34; transform-origin: right top; opacity: 1;
            }
        }

        /* ⚠️ La mancha de Reseñas NO se declara aquí, y no es un olvido: esa pieza se pinta SIEMPRE,
           no solo con variante, así que su CSS vive en `landing.css` con el resto de la producción.
           Declararla aquí la dejaría sin estilo —estática y en el flujo— en la portada normal. */

        @media (max-width: 700px) {
            .arc { height: 104px; max-width: 340px; }
        }

        /* En teléfono las figuras se encogen y se apartan: a 390 px una silueta de 560 tapa media
           sección. No es una variante distinta, es la misma con el tamaño que cabe. */
        @media (max-width: 700px) {
            .fac-p { max-height: 46vh; max-width: 62vw; }

            /* ⚠️ **«Antes de venir»: la mancha se muda DETRÁS DE LA TARJETA** (`[DECIDIDO owner]`).
               En escritorio vive al lado del titular; en móvil no hay lado, así que se coloca contra
               la tarjeta del QR —medido: `y 268..550`— y asoma por su canto inferior izquierdo. Va
               en la capa de fondo, así que la tarjeta la tapa salvo por donde sobresale.

               ⚠️⚠️ **`!important` porque la posición de base viene en `style` INLINE**, que es como el
               mapa declara cada pieza — y un atributo `style` gana a cualquier regla de hoja. Sin
               esto la mancha se quedaba arriba, **encima del titular y de la entradilla**, con la
               media query aplicándose y sin que nada fallara. ▶ Se va el día que esta colocación se
               confirme y los estilos bajen a `landing.css`, donde ya no habrá inline que vencer. */
            #before .fac-p {
                left: -66px !important; right: auto !important; top: 330px !important;
                width: 240px !important; max-width: none; max-height: none;
            }
        }

        /* El rótulo de la variante, para no confundir una captura con la portada de verdad. */
        .fac-flag {
            position: fixed; z-index: 2147483000; left: 12px; bottom: 12px;
            font-family: var(--font-mono); font-size: 11px; letter-spacing: .12em; text-transform: uppercase;
            background: var(--fg); color: var(--bg); padding: 7px 11px; border-radius: 999px;
            text-decoration: none; display: inline-flex; gap: 10px; align-items: center;
        }
        .fac-flag a { color: var(--bg); text-decoration: underline; text-underline-offset: 3px; }
    </style>

    <p class="fac-flag">
        Fachada · variante {{ $v }} «{{ $v === 1 ? 'dentro' : 'mural' }}»
        <a href="{{ url('/?fachada='.($v === 1 ? 2 : 1)) }}">ver la {{ $v === 1 ? 2 : 1 }}</a>
        <a href="{{ url('/') }}">sin fachada</a>
    </p>
@endif
