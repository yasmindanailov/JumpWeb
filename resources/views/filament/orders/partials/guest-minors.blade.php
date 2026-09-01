@php
    /**
     * Los menores INVITADOS de un pedido, vistos por el OPERADOR
     * (`docs/specs/waiver-por-reserva.md` §4.12, §12.1, §12.6; `#337` y la T7).
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
     *
     * ⚠️⚠️ **La sección existe también con CERO justificantes** desde la T7, y por eso hay estado
     * vacío: antes se ocultaba cuando no había ninguno **con el botón del enlace dentro**, así que el
     * operador no tenía por dónde empezar (§12.1·a). Un estado vacío que dice qué hacer no es ruido:
     * es la mitad útil de esta sección durante toda la vida del pedido hasta que alguien firma.
     *
     * ⚠️ **El contexto viene de Booking por CONTRATO** (`AuthorizableOrders`), como en la hoja de
     * sala: Identity no puede mirar a Booking, así que quien los junta es la capa de entrega.
     */
    use App\Domain\Booking\Contracts\AuthorizableOrders;
    use App\Domain\Identity\Services\GuardianRoster;
    use App\Domain\Identity\Services\WaiverStatus;

    $rows = app(GuardianRoster::class)->forOperator((int) $record->getKey());
    $context = app(AuthorizableOrders::class)->find((int) $record->getKey());
    $capacity = $context?->capacity ?? 0;
    // §12.6: bajar la cantidad NO borra justificantes —son firmas con valor probatorio— así que un
    // pedido puede acabar con más papeles que plazas. Antes no lo decía nadie y la hoja de sala
    // imprimía los cincuenta tan tranquila. *Entre un fallo mudo y uno que habla, el que habla.*
    $overflow = count($rows) > $capacity;
@endphp

<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ trans_choice('admin.orders.guest_minors.count', count($rows), ['count' => count($rows)]) }}
        · {{ trans_choice('admin.orders.guest_minors.capacity', $capacity, ['count' => $capacity]) }}
    </p>

    @if ($overflow)
        <p class="text-sm font-medium text-danger-600 dark:text-danger-400" data-guest-minors-overflow>
            {{ __('admin.orders.guest_minors.overflow', ['count' => count($rows), 'capacity' => $capacity]) }}
        </p>
    @endif

    @if ($rows === [])
        {{-- El estado vacío DICE QUÉ HACER. Es la pantalla que ve el operador en el momento en que
             esta feature sirve para algo: justo después de vender, cuando todavía no ha firmado
             nadie y hay que repartir el enlace. --}}
        <p class="text-sm text-gray-500 dark:text-gray-400" data-guest-minors-empty>
            {{ __('admin.orders.guest_minors.empty') }}
        </p>
    @else
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
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button
            wire:click="mountAction('copyGuardianLink')"
            icon="heroicon-o-link"
            color="gray"
            size="sm"
        >
            {{ __('admin.orders.guest_minors.copy_link') }}
        </x-filament::button>

        {{-- Mandárselo al cliente por correo, que es lo que el owner pidió: *«el operador puede
             generar enlace, se lo envía al cliente y que el cliente se lo envíe al padre»*. Reutiliza
             la infraestructura de reenvíos del panel (`RESEND_TYPE_*`), con su límite y su rastro.
             ⚠️ Solo con buzón: sin correo, el enlace se copia y se pasa por otro canal. --}}
        @if (filled($record->user?->email))
            <x-filament::button
                wire:click="mountAction('sendGuardianLink')"
                icon="heroicon-o-paper-airplane"
                color="gray"
                size="sm"
            >
                {{ __('admin.orders.guest_minors.send_link') }}
            </x-filament::button>
        @endif
    </div>
</div>
