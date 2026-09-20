{{-- ══ EL ANFITRIÓN MÍNIMO de /precios · lo que el producto sirve SIN paquete de instancia ══════
     F5 · T2b (`specs/paquete-de-instancia.md` §4.4, `DECISIONES #658`). La página de PlayJump (artboard
     `Precios Pagina PJP` 1a/1b, `#531` del carril de diseño) vive en su instancia; esto es lo que queda en
     el producto: el armazón, la semana dibujada, una tabla por zona con sus dos columnas de precio entero,
     la nota del IVA, qué es la tarifa especial, el calendario de fechas y el QR del registro externo. Sin
     arte: ni fachada ni rayos. Es marcado del PRODUCTO (`AnfitrionPreciosTest` lo mira).

     ⚠️ Lo que se pinta aquí es EXACTAMENTE el contrato de vista (`InstanceViews::CONTRATO_DE_VISTAS`), y
     TODO llega compuesto: esta vista no calcula un precio, ni decide si un día se vende, ni escribe una
     fecha. Una instancia que pinte lo mismo con su diseño no necesita saber nada más.

     ❗❗ **La página NO lleva CTA propio**: la tabla es para consultar y la acción la trae el armazón —el
     par de arriba y la barra de abajo en móvil—, que ya cambia «Reservar» por el teléfono cuando la venta
     online está cerrada. Recuperar aquí un botón es volver a tener dos sitios que deciden lo mismo. --}}
