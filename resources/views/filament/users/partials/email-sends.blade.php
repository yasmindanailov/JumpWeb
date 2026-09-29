@php
    /**
     * @var \App\Domain\Identity\Models\User $record
     *
     * **Los correos de este cliente** (`specs/correos-salientes.md` §4.2, `#794`): los diez últimos —cuándo, cuál, si
     * salió y sus clics (la C2, §4.8)— y «Ver todos», que abre la página de los correos enviados filtrada a él (allí está la
     * vista previa). Solo con `emails.view` (la sección se esconde sin él). Una cuenta anonimizada ya no tiene envíos atados
     * (`RGPD-01`).
     */
    use App\Domain\Platform\Models\EmailSend;
    use App\Domain\Platform\Services\DisplayTime;
    use App\Filament\Resources\EmailSends\EmailSendResource;
    use App\Filament\Resources\EmailSends\Tables\EmailSendTable;

    $sends = EmailSend::query()->where('user_id', $record->getKey())->withClickCounts()->latest('id')->limit(10)->get();
@endphp

@if ($sends->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400" data-email-sends="none">{{ __('admin.email_sends.none_for_user') }}</p>
@else
    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/10" data-email-sends="{{ $sends->count() }}">
        @foreach ($sends as $send)
            <li class="flex items-start justify-between gap-3 py-2" data-email-send-state="{{ EmailSendTable::state($send) }}">
                <div class="min-w-0">
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ EmailSendTable::label($send->mail_key) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ DisplayTime::format($send->sent_at ?? $send->failed_at ?? $send->created_at, 'd/m/Y H:i') }}</p>
                </div>
                <div class="shrink-0 text-right text-xs text-gray-600 dark:text-gray-300">
                    <p>{{ trans_choice('admin.email_sends.state.'.EmailSendTable::state($send), $send->failures, ['count' => $send->failures]) }}</p>
                    <p class="text-gray-500 dark:text-gray-400" data-email-send-clicks>{{ EmailSendTable::clicks($send) }}</p>
                </div>
            </li>
        @endforeach
    </ul>
    <a href="{{ EmailSendResource::urlForUser((int) $record->getKey()) }}" class="mt-2 inline-block text-sm font-medium text-primary-600 hover:underline dark:text-primary-400" data-email-sends-all>
        {{ __('admin.email_sends.see_all') }}
    </a>
@endif
