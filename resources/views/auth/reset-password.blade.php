{{-- `noindex`: esta URL lleva un TOKEN de restablecimiento. Que una superficie así se sirviera
     `index, follow` era un descuido heredado —el `noindex` de auth venía del prop `authModal`, que
     esta página pone a `null`—. Fijado en `SeoTest`. --}}
<x-layout :title="__('account.reset.title')" :auth-modal="null" :noindex="true">
<div x-data="landing">
    <x-site.nav />

    <main class="page wrap">
        <div class="page__head">
            <div class="eyebrow">{{ __('account.reset.eyebrow') }}</div>
            <h1 class="page__title">{{ __('account.reset.title') }}</h1>
        </div>

        <div class="page__body">
            <p>{{ __('account.reset.intro') }}</p>
        </div>

        <div class="auth auth--page">
            @livewire('auth.reset-password', ['token' => $token, 'email' => $email])
        </div>

        <a href="{{ url('/') }}" class="page__back">{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
