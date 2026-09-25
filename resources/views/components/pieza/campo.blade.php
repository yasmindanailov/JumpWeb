@props(['label' => '', 'hint' => '', 'error' => '', 'required' => false, 'optional' => false, 'type' => 'text', 'size' => 'md', 'prefijo' => null, 'sufijo' => null, 'id' => null, 'name' => null, 'value' => ''])
@php
    /*
     * El campo de texto del sistema (`forms/Field.jsx`): etiqueta, ayuda y error; altura mínima 48. El anillo lo dibuja
     * la CAJA con `:focus-within`, no el campo (el estilo, `.pz-campo` en `fiesta.css`). Lo que no se nombra aquí
     * (`autocomplete`, `inputmode`, `pattern`, `maxlength`, `enterkeyhint`, `autocapitalize`, `autofocus`,
     * `placeholder`, `disabled`…) pasa al `<input>`, como el `...rest` del diseño. El «ver la contraseña» no está
     * portado: la fiesta no tiene contraseñas.
     */
    $fid = $id ?: 'f-'.strtolower(preg_replace('/\s+/', '-', $label ?: $type));
    $msg = ($error !== '' || $hint !== '') ? $fid.'-m' : null;
    $clases = 'pz-campo'.($size === 'lg' ? ' pz-campo--lg' : '').($error !== '' ? ' pz-campo--error' : '');
@endphp
<div class="{{ $clases }}">
@if ($label !== '')<label for="{{ $fid }}" class="pz-campo__label">{{ $label }}@if ($required)<span class="pz-campo__req"> *</span>@endif{{ '' }}@if ($optional && ! $required)<span class="pz-campo__opt">{{ __('fiesta.pieza.opcional') }}</span>@endif</label>@endif
<div class="pz-campo__caja">@if ($prefijo)<span class="pz-campo__prefijo">{{ $prefijo }}</span>@endif<input id="{{ $fid }}" type="{{ $type }}" @if ($name !== null)name="{{ $name }}" @endif{{ '' }}value="{{ $value }}" @required($required) @if ($error !== '') aria-invalid="true" @endif @if ($msg) aria-describedby="{{ $msg }}" @endif class="pz-campo__input" {{ $attributes->except(['class']) }}>@if ($sufijo)<span class="pz-campo__sufijo">{{ $sufijo }}</span>@endif</div>
@if ($error !== '')<span id="{{ $msg }}" class="pz-campo__error">{{ $error }}</span>@elseif ($hint !== '')<span id="{{ $msg }}" class="pz-campo__pista">{{ $hint }}</span>@endif
</div>