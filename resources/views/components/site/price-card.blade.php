@props(['ticket'])

{{-- Tarjeta de una entrada (catálogo). Precio de la tarifa `normal` con la unidad («POR PERSONA»)
     pegada, MISMO estilo/texto que el «POR NIÑO» de la card de pack. Sin «desde» (el chip ya explica
     el recargo). Tarifa(s) especial(es) → chip «Suplemento +X€» debajo. Ver specialRateSurcharges(). --}}
<div class="price {{ $ticket->featured ? 'price--feat' : '' }}">
    @if ($ticket->tr('badge'))<span class="tag tag--senal tag--tinta price__badge">{{ $ticket->tr('badge') }}</span>@endif
    {{-- ⚠️⚠️ **El MARCADOR del producto, el que ya elige el panel** (`ticket_types.icon`, `#259`) —
         no un icono decorativo que decida esta vista. Hasta ahora ese campo tenía **un solo
         consumidor, el cajón**: aquí gana el segundo, y con él una razón para que el operador lo
         rellene (medido: 2 claves usadas de 11).
         ▶ Se resuelve con el MISMO puente que la banda de cumpleaños (`events-section`):
         `iconKey()` traduce la clave a componente y trata una clave desconocida como ausente, así
         que un producto sin icono elegido cae al defecto de su tipo en vez de quedarse en blanco.
         ⚠️ `aria-hidden`: el nombre del producto va justo al lado y un icono que se anunciara lo
         duplicaría en el nombre accesible de la tarjeta. --}}
    <span class="price__ico" aria-hidden="true">
        <x-dynamic-component :component="'icons.'.$ticket->iconKey()" :width="26" :height="26" />
    </span>
    <span class="price__name">{{ $ticket->tr('name') }}</span>
    {{-- ⚠️⚠️ **LA UNIDAD SALE DE DENTRO DEL NÚMERO, y era un defecto VISIBLE** (medido con control el
         2026-09-01: idéntico antes de esta tanda, así que es preexistente). `.price__num` va a **80 px
         con `line-height: 0.85`**, y «POR PERSONA» vivía dentro heredando esa caja: la unidad se
         partía en dos —«POR» arriba a la derecha, «PERSONA» 68 px más abajo— y el símbolo del euro
         montaba encima de los céntimos.
         ▶ Es el MISMO fallo que el sufijo del precio en el catálogo del cajón: **una unidad no es
         parte del número, es la línea de debajo**. Sale como hermano, con su propio interlineado. --}}
    <div class="price__num">{{ $ticket->euros() }}<span class="cents">,{{ $ticket->cents() }}</span><span class="eur">€</span></div>
    @if ($ticket->tr('period_label'))<span class="price__per">{{ $ticket->tr('period_label') }}</span>@endif
    <x-site.special-rate-chips :product="$ticket" />
    <ul>
        @foreach ($ticket->tr('features') ?? [] as $item)<li>{{ $item }}</li>@endforeach
    </ul>
    {{-- Complementos que admite ESTA entrada (coherente con el pivote; #194). --}}
    <x-site.product-addons :product="$ticket" />
    {{-- CTA por card: «Reservar» abre el sidebar de compra (el catálogo arranca en «Entradas»).
         Si la entrada NO tiene venta online (`is_sellable=false`) → fallback a «Llamar» con el
         teléfono de contacto; si tampoco hay teléfono configurado, no se muestra CTA. --}}
    @if ($ticket->is_sellable)
        <button type="button" class="btn price__cta" @click="$store.purchase.open()">{{ __('landing.pricing.book') }}</button>
    @elseif ($site['has_phone'])
        <a href="tel:{{ $site['phone_tel'] }}" class="btn price__cta">{{ __('landing.pricing.call') }}</a>
    @endif
</div>
