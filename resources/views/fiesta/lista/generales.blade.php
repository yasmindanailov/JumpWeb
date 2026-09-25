{{-- Los DATOS GENERALES del formulario (`event_fields` de la fase `postform`, data-driven): el diseño no los dibuja;
     van con las piezas del sistema y se juzgan sin A (spec §1.4). --}}
<section class="pli-zona" data-zona="generales" aria-labelledby="pli-h-generales">
    <h2 id="pli-h-generales" class="pli-h2">{{ __('fiesta.lista.generales') }}</h2>
    @foreach ($m['generales'] as $g)
        @if ($g['textarea'])
            <x-pieza.area :id="'g-'.$g['key']" :name="$g['name']" :label="$g['label']" :optional="! $g['required']" :rows="2" :value="$g['value']" :disabled="! $g['editable']" />
        @else
            <x-pieza.campo :id="'g-'.$g['key']" :name="$g['name']" :label="$g['label']" :optional="! $g['required']" :value="$g['value']" :type="$g['numeric'] ? 'number' : 'text'" :inputmode="$g['numeric'] ? 'numeric' : null" :min="$g['numeric'] ? 0 : null" :disabled="! $g['editable']" />
        @endif
    @endforeach
</section>
