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
    $minorLabel = static function (array $m): string {
        $label = __('admin.puerta.validar.profile.minor', ['age' => $m['age']]);

        return $m['waiver'] === null ? $label : $label.' · '.__('admin.puerta.validar.profile.minor_waiver_'.$m['waiver']);
    };
@endphp
<li class="rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" data-gate-reservation="{{ $r['order_code'] }}">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $r['product'] }}</span>
        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $r['order_code'] }}</span>
    </div>
    <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
        <span class="font-medium">{{ DisplayTime::dayLabel($r['date']) }}</span>
        @if ($r['time_window'])<span class="text-gray-400"> · </span>{{ $r['time_window'] }}@endif
        <span class="text-gray-400"> · </span>{{ $units }}
        @foreach ($r['addons'] as $addon)
            <span class="text-gray-500 dark:text-gray-400"> · + {{ $addon }}</span>
        @endforeach
    </div>
    @if ((int) $r['pending_gate_cents'] > 0)
        <p class="mt-2 rounded-md bg-amber-50 px-2 py-1 text-sm font-semibold text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30" data-gate-pending>
            {{ __('admin.puerta.validar.profile.pending_gate', ['amount' => Money::amount((int) $r['pending_gate_cents'])]) }}
        </p>
    @else
        <p class="mt-2 text-sm text-green-700 dark:text-green-300">{{ __('admin.puerta.validar.profile.nothing_pending') }}</p>
    @endif
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {{ __('admin.puerta.validar.profile.paid', ['amount' => Money::amount((int) $r['paid_online_cents']), 'method' => $method]) }}
        · {{ __('admin.puerta.validar.profile.booked_on', ['when' => DisplayTime::format($r['created_at'], 'd/m/Y H:i')]) }}@if ($r['paid_at']) · {{ __('admin.puerta.validar.profile.paid_on', ['when' => DisplayTime::format($r['paid_at'], 'd/m/Y H:i')]) }}@endif
    </p>
    @if ($r['minors'] !== [])
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            {{ __('admin.puerta.validar.profile.minors_on_line', ['list' => implode(', ', array_map($minorLabel, $r['minors']))]) }}
        </p>
    @endif
</li>
