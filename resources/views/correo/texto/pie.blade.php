{{-- La gemela de texto del pie: tras una raya, el parque, dónde, el horario de hoy y cómo hablar con él. --}}
@php
    $p = $correo->pie;
    $hablar = array_filter([$p->telefono, $p->whatsapp !== null ? 'WhatsApp: https://wa.me/'.$p->whatsapp : null, $p->correo]);
@endphp
{!! implode("\n", array_filter(['—', $p->nombre, $p->direccion, $p->hoy, $hablar !== [] ? implode(' · ', $hablar) : null])) !!}

{!! implode("\n", array_map(static fn (array $l) => $l[0].': '.$l[1], $correo->legales)) !!}
