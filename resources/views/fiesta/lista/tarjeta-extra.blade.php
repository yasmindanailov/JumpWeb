{{-- UNA TARJETA de complemento de la zona 4 (`AddonCard`): la de los padres y la de la rejilla. Con `data-*` para las
     sugerencias de `lista.js` (para cuántos es, su precio, su tope, su nombre y lo pedido). ⚠️ Lo pedido es `data-pedido`
     y no `data-cantidad`: ésa es la marca del selector de cantidad y `lista.js` ataría la tarjeta como si fuera uno. El
     id viaja siempre. --}}
<input type="hidden" name="addons[{{ $e['indice'] }}][product_id]" value="{{ $e['id'] }}">
<x-fiesta.complemento :name="$e['nombre']" :line="implode(' · ', array_merge($e['que_lleva'], $e['regalos']))" :serves="$e['para']" :image="$e['foto']" :price="$e['precio']" :due="$e['plazo']" :changeNote="$e['cambia']" :value="$e['cantidad']" :min="0" :max="$e['tope']" :closed="$e['cerrado']" :total="$e['total']" :inputName="'addons['.$e['indice'].'][quantity]'" :inputId="'x-'.$e['id']" :data-precio="$e['precio_unidad']" data-variante :data-serves="$e['serves'] ?? 0" :data-max="$e['tope']" :data-nombre="$e['nombre']" :data-pedido="$e['cantidad']">
    <x-slot:reason>{{ $e['motivo'] }} <a href="tel:{{ $x['tel'] }}">{{ __('fiesta.lista.numero.llamanos') }}</a>{{ __('fiesta.lista.numero.y_lo_vemos') }}</x-slot:reason>
</x-fiesta.complemento>
