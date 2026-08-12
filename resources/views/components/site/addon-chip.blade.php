@props(['row'])

{{-- Chip compacto de un complemento. La ETIQUETA (Incluido/Gratis) y el COSTE no se duplican:
     la nota de precio solo aparece si añade info (coste real). Si el complemento tiene VENTAJAS
     (`features`), el botón «Más info +» (mismo patrón que el sidebar de compra: `.addons__moreinfo`
     + `.addons__features` + toggle Alpine) va INLINE, en la misma fila, justo tras el NOMBRE
     (decisión clienta); las ventajas se despliegan debajo. El badge + el precio se agrupan en
     `.addons-mini__meta` (empujado a la derecha) para que el «Más info» quede pegado al título. (#194) --}}
<li class="addons-mini__item" x-data="{ info: false }">
    <div class="addons-mini__row">
        <span class="addons-mini__plus" aria-hidden="true">+</span>
        <span class="addons-mini__name">{{ $row['name'] }}</span>
        @if (! empty($row['features']))
            <button type="button" class="addons__moreinfo addons-mini__moreinfo" @click="info = ! info" :aria-expanded="info">{{ __('tickets.addon_more_info') }}</button>
        @endif
        @if ($row['badge'] || ! empty($row['note']))
            <span class="addons-mini__meta">
                @if ($row['badge'])
                    <span class="addons-mini__badge addons-mini__badge--{{ $row['badge'] }}">{{ __('tickets.addon_badge_'.$row['badge']) }}</span>
                @endif
                @if (! empty($row['note']))
                    <span class="addons-mini__price">{{ $row['note'] }}</span>
                @endif
            </span>
        @endif
    </div>
    @if (! empty($row['features']))
        <ul class="addons__features" x-show="info" x-cloak>
            @foreach ($row['features'] as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        </ul>
    @endif
</li>
