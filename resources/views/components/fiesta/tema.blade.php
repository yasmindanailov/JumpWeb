@props(['name' => 'tema', 'items' => [], 'value' => null, 'label' => '', 'age' => '', 'vivo' => false])
@php
    /*
     * EL TEMA de la invitación, elegido viendo su banda (`invitados/ThemePicker.jsx`): radios nativos debajo, el elegido
     * y el anillo de foco por `:has()` (`.fi-tema` en `fiesta.css`). De tres en tres; con el kit de ilustración serán
     * seis. Cada `item`: `value`, `label`, `note`. Con `vivo` (F2), las miniaturas llevan la chapa de la edad siempre,
     * oculta sin edad, para que la edad tecleada en «Personalizar» se vea también aquí, como en el diseño.
     */
    $columnas = max(1, min(3, count($items)));
@endphp
<fieldset {{ $attributes->class('fi-tema') }}>
@if ($label !== '')<legend class="fi-tema__legend">{{ $label }}</legend>@endif
<div class="fi-tema__rejilla" style="grid-template-columns: repeat({{ $columnas }}, minmax(0, 1fr));">
@foreach ($items as $it)
<label class="fi-tema__item"><input type="radio" name="{{ $name }}" value="{{ $it['value'] }}" @checked($value === $it['value']) class="fi-tema__input"><x-fiesta.invitacion mini :theme="$it['value']" :age="$age" :vivo="$vivo" /><span class="fi-tema__pie"><span aria-hidden="true" class="fi-tema__punto"><span class="fi-tema__marca"><x-lucide name="check" :size="12" /></span></span><span class="fi-tema__textos"><span class="fi-tema__nombre">{{ $it['label'] }}</span>@if (! empty($it['note']))<span class="fi-tema__nota">{{ $it['note'] }}</span>@endif</span></span></label>
@endforeach
</div>
</fieldset>