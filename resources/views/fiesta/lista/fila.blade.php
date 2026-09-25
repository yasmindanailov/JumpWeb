{{-- Una fila del formulario POSICIONAL, pintada con la pieza del diseño: sus tres campos con los `name` de siempre
     (`guests[i][columna]`), la marca de adopción FUERA de la fila (`adopt[]`, §7.2·R3) y las columnas del pack que
     la ficha no dibuja, escondidas con su valor para que un guardado no las borre. --}}
@php
    $estado = $n['respuesta'] === 'si' ? 'confirmado' : ($n['respuesta'] === 'no' ? 'no' : 'sin-contestar');
    $descartar = $n['pendiente'] && $n['editable'] ? 'fiesta-descartar' : null;
@endphp
<x-fiesta.fila-invitado :id="$n['id']" :name="$n['nombre']" :age="$n['edad']" :allergies="$n['alergias']" :state="$estado" :viaInvite="$n['origen'] === 'invitacion'" :signed="$n['firmada']" :editable="$n['editable']" :last="$ultima" :campos="$n['campos']" :vacia="$n['vacia']" :quitar="$n['editable'] && $n['origen'] === 'mano'" :omitir="$n['pendiente'] && $n['editable']" :omitirForm="$descartar" :omitirValue="$n['reply_id']" data-indice="{{ $n['indice'] }}" data-completa="{{ $n['completa'] ? '1' : '0' }}" data-origen="{{ $n['origen'] }}" data-respuesta="{{ $n['respuesta'] ?? '' }}" :data-regimen="$n['regimen']" :data-sin-producto="$n['sin_producto'] ? '1' : null">
    {{-- Una fila que NO abre ficha (un «no», o solo lectura) sigue siendo una posición del formulario: sus tres
         columnas viajan escondidas, o el guardado las borraría (la lista entera se sustituye). --}}
    <x-slot:oculto>@if ($n['pendiente'])<input type="hidden" name="adopt[]" value="{{ $n['reply_id'] }}">@endif{{ '' }}@if ($n['respuesta'] === 'no' && $n['editable'])@foreach (['name' => $n['nombre'], 'age' => $n['edad'], 'allergies' => $n['alergias']] as $col => $valor)@if ($n['campos'][$col] !== null)<input type="hidden" name="{{ $n['campos'][$col] }}" value="{{ $valor }}">@endif @endforeach @endif{{ '' }}@foreach ($n['extra'] as $clave => $valor)<input type="hidden" name="guests[{{ $n['indice'] }}][{{ $clave }}]" value="{{ $valor }}">@endforeach</x-slot:oculto>
    <x-slot:chapa>@if ($n['repetida'])<x-pieza.chapa tone="neutral" size="sm">{{ __('guestform.invite.repeated') }}</x-pieza.chapa>@endif{{ '' }}@if ($n['sin_producto'])<x-pieza.chapa tone="warn" size="sm">{{ __('fiesta.lista.la_lista.sin_producto') }}</x-pieza.chapa>@elseif ($n['regimen'] !== null)<x-pieza.chapa tone="neutral" size="sm">{{ $n['regimen'] }}</x-pieza.chapa>@endif</x-slot:chapa>
</x-fiesta.fila-invitado>
