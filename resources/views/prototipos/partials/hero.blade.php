{{-- El hero de la portada, TAL CUAL (copiado sin sus comentarios de `home.blade.php`): no es objeto de
     esta decisión (`#252`, `#254`). --}}
<header id="top" class="hero hero--full" x-data="heroChoreo">
    <div class="hero__sticky">
    <div class="hero__stage" data-surface="ink" data-has-video="true">
        <div class="hero__stage-placeholder" aria-hidden="true"></div>
        <video class="hero__video" autoplay muted loop playsinline preload="auto"
               poster="{{ asset('videos/header_poster.jpg') }}?v={{ @filemtime(public_path('videos/header_poster.jpg')) }}" aria-hidden="true">
            <source src="{{ asset('videos/header_hero.mp4') }}?v={{ @filemtime(public_path('videos/header_hero.mp4')) }}" type="video/mp4">
        </video>
        <div class="hero__stage-scrim" aria-hidden="true"></div>
        <span class="hero__stage-label">{{ __('landing.hero.reel') }}</span>
        <div class="hero__stage-content">
            <div class="hero__headline">
                <span class="hero__kicker">{{ __('landing.hero.kicker') }}</span>
                <h1 class="hero__title hero__title--onvideo">{{ __('landing.hero.l1') }} <span class="hero__switch"><span class="hero__switch-sw" aria-hidden="true"><span class="hero__switch-knob"></span><span class="hero__switch-on">{{ __('landing.hero.l2') }}</span></span><span class="sr-only">{{ __('landing.hero.l2') }}</span></span></h1>
            </div>
            <div class="hero__pair-slot">
                <x-site.cta-pair place="hero" />
            </div>
        </div>
    </div>
    <div class="hero__strip" aria-hidden="true">
        <x-site.brand-strip class="brand-strip--wedge hero__strip-box" />
    </div>
    </div>
    <div class="hero__sentinel" aria-hidden="true"></div>
</header>
