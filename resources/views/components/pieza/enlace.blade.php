@props(['href' => null, 'variant' => 'default', 'size' => 'md', 'arrow' => false, 'external' => false, 'block' => false, 'underline' => 'hover', 'icono' => null, 'disabled' => false])
@php
    /*
     * El enlace del sistema (`core/Link.jsx`): la acción secundaria, nunca compite con el color de acción. Con `href`
     * pinta `<a>`; sin él, `<button>` con la misma cara. El área de toque de 44 px es un `span` absoluto que no mueve
     * el texto (sin `block`). El estilo, `.pz-enlace` en `fiesta.css`; el foco vale como el `hover`, como en el diseño.
     * ⚠️ Sin espacios alrededor: el JSX no deja ninguno entre el icono y el texto.
     * ⚠️ El `type` del botón es `button` SALVO que se pase otro (`type="submit" form="…"`, el recordatorio de la lista):
     *    un `type="button"` escrito delante ganaba al `type="submit"` de los atributos y el botón no enviaba nunca (T1b).
     */
    $clases = 'pz-enlace pz-enlace--'.$size.' pz-enlace--'.$variant
        .($block ? ' pz-enlace--block' : '')
        .($arrow ? ' pz-enlace--arrow' : '')
        .($underline === 'always' ? ' pz-enlace--always' : ($underline === 'none' ? ' pz-enlace--none' : ''));
    $iconoTam = ['sm' => 14, 'md' => 16, 'lg' => 18][$size] ?? 16;
    $cola = $external || $arrow;
@endphp
@if ($href && ! $disabled)<a href="{{ $href }}" @if ($external)target="_blank" rel="noopener noreferrer" @endif{{ '' }}{{ $attributes->class($clases) }}>@else<button {{ $attributes->class($clases)->merge(['type' => 'button'] + ($disabled ? ['disabled' => true] : [])) }}>@endif{{ '' }}@unless ($block)<span aria-hidden="true" class="pz-enlace__toque"></span>@endunless<span class="pz-enlace__cuerpo">@if ($icono)<span class="pz-enlace__icono">{{ $icono }}</span>@endif<span class="pz-enlace__texto">{{ $slot }}</span></span>@if ($cola)<span class="pz-enlace__cola">@if ($external)<x-lucide name="arrow-up-right" :size="$iconoTam" />@endif @if ($arrow)<x-lucide :name="$block ? 'chevron-right' : 'arrow-right'" :size="$iconoTam" />@endif</span>@endif{{ '' }}@if ($external)<span class="pz-sr">(se abre en otra pestaña)</span>@endif{{ '' }}@if ($href && ! $disabled)</a>@else</button>@endif