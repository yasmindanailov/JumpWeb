{{-- La cabecera: el logotipo, el titular y el resguardo corto de la fiesta (con «Ha cambiado» si el testigo falló). --}}
<header class="pli-cab" data-zona="1">
    @if ($m['logo']['src'] !== null)
        <img class="pli-logo" src="{{ $m['logo']['src'] }}" alt="{{ $m['logo']['alt'] }}">
    @else
        <span class="pli-logo" style="display: inline-flex; align-items: center; font: 900 1.375rem/1 var(--font-display); color: var(--fiesta-tinta-900);">{{ $m['logo']['alt'] }}</span>
    @endif
    <h1 id="pli-h1" class="pli-h1" tabindex="-1">{{ __('fiesta.lista.titular', ['n' => $m['cumple']['nombre']]) }}</h1>
    <p class="pli-res"><span>{{ __('fiesta.lista.resguardo', ['corto' => $m['reserva']['corto'], 'hora' => $m['reserva']['hora'], 'pack' => $m['reserva']['pack'], 'codigo' => $m['reserva']['codigo']]) }}</span>@if ($m['guardar']['estado'] === 'conflict')<x-pieza.chapa tone="warn" size="sm">{{ __('fiesta.lista.ha_cambiado') }}</x-pieza.chapa>@endif</p>
    {{-- EL PLAZO de la lista, UNO para todo y aquí solo (P1 de §4.20, `#912`): ni en las tarjetas ni en el pie. --}}
    @if ($m['plazo'] !== null)<p @class(['pli-plazo-lista', 'pli-plazo-lista--cerrada' => ! $m['plazo']['abierta']]) data-plazo-lista><x-lucide :name="$m['plazo']['abierta'] ? 'clock' : 'lock'" :size="15" /><span>{{ $m['plazo']['texto'] }}</span>@if (! $m['plazo']['abierta'] && $m['reserva']['tel'] !== '') <a href="tel:{{ $m['reserva']['tel'] }}">{{ __('fiesta.lista.numero.llamanos') }}</a>@endif</p>@endif
</header>
