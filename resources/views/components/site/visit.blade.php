@props([
    'schedule',          // App\Domain\Content\Services\ScheduleDisplay (lo pasa el HomeController)
])

{{--
    «Visítanos» EN TARJETAS (`[DECIDIDO owner, 2026-09-01]`: «quiero un diseño de cards, todo en
    cards en la medida de lo posible; lo siento más organizado y limpio»), rompiendo el molde
    editorial que `specs/idioma-visual-heredado.md` §3.quinquies midió aquí como caso EXTREMO:
    cuatro encabezados para cuatro líneas de dato.

    ▶ **La tarjeta es la PEGATINA del producto** (`#303`): borde de tinta, radio grande y sombra
    dura por ROL. NO es la `.info-card` heredada —blanda, con `--line` y sin sombra—, que es
    justamente el idioma del cliente antiguo que este carril está sustituyendo.

    ▶ **Ninguna tarjeta lleva título dentro**, y eso no es un descuido: §3.quinquies.4 dio por
    decidido retirar los dos `h3` internos y el `h4` de fechas especiales. Un punto verde junto a
    «Abierto ahora» no necesita que nadie lo presente, y una dirección con un botón «Cómo llegar»
    debajo tampoco. La sección conserva UN encabezado: su `<h2>`.

    ▶ **Tampoco llevan `:hover`.** La pegatina de `#303` responde al puntero porque tiene un CTA
    dentro; éstas no llevan a ninguna parte. Dar respuesta de puntero a una tarjeta inerte es el
    defecto que `#295` encontró en `.ride-card` (`cursor: pointer` sobre un `<article>` sin enlace):
    una afordancia que promete algo que no ocurre.

    Lo que entra y no estaba: el estado EN VIVO, la excepción PEGADA a él (una excepción invalida
    la frase de arriba, no es un apéndice) y el TELÉFONO, cuyo dato ya viajaba en `$site` sin que
    esta sección lo usara. Lo que sale: «Parking gratis 2h», que estaba en el código y no en el
    panel (`[DECIDIDO owner]` de §3.quinquies.5·2, pendiente de aplicar «cuando se rehaga la
    sección»), y la caja blanca posada sobre el mapa.
--}}

@php
    $rows = $schedule->weeklyRows();
    $seasons = $schedule->seasons();
    $specials = $schedule->upcomingSpecialDates();
    $hasAddress = filled($site['address1'] ?? null) || filled($site['address2'] ?? null);
@endphp

<div class="visit">
    <div class="visit__col">

        {{-- TARJETA 1 · CUÁNDO — la respuesta primero, el calendario después. --}}
        <div class="visit-card">
            @if (! empty($heroStatus))
                <p class="visit__now @if ($heroStatus['open_now']) is-open @endif">
                    <span class="visit__dot" aria-hidden="true"></span>
                    <span>{{ $heroStatus['status'] }}</span>
                    @if (! empty($heroStatus['closes_at']))
                        <span class="visit__until">{{ __('landing.info.until', ['time' => $heroStatus['closes_at']]) }}</span>
                    @endif
                </p>
            @endif

            @foreach ($specials as $sd)
                <p class="visit__exception @if ($sd['is_closed']) is-closed @endif">
                    {{ $sd['date'] }} — {{ $sd['detail'] }}
                </p>
            @endforeach

            <dl class="visit__hours">
                @forelse ($rows as $row)
                    <div><dt>{{ $row['label'] }}</dt><dd>{{ $row['time'] }}</dd></div>
                @empty
                    <div><dt>{{ __('landing.info.hours_tbd') }}</dt><dd>—</dd></div>
                @endforelse
                @foreach ($seasons as $season)
                    <div class="@if ($season['is_current']) is-current @endif">
                        <dt>{{ $season['name'] }} <small>({{ $season['range'] }})</small></dt>
                        <dd>{{ $season['time'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- TARJETA 2 · DÓNDE — la dirección y lo que se hace con ella. --}}
        <div class="visit-card">
            @if ($hasAddress)
                <p class="visit__addr">{{ $site['address1'] }}<br />{{ $site['address2'] }}</p>
            @endif
            <div class="visit__actions">
                <a class="btn btn--ghost btn--sm" href="{{ $site['maps'] ?? '#' }}" data-tap>{{ __('landing.info.directions') }}</a>
                @if (! empty($site['has_phone']))
                    <a class="btn btn--ghost btn--sm" href="tel:{{ $site['phone_tel'] }}" data-tap>{{ $site['phone'] }}</a>
                @endif
            </div>
        </div>
    </div>

    {{-- TARJETA 3 · EL MAPA — misma pegatina, sin relleno: la imagen llega al borde.
         Mismo bloqueo previo de siempre (`#219`): el iframe solo carga con consentimiento de la
         categoría «mapa»; sin URL configurada cae al pin decorativo. --}}
    <div class="visit-card visit-card--map">
        <x-site.consent-frame category="maps" :src="$site['maps_embed']"
            :title="__('landing.info.address_title')"
            wrapper-class="visit__frame"
            frame-class="visit__iframe"
            referrerpolicy="no-referrer-when-downgrade" allowfullscreen>
            <span class="map-pin"></span>
        </x-site.consent-frame>
    </div>
</div>
