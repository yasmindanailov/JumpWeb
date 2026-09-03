@props(['zones', 'tickets', 'rateNormal', 'rateEspeciales', 'diasEspeciales', 'precioPor', 'variante' => 'semana', 'socks' => null])

{{-- EL PRECIO PARA EL DÍA QUE VAS (D-G5). Un panel por zona, gobernado por `zone`.
     `semana`: la semana como tira + la tabla de dos precios · `dos`: solo la tabla · `desde`: tarjetas
     «desde» con el precio de finde impreso pequeño y el día en el calendario (el turno 4 del cliente). --}}
@php
    $especial = $rateEspeciales->first();
    $dias = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 0 => 'D'];
    $eur = fn (?int $c): string => $c === null ? '—' : \App\Domain\Platform\Services\Money::amount($c).' €';
    $dur = function ($t): string {
        if (! $t->duration_min) return $t->tr('name');
        return $t->duration_min % 60 === 0 ? intdiv($t->duration_min, 60).' '.(intdiv($t->duration_min, 60) === 1 ? 'hora' : 'horas') : $t->duration_min.' min';
    };
    $socksCents = $socks?->displayPriceCents();
@endphp

@foreach ($zones as $z)
    <div x-show="zone === @js($z->slug)" @if (! $loop->first) style="display:none" @endif
         style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($z->color, $z->color_secondary, $z->accent) }}">
        @php $lista = $tickets->where('zone_id', $z->id)->values(); @endphp

        @if ($variante === 'semana')
            <div class="pg-week" aria-hidden="true">
                @foreach ($dias as $n => $letra)
                    <span class="pg-week__day {{ in_array($n, $diasEspeciales, true) ? 'pg-week__day--special' : '' }}">{{ $letra }}</span>
                @endforeach
                <span class="pg-week__day pg-week__day--special pg-week__day--fest">fest.</span>
            </div>
        @endif

        @if ($variante === 'semana' || $variante === 'dos')
            <table class="pg-price-table">
                <thead>
                    <tr>
                        <th>{{ $z->tr('name') }} · por persona</th>
                        <th class="pg-num">Lunes a jueves</th>
                        <th class="pg-num">{{ $especial?->tr('label') ?? 'Especial' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lista as $t)
                        @php $pn = $precioPor($t, $rateNormal); $pe = $especial ? $precioPor($t, $especial) : null; @endphp
                        <tr>
                            <td class="pg-dur">{{ $dur($t) }}@if ($t->tr('period_label') && $dur($t) !== $t->tr('name'))<small>{{ $t->tr('name') }}</small>@endif</td>
                            <td class="pg-num"><span class="pg-eur">{{ $eur($pn) }}</span></td>
                            <td class="pg-num">
                                @if ($pe === null || $pe === $pn)
                                    <span class="pg-eur pg-eur--special">{{ $eur($pn) }}</span> <span class="pg-same">igual</span>
                                @else
                                    <span class="pg-eur pg-eur--special">{{ $eur($pe) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="pg-desde">
                @foreach ($lista as $t)
                    @php $pn = $precioPor($t, $rateNormal); $pe = $especial ? $precioPor($t, $especial) : null; @endphp
                    <div class="pg-desde__card">
                        <span class="pg-desde__dur">{{ $dur($t) }}</span>
                        <span class="pg-desde__from">{{ $pe !== null && $pe !== $pn ? 'desde' : 'siempre' }}</span>
                        <span class="pg-desde__eur">{{ $eur($pn) }}</span>
                        @if ($pe !== null && $pe !== $pn)
                            <span class="pg-desde__fest">{{ $especial->tr('label') }}: {{ $eur($pe) }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="pg-note" style="margin-top: var(--sp-12)">El día exacto lo eliges al reservar, y ahí ves el precio de esa fecha.</p>
        @endif

        <div class="pg-price-foot">
            @if ($socksCents)
                <span class="pg-socks">Calcetines antideslizantes obligatorios: <b>+{{ $eur($socksCents) }}</b> si no los traes.</span>
            @endif
            @if ($site['sales_online'])
                <button type="button" class="btn" @click="$store.purchase.openWith({ type: 'zone', slug: @js($z->slug) })">Reservar {{ $z->tr('name') }}</button>
            @elseif ($site['has_phone'])
                <a class="btn" href="tel:{{ $site['phone_tel'] }}">{{ __('landing.pricing.call') }}</a>
            @endif
        </div>
    </div>
@endforeach
