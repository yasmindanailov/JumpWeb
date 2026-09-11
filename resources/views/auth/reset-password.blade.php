{{-- `noindex`: esta URL lleva un TOKEN de restablecimiento. Que una superficie así se sirviera
     `index, follow` era un descuido heredado —el `noindex` de auth venía del prop `authModal`, que
     esta página pone a `null`—. Fijado en `SeoTest`. --}}
<x-layout :title="__('account.reset.title')" :noindex="true">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        {{-- El rótulo va A MANO: la URL de esta pantalla lleva un TOKEN y no es un destino. --}}
        <x-site.page-head :eyebrow="__('account.reset.eyebrow')" :title="__('account.reset.title')" />

        <div class="page__body">
            <p>{{ __('account.reset.intro') }}</p>
        </div>

        <div class="auth auth--page">
            @livewire('auth.reset-password', ['token' => $token, 'email' => $email])
        </div>

        <a href="{{ url('/') }}" class="page__back" data-tap>{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
