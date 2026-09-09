@props(['product'])

{{-- **LA TARIFA ESPECIAL DE UN PRODUCTO, CON SU PRECIO ENTERO.**

     ❗❗❗ **Hasta `#479` esto publicaba el RECARGO («Suplemento +2 €») y ahora publica el PRECIO.**
     `[DECIDIDO owner, 2026-09-09]`, y es una regla dura del sistema del canvas: *«un recargo no se
     publica como recargo. Y menos si no es plano: en cumpleaños es +2 € en Kids y +4 € en Jump, así
     que el cliente tendría que recordar cuál le toca. El precio, entero»*. Va con su hermana, *«nunca
     una suma»*: si el catálogo vende por unidad y el cliente compra un total, el total se escribe.

     ⚠️⚠️ **El dato ya venía y no se ha tocado el dominio.** `specialRateSurcharges()` devuelve las
     DOS cifras —`priceCents` y `surchargeCents`— desde `#267`; lo único que cambia es cuál escribe
     la vista. El recargo sigue existiendo para quien lo necesite (el panel, un informe), pero **no
     se le enseña al cliente**.

     ⚠️ **Los DÍAS ya no van en el chip: van UNA vez por sección** (`<x-site.special-rate-note>`),
     que es la otra mitad de la misma regla — *«un término que se usa en un sitio y se esquiva en
     otro no se aprende nunca»*. Repetir «Viernes, findes y festivos» en cada tarjeta era el ruido
     que la regla existe para quitar.

     ⚠️ La UNIDAD (por persona / por niño) la lleva la línea de precio de cada card
     (`period_label`), así que esto es idéntico en entradas y en packs.
     Fuente única: `TicketType::specialRateSurcharges()`. --}}
@php $rows = $product->specialRateSurcharges(); @endphp

@if ($rows)
    <div class="price__special">
        @foreach ($rows as $sr)
            <span class="price__special-line">
                <b>{{ \App\Domain\Platform\Services\Money::showcase($sr['priceCents']) }}&nbsp;€</b>
                <span>{{ __('landing.rates.special_suffix') }}</span>
            </span>
        @endforeach
    </div>
@endif
