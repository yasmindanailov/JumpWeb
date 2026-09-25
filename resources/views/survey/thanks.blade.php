{{-- «Gracias» de la encuesta del correo (`specs/encuestas.md` §4.3, T3): su propia URL, para que recargar no
     reenvíe nada. Solo abre para un token que YA contestó. --}}
<x-focused-layout :title="__('surveys.page.title')">
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
            <div class="gf-notice gf-notice--ok" role="status" data-survey-thanks>
                <p class="gf-notice__title">{{ __('surveys.page.thanks_title') }}</p>
                <p class="gf-notice__text">{{ __('surveys.page.thanks', ['park' => $site['name'] ?? config('app.name')]) }}</p>
            </div>
        </main>
    </div>
</x-focused-layout>
