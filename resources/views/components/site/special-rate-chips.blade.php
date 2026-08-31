@props(['product'])

{{-- Chips de TARIFA ESPECIAL de un producto (entrada o pack), presentación «Suplemento +X€» (#267).
     Uno por tarifa `is_special` con precio propio: recargo («+X€») si es más cara que la base `normal`,
     o importe absoluto si fuese ≤ base (config. rara: el panel no valida el signo) → nunca engaña.
     Etiqueta = label i18n de la tarifa (BD). La UNIDAD (por persona / por niño) la lleva la línea de
     precio de cada card (`period_label`), así que el chip es idéntico en entradas y packs (DRY).
     Fuente única: TicketType::specialRateSurcharges(). --}}
@php $rows = $product->specialRateSurcharges(); @endphp

@if ($rows)
    <div class="price__special">
        @foreach ($rows as $sr)
            <span class="tag tag--dato price__special-chip">
                @if ($sr['surchargeCents'] > 0)
                    <b>+{{ \App\Domain\Platform\Services\Money::amount($sr['surchargeCents']) }}&nbsp;€</b>
                @else
                    <b>{{ \App\Domain\Platform\Services\Money::amount($sr['priceCents']) }}&nbsp;€</b>
                @endif
                <span class="price__special-day">{{ $sr['rate']->tr('label') }}</span>
            </span>
        @endforeach
    </div>
@endif
