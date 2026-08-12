@props(['ticket'])

{{-- Tarjeta de una entrada (catálogo). Precio de la tarifa `normal` con la unidad («POR PERSONA»)
     pegada, MISMO estilo/texto que el «POR NIÑO» de la card de pack. Sin «desde» (el chip ya explica
     el recargo). Tarifa(s) especial(es) → chip «Suplemento +X€» debajo. Ver specialRateSurcharges(). --}}
<div class="price {{ $ticket->featured ? 'price--feat' : '' }}">
    @if ($ticket->tr('badge'))<span class="price__badge">{{ $ticket->tr('badge') }}</span>@endif
    <span class="price__name">{{ $ticket->tr('name') }}</span>
    <div class="price__num">{{ $ticket->euros() }}<span class="cents">,{{ $ticket->cents() }}</span><span class="eur">€</span><span class="price__per">{{ $ticket->tr('period_label') }}</span></div>
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
