@props(['tone' => 'info', 'icono' => null, 'title' => '', 'accion' => null, 'size' => 'md', 'role' => null])
@php
    /*
     * El aviso destacado (`content/InfoCallout.jsx`), con sus estilos EN LÍNEA como el JSX: un tono, tres tokens
     * (`--notice-<tono>-*`), los mismos nombres sobre claro y sobre tinta. `size="sm"`, el de dentro de una barra. El
     * tono `danger` grande lleva su franja de peligro arriba.
     */
    $k = in_array($tone, ['info', 'warn', 'danger', 'success', 'neutral'], true) ? $tone : 'info';
    $sm = $size === 'sm';
    $relleno = $sm ? '12px 14px' : ($tone === 'danger' ? 'calc(var(--space-5) + 4px) var(--space-5) var(--space-5)' : 'var(--space-5)');
    $estilo = 'position: relative; overflow: hidden; display: flex; align-items: flex-start; gap: '.($sm ? '10px' : 'var(--space-4)').'; padding: '.$relleno.';'
        .' background: var(--notice-'.$k.'-bg); border-radius: '.($sm ? 'var(--r-md)' : 'var(--r-lg)').'; box-shadow: inset 0 0 0 1px var(--notice-'.$k.'-border);';
    if ($attributes->has('style')) {
        $estilo .= ' '.$attributes->get('style');
    }
@endphp
<aside @if ($role)role="{{ $role }}" @endif{{ '' }}style="{{ $estilo }}" {{ $attributes->except('style') }}>@if ($tone === 'danger' && ! $sm)<span aria-hidden="true" style="position: absolute; top: 0; left: 0; right: 0; height: 6px; background: repeating-linear-gradient(135deg, var(--danger-500) 0 9px, var(--fiesta-tinta-900) 9px 18px);"></span>@endif{{ '' }}@if ($icono)<span style="color: var(--notice-{{ $k }}-fg); display: flex; flex: 0 0 auto; margin-top: 1px;">{{ $icono }}</span>@endif<div style="display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1;">@if ($title !== '')<strong style="font-family: var(--font-ui); font-size: {{ $sm ? 'var(--fs-body-sm)' : 'var(--fs-body)' }}; font-weight: var(--fw-bold); line-height: 1.35; color: {{ $sm ? 'var(--text-strong)' : 'var(--notice-'.$k.'-fg)' }};">{{ $title }}</strong>@endif{{ '' }}@if ($slot->isNotEmpty())<div style="font: var(--type-body); font-size: var(--fs-body-sm); line-height: 1.5; color: var(--notice-text); text-wrap: pretty;">{{ $slot }}</div>@endif</div>@if ($accion)<div style="flex: 0 0 auto;">{{ $accion }}</div>@endif</aside>