@props([
    'packages',
    'showInvite' => false,
    // Nivel del heading de la banda (Lote 7, a11y): 1 en /cumpleanos (es el <h1> de
    // la página) · 2 embebido en la landing (el hero ya es el <h1>) → evita doble <h1>.
    'level' => 2,
])

{{-- Sección de cumpleaños (#231) — rediseño en 3 secciones del mockup `Seccion Cumpleanos`:
       01 Cumpleaños (banda accent: título · tabs Jump/Kids · polaroid con la FOTO de la zona
          cumpleaños · pack + complementos reales + CTA)
       02 Proceso (5 pasos de la reserva, paso protagonista + raíl de cubos, depósito destacado)
       03 Invitación (editor + tarjeta en vivo) — SOLO en /cumpleanos (`showInvite`), con ancla
          #tarjeta-invitacion. En la landing se sustituye por un enlace sutil a esa página.
     Compartida por home y /cumpleanos para que NO diverjan. Conserva la lógica actual: selector
     de N packs por id (#194), complementos via `<x-site.product-addons>` (incluidos/gratis/«ver
     más»/opciones a elegir) y la tarjeta de invitación editable (`birthdayInvite` + html2canvas).
     El acento `--bda` (y `--zone-1/2` para los complementos) lo fija el pack seleccionado. --}}
@php
    /**
     * ⚠️⚠️ **La paleta de un pack sale de SU ZONA, no de su nombre** (`DECISIONES #139`).
     *
     * Aquí vivía `$packAccent`, que buscaba la subcadena «kids» en el nombre del producto para
     * decidir su acento. Funcionaba con los dos packs del primer cliente y **con nadie más**: en una
     * instalación cuyos packs se llamen de otra forma, TODOS caían al acento `jump` y la sección
     * entera se pintaba con el naranja de una marca ajena, sin que nada fallara.
     *
     * Un pack tiene `zone_id` y las dos rutas que pintan esta sección ya cargan la relación, así que
     * la respuesta estaba en la BD todo el tiempo. La composición es la ÚNICA del tema.
     */
    $packStyle = fn ($p): string => \App\Domain\Content\Services\ThemeSettings::zoneStyle(
        $p->zone?->color, $p->zone?->color_secondary, (string) ($p->zone?->accent ?? ''),
    );

    /**
     * Las paletas que ofrece el selector de color de la invitación: **una por zona con pack**, no
     * dos fijas. Antes eran dos botones escritos a mano, con las palabras «Jump» y «Kids» dentro.
     */
    $invitePalettes = $packages
        ->map(fn ($p) => $p->zone)
        ->filter()
        ->unique('id')
        ->values()
        ->map(fn ($z): array => [
            'key' => (string) $z->id,
            'label' => $z->tr('name'),
            'style' => \App\Domain\Content\Services\ThemeSettings::zoneStyle($z->color, $z->color_secondary, (string) $z->accent),
        ]);

    $firstPack = $packages->first();
    // Foto de la zona cumpleaños (#231): todos los packs comparten la zona operativa «cumpleanos».
    $cumpleImage = $firstPack?->zone?->image;
    $parkName = $site['name'] ?? config('app.name');

    // Depósito (€) para la sección Proceso. Representativo: los packs comparten la misma señal.
    $processDeposit = $firstPack ? intdiv((int) $firstPack->deposit_value, 100) : 30;

    // 5 pasos de la reserva (i18n; el depósito se interpola en el paso de pago).
    $processSteps = [
        ['k' => __('landing.events.process.s1_k'), 't' => __('landing.events.process.s1_t'), 's' => __('landing.events.process.s1_s')],
        ['k' => __('landing.events.process.s2_k'), 't' => __('landing.events.process.s2_t'), 's' => __('landing.events.process.s2_s')],
        ['k' => __('landing.events.process.s3_k'), 't' => __('landing.events.process.s3_t', ['deposit' => $processDeposit]), 's' => __('landing.events.process.s3_s'), 'pay' => true],
        ['k' => __('landing.events.process.s4_k'), 't' => __('landing.events.process.s4_t'), 's' => __('landing.events.process.s4_s')],
        ['k' => __('landing.events.process.s5_k'), 't' => __('landing.events.process.s5_t'), 's' => __('landing.events.process.s5_s')],
    ];

    $inviteCfg = [
        'park' => $parkName,
        'time' => '17:00',
        // ⚠️ La paleta inicial y el mapa de paletas salen de las ZONAS, no de dos claves fijas
        // (`DECISIONES #139`). `invZone` guardaba la cadena `'jump'` y el blade la comparaba con un
        // ternario: con eso, un parque sin una zona llamada así se quedaba con una sola opción y el
        // color de otra marca.
        'invZone' => (string) ($invitePalettes->first()['key'] ?? ''),
        'invStyles' => $invitePalettes->mapWithKeys(fn (array $p): array => [$p['key'] => $p['style']])->all(),
        'name' => __('landing.events.invite.sample_name'),
        'age' => 7,
        'labels' => [
            'fileName' => 'invitacion-'.\Illuminate\Support\Str::slug($parkName),
            'nameFallback' => __('landing.events.invite.name_fallback'),
            'dateFallback' => __('landing.events.invite.date_fallback'),
            'shareText' => __('landing.events.invite.share_text', ['park' => $parkName]),
        ],
    ];
@endphp

{{-- El pack se identifica por su ID ÚNICO (#194).
     ⚠️ El color del bloque llega YA COMPUESTO por pack (`DECISIONES #139`): antes había un ternario
     que preguntaba «¿el acento es kids?» y escribía a mano seis tokens, así que solo sabía pintar dos
     zonas. Ahora es un mapa `id → estilo`, y `--bda` es un alias de `--zone-1` en el CSS. --}}
<div class="bd-page"
     x-data="{ pack: '{{ $firstPack->id }}', packStyles: @js($packages->mapWithKeys(fn ($p) => [(string) $p->id => $packStyle($p)])), minq: @js($packages->mapWithKeys(fn ($p) => [(string) $p->id => (int) $p->min_qty])) }"
     :style="packStyles[pack]">

    {{-- ============ 01 · CUMPLEAÑOS ============ --}}
    <section class="wrap bd-sec1" id="events">
        <div class="bd-band">
            <span class="bd-shape" style="width:42px;height:42px;top:32px;right:60px;border-radius:9px;transform:rotate(18deg)"></span>
            <span class="bd-shape" style="width:22px;height:22px;top:110px;right:200px;border-radius:5px;transform:rotate(-14deg)"></span>
            <span class="bd-shape" style="width:30px;height:30px;bottom:56px;left:240px;border-radius:6px;transform:rotate(-32deg)"></span>
            <span class="bd-shape" style="width:16px;height:16px;top:220px;left:90px;border-radius:4px;transform:rotate(40deg)"></span>

            <div class="bd-band__head">
                <h{{ $level }} class="bd-band__title">{{ __('landing.events.title') }}</h{{ $level }}>

                @if ($packages->count() > 1)
                    <div class="bd-tabs" role="tablist" aria-label="{{ __('landing.events.choose') }}">
                        @foreach ($packages as $p)
                            <button type="button" class="bd-tab" data-tap role="tab"
                                    :class="pack === '{{ $p->id }}' && 'is-active'"
                                    :aria-selected="pack === '{{ $p->id }}'"
                                    @click="pack = '{{ $p->id }}'">{{ $p->tr('name') }}</button>
                        @endforeach
                    </div>
                @endif

                @foreach ($packages as $p)
                    <p class="bd-band__body" x-show="pack === '{{ $p->id }}'" x-cloak>{{ $p->tr('description') }}</p>
                @endforeach
            </div>

            <div class="bd-grid">
                {{-- Polaroid con la foto de la zona cumpleaños --}}
                <div class="bd-pol">
                    <span class="bd-pol__tape"></span>
                    <div class="bd-pol__frame">
                        @if ($cumpleImage)
                            <img src="{{ asset($cumpleImage) }}" alt="{{ __('landing.events.eyebrow') }} · {{ $parkName }}" loading="lazy">
                        @endif
                    </div>
                    <span class="bd-pol__cap">{{ __('landing.events.bd_photo_cap') }}</span>
                    <span class="bd-pol__sticker">{{ __('landing.events.bd_sticker') }}</span>
                    <div class="bd-pol__ticket">
                        <span class="lbl">{{ __('landing.events.bd_ticket_label') }}</span>
                        <span class="num" x-text="minq[pack] + '+'"></span>
                        <span class="sub">{{ __('landing.events.bd_ticket_sub') }}</span>
                    </div>
                </div>

                {{-- Pack card + (en landing) enlace sutil a la invitación, justo debajo de la card. --}}
                <div class="bd-pack-col">
                <div class="bd-pack">
                    @foreach ($packages as $p)
                        <div x-show="pack === '{{ $p->id }}'" x-cloak>
                            <div class="bd-pack__head">
                                <span class="bd-pack__label">{{ __('landing.events.included') }}</span>
                                <span class="bd-pack__price">
                                    {{-- Sin «Desde»: el chip de suplemento (debajo) ya explica el recargo (#267). --}}
                                    <span class="val">{{ $p->euros() }},{{ $p->cents() }}€</span>
                                    <span class="per">{{ $p->tr('period_label') }}</span>
                                </span>
                            </div>
                            {{-- Suplemento de tarifa especial (#267), «solo el chip»: misma presentación que
                                 en entradas, SIN tocar la línea de precio de arriba («Desde … por niño»). --}}
                            <x-site.special-rate-chips :product="$p" />
                            <ul class="bd-pack__list">
                                @foreach ($p->tr('features') ?? [] as $f)
                                    <li>
                                        {{-- ⚠️ CUADRADO. El check pasó a la rejilla 24 del set (`#257`) y una talla
                                             12×10 sobre un lienzo cuadrado ya no encoge: DEFORMA. --}}
                                        <span class="bd-check"><x-icons.check :width="12" :height="12" /></span>
                                        <span><span class="bd-feat__t">{{ $f }}</span></span>
                                    </li>
                                @endforeach
                            </ul>
                            {{-- Complementos REALES del pack (#194): incluidos/gratis/«ver más»/opciones a elegir.
                                 Wrapper propio (solo separador) para NO heredar los selectores .bd-ext ul/li
                                 del mockup, que pisarían los chips de `addons-mini`. --}}
                            <div class="bd-pack__addons">
                                <x-site.product-addons :product="$p" :is-pack="true" />
                            </div>
                            <p class="bd-pack__note">{!! __('landing.events.reserve_terms_rich', ['min' => $p->min_qty, 'max' => $p->max_qty, 'deposit' => intdiv((int) $p->deposit_value, 100)]) !!}</p>
                            <button type="button" class="bd-pack__cta"
                                    @click="$store.purchase.openWith({ type: 'packs' })">
                                {{ __('landing.events.cta') }}
                                <x-icons.arrow-right :width="15" :height="15" />
                            </button>
                        </div>
                    @endforeach
                </div>
                @unless ($showInvite)
                    {{-- En la landing, JUSTO DEBAJO de la card del cumpleaños: pregunta + enlace
                         sutil a la tarjeta de invitación de /cumpleanos (no se satura la home con
                         el editor completo). En /cumpleanos no va (ahí está el editor). --}}
                    <a class="bd-invite-cta" data-tap href="{{ route('cumpleanos') }}#tarjeta-invitacion">
                        {{ __('landing.events.invite_link') }}
                        <x-icons.arrow-right :width="15" :height="15" />
                    </a>
                @endunless
                </div>
            </div>
        </div>
    </section>

    {{-- ============ 02 · PROCESO ============ --}}
    <section class="wrap bd-sec3" x-data="birthdayProcess(@js($processSteps), {{ $processDeposit }})" x-init="init()">
        <div class="bd-sec3__top">
            <div>
                <h2 class="bd-sec3__title">{{ __('landing.events.process_title') }}</h2>
            </div>
        </div>

        <div class="bd-proc">
            <span class="bd-proc__num" aria-hidden="true" x-text="active + 1"></span>
            <div class="bd-proc__body">
                <span class="bd-proc__kicker" x-text="'{{ __('landing.events.process_step') }} ' + (active + 1) + ' {{ __('landing.events.process_of') }} ' + steps.length"></span>
                <h3 class="bd-proc__t" x-text="steps[active].t"></h3>
                <p class="bd-proc__s" x-text="steps[active].s"></p>
            </div>
            <div class="bd-proc__arrows">
                <button type="button" class="bd-proc__arrow" @click="go(active - 1)" aria-label="{{ __('landing.events.process_prev') }}">
                    <x-icons.arrow-left :width="16" :height="16" />
                </button>
                <button type="button" class="bd-proc__arrow" @click="go(active + 1)" aria-label="{{ __('landing.events.process_next') }}">
                    <x-icons.arrow-right :width="16" :height="16" />
                </button>
            </div>
        </div>

        <div class="bd-proc__rail" role="tablist" aria-label="{{ __('landing.events.process_eyebrow') }}">
            <template x-for="(s, i) in steps" :key="i">
                <span style="display:contents">
                    <button type="button" class="bd-proc__stop"
                            :class="{ 'is-active': i === active, 'is-done': i < active, 'bd-proc__stop--pay': s.pay }"
                            role="tab" :aria-selected="i === active" @click="go(i)">
                        <span class="bd-proc__cube">
                            <template x-if="i < active"><x-icons.check :width="13" :height="13" /></template>
                            <template x-if="i >= active && s.pay"><span x-text="deposit + '€'"></span></template>
                            <template x-if="i >= active && !s.pay"><span x-text="i + 1"></span></template>
                        </span>
                        <span class="bd-proc__lbl" x-text="s.k"></span>
                    </button>
                    <span class="bd-proc__seg" :class="i < active && 'is-filled'" x-show="i < steps.length - 1"></span>
                </span>
            </template>
        </div>
    </section>

    {{-- ============ 03 · INVITACIÓN ============ --}}
    @if ($showInvite)
        <section class="wrap bd-sec2" id="tarjeta-invitacion" x-data="birthdayInvite(@js($inviteCfg))">
            <div class="bd-sec2__head">
                <h2 class="bd-sec2__title">{{ __('landing.events.invite.title') }}</h2>
                <p class="bd-sec2__intro">{{ __('landing.events.invite.intro') }}</p>
            </div>

            <div class="bd-inv">
                <div class="bd-editor">
                    <span class="bd-editor__title">{{ __('landing.events.invite.editor_title') }}</span>
                    <div class="bd-fields">
                        <div class="bd-field">
                            <label for="bd-f-name">{{ __('landing.events.invite.name_label') }}</label>
                            <input id="bd-f-name" type="text" maxlength="14" x-model="name" placeholder="{{ __('landing.events.invite.name_placeholder') }}">
                        </div>
                        <div class="bd-field">
                            <label for="bd-f-age">{{ __('landing.events.invite.age_label') }}</label>
                            <input id="bd-f-age" type="number" min="1" max="99" inputmode="numeric" x-model="age">
                        </div>
                        <div class="bd-field">
                            <label for="bd-f-date">{{ __('landing.events.invite.date_label') }}</label>
                            <input id="bd-f-date" type="date" x-model="date">
                        </div>
                        <div class="bd-field">
                            <label for="bd-f-time">{{ __('landing.events.invite.time_label') }}</label>
                            <input id="bd-f-time" type="time" x-model="time">
                        </div>
                    </div>
                    <div class="bd-field bd-field--full">
                        <label>{{ __('landing.events.invite.color_label') }}</label>
                        <div class="bd-swatches">
                            {{-- ⚠️ UNA por zona con pack, no dos escritas a mano con las palabras
                                 «Jump» y «Kids» dentro (`DECISIONES #139`). El punto de color toma
                                 el `--zone-1` que la propia paleta pinta en línea. --}}
                            @foreach ($invitePalettes as $palette)
                                <button type="button" class="bd-swatch" data-tap
                                        style="{{ $palette['style'] }}"
                                        :class="invZone === @js($palette['key']) && 'is-active'"
                                        @click="invZone = @js($palette['key'])"><span class="dot"></span>{{ $palette['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="bd-editor__actions">
                        <button type="button" class="bd-btn bd-btn--solid" @click="download()" :disabled="busy">
                            <x-icons.download :width="14" :height="14" />
                            {{ __('landing.events.invite.download') }}
                        </button>
                        <button type="button" class="bd-btn bd-btn--ghost" @click="share()" :disabled="busy">
                            <x-icons.share :width="14" :height="14" />
                            {{ __('landing.events.invite.share') }}
                        </button>
                    </div>
                    <p class="bd-editor__hint">{{ __('landing.events.invite.hint') }}</p>
                </div>

                {{-- ⚠️ La paleta se pinta en el ESCENARIO y las formas la heredan: eran tres ternarios
                     que nombraban las cuatro variables de las dos zonas del primer cliente. --}}
                <div class="bd-stage" :style="invStyles[invZone]">
                    <span class="bd-stage__shape" style="width:34px;height:34px;top:40px;left:48px;border-radius:8px;transform:rotate(18deg)"></span>
                    <span class="bd-stage__shape" style="width:18px;height:18px;bottom:60px;right:70px;border-radius:5px;transform:rotate(-20deg);background:var(--zone-2)"></span>
                    <span class="bd-stage__shape" style="width:24px;height:24px;top:90px;right:140px;border-radius:6px;transform:rotate(35deg);background:var(--fg)"></span>

                    {{-- ⚠️ La tarjeta repite la paleta EN LÍNEA a propósito, aunque la heredaría del
                         escenario: `_render()` la captura con html2canvas y resuelve `--inv`/`--inv2`
                         sobre ella. Tenerla aquí deja el color a un salto de distancia y no a merced
                         de qué ancestro sobreviva a un clon. --}}
                    <div class="bd-card" x-ref="card" :style="invStyles[invZone]">
                        <div class="bd-card__bunting" aria-hidden="true">
                            @for ($i = 0; $i < 11; $i++)<span class="flag"></span>@endfor
                        </div>
                        <span class="bd-card__conf bd-card__conf--1" aria-hidden="true"></span>
                        <span class="bd-card__conf bd-card__conf--2" aria-hidden="true"></span>
                        <span class="bd-card__conf bd-card__conf--3" aria-hidden="true"></span>
                        <span class="bd-card__conf bd-card__conf--4" aria-hidden="true"></span>
                        <div class="bd-card__star">
                            {{-- Estrella como SVG (no clip-path): html2canvas captura el SVG pero
                                 NO el clip-path → así el PNG descargado es fiel a la vista (#231 p2). --}}
                            <svg class="bd-card__star-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polygon points="50,0 59,12 72,5 75,19 90,18 86,32 100,38 89,50 100,62 86,68 90,82 75,81 72,95 59,88 50,100 41,88 28,95 25,81 10,82 14,68 0,62 11,50 0,38 14,32 10,18 25,19 28,5 41,12" /></svg>
                            <span class="num" x-text="displayAge ?? '·'">7</span>
                            <span class="yrs">{{ __('landing.events.invite.years') }}</span>
                        </div>
                        <div class="bd-card__inner">
                            <div class="bd-card__top">
                                <span class="bd-card__brand">{{ $parkName }}<span class="dot">.</span></span>
                                <span class="bd-card__eyebrow">{{ __('landing.events.invite.card_eyebrow') }}</span>
                            </div>
                            <p class="bd-card__msg">{{ __('landing.events.invite.card_msg') }}</p>
                            <h3 class="bd-card__name">
                                <template x-for="(ch, i) in displayName.split('')" :key="i">
                                    <span :class="ch === ' ' ? 'sp' : 'ltr ltr-' + (i % 4)" :style="`transition-delay:${i * 28}ms`" x-text="ch === ' ' ? ' ' : ch"></span>
                                </template>
                            </h3>
                            <div class="bd-card__details">
                                <div class="bd-card__row">
                                    {{-- ⚠️ Los tres de esta tarjeta se CAPTURAN con html2canvas al
                                         descargar la invitación (`birthdayInvite._render`), así que
                                         siguen siendo SVG en línea: el componente emite lo mismo. --}}
                                    <span class="ico"><x-icons.calendar :width="12" :height="12" /></span>
                                    <span class="lbl">{{ __('landing.events.invite.when') }}</span><span class="val" x-text="displayDate">—</span>
                                </div>
                                <div class="bd-card__row">
                                    <span class="ico"><x-icons.clock :width="12" :height="12" /></span>
                                    <span class="lbl">{{ __('landing.events.invite.time') }}</span><span class="val" x-text="displayTime">17:00</span>
                                </div>
                                <div class="bd-card__row">
                                    <span class="ico"><x-icons.pin :width="12" :height="12" /></span>
                                    <span class="lbl">{{ __('landing.events.invite.where') }}</span><span class="val">{{ $parkName }}@if (! empty($site['address1'])) · {{ $site['address1'] }}@endif</span>
                                </div>
                            </div>
                            <p class="bd-card__ps">{{ __('landing.events.invite.card_ps') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
