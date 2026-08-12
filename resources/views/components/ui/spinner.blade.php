@props(['size' => 'md', 'label' => null, 'decorative' => false])

{{--
    Spinner de marca (el punto saltando sobre el bloque). Ver docs/UI-SPINNER.md.
    Reenvía wire:loading / clases extra vía $attributes, p. ej.:
        <x-ui.spinner size="xs" decorative wire:loading.delay wire:target="login" />
    - size: xs | sm | md | lg | xl
    - decorative: true cuando el contexto ya da el texto (p. ej. dentro de un botón con "Enviando…").
--}}
@if ($decorative)
    <span {{ $attributes->class(['jj-spinner', 'jj-spinner--'.$size]) }} aria-hidden="true"></span>
@else
    <span {{ $attributes->class(['jj-spinner', 'jj-spinner--'.$size]) }} role="status">
        <span class="jj-spinner__sr">{{ $label ?? __('ui.loading') }}</span>
    </span>
@endif
