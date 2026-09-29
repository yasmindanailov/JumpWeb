{{--
    La línea de tiempo de un correo enviado (`specs/correos-salientes.md` §4.10, `#796`): cuándo salió, cada apertura (con su
    origen, §4.12) y cada visita que llegó con su marca, en la hora del parque —cuánto después del envío, qué enlace y desde
    qué—. Lo de una máquina (Apple al entregar, un escáner, la vista previa de un chat, lo mismo otra vez) va también,
    atenuado y con su porqué: no cuenta.

    @var list<array{kind: string, at: string, after: string|null, route: string|null, device: string|null, via: string|null, counts: bool, why: string|null}> $events
--}}
<div data-email-activity>
    @if (count($events) <= 1)
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400" data-email-activity-empty>{{ __('admin.email_sends.activity.nothing_yet') }}</p>
    @endif
    <ol class="divide-y divide-gray-100 text-sm dark:divide-white/10">
        @foreach ($events as $event)
            <li @class(['flex items-start justify-between gap-3 py-2', 'opacity-60' => ! $event['counts']]) data-email-activity-event="{{ $event['kind'] }}" data-counts="{{ $event['counts'] ? '1' : '0' }}">
                <div class="min-w-0">
                    <p class="font-medium text-gray-800 dark:text-gray-200">
                        {{ __('admin.email_sends.activity.kind.'.$event['kind']) }}@if ($event['route'] !== null) · <code class="text-xs">{{ $event['route'] }}</code>@endif
                    </p>
                    @if ($event['after'] !== null || $event['device'] !== null || $event['via'] !== null || $event['why'] !== null)
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ implode(' · ', array_filter([$event['after'], $event['device'], $event['via'], $event['why']])) }}
                        </p>
                    @endif
                </div>
                <span class="shrink-0 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $event['at'] }}</span>
            </li>
        @endforeach
    </ol>
</div>
