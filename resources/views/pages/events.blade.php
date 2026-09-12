@php
    // Meta description (SEO, INVISIBLE en la página): la descripción del primer pack, como antes.
    $meta = $packages->first()?->tr('description') ?: __('landing.birthday.lede');
    $cols = $compare ? count($compare['columns']) : 0;
@endphp
<x-layout :title="__('landing.nav.events')" :description="$meta">
<div x-data="landing">
    <x-site.nav />

    {{-- ══ /cumpleanos · EL CUMPLE, AL DETALLE ═══════════════════════════════════════════════════════
         Carril de diseño Fase 3 · T3b (`DECISIONES #528`). Artboard `Cumpleanos Pagina PJP` **1a**
         (móvil) + **1b** (escritorio), con el carril de **5a**.

         ▶ **La espina es la del artboard**: el reloj primero —qué pasa ese día—, después los packs
         comparados, qué comen, lo que se puede añadir, lo que no cambia de un pack a otro y lo que se
         decide después de reservar.

         ⚠️⚠️ **CUATRO piezas del artboard NO están, y las cuatro las decidió el owner** (`#528`):
         · **las dos fotos 16:9** —la zona montada y la mesa—: no hay ninguna así en el parque, y la
           regla del propio canvas es *«o la foto vende o no está»*. Entran cuando lleguen.
         · **la ficha de invitado de ejemplo** («Lucía, 7 años, sin gluten»): sus valores son
           inventados y los campos los crea el panel; se publican los RÓTULOS reales.
         · **el paso a paso y el editor de la invitación** de la página anterior: el artboard no los
           trae y se retiran con su JavaScript y su dependencia.
         ⚠️ Y **«Su día especial» se llama aquí «Después de reservar»**: el formulario ya tiene nombre
         de cara al cliente (`guestform.title`) y la página lo cita por ese nombre, que es el que se
         va a encontrar en el correo (`#462`: el post-form no se renombra). --}}
    <main id="main" class="page party-page wrap">
        <x-site.page-head :title="__('landing.birthday.title')"
                          :lede="$compare ? __('landing.birthday.lede') : __('landing.events.coming_soon')" />

        @if (! $compare)
            {{-- Sin packs vendibles no hay comparativa que enseñar: vacío es una respuesta, y la
                 página dice cómo reservar igualmente. --}}
            <a href="{{ route('contacto') }}" class="btn">{{ __('landing.events.coming_soon_cta') }}</a>
        @else
            {{-- ══ LA FOTO DE LA ZONA Y EL RELOJ, EN LA MISMA FILA ═════════════════════════════════
                 El artboard reparte aquí **736 + 352** (`Cumpleanos Pagina PJP` 1b): la foto es *«la
                 prueba del acceso exclusivo —la promesa que más cuesta creer—»* y el reloj va a su
                 derecha. Hasta `#532` la página no tenía foto y el reloj ocupaba la fila entera.

                 ⚠️ **La foto es DATO** (`zones.image`, el campo del panel): si la zona no tiene, no
                 se pinta nada y el reloj recupera la fila — **no se reserva un hueco gris**, que es
                 la regla que el canvas escribió para la tarjeta del bar y que `/atracciones` ya
                 aplica en sus fichas.
                 ⚠️⚠️ **El `alt` es el nombre de la ZONA y sale del panel**, como en `/atracciones`
                 con el nombre de la atracción: describir la foto con una frase escrita aquí sería
                 afirmar lo que enseña una imagen que cambia con cada instalación. --}}
            <div class="party-hero">
                @if ($zone?->image)
                    <figure class="party-photo">
                        <img src="{{ asset($zone->image) }}" alt="{{ $zone->tr('name') }}"
                             loading="lazy" decoding="async">
                    </figure>
                @endif

                {{-- EL RELOJ DE LAS DOS HORAS, el MISMO componente que la portada. ⚠️ Solo si todos
                     los packs duran lo mismo: si no, su titular hablaría de uno como si fuera de
                     todos, y la duración baja a una fila de la comparativa. --}}
                @if ($compare['duration'])
                    <x-site.party-clock :duration="$compare['duration']" :level="2" />
                @endif
            </div>

            {{-- ══ LOS PACKS, COMPARADOS ══════════════════════════════════════════════════════════
                 ❗❗ **Solo compara lo que DIFIERE**: lo común baja a «Igual en los dos».
                 ❗❗ **EL TOTAL NO SE CALCULA EN EL NAVEGADOR**: el servidor lo trae hecho para cada
                 número de niños posible —con el precio que cobra la cesta, tramos incluidos— y el
                 contador solo elige cuál enseñar. Sin JavaScript se lee la tabla del mínimo y los
                 botones no aparecen (`x-cloak`): un control que no hace nada no se ofrece. --}}
            <section class="party-page__block" aria-labelledby="party-packs">
                <h2 class="party-page__title" id="party-packs">{{ trans_choice('landing.birthday.packs_title', $cols, ['count' => $cols]) }}</h2>

                <div class="party-compare"
                     x-data="{ n: {{ $compare['counter']['from'] }}, from: {{ $compare['counter']['from'] }}, to: {{ $compare['counter']['to'] }}, live: @js($compare['live']) }">
                    @if ($compare['counter']['to'] > $compare['counter']['from'])
                        <div class="party-count">
                            <span class="party-count__q">{{ __('landing.birthday.count_question') }}</span>
                            <button type="button" class="party-count__btn" x-cloak
                                    aria-label="{{ __('landing.birthday.count_less') }}"
                                    :disabled="n <= from" @click="n = Math.max(from, n - 1)">−</button>
                            <span class="party-count__n" aria-live="polite" x-text="n">{{ $compare['counter']['from'] }}</span>
                            <button type="button" class="party-count__btn" x-cloak
                                    aria-label="{{ __('landing.birthday.count_more') }}"
                                    :disabled="n >= to" @click="n = Math.min(to, n + 1)">+</button>
                        </div>
                    @endif

                    {{-- ⚠️ Una TABLA de verdad, no filas de `<div>`: son valores alineados por pack
                         y el lector de pantalla tiene que poder decir de qué columna es cada cifra.
                         ⚠️ Con tres packs o más no cabe en un teléfono: el envoltorio desliza, que es
                         lo único que puede ser más ancho que la página. --}}
                    <div class="party-compare__scroll">
                        <table class="party-compare__table">
                            <caption class="sr-only">{{ __('landing.birthday.table_label') }}</caption>
                            <thead>
                                <tr>
                                    <td class="party-compare__corner"></td>
                                    @foreach ($compare['columns'] as $column)
                                        <th scope="col" class="party-compare__pack">{{ $column['name'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($compare['rows'] as $row)
                                    <tr class="party-compare__row party-compare__row--{{ $row['kind'] }}">
                                        <th scope="row" class="party-compare__label">{{ $row['label'] }}</th>
                                        @foreach ($row['cells'] as $i => $cell)
                                            <td class="party-compare__cell"
                                                @if ($row['live']) x-text="live.{{ $row['live'] }}[n][{{ $i }}] ?? '—'" @endif>
                                                @if (is_array($cell))
                                                    @foreach ($cell as $line)
                                                        {{-- ⚠️ El espacio antes de la línea de la especial no es de maquetación (la línea es un
                                                             bloque): sin él un lector de pantalla leería «3 €5 € en tarifa especial». --}}
                                                        <span class="party-compare__line">{{ $line['t'] }}@if ($line['s']) <span class="party-compare__sub">{{ $line['s'] }}</span>@endif</span>
                                                    @endforeach
                                                @else
                                                    {{ $cell ?? '—' }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="party-compare__foot">
                        <div class="party-compare__notes">
                            {{-- Los días de la tarifa especial, UNA vez por página (`#479`). --}}
                            @if ($compare['special'])
                                <x-site.special-rate-note />
                            @endif
                            @if ($compare['deposit'])
                                <p class="party-page__note">{{ __('landing.birthday.deposit_line', ['deposit' => $compare['deposit']]) }}</p>
                            @endif
                        </div>
                        {{-- ❗ **El «Reservar el cumple» FANTASMA del artboard, solo en escritorio**: allí no
                             hay barra flotante —se retira a 1024— y quien decide a mitad de página
                             tendría que subir al racimo o bajar hasta el pie. En móvil la barra ya está.
                             ⚠️ Es fantasma (`.btn--ghost`), no relleno: el único relleno de acción de la
                             pantalla sigue siendo el del racimo. --}}
                        <button type="button" class="btn btn--ghost party-compare__cta"
                                @click="$store.purchase.openWith({ type: 'packs' })">{{ __('landing.birthday.book') }}</button>
                    </div>
                </div>
            </section>

            {{-- ══ QUÉ COMEN ═══════════════════════════════════════════════════════════════════════
                 El artboard: *«el menú no es un complemento: es una elección dentro del pack»*. Sale
                 de los GRUPOS de elección excluyente del catálogo —el mismo mecanismo que en el cajón
                 obliga a elegir uno— y cada opción cuenta lo que lleva (sus ventajas en el panel).
                 ⚠️ El titular «Qué comen» es para el grupo `menu`, la clave que el propio panel pone de
                 ejemplo; cualquier otro grupo lleva un titular neutro, porque no sabemos qué elige.
                 ⚠️ **El precio sale del catálogo con su unidad del PIVOTE** (`[DECIDIDO owner]`: «+2 €
                 por invitado», no un precio entero por pack). --}}
            @foreach ($choices as $group)
                <section class="party-page__block" aria-labelledby="party-choice-{{ $loop->index }}">
                    <h2 class="party-page__title" id="party-choice-{{ $loop->index }}">{{ $group['key'] === 'menu' ? __('landing.birthday.menu_title') : __('landing.birthday.choice_title') }}</h2>
                    <p class="party-page__lede">{{ trans_choice('landing.birthday.choice_lede', count($group['rows']), ['count' => count($group['rows'])]) }}</p>
                    <ul class="party-menus" role="list">
                        @foreach ($group['rows'] as $row)
                            <li class="party-menu">
                                <div class="party-menu__head">
                                    <h3 class="party-menu__name">{{ $row['name'] }}</h3>
                                    <span class="party-menu__chip @if (! $row['price']) party-menu__chip--included @endif">
                                        @if ($row['price'])
                                            @if ($row['varies']){{ __('landing.rates.from') }} @endif+{{ $row['price'] }}&nbsp;€ {{ $row['perGuest'] ? __('landing.rates.addon_per_guest') : __('landing.rates.addon_each') }}
                                        @elseif ($row['badge'])
                                            {{ __('tickets.addon_badge_'.$row['badge']) }}
                                        @endif
                                    </span>
                                </div>
                                @if ($row['features'] !== [])
                                    <ul class="party-menu__items" role="list">
                                        @foreach ($row['features'] as $feature)
                                            <li><span class="party-menu__dot" aria-hidden="true"></span><span>{{ $feature }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            {{-- ══ TU FIESTA, TU MANERA · el carril compartido con la portada y las tarifas ══════════
                 Lo que se añade AL RESERVAR, sin el menú (que ya tiene su bloque). `#483`: *«el mismo
                 formato y diseño que los complementos de las entradas»*. --}}
            <div class="party-page__block">
                <x-site.addons-rail :products="$packages" :without-choices="true" :level="2"
                                    :title="__('landing.events.addons_title')"
                                    :lede="__('landing.events.addons_intro')" />
            </div>

            {{-- ══ IGUAL EN LOS DOS ══════════════════════════════════════════════════════════════
                 La otra mitad de la regla de la comparativa: lo que coincide en todos los packs, dicho
                 una vez. ⚠️ Lo decide el servidor comparando el catálogo, no una lista escrita aquí. --}}
            @if ($compare['shared'] !== [])
                <section class="party-page__block party-shared" aria-labelledby="party-shared">
                    <div class="party-shared__head">
                        <h2 class="party-page__title" id="party-shared">{{ trans_choice('landing.birthday.shared_title', $cols) }}</h2>
                        <p class="party-page__lede">{{ trans_choice('landing.birthday.shared_lede', $cols) }}</p>
                    </div>
                    <ul class="party-shared__list" role="list">
                        @foreach ($compare['shared'] as $item)
                            <li class="party-shared__item">
                                <span class="party-shared__tick" aria-hidden="true"><x-icons.check :width="12" :height="12" /></span>
                                <span class="party-shared__text">
                                    <span class="party-shared__t">{{ $item['title'] }}</span>
                                    @if ($item['note'])
                                        <span class="party-shared__d">{{ $item['note'] }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- ══ DESPUÉS DE RESERVAR ════════════════════════════════════════════════════════════
                 Lo que pide el formulario de la reserva y lo que se puede añadir desde él. Todo sale
                 del esquema de cada pack; lo añadido ahí se paga en el parque (`#244`). --}}
            @if ($form['children'] !== [] || $form['group'] !== [] || $form['extras'] !== [])
                <section class="party-page__block" aria-labelledby="party-after">
                    <h2 class="party-page__title" id="party-after">{{ __('landing.birthday.after_title') }}</h2>
                    <p class="party-page__lede">{{ __('landing.birthday.after_lede', ['form' => __('guestform.title')]) }}</p>
                    <div class="party-form">
                        @foreach (['after_children' => $form['children'], 'after_group' => $form['group']] as $key => $labels)
                            @if ($labels !== [])
                                <div class="party-form__col">
                                    <p class="party-form__label">{{ __('landing.birthday.'.$key) }}</p>
                                    <ul class="party-form__card" role="list">
                                        @foreach ($labels as $label)
                                            <li class="party-form__row"><span class="party-form__t">{{ $label }}</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                        @if ($form['extras'] !== [])
                            <div class="party-form__col">
                                <p class="party-form__label">{{ __('landing.birthday.after_extras') }}</p>
                                <ul class="party-form__card" role="list">
                                    @foreach ($form['extras'] as $extra)
                                        <li class="party-form__row">
                                            <span class="party-form__t">{{ $extra['name'] }}</span>
                                            <span class="party-form__d">{{ $extra['price'] }}@if ($extra['cutoff']) · {{ $extra['cutoff'] }}@endif</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    @if ($form['extras'] !== [])
                        <p class="party-page__note">{{ __('landing.birthday.after_paid') }}</p>
                    @endif
                </section>
            @endif

            {{-- ══ EL CIERRE: las edades mezcladas y el aviso ═════════════════════════════════════
                 ❗❗ **La nota de edades mezcladas llegó de la portada en `#498`** («se mueve, no se
                 borra»: el suplemento mixto cobra de verdad). Aquí es la tarjeta del artboard y dice
                 las dos direcciones —a favor o en contra— porque las dos existen (`#296`).
                 ⚠️ Solo si hay dos packs o más de la MISMA familia de edades: sin eso una fiesta no
                 puede ser mixta y la tarjeta hablaría de algo que no pasa. --}}
            <div class="party-page__block party-close">
                @if ($compare['mixed'] >= 2)
                    <div class="party-mixed">
                        <h2 class="party-mixed__title">{{ trans_choice('landing.birthday.mixed_title', $compare['mixed']) }}</h2>
                        <p class="party-mixed__text">{{ __('landing.birthday.mixed_text') }}</p>
                        <p class="party-mixed__text">{{ __('landing.birthday.mixed_seal') }}</p>
                    </div>
                @endif
                <aside class="party-info">
                    <span class="party-info__mark" aria-hidden="true">i</span>
                    <div>
                        <p class="party-info__title">{{ __('landing.birthday.info_title') }}</p>
                        <p class="party-info__text">{{ __('landing.birthday.info_text') }}</p>
                    </div>
                </aside>
            </div>
        @endif
        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
