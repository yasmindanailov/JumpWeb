{{-- ══ EL ANFITRIÓN MÍNIMO de /servicios · lo que el producto sirve SIN paquete de instancia ═══
     F5 · T2b (`specs/paquete-de-instancia.md` §4.4, `DECISIONES #660`). La página de PlayJump (diseño
     «Editorial XL», `#256`/`#588`) vive en su instancia; esto es lo que queda en el producto: el hero con
     su índice de anclas, una fila por servicio con sus tablas de grupo, el «desde», la foto, los packs de
     cumpleaños en corto y las bandas. Sin arte: sin la CINTA `C3`. Es marcado del PRODUCTO
     (`AnfitrionServiciosTest` lo mira).

     ⚠️ Lo que se pinta aquí es EXACTAMENTE el contrato de vista (`InstanceViews::CONTRATO_DE_VISTAS`) y
     TODO lo comercial llega compuesto: ni el precio que se anuncia ni cómo se escribe se deciden aquí.

     ▶ Cada fila conserva su ancla estable (`slug`): el menú enlaza a `/servicios#slug`, así que cambiarla
     es SEO perdido sin que falle nada. --}}
@php
    // Hero: resalta `title_accent` (subcadena exacta de `title`) con `.blink`, fiel al mockup v2.
    // Se escapa todo; si el fragmento no aparece en el título, queda el título plano escapado.
    $svcTitle = __('services.title');
    $svcAccent = (string) __('services.title_accent');
    $svcTitleHtml = ($svcAccent !== '' && str_contains($svcTitle, $svcAccent))
        ? str_replace(e($svcAccent), '<span class="blink">'.e($svcAccent).'</span>', e($svcTitle))
        : e($svcTitle);
