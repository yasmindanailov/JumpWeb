{{-- La BAJA de las encuestas por correo (`specs/encuestas.md` §4.3, T3): una página con UN botón. No es un
     enlace que escriba al abrirse: los escáneres de enlaces de los gestores de correo abren los GET y darían de
     baja a quien no pidió nada. Se puede volver a encender desde «Mi cuenta → Privacidad». --}}
<x-focused-layout :title="__('surveys.page.optout_title')">
    <div class="gf-page">
        @php $clientLogo = @filemtime(public_path('img/client-logo.svg')); @endphp
        <div class="gf-mark">
            @if ($clientLogo)
                <img class="gf-mark__logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
                     alt="{{ $site['name'] ?? config('app.name') }}">
            @else
                <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            @endif
            <span class="gf-mark__sub">{{ __('surveys.page.title') }}</span>
        </div>

        <main class="gf-sheet">
            @if ($done)
                <div class="gf-notice gf-notice--ok" role="status" data-survey-optout="done">
                    <p class="gf-notice__title">{{ __('surveys.page.optout_done_title') }}</p>
                    <p class="gf-notice__text">{{ __('surveys.page.optout_done') }}</p>
                </div>
            @else
                <form method="post" action="{{ route('survey.optout.confirm', ['token' => $token]) }}" class="gf-form survey" data-survey-optout="ask">
                    @csrf
                    <h1 class="survey__label">{{ __('surveys.page.optout_title') }}</h1>
                    <p class="survey__lede">{{ __('surveys.page.optout_text') }}</p>
                    <div class="survey__actions">
                        <button type="submit" class="btn" data-survey-optout-button>{{ __('surveys.page.optout_button') }}</button>
                    </div>
                </form>
            @endif
        </main>
    </div>
</x-focused-layout>
