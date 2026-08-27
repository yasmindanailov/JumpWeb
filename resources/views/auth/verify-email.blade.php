{{-- `noindex`: es una superficie de AUTH y no tiene nada que buscar en un índice. Lo llevaban las
     tres puertas del modal por efecto lateral del prop `authModal`, y estas dos páginas —que no
     abren modal— se quedaban fuera sin que nadie lo mirara. Fijado en `SeoTest`. --}}
<x-layout :title="__('account.verify.title')" :noindex="true">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        <div class="page__head">
            <div class="eyebrow">{{ __('account.verify.eyebrow') }}</div>
            <h1 class="page__title">{{ __('account.verify.title') }}</h1>
        </div>

        <div class="page__body">
            <p>{{ __('account.verify.intro') }}</p>
            <p>{{ __('account.verify.spam_hint') }}</p>

            @auth
                @unless (auth()->user()->hasVerifiedEmail())
                    {{-- Pay-first: reenvío real del correo para quien se registró en la compra y
                         abandonó sin pagar (quedó sin verificar). Cooldown server-side (1/min). --}}
                    <p>{{ __('account.verify.notice_resend_hint') }}</p>
                    <form method="POST" action="{{ route('verification.send') }}" class="verify__resend">
                        @csrf
                        <button type="submit" class="btn btn--zone">{{ __('account.verify.notice_resend_button') }}</button>
                    </form>
                @endunless
            @else
                <p>{{ __('account.verify.notice_resend_hint_guest') }}</p>
            @endauth
        </div>

        <a href="{{ url('/') }}" class="page__back">{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
