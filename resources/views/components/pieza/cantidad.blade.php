@props(['label' => '', 'sublabel' => '', 'price' => '', 'value' => 0, 'min' => 0, 'max' => 20, 'format' => null, 'variant' => 'card', 'labels' => null, 'name' => null, 'id' => null])
@php
    /*
     * El selector de cantidad (`forms/QuantityStepper.jsx`). Sin JavaScript es un campo numérico nativo (el que viaja
     * en el formulario); con él (`html.js`), los botones − y + y la cifra, que el JS de la página mantiene en el
     * campo (`data-cantidad`). `variant="card"`: tarjeta con etiqueta, precio y botones; `bare`: solo − cifra +. Con
     * `format` («:n niños») la cifra se lee con su unidad. El estilo, `.pz-cantidad` en `fiesta.css`.
     * ⚠️ El campo nativo y los botones son el MISMO valor: el diseño pinta uno u otro, aquí van los dos y el CSS
     *    enseña el que toca, así el valor viaja siempre por el campo.
     * ⚠️ Al día con el zip del 25-09 (el lado del precio ENVUELVE y sube encima de los botones si no cabe). La prop
     *    `editable` del diseño (la cifra se escribe: 30–100 alumnos de Colegios) NO está portada: la fiesta no la usa.
     */
    $rotulos = $labels ?? [__('fiesta.pieza.uno_menos'), __('fiesta.pieza.uno_mas')];
    $texto = $format !== null ? str_replace(':n', (string) $value, $format) : (string) $value;
    $infinito = ! is_finite((float) $max);
    $clases = 'pz-cantidad pz-cantidad--'.$variant.($value > 0 ? ' pz-cantidad--con' : '').($format !== null ? ' pz-cantidad--formato' : '');
@endphp
<div {{ $attributes->class($clases) }} data-cantidad data-min="{{ $min }}" @unless ($infinito) data-max="{{ $max }}" @endunless @if ($format !== null) data-formato="{{ $format }}" @endif>
@if ($variant === 'card')<div class="pz-cantidad__rotulo"><span class="pz-cantidad__label">{{ $label }}</span>@if ($sublabel !== '')<span class="pz-cantidad__sub">{{ $sublabel }}</span>@endif</div><div class="pz-cantidad__lado">@if ($price !== '')<span class="pz-cantidad__precio">{{ $price }}</span>@endif @endif
<input type="number" @if ($id !== null)id="{{ $id }}" @endif{{ '' }}@if ($name !== null)name="{{ $name }}" @endif{{ '' }}value="{{ $value }}" min="{{ $min }}" @unless ($infinito) max="{{ $max }}" @endunless step="1" inputmode="numeric" aria-label="{{ $label }}" class="pz-cantidad__nativo" data-cantidad-campo>
<div class="pz-cantidad__botones"><button type="button" aria-label="{{ $rotulos[0] }}" @disabled($value <= $min) class="pz-cantidad__boton" data-cantidad-menos><x-lucide name="minus" :size="18" /></button><output aria-live="polite" class="pz-cantidad__valor" data-cantidad-valor>{{ $texto }}</output><button type="button" aria-label="{{ $rotulos[1] }}" @disabled(! $infinito && $value >= $max) class="pz-cantidad__boton" data-cantidad-mas><x-lucide name="plus" :size="18" /></button></div>
@if ($variant === 'card')</div>@endif
</div>