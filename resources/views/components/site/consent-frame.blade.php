@props([
    'category',          // 'maps' | 'social' — finalidad (granularidad por finalidad, RGPD art. 7)
    'src' => null,       // URL de inserción ya saneada ($site['maps_embed'] / $site['social_feed'])
    'title' => '',
    'wrapperClass' => '',
    'wrapperStyle' => '',
    'frameClass' => '',
    'frameStyle' => '',
])
{{--
    Bloqueo previo de un iframe de tercero (#219, `docs/PLAN-COOKIES.md` §5). Componente ÚNICO
    reutilizado en los 3 puntos (home mapa, /contacto mapa, home feed social) para que no se pueda
    olvidar gatear uno nuevo. Tres estados:
      · NO configurado (sin URL)            → se muestra el slot por defecto (pin del mapa / galería).
      · Configurado + consentido            → <iframe src> directo, server-side, sin JS.
      · Configurado + SIN consentir         → placeholder con botón «Cargar …» (Alpine inyecta el
                                              src desde data-src, sin recarga) + enlace a la política.
    El consentimiento lo decide el SERVIDOR (`$cookieConsent`), no el cliente → defensa real (las
    cookies del tercero se fijan DENTRO de su iframe; la única forma de evitarlas es no cargarlo).
--}}
@php
    $configured = filled($src);
    $consented = (bool) data_get($cookieConsent ?? [], $category, false);
@endphp

@if (! $configured)
    {{ $slot }}
@else
    <div class="consent-frame {{ $wrapperClass }}" @if ($wrapperStyle) style="{{ $wrapperStyle }}" @endif
         x-data="consentFrame('{{ $category }}', {{ $consented ? 'true' : 'false' }})">
        <iframe
            @if ($consented) src="{{ $src }}" @else data-src="{{ $src }}" x-cloak @endif
            x-ref="frame"
            x-show="loaded"
            title="{{ $title }}"
            class="{{ $frameClass }}"
            @if ($frameStyle) style="{{ $frameStyle }}" @endif
            loading="lazy"
            {{ $attributes }}></iframe>

        @unless ($consented)
            <div class="consent-frame__ph" x-show="! loaded">
                <p class="consent-frame__ph-text">{{ __('cookies.frame.'.$category.'_text') }}</p>
                <button type="button" class="btn btn--ghost consent-frame__ph-btn" @click="accept()">
                    {{ __('cookies.frame.'.$category.'_btn') }}
                </button>
                <a class="consent-frame__ph-link" href="{{ route('legal.cookies') }}">{{ __('cookies.frame.policy_link') }}</a>
                {{-- Degradación con JS desactivado: sin Alpine no hay botón → se informa de que el
                     contenido no carga por respeto a la privacidad (no se instalan cookies de tercero). --}}
                <noscript><span class="consent-frame__ph-text">{{ __('cookies.frame.noscript') }}</span></noscript>
            </div>
        @endunless
    </div>
@endif
