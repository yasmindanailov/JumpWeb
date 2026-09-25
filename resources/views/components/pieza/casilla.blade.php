@props(['label' => '', 'description' => '', 'checked' => false, 'disabled' => false, 'required' => false, 'error' => '', 'id' => null, 'name' => null, 'value' => '1'])
@php
    /*
     * La casilla del sistema (`forms/Checkbox.jsx`): el estado marcado y el foco los lee el CSS con `:has()`
     * (`.pz-casilla` en `fiesta.css`). `children` (la ranura) va debajo de la casilla, fuera de la zona que marca.
     * El error se dice con palabras, no solo en rojo (WCAG 3.3.1).
     */
    $cid = $id ?: 'cb-'.substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($label ?: 'x')), 0, 24);
    $msg = strlen($error) > 1 ? $error : '';
@endphp
<div @class(['pz-casilla', 'pz-casilla--error' => $error !== ''])>
<label for="{{ $cid }}" class="pz-casilla__label" {{ $attributes->except(['class']) }}><input id="{{ $cid }}" type="checkbox" @if ($name !== null) name="{{ $name }}" @endif value="{{ $value }}" @checked($checked) @disabled($disabled) @required($required) @if ($error !== '') aria-invalid="true" @endif @if ($msg !== '') aria-describedby="{{ $cid }}-m" @endif class="pz-casilla__input"><span aria-hidden="true" class="pz-casilla__caja"><span class="pz-casilla__marca"><x-lucide name="check" :size="14" /></span></span><span class="pz-casilla__texto"><span class="pz-casilla__titulo">{{ $label }}@if ($required)<span class="pz-campo__req"> *</span>@endif</span>@if ($description !== '')<span class="pz-casilla__desc">{{ $description }}</span>@endif</span></label>
@if ($msg !== '')<span id="{{ $cid }}-m" class="pz-casilla__error">{{ $msg }}</span>@endif
@if ($slot->isNotEmpty())<div class="pz-casilla__hijos">{{ $slot }}</div>@endif
</div>