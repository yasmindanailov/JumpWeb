@props(['total' => 0, 'confirmed' => 0, 'pending' => 0, 'reserved' => 0, 'label' => ''])
@php
    /*
     * LAS PLAZAS de una fiesta, de un vistazo (`invitados/PlacesMeter.jsx`): una casilla por plaza. Verdes, las
     * ocupadas; vacías, las libres; en ámbar, solo las de más que aún no se han confirmado. Con más en la lista que
     * en la reserva, un hueco marca dónde acababa. Hasta 40 plazas, casillas; con más, una barra seguida. Estilos
     * EN LÍNEA como el JSX.
     */
    $n = max(0, (int) round($total));
    $c = min(max(0, (int) $confirmed), $n);
    $p = min(max(0, (int) $pending), $n - $c);
    $corte = ($reserved > 0 && $reserved < $n) ? (int) $reserved : 0;
    $tr = 'background var(--dur-base) var(--ease-out)';
    $color = static fn (int $i): string => $i < $c ? 'var(--success-500)' : ($i < $c + $p ? 'var(--warn-500)' : 'var(--fiesta-tinta-200)');
    $estiloExtra = $attributes->has('style') ? ' '.$attributes->get('style') : '';
    $pct = static fn (int $x): string => rtrim(rtrim(number_format($n > 0 ? $x / $n * 100 : 0, 4, '.', ''), '0'), '.');
@endphp
@if ($n > 40)
<div role="img" aria-label="{{ $label }}" style="display: flex; height: 12px; border-radius: var(--r-pill); overflow: hidden; background: var(--fiesta-tinta-200);{{ $estiloExtra }}" {{ $attributes->except('style') }}><span style="width: {{ $pct($c) }}%; background: var(--success-500); transition: width var(--dur-base) var(--ease-out);"></span><span style="width: {{ $pct($p) }}%; background: var(--warn-500); transition: width var(--dur-base) var(--ease-out);"></span></div>
@else
<div role="img" aria-label="{{ $label }}" style="display: flex; gap: {{ $n > 20 ? '3px' : '4px' }}; height: 12px;{{ $estiloExtra }}" {{ $attributes->except('style') }}>@for ($i = 0; $i < $n; $i++)<span style="flex: 1 1 0; min-width: 0; background: {{ $color($i) }}; transition: {{ $tr }}; margin-left: {{ ($corte && $i === $corte) ? ($n > 20 ? '5px' : '8px') : '0px' }}; border-radius: {{ $n === 1 ? 'var(--r-pill)' : ($i === 0 ? '999px 4px 4px 999px' : ($i === $n - 1 ? '4px 999px 999px 4px' : '4px')) }};"></span>@endfor</div>
@endif