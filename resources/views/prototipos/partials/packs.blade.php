@props(['packages', 'rateNormal', 'rateEspeciales', 'precioPor'])

{{-- LOS DOS PACKS, LADO A LADO — se comparan, no se alternan (`design.md` §9). Lo que incluye cada uno
     en filas; los complementos y el menú se eligen DESPUÉS, al reservar; y la promesa del
     post-formulario dicha aquí, una vez. --}}
@php
    $especial = $rateEspeciales->first();
    $eur = fn (?int $c): string => $c === null ? '—' : \App\Domain\Platform\Services\Money::amount($c).' €';
@endphp
<div class="pg-packs">
    @foreach ($packages as $p)
        @php $pn = $precioPor($p, $rateNormal); $pe = $especial ? $precioPor($p, $especial) : null; @endphp
        <article class="pg-pack" style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($p->zone?->color, $p->zone?->color_secondary, (string) ($p->zone?->accent ?? '')) }}">
            <span class="pg-pack__zone">
                @if ($p->guest_age_min !== null)
                    {{ $p->guest_age_max ? "de {$p->guest_age_min} a {$p->guest_age_max} años" : "desde {$p->guest_age_min} años" }}
                @else
                    cumpleaños
                @endif
            </span>
            <h3 class="pg-pack__name">{{ $p->tr('name') }}</h3>
            <div class="pg-pack__price">
                <span class="pg-eur">{{ $eur($pn) }}</span>
                <span class="pg-per">por niño · lunes a jueves</span>
                @if ($pe !== null && $pe !== $pn)
                    <span class="pg-per">{{ $especial->tr('label') }}: {{ $eur($pe) }}</span>
                @endif
            </div>
            @if ($p->tr('description'))
                <p style="margin:0; font-size: var(--fs-15); color: var(--fg-mute); line-height: 1.5">{{ $p->tr('description') }}</p>
            @endif
            <ul class="pg-pack__rows">
                @if ($p->min_qty)<li><span>Invitados</span><span>mínimo {{ $p->min_qty }}</span></li>@endif
                @if ($p->duration_min)<li><span>Duración</span><span>{{ intdiv($p->duration_min, 60) }} h</span></li>@endif
                @if ($p->deposit_value)<li><span>Señal al reservar</span><span>{{ $eur((int) $p->deposit_value) }} · se descuenta</span></li>@endif
                <li><span>Menú, tarta y extras</span><span>{{ $p->addons->count() }} a elegir al reservar</span></li>
            </ul>
            <p class="pg-pack__after">Después de reservar te pedimos los nombres de los niños — sin prisa, cuando los tengas.</p>
            @if ($site['sales_online'])
                <button type="button" class="btn" @click="$store.purchase.openWith({ type: 'pack', id: {{ $p->id }} })">Reservar {{ $p->tr('name') }}</button>
            @elseif ($site['has_phone'])
                <a class="btn" href="tel:{{ $site['phone_tel'] }}">{{ __('landing.pricing.call') }}</a>
            @endif
        </article>
    @endforeach
</div>
