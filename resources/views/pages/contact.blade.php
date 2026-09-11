<x-layout :title="__('site.contact_title')" :description="__('site.contact_intro')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        {{-- La cabecera del armazón (T3a·3): la frase de presentación sube a ENTRADILLA, que es lo
             que era — iba suelta como cuerpo de página, con su ancho y su margen a mano. --}}
        <x-site.page-head :title="__('site.contact_title')" :lede="__('site.contact_intro')" />

        {{-- #216: CTAs directos data-driven (solo si el dato existe en Ajustes), con los botones del
             sistema de diseño de la landing (`.btn`). La clienta prefiere resolver dudas por aquí. --}}
        @php
            $hasMaps = ! empty($site['maps']) && $site['maps'] !== '#';
        @endphp

        {{-- #216: la ubicación va en el lateral (columna derecha) en escritorio y se apila debajo
             del formulario en móvil. --}}
        <div class="contact-layout">
        <div class="contact-layout__main">
        @if ($site['has_phone'] || ! empty($site['whatsapp']))
            <div class="contact-quick">
                <span class="contact-quick__label">{{ __('site.contact_quick_title') }}</span>
                <div class="contact-quick__row">
                    @if ($site['has_phone'])
                        <a href="tel:{{ $site['phone_tel'] }}" class="btn btn--ghost">{{ __('site.contact_call') }} · {{ $site['phone'] }}</a>
                    @endif
                    @if (! empty($site['whatsapp']))
                        <a href="https://wa.me/{{ $site['whatsapp'] }}" target="_blank" rel="noopener" class="btn">{{ __('site.contact_whatsapp') }} →</a>
                    @endif
                </div>
            </div>
        @endif

        @if (session('contact_sent'))
            <div class="form-success">{{ __('site.contact_success') }}</div>
        @else
            <form method="POST" action="{{ route('contacto.store') }}" class="form">
                @csrf

                {{-- honeypot anti-spam: display:none para que el autocompletar no lo rellene --}}
                <div style="display:none" aria-hidden="true">
                    <label>{{ __('site.contact_hp') }}<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="form__row">
                    <label class="form__field">
                        <span>{{ __('site.contact_name') }}</span>
                        <input type="text" name="name" value="{{ old('name') }}" required>
                        @error('name')<em class="form__error">{{ $message }}</em>@enderror
                    </label>
                    <label class="form__field">
                        <span>{{ __('site.contact_email') }}</span>
                        <input type="email" name="email" value="{{ old('email') }}" required>
                        @error('email')<em class="form__error">{{ $message }}</em>@enderror
                    </label>
                </div>

                <label class="form__field">
                    <span>{{ __('site.contact_phone') }}</span>
                    <input type="tel" name="phone" value="{{ old('phone') }}">
                    @error('phone')<em class="form__error">{{ $message }}</em>@enderror
                </label>

                <label class="form__field">
                    <span>{{ __('site.contact_message') }}</span>
                    <textarea name="message" rows="6" required>{{ old('message') }}</textarea>
                    @error('message')<em class="form__error">{{ $message }}</em>@enderror
                </label>

                {{-- Anti-bot Turnstile (auditoría Fase 1, A4): solo se renderiza si hay claves
                     configuradas (security.turnstile_*). Sin claves no aparece nada y el envío funciona
                     igual. El CSP ya permite challenges.cloudflare.com (script-src/frame-src). --}}
                @if (\App\Domain\Platform\Services\Turnstile::enabled())
                    <div class="cf-turnstile" data-sitekey="{{ \App\Domain\Platform\Services\Turnstile::siteKey() }}"></div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @endif

                <button type="submit" class="btn btn--lg">{{ __('site.contact_send') }} →</button>
            </form>
        @endif
        </div>{{-- /.contact-layout__main --}}

        {{-- #216: ubicación del parque, reutilizando la `map-card` de la landing (mapa embebido si
             está configurado + dirección + «Cómo llegar»). Mismo recurso de diseño, sin duplicar. --}}
        <aside class="contact-layout__aside">
        <div class="map-card map-card--aside">
            {{-- A5 · niebla de spray. ⚠️ Su nota dice «formularios largos y paneles laterales», y
                 EN EL FORMULARIO SE VEÍA MAL: los halos caían bajo las etiquetas, que es lo que su
                 regla 02 prohíbe. Aquí es panel lateral y no hay párrafo encima. --}}
            <div class="spray" aria-hidden="true"></div>
            {{-- Bloqueo previo (#219): el iframe del mapa solo carga con consentimiento «mapa». --}}
            <x-site.consent-frame category="maps" :src="$site['maps_embed']"
                :title="__('landing.info.address_title')"
                wrapper-style="position:absolute; inset:0"
                frame-style="width:100%; height:100%; border:0"
                referrerpolicy="no-referrer-when-downgrade" allowfullscreen>
                <span class="map-pin"></span>
            </x-site.consent-frame>
        </div>
        {{-- LA DIRECCIÓN, DEBAJO DEL MAPA Y NO ENCIMA (auditoría M4 · `[DECIDIDO owner, 2026-09-03]`,
             `DECISIONES #434`). Hasta hoy esta tarjeta iba posada SOBRE el marco del mapa y tapaba
             al 100 % el botón «Cargar el mapa» y su enlace a la política (medido con
             `elementFromPoint()` a 1440 y a 390): en escritorio el mapa no se podía cargar.
             ▶ Es la PEGATINA de «Visítanos» (`#307`, `CardSkinTest`), sin título dentro —el pin dice
             qué es— y sin `:hover`, porque no lleva a ninguna parte.
             ⚠️ «Parking gratis 2h» se retira (auditoría M5, `[DECIDIDO owner]`: fuera): era un dato
             de negocio escrito en el código; la FAQ del panel ya lo dice. `ContactPageTest` vigila
             las dos cosas: que la tarjeta no vuelva dentro del mapa y que la frase no vuelva. --}}
        @if (filled($site['address1'] ?? null) || filled($site['address2'] ?? null) || $hasMaps)
            <div class="visit-card contact-where">
                <span class="visit-card__ico" aria-hidden="true"><x-icons.pin :width="22" :height="22" /></span>
                @if (filled($site['address1'] ?? null) || filled($site['address2'] ?? null))
                    <p class="visit__addr">{{ $site['address1'] ?? '' }}<br />{{ $site['address2'] ?? '' }}</p>
                @endif
                @if ($hasMaps)
                    <div class="visit__actions">
                        <a href="{{ $site['maps'] }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm" data-tap>{{ __('landing.info.directions') }}</a>
                    </div>
                @endif
            </div>
        @endif
        </aside>
        </div>{{-- /.contact-layout --}}

        <a href="{{ url('/') }}" class="page__back" data-tap style="margin-top:32px">{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