@endphp
<x-layout :title="__('services.meta.title')" :description="__('services.meta.description')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="svc-main">
        {{-- Hero: título XL + intro + índice de anclas a cada servicio.
             ⚠️ El EYEBROW se retiró (`#303`, `[DECIDIDO owner]`: «el eyebrow de los titulares lo
             vamos a quitar»). --}}
        <header class="svc-hero wrap">
            <h1 class="svc-hero__title">{!! $svcTitleHtml !!}</h1>
            <p class="svc-hero__intro">{{ __('services.intro') }}</p>
            @if ($services->isNotEmpty())
                <nav class="svc-hero__index" aria-label="{{ __('services.title') }}">
                    @foreach ($services as $service)
                        <a href="#{{ $service->slug }}" class="svc-hero__jump" data-tap>{{ $service->tr('title') }}</a>
                    @endforeach
                </nav>
            @endif
        </header>

        {{-- Filas editoriales: una por servicio, full-bleed alternadas (par = flip + banda) --}}
        @if ($services->isNotEmpty())
            <div class="svc-ed2">
                @foreach ($services as $service)
                    @php
                        $flip = $loop->iteration % 2 === 0;
                        $zoneLabel = $service->tr('zone_label');
                        // Las tablas de sus productos comprables (`#588`), ya compuestas en el dominio.
                        $tables = $groupRates[$service->id] ?? [];
                        // ⚠️ El «desde» lo elige y lo escribe el PRODUCTO (`GroupRateTables::lowestWritten`,
                        // `#660`): cuál es el precio más bajo de sus tablas es regla de catálogo, no de esta
                        // página. Aquí solo se pinta.
                        $from = $groupFrom[$service->id] ?? null;
                        $unit = $tables[0]['unit'] ?? null;
                    @endphp
                    <section id="{{ $service->slug }}"
                             class="svc-ed2__row @if ($flip) svc-ed2__row--flip svc-ed2__row--band @endif">
                        <div class="wrap svc-ed2__inner">
                            <div class="svc-ed2__body">
                                <span class="svc-ed2__kicker">{{ __('services.service_label') }} · {{ $zoneLabel }}</span>
                                <h2 class="svc-ed2__title">{{ $service->tr('title') }}</h2>
                                <p class="svc-ed2__desc">{{ $service->tr('body') }}</p>
                                <dl class="svc-spec">
                                    @foreach ($service->tr('specs') ?? [] as $spec)
                                        <div class="svc-spec__row">
                                            <dt class="svc-spec__label">{{ $spec['label'] }}</dt>
                                            <dd class="svc-spec__val">{{ $spec['value'] }}</dd>
                                        </div>
                                    @endforeach
                                    @if ($zoneLabel)
                                        <div class="svc-spec__row">
                                            <dt class="svc-spec__label">{{ __('services.zone_label') }}</dt>
                                            <dd class="svc-spec__val">{{ $zoneLabel }}</dd>
                                        </div>
                                    @endif
                                </dl>

                                {{-- ══ LAS TABLAS DE SUS PRODUCTOS (`#588`) ══════════════════════════════════
                                     Una por producto, leída de sus TRAMOS: la tabla dice lo mismo que cobra la
                                     cesta, sin una segunda copia tecleada. Cada una con su «Reservar», que abre el
                                     cajón en ESE producto (`#568`). Las columnas son las de `/precios`. --}}
                                @if ($tables !== [])
                                    <div class="svc-rates">
                                        <span class="svc-rates__title">{{ __('services.rates.title') }}</span>
                                        {{-- Un producto por FILA (`#589`, `[DECIDIDO owner]`): lado a lado las dos tablas
                                             quedaban estrechas y se leían como una sola. --}}
                                        <div class="svc-rates__panel svc-rates__panel--rows">
                                            @foreach ($tables as $table)
                                                <div class="svc-rates__product">
                                                    <table class="svc-rates__table">
                                                        <caption>
                                                            <span class="svc-rates__cap">{{ $table['name'] }}</span>
                                                            @if ($table['badge'])
                                                                <span class="svc-ed2__badge">{{ $table['badge'] }}</span>
                                                            @endif
                                                        </caption>
                                                        <thead>
                                                            <tr>
                                                                <th scope="col">{{ __('services.rates.group') }}</th>
                                                                <th scope="col">{{ $rateColumns['normal'] }}</th>
                                                                @if ($rateColumns['special'])
                                                                    <th scope="col">{{ $rateColumns['special'] }}</th>
                                                                @endif
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($table['rows'] as $row)
                                                                <tr>
                                                                    <th scope="row">{{ __('services.rates.from_count', ['count' => $row['from']]) }}</th>
                                                                    <td>
                                                                        @if ($row['normal'])
                                                                            {{ $row['normal'] }}
                                                                        @else
                                                                            <span aria-hidden="true">—</span>
                                                                            <span class="sr-only">{{ __('landing.pricing.not_sold') }}</span>
                                                                        @endif
                                                                    </td>
                                                                    @if ($rateColumns['special'])
                                                                        <td>
                                                                            @if ($row['special'])
                                                                                {{ $row['special'] }}
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
                                                    {{-- Los regalos del servicio (`#589`), cada uno en su etiqueta. --}}
                                                    <x-site.gifts :gifts="$table['gifts']" class="svc-rates__gifts" />
                                                    <button type="button" class="svc-cta svc-cta--book"
                                                            aria-label="{{ __('landing.pricing.book') }} · {{ $table['name'] }}"
                                                            @click="$store.purchase.openWith({ type: 'product', id: {{ $table['id'] }} })">
                                                        {{ __('landing.pricing.book') }}
                                                        <x-icons.arrow-right :width="15" :height="15" />
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                        <p class="svc-rates__note">{{ $unit ? __('services.rates.note_products', ['unit' => $unit]) : __('landing.pricing.vat_note') }}</p>
                                    </div>
                                {{-- La tabla TECLEADA (`price_table`) queda solo para un servicio SIN productos que se
                                     vendan online: informativa, con reserva por teléfono. --}}
                                @elseif (! empty($service->price_table['zones'] ?? null))
                                    {{-- ⚠️ CÓMO se escribe un importe de escaparate NO se decide aquí: es regla del
                                         producto (`Money::showcase()`, `#479`), que quita los decimales en cero y
                                         pide el separador al IDIOMA. Antes vivía aquí una tercera variante escrita a
                                         mano —`intdiv()` para los euros redondos y `Money::format()` para el resto—
                                         que en inglés ponía coma decimal (`#660`). Medido en esta instalación: los
                                         doce importes de las tablas son euros exactos, así que no cambia un byte. --}}
                                    @php($fmt = fn (int $c): string => \App\Domain\Platform\Services\Money::showcase($c).' €')
                                    @php($unit = $service->price_table['unit'] ?? 'kids')
                                    <div class="svc-rates" x-data="{ rz: 0 }">
                                        <span class="svc-rates__title">{{ __('services.rates.title') }}</span>
                                        {{-- Pestañas de zona SOLO si hay >1 zona (mismo criterio que el switcher de precios y los
                                             tabs de cumpleaños). Con UNA sola zona (p. ej. Empresas = solo Jump) NO hay toggle: se
                                             pinta su panel directamente.
                                             ⚠️ El tinte va INLINE desde `#138`: las reglas `.zone-tab--{accent}` solo existían para
                                             `jump` y `kids`, así que cualquier otro acento se quedaba sin color. Se resuelve UNA vez
                                             por acento —consulta la BD— y no dentro de los bucles. --}}
                                    @php($zoneStyles = collect($service->price_table['zones'])
                                        ->pluck('accent')->filter()->unique()
                                        ->mapWithKeys(fn (string $a): array => [$a => \App\Domain\Content\Services\ThemeSettings::zoneStyleForAccent($a)])
                                        ->all())
                                        @if (count($service->price_table['zones']) > 1)
                                            <div class="zone-tabs" role="tablist" aria-label="{{ __('services.rates.title') }}">
                                                @foreach ($service->price_table['zones'] as $z => $zone)
                                                    <button type="button" role="tab" class="zone-tab" data-tap
                                                            style="{{ $zoneStyles[$zone['accent'] ?? ''] ?? '' }}"
                                                            :class="rz === {{ $z }} && 'active'"
                                                            :aria-selected="rz === {{ $z }} ? 'true' : 'false'"
                                                            @click="rz = {{ $z }}">{{ $zone['label'] }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @foreach ($service->price_table['zones'] as $z => $zone)
                                            {{-- ⚠️ El estilo se compone en PHP: Blade no compila un `@endif` pegado a un
                                                 carácter de palabra, y el `@if` suelto rompe la vista compilada. --}}
                                            @php($panelStyle = ($zoneStyles[$zone['accent'] ?? ''] ?? '').($loop->first ? '' : 'display:none'))
                                            <div class="svc-rates__panel" role="tabpanel"
                                                 style="{{ $panelStyle }}"
                                                 x-show="rz === {{ $z }}">
                                                @foreach ($zone['durations'] as $dur)
                                                    <table class="svc-rates__table">
                                                        <caption><span class="svc-rates__cap">{{ $zone['label'] }} · {{ trans_choice('services.rates.hours', intdiv((int) $dur['minutes'], 60)) }}</span></caption>
                                                        <thead>
                                                            <tr>
                                                                <th scope="col">{{ __('services.rates.group') }}</th>
                                                                <th scope="col">{{ __('services.rates.weekday') }}</th>
                                                                <th scope="col">{{ __('services.rates.weekend') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($dur['tiers'] as $tier)
                                                                <tr>
                                                                    <th scope="row">{{ __('services.rates.'.$unit, ['count' => $tier['size']]) }}</th>
                                                                    <td>{{ $fmt((int) $tier['weekday']) }}</td>
                                                                    <td>{{ $fmt((int) $tier['weekend']) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @endforeach
                                            </div>
                                        @endforeach
                                        <p class="svc-rates__note">{{ __('services.rates.note_'.$unit) }}</p>
                                    </div>
                                @endif

                                {{-- ⚠️ Aquí vivían los COMPLEMENTOS del pack, y se fueron (`#588`) con la misma decisión
                                     que los retiró del resto de la web (`#583`): solo se ofrecen al reservar. --}}

                                <div class="svc-ed2__foot">
                                    @if ($tables !== [])
                                        {{-- El «desde» es el precio MÁS BAJO de sus tablas (`#329`). La reserva va en cada
                                             tabla, que es donde se elige cuál; la etiqueta destacada, en su título (`#585`). --}}
                                        @if ($from !== null)
                                            <span class="svc-ed2__price">
                                                <span class="from">{{ __('landing.pricing.from') }}</span>
                                                <span class="val">{{ $from }}</span>
                                                @if ($unit)
                                                    <span class="per">{{ $unit }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    @else
                                        <a href="{{ route('contacto') }}" class="svc-cta svc-cta--info">
                                            {{ __('services.cta_contact') }}
                                            <x-icons.arrow-right :width="15" :height="15" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div class="svc-ed2__media">
                                <span class="svc-ed2__big" aria-hidden="true">{{ $service->tr('accent_word') }}</span>
                                <div class="svc-photo">
                                    @if ($foto = $service->imageUrl())
                                        <img class="svc-photo__img" src="{{ $foto }}" alt="{{ $service->tr('title') }}" loading="lazy">
                                    @endif
                                    <span class="tag tag--senal tag--punteada svc-photo__tag">{{ $zoneLabel }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        {{-- ══ LOS CUMPLEAÑOS, EN CORTO ═════════════════════════════════════════════════════════
             `#588`, `[DECIDIDO owner]`: esta página presenta las excursiones y termina con los packs de
             cumpleaños resumidos y su puerta a `/cumpleanos`, donde está todo. Son los de la superficie
             de cumpleaños, así que un pack que vende un servicio no sale dos veces. Sin packs, nada. --}}
        @if ($birthdayCards !== [])
            <section class="svc-party wrap" aria-labelledby="svc-party-title">
                <div class="svc-party__card">
                    <h2 class="svc-party__title" id="svc-party-title">{{ __('services.party.title') }}</h2>
                    <p class="svc-party__lede">{{ __('services.party.lede') }}</p>
                    <ul class="svc-party__list" role="list">
                        @foreach ($birthdayCards as $card)
                            <li class="svc-party__item">
                                <span class="svc-party__name">{{ $card['name'] }}</span>
                                @if ($card['age'])
                                    <span class="svc-party__age">{{ $card['age'] }}</span>
                                @endif
                                {{-- ⚠️ El «€» viene con la cifra desde `PartyCards` (`#661`). --}}
                                <span class="svc-party__price">{{ __('landing.pricing.from') }} {{ $card['price'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a class="btn btn--ink svc-party__cta" href="{{ route('cumpleanos') }}">
                        {{ __('services.party.cta') }}
                        <x-icons.arrow-right :width="18" :height="18" />
                    </a>
                </div>
            </section>
        @endif

        {{-- ⚠️ Aquí vivía la banda «Otros eventos» (despedidas, fiestas privadas, rodajes), y se
             retiró (`#586`, `[DECIDIDO owner, 2026-09-13]`): solo se ofrecen las excursiones de colegio.
             Si vuelve un servicio, entra como fila desde el panel. --}}
        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