<x-layout :title="__('landing.nav.pricing')" :description="__('landing.pricing.intro')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page rate-page wrap">
        <x-site.page-head :title="__('landing.pricing.title')" :lede="__('landing.pricing.intro')" />

        {{-- ══ LA SEMANA DIBUJADA ═══════════════════════════════════════════════════════════════
             ⚠️ **Cada día lleva su nombre completo para quien no ve la inicial**: «L» no es un nombre
             accesible, y en francés la inicial del martes y la del miércoles son la misma.
             ⚠️ Sin tarifa especial declarada no hay tira: dibujar siete días sin saber cuáles son
             especiales sería un diagrama que afirma lo que nadie ha medido (`RateTable::week()`). --}}
        @if ($week)
            <section class="week" aria-labelledby="week-title">
                <h2 id="week-title" class="sr-only">{{ __('landing.pricing.week_label') }}</h2>
                <ul class="week__days" role="list">
                    @foreach ($week as $dia)
                        <li @class(['week__day', 'week__day--special' => $dia['special']])>
                            <span class="week__initial" aria-hidden="true">{{ $dia['initial'] }}</span>
                            <span class="sr-only">{{ $dia['name'] }}: {{ $dia['special'] ? __('landing.pricing.week_special') : __('landing.pricing.week_normal') }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="week__legend" aria-hidden="true">
                    <span class="week__legend-item">{{ __('landing.pricing.week_normal') }}</span>
                    <span class="week__legend-item week__legend-item--special">{{ __('landing.pricing.week_special') }}</span>
                </p>
            </section>
        @endif

        {{-- ══ UNA TABLA POR ZONA ═══════════════════════════════════════════════════════════════
             ❗❗❗ **DOS COLUMNAS DE PRECIO ENTERO**, nunca un recargo: *«un recargo no se publica como
             recargo»*, y menos cuando no es plano (en este catálogo la especial sube 2 € en una zona y 4 €
             en la otra).
             ⚠️ **La raya no es «gratis»: es que ese día no se vende**, y lo dice en voz alta el texto para
             lector de pantalla. El producto lo distingue en el dato (`normal`/`special` en `null`) para que
             la vista no tenga que inventar la diferencia. --}}
        <div class="rate-page__zones">
            @foreach ($rateTable as $zona)
                <section class="rate-zone" aria-labelledby="zone-{{ $zona['slug'] }}">
                    <h2 class="rate-zone__head" id="zone-{{ $zona['slug'] }}">
                        {{-- El cuadrado es la IDENTIDAD de la zona y su color llega del panel: no es color
                             de acción ni de estado (`#436`). Va `aria-hidden` porque el nombre está al lado. --}}
                        <span class="rate-zone__dot" aria-hidden="true" style="background: {{ $zona['color'] }}"></span>
                        <span class="rate-zone__name">{{ $zona['name'] }}</span>
                        @if ($zona['age'])
                            <span class="rate-zone__age">{{ $zona['age'] }}</span>
                        @endif
                        @if ($zona['unit'])
                            <span class="rate-zone__unit">{{ $zona['unit'] }}</span>
                        @endif
                    </h2>

                    <div class="rate-table">
                        <table class="rate-table__table">
                            <caption class="sr-only">{{ __('landing.pricing.table_label', ['zone' => $zona['name']]) }}</caption>
                            <thead>
                                <tr>
                                    <td class="rate-table__corner"></td>
                                    <th scope="col" class="rate-table__col">{{ $colNormal }}</th>
                                    @if ($colSpecial)
                                        <th scope="col" class="rate-table__col">{{ $colSpecial }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($zona['rows'] as $fila)
                                    <tr class="rate-table__row">
                                        <th scope="row" class="rate-table__what">
                                            <span class="rate-table__name">{{ $fila['name'] }}</span>
                                            @if ($fila['nuance'])
                                                <span class="rate-table__nuance">{{ $fila['nuance'] }}</span>
                                            @endif
                                            {{-- La ETIQUETA DESTACADA del panel (`ticket_types.badge`), en
                                                 toda fila que la tenga (`#585`). --}}
                                            @if ($fila['badge'])
                                                <span class="rate-table__badge">{{ $fila['badge'] }}</span>
                                            @endif
                                            @if ($fila['note'])
                                                <span class="rate-table__note">{{ $fila['note'] }}</span>
                                            @endif
                                        </th>
                                        <td class="rate-table__cell rate-table__cell--normal">
                                            @if ($fila['normal'])
                                                {{-- El precio de ANTES, tachado (chapuza declarada; solo con
                                                     `promo.percent`). --}}
                                                @if ($fila['normal_was'])
                                                    <s class="rate-table__was"><span class="sr-only">{{ __('landing.rates.was') }} </span>{{ $fila['normal_was'] }}</s>
                                                @endif
                                                {{ $fila['normal'] }}
                                            @else
                                                <span aria-hidden="true">—</span>
                                                <span class="sr-only">{{ __('landing.pricing.not_sold') }}</span>
                                            @endif
                                        </td>
                                        @if ($colSpecial)
                                            <td class="rate-table__cell rate-table__cell--special">
                                                @if ($fila['special'])
                                                    @if ($fila['special_was'])
                                                        <s class="rate-table__was"><span class="sr-only">{{ __('landing.rates.was') }} </span>{{ $fila['special_was'] }}</s>
                                                    @endif
                                                    {{ $fila['special'] }}
                                                @else
                                                    <span aria-hidden="true">—</span>
                                                    <span class="sr-only">{{ __('landing.pricing.not_sold') }}</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
        {{-- Quien compara precios pregunta si el IVA va dentro (`#586`). --}}
        @if ($rateTable !== [])
            <p class="rate-page__vat">{{ __('landing.pricing.vat_note') }}</p>
        @endif

        {{-- ══ QUÉ ES LA TARIFA ESPECIAL · Y LOS FESTIVOS ════════════════════════════════════════
             ⚠️ **Los días salen del panel** (`rate_types.label`) y los llanos se derivan: sin la tarifa
             especial esta tarjeta no se pinta, porque no habría término que definir.
             ⚠️ **La lista de festivos NO lleva frase general**: cada fecha dice su propio hecho —cerrado,
             su tarifa o su horario—. «Cuentan como fin de semana» es justo lo que `#487` retiró de la
             portada, porque el producto no puede afirmarlo. --}}
        <div class="rate-page__pair">
            @if ($specialLabel)
                <section class="rate-note" aria-labelledby="rate-note-title">
                    <h2 class="rate-note__title" id="rate-note-title">{{ __('landing.pricing.special_title') }}</h2>
                    {{-- ⚠️ El rótulo va en negrita y **escapado antes de entrar**: el dato lo escribe el
                         panel, así que se resalta sin darle permiso para traer marcado. --}}
                    <p class="rate-note__text">{!! __('landing.pricing.special_text', ['label' => '<b>'.e($specialLabel).'</b>']) !!}</p>
                    @if ($plainDays)
                        <p class="rate-note__text">{{ __('landing.pricing.special_plain', ['days' => $plainDays]) }}</p>
                    @endif
                    <p class="rate-note__text">{{ __('landing.pricing.special_calm') }}</p>
                </section>
            @endif

            @if ($holidays !== [])
                <section class="holidays" aria-labelledby="holidays-title">
                    <h2 class="rate-page__title" id="holidays-title">{{ __('landing.pricing.holidays_title') }}</h2>
                    <ul class="holidays__list" role="list">
                        @foreach ($holidays as $festivo)
                            <li @class(['holidays__row', 'holidays__row--closed' => $festivo['is_closed']])>
                                <span class="holidays__when">
                                    <span class="holidays__date">{{ $festivo['date'] }}</span>
                                    @if ($festivo['name'])
                                        <span class="holidays__name">{{ $festivo['name'] }}</span>
                                    @endif
                                </span>
                                <span class="holidays__fact">{{ $festivo['fact'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        {{-- El QR del registro EXTERNO: solo existe en una instalación que registre fuera (el componente se
             guarda solo). En ésta no se pinta, y por eso el SVG ni se genera. --}}
        <x-site.registration-qr :svg="$registrationSvg" />

        {{-- La salida: esta página acaba apuntando a los packs, porque no están en su tabla y tienen su
             propia tarifa. Quien ofrece destinos es la banda, no una línea escrita para esta página. --}}
        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
