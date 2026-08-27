@php
    /**
     * @var \App\Domain\Identity\Models\User $record
     *
     * Fase 6 · waiver (`docs/specs/waiver-probatorio.md` §4.6): el REGISTRO probatorio del titular,
     * solo lectura y fuera de la ficha normal — se abre desde una acción con permiso propio y cada
     * apertura queda auditada (`ViewUser::waiverProofAction`). Sobrevive a la anonimización: es
     * exactamente entonces cuando hace falta.
     */
    use App\Domain\Identity\Services\WaiverStatus;
    use App\Domain\Platform\Services\DisplayTime;

    $status = WaiverStatus::for($record);
    $signatures = $record->waiverSignatures()->with(['version', 'declaredBy'])->orderByDesc('id')->get();
@endphp

<div class="space-y-4 text-sm">
    <p class="text-gray-600 dark:text-gray-300">
        <span class="font-medium">{{ __('admin.waiver.proof.status_label') }}:</span>
        {{ __('admin.waiver.modes.'.$status->mode) }}
        @if ($status->isEnabled())
            ·
            @if (! $status->signed)
                {{ __('admin.waiver.proof.status_unsigned') }}
            @elseif ($status->isOutdated())
                {{ __('admin.waiver.proof.status_outdated', ['version' => $status->version]) }}
            @else
                {{ __('admin.waiver.proof.status_current', ['version' => $status->version]) }}
            @endif
        @endif
    </p>

    @if ($signatures->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">{{ __('admin.waiver.proof.empty') }}</p>
    @else
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($signatures as $signature)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-800 dark:text-gray-200">
                            {{ DisplayTime::format($signature->accepted_at) }}
                            · {{ $signature->version?->label() ?? '—' }}
                            · {{ __('admin.waiver.proof.channels.'.$signature->channel) }}
                            @if (! $signature->isForHolder())
                                · {{ __('admin.waiver.proof.subject_dependent', ['name' => $signature->subjectName() ?? ('#'.$signature->subject_id)]) }}
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $signature->holderName() ?? '—' }} · {{ $signature->holderEmail() ?? '—' }}
                            @if ($signature->isDeclaredByOperator())
                                · <span class="font-medium text-amber-700 dark:text-amber-300">{{ __('admin.waiver.proof.declared_by', ['operator' => $signature->declaredBy?->name ?? '—']) }}</span>
                            @endif
                            · {{ $signature->verifyHash() ? __('admin.waiver.proof.integrity_ok') : __('admin.waiver.proof.integrity_ko') }}
                        </div>
                    </div>
                    <a href="{{ route('admin.users.waiver.proof', ['user' => $record, 'signature' => $signature]) }}"
                       target="_blank" rel="noopener"
                       class="text-primary-600 underline dark:text-primary-400">
                        {{ __('admin.waiver.proof.pdf') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
