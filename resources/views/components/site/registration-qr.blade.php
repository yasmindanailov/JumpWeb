@props(['svg' => null])

{{-- Card «Regístrate antes de venir» (#268): QR + CTA al MISMO enlace de registro EXTERNO.

     Se muestra SOLO cuando hay una URL de registro externa (`$site['registration_url']` =
     `safeExternalUrl(registration.url)`), la señal de «registro externo» que ya usan nav y footer.
     El QR se genera SERVER-SIDE (SVG inline, sin JS) desde esa URL → mejora progresiva: el CTA es un
     enlace real y funciona aunque no se escanee ni haya JS.

     `:svg`  → SVG del QR ya generado (lo pasa el llamante, hoy `/precios`); si no se pasa, se genera
               aquí (uso suelto).

     ⚠️ **La card es siempre una BANDA desde `#531`.** El modo `:inline` existía porque `/precios`
     pintaba una rejilla de tarjetas de entrada y la card entraba como una columna más cuando la zona
     dejaba hueco; esa rejilla se fue con la página rehecha, así que el prop, su clase y su CSS se
     retiraron con su sujeto. --}}
@php $regUrl = $site['registration_url'] ?? null; @endphp

@if (! empty($regUrl))
    <div class="regcard">
        {{-- Columna IZQUIERDA: el QR (con «Escanéame») y, debajo, el CTA. --}}
        <div class="reg-aside">
            <div class="qr-frame">
                <div class="qr-tile">
                    <span class="qr-corner qr-corner--tl" aria-hidden="true"></span>
                    <span class="qr-corner qr-corner--tr" aria-hidden="true"></span>
                    <span class="qr-corner qr-corner--bl" aria-hidden="true"></span>
                    <span class="qr-corner qr-corner--br" aria-hidden="true"></span>
                    <div class="qr-slot" role="img" aria-label="{{ __('landing.registration.qr_aria') }}">
                        {!! $svg ?? \App\Domain\Platform\Services\QrCode::svg($regUrl) !!}
                    </div>
                </div>
            </div>
            <div class="reg-actions">
                <a class="reg-cta" href="{{ $regUrl }}" target="_blank" rel="noopener">{{ __('landing.registration.cta') }} <x-icons.arrow-right :width="16" :height="16" class="reg-cta__arrow" /></a>
            </div>
        </div>

        {{-- Columna DERECHA: solo título + texto. --}}
        <div class="reg-body">
            <h3 class="reg-title">{{ __('landing.registration.title') }}</h3>
            <p class="reg-copy">{{ __('landing.registration.copy') }}</p>
            <p class="reg-copy">{{ __('landing.registration.copy2') }}</p>
        </div>
    </div>
@endif
