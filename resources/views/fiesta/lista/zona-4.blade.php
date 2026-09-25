{{-- ZONA 4 · Los complementos de venta posterior (`specs/complementos-post-reserva.md`), cada uno en su tarjeta con
     su plazo y su cantidad; fuera de plazo, a la vista con su motivo. Los ids viajan SIEMPRE, también en los cerrados.
     ⚠️ La tarta como pregunta, «para N adultos» y «¿Cuántos adultos se quedan?» son lo que FALTA (spec §1.4). --}}
@php($x = $m['extras'])
<section class="pli-zona" data-zona="4" aria-labelledby="pli-h-extras" data-extras>
    <h2 id="pli-h-extras" class="pli-h2">{{ __('fiesta.lista.extras.titular') }}</h2>
    <div class="pli-grid2">
        @foreach ($x['lista'] as $e)
            <input type="hidden" name="addons[{{ $e['indice'] }}][product_id]" value="{{ $e['id'] }}">
            <x-fiesta.complemento :name="$e['nombre']" :line="implode(' · ', array_merge($e['que_lleva'], $e['regalos']))" :price="$e['precio']" :due="$e['plazo']" :changeNote="$e['cambia']" :value="$e['cantidad']" :min="0" :max="$e['tope']" :closed="$e['cerrado']" :total="$e['total']" :inputName="'addons['.$e['indice'].'][quantity]'" :inputId="'x-'.$e['id']" :data-precio="$e['precio_unidad']">
                <x-slot:reason>{{ $e['motivo'] }} <a href="tel:{{ $x['tel'] }}">{{ __('fiesta.lista.numero.llamanos') }}</a>{{ __('fiesta.lista.numero.y_lo_vemos') }}</x-slot:reason>
            </x-fiesta.complemento>
        @endforeach
    </div>
    <p class="pli-pie-z"><x-lucide name="wallet" :size="16" />{{ __('fiesta.lista.extras.pie') }}</p>
</section>
