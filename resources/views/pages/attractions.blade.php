@php
    // Meta description (SEO, INVISIBLE en la página): las primeras atracciones reales, no el
    // titular repetido. Mismo criterio que `/normas`.
    $ridesMeta = $zones->isNotEmpty()
        ? \Illuminate\Support\Str::limit($zones->flatMap->attractions->take(8)->map(fn ($a) => $a->tr('name'))->implode(' · '), 155)
        : __('landing.attractions.title');
@endphp
<x-layout :title="__('landing.attractions.title')" :description="$ridesMeta">
<div x-data="landing">
    <x-site.nav />

    {{-- ══ /atracciones · las 23, con su zona en la pestaña ══════════════════════════════════════
         Carril de diseño, T2d. Artboard `Atracciones PJP` **1a** (móvil) + **1c** (escritorio),
         cerrado en el acta de páginas del canvas.

         ⚠️⚠️ **ESTA PÁGINA EXISTE PORQUE LA SECCIÓN 03 LA NECESITA.** Su portada enseña CINCO
         atracciones y delega las otras dieciocho aquí; sin destino, esa puerta sería el ancla
         muerta que la regla del canvas prohíbe («si dos secciones cerradas apuntan al mismo destino
         inexistente, ese destino existe: hay que escribirlo»).

         ⚠️ **LA EDAD SÍ SE PINTA AQUÍ, y no contradice a `#302`.** Aquella decisión dejó la tarjeta
         de la PORTADA en «foto + título + tag» y el owner rechazó la edad *ahí*: en un carril de 23
         párrafos competía con 23 fotos. Esto es **capa 3** —«quien llega aquí ha querido llegar»—,
         y el propio canvas escribe la ficha con su chip de edad. La guarda de la portada
         (`ZonesSectionTest`) sigue vigilando lo suyo y no alcanza a esta vista.
         ▶ Y el dato de esta instalación es más rico que el del mockup: allí el chip repetía la edad
         de la ZONA 23 veces (y su propia nota lo señalaba como saturación); aquí cada atracción
         declara la suya —23 de 23, medido—, así que el chip dice algo distinto en cada ficha. --}}
    <main id="main" class="page page--rides wrap">

        {{-- LA CABECERA DE PÁGINA del canvas: rótulo con la RUTA, titular en Display L y entradilla.
             La estrenó esta página (`#481`) y desde la T3a·3 es el componente del armazón (`#525`).
             ⚠️ El rótulo ya NO es una clave de idioma con la ruta escrita a mano: la deriva el
             componente de la URL real, con la misma función que el menú. --}}
        <x-site.page-head :title="__('landing.attractions.title')" :lede="__('landing.attractions.intro', ['count' => $total])" />

        @if ($zones->isNotEmpty())
            {{-- ⚠️ El estado arranca en la zona que la URL pide (`?zona=`), ya resuelta y SANEADA por
                 el controlador: aquí no se vuelve a decidir. Un segundo sitio que eligiera zona es
                 como nacen las divergencias que este carril lleva tres tandas cerrando. --}}
            <div class="rides-page" x-data="{ zone: @js($active) }">

                {{-- LA PESTAÑA DEL SISTEMA (`.tabset`), la que estrenó `#479` en Tarifas. No se
                     inventa otra: la del canvas es pista en Nube con radio 16 y la activa levantada
                     a blanco con radio 10, y ya vive en la hoja.
                     ⚠️ Con una sola zona no se pinta: un control de una opción no elige nada.
                     ⚠️ El ORDEN lo manda `zones.position`, o sea el panel — el mismo dato y el mismo
                     paso de despliegue que la sección de tarifas. --}}
                {{-- ⚠️⚠️ **LA CIFRA ES HERMANA DE LAS PESTAÑAS, NO HIJA DEL PANEL**, y eso lo manda
                     el escritorio: allí el control y su consecuencia van en la MISMA fila —352 el
                     carril y la cifra a su lado—, y dos elementos que comparten fila tienen que
                     compartir padre. En móvil el orden de bloque es el mismo del artboard: pestañas,
                     cifra, rejilla. --}}
                <div class="rides-page__head">
                    @if ($zones->count() > 1)
                        <div class="tabset rides-page__tabs" role="tablist" aria-label="{{ __('landing.attractions.zone_tablist') }}">
                            @foreach ($zones as $zone)
                                <button type="button" class="tabset__tab" role="tab"
                                        id="rides-tab-{{ $zone->slug }}"
                                        aria-controls="atracciones-{{ $zone->slug }}"
                                        :class="zone === @js($zone->slug) && 'is-active'"
                                        :aria-selected="zone === @js($zone->slug) ? 'true' : 'false'"
                                        @click="zone = @js($zone->slug)">{{ $zone->tr('name') }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="rides-page__counts">
                        @foreach ($zones as $zone)
                            {{-- LA CIFRA y su frase. La cifra es DATO del panel, no un número
                                 escrito, y su color sale de la paleta compuesta de la zona.
                                 ⚠️ Sin tramo de edad la frase cae a la variante que nombra la zona:
                                 una frase con el hueco vacío diría «atracciones para », que es peor
                                 que no decir la edad. --}}
                            <p class="rides-zone__count"
                               style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
                               x-show="zone === @js($zone->slug)" @if ($zone->slug !== $active) x-cloak @endif>
                                <span class="rides-zone__n">{{ $zone->attractions->count() }}</span>
                                <span class="rides-zone__phrase">{{ $zone->tr('age_range')
                                    ? __('landing.attractions.count_phrase', ['age' => $zone->tr('age_range')])
                                    : __('landing.attractions.count_phrase_plain', ['zone' => $zone->tr('name')]) }}</span>
                            </p>
                        @endforeach
                    </div>
                </div>

                @foreach ($zones as $zone)
                    {{-- ⚠️⚠️ **LA PALETA VIAJA COMPUESTA** (`#138`/`#139`): `zoneStyle()` emite
                         `--zone-1`, `--zone-2` y `--on-brand` de una vez, y de ahí cuelgan la cifra
                         y el chip. Componerla en la vista sería la segunda definición de una regla
                         que ya tiene la suya.
                         ⚠️ `x-cloak` va en las que NO llegan activas, no en «todas menos la
                         primera»: con `?zona=kids` la primera es Jump, y ocultar por posición
                         pintaría Jump un instante antes de cambiar. --}}
                    <section class="rides-zone" role="tabpanel"
                             id="atracciones-{{ $zone->slug }}"
                             @if ($zones->count() > 1) aria-labelledby="rides-tab-{{ $zone->slug }}" @endif
                             style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
                             x-show="zone === @js($zone->slug)" @if ($zone->slug !== $active) x-cloak @endif>

                        {{-- ⚠️ `<ul>` y no `<div>`: son las atracciones de esa zona, o sea una lista,
                             y el lector de pantalla anuncia cuántas hay antes de recorrerlas. Es el
                             mismo criterio que el carril de tarifas. --}}
                        <ul class="rides-grid" role="list">
                            @foreach ($zone->attractions as $ride)
                                <li class="ride-tile">
                                    {{-- ⚠️ **Sin foto NO se reserva hueco**: un cuadrado gris
                                         esperando es peor que una ficha de texto, que es la regla
                                         que el canvas escribió para la tarjeta del bar. --}}
                                    @if ($ride->image)
                                        <img class="ride-tile__img" src="{{ asset($ride->image) }}"
                                             alt="{{ $ride->tr('name') }}" loading="lazy" decoding="async">
                                    @endif
                                    <p class="ride-tile__name">{{ $ride->tr('name') }}</p>
                                    {{-- El chip de edad es `chipDato` entero: cápsula, mono 11 y el
                                         color de la zona AL 14 %, nunca pleno. Un chip que informa
                                         no va a color macizo, y su texto es tinta, así que se lee
                                         sea cual sea el color que el panel le ponga a la zona. --}}
                                    @if ($ride->tr('age') || $ride->tr('badge'))
                                        <p class="ride-tile__chips">
                                            @if ($ride->tr('age'))
                                                <span class="ride-tile__age">{{ $ride->tr('age') }}</span>
                                            @endif
                                            {{-- ⚠️⚠️ **EL DISTINTIVO OCUPA EL SEGUNDO CHIP QUE EL ARTBOARD DEJÓ
                                                 VACÍO.** Su ficha declara dos —`edad` y un segundo en Nube— y el
                                                 canvas retiró el suyo (la altura por atracción) porque **no hay
                                                 dato que lo llene**: su propia nota dice que el 1,40 que había
                                                 dibujado era invento. Aquí sí lo hay (`attractions.badge`, que el
                                                 panel escribe), y sin este hueco ese campo se habría quedado **sin
                                                 una sola pantalla que lo pinte** al retirarse el carrusel de la
                                                 portada — como le pasó a `zones.image` en `#302`.
                                                 ⚠️ Va en Nube y no en color de zona: el de zona ya lo lleva la
                                                 edad, y dos chips teñidos igual dejan de distinguirse. --}}
                                            @if ($ride->tr('badge'))
                                                <span class="ride-tile__badge">{{ $ride->tr('badge') }}</span>
                                            @endif
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif

        {{-- EL PIE de la página: la regla del parque y la vuelta a las zonas.
             ⚠️⚠️ **La regla es la MISMA cadena que la sección 01** (`landing.zones.rule`), no una
             copia: es la regla del parque y se dice igual en todas las superficies. Escribirla otra
             vez aquí es cómo dos sitios acaban publicando la misma norma con dos redacciones.
             ⚠️ Divergencia declarada con el artboard 1a, que aquí escribe una nota cruzada («los de
             7 en adelante saltan en Jump, que tiene 15»): esa frase supone EXACTAMENTE dos zonas y
             nombra la otra, y las zonas las pone el panel. Se toma el pie de su opción 1b, que es
             esta misma regla. --}}
        <div class="page__foot">
            <p class="page__foot-rule">{{ __('landing.zones.rule') }}</p>
            {{-- ⚠️ Sin `data-tap`: el enlace declara `min-height` de objetivo táctil por su cuenta,
                 así que el pseudo centrado del mecanismo no tendría nada que ampliar. --}}
            <a class="page__foot-link" href="{{ url('/#zones') }}">
                {{ __('landing.attractions.see_zones') }}
                <x-icons.arrow-right class="arrow" :width="16" :height="16" />
            </a>
        </div>
    </main>

    <x-site.footer :closing="true" />
</div>
</x-layout>
