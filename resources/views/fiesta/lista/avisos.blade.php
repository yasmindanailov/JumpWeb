{{-- Lo que se DICE arriba (el guardado, los rechazos, la fiesta mixta, las edades…): con las piezas del sistema; el
     diseño no lo dibuja y se juzga sin A (spec §1.4). --}}
@foreach ($m['avisos'] as $aviso)
    <x-pieza.aviso :tone="$aviso['tono']" :title="$aviso['titulo']" :role="$aviso['rol']" size="sm">
        <x-slot:icono><x-lucide :name="['success' => 'circle-check', 'warn' => 'triangle-alert', 'danger' => 'circle-alert'][$aviso['tono']] ?? 'info'" :size="17" /></x-slot:icono>
        {{ '' }}@foreach ($aviso['lineas'] as $linea)<p style="margin: 0;">{{ $linea }}</p>@endforeach
    </x-pieza.aviso>
@endforeach
@if ($m['solo_lectura'])
    <x-pieza.aviso tone="neutral" size="sm" role="status"><x-slot:icono><x-lucide name="lock" :size="17" /></x-slot:icono>{{ '' }}{{ __('guestform.readonly_notice') }}</x-pieza.aviso>
@endif
@if ($m['invitacion'] !== null && $m['invitacion']['texto_rechazado'])
    <x-pieza.aviso tone="danger" size="sm" role="alert" :title="__('guestform.invite.rejected_title')"><x-slot:icono><x-lucide name="circle-alert" :size="17" /></x-slot:icono>{{ '' }}{{ __('guestform.invite.rejected') }}</x-pieza.aviso>
@endif
@if ($m['invitacion'] !== null && $m['invitacion']['descartada'])
    <x-pieza.aviso tone="success" size="sm" role="status"><x-slot:icono><x-lucide name="circle-check" :size="17" /></x-slot:icono>{{ '' }}{{ __('guestform.invite.dismissed') }}</x-pieza.aviso>
@endif
{{-- BAJAR INVITADOS DESTRUYE FICHAS y se dice ANTES de guardar (`#444`): lo rellena el JS con las fichas que se perderían. --}}
@if ($m['numero']['editable'])
    <div hidden id="pli-aviso-numero" data-tpl="{{ __('guestform.count_warn_discard', ['count' => ':count', 'discarded' => ':discarded']) }}">
        <x-pieza.aviso tone="warn" size="sm" role="alert" :title="__('guestform.count_warn_title')"><x-slot:icono><x-lucide name="triangle-alert" :size="17" /></x-slot:icono>{{ '' }}<span data-aviso-numero-texto></span></x-pieza.aviso>
    </div>
@endif
