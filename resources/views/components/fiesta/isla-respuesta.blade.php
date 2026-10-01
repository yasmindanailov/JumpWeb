@props(['action' => '', 'fieldName' => 'child_name', 'inputId' => 'rsvp-nino', 'value' => '', 'deadline' => '', 'closed' => false, 'error' => '', 'labels' => [], 'closedNote' => null, 'notice' => null, 'tercero' => null])
@php
    /*
     * LA RESPUESTA a una invitación, dentro de la isla de enlace (`LinkIsland.jsx` · `Answer` y `Line`, `#814`; antes,
     * `x-fiesta.rsvp-bar`): el plazo, el nombre del niño y «Vamos» o «No podemos». Un <form> de verdad con dos botones de
     * enviar (`attending=1|0`, el contrato del controlador de siempre): sin JavaScript contesta igual. Intro no contesta
     * (lo quita `fiesta/invitacion.js`): la respuesta es un toque. El campo es SUYO: con el teclado abierto, la isla se queda.
     * Pasado el plazo, la LÍNEA (`closed`): por qué ya no hay respuesta y «Llamar», si quien invita dejó su teléfono, en UNA
     * fila como el `Line` del diseño (el texto se parte, el botón no baja; sin el relleno del formulario: medido en la
     * pasada ligera de `#768`, con `flex-wrap` «Llamar» caía debajo).
     * Lo que pasó con lo contestado (`notice`: un rechazo, el enlace caducado) va encima de las dos.
     * Nunca dice quién más va ni si un nombre ya contestó, tampoco en el error.
     * ⚠️ `tercero`: la caja del anti-robot (Turnstile), entre el campo y los botones. Es un tercero: no se recolorea.
     */
    $L = array_merge([
        'field' => __('fiesta.invitacion_pagina.campo'), 'yes' => __('fiesta.invitacion_pagina.si'),
        'no' => __('fiesta.invitacion_pagina.no'), 'sending' => __('fiesta.invitacion_pagina.enviando'),
    ], $labels);
    $linea = 'margin: 0; display: flex; align-items: flex-start; gap: 7px; font-family: var(--font-ui); line-height: 1.4;';
@endphp
<form method="post" action="{{ $action }}" novalidate style="display: grid; gap: 8px; padding: {{ $closed ? '0' : '4px 4px 2px' }};" {{ $attributes }} data-rsvp data-enviando="{{ $L['sending'] }}">@csrf{{ '' }}@if ($notice)<div style="padding: 2px 4px 0;">{{ $notice }}</div>@endif{{ '' }}@if ($closed)<div role="status" style="display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 4px 5px 4px 18px; font-family: var(--font-ui);"><span aria-hidden="true" style="display: inline-flex; flex: 0 0 auto; color: var(--text-muted);"><x-lucide name="lock" :size="17" /></span>{{ $closedNote }}</div>@else{{ '' }}@if ($deadline !== '')<p style="{{ $linea }} padding: 2px 8px 0; font-size: var(--fs-caption); font-weight: var(--fw-bold); color: var(--text-muted);"><span aria-hidden="true" style="display: inline-flex; margin-top: 1px;"><x-lucide name="clock" :size="14" /></span>{{ $deadline }}</p>@endif<x-pieza.campo :id="$inputId" :name="$fieldName" :label="$L['field']" :value="$value" :error="$error" autocomplete="off" autocapitalize="words" enterkeyhint="done" data-rsvp-nombre />{{ $tercero }}<div style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px;"><x-pieza.boton type="submit" name="attending" value="1" variant="primary" size="md" full data-rsvp-si>{{ $L['yes'] }}</x-pieza.boton><x-pieza.boton type="submit" name="attending" value="0" variant="quiet" size="md" style="white-space: nowrap; padding-left: 18px; padding-right: 18px;" data-rsvp-no>{{ $L['no'] }}</x-pieza.boton></div>@endif</form>
