@props(['action' => '', 'method' => 'post', 'fieldName' => 'child_name', 'inputId' => 'rsvp-nino', 'value' => '', 'deadline' => '', 'closed' => false, 'error' => '', 'sticky' => true, 'animate' => false, 'labels' => [], 'closedNote' => null, 'notice' => null, 'tercero' => null])
@php
    /*
     * LA RESPUESTA a una invitación, siempre a la vista (`invitados/RsvpBar.jsx`), con sus estilos EN LÍNEA como el JSX:
     * el nombre del niño y «Vamos» o «No podemos». Un <form> de verdad con dos botones de enviar (`attending=1|0`, el
     * contrato del controlador de siempre): sin JavaScript contesta igual. Intro no contesta (el JS de la página lo
     * quita): la respuesta es siempre un toque. De tinta, como la isla, y de cristal cuando va pegada abajo. Nunca dice
     * quién más va ni si un nombre ya contestó, tampoco en el error.
     * ⚠️ `tercero`: la caja del anti-robot (Turnstile), entre el campo y los botones. Es un tercero: no se recolorea.
     */
    $L = array_merge([
        'field' => __('fiesta.invitacion_pagina.campo'), 'yes' => __('fiesta.invitacion_pagina.si'),
        'no' => __('fiesta.invitacion_pagina.no'), 'sending' => __('fiesta.invitacion_pagina.enviando'),
    ], $labels);
    $estilo = 'position: '.($sticky ? 'sticky' : 'relative').';'.($sticky ? ' bottom: calc(10px + env(safe-area-inset-bottom, 0px));' : '')
        .' z-index: 30; display: grid; gap: 8px; padding: 10px 12px 12px; border-radius: var(--r-xl);'
        .' animation: '.($animate ? 'fiesta-bar-rise 560ms var(--ease-spring) 650ms both' : 'none').';'
        .' background: '.($sticky ? 'var(--surface-glass-ink-float)' : 'var(--ink-surface)').'; backdrop-filter: var(--blur-island); -webkit-backdrop-filter: var(--blur-island);'
        .' border: 1px solid var(--surface-glass-ink-border);'
        .' box-shadow: '.($sticky ? 'var(--shadow-island-float)' : 'inset 0 1px 0 rgba(255, 255, 255, 0.14)').'; color: var(--text-body);';
    if ($attributes->has('style')) {
        $estilo .= ' '.$attributes->get('style');
    }
    $linea = 'margin: 0; display: flex; align-items: flex-start; gap: 7px; font-family: var(--font-ui); line-height: 1.4;';
@endphp
<form data-surface="ink" method="{{ $method }}" action="{{ $action }}" novalidate style="{{ $estilo }}" {{ $attributes->except('style') }} data-rsvp data-enviando="{{ $L['sending'] }}">@if (strtolower($method) === 'post')@csrf @endif{{ '' }}@if ($notice)<div style="padding: 2px 4px 0;">{{ $notice }}</div>@endif{{ '' }}@if ($closed)<div role="status" style="{{ $linea }} flex-wrap: wrap; align-items: center; padding: 6px 6px; font-size: var(--fs-body-sm); font-weight: var(--fw-bold); color: var(--text-strong);"><span aria-hidden="true" style="display: inline-flex; color: var(--text-muted);"><x-lucide name="lock" :size="17" /></span>{{ $closedNote }}</div>@else{{ '' }}@if ($deadline !== '')<p style="{{ $linea }} padding: 2px 6px 0; font-size: var(--fs-caption); font-weight: var(--fw-bold); color: var(--text-muted);"><span aria-hidden="true" style="display: inline-flex; margin-top: 1px;"><x-lucide name="clock" :size="14" /></span>{{ $deadline }}</p>@endif<x-pieza.campo :id="$inputId" :name="$fieldName" :label="$L['field']" :value="$value" :error="$error" autocomplete="off" autocapitalize="words" enterkeyhint="done" data-rsvp-nombre />{{ $tercero }}<div style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px;"><x-pieza.boton type="submit" name="attending" value="1" variant="primary" size="md" full data-rsvp-si>{{ $L['yes'] }}</x-pieza.boton><x-pieza.boton type="submit" name="attending" value="0" variant="quiet" size="md" style="white-space: nowrap; padding-left: 18px; padding-right: 18px;" data-rsvp-no>{{ $L['no'] }}</x-pieza.boton></div>@endif</form>
