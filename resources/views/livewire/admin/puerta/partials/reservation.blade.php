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

    {{-- T3 · E (`specs/cumple-mixto.md` §23.2): lo ESCRITO del suplemento de fiesta MIXTA, bajo el
         producto y encima del pendiente — la diferencia por cabeza que el empleado hacía de memoria
         con el cliente delante. Se enseña lo escrito y NUNCA el veredicto derivado: es lo que se
         cobra (`PAY-19`), y ya está sumado dentro de «pendiente de cobrar en puerta». --}}
    {{-- `?? 0`: la fila viaja en el ESTADO Livewire del componente, y un snapshot abierto antes de
         un despliegue puede traer filas sin estas claves — un 500 en la cara del operador no es el
         precio correcto de esa ventana. Desde la T4 el total es el NETO (con signo) y el descuento
         lleva su frase compuesta por el dominio; lo que la puerta no absorbe lo dice el SALDO de
         abajo (T3·3 del libro: murió el «a tu favor» con el tope). --}}
    @php
        $mixNet = (int) ($r['mixed_party_surcharge_cents'] ?? 0);
        $mixCredit = $r['mixed_party_credit'] ?? null;
    @endphp
    @if ($mixNet !== 0 || $mixCredit !== null)
        <div class="gate-res__mixed" data-gate-mixed-party>
            @foreach ($r['mixed_party_lines'] ?? [] as $line)
                <p class="gate-res__mixed-line">{{ __('admin.puerta.validar.profile.mixed_party_line', ['count' => (int) $line['count'], 'name' => $line['name'], 'unit' => Money::amount((int) $line['unit_cents'])]) }}</p>
            @endforeach
            @if ($mixCredit !== null)
                <p class="gate-res__mixed-line">{{ $mixCredit['label'] }}: −{{ Money::amount((int) $mixCredit['cents']) }} €</p>
            @endif
            @if ($mixNet > 0)
                <p class="gate-res__mixed-total">{{ __('admin.puerta.validar.profile.mixed_party_total', ['amount' => Money::amount($mixNet)]) }}</p>
            @elseif ($mixNet < 0)
                <p class="gate-res__mixed-total">{{ __('admin.puerta.validar.profile.mixed_party_discount_total', ['amount' => Money::amount(-$mixNet)]) }}</p>
            @endif
        </div>
    @endif

    {{-- El dinero SALE del LIBRO de la reserva (`OrderBook`, `DECISIONES #305`; T3·2), nunca se
         recompone aquí, y se pinta por CLASE (D-T3·8): «pendiente de cobrar» no es opcional —con
         sistema de señal, si el empleado no lo ve, el negocio no cobra— y «pendiente de devolver» es
         dinero que el empleado tiene que devolver: las dos llevan tratamiento de ALERTA. Y un libro
         que no cuadra NUNCA dice «nada pendiente». `?? …` por los snapshots Livewire de antes de un
         despliegue (ver arriba). --}}
    @php
        $balanceKind = (string) ($r['balance_kind'] ?? 'under_review');
        $balanceCents = (int) ($r['balance_cents'] ?? 0);
    @endphp
    @if ($balanceKind === 'pay_at_park')
        <p class="gate-res__pending" data-gate-pending>
            {{ __('admin.puerta.validar.profile.pending_gate', ['amount' => Money::amount($balanceCents)]) }}
        </p>
    @elseif ($balanceKind === 'refund_at_park' || $balanceKind === 'refund_pending')
        <p class="gate-res__pending" data-gate-refund>
            {{ __('admin.puerta.validar.profile.refund_at_gate', ['amount' => Money::amount(-$balanceCents)]) }}
        </p>
    @elseif ($balanceKind === 'settled')
        <p class="gate-res__settled">{{ __('admin.puerta.validar.profile.nothing_pending') }}</p>
    @else
        <p class="gate-res__pending" data-gate-under-review>{{ __('admin.puerta.validar.profile.under_review') }}</p>
    @endif

    <p class="gate-res__paid">
        {{ __('admin.puerta.validar.profile.paid', ['amount' => Money::amount((int) ($r['paid_cents'] ?? 0)), 'method' => $method]) }}
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
                    {{-- `#320`: solo la EXCEPCIÓN lleva pastilla. La firma vigente es condición para
                         estar asignado, así que no se anuncia; `outdated` («versión anterior — deja
                         pasar») y `missing` sí, que es lo que el operador necesita decidir. --}}
                    @if (\App\Domain\Identity\Services\WaiverStatus::minorStateIsNoteworthy($m['waiver']))
                        <x-filament::badge size="xs" :color="$m['waiver'] === 'outdated' ? 'warning' : 'danger'">
                            {{ __('admin.puerta.validar.profile.minor_waiver_'.$m['waiver']) }}
                        </x-filament::badge>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</li>
