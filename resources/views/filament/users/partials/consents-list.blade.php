@php
    /**
     * @var \App\Models\User $record
     *
     * Fase 7.5 (decisión #180): consentimientos del usuario, solo lectura. Trazas
     * de auditoría legal, no editables. Una cuenta anonimizada los pierde
     * (`User::anonymize()` los borra) → empty-state.
     *
     * Las etiquetas de tipo viven en `admin.users.consents.types.*` (panel ES+ZH),
     * NO en `account.php` (que no tiene paridad ZH y caería a ES en el panel chino).
     */
    $consents = $record->consents()->orderByDesc('accepted_at')->get();
@endphp

@if ($consents->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.users.consents.empty') }}</p>
@else
    <ul class="space-y-1.5">
        @foreach ($consents as $consent)
            <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="font-medium text-gray-800 dark:text-gray-200">
                    {{ __('admin.users.consents.types.'.$consent->type) }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ \App\Domain\Platform\Services\DisplayTime::format($consent->accepted_at, 'd/m/Y') }} · v{{ $consent->version }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
