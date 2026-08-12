{{-- Widget flotante «caja de regalo» de OFERTAS informativas (#270, docs/PLAN-OFERTAS-WIDGET.md).
     Solo se pinta si hay ofertas activas (`$offers` viene del composer memoizado de AppServiceProvider).
     Al pulsar la caja: destello (`is-burst`) + el modal vuela desde la caja; dentro, carrusel de
     ofertas (título + imagen) con flechas. Toda la lógica vive en `Alpine.data('offersWidget')`
     (app.js) — NUNCA <script> suelto (morph de Livewire). Las imágenes se cargan on-demand (a la 1ª
     apertura), no en la carga de página. Va al final del <body>, fuera del `x-data="landing"`. --}}
@php($offers = ($offers ?? collect())->values())
@if ($offers->isNotEmpty())
    <div class="offw" x-data="offersWidget({{ $offers->count() }})" @resize.window="onResize()">

        {{-- Lanzador: la caja ES el botón. bottom-right; en móvil, por encima de la .book-bar (CSS). --}}
        <button type="button" class="offw-launch" x-ref="launch" @click="openModal()"
                :class="{ 'is-open-modal': open }" aria-haspopup="dialog"
                aria-label="{{ __('offers.open_aria') }}">
            <span class="offw-gift gm-1" :class="{ 'is-open': open }" aria-hidden="true">
                <svg viewBox="0 0 40 40">
                    <g class="all">
                        <g class="box">
                            <rect class="pc-box" x="9" y="20" width="22" height="13" rx="2.2"/>
                            <rect class="pc-inner" x="10.8" y="18.4" width="18.4" height="2.4" rx="1.1"/>
                            <rect class="pc-shadow" x="9.6" y="20" width="20.8" height="1.5" rx="0.7"/>
                            <rect class="pc-ribbon" x="17.3" y="20" width="5.4" height="13" rx="0.7"/>
                        </g>
                        <g class="lid">
                            <rect class="pc-lid" x="7" y="15" width="26" height="5.8" rx="2.2"/>
                            <rect class="pc-ribbon" x="17.3" y="15" width="5.4" height="5.8" rx="0.7"/>
                            <g class="bow">
                                <path class="pc-bow" d="M 18.7 15.2 L 16.1 20.5 L 17.9 19.5 L 19 20.7 L 19.9 15.5 Z"/>
                                <path class="pc-bow" d="M 21.3 15.2 L 23.9 20.5 L 22.1 19.5 L 21 20.7 L 20.1 15.5 Z"/>
                                <path class="pc-bow" d="M 20 13.3 C 16.6 10.6, 10.7 10, 10.2 12.8 C 10 14.3, 11.7 14.9, 13.9 14.9 C 16.3 14.9, 18.7 14.4, 20 13.9 Z"/>
                                <path class="pc-bow" d="M 20 13.3 C 23.4 10.6, 29.3 10, 29.8 12.8 C 30 14.3, 28.3 14.9, 26.1 14.9 C 23.7 14.9, 21.3 14.4, 20 13.9 Z"/>
                                <path class="pc-crease" d="M 19.5 13.8 C 17.2 13.7, 14.5 13.9, 12.5 14.7"/>
                                <path class="pc-crease" d="M 20.5 13.8 C 22.8 13.7, 25.5 13.9, 27.5 14.7"/>
                                <rect class="pc-knot" x="18.3" y="12.4" width="3.4" height="3.7" rx="1.2"/>
                                <path class="pc-hi" d="M 18.8 12.8 L 19.4 12.8 L 19.1 15.5 Z"/>
                            </g>
                        </g>
                    </g>
                </svg>
                <span class="cft c1" style="left:34%;top:36%;width:5px;height:5px;background:var(--offw-accent);--cx:-20px;--cy:-26px;--cr:-60deg"></span>
                <span class="cft c2" style="left:60%;top:34%;width:5px;height:5px;background:var(--ribbon);--cx:20px;--cy:-28px;--cr:70deg"></span>
                <span class="cft c3" style="left:50%;top:31%;width:5px;height:5px;background:var(--offw-accent);--cx:3px;--cy:-34px;--cr:30deg"></span>
                <span class="cft c4" style="left:44%;top:37%;width:4px;height:4px;background:#F0B33F;--cx:-11px;--cy:-27px;--cr:-30deg"></span>
                <span class="cft c5" style="left:56%;top:38%;width:4px;height:4px;background:var(--ribbon);--cx:14px;--cy:-24px;--cr:45deg"></span>
            </span>
            {{-- Badge SIEMPRE visible (el widget solo se pinta si hay ≥1 oferta): también con 1. --}}
            <span class="offw-badge">{{ $offers->count() }}</span>
        </button>

        {{-- Scrim (se centra en el punto de la caja, --ox/--oy) --}}
        <div class="offw-scrim" :class="{ 'is-on': open }" @click="close()"></div>

        {{-- Estallido de luz — nace en la caja (--ox/--oy). Solo se anima con `is-burst`. --}}
        <div class="offw-burst" :class="{ 'is-burst': burst }" aria-hidden="true">
            <span class="offw-flash"></span>
            <span class="offw-rays"></span>
            <span class="offw-ring"></span>
            <span class="offw-ring r2"></span>
            <span class="spark s-a" style="--sx:-8px;--sy:-120px;--sr:120deg"></span>
            <span class="spark s-g star" style="--sx:60px;--sy:-104px;--sr:90deg"></span>
            <span class="spark s-w" style="--sx:-66px;--sy:-92px;--sr:-60deg"></span>
            <span class="spark s-c" style="--sx:100px;--sy:-58px;--sr:150deg"></span>
            <span class="spark s-a star" style="--sx:34px;--sy:-130px;--sr:60deg"></span>
            <span class="spark s-g" style="--sx:-40px;--sy:-112px;--sr:200deg"></span>
            <span class="spark s-w" style="--sx:78px;--sy:-98px;--sr:110deg"></span>
            <span class="spark s-c" style="--sx:-88px;--sy:-46px;--sr:-140deg"></span>
            <span class="spark s-a" style="--sx:16px;--sy:-138px;--sr:30deg"></span>
        </div>

        {{-- Modal: vuela y crece desde la caja hasta el centro. `open`/`i`/`go`/`close` viven en
             `offersWidget` (mismo x-data) → sin problema de scope anidado. --}}
        <div class="offw-modal" :class="{ 'show': shown }" role="dialog" aria-modal="true"
             aria-label="{{ __('offers.modal_aria') }}"
             @keydown.escape.window="close()" @keydown="trap($event)">
            <div class="offw-card" x-ref="card">
                <button type="button" class="offw-close" @click="close()" aria-label="{{ __('offers.close') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>

                @if ($offers->count() > 1)
                    <button type="button" class="offw-arrow offw-arrow--prev" @click="go(i - 1)" aria-label="{{ __('offers.prev') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button type="button" class="offw-arrow offw-arrow--next" @click="go(i + 1)" aria-label="{{ __('offers.next') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                @endif

                <div class="offw-slides">
                    @foreach ($offers as $idx => $offer)
                        <figure class="offw-slide" x-show="i === {{ $idx }}" x-cloak>
                            <h2 class="offw-title">{{ $offer->tr('title') }}</h2>
                            @if ($offer->image)
                                <img class="offw-img" alt="{{ $offer->tr('title') }}" loading="lazy" decoding="async"
                                     data-src="{{ $offer->imageUrl() }}" :src="loaded ? $el.dataset.src : null">
                            @endif
                        </figure>
                    @endforeach
                </div>

                @if ($offers->count() > 1)
                    <div class="offw-dots" aria-hidden="true">
                        @foreach ($offers as $idx => $offer)
                            <button type="button" class="offw-dot" :class="{ 'is-on': i === {{ $idx }} }"
                                    @click="go({{ $idx }})" tabindex="-1"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
