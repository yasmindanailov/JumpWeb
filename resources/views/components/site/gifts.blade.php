{{-- LOS REGALOS de un producto (`#589`, `[DECIDIDO owner]`): lo que el parque da sin cobrar, cada uno
     en su etiqueta amarilla con la caja de regalo delante. Sale del campo `gifts` del catálogo, que el
     panel escribe APARTE de lo que el producto incluye: mezclados en las ventajas no se veían.
     ▶ Gemelo de `resources/js/sidebar/GiftList.vue`: mismas clases, así que la web y el cajón los
     pintan con UNA regla de CSS (`site.css`, «Los regalos»).
     ⚠️ `tag="span"` para ir dentro de un enlace o de un botón —la tarjeta de cumpleaños de la
     portada—, donde una lista no es contenido válido.
     ⚠️ Sin regalos no pinta nada, ni el contenedor: un hueco vacío movería la tarjeta. --}}
@props(['gifts' => [], 'tag' => 'ul'])
@php([$outer, $item] = $tag === 'span' ? ['span', 'span'] : ['ul', 'li'])
@if ($gifts !== [])
    <{{ $outer }} {{ $attributes->class('gifts') }} @if ($outer === 'ul') role="list" @endif>
        @foreach ($gifts as $gift)
            <{{ $item }} class="gift">
                <x-icons.gift width="16" height="16" />
                <span><span class="sr-only">{{ __('tickets.gifts_label') }}: </span>{{ $gift }}</span>
            </{{ $item }}>
        @endforeach
    </{{ $outer }}>
@endif
