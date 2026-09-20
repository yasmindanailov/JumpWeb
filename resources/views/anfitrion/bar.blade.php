{{-- ══ EL ANFITRIÓN MÍNIMO de /bar · lo que el producto sirve SIN paquete de instancia ══════════
     F5 · T2b (`specs/paquete-de-instancia.md` §4.4, `DECISIONES #655`). La página de PlayJump (artboard
     `Bar PJP`, `#536`) vive en su instancia; esto es lo que queda en el producto: el armazón, la foto del
     local, la carta como imágenes —cada cara con su `alt`, sus dimensiones y su enlace al fichero— con la
     línea de alérgenos, la chapa de la barra con su línea de acceso, y la nota de los cumpleaños. Sin
     arte: ni fachada, ni trama, ni superficies de tinta. Es marcado del PRODUCTO (`AnfitrionBarTest`).

     ⚠️ La carta se publica como IMAGEN (`[DECIDIDO owner, 2026-09-12]`), y eso obliga a tres cosas que
     este respaldo conserva porque son del producto y no del diseño: el `alt` (lo único que encuentra
     quien no la ve), las dimensiones (sin ellas la página salta al cargar) y el enlace al fichero (la
     única forma de leerla en un móvil sin un visor). Y la línea de alérgenos es obligación legal
     (Reglamento UE 1169/2011): se dice dónde preguntarlo, pegado a la carta. --}}
<x-layout :title="$barName" :description="$barLede ?? __('site.bar_counter_text')">
<div x-data="landing">
    <x-site.nav />
    <main id="main" class="page page--bar wrap">
        <x-site.page-head :title="$barName" :lede="$barLede" />

        <div class="bar-layout">
            @if ($barPhoto)
                <figure class="bar-photo">
                    <img src="{{ $barPhoto->imageUrl() }}" alt="{{ $barPhoto->tr('alt') }}"
                         @if ($barPhoto->width && $barPhoto->height) width="{{ $barPhoto->width }}" height="{{ $barPhoto->height }}" @endif
                         loading="lazy" decoding="async">
                    @if (filled($barPhotoCaption))
                        <figcaption>{{ $barPhotoCaption }}</figcaption>
                    @endif
                </figure>
            @endif

            {{-- Sin carta subida, la sección NO se pinta y no se pone nada en su lugar. --}}
            @if ($barMenu->isNotEmpty())
                <section class="bar-menu" aria-labelledby="bar-menu-title">
                    <h2 class="bar-menu__title" id="bar-menu-title">{{ __('site.bar_menu_title') }}</h2>
                    <p class="bar-menu__hint">{{ __('site.bar_menu_hint') }}</p>

                    <ul class="bar-menu__list" role="list">
                        @foreach ($barMenu as $sheet)
                            <li>
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

                    <p class="bar-menu__allergens">{{ __('site.bar_allergens') }}</p>
                </section>
            @endif

            <aside class="bar-counter" aria-labelledby="bar-counter-title">
                <h2 class="bar-counter__title" id="bar-counter-title">{{ __('site.bar_counter_title') }}</h2>
                <p class="bar-counter__text">{{ __('site.bar_counter_text') }}</p>
                {{-- Tres estados, no dos (`#536`): sin decidir en el panel no se escribe nada del acceso. --}}
                @if ($barFreeEntry)
                    <p class="bar-counter__entry">{{ __('site.bar_free_entry_'.$barFreeEntry) }}</p>
                @endif
            </aside>
        </div>

        {{-- La nota de los que vienen de cumpleaños cuelga de `partyUrl`, que sale del INVENTARIO: con
             esa página en mantenimiento no hay mesas de las que hablar (`#521`). --}}
        @if ($partyUrl)
            <div class="bar-party">
                <p class="bar-party__line">{{ __('site.bar_party_line') }}</p>
            </div>
        @endif

        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
