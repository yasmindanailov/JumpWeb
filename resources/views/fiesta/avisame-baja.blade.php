{{-- LA BAJA de «Avísame de fechas» (`specs/avisame-de-fechas.md` §4.4): una página con UN botón, en el molde de la baja
     de las encuestas. No escribe al abrirse (los escáneres de enlaces abren los GET). Dice el nombre de pila del niño y
     nada más. --}}
<x-focused-layout :title="__('fiesta.avisame_baja.titulo')">
    <div class="gf-page">
        @php $clientLogo = @filemtime(public_path('img/client-logo.svg')); @endphp
        <div class="gf-mark">
            @if ($clientLogo)
                <img class="gf-mark__logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
                     alt="{{ $site['name'] ?? config('app.name') }}">
            @else
                <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            @endif
        </div>

        <main class="gf-sheet">
            @if ($hecho)
                <div class="gf-notice gf-notice--ok" role="status" data-avisame-baja="done">
                    <p class="gf-notice__title">{{ __('fiesta.avisame_baja.hecho_titulo') }}</p>
                    <p class="gf-notice__text">{{ __('fiesta.avisame_baja.hecho') }}</p>
                </div>
            @else
                <form method="post" action="{{ $accion }}" class="gf-form survey" data-avisame-baja="ask">
                    @csrf
                    <h1 class="survey__label">{{ __('fiesta.avisame_baja.titulo') }}</h1>
                    <p class="survey__lede">{{ __('fiesta.avisame_baja.texto', ['nino' => $nino]) }}</p>
                    <div class="survey__actions">
                        <button type="submit" class="btn" data-avisame-baja-boton>{{ __('fiesta.avisame_baja.boton') }}</button>
                    </div>
                </form>
            @endif
        </main>
    </div>
</x-focused-layout>
