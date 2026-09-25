@props(['name' => '', 'line' => '', 'serves' => '', 'price' => '', 'due' => '', 'changeNote' => '', 'image' => null, 'imageNote' => '', 'value' => 0, 'min' => 0, 'max' => 10, 'closed' => false, 'reason' => null, 'total' => '', 'inputName' => null, 'inputId' => null, 'labels' => []])
@php
    /*
     * UN COMPLEMENTO que se pide después de reservar (`invitados/AddonCard.jsx`): su foto real, una línea de qué lleva,
     * para cuántos es, su precio, su plazo y la cantidad con − y +. Pedido, el plazo pasa a «Lo cambias hasta…».
     * Fuera de plazo, la tarjeta se queda a la vista con su motivo (la ranura `reason`). La tarjeta con pedido lleva
     * el borde fuerte (`.fi-complemento--con`); lo demás, EN LÍNEA como el JSX.
     */
    $L = array_merge(['less' => __('fiesta.complemento.less'), 'more' => __('fiesta.complemento.more')], $labels);
    $pedido = $value > 0 && ! $closed;
@endphp
<article {{ $attributes->class(['fi-complemento', 'fi-complemento--con' => $pedido]) }}>
<div style="opacity: {{ $closed ? '0.6' : '1' }};"><x-pieza.marco kind="image" :src="$image" :alt="$name" :note="$imageNote" aspect="16 / 7" rounded="0" flat /></div>
<div style="display: grid; gap: 6px; padding: 14px 16px 16px;">
<div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 2px 12px;"><h4 style="margin: 0; font-family: var(--font-ui); font-size: var(--fs-body); font-weight: var(--fw-bold); letter-spacing: 0; color: var(--text-strong);">{{ $name }}</h4><span class="pj-num" style="font-family: var(--font-mono); font-size: var(--fs-body-sm); font-weight: 500; color: var(--text-strong); white-space: nowrap;">{{ $price }}</span></div>
@if ($line !== '')<p style="margin: 0; font-family: var(--font-ui); font-size: var(--fs-body-sm); line-height: 1.45; color: var(--text-body);">{{ $line }}</p>@endif
@if ($serves !== '')<p style="margin: 0; display: flex; align-items: center; gap: 6px; font-family: var(--font-ui); font-size: var(--fs-caption); font-weight: var(--fw-bold); color: var(--text-strong);"><span aria-hidden="true" style="display: inline-flex; color: var(--icon-accent);"><x-lucide name="users" :size="15" /></span>{{ $serves }}</p>@endif
@if ($closed)
<x-pieza.aviso size="sm" tone="warn" style="margin-top: 6px;"><x-slot:icono><x-lucide name="clock-alert" :size="17" /></x-slot:icono>{{ $reason }}@if ($value > 0)<span style="display: block; color: var(--text-muted);">{{ trans_choice('fiesta.complemento.ordered', $value, ['n' => $value]) }}</span>@endif</x-pieza.aviso>
@if ($inputName !== null)<input type="hidden" name="{{ $inputName }}" value="{{ $value }}">@endif
@else
<div style="display: flex; flex-wrap: nowrap; align-items: flex-start; justify-content: space-between; gap: 12px; margin-top: 4px;"><span style="display: grid; gap: 2px; min-width: 0; flex: 1 1 auto; align-self: center; min-height: 0; overflow-wrap: anywhere;">@if ($value > 0 && $changeNote !== '')<span style="font-family: var(--font-ui); font-size: var(--fs-caption); font-weight: var(--fw-semibold); color: var(--text-muted);">{{ $changeNote }}</span>@elseif ($due !== '')<span style="font-family: var(--font-ui); font-size: var(--fs-caption); font-weight: var(--fw-bold); color: var(--text-low);">{{ $due }}</span>@endif{{ '' }}@if ($value > 0 && $total !== '')<span class="pj-num" style="font: var(--type-mono); color: var(--text-strong);" data-total>{{ $total }}</span>@else<span class="pj-num" style="font: var(--type-mono); color: var(--text-strong);" data-total hidden></span>@endif</span><x-pieza.cantidad variant="bare" :label="$name" :value="$value" :min="$min" :max="$max" :labels="[$L['less'].': '.$name, $L['more'].': '.$name]" :name="$inputName" :id="$inputId" style="flex: 0 0 auto;" /></div>
@endif
</div>
</article>