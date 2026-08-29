<x-layout :title="__('site.contact_title')" :description="__('site.contact_intro')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        <div class="page__head">
            <div class="eyebrow">{{ __('site.contact_eyebrow') }}</div>
            <h1 class="page__title">{{ __('site.contact_title') }}</h1>
        </div>

        <p class="page__body" style="max-width:60ch; margin-bottom:24px">{{ __('site.contact_intro') }}</p>

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
                        <a href="https://wa.me/{{ $site['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn--zone">{{ __('site.contact_whatsapp') }} →</a>
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

                <button type="submit" class="btn btn--zone btn--lg">{{ __('site.contact_send') }} →</button>
            </form>
        @endif
        </div>{{-- /.contact-layout__main --}}

        {{-- #216: ubicación del parque, reutilizando la `map-card` de la landing (mapa embebido si
             está configurado + dirección + «Cómo llegar»). Mismo recurso de diseño, sin duplicar. --}}
        <aside class="contact-layout__aside">
        <div class="map-card map-card--aside">
            {{-- Bloqueo previo (#219): el iframe del mapa solo carga con consentimiento «mapa». --}}
            <x-site.consent-frame category="maps" :src="$site['maps_embed']"
                :title="__('landing.info.address_title')"
                wrapper-style="position:absolute; inset:0"
                frame-style="width:100%; height:100%; border:0"
                referrerpolicy="no-referrer-when-downgrade" allowfullscreen>
                <span class="map-pin"></span>
            </x-site.consent-frame>
            <div style="position:relative; z-index:1; background:var(--bg-card); padding:20px 24px; border-radius:var(--r); border:1px solid var(--line); max-width:340px">
                <h2 style="margin:0; font-family:var(--font-display); font-size:32px; letter-spacing:-0.03em; font-weight:800">{{ __('landing.info.address_title') }}</h2>
                <p style="margin:10px 0 16px; color:var(--fg-mute); font-size:14px; line-height:1.6">
                    {{ $site['address1'] ?? '' }}<br />
                    {{ $site['address2'] ?? '' }}<br />
                    {{ __('landing.info.parking') }}
                </p>
                @if ($hasMaps)
                    <a href="{{ $site['maps'] }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">{{ __('landing.info.directions') }}</a>
                @endif
            </div>
        </div>
        </aside>
        </div>{{-- /.contact-layout --}}

        <a href="{{ url('/') }}" class="page__back" data-tap style="margin-top:32px">{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
