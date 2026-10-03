{{-- La gemela de texto del pie: tras una raya, el parque, dónde, el horario de hoy y cómo hablar con él; en un comercial, por
     qué lo recibe y la baja (con su dirección: `rico()` la pone detrás). --}}
@php
    $p = $correo->pie;
    $hablar = array_filter([$p->telefono, $p->whatsapp !== null ? 'WhatsApp: https://wa.me/'.$p->whatsapp : null, $p->correo]);
@endphp
@if ($correo->responde !== null)
{!! $correo->responde !!}

@endif
{!! implode("\n", array_filter(['—', $p->nombre, $p->direccion, $p->hoy, $hablar !== [] ? implode(' · ', $hablar) : null])) !!}

@if ($correo->comercial !== null)
{!! $correo->rico($correo->comercial)['t'] !!}

@endif
{!! implode("\n", array_map(static fn (array $l) => $l[0].': '.$l[1], $correo->legales)) !!}
