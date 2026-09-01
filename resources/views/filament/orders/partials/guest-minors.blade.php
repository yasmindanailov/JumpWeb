@php
    /**
     * Los menores INVITADOS de un pedido, vistos por el OPERADOR
     * (`docs/specs/waiver-por-reserva.md` §4.12, `#337`).
     *
     * ⚠️ Es la forma `forOperator()` del roster: lleva el adulto que firmó y su relación —que es lo
     * que sostiene que pudiera firmar por ese niño— y **NO lleva su correo ni su teléfono**. Quien
     * necesite la prueba completa abre el registro probatorio, que tiene permiso propio y consulta
     * auditada. Si algún día hace falta el contacto aquí, se decide y se audita; no se añade porque
     * quepa en la tabla.
     *
     * ⚠️ El ENLACE es una credencial portadora: se sirve bajo demanda, en un modal, y **nunca** se
     * siembra en el HTML de la página (la prohibición que `AccountContextResource` documenta para su
     * gemelo del post-form).
     */
    use App\Domain\Identity\Services\GuardianRoster;
    use App\Domain\Identity\Services\WaiverStatus;

    $rows = app(GuardianRoster::class)->forOperator((int) $record->getKey());
@endphp

<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ trans_choice('admin.orders.guest_minors.count', count($rows), ['count' => count($rows)]) }}
    </p>

    <ul class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($rows as $row)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2" data-guest-minor>
                <div class="min-w-0">
                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ $row['minor'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('admin.orders.guest_minors.signed_by', [
                            'name' => $row['guardian'],
                            'relationship' => __('guardian.relationships.'.$row['relationship']),
                        ]) }}
                        @if ($row['signed_on'])
                            · {{ $row['signed_on'] }}
                        @endif
                    </div>
                </div>

                {{-- `#320`: solo la EXCEPCIÓN lleva pastilla. Un justificante vigente no se anuncia —
                     es la condición para que ese niño esté en la lista. --}}
                @if (WaiverStatus::minorStateIsNoteworthy($row['waiver']))
                    <x-filament::badge size="sm" :color="$row['waiver'] === 'outdated' ? 'warning' : 'danger'">
                        {{ __('admin.orders.guest_minors.waiver_'.$row['waiver']) }}
                    </x-filament::badge>
                @endif
            </li>
        @endforeach
    </ul>

    <x-filament::button
        wire:click="mountAction('copyGuardianLink')"
        icon="heroicon-o-link"
        color="gray"
        size="sm"
    >
        {{ __('admin.orders.guest_minors.copy_link') }}
    </x-filament::button>
</div>
