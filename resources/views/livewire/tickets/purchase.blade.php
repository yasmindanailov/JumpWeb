@php use Illuminate\Support\Carbon; use Illuminate\Support\Str; @endphp
<div class="purchase"
     {{-- Sidebar v2: refleja el «modo» del flujo en el panel (`.sidecart__panel`) reaccionando a
          `$wire.step` (propiedad pública reactiva). Sin viaje al servidor; cubre también la carga
          inicial (cesta en sesión → paso 4). El mapa paso→modo es la fuente única del componente. --}}
     x-data="{ stepMode: @js($stepModeMap), identifyStep: @js($identifyStep) }"
     x-effect="$store.purchase.setMode(stepMode[$wire.step] ?? 'result'); $store.purchase.identifying = ($wire.step === identifyStep)">
    {{-- Velo de carga (nivel 2): cualquier acción que recargue el panel. Ver docs/UI-SPINNER.md. --}}
    <x-ui.loading-overlay wire:loading.delay
        wire:target="selectType, selectDate, selectTime, prevMonth, nextMonth, back, goToTime, addToCart, addAnother, removeLine, goToCart, checkout, confirmReservation" />

    {{-- Stepper detallado (Sidebar v2): banda STICKY (fuera del scroll), pegada bajo la sección de
         «Mi cuenta» y con fondo/borde propios. Solo en el flujo (pasos 2-3); el VM (`bookingProgress`)
         se calcula en el componente. La 3.ª fase es «Extras» (entrada) o «Datos» (pack), según el tipo.
         El botón «Volver» y la línea de contexto reemplazan al `wiz__back`/`wiz__recap` de los pasos 2/3. --}}
    @if (! $this->showPausedNotice() && $bookingProgress)
        <div class="bk-progress">
            <div class="bk-progress__top">
                <button type="button" class="bk-back" wire:click="back">
                    <x-icons.arrow-left />
                    <span>{{ __('tickets.back') }}</span>
                </button>
                <span class="bk-step-count">{{ __('tickets.step_count', ['n' => $bookingProgress['active'], 'total' => $bookingProgress['total']]) }}</span>
            </div>
            <div class="bk-seg" aria-hidden="true">
                @foreach ($bookingProgress['steps'] as $segment)
                    <span class="bk-seg__item is-{{ $segment['state'] }}"><span class="bk-seg__bar"></span><span class="bk-seg__label">{{ $segment['label'] }}</span></span>
                @endforeach
            </div>
            @if ($bookingProgress['context'] !== '')
                <div class="bk-context"><span class="jj-block" aria-hidden="true"></span><span>{{ $bookingProgress['context'] }}</span></div>
            @endif
        </div>
    @endif

    {{-- Sidebar v2: TODO el contenido de pasos va en una zona scrollable; el footer dinámico
         (`.bk-foot`, más abajo) queda FUERA del scroll, anclado al fondo del panel (flex column). --}}
    <div class="purchase__scroll">

    {{-- Reservas en pausa (#218, item 3): en los pasos de RESERVA, el sidecart muestra SOLO el aviso
         de mantenimiento + los canales de contacto (teléfono / WhatsApp), en lugar del flujo de
         compra. Los pasos de RESULTADO de pago y la verificación de email siguen rindiendo normales
         (acciones ya iniciadas). El server-guard de `proceed`/`confirmReservation` es la red de
         seguridad detrás de esta UI. --}}
    @if ($this->showPausedNotice())
        @php($maint = $this->pausedContact())
        <div class="purchase__maint">
            <h3 class="wiz__title">{{ $this->pausedTitle() }}</h3>
            <p class="purchase__maint-body">{{ $this->pausedMessage() }}</p>
            <div class="purchase__maint-ctas">
                @if ($maint['phone_tel'] !== '')
                    <a href="tel:{{ $maint['phone_tel'] }}" class="btn btn--zone btn--lg">{{ __('tickets.paused.call', ['phone' => $maint['phone']]) }}</a>
                @endif
                @if ($maint['whatsapp'] !== '')
                    <a href="https://wa.me/{{ $maint['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn--lg">{{ __('tickets.paused.whatsapp') }}</a>
                @endif
                @if ($maint['phone_tel'] === '' && $maint['whatsapp'] === '')
                    <a href="{{ route('contacto') }}" class="btn btn--lg">{{ __('tickets.paused.contact') }}</a>
                @endif
            </div>
        </div>
    @else

    {{-- Paso 1 — Catálogo en secciones-acordeón POR TIPO: «Entradas» (entry) y «Servicios» (pack),
         lista plana (los nombres ya distinguen la zona; la zona es operativa, no categoría). Abrir/
         cerrar y buscar son CLIENT-SIDE (Alpine), sin viaje al servidor. El buscador es PROGRESIVO:
         solo aparece si el catálogo es grande (`catalogSearchEnabled`). El deep-link de la página de
         cumpleaños abre y hace scroll a «Servicios» (evento `catalog-open-services`). Toda la lógica
         de datos va en el componente (blade fino: este blade no admite `@php` anidados). --}}
    @if ($step === 1)
        <div class="catalog-acc"
             x-data="{
                 open: { entries: true, services: true },
                 q: '',
                 index: @js($catalogSearchIndex),
                 norm(s) { return (s || '').toString().toLowerCase().trim(); },
                 itemHit(s) { return ! this.q || (s || '').includes(this.norm(this.q)); },
                 secHit(k) { return ! this.q || (this.index[k] || []).some(s => s.includes(this.norm(this.q))); },
                 isOpen(k) { return true; }, /* P6: las secciones del catálogo están SIEMPRE abiertas (no plegables) */
                 anyHit() { return ! this.q || Object.keys(this.index).some(k => this.secHit(k)); },
             }"
             x-on:catalog-open-services.window="open.services = true; $nextTick(() => $refs.services && $refs.services.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
             x-on:catalog-open-entries-zone.window="open.entries = true; $nextTick(() => { const el = ($event.detail?.slug && $refs['zone-' + $event.detail.slug]) || $refs.entries; el && el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })">

            @if ($catalogSearchEnabled)
                <div class="catalog-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line></svg>
                    <input type="search" x-model="q" class="catalog-search__input"
                           placeholder="{{ __('tickets.catalog_search') }}" aria-label="{{ __('tickets.catalog_search') }}">
                </div>
            @endif

            @foreach ($catalog as $section)
                @continue (empty($section['items']))
                <section class="catalog-acc__sec" x-ref="{{ $section['key'] }}" x-show="secHit('{{ $section['key'] }}')" aria-labelledby="catalog-title-{{ $section['key'] }}">
                    {{-- P6: cabecera de sección NO plegable (las secciones están siempre abiertas). --}}
                    <div class="catalog-acc__head">
                        <span class="catalog-acc__icon" aria-hidden="true">
                            @if ($section['key'] === 'entries')
                                <x-icons.ic-e5 :width="24" :height="15" />
                            @else
