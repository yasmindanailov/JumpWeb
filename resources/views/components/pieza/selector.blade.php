@props(['label' => '', 'hint' => '', 'error' => '', 'required' => false, 'options' => [], 'size' => 'md', 'id' => null, 'name' => null, 'value' => ''])
@php
    /*
     * El desplegable nativo con estilo del sistema (`forms/Select.jsx`), con sus estilos EN LÍNEA como el JSX; el foco
     * (que el diseño lleva en estado de React) va por `:focus` en `.pz-selector` (`fiesta.css`). Cada opción es una
     * cadena o `['value', 'label', 'disabled', 'attrs' => [...]]`: `attrs` son atributos `data-*` de la opción (el
     * selector de menores a cargo los usa para rellenar la ficha). El error se dice con palabras.
     */
    $fid = $id ?: 's-'.strtolower((string) preg_replace('/\s+/', '-', $label !== '' ? $label : 'select'));
    $alto = $size === 'lg' ? 'var(--control-lg)' : 'var(--control-md)';
    $borde = $error !== '' ? 'var(--border-danger)' : 'var(--control-border)';
@endphp
<div @class(['pz-selector', 'pz-selector--error' => $error !== '']) style="display: flex; flex-direction: column; gap: 7px; min-width: 0;">
@if ($label !== '')<label for="{{ $fid }}" style="font: var(--type-label); color: var(--text-strong);">{{ $label }}@if ($required)<span style="color: var(--fiesta-llama-600);"> *</span>@endif</label>@endif
<div style="position: relative; display: flex; align-items: center;"><select id="{{ $fid }}" @if ($name !== null)name="{{ $name }}" @endif{{ '' }}@required($required) class="pz-selector__select" style="appearance: none; -webkit-appearance: none; width: 100%; height: {{ $alto }}; padding: 0 44px 0 16px; background: var(--control-bg); color: var(--text-strong); border: 1px solid {{ $borde }}; border-radius: var(--r-md); box-shadow: none; font-family: var(--font-ui); font-size: var(--fs-body); cursor: pointer; outline: none; transition: var(--t-hover);" {{ $attributes->except(['class', 'style']) }}>@foreach ($options as $o)@php
    $ov = is_array($o) ? (string) ($o['value'] ?? '') : (string) $o;
    $ol = is_array($o) ? (string) ($o['label'] ?? $ov) : (string) $o;
    $od = is_array($o) && (bool) ($o['disabled'] ?? false);
    $oa = is_array($o) ? (array) ($o['attrs'] ?? []) : [];
@endphp<option value="{{ $ov }}" @selected((string) $value === $ov) @disabled($od) @foreach ($oa as $ak => $av) {{ $ak }}="{{ $av }}" @endforeach>{{ $ol }}</option>@endforeach</select><x-lucide name="chevron-down" :size="18" color="var(--text-muted)" style="position: absolute; right: 16px; pointer-events: none;" /></div>
@if ($error !== '')<span style="font-family: var(--font-ui); font-size: var(--fs-caption); color: var(--text-danger);">{{ $error }}</span>@elseif ($hint !== '')<span style="font-family: var(--font-ui); font-size: var(--fs-caption); color: var(--text-muted);">{{ $hint }}</span>@endif
</div>
