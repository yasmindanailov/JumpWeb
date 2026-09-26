{{-- «TUS RESPUESTAS» (`InvMias`, F6b de la spec §4.12): el hueco que `invitacion.js` llena con los niños contestados
     DESDE ESTE MÓVIL (su `localStorage`, 24 h, como el enlace del recibo). En el recibo lleva lo que hay que guardar
     (`data-mia`: id y nombre de pila). `data-fiesta` es el id de la invitación, nunca su token. Sin JavaScript, escondido. --}}
<div class="inv-mias" data-mias hidden data-fiesta="{{ $m['mias']['fiesta'] }}"@if ($m['mias']['mia'] !== null) data-mia="{{ json_encode($m['mias']['mia'], JSON_UNESCAPED_UNICODE) }}"@endif><span>{{ __('fiesta.invitacion_pagina.tus') }}</span></div>