<x-icons.ic-b1 :size="18" />
                            @endif
                        </span>
                        <span class="catalog-acc__title" id="catalog-title-{{ $section['key'] }}">{{ __('tickets.section_'.$section['key']) }}</span>
                        <span class="catalog-acc__count">{{ count($section['items']) }}</span>
                    </div>
                    <div class="catalog-acc__body" :class="isOpen('{{ $section['key'] }}') ? 'is-open' : ''" id="catalog-sec-{{ $section['key'] }}">
                        <div class="catalog-acc__body-inner">
                            <div class="catalog">
                                @foreach ($section['items'] as $item)
                                    {{-- #222: icono de «entrada» (motivo ticket), badge editable (panel) y
                                         resaltado de las destacadas (`featured`). data-search alimenta el filtro. --}}
                                    <button type="button"
                                            class="catalog__item @if ($item['featured']) catalog__item--feat @endif"
                                            wire:click="selectType({{ $item['id'] }})"
                                            data-search="{{ $item['search'] }}"
                                            @if (! empty($item['zone_anchor'])) x-ref="zone-{{ $item['zone_anchor'] }}" @endif
                                            x-show="itemHit($el.dataset.search)">
                                        <span class="catalog__tk">@if ($item['is_pack'])<x-icons.ic-b1 :size="26" />@else<x-icons.ticket-tear-off :width="38" :height="24" />@endif</span>
                                        <span class="catalog__info">
                                            <span class="catalog__name">{{ $item['name'] }}@if ($item['badge'])<span class="catalog__badge">{{ $item['badge'] }}</span>@endif</span>
                                            @if ($item['features'] !== '')
                                                <span class="catalog__feat">{{ $item['features'] }}</span>
                                            @endif
                                        </span>
                                        {{-- Columna de precio: el precio y, DEBAJO, el anuncio de señal del producto
                                             (#225 F2 → reubicado bajo el precio, no bajo la descripción). --}}
                                        @if ($item['from'] !== null || $item['deposit_label'] !== '')
                                            <span class="catalog__pricecol">
                                                @if ($item['from'] !== null)
                                                    <span class="catalog__price"><span class="price__from">{{ __('tickets.from') }}</span>{{ number_format($item['from'] / 100, 2, ',', '.') }} €@if ($item['is_pack'])<span class="catalog__per"> {{ $item['period_label'] ?: __('tickets.per_child') }}</span>@endif</span>
                                                @endif
                                                @if ($item['deposit_label'] !== '')
                                                    <span class="catalog__deposit">{{ __('tickets.deposit_catalog', ['amount' => $item['deposit_label']]) }}</span>
                                                @endif
                                            </span>
                                        @endif
                                        {{-- Flecha CTA (Sidebar v2): señal visual de «entra a reservar». La card ENTERA sigue
                                             siendo el botón (objetivo grande); la flecha solo aporta affordance y anima al hover. --}}
                                        <span class="catalog__go" aria-hidden="true">
                                            <x-icons.arrow-right />{{-- Lote 12: flecha canónica (la dimensiona `.catalog__go svg`); se escapó del Lote 2. --}}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach

            @unless ($catalogHasItems)
                <p class="purchase__empty">{{ __('tickets.no_dates') }}</p>
            @endunless

            @if ($catalogSearchEnabled)
                <p class="purchase__empty catalog-acc__none" x-show="! anyHit()" x-cloak>{{ __('tickets.catalog_search_none') }}</p>
            @endif
        </div>
    @endif

    {{-- Paso 2 — Día (calendario coloreado por tarifa) --}}
    @if ($step === 2)
        <h3 class="wiz__title">{{ __('tickets.step_date') }}</h3>
        @php($hasSelectable = collect($weeks)->flatten(1)->contains(fn ($c) => $c['selectable']))
        <div class="cal">
            <div class="cal__head">
                <button type="button" class="cal__nav" wire:click="prevMonth" @disabled(! $canPrev) aria-label="{{ __('tickets.prev_month') }}">&lsaquo;</button>
                <span class="cal__month">{{ $monthLabel }}</span>
                <button type="button" class="cal__nav" wire:click="nextMonth" @disabled(! $canNext) aria-label="{{ __('tickets.next_month') }}">&rsaquo;</button>
            </div>
            <div class="cal__grid cal__grid--head">
                @foreach ($weekdayHeaders as $wd)<span class="cal__wd">{{ $wd }}</span>@endforeach
            </div>
            @foreach ($weeks as $week)
                <div class="cal__grid">
                    @foreach ($week as $cell)
                        @if ($cell['selectable'])
                            <button type="button" wire:click="selectDate('{{ $cell['date'] }}')"
                                class="cal__day cal__day--{{ $cell['type'] }} {{ $cell['in_month'] ? '' : 'is-out' }} @if ($cell['date'] === $date) is-selected @endif"
                                @if ($cell['date'] === $date) aria-current="date" @endif>
                                <span class="cal__day-num">{{ $cell['day'] }}</span>
                                @if ($cell['price_cents'] !== null)
                                    <span class="cal__day-price">{{ number_format($cell['price_cents'] / 100, 0, ',', '.') }}€</span>
                                @endif
                            </button>
                        @else
                            <span class="cal__day is-disabled {{ $cell['in_month'] ? '' : 'is-out' }}">{{ $cell['day'] }}</span>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="cal__legend">
            <span class="cal__legend-item"><i class="cal__dot cal__dot--normal"></i> {{ __('tickets.legend_normal') }}</span>
            <span class="cal__legend-item"><i class="cal__dot cal__dot--special"></i> {{ __('tickets.legend_special') }}</span>
        </div>
        @unless ($hasSelectable)<p class="purchase__empty">{{ __('tickets.no_dates') }}</p>@endunless
    @endif

    {{-- Paso 3 — Hora de entrada + cantidad (topada al aforo, precio del día) --}}
    @if ($step === 3)
        <h3 class="wiz__title">{{ __('tickets.step_time') }}</h3>
        <div class="purchase__chips">
            @foreach ($times as $t)
                <button type="button" wire:click="selectTime('{{ $t }}')" class="purchase__chip {{ $time === $t ? 'is-active' : '' }}">{{ Str::substr($t, 0, 5) }}</button>
            @endforeach
        </div>

        @if ($time)
            <div class="qtybox">
                <div class="qtybox__row">
                    <span class="qtybox__label">{{ $selectedIsPack ? __('tickets.guests') : __('tickets.quantity') }}</span>
                    <div class="entry__stepper">
                        <button type="button" wire:click="dec" @disabled($qty <= ($selectedIsPack ? $minQty : 0))>&minus;</button>
                        <span class="entry__qty">{{ $qty }}</span>
                        <button type="button" wire:click="inc" @disabled($qty >= $maxQty)>+</button>
                    </div>
                </div>
                <p class="qtybox__avail">
                    @if ($maxQty > 0){{ $selectedIsPack ? __('tickets.guests_left', ['count' => $maxQty]) : __('tickets.seats_left', ['count' => $maxQty]) }}@else{{ __('tickets.sold_out') }}@endif
                    @if ($dayPriceCents !== null)<span class="qtybox__price"> · {{ number_format($dayPriceCents / 100, 2, ',', '.') }} €@if ($selectedIsPack) {{ $selectedPeriodLabel ?: __('tickets.per_child') }}@endif</span>@endif
                </p>
            </div>

            {{-- Datos del evento del pack: campos configurables por pack (#86), renderizados dinámicamente --}}
            @if ($selectedIsPack && count($eventFields))
                <div class="eventfields">
                    @foreach ($eventFields as $field)
                        {{-- Validar-al-pulsar (#UX): el CTA no se deshabilita por estos campos; al pulsar
                             «Añadir», `addToCart` resalta los obligatorios que falten (`is-invalid`) y los
                             nombra en el resumen. wire:model normal (la sincronía la fuerza el propio clic). --}}
                        <label class="eventfields__field @error('eventData.'.$field['key']) is-invalid @enderror" wire:key="ef-{{ $field['key'] }}">
                            <span class="eventfields__label">{{ $selectedType->eventFieldLabel($field) }}@if ($field['required']) <span class="eventfields__req" aria-hidden="true">*</span>@endif</span>
                            @if ($field['type'] === 'textarea')
                                <textarea wire:model="eventData.{{ $field['key'] }}" rows="2" @required($field['required'])></textarea>
                            @else
                                <input type="{{ $field['type'] === 'number' ? 'number' : 'text' }}" @if ($field['type'] === 'number') min="0" @endif wire:model="eventData.{{ $field['key'] }}" @required($field['required'])>
                            @endif
                            @error('eventData.'.$field['key'])<span class="form__error">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                </div>
            @endif

            {{-- Complementos del producto seleccionado (#87): incluidos, opcionales y grupos de elección --}}
            @if (count($addonModel['groups']) || count($addonModel['singles']))
                <div class="addons">
                    <p class="addons__intro">{{ __('tickets.complements_intro') }}</p>

                    {{-- Grupos de elección EXCLUYENTE (radio): p. ej. Menú 1 ⊻ Menú 2 --}}
                    @foreach ($addonModel['groups'] as $group)
                        <fieldset class="addons__group" wire:key="addon-group-{{ $group['key'] }}">
                            <legend class="addons__group-label">{{ $group['label'] }}</legend>
                            @foreach ($group['options'] as $opt)
                                <div @class(['addons__row', 'addons__row--choice', 'is-selected' => $opt['selected'], 'is-disabled' => ! $opt['available']]) wire:key="addon-opt-{{ $opt['id'] }}" x-data="{ info: false }">
                                    <label class="addons__choice">
                                        <input type="radio" class="addons__radio" name="addon-group-{{ $group['key'] }}" @checked($opt['selected']) @disabled(! $opt['available']) wire:click="selectAddonOption('{{ $group['key'] }}', {{ $opt['id'] }})">
                                        <span class="addons__info">
                                            <span class="addons__name">{{ $opt['name'] }}@if ($opt['badge'])<span class="addons__badge addons__badge--{{ $opt['badge'] }}">{{ __('tickets.addon_badge_' . $opt['badge']) }}</span>@endif</span>
                                            <span class="addons__price">{{ $opt['note'] }}@if ($opt['selected'] && $opt['charged'] > 0) <span class="addons__charged">+{{ number_format($opt['charged'] / 100, 2, ',', '.') }} €</span>@endif</span>
                                            @if (! $opt['available'] && ! empty($opt['requires_name']))<span class="addons__requires">{{ __('tickets.addon_requires', ['name' => $opt['requires_name']]) }}</span>@endif
                                        </span>
                                    </label>
                                    @if (count($opt['features']))
                                        <button type="button" class="addons__moreinfo" @click="info = !info" :aria-expanded="info">{{ __('tickets.addon_more_info') }} <span aria-hidden="true" x-text="info ? '−' : '+'"></span></button>
                                        <ul class="addons__features" x-show="info" x-cloak>
                                            @foreach ($opt['features'] as $f)<li>{{ $f }}</li>@endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach

                    {{-- Complementos sueltos: incluidos (tarta), obligatorios u opcionales --}}
                    @foreach ($addonModel['singles'] as $opt)
                        <div @class(['addons__row', 'is-disabled' => ! $opt['available']]) wire:key="addon-{{ $opt['id'] }}" x-data="{ info: false }">
                            <span class="addons__info">
                                <span class="addons__name">{{ $opt['name'] }}@if ($opt['badge'])<span class="addons__badge addons__badge--{{ $opt['badge'] }}">{{ __('tickets.addon_badge_' . $opt['badge']) }}</span>@endif</span>
                                <span class="addons__price">{{ $opt['note'] }}</span>
                                @if (! $opt['available'] && ! empty($opt['requires_name']))<span class="addons__requires">{{ __('tickets.addon_requires', ['name' => $opt['requires_name']]) }}</span>@endif
                                @if (count($opt['features']))
                                    <button type="button" class="addons__moreinfo" @click="info = !info" :aria-expanded="info">{{ __('tickets.addon_more_info') }} <span aria-hidden="true" x-text="info ? '−' : '+'"></span></button>
                                @endif
                            </span>
                            @if (! $opt['available'])
                                {{-- Dependiente «requiere»: bloqueado hasta elegir el requisito. Control inerte. --}}
                                <div class="entry__stepper" aria-hidden="true">
                                    <button type="button" disabled>&minus;</button>
                                    <span class="entry__qty">0</span>
                                    <button type="button" disabled>+</button>
                                </div>
                            @elseif ($opt['can_toggle'])
                                <label class="addons__perguest-toggle">
                                    <input type="checkbox" class="addons__check" wire:click="toggleAddon({{ $opt['id'] }})" @checked($opt['selected'])>
                                    <span class="addons__perguest">{{ __('tickets.addon_per_guest_add') }}@if ($opt['selected'] && $opt['charged'] > 0) <span class="addons__charged">+{{ number_format($opt['charged'] / 100, 2, ',', '.') }} €</span>@endif</span>
                                </label>
                            @elseif ($opt['per_guest'])
                                <span class="addons__perguest">{{ __('tickets.addon_per_guest_qty', ['count' => $opt['qty']]) }}</span>
                            @else
                                <div class="entry__stepper">
                                    <button type="button" wire:click="decAddon({{ $opt['id'] }})" @disabled(! $opt['can_dec'])>&minus;</button>
                                    <span class="entry__qty">{{ $opt['qty'] }}</span>
                                    <button type="button" wire:click="incAddon({{ $opt['id'] }})" @disabled(! $opt['can_inc'])>+</button>
                                </div>
                            @endif
                            @if (count($opt['features']))
                                <ul class="addons__features addons__features--single" x-show="info" x-cloak>
                                    @foreach ($opt['features'] as $f)<li>{{ $f }}</li>@endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Sidebar v2: el total, la señal y la nota de IVA viajan al footer sticky (`.bk-foot`).
                 Aquí queda solo la validación, junto al formulario. --}}
            @error('selection')<div class="purchase__foot purchase__foot--info"><p class="form__error">{{ $message }}</p></div>@enderror
        @endif
    @endif

    {{-- Paso 4 — Carrito (líneas acumuladas) --}}
    @if ($step === 4)
        <h3 class="wiz__title">{{ __('tickets.cart_title') }}</h3>
        @if ($cartCount === 0)
            <p class="purchase__empty">{{ __('tickets.cart_empty') }}</p>
        @else
            <ul class="cart">
                @foreach ($cartLines as $line)
                    <li class="cart__item" wire:key="line-{{ $line['index'] }}-{{ $line['date'] }}-{{ $line['time'] }}">
                        <div class="cart__head">
                            <span class="cart__when"><x-icons.product :is-pack="$line['is_pack']" />@if ($line['is_pack']){{ __('tickets.guests_count', ['count' => $line['qty']]) }} · {{ $line['name'] }}@else{{ $line['qty'] }}&times; {{ $line['name'] }}@endif</span>
                            <span class="cart__price">{{ number_format($line['subtotal'] / 100, 2, ',', '.') }} €</span>
                            <button type="button" class="cart__remove" wire:click="removeLine({{ $line['index'] }})" aria-label="{{ __('tickets.remove') }}">&times;</button>
                        </div>
                        <div class="cart__lines">
                            @if ($line['date'])<span>{{ Str::ucfirst(Carbon::parse($line['date'])->locale(app()->getLocale())->isoFormat('ddd D MMM')) }} · {{ Str::substr($line['time'], 0, 5) }}</span>@endif
                        </div>
                        @if (! empty($line['event']))
                            <ul class="cart__event">
                                @foreach ($line['event'] as $ev)<li><span class="cart__event-label">{{ $ev['label'] }}:</span> {{ $ev['value'] }}</li>@endforeach
                            </ul>
                        @endif
                        @if (! empty($line['addons']))
                            <ul class="cart__addons">
                                @foreach ($line['addons'] as $ad)<li><span>+ {{ $ad['qty'] }}&times; {{ $ad['name'] }}@if (! empty($ad['free_qty'])) <em class="cart__addon-incl">{{ ($ad['free_qty'] >= $ad['qty']) ? __('tickets.addon_included') : __('tickets.addon_included_partial', ['count' => $ad['free_qty']]) }}</em>@endif</span><span>{{ number_format($ad['subtotal'] / 100, 2, ',', '.') }} €</span></li>@endforeach
                            </ul>
                        @endif
                        {{-- #225 F2: señal del producto (resto en el parque) en su propia card. --}}
                        @if (! empty($line['has_deposit']))
                            <p class="cart__deposit">{{ __('tickets.deposit_card_note', ['deposit' => number_format($line['deposit'] / 100, 2, ',', '.').' €', 'rest' => number_format($line['gate_remainder'] / 100, 2, ',', '.').' €']) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- Sidebar v2: el «Total a pagar ahora» (o «Total») y el CTA «Ir a pagar» se mueven al
                 footer sticky. #225: aquí conservamos el desglose del resto a pagar EN EL PARQUE
                 cuando hay señal (el footer solo muestra lo que se paga ahora). --}}
            {{-- Sidebar v2: «Total a pagar ahora», «En el parque» y la nota de IVA viajan al footer
                 sticky. Aquí quedan la validación, el aviso de carrito listo y «añadir otra visita». --}}
            <div class="purchase__foot purchase__foot--info">
                @error('cart')<p class="form__error">{{ $message }}</p>@enderror
                @if ($confirmed)<div class="purchase__confirm">{{ __('tickets.confirm_next') }}</div>@endif
                <button type="button" class="purchase__add-more" wire:click="addAnother">+ {{ __('tickets.add_another') }}</button>
            </div>
        @endif
    @endif

    {{-- Paso 5 — Identificación (login / registro embebido, #69) --}}
    @if ($step === 5)
        {{-- Volver al carrito: este paso no tiene la barra de progreso (`bk-progress`) con su
             botón «Volver», así que añadimos uno propio (la cuenta/reserva aún no se ha creado). --}}
        <button type="button" class="bk-back purchase__back" wire:click="goToCart">
            <x-icons.arrow-left />
            <span>{{ __('tickets.back_to_cart') }}</span>
        </button>
        <h3 class="wiz__title">{{ __('tickets.identify_title') }}</h3>
        <p class="purchase__note">{{ __('tickets.identify_intro') }}</p>
        <div class="zone-tabs purchase__authtabs">
            <button type="button" class="zone-tab @if ($authMode === 'login') active @endif" wire:click="setAuthMode('login')">{{ __('account.login.cta') }}</button>
            <button type="button" class="zone-tab @if ($authMode === 'register') active @endif" wire:click="setAuthMode('register')">{{ __('account.register.cta') }}</button>
        </div>

        @if ($authMode === 'login')
            <livewire:auth.login :embedded="true" wire:key="purchase-login" />
        @else
            <livewire:auth.register :embedded="true" wire:key="purchase-register" />
        @endif
    @endif

    {{-- Paso 8 — Pago (placeholder de Redsys; va ANTES de crear la reserva firme, #62) --}}
    @if ($step === 8)
        {{-- Volver al carrito (Sidebar v2): el pedido aún NO está creado en este paso (se crea en
             `confirmReservation`), así que retroceder es seguro y no pierde la cesta. --}}
        <button type="button" class="bk-back purchase__back" wire:click="goToCart">
            <x-icons.arrow-left />
            <span>{{ __('tickets.back_to_cart') }}</span>
        </button>
        <h3 class="wiz__title">{{ __('tickets.pay_title') }}</h3>
        <p class="purchase__note">{{ __('tickets.pay_intro') }}</p>

        <ul class="cart cart--summary">
            @foreach ($cartLines as $line)
                <li class="cart__item" wire:key="pay-{{ $line['index'] }}-{{ $line['date'] }}-{{ $line['time'] }}">
                    <div class="cart__head">
                        <span class="cart__when"><x-icons.product :is-pack="$line['is_pack']" />@if ($line['is_pack']){{ __('tickets.guests_count', ['count' => $line['qty']]) }} · {{ $line['name'] }}@else{{ $line['qty'] }}&times; {{ $line['name'] }}@endif</span>
                        <span>{{ number_format($line['subtotal'] / 100, 2, ',', '.') }} €</span>
                    </div>
                    <div class="cart__lines">
                        @if ($line['date'])<span>{{ Str::ucfirst(Carbon::parse($line['date'])->locale(app()->getLocale())->isoFormat('ddd D MMM')) }} · {{ Str::substr($line['time'], 0, 5) }}</span>@endif
                    </div>
                    @if (! empty($line['event']))
                        <ul class="cart__event">
                            @foreach ($line['event'] as $ev)<li><span class="cart__event-label">{{ $ev['label'] }}:</span> {{ $ev['value'] }}</li>@endforeach
                        </ul>
                    @endif
                    @if (! empty($line['addons']))
                        <ul class="cart__addons">
                            @foreach ($line['addons'] as $ad)<li><span>+ {{ $ad['qty'] }}&times; {{ $ad['name'] }}@if (! empty($ad['free_qty'])) <em class="cart__addon-incl">{{ ($ad['free_qty'] >= $ad['qty']) ? __('tickets.addon_included') : __('tickets.addon_included_partial', ['count' => $ad['free_qty']]) }}</em>@endif</span><span>{{ number_format($ad['subtotal'] / 100, 2, ',', '.') }} €</span></li>@endforeach
                        </ul>
                    @endif
                    {{-- #225 F2: la SEÑAL se detalla aquí, en la card del producto que la cobra —no
                         en el agregado, que en cestas mixtas (entrada + pack) confundía. --}}
                    @if (! empty($line['has_deposit']))
                        <p class="cart__deposit">{{ __('tickets.deposit_card_note', ['deposit' => number_format($line['deposit'] / 100, 2, ',', '.').' €', 'rest' => number_format($line['gate_remainder'] / 100, 2, ',', '.').' €']) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>

        {{-- Sidebar v2: el total a pagar ahora, «En el parque» y la nota de IVA viajan al footer sticky.
             #225: la señal por producto va en la card de cada producto (`cart__deposit`). Aquí queda la
             validación y el aviso de pago. --}}
        <div class="purchase__foot purchase__foot--info">
            @error('cart')<p class="form__error">{{ $message }}</p>@enderror
        </div>
    @endif

    {{-- Paso 11 — Verificando pago (vuelta de Redsys sin datos firmados; capa 5.5c, #106).
         El terminal Redsys no incluyó los Ds_* en la redirección; esperamos a la notificación
         on-line (5.5d) para confirmar. Hasta entonces, el cliente ve "verificando" con su nº
         de pedido. La OrderConfirmation por email llegará si el cobro acaba autorizándose. --}}
    @if ($step === 11)
        {{-- Polling cada 5 s (audit #114 G10): en cuanto la notificación on-line confirma
             el pago, la pantalla pasa a paso 6 sin que el cliente tenga que recargar. Si
             la Order caduca antes (raro: notificación nunca llegó), volvemos al paso 1
             con mensaje claro — la pantalla 11 nunca queda colgada indefinidamente. --}}
        <div class="purchase__verifying" role="status" aria-live="polite" wire:key="redsys-verifying-{{ $orderCode }}"
             wire:poll.5s="checkPaymentStatus">
            <h3 class="wiz__title">{{ __('tickets.payment_verifying_title') }}</h3>
            <p class="purchase__note">{{ __('tickets.payment_verifying_intro') }}</p>
            @if ($orderCode)
                <p class="purchase__code">{{ __('tickets.order_code') }}: <strong>{{ $orderCode }}</strong></p>
            @endif
            <p class="purchase__note">{{ __('tickets.payment_verifying_email_note') }}</p>

            {{-- Acción única a ancho completo (coherente con pasos 6/10): "Ver mis reservas". --}}
            <div class="purchase__final-actions">
                <a href="{{ route('account.orders') }}" class="btn btn--zone btn--lg purchase__cta">
                    {{ __('tickets.see_my_orders') }}
                </a>
            </div>
        </div>
    @endif

    {{-- Paso 10 — Pago denegado (vuelta KO de Redsys; capa 5.5c, #104, audit #114 G6+G7).
         Mostramos el motivo concreto si lo conocemos (`Ds_Response` → texto via
         `RedsysResponseCode`); el CTA principal "Reintentar el pago" reusa la misma Order
         pending sin consumir aforo adicional. Si la Order ya caducó, retryPayment vuelve
         al paso 1 con `errors.retry_expired`. --}}
    @if ($step === 10)
        <div class="purchase__failed" role="alert" wire:key="redsys-failed-{{ $orderCode }}">
            <h3 class="wiz__title">{{ __('tickets.payment_failed_title') }}</h3>
            <p class="purchase__note">{{ __('tickets.payment_failed_intro') }}</p>
            @if ($declinedReasonText)
                <p class="purchase__note purchase__note--reason">
                    <strong>{{ __('tickets.payment_failed_reason_label') }}:</strong>
                    {{ $declinedReasonText }}
                </p>
            @endif
            @if ($orderCode)
                <p class="purchase__code">{{ __('tickets.order_code') }}: <strong>{{ $orderCode }}</strong></p>
            @endif
            <p class="purchase__note">{{ __('tickets.payment_failed_retry') }}</p>

            {{-- Jerarquía de CTAs (audit #114 G6):
                 - Principal: "Reintentar el pago" → reusa Order pending + nuevo Payment.
                 - Secundario: "Hacer otra reserva" → crea Order nueva (limpia el contexto).
                 - Terciario: "Escribirnos" → soporte.
                 Mismo patrón visual que el paso 6 (#101). --}}
            <div class="purchase__final-actions">
                <button type="button" class="btn btn--zone btn--lg purchase__cta"
                        wire:click="retryPayment"
                        wire:loading.attr="disabled" wire:target="retryPayment">
                    <span wire:loading.remove.delay wire:target="retryPayment">{{ __('tickets.payment_failed_retry_cta') }}</span>
                    <span class="btn__loading" wire:loading.delay wire:target="retryPayment">
                        <x-ui.spinner size="xs" :decorative="true" /> {{ __('tickets.pay_redirecting') }}
                    </span>
                </button>
                <button type="button" class="btn btn--ghost purchase__cta-secondary" wire:click="addAnother">
                    {{ __('tickets.new_purchase') }}
                </button>
                <a href="{{ route('contacto') }}" class="btn btn--ghost purchase__cta-tertiary">
                    {{ __('tickets.payment_failed_contact') }}
                </a>
            </div>
        </div>
    @endif

    {{-- Paso 9 — Redirigiendo a Redsys (auto-POST a la pasarela; capa 5.5b, #104). --}}
    {{-- target="_top" rompe cualquier contenedor (sidebar es un panel del propio document; --}}
    {{-- el navegador navega la página entera al TPV de Redsys). La tarjeta NO toca este server. --}}
    @if ($step === 9 && ! empty($redsysFormData))
        <div class="purchase__redirecting" role="status" aria-live="polite" wire:key="redsys-redirect">
            <p class="purchase__note">{{ __('tickets.pay_redirecting') }}</p>
            <form id="redsys-form"
                  action="{{ $redsysFormData['gatewayUrl'] }}"
                  method="POST"
                  target="_top"
                  x-data x-init="setTimeout(() => $el.submit(), 80)">
                <input type="hidden" name="Ds_SignatureVersion" value="{{ $redsysFormData['signatureVersion'] }}">
                <input type="hidden" name="Ds_MerchantParameters" value="{{ $redsysFormData['params'] }}">
                <input type="hidden" name="Ds_Signature" value="{{ $redsysFormData['signature'] }}">
                <noscript>
                    <button type="submit" class="btn btn--zone btn--lg purchase__cta">
                        {{ __('tickets.pay_proceed_manual') }}
                    </button>
                </noscript>
            </form>
        </div>
    @endif

    {{-- Paso 6 — Reserva creada (resumen + feedback celebratorio) --}}
    @if ($step === 6)
        <div class="purchase__confirm purchase__done" role="status"
             wire:key="confirm-{{ $orderCode }}"
             x-data x-init="$nextTick(() => $store.purchase.celebrate())">
            <div class="purchase__party" aria-hidden="true"><x-icons.ic-b7 :size="56" /></div>
            <h3 class="wiz__title">{{ __('tickets.reservation_created') }}</h3>

            @if ($confirmation)
                <ul class="cart cart--summary">
                    @foreach ($confirmation['lines'] as $line)
                        <li class="cart__item">
                            <div class="cart__head">
                                <span class="cart__when"><x-icons.product :is-pack="$line['is_pack']" />@if ($line['is_pack']){{ __('tickets.guests_count', ['count' => $line['qty']]) }} · {{ $line['name'] }}@else{{ $line['qty'] }}&times; {{ $line['name'] }}@endif</span>
                                <span>{{ number_format($line['subtotal'] / 100, 2, ',', '.') }} €</span>
                            </div>
                            <div class="cart__lines">
                                @if ($line['date'])<span>{{ Str::ucfirst(Carbon::parse($line['date'])->locale(app()->getLocale())->isoFormat('ddd D MMM')) }} · {{ Str::substr($line['time'], 0, 5) }}</span>@endif
                            </div>
                            @if (! empty($line['event']))
                                <ul class="cart__event">
                                    @foreach ($line['event'] as $ev)<li><span class="cart__event-label">{{ $ev['label'] }}:</span> {{ $ev['value'] }}</li>@endforeach
                                </ul>
                            @endif
                            @if (! empty($line['addons']))
                                <ul class="cart__addons">
                                    @foreach ($line['addons'] as $ad)<li><span>+ {{ $ad['qty'] }}&times; {{ $ad['name'] }}@if (! empty($ad['free_qty'])) <em class="cart__addon-incl">{{ ($ad['free_qty'] >= $ad['qty']) ? __('tickets.addon_included') : __('tickets.addon_included_partial', ['count' => $ad['free_qty']]) }}</em>@endif</span><span>{{ number_format($ad['subtotal'] / 100, 2, ',', '.') }} €</span></li>@endforeach
                                </ul>
                            @endif
                            {{-- #225 F3: la SEÑAL se detalla aquí, en la card del producto que la cobra
                                 (coherente con el sidecart) — el agregado deja de etiquetarse «Señal
                                 pagada», que era falso en cestas mixtas (la entrada se paga entera). --}}
                            @if (! empty($line['has_deposit']))
                                <p class="cart__deposit">{{ __('tickets.deposit_card_note', ['deposit' => number_format($line['deposit'] / 100, 2, ',', '.').' €', 'rest' => number_format($line['gate_remainder'] / 100, 2, ',', '.').' €']) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="purchase__total">
                    <span>{{ __('tickets.total') }}</span>
                    <strong>{{ number_format($confirmation['total'] / 100, 2, ',', '.') }} €</strong>
                </div>
                {{-- #225 F3: split del pago. El agregado se etiqueta NEUTRO «Pagado online» (lo cobrado
                     ahora = señal(es) + productos de pago completo); la señal por-producto se nombra en
                     su card (arriba). Solo si PAGADO y queda algo en el parque (en «verifica tu email»
                     la Order está PENDING → no se muestra). --}}
                @if (($confirmation['status'] ?? null) === \App\Domain\Booking\Models\Order::STATUS_PAID && ($confirmation['pending_at_park'] ?? 0) > 0)
                    <div class="purchase__split">
                        <span>{{ __('tickets.paid_online_confirmed') }}</span>
                        <strong>{{ number_format(($confirmation['online'] ?? 0) / 100, 2, ',', '.') }} €</strong>
                    </div>
                    <div class="purchase__split">
                        <span>{{ __('tickets.pending_at_park') }}</span>
                        <strong>{{ number_format($confirmation['pending_at_park'] / 100, 2, ',', '.') }} €</strong>
                    </div>
                @endif
            @endif

            <p class="purchase__code">{{ __('tickets.order_code') }}: <strong>{{ $orderCode }}</strong></p>
            <p class="purchase__note">{{ __('tickets.email_sent_note') }}</p>
            {{-- El mensaje de pago refleja el estado REAL del pedido (#224). El paso 6 es compartido:
                 se llega PAGADO (vuelta OK de Redsys / notificación) o PENDIENTE (flujo de verificar
                 email). «Pendiente de pago» solo si de verdad está pendiente; si está pagado,
                 confirmamos el pago (antes se mostraba «pendiente» SIEMPRE → incoherente al pagar). --}}
            @if (($confirmation['status'] ?? null) === \App\Domain\Booking\Models\Order::STATUS_PAID)
                <p class="purchase__note">{{ __('tickets.payment_confirmed_note') }}</p>
            @elseif (($confirmation['status'] ?? null) === \App\Domain\Booking\Models\Order::STATUS_PENDING)
                <p class="purchase__note">{{ __('tickets.pending_payment') }}</p>
            @endif

            {{-- Aviso del formulario de reserva (#217): SOLO si algún producto lo pide realmente
                 (`has_guest_form`), no por ser pack — un pack sin formulario no debe prometerlo. --}}
            @if ($confirmation && ! empty($confirmation['has_guest_form']))
                <p class="purchase__note purchase__note--guestform">{{ __('tickets.guest_form_notice') }}</p>
            @endif

            {{-- Registro «del parque» (#216 config): si hay URL de registro externo configurada,
                 invitamos a completar el registro de acceso con un texto + botón editables desde el
                 panel (apartado Registro). Solo aquí, en la pantalla de compra completada. --}}
            @if ($registration)
                <div class="purchase__reginfo">
                    <p class="purchase__reginfo-text">{{ $registration['description'] }}</p>
                    <a href="{{ $registration['url'] }}" target="_blank" rel="noopener" class="btn btn--ghost purchase__reginfo-btn">{{ $registration['label'] }} →</a>
                </div>
            @endif

            {{-- #225 F3: «Ver mis reservas» se retira — ya está SIEMPRE en el sidebar (#221), aquí
                 duplicaba. Queda «Hacer otra reserva» como ÚNICA acción, primaria (criterio de
                 jerarquía: una sola acción → primaria, sin competir con un secundario redundante). --}}
            <div class="purchase__final-actions">
                <button type="button" class="btn btn--zone btn--lg purchase__cta" wire:click="addAnother">{{ __('tickets.new_purchase') }}</button>
            </div>
        </div>
    @endif

    {{-- Paso 7 — Aviso genérico «revisa tu correo». Pay-first: ya NO es parte del flujo de compra
         (el registro inicia sesión y va a pago); queda solo como respuesta genérica del borde
         anti-abuso (p. ej. registro del mismo email rate-limited), sin código y sin auto-avanzar. --}}
    @if ($step === 7)
        <div class="purchase__confirm" role="status">
            <h3 class="wiz__title">{{ __('tickets.verify_title') }}</h3>
            <p class="purchase__note">{{ __('tickets.verify_intro') }}</p>
        </div>
    @endif
    @endif {{-- /reservas en pausa del sidecart (#218) --}}
    </div>{{-- /.purchase__scroll --}}

    {{-- Banda de desglose del pago (solo paso de pago, `splitMode === 'band'`): sticky propia ENCIMA
         del footer, con bordes top+bottom. En el pago el cliente debe ver siempre lo que pagará. --}}
    @if (! $this->showPausedNotice() && $footer && ($footer['splitMode'] ?? null) === 'band' && $footer['split'])
        <div class="bk-paybreakdown" wire:key="bk-pay-breakdown">
            <div class="bk-paybreakdown__row">
                <span class="bk-paybreakdown__l">{{ $footer['split']['nowLabel'] }}</span>
                <span class="bk-paybreakdown__v">{{ $footer['split']['now'] }}</span>
            </div>
            <div class="bk-paybreakdown__row">
                <span class="bk-paybreakdown__l">{{ __('tickets.pay_at_park') }}</span>
                <span class="bk-paybreakdown__v">{{ $footer['split']['park'] }}</span>
            </div>
        </div>
    @endif

    {{-- Footer sticky y dinámico (Sidebar v2): anclado al fondo del panel (fuera del scroll). Su CTA
         cambia según el paso — «Ir al carrito» (catálogo), «Añadir al carrito» (flujo), «Ir a pagar»
         (carrito), «Pagar y confirmar» (pago). Mantiene la estética «Total — CTA»: el desglose de la
         señal NO añade líneas; se consulta con la ⓘ (popover) o, en el pago, en la banda de arriba. --}}
    @if (! $this->showPausedNotice() && $footer)
        <div class="bk-foot" wire:key="bk-foot-{{ $step }}">
            @if ($footer['type'] === 'cart')
                {{-- Catálogo: barra-carrito al estilo del mockup — badge de cantidad + (artículos/total)
                     + «Ir al carrito». Un único botón con toda la info del carrito. --}}
                <button type="button" class="cartbar" wire:click="{{ $footer['action'] }}">
                    <span class="cartbar__count">{{ $footer['count'] }}</span>
                    <span class="cartbar__txt">
                        <span class="cartbar__label">{{ $footer['label'] }}</span>
                        <span class="cartbar__total">{{ $footer['amount'] }}</span>
                    </span>
                    <span class="cartbar__go">{{ $footer['cta'] }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="12" x2="19" y2="12"></line><polyline points="13 6 19 12 13 18"></polyline></svg>
                    </span>
                </button>
            @else
                <div class="bk-foot__row">
                    <span class="bk-foot__total">
                        <span class="bk-foot__l">{{ $footer['label'] }}@if ($footer['split'] && ($footer['splitMode'] ?? null) === 'popover')<span class="bk-foot__info" x-data="{ o: false }" @keydown.escape="o = false">
                            <button type="button" class="bk-foot__info-btn" @click="o = ! o" @click.outside="o = false" :aria-expanded="o ? 'true' : 'false'" aria-label="{{ __('tickets.deposit_info') }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><line x1="12" y1="11" x2="12" y2="16"></line><circle cx="12" cy="8" r="0.6" fill="currentColor"></circle></svg>
                            </button>
                            <span class="bk-foot__pop" x-show="o" x-cloak x-transition.origin.bottom.left>
                                <span class="bk-foot__pop-row"><span>{{ $footer['split']['nowLabel'] }}</span><span>{{ $footer['split']['now'] }}</span></span>
                                <span class="bk-foot__pop-row"><span>{{ __('tickets.pay_at_park') }}</span><span>{{ $footer['split']['park'] }}</span></span>
                            </span>
                        </span>@endif</span>
                        <span class="bk-foot__v">{{ $footer['amount'] }}</span>
                    </span>
                    <button type="button" class="bk-cta" wire:click="{{ $footer['action'] }}" @disabled($footer['disabled'])>
                        <span>{{ $footer['cta'] }}</span>
                        @if ($footer['icon'] === 'card')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2.5"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="12" x2="19" y2="12"></line><polyline points="13 6 19 12 13 18"></polyline></svg>
                        @endif
                    </button>
                </div>
                @if ($footer['note'])
                    <p class="bk-foot__note">{{ $footer['note'] }}</p>
                @endif
            @endif
        </div>
    @endif
</div>
