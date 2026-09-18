<x-layout :title="__('landing.nav.pricing')" :description="__('landing.pricing.intro')">
<div x-data="landing">
    <x-site.nav />

    {{-- ══ /precios · TODAS LAS TARIFAS ══════════════════════════════════════════════════════════
         Carril de diseño Fase 3 · T3b (`DECISIONES #531`). Artboard `Precios Pagina PJP` **1a**
         (móvil) + **1b** (escritorio).

         ❗❗❗ **SIN PESTAÑA DE ZONA, y el motivo está en la prueba que le dio página**: *«los precios
         se buscan por su nombre o se mandan por WhatsApp desde el mostrador»*, así que quien abre el
         enlace **no ha elegido zona** — esconder media tabla detrás de un control es lo contrario de
         lo que viene a hacer. En `/atracciones` la zona sí viene elegida desde la portada.

         ▶ **La página NO lleva CTA propio** (artboard, las dos superficies): la tabla es para
         consultar y la acción la trae el armazón —el par arriba y la barra abajo en el móvil—, que
         ya cambia «Reservar» por el teléfono cuando la venta online está cerrada (`cta-pair`).

         ⚠️ Lo que esta página **no** dice y antes decía: las `features` de cada entrada (la tabla
         publica lo que el visitante viene a mirar; las ventajas siguen en el cajón, que es donde se
         compra) y la nota estática de calcetines, que ahora es una ficha del bloque de abajo **con
         el texto que el panel escribe en el propio complemento**. --}}
    <main id="main" class="page rate-page wrap">
        <x-site.facade en="page-precios" />
        {{-- ⚠️ A3 · el abanico de rayos ya NO va en la ranura `deco`: la cabecera lo recortaba en una
             banda. Vive detrás de la figura de la fachada (`<x-site.facade>`, `#580`). --}}
        <x-site.page-head :title="__('landing.pricing.title')" :lede="__('landing.pricing.intro')" />

        {{-- ══ LA SEMANA DIBUJADA ═══════════════════════════════════════════════════════════════
             ❗❗ **El día especial se marca con SUPERFICIE —tinta contra papel—, no con color**: es la
             regla del velo del carril de la portada, y la que hace que la tira se lea igual en las
             dos superficies del tema.
             ⚠️ **Cada día lleva su nombre completo para quien no ve la inicial**: «L» no es un
             nombre accesible, y en francés la inicial del martes y la del miércoles son la misma.
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
             recargo»*, y menos cuando no es plano (en este catálogo la especial sube 2 € en una zona
             y 4 € en la otra).
             ⚠️ **La raya no es «gratis»: es que ese día no se vende**, y lo dice en voz alta el texto
             para lector de pantalla. La misma regla que pone el «solo …» en la nota de la fila.
             ⚠️ **La hora extra ya NO es una fila** (`[DECIDIDO owner, 2026-09-13]`, `#583`, revierte la
             decisión de `#531`): es un complemento, y los complementos solo se ofrecen al reservar. --}}
        <div class="rate-page__zones">
            @foreach ($rateTable as $zona)
                <section class="rate-zone" aria-labelledby="zone-{{ $zona['slug'] }}">
                    <h2 class="rate-zone__head" id="zone-{{ $zona['slug'] }}">
                        {{-- El cuadrado es la IDENTIDAD de la zona y su color llega del panel: no es
                             color de acción ni de estado (`#436`). Va `aria-hidden` porque el nombre
                             está justo al lado. --}}
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
                                            {{-- La ETIQUETA DESTACADA del panel (`ticket_types.badge`),
                                                 en toda fila que la tenga (`#585`). --}}
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
                                                     `promo.percent`), como en el carril de la portada. --}}
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
        {{-- Quien compara precios pregunta si el IVA va dentro (`#586`). Fuera de `.rate-page__zones`,
             que en escritorio es una fila: dentro sería una columna más al lado de las tablas. --}}
        @if ($rateTable !== [])
            <p class="rate-page__vat">{{ __('landing.pricing.vat_note') }}</p>
        @endif

        {{-- ══ QUÉ ES LA TARIFA ESPECIAL · Y LOS FESTIVOS ════════════════════════════════════════
             ⚠️ **Los días salen del panel** (`rate_types.label`) y los normales se derivan: sin la
             tarifa especial esta tarjeta no se pinta, porque no habría término que definir.
             ⚠️ **La lista de festivos NO lleva frase general**: cada fecha dice su propio hecho
             —cerrado, su tarifa o su horario—. «Cuentan como fin de semana» es justo lo que `#487`
             retiró de la portada, porque el producto no puede afirmarlo. --}}
        <div class="rate-page__pair">
            @if ($specialLabel)
                <section class="rate-note" aria-labelledby="rate-note-title">
                    <h2 class="rate-note__title" id="rate-note-title">{{ __('landing.pricing.special_title') }}</h2>
                    {{-- ⚠️ El rótulo va en negrita y **escapado antes de entrar**: el dato lo escribe
                         el panel, así que se resalta sin darle permiso para traer marcado. --}}
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

        {{-- ⚠️⚠️ **AQUÍ VIVÍA «LO QUE SE AÑADE», LOS COMPLEMENTOS, Y SE RETIRÓ** (`[DECIDIDO owner,
             2026-09-13]`, `#583`): *«quita los complementos de la página web, solo los dejamos en el
             SPA al reservar»*. Se fue con él la fila de la hora extra de cada tabla. --}}

        {{-- El QR del registro EXTERNO: solo existe en una instalación que registre fuera (el
             componente se guarda solo). Aquí no se pinta. --}}
        <x-site.registration-qr :svg="$registrationSvg" />

        {{-- ══ LA SALIDA A CUMPLEAÑOS, AHORA EN SU PIEZA ════════════════════════════════════════
             Aquí vivía `.rate-page__birthdays`: una línea con su enlace a los packs, escrita a mano.
             La PROPIEDAD no cambia —esta página sigue acabando apuntando a los packs, porque los
             packs no están en su tabla y tienen su propia tarifa—, cambia quién la pinta: era una de
             las cuatro salidas a medida del producto y ahora es la banda GORDA, que en el reparto
             del canvas contesta desde aquí exactamente eso («¿y si venís en grupo?» → `/cumpleanos`).
             ⚠️ Su frase decía «con la comida incluida», comprobable en ESTE catálogo pero no en el
             producto: el cuerpo de la banda describe el destino y deja el dato a quien lo tiene. --}}
        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
