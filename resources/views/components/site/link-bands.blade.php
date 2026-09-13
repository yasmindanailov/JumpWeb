{{-- ══ LAS BANDAS DE ENLACE · lo que la página ofrece cuando se acaba lo que venías a leer ═══════
     Carril de diseño Fase 3 · artboard `Bandas PJP` turno 1 (`1c` móvil, `1d` finas, `1f` a 1120)
     y `doc/bandas.md`. El reparto y su filtrado viven en `Content\Services\LinkBands`.

     Son DOS piezas y se emiten juntas porque su ORDEN es la regla, no una elección por página: la
     gorda primero, las finas después. Una página añade una línea y no decide nada.

     ❗❗❗ **LA GORDA VA AQUÍ, AL FINAL, Y POR ENCIMA DE LAS FINAS — no a media página.**
     El artboard escribe «a media página, no al final» y da su motivo: *«el cierre de las interiores
     ya es una tarjeta de tinta, y dos bloques negros separados solo por el aire de sección se leen
     como uno mal cortado»*. Ese motivo **no caducó con `#527`: cambió de sujeto**, y está medido:

         las seis interiores acaban en un bloque de PAPEL
         · 144 px de aire de sección (`--sec-air`) ·
         `<x-site.footer />` sin `surface`, o sea `data-surface="ink"` — TINTA (L=0,007)

     Así que una gorda de tinta al final quedaría a 144 px de otro bloque de tinta: **la
     configuración exacta que la regla nombra**. Y no tiene precedente — la única pareja de bloques
     oscuros del producto está en la portada, **pegada (0 px medidos) y contra un pie de PAPEL**
     (`#523`).
     ▶ **Lo que lo resuelve es el propio orden del artboard**: con la gorda por encima de las finas,
     las dos tarjetas blancas quedan entre las dos tintas. Se cumple «la gorda antes que las finas»
     y no hacen falta costuras a media página — que **`/atracciones` y `/bar` no tienen**: medido,
     su contenido es UN bloque de 1.510 y 997 px, y la costura más cercana a la mitad cae a 771 y
     487 px de ella. Colocarla ahí sería partir un componente por dentro.

     ⚠️ **No se pinta nada si no hay nada que ofrecer.** El canvas: *«las tres son opcionales por
     página: si una página no deja ninguna pregunta abierta, no lleva gorda — no se rellena el
     hueco»*. --}}
@props(['route' => null])
@php
    $route ??= request()->route()?->getName();

    $gorda = \App\Domain\Content\Services\LinkBands::wide((string) $route);
    $finas = \App\Domain\Content\Services\LinkBands::thin((string) $route);
@endphp
@if ($gorda || $finas)
    <div {{ $attributes->class('bands') }}>
        @if ($gorda)
            {{-- ⚠️ `data-surface="ink"` va en la TARJETA y nunca en su contenedor (`#484`): además de
                 re-escopar los tokens PINTA el fondo, así que en un contenedor sin radio dejaría un
                 rectángulo detrás. Y con él `--interactive` vale Cian y `--secondary` lo sigue, que
                 es el «cian relleno con texto tinta» que pide el artboard, sin un literal.
                 ⚠️ Sin sombra y sin keyline a propósito: *«no es una pegatina»*. --}}
            <div class="band-wide" data-surface="ink">
                <div class="band-wide__say">
                    <p class="band-wide__q">{{ $gorda['q'] }}</p>
                    <p class="band-wide__body">{{ $gorda['body'] }}</p>
                </div>
                {{-- El secundario del sistema. ⚠️ **No es el de acción** aunque hoy compartan color:
                     desde `#541` `theme.action` es el mismo cian, así que el argumento del artboard
                     —«el naranja no baja aquí, porque esto no es comprar»— se quedó sin sujeto. Lo
                     que sostiene la elección hoy es la otra regla de `#541`: *los rellenos son los
                     que hacen AVANZAR*, y esto hace avanzar. --}}
                <a class="btn btn--ink btn--lg band-wide__cta" href="{{ $gorda['url'] }}">
                    <span>{{ $gorda['cta'] }}</span>
                    {{-- ⚠️ Sin `aria-hidden`: el componente del icono ya lo declara fuera de su
                         `merge`, así que pasarlo escribe el atributo DOS veces en el `<svg>`. --}}
                    <x-icons.arrow-right class="band-wide__arrow" :width="18" :height="18" />
                </a>
            </div>
        @endif

        @if ($finas)
            {{-- La tarjeta-enlace del sistema: blanco, borde de Línea y radio 16 ya significan «esto
                 es una puerta», así que no lleva botón dentro, ni sombra, ni color de fondo. --}}
            <nav class="bands-thin" aria-labelledby="bands-thin-lead">
                <p class="bands-thin__lead" id="bands-thin-lead">{{ __('site.bands.lead') }}</p>
                <ul class="bands-thin__list">
                    @foreach ($finas as $fina)
                        <li>
                            <a class="band-thin" href="{{ $fina['url'] }}">
                                <span class="band-thin__say">
                                    <span class="band-thin__what">{{ $fina['t'] }}</span>
                                </span>
                                <x-icons.arrow-right class="band-thin__arrow" :width="20" :height="20" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
@endif
