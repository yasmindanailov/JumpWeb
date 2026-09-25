@props(['label' => '', 'hint' => '', 'error' => '', 'required' => false, 'optional' => false, 'rows' => 3, 'maxlength' => null, 'counter' => false, 'autoGrow' => false, 'value' => '', 'id' => null, 'name' => null])
@php
    /*
     * El texto largo del sistema (`forms/Textarea.jsx`): mínimo 96 px. Con `autoGrow` crece hasta su contenido (lo hace
     * el JS de la página, `data-crece`); sin él, se redimensiona en vertical. El estilo, `.pz-area` en `fiesta.css`.
     */
    $fid = $id ?: 't-'.strtolower(preg_replace('/\s+/', '-', $label ?: 'texto'));
    $len = mb_strlen((string) $value);
    $cerca = $maxlength ? $len >= $maxlength * 0.9 : false;
    $pie = $error !== '' || $hint !== '' || ($counter && $maxlength);
    $clases = 'pz-area'.($error !== '' ? ' pz-area--error' : '').($autoGrow ? ' pz-area--grow' : '');
@endphp
<div class="{{ $clases }}">
@if ($label !== '')<label for="{{ $fid }}" class="pz-area__label">{{ $label }}@if ($required)<span class="pz-area__req">*</span>@endif @if ($optional && ! $required)<span class="pz-area__opt">{{ __('fiesta.pieza.opcional') }}</span>@endif</label>@endif
<div class="pz-area__caja"><textarea id="{{ $fid }}" rows="{{ $rows }}" @required($required) @if ($maxlength) maxlength="{{ $maxlength }}" @endif @if ($name !== null) name="{{ $name }}" @endif @if ($autoGrow) data-crece @endif class="pz-area__input" {{ $attributes->except(['class']) }}>{{ $value }}</textarea></div>
@if ($pie)<div class="pz-area__pie"><span class="pz-area__pista">{{ $error !== '' ? $error : $hint }}</span>@if ($counter && $maxlength)<span @class(['pj-num pz-area__contador', 'pz-area__contador--cerca' => $cerca])>{{ $len }}/{{ $maxlength }}</span>@endif</div>@endif
</div>