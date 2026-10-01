@props(['label' => '', 'sub' => '', 'icon' => 'arrow-right', 'primary' => false, 'href' => null, 'download' => null, 'target' => null, 'form' => null, 'dot' => null])
@php
    /*
     * LA CARA DE BARRA de la isla de enlace (`LinkIsland.jsx` · `Bar`, la cara de barra de B2): la etiqueta —el verbo—, su
     * línea EXACTA debajo (el plazo con su día, cuántos cambios, qué falta por su nombre; nunca «tienes cosas pendientes») y
     * el círculo con su icono, NARANJA solo si es la acción principal (`primary`). Se toca entera.
     * ⚠️ Con `href`, un enlace (el calendario, compartir); con `form`, el botón de ENVIAR de ese formulario aunque viva fuera
     *    de él (la lista, la firma): sin JavaScript envía igual.
     * ⚠️ Sus estados (`:hover`, pulsada, ocupada) van por clase (`.fi-isla-barra*`). Los ganchos `data-isla-*` son lo que
     *    `fiesta/isla.js` cambia al cambiar de cara.
     */
    $tag = $href !== null ? 'a' : 'button';
@endphp
<{{ $tag }}@if ($href !== null) href="{{ $href }}"@if ($download !== null) download="{{ $download }}"@endif{{ '' }}@if ($target !== null) target="{{ $target }}" rel="noopener noreferrer"@endif{{ '' }}@else type="{{ $form !== null ? 'submit' : 'button' }}"@if ($form !== null) form="{{ $form }}"@endif{{ '' }}@endif {{ $attributes->class(['fi-isla-barra', 'fi-isla-barra--primary' => $primary]) }} data-cara="barra"><span class="fi-isla-barra-texto"><b data-isla-label>{{ $label }}</b><small class="fi-isla-barra-sub" aria-live="polite"@if ($sub === '') hidden @endif data-isla-sub><span class="fi-isla-punto"@if ($dot === null) hidden @else style="background: {{ $dot }};"@endif data-isla-punto></span><span data-isla-sub-texto>{{ $sub }}</span></small></span><span class="fi-isla-circulo" aria-hidden="true" data-isla-circulo><x-lucide :name="$icon" :size="19" /></span></{{ $tag }}>
