@props(['items' => [], 'confirm' => null, 'value' => '', 'align' => 'start', 'tone' => 'light'])
@php
    /*
     * Compartir (`marketing/ShareRow.jsx`): píldoras tranquilas, nunca el color de acción. Cada `item`: `kind`
     * (`whatsapp`, `email`, `copy`, `link`), `label`, `href`. El de `copy` lo atiende el JS de la página
     * (`data-compartir`), que copia `value` y enseña la confirmación 2,6 s; sin JS, el enlace escrito sigue en la
     * página. El estilo, `.pz-compartir` en `fiesta.css`.
     */
    $iconos = ['whatsapp' => 'message-circle', 'email' => 'mail', 'copy' => 'link', 'link' => 'arrow-up-right'];
    $clases = 'pz-compartir'.($align === 'center' ? ' pz-compartir--center' : '').($tone === 'ink' ? ' pz-compartir--ink' : '');
@endphp
<div {{ $attributes->class($clases) }} data-compartir data-valor="{{ $value }}">
@foreach ($items as $it)
@php($fuera = ! empty($it['href']) && str_starts_with($it['href'], 'http'))
@if (! empty($it['href']))<a href="{{ $it['href'] }}" @if ($fuera) target="_blank" rel="noopener noreferrer" @endif class="pz-compartir__item" data-kind="{{ $it['kind'] ?? 'link' }}"><x-lucide :name="$iconos[$it['kind'] ?? ''] ?? 'share-2'" :size="18" />{{ $it['label'] }}</a>@else<button type="button" class="pz-compartir__item" data-kind="{{ $it['kind'] ?? 'link' }}"><x-lucide :name="$iconos[$it['kind'] ?? ''] ?? 'share-2'" :size="18" />{{ $it['label'] }}</button>@endif
@endforeach
<span role="status" class="pz-compartir__ok" hidden data-compartir-ok><x-lucide name="check" :size="16" />{{ $confirm ?? __('fiesta.pieza.enlace_copiado') }}</span>
</div>