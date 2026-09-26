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
{{-- ▶ Desde F4 (§4.9, `#747`) bajar el número por debajo de la lista ya NO destruye fichas: la zona 3 pregunta «¿Es
     correcto?» y guardar se para hasta confirmarlo (el aviso de «se perderán N fichas» de `#444` se retiró con esto). --}}
