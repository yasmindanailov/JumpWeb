{{-- UNA PREGUNTA de un grupo de opciones (`[DECIDIDO owner]` `#914`, P3·3 de §4.21): «elige una» con las `OptionCards` del
     sistema (`x-pieza.opciones`). Radio nativo: sin JavaScript también se elige. Viaja como `choices[<clave>]` —el id elegido o
     `none`— y el servidor lo traduce a cantidades; la regla («elige una», si hay que elegir) la decide el reconciliador.
     Cerrada, los radios van deshabilitados: no se envían y el grupo no se toca. --}}
<div class="pli-pregunta" data-pregunta="{{ $p['clave'] }}" @if ($p['chapa'] !== '') data-falta @endif>
    <x-pieza.opciones :name="$p['name']" :items="$p['opciones']" :value="$p['valor']" :label="$p['titulo']" :hint="$p['pista']" :badge="$p['chapa']" />
</div>
