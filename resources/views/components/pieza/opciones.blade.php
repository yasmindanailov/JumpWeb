@props(['name', 'items' => [], 'value' => null, 'label' => '', 'hint' => '', 'error' => '', 'columns' => 'auto', 'size' => 'md'])
@php
    /*
     * El grupo de radios en forma de tarjeta (`forms/OptionCards.jsx`). Radio nativo debajo: el teclado y los lectores
     * de pantalla funcionan solos; el elegido y el `hover` los lee el CSS (`.pz-opciones` en `fiesta.css`). Con un
     * número de columnas: N o una, nunca un paso intermedio (la fórmula del diseño). Cada `item`: `value`, `title`,
     * `description`, `price`, `was`, `includes[]`, `note`, `highlight`, `image`, `imageAlt`, `disabled`.
     */
    $n = (int) $columns ?: 2;
    $cols = $columns === 'auto'
        ? 'repeat(auto-fit, minmax(210px, 1fr))'
        : 'repeat(auto-fit, minmax(clamp(calc((100% - '.($n - 1).' * 12px) / '.$n.'), calc(('.($n * 17).'rem - 100%) * 999), 100%), 1fr))';
    $clases = 'pz-opciones'.($hint !== '' ? ' pz-opciones--pista' : '').($size === 'lg' ? ' pz-opciones--lg' : '').($error !== '' ? ' pz-opciones--error' : '');
@endphp
<fieldset {{ $attributes->class($clases) }}>
@if ($label !== '')<legend class="pz-opciones__legend">{{ $label }}</legend>@endif
@if ($hint !== '')<p class="pz-opciones__pista">{{ $hint }}</p>@endif
<div class="pz-opciones__rejilla" style="grid-template-columns: {{ $cols }};">
@foreach ($items as $it)
@php($on = $value !== null && (string) $value === (string) $it['value'])
<label class="pz-opciones__tarjeta"><input type="radio" name="{{ $name }}" value="{{ $it['value'] }}" @checked($on) @disabled($it['disabled'] ?? false) class="pz-opciones__input"><span aria-hidden="true" class="pz-opciones__punto"><span class="pz-opciones__marca"><x-lucide name="check" :size="14" /></span></span>@if (! empty($it['image']))<img src="{{ $it['image'] }}" alt="{{ $it['imageAlt'] ?? $it['title'] ?? '' }}" class="pz-opciones__mini">@endif<span class="pz-opciones__texto"><span class="pz-opciones__fila"><span class="pz-opciones__titulo">@if (! empty($it['icon']))<span class="pz-opciones__icono">{{ $it['icon'] }}</span>@endif{{ $it['title'] ?? '' }}</span>@if (! empty($it['price']))<span class="pj-num pz-opciones__precio">@if (! empty($it['was']))<s class="pz-opciones__antes">{{ $it['was'] }}</s>@endif{{ $it['price'] }}</span>@endif</span>@if (! empty($it['description']))<span class="pz-opciones__desc">{{ $it['description'] }}</span>@endif @if (! empty($it['includes']))<span class="pz-opciones__incluye">@foreach ($it['includes'] as $x)<span><span class="pz-opciones__check"><x-lucide name="check" :size="13" /></span>{{ $x }}</span>@endforeach</span>@endif @if (! empty($it['note']))<span class="pz-opciones__nota">{{ $it['note'] }}</span>@endif @if (! empty($it['highlight']))<span class="pz-opciones__destacado">{{ $it['highlight'] }}</span>@endif</span></label>
@endforeach
</div>
@if ($error !== '')<p class="pz-opciones__error">{{ $error }}</p>@endif
</fieldset>