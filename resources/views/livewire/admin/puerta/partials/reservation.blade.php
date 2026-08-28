@php
    /** @var array<string, mixed> $r  una fila de `GateProfileData::today_reservations`/`window` */
    use App\Domain\Platform\Services\DisplayTime;
    use App\Domain\Platform\Services\Money;

    $units = $r['is_entry']
        ? trans_choice('admin.puerta.validar.profile.entries', $r['quantity'], ['n' => $r['quantity']])
        : trans_choice('admin.puerta.validar.profile.guests', $r['quantity'], ['n' => $r['quantity']]);
    $method = $r['charge_method'] === 'desk'
        ? __('admin.puerta.validar.profile.method_desk')
        : __('admin.puerta.validar.profile.method_redsys');
@endphp
<li class="gate-res" data-gate-reservation="{{ $r['order_code'] }}">
    <div class="gate-res__top">
        <span class="gate-res__product">{{ $r['product'] }}</span>
        <span class="gate-res__code">{{ $r['order_code'] }}</span>
    </div>
    <p class="gate-res__meta">
        <span class="gate-res__day">{{ DisplayTime::dayLabel($r['date']) }}</span>
        @if ($r['time_window'])<span class="gate-res__sep" aria-hidden="true"></span>{{ $r['time_window'] }}@endif
        <span class="gate-res__sep" aria-hidden="true"></span>{{ $units }}
        @foreach ($r['addons'] as $addon)
            <span class="gate-res__addon">+ {{ $addon }}</span>
        @endforeach
    </p>

    {{-- El dinero SALE del ledger (`OrderLedger`), nunca se recompone aquí (§4.7). Y «pendiente de
         cobrar en puerta» no es opcional: con sistema de señal, si el empleado no lo ve, el negocio
         no cobra. Por eso es la única línea de la tarjeta con tratamiento de ALERTA. --}}
    @if ((int) $r['pending_gate_cents'] > 0)
        <p class="gate-res__pending" data-gate-pending>
            {{ __('admin.puerta.validar.profile.pending_gate', ['amount' => Money::amount((int) $r['pending_gate_cents'])]) }}
        </p>
    @else
        <p class="gate-res__settled">{{ __('admin.puerta.validar.profile.nothing_pending') }}</p>
    @endif

    <p class="gate-res__paid">
        {{ __('admin.puerta.validar.profile.paid', ['amount' => Money::amount((int) $r['paid_online_cents']), 'method' => $method]) }}
        <span class="gate-res__sep" aria-hidden="true"></span>{{ __('admin.puerta.validar.profile.booked_on', ['when' => DisplayTime::format($r['created_at'], 'd/m/Y H:i')]) }}@if ($r['paid_at'])<span class="gate-res__sep" aria-hidden="true"></span>{{ __('admin.puerta.validar.profile.paid_on', ['when' => DisplayTime::format($r['paid_at'], 'd/m/Y H:i')]) }}@endif
    </p>

    {{-- ⚠️⚠️ Menores de la línea: EDAD y estado de la exención, JAMÁS el nombre (§4.6). El DTO no
         tiene campo para el nombre — que siga siendo verdad depende también de que aquí no se
         imprima nada más que `age` y `waiver`. --}}
    @if ($r['minors'] !== [])
        <p class="gate-res__minors-label">{{ __('admin.puerta.validar.profile.minors_on_line_label') }}</p>
        <ul class="gate-res__minors" data-gate-line-minors>
            @foreach ($r['minors'] as $m)
                <li class="gate-minor" data-gate-minor data-gate-minor-name="{{ $m['name'] ?? '' }}" data-gate-minor-age="{{ (int) $m['age'] }}" data-gate-minor-waiver="{{ $m['waiver'] ?? 'unknown' }}">
                    <span class="gate-minor__name">{{ $m['name'] ?? '' }}</span>
                    <span class="gate-minor__age">{{ __('admin.puerta.validar.profile.minor', ['age' => (int) $m['age']]) }}</span>
                    @if ($m['waiver'] !== null)
                        <x-filament::badge size="xs" :color="$m['waiver'] === 'current' ? 'success' : ($m['waiver'] === 'outdated' ? 'warning' : 'danger')">
                            {{ __('admin.puerta.validar.profile.minor_waiver_'.$m['waiver']) }}
                        </x-filament::badge>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</li>
