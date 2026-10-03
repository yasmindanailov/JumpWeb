{{-- LA BAJA de «novedades» (`specs/correos-rediseno.md` §4.4, la C1a, `#920`): la de los correos comerciales, en el molde de
     la baja de las encuestas y de «Avísame de fechas». Una página con UN botón: no escribe al abrirse (los escáneres de enlaces
     abren los GET). No dice el correo ni el nombre. Se puede volver a marcar desde Mi cuenta. --}}
<x-focused-layout :title="__('account.novedades_baja.titulo')">
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
                <div class="gf-notice gf-notice--ok" role="status" data-novedades-baja="done">
                    <p class="gf-notice__title">{{ __('account.novedades_baja.hecho_titulo') }}</p>
                    <p class="gf-notice__text">{{ __('account.novedades_baja.hecho') }}</p>
                </div>
            @else
                <form method="post" action="{{ $accion }}" class="gf-form survey" data-novedades-baja="ask">
                    @csrf
                    <h1 class="survey__label">{{ __('account.novedades_baja.titulo') }}</h1>
                    <p class="survey__lede">{{ __('account.novedades_baja.texto') }}</p>
                    <div class="survey__actions">
                        <button type="submit" class="btn" data-novedades-baja-boton>{{ __('account.novedades_baja.boton') }}</button>
                    </div>
                </form>
            @endif
        </main>
    </div>
</x-focused-layout>
