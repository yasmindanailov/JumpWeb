@props(['zones', 'complements'])

{{-- QUÉ HAY DENTRO — el carrusel de atracciones de la portada real, gobernado por el MISMO `zone` del
     selector único. Conserva `id="rides"` y `.slider[data-zone]` porque `landing.init()` lee ahí la
     primera zona y `applyZoneAccent()` tiñe este contenedor. --}}
<div id="rides" class="zones__juegos">
    @foreach ($zones as $zone)
        <div class="slider" x-ref="slider_{{ $zone->slug }}" data-zone="{{ $zone->slug }}"
             id="rides-{{ $zone->slug }}" role="tabpanel"
             data-color="{{ \App\Domain\Content\Services\ThemeSettings::colorForAccent($zone->color, $zone->accent) }}"
             data-zone-style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
             x-show="zone === @js($zone->slug)" @scroll="updateProgress()" @if (! $loop->first) style="display:none" @endif>
            @foreach ($zone->attractions as $ride)
                <article class="ride-card{{ $ride->is_special ? ' ride-card--special' : '' }}{{ $complements->isPurchasable($ride) ? ' ride-card--sellable' : '' }}">
                    <div class="ride-card__viz">
                        @if ($ride->tr('badge'))
                            <span class="tag tag--senal tag--punteada ride-card__badge">{{ $ride->tr('badge') }}</span>
                        @endif
                        @if ($ride->image)
                            <img class="ride-card__img" src="{{ asset($ride->image) }}" alt="{{ $ride->tr('name') }}" loading="lazy">
                        @else
                            <span class="ride-card__placeholder">Foto — {{ \Illuminate\Support\Str::lower($ride->tr('name')) }}</span>
                        @endif
                    </div>
                    <h3 class="ride-card__name">{{ $ride->tr('name') }}</h3>
                    <div class="ride-card__buy">
                        @if ($complements->isPurchasable($ride))
                            <div class="ride-card__price">@if ($ride->ticketType?->priceVaries())<span class="ride-card__from">{{ __('landing.pricing.from') }}</span>@endif{{ $ride->ticketType?->euros() }}<span class="cents">,{{ $ride->ticketType?->cents() }}</span><span class="eur">€</span></div>
                            <button type="button" class="btn ride-card__cta" aria-label="{{ __('landing.rides.buy') }} · {{ $ride->tr('name') }}" @click="$store.purchase.openWith({ type: 'zone', slug: '{{ $zone->slug }}' })">{{ __('landing.rides.buy') }}</button>
                        @else
                            <button type="button" class="btn btn--ghost ride-card__cta" aria-label="{{ __('landing.rides.book_zone', ['zone' => $zone->tr('name')]) }}" @click="$store.purchase.openWith({ type: 'zone', slug: '{{ $zone->slug }}' })">{{ __('landing.rides.book_zone', ['zone' => $zone->tr('name')]) }} <x-icons.arrow-right class="arrow" :width="14" :height="14" /></button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endforeach
    <div class="slider-foot">
        <div class="slider-progress"><div class="slider-progress__bar" :style="{ left: (progressLeft*100)+'%', width: (progressWidth*100)+'%' }"></div></div>
        <div class="slider-nav">
            <button class="slider-arrow" @click="scrollSlider(-1)" aria-label="{{ __('landing.nav.slider_prev') }}"><x-icons.arrow-left :width="16" :height="16" /></button>
            <button class="slider-arrow" @click="scrollSlider(1)" aria-label="{{ __('landing.nav.slider_next') }}"><x-icons.arrow-right :width="16" :height="16" /></button>
        </div>
    </div>
</div>
