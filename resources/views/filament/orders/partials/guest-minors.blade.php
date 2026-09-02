@php
    /**
     * Los menores INVITADOS de un pedido, vistos por el OPERADOR
     * (`docs/specs/waiver-por-reserva.md` §4.12, §12.1, §12.6, §13).
     *
     * ⚠️⚠️ **Agrupado POR RESERVA desde `#401`, y ése era el fallo de fondo.** El justificante cuelga
     * de la visita, no del pedido: un pedido con una excursión el lunes y una entrada el miércoles
     * tiene **dos** enlaces, dos cupos y dos listas. Antes se pintaba una sola lista y un solo enlace
     * que, abierto, decía las dos fechas.
     *
     * ⚠️ Es la forma `forOperator()` del roster: lleva el adulto que firmó y su relación —que es lo
     * que sostiene que pudiera firmar por ese niño— y **NO lleva su correo ni su teléfono**. Quien
     * necesite la prueba completa abre el registro probatorio, que tiene permiso propio y consulta
     * auditada.
     *
     * ⚠️ El ENLACE es una credencial portadora: se sirve bajo demanda, en un modal, y **nunca** se
     * siembra en el HTML de la página (la prohibición que `AccountContextResource` documenta).
     *
     * ⚠️ **Las plazas LIBRES descuentan los menores a cargo ya asignados** (`GuardianPlaces`): el
     * owner compró UNA entrada, se la asignó a su hija, y la pantalla decía «3 plazas».
     */
    use App\Domain\Booking\Contracts\AuthorizableReservations;
    use App\Domain\Identity\Services\GuardianPlaces;
    use App\Domain\Identity\Services\GuardianRoster;
    use App\Domain\Identity\Services\WaiverStatus;

    $reservations = app(AuthorizableReservations::class)->markedForOrder((int) $record->getKey());
    $roster = app(GuardianRoster::class);
    $places = app(GuardianPlaces::class);

    // Las que NO nacieron marcadas pero ya tienen justificantes: el operador pudo mandar el enlace a
    // mano (el caso «el cliente no sabía»). Sin esto desaparecerían de la ficha.
    $markedIds = array_map(fn ($r): int => $r->reservationId, $reservations);
    foreach ($record->items()->whereNull('parent_item_id')->whereNull('cancelled_at')->orderBy('id')->get() as $item) {
        if (! in_array((int) $item->getKey(), $markedIds, true) && $roster->countFor((int) $item->getKey()) > 0) {
            $reservations[] = app(AuthorizableReservations::class)->find((int) $item->getKey());
        }
    }
    $reservations = array_values(array_filter($reservations));
@endphp

<div class="space-y-5">
    @foreach ($reservations as $reservation)
        @php
            $rows = $roster->forOperator($reservation->reservationId);
            $assigned = $places->assignedDependents($reservation->reservationId);
            $capacity = max(0, $reservation->quantity - $assigned);
            // §12.6: bajar la cantidad NO borra justificantes —son firmas con valor probatorio— así
            // que una reserva puede acabar con más papeles que plazas. Antes no lo decía nadie.
            $overflow = count($rows) > $capacity;
        @endphp

        <div class="space-y-3" data-guest-minors-reservation="{{ $reservation->reservationId }}">
            {{-- QUÉ visita, que es lo que distingue una lista de la otra. --}}
            <div class="text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ $reservation->productName }}
                @if ($reservation->date)
                    · {{ \App\Domain\Platform\Services\DisplayTime::format(\Illuminate\Support\Carbon::parse($reservation->date), 'd/m/Y') }}
                @endif
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ trans_choice('admin.orders.guest_minors.count', count($rows), ['count' => count($rows)]) }}
                · {{ trans_choice('admin.orders.guest_minors.capacity', $capacity, ['count' => $capacity]) }}
                @if ($assigned > 0)
                    · {{ trans_choice('admin.orders.guest_minors.assigned', $assigned, ['count' => $assigned]) }}
                @endif
            </p>

            @if ($overflow)
                <p class="text-sm font-medium text-danger-600 dark:text-danger-400" data-guest-minors-overflow>
                    {{ __('admin.orders.guest_minors.overflow', ['count' => count($rows), 'capacity' => $capacity]) }}
                </p>
            @endif

            @if ($rows === [])
                {{-- El estado vacío DICE QUÉ HACER. Es la pantalla que ve el operador justo después de
                     vender, cuando todavía no ha firmado nadie y hay que repartir el enlace. --}}
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

                            {{-- `#320`: solo la EXCEPCIÓN lleva pastilla. Un justificante vigente no se
                                 anuncia — es la condición para que ese niño esté en la lista. --}}
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
                    wire:click="mountAction('copyGuardianLink', { item: {{ $reservation->reservationId }} })"
                    icon="heroicon-o-link"
                    color="gray"
                    size="sm"
                >
                    {{ __('admin.orders.guest_minors.copy_link') }}
                </x-filament::button>

                {{-- Mandárselo al cliente por correo, que es lo que el owner pidió: *«el operador puede
                     generar enlace, se lo envía al cliente y que el cliente se lo envíe al padre»*.
                     Reutiliza la infraestructura de reenvíos del panel, con su límite y su rastro.
                     ⚠️ Solo con buzón: sin correo, el enlace se copia y se pasa por otro canal. --}}
                @if (filled($record->user?->email))
                    <x-filament::button
                        wire:click="mountAction('sendGuardianLink', { item: {{ $reservation->reservationId }} })"
                        icon="heroicon-o-paper-airplane"
                        color="gray"
                        size="sm"
                    >
                        {{ __('admin.orders.guest_minors.send_link') }}
                    </x-filament::button>
                @endif
            </div>
        </div>
    @endforeach
</div>
