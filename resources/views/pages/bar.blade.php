<x-layout :title="$barName" :description="$barLede ?? __('site.bar_counter_text')">
<div x-data="landing">
    <x-site.nav />

    {{-- ══ /bar · LA CARTA CORTA, Y NADA MÁS ═════════════════════════════════════════════════════
         Carril de diseño Fase 3 · T3b (`DECISIONES #536`). Artboard `Bar PJP` **1a** (móvil) +
         **1b** (escritorio).

         ▶ **Esta página existe por un contrato ya escrito**: la tarjeta del bar de la sección 03
         promete «mesas con el parque a la vista y carta corta», y la carta es lo único de esa
         promesa que no cabe en una tarjeta.

         ❗❗❗ **LA CARTA SE PUBLICA COMO IMAGEN** (`[DECIDIDO owner, 2026-09-12]`), no tecleando los
         platos como dibuja el artboard. El parque sube la foto de la carta que ya tiene impresa, que
         es lo único que va a mantener de verdad.
         ⚠️⚠️ **Lo que eso cuesta, y por eso la página hace tres cosas que el artboard no pide**: el
         texto dentro de una imagen no lo lee un lector de pantalla, no se traduce y no se indexa.
         ▶ (1) cada imagen lleva su `alt`, OBLIGATORIO en el panel; (2) se pueden subir VARIAS, una
         por cara, sin montajes a mano; y (3) **cada una es un enlace a su fichero**, que es la forma
         de ampliarla en un teléfono sin una sola línea de JavaScript ni un visor que mantener.

         ⚠️ **Cero naranja**: en el bar no se compra por la web, así que el único relleno de acción
         de esta pantalla lo trae la barra del armazón.

         ⚠️ **Sin carta subida, la sección de la carta NO se pinta** — y no se pone un «próximamente»
         en su lugar: una disculpa en una página pública es peor que un hueco. Lo que falta lo dice
         el panel, no la web. --}}
    <main id="main" class="page page--bar wrap">
        <x-site.page-head :title="$barName" :lede="$barLede" />

    {{-- ⚠️⚠️ **EL DOM VA EN EL ORDEN DE MÓVIL** —foto · carta · chapa— y en escritorio lo recoloca la
         rejilla por ÁREAS, que es lo que el artboard pide: *«la chapa sube debajo de la foto en vez
         de ir al final: en móvil cierra la página porque no hay sitio, y aquí acompaña a la imagen,
         que es lo que describe»*. ▶ Reordenar con `grid-area` **no mueve el foco de teclado**, y aquí
         eso no crea ningún salto porque **la chapa no tiene ni un control**: es texto. --}}
        <div class="bar-layout">
            @if ($barPhoto)
                {{-- La foto es 16:9 y única, por la regla que `/cumpleanos` ya fijó: para unas mesas
                     hace falta anchura, o no hay foto. --}}
                <figure class="bar-photo">
                    <img src="{{ $barPhoto->imageUrl() }}" alt="{{ $barPhoto->tr('alt') }}"
                         @if ($barPhoto->width && $barPhoto->height) width="{{ $barPhoto->width }}" height="{{ $barPhoto->height }}" @endif
                         loading="lazy" decoding="async">
                    @if (filled($barPhotoCaption))
                        <figcaption>{{ $barPhotoCaption }}</figcaption>
                    @endif
                </figure>
            @endif

            {{-- ── La carta ──────────────────────────────────────────────────────────────────── --}}
            @if ($barMenu->isNotEmpty())
                <section class="bar-menu" aria-labelledby="bar-menu-title">
                    <h2 class="bar-menu__title" id="bar-menu-title">{{ __('site.bar_menu_title') }}</h2>
                    <p class="bar-menu__hint">{{ __('site.bar_menu_hint') }}</p>

                    <ul class="bar-menu__list" role="list">
                        @foreach ($barMenu as $sheet)
                            <li>
                                {{-- ⚠️ El enlace abre el fichero en otra pestaña: ahí el navegador da
                                     zoom nativo, que es lo que hace legible una carta en un móvil.
                                     ⚠️ El rótulo va DENTRO del enlace, así que su nombre accesible es
                                     «{alt} · Ver a tamaño completo» — se sabe adónde lleva y que se
                                     abre aparte. --}}
                                <a class="bar-sheet" href="{{ $sheet->imageUrl() }}" target="_blank" rel="noopener">
                                    <img src="{{ $sheet->imageUrl() }}" alt="{{ $sheet->tr('alt') }}"
                                         @if ($sheet->width && $sheet->height) width="{{ $sheet->width }}" height="{{ $sheet->height }}" @endif
                                         loading="lazy" decoding="async">
                                    <span class="bar-sheet__zoom">
                                        <span>{{ __('site.bar_menu_zoom') }}</span>
                                        <span aria-hidden="true">&rarr;</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    {{-- ❗ Obligación legal (Reglamento UE 1169/2011): con comida hay que informar de
                         los alérgenos, y la norma admite hacerlo de viva voz SIEMPRE que se diga de
                         forma visible dónde preguntarlo. `[DECIDIDO owner]`: esta línea es esa
                         indicación, y por eso va PEGADA a la carta y no perdida en el pie. --}}
                    <p class="bar-menu__allergens">{{ __('site.bar_allergens') }}</p>
                </section>
            @endif

            {{-- La única superficie de tinta de la página. `data-surface="ink"` va en la TARJETA y no
                 en su contenedor (`#484`): ahí además PINTA, y en un contenedor sin radio dejaría un
                 rectángulo detrás. --}}
            <aside class="bar-counter" data-surface="ink" aria-labelledby="bar-counter-title">
                <h2 class="bar-counter__title" id="bar-counter-title">{{ __('site.bar_counter_title') }}</h2>
                <p class="bar-counter__text">{{ __('site.bar_counter_text') }}</p>
                {{-- ⚠️ **Tres estados, no dos** (`#536`): sin decidir en el panel no se escribe nada.
                     La pregunta la hace quien vive al lado y no viene a saltar, y afirmarle que «hace
                     falta entrada» sin que nadie lo haya decidido sería peor que callar. --}}
                @if ($barFreeEntry)
                    <p class="bar-counter__entry">{{ __('site.bar_free_entry_'.$barFreeEntry) }}</p>
                @endif
            </aside>
        </div>

        {{-- ── El cierre: los que vienen de cumpleaños ───────────────────────────────────────
             ⚠️ **No repite los menús del pack**, que son de `/cumpleanos`: solo dice que las mesas
             son éstas. Y el enlace sale del INVENTARIO, así que si esa página está en mantenimiento
             la línea no se pinta — un destino que no se puede visitar no se anuncia (`#521`). --}}
        @if ($partyUrl)
            <div class="bar-party">
                <p class="bar-party__line">{{ __('site.bar_party_line') }}</p>
                <a class="bar-party__cta" href="{{ $partyUrl }}" data-tap>
                    <span>{{ __('site.bar_party_cta') }}</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        @endif
    </main>

    <x-site.footer />
</div>
</x-layout>
