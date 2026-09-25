@props(['label' => null, 'state' => 'clean', 'status' => '', 'detail' => '', 'aviso' => null, 'sticky' => false, 'loadingLabel' => '', 'buttonType' => 'button'])
@php
    /*
     * EL ÚNICO GUARDAR de una página que se rellena a ratos (`forms/SaveBar.jsx`): dice en qué estado está lo tecleado
     * y lleva el botón que escribe. Con cambios sin guardar va pegada abajo; sin nada que guardar, descansa al
     * final. Es de tinta, como la isla: la única pieza que flota. Estilos EN LÍNEA como el JSX; la superficie de
     * tinta (`data-surface="ink"`) cambia los tokens de texto y control (respaldo en `fiesta.css`).
     */
    $iconos = ['dirty' => ['pencil-line', 'var(--icon-accent)'], 'saved' => ['circle-check', 'var(--text-positive)'], 'clean' => ['circle-check', 'var(--text-muted)'], 'conflict' => ['triangle-alert', 'var(--text-low)'], 'saving' => [null, null]];
    $ic = $iconos[$state] ?? $iconos['clean'];
    $nada = $state === 'clean' || $state === 'saved';
    $estilo = 'position: '.($sticky ? 'sticky' : 'relative').';'.($sticky ? ' bottom: calc(12px + env(safe-area-inset-bottom, 0px));' : '').' z-index: 30; display: grid; gap: 10px; padding: 10px; border-radius: var(--r-xl);'
        .' background: '.($sticky ? 'var(--surface-glass-ink-float)' : 'var(--ink-surface)').'; backdrop-filter: var(--blur-island); -webkit-backdrop-filter: var(--blur-island);'
        .' border: 1px solid var(--surface-glass-ink-border); box-shadow: '.($sticky ? 'var(--shadow-island-float)' : 'inset 0 1px 0 rgba(255, 255, 255, 0.14)').'; color: var(--text-body);';
    if ($attributes->has('style')) {
        $estilo .= ' '.$attributes->get('style');
    }
@endphp
<div data-surface="ink" style="{{ $estilo }}" {{ $attributes->except('style') }} data-barra data-estado="{{ $state }}">@if ($aviso)<div style="padding: 4px 4px 0;" data-barra-aviso>{{ $aviso }}</div>@endif<div style="display: flex; align-items: center; gap: 12px; padding-left: 8px;"><div aria-live="polite" style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">@if ($ic[0])<span aria-hidden="true" style="display: inline-flex; flex: 0 0 auto; color: {{ $ic[1] }};" data-barra-icono><x-lucide :name="$ic[0]" :size="20" /></span>@endif<span style="display: grid; gap: 1px; min-width: 0;"><span style="font-family: var(--font-ui); font-size: var(--fs-body-sm); font-weight: var(--fw-bold); line-height: 1.3; color: var(--text-strong);" data-barra-estado>{{ $status }}</span>@if ($detail !== '')<span style="font: var(--type-mono); font-size: 12px; color: var(--text-muted);" data-barra-detalle>{{ $detail }}</span>@endif</span></div><x-pieza.boton :type="$buttonType" :variant="$nada ? 'quiet' : 'primary'" size="md" :disabled="$nada" :loading="$state === 'saving'" :loadingLabel="$loadingLabel" style="flex: 0 0 auto; min-width: 132px;" data-barra-boton>{{ $label ?? __('fiesta.barra.label') }}</x-pieza.boton></div><template data-barra-iconos><span data-icono="dirty" style="color: var(--icon-accent);"><x-lucide name="pencil-line" :size="20" /></span><span data-icono="saved" style="color: var(--text-positive);"><x-lucide name="circle-check" :size="20" /></span><span data-icono="clean" style="color: var(--text-muted);"><x-lucide name="circle-check" :size="20" /></span><span data-icono="conflict" style="color: var(--text-low);"><x-lucide name="triangle-alert" :size="20" /></span></template></div>