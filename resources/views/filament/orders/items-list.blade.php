@php
    use App\Domain\Booking\Models\OrderItem;
    use App\Domain\Platform\Services\DisplayTime;
    use App\Domain\Platform\Services\Duration;

    /** @var \App\Domain\Booking\Models\Order $record */
    // #F11: ocultamos también los PRINCIPALES voided-leftover (cancelados net-cero,
    // nunca cobrados online ni reembolsados): saldrían fantasma como 0,00 € ·
    // Cancelado. Mismo criterio que los complementos (autoridad única:
    // Order::isVoidedLeftoverItem). Un cancelado con cargo real SÍ se sigue mostrando.
    $items = $record->items->whereNull('parent_item_id')
        ->reject(fn ($i) => $record->isVoidedLeftoverItem($i))
        ->values();
    $authUser = auth()->user();

    // Menores a cargo (Fase 6 · C, tanda 5, spec §9.10 D14·1): para quién es cada ENTRADA, compuesto
    // UNA vez para todo el pedido (presupuesto constante). Solo hay entrada en el mapa si hay algo asignado.
    $assignedByItem = \App\Filament\Resources\Orders\Support\AssignedDependents::forOrder($record);
    // ¿Tiene sentido ofrecer «Asignar menores» en este pedido? Si el titular no tiene menores activos y
    // ninguna línea lleva ya uno, el icono sería ruido en el 95 % de los pedidos. UNA consulta por pedido.
    $holderHasDependents = $assignedByItem !== [] || ($record->user !== null
        && \App\Domain\Identity\Models\Dependent::query()->where('user_id', $record->user_id)->active()->exists());

    // #173: cancelar y reembolsar por-item se gestionan desde el pie del modal
    // Gestionar (#171/#172); ya no hay iconos de esas acciones en la sub-card.
    // La visibilidad real la imponen los handlers de las Filament Actions
    // (mountUsing + handler + orquestador) — defense in depth completa.

    /**
     * Fallback gris para zonas sin color asignado. Coincide con el fallback
     * del CSS `.zone-card` (`#9CA3AF` = gray-400) y se usa también en el
     * resumen operativo del modal. Tener UNA fuente de verdad evita drift.
     */
    $zoneColorFallback = '#9CA3AF';
@endphp

{{-- Banner contextual de la card "Productos del pedido" (sub-fase 7.2e.1bis5,
     decisión #158). Dos mensajes según el ESTADO del Order:

      1. Order CANCELADO entero (`status=cancelled`) → "pedido cancelado, las
         acciones individuales no aplican".
      2. Order FULLY REFUNDED (`isFullyRefunded`) → "reembolsado por completo:
         ya no queda importe que reembolsar; CANCELAR productos SÍ sigue
         disponible" (#225 D9: cancelar ≠ reembolsar — un full-refund deja el
         Order en `paid`, así que `canCancelItem` sigue true; solo `refundItem`
         queda bloqueado por `refundableCapacityCents()<=0`).

     El backend gatea cada action con `canCancelItem`/`canRefundItem`; este
     banner es solo la capa de UX explicativa. --}}
@php
    $orderCancelled = $record->status === \App\Domain\Booking\Models\Order::STATUS_CANCELLED;
    $orderFullyRefunded = $record->isFullyRefunded();
    $showCoherenceBanner = $orderCancelled || $orderFullyRefunded;
    // `#152`: un pedido cancelado CON deuda ya no bloquea el reembolso por línea — el banner
    // deja de afirmar «ya no aplican» y pasa a decir cuánto se debe y por dónde se devuelve.
    // T3·2 del libro: lo que se le debe al cliente lo dice el SALDO del libro (clase de devolución).
    $cancelledDebtCents = $orderCancelled ? \App\Domain\Booking\Services\OrderBook::forOrder($record)->owedToCustomerCents() : 0;
@endphp

@if ($showCoherenceBanner)
    @php
        $coherenceKey = $orderCancelled
            ? ($cancelledDebtCents > 0
                ? 'admin.orders.item_actions.banner.order_cancelled_with_debt'
                : 'admin.orders.item_actions.banner.order_cancelled')
            : 'admin.orders.item_actions.banner.order_fully_refunded';
        $coherenceParams = $cancelledDebtCents > 0
            ? ['pendiente' => number_format($cancelledDebtCents / 100, 2, ',', '.').' €']
            : [];
    @endphp
    <div class="mb-3 flex items-start gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/30">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
        </svg>
        <p>{{ __($coherenceKey, $coherenceParams) }}</p>
    </div>
@endif

<div class="space-y-3">
    @forelse ($items as $item)
        @php
            $status = $item->displayStatusForCustomer();
            $record->loadMissing('adjustments');
            $children = $item->children;
            // Complementos VISIBLES en las listas: ocultamos los CANCELADOS que nunca se
            // cobraron online ni se reembolsaron (p. ej. un menú añadido por extra_due y
            // luego sustituido en un cambio de menú): son net-cero, mostrarlos solo confunde
            // (líneas duplicadas). Los cancelados que SÍ se cobraron se mantienen (tachados),
            // porque implican un reembolso pendiente que el staff debe cerrar.
            $visibleChildren = $children->reject(fn ($c) => $record->isVoidedLeftoverItem($c))->values();
            $ticketType = $item->ticketType;
            $isPack = $ticketType?->isPack() ?? false;
            $zone = $ticketType?->zone;
            $zoneColor = $zone?->color ?? $zoneColorFallback;
            // Sub-fase 7.2e.1bis5 (decisión #158): el badge principal del header
            // del item PASA A SER el nombre del producto (no la zona). El color
            // de zona sigue aplicándose al badge (la "pulsera simbólica" que el
            // empleado escanea), pero el texto es el producto que el cliente
            // compró. Resuelve la duplicación que había antes: el badge "JUMP"/
            // "KIDS"/"Cumpleaños" arriba y el nombre "Cumpleaños Jump" como
            // subtítulo plano debajo eran dos lugares para la misma información.
            // ⚠️ El nombre CRUDO del catálogo, no `displayProductName()`: esta pantalla pinta la
            // etiqueta MIXTA como PASTILLA justo al lado (`specs/cumple-mixto.md` §13), y usar el
            // compositor aquí la diría dos veces. Las superficies de texto plano —los dos PDF, los
            // correos, la puerta, el calendario— sí usan el compositor, porque no pueden pintarla.
            $productName = $ticketType?->tr('name') ?? '—';
            $durationMin = $ticketType?->duration_min;
            $quantityLabel = $isPack
                ? __('tickets.guests_count', ['count' => $item->quantity])
                : $item->quantity.' × '.__('admin.orders.item_detail.unit_entries');

            // Sub-fase 7.2e.1bis5: badge "Reembolsado" del PRINCIPAL del item.
            // Aparece junto al badge del nombre cuando hay refund parcial sobre
            // el item principal (no sus children, esos llevan su propio badge
            // más abajo). Reusa el texto genérico `refunded_badge` del Order
            // por coherencia. NO incluye importe inline — el agregado vive en
            // el bloque "Totales del producto".
            $principalRefundedCents = $record->itemRefundedCents($item);
        @endphp

        {{-- Sub-card del item.
             - El background/ring de la card cambia según el estado operativo
               (`displayStatusForCustomer`): neutro (activo) o atenuado (finalizado).
             - El color de zona se aplica SOLO al badge (`.zone-badge` con
               `style="--zone-color"`), no al wrapper de la card — alineado con
               la operativa: el badge ES la "pulsera" simbólica que el empleado
               escanea.

             Sub-fase 7.2e.1: si el item está CANCELADO (soft-cancel #152),
             la card se renderiza atenuada con borde rojo sutil y sin botones
             de acción — el item es histórico para auditoría, no operativa. --}}
        @php
            $isItemCancelled = $item->isCancelled();
            // P6: icono del TIPO de producto a la izquierda del nombre (entrada → ticket,
            // cumpleaños/pack → tarta). Decorativo; el nombre sigue dando la semántica.
            $typeIcon = $isPack ? 'heroicon-o-cake' : 'heroicon-o-ticket';
            // Veredicto de fiesta MIXTA: desde la T1 (`cumple-mixto.md` §21.5) deriva del SELLO de
            // la reserva —los tramos y precios con los que se vendió—, nunca del catálogo vivo. El
            // lector es sin estado (nada de `scoped` ni memoización: aquello caducó con el sello).
            $mix = app(\App\Domain\Booking\Services\GuestAgeMixReader::class)->for($item);
        @endphp
        {{-- P3/P6: borde superior 5px sólido con el color de la zona/tipo (acento data-driven, visible). --}}
        <div @class([
            'rounded-xl ring-1 p-4',
            'bg-gray-100 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10' => ! $isItemCancelled && $status === OrderItem::STATUS_ACTIVE,
            'bg-gray-50 ring-gray-950/5 dark:bg-gray-900/50 dark:ring-white/5 opacity-80' => ! $isItemCancelled && $status === OrderItem::STATUS_FINISHED,
            'bg-red-50/50 ring-red-600/20 dark:bg-red-400/5 dark:ring-red-400/20 opacity-75' => $isItemCancelled,
        ]) style="border-top: 5px solid {{ $zoneColor }};">
            {{-- Header del sub-card (P6): título GRANDE y CENTRADO con el icono del tipo de
                 producto a su izquierda; debajo, fecha · hora · invitados inline. El badge de
                 estado (Cancelado/Finalizado) queda arriba a la derecha. --}}
            <div class="relative text-center">
                {{-- Estado (no accionable), esquina superior derecha. --}}
                @if ($isItemCancelled)
                    <span class="absolute right-0 top-0 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset bg-red-100 text-red-700 ring-red-600/30 dark:bg-red-400/15 dark:text-red-300 dark:ring-red-400/40">
                        {{ __('admin.orders.item_status.cancelled') }}
                    </span>
                @elseif ($status === OrderItem::STATUS_FINISHED)
                    <span class="absolute right-0 top-0 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset bg-gray-200 text-gray-700 ring-gray-500/30 dark:bg-gray-800 dark:text-gray-300">
                        {{ __('admin.orders.item_status.finished') }}
                    </span>
                @endif

                {{-- Título: icono del tipo (tintado con el color de zona) + nombre del producto, grande. --}}
                <div class="flex items-center justify-center gap-2">
                    <span class="shrink-0" style="color: {{ $zoneColor }};">
                        <x-filament::icon :icon="$typeIcon" class="h-6 w-6" />
                    </span>
                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $productName }}</span>
                    @if (! $isItemCancelled && $principalRefundedCents > 0)
                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset bg-amber-100 text-amber-700 ring-amber-600/30 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/40">
                            {{ __('admin.orders.refunded_badge') }}
                        </span>
                    @endif
                    {{-- La etiqueta MIXTA va JUNTO al nombre pero NO forma parte de él (spec §8.9):
                         el nombre sigue siendo el del catálogo en los 43 puntos que lo componen, y
                         esto es un dato propio de la reserva que se puede filtrar y contar. --}}
                    @if (! $isItemCancelled && $mix->mixed)
                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold ring-1 ring-inset bg-violet-100 text-violet-700 ring-violet-600/30 dark:bg-violet-400/15 dark:text-violet-300 dark:ring-violet-400/40">
                            {{ __('tickets.mixed_party_badge') }}
                        </span>
                    @endif
                </div>

                {{-- Meta inline: fecha · hora · invitados (· duración). Es lo que el empleado más
                     mira cuando un cliente pregunta "¿es a las X?". --}}
                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    @if ($item->slot)
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Carbon::parse($item->slot->date)->isoFormat('ddd D MMM') }}</span>
                        <span class="text-gray-400 dark:text-gray-500">·</span>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $item->displayTimeWindow() }}</span>
                        <span class="text-gray-400 dark:text-gray-500">·</span>
                    @endif
                    <span>{{ $quantityLabel }}</span>
                    @if ($durationLabel = Duration::formatHumane($durationMin))
                        <span class="text-gray-400 dark:text-gray-500">·</span>
                        <span>{{ $durationLabel }}</span>
                    @endif
                </div>

                {{-- Menores a cargo (tanda 5, D14·1): para quién es cada entrada — nombre, edad EN LA FECHA
                     DE LA VISITA y el estado de su exención (solo en modo interno). Solo líneas de ENTRADA;
                     el resto de unidades son adultos. --}}
                @if (! empty($assignedByItem[$item->id] ?? []))
                    <div class="mt-1 text-sm text-gray-700 dark:text-gray-300" data-dependents-for="{{ $item->id }}">
                        <span class="font-medium">{{ __('admin.orders.dependents.for') }}</span>
                        @foreach ($assignedByItem[$item->id] as $dep)
                            <span class="whitespace-nowrap">{{ $dep['label'] }}{{ $loop->last ? '' : ',' }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Sub-fase 7.2e.1bis5 (decisión #158): subtítulo con nombre del
                 producto ELIMINADO. La información vive ahora en el badge
                 principal del header con color de zona. --}}

            {{-- Sub-fase 7.2e.1: meta de cancelación si aplica (audit visible para
                 el operador + cliente vía "Mis pedidos"). --}}
            @if ($isItemCancelled)
                <div class="mt-1 text-xs text-red-700 dark:text-red-300">
                    {{ __('admin.orders.cancel_item.meta', [
                        'when' => DisplayTime::format($item->cancelled_at, 'd/m/Y H:i'),
                        'who' => $item->cancelledBy?->name ?? '—',
                    ]) }}
                </div>
            @endif

            {{-- Datos del evento (#86) + datos por niño (post-form #217) en un ÚNICO subcard
                 COLAPSABLE (decisión clienta: más limpio). El badge del estado del post-form se ve
                 SIEMPRE en la cabecera; el detalle (evento + tabla por-niño) se despliega con
                 «ver más». Las labels del evento se ordenan por el ESQUEMA del pack (#173); las
                 claves huérfanas (pack editado) caen al final con la clave como label. --}}
            @php
                $eventFields = $ticketType?->eventFields() ?? [];
                $schemaKeys = collect($eventFields)->pluck('key')->all();
                $eventRows = [];
                if (is_array($item->event_data)) {
                    foreach ($eventFields as $field) {
                        $value = $item->event_data[$field['key']] ?? null;
                        if ($value !== null && $value !== '') {
                            $eventRows[] = ['label' => $ticketType->eventFieldLabel($field), 'value' => $value];
                        }
                    }
                    foreach ($item->event_data as $key => $value) {
                        if (! in_array($key, $schemaKeys, true) && $value !== null && $value !== '') {
                            $eventRows[] = ['label' => ucfirst(str_replace('_', ' ', (string) $key)), 'value' => $value];
                        }
                    }
                }
                $guestFormStatus = $item->guestFormStatus();
                $guestFields = $guestFormStatus !== null ? ($ticketType?->guestFields() ?? []) : [];
                $guestData = $item->guestData();
                $guestOk = $guestFormStatus === \App\Domain\Booking\Models\OrderItem::GUEST_FORM_STATUS_OK;
            @endphp
            @if (count($eventRows) > 0 || $guestFormStatus !== null)
                <div x-data="{ open: false }" class="mt-3 rounded-lg bg-white/60 p-3 text-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $guestFormStatus !== null ? __('admin.orders.party_data_section') : __('admin.orders.event_data_section') }}</span>
                        @if ($guestFormStatus !== null)
                            @if ($guestOk)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-500/15 dark:text-green-400">✓ {{ __('admin.orders.guest_badge_ok') }}</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">! {{ __('admin.orders.guest_badge_pending') }}</span>
                            @endif
                        @endif
                        <button type="button" x-on:click="open = ! open" class="ml-auto inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                            <span x-show="! open">{{ __('admin.orders.show_more') }}</span>
                            <span x-show="open" x-cloak>{{ __('admin.orders.show_less') }}</span>
                        </button>
                    </div>

                    {{-- Fiesta MIXTA (`docs/specs/cumple-mixto.md` §12). `[DECIDIDO owner]`: el
                         suplemento NO se aprueba — sigue solo a las edades declaradas. Lo que el
                         operador necesita ver aquí son cuatro cosas distintas, y por eso no se
                         funden en un número: cuánto está APLICADO, si lo escrito se ha quedado
                         atrás, si falta el producto que lo lleva (y entonces esta fiesta no cobra
                         nada) y si el veredicto está todavía a medias.
                         T5 (§25.6): el ORDEN es lo escrito primero y las condiciones después, con
                         etiqueta — este bloque es la única superficie que mezcla derivado y
                         escrito, y sin etiquetar cuál es cuál no se explicaba solo. --}}
                    @php
                        $mixService = app(\App\Domain\Booking\Services\MixedPartySurcharge::class);
                        $mixWritten = $mixService->written($item);
                        // ¿Hay ALGO escrito (cargo o descuento)? El neto puede ser 0 con las dos
                        // cosas puestas (T4: un cargo cubierto entero por el descuento) y eso sigue
                        // siendo dinero escrito que explicar.
                        $mixHasWritten = $mixWritten['charge_cents'] > 0 || $mixWritten['credit_cents'] > 0;
                        // El desfase se mira por el lado del CARGO (su propósito de siempre: lo
                        // escrito no sigue al catálogo). El lado del descuento se escribe ENTERO
                        // desde la T3·3 del libro (D4): lo que la puerta no absorbe es saldo «a
                        // devolver en el parque», que el libro de la reserva dice más abajo.
                        $mixDrift = $mix->applies
                            && $mix->surchargeCents !== null
                            && $mix->surchargeCents !== $mixWritten['charge_cents'];
                        // ⚠️⚠️ Hay dinero escrito que el cliente debe en el parque y NO hay veredicto
                        // contra el que contrastarlo. Sin esta rama el bloque entero desaparecía y
                        // el operador se encontraba un importe en «a cobrar en el parque» SIN una
                        // sola línea que lo explicara: medido, 7,00 € y cero explicación. Con el
                        // sello (`specs/cumple-mixto.md` §21.5) esto ya no lo produce un cambio de
                        // catálogo: lo produce una reserva SIN sello (anterior a él), y se dice así.
                        $mixOrphaned = ! $mix->applies && ! $mix->staleSeal && $mixHasWritten;
                        // El sello no corresponde a la fila (pack o fecha movidos sin re-sellar):
                        // el veredicto calla y no se mueve dinero, pero el operador tiene que verlo.
                        $mixStale = $mix->staleSeal;
                        // Los portadores solo se consultan cuando su ausencia explicaría algo:
                        // cuando el veredicto pide escribir y no hay nada escrito de ese lado.
                        $mixCarrierMissing = $mix->mixed
                            && ($mix->surchargeCents ?? 0) > 0
                            && $mixWritten['charge_cents'] === 0
                            && \App\Domain\Booking\Services\MixedPartySettings::surchargeProduct() === null;
                        $mixCreditCarrierMissing = ($mix->savingsCents ?? 0) > 0
                            && $mixWritten['credit_cents'] === 0
                            && \App\Domain\Booking\Services\MixedPartySettings::creditProduct() === null;
                    @endphp
                    @if (! $isItemCancelled && ($mixOrphaned || $mixStale || ($mix->applies && ($mix->mixed || $mixHasWritten || ! $mix->isComplete()))))
                        <div class="mt-2 space-y-1 rounded-md bg-violet-50 p-2 ring-1 ring-violet-600/15 dark:bg-violet-400/10 dark:ring-violet-400/20">
                            @if ($mix->mixed)
                                <div class="font-medium text-violet-800 dark:text-violet-300">
                                    {{ __('admin.orders.mixed_party.title') }}
                                </div>
                            @endif

                            @if ($mixWritten['charge_cents'] > 0)
                                <div class="font-semibold text-violet-900 dark:text-violet-200">
                                    {{ __('admin.orders.mixed_party.applied', ['amount' => \App\Domain\Platform\Services\Money::format($mixWritten['charge_cents'])]) }}
                                </div>
                            @endif
                            @if ($mixWritten['credit'] !== null)
                                {{-- T4 (§24.5): el DESCUENTO escrito, con la frase compuesta por el
                                     dominio — la misma que leen el cliente, la hoja y la puerta. --}}
                                <div class="font-semibold text-violet-900 dark:text-violet-200">
                                    {{ $mixWritten['credit']['label'] }}: −{{ \App\Domain\Platform\Services\Money::format($mixWritten['credit']['cents']) }}
                                </div>
                            @endif
                            @if ($mixWritten['charge_cents'] > 0 && $mixWritten['credit_cents'] > 0)
                                <div class="text-violet-900 dark:text-violet-200">
                                    {{ __('admin.orders.mixed_party.net', ['amount' => ($mixWritten['cents'] < 0 ? '−' : '+').\App\Domain\Platform\Services\Money::format(abs($mixWritten['cents']))]) }}
                                </div>
                            @endif

                            {{-- T5 (§25.6·1, el hallazgo del T0): las CONDICIONES van DESPUÉS de lo
                                 escrito —«el aplicado primero», el arreglo que dio el owner— y con
                                 etiqueta de origen: sin ella, «2 × Jump · 9,00 €» pegado a
                                 «Suplemento aplicado: 8,00 €» se lee como contradicción hasta llegar
                                 a la frase del desfase (el owner mismo tuvo que preguntar). La
                                 dirección barata no pinta su «0,00 €» ({@see GuestAgeMix::visibleUpgrades}):
                                 su historia la cuenta el descuento de arriba y el saldo del libro. --}}
                            @if ($mix->mixed)
                                @foreach ($mix->visibleUpgrades() as $upgrade)
                                    <div class="text-violet-800/90 dark:text-violet-300/90">
                                        {{ __('admin.orders.mixed_party.conditions_line', [
                                            'count' => $upgrade['count'],
                                            'name' => $upgrade['name'],
                                            'unit' => $upgrade['unit_cents'] === null
                                                ? '—'
                                                : \App\Domain\Platform\Services\Money::format($upgrade['unit_cents']),
                                        ]) }}
                                    </div>
                                @endforeach
                            @endif

                            @if ($mixStale)
                                {{-- En ROJO y antes que nada: es un dato inconsistente, no un estado
                                     del negocio. Ningún camino del producto lo produce; si aparece,
                                     alguien movió la fila por fuera del sellador. --}}
                                <div class="font-semibold text-red-700 dark:text-red-300">{{ __('admin.orders.mixed_party.stale_seal') }}</div>
                            @elseif ($mixOrphaned)
                                {{-- Se le dice al operador las dos cosas: que el cargo sigue vivo y
                                     que no hay veredicto contra el que contrastarlo, para que no
                                     lo lea como un error del sistema ni intente cuadrarlo. --}}
                                <div class="text-amber-800 dark:text-amber-300">{{ __('admin.orders.mixed_party.orphaned') }}</div>
                            @elseif ($mix->mixed && $mix->surchargeCents === null)
                                <div class="text-amber-800 dark:text-amber-300">{{ __('admin.orders.mixed_party.unpriced') }}</div>
                            @else
                                {{-- T5 (§25.6·3): los avisos van por LADOS y los lados son
                                     INDEPENDIENTES — el portador del descuento puede faltar Y el
                                     cargo estar en desfase A LA VEZ, y la cadena única se tragaba el
                                     desfase (medido en §25.2). Dentro de cada lado sí hay
                                     precedencia: el portador ausente ES la explicación de su propio
                                     desfase, así que no se pintan los dos. --}}
                                @if ($mixCarrierMissing)
                                    <div class="font-semibold text-red-700 dark:text-red-300">{{ __('admin.orders.mixed_party.missing_carrier') }}</div>
                                @elseif ($mixDrift)
                                    <div class="text-amber-800 dark:text-amber-300">
                                        {{ __('admin.orders.mixed_party.drift', [
                                            'written' => \App\Domain\Platform\Services\Money::format($mixWritten['charge_cents']),
                                            'derived' => \App\Domain\Platform\Services\Money::format($mix->surchargeCents),
                                        ]) }}
                                    </div>
                                @endif
                                @if ($mixCreditCarrierMissing)
                                    <div class="font-semibold text-red-700 dark:text-red-300">{{ __('admin.orders.mixed_party.missing_credit_carrier') }}</div>
                                @endif
                            @endif

                            @if ($mix->withoutAge > 0)
                                {{-- El dinero solo se mueve con TODAS las edades (`#285` §20.6): con
                                     dinero escrito, lo que importa es que está CONGELADO y cuántas
                                     faltan; sin nada escrito, solo que el veredicto puede cambiar. --}}
                                @if ($mixHasWritten)
                                    <div class="text-amber-800 dark:text-amber-300">{{ trans_choice('admin.orders.mixed_party.frozen', $mix->withoutAge, ['count' => $mix->withoutAge]) }}</div>
                                @else
                                    <div class="text-amber-800 dark:text-amber-300">{{ __('admin.orders.mixed_party.without_age', ['count' => $mix->withoutAge]) }}</div>
                                @endif
                            @endif
                            @if ($mix->outOfRange > 0)
                                <div class="text-amber-800 dark:text-amber-300">{{ __('admin.orders.mixed_party.out_of_range', ['count' => $mix->outOfRange]) }}</div>
                            @endif
                        </div>
                    @endif

                    <div x-show="open" x-cloak class="mt-2 space-y-3">
                        @if (count($eventRows) > 0)
                            <dl class="flex flex-col gap-y-1">
                                @foreach ($eventRows as $row)
                                    @php $shown = is_scalar($row['value']) ? (string) $row['value'] : json_encode($row['value'], JSON_UNESCAPED_UNICODE); @endphp
                                    <div class="flex gap-2">
                                        <dt class="shrink-0 font-medium text-gray-600 dark:text-gray-400">{{ $row['label'] }}:</dt>
                                        <dd class="text-gray-800 dark:text-gray-200 break-words">{{ $shown }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        @if ($guestFormStatus !== null)
                            <div>
                                @if (! empty($guestData))
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead>
                                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                                    <th class="py-1 pr-2 font-medium">#</th>
                                                    @foreach ($guestFields as $gf)
                                                        <th class="py-1 pr-2 font-medium">{{ $ticketType->guestFieldLabel($gf) }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @for ($gi = 0; $gi < $item->quantity; $gi++)
                                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                                        <td class="py-1 pr-2 text-gray-400">{{ $gi + 1 }}</td>
                                                        @foreach ($guestFields as $gf)
                                                            <td class="py-1 pr-2 text-gray-800 dark:text-gray-200">{{ $guestData[$gi][$gf['key']] ?? '—' }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endfor
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-gray-400 dark:text-gray-500">{{ __('admin.orders.guests_empty') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Children / addons (complementos asociados a este item principal).
                 Sub-fase 7.2e.1bis2 (feedback 2026-05-30): cada child muestra
                 estado individual (Cancelado tachado o ↩ Devuelto badge), SIN
                 precio inline a la derecha — el agregado total se reserva para
                 la sección "Totales del producto" debajo. Visualmente la
                 lista de children queda como inventario "qué incluye el pack",
                 no como mini-tabla de precios. --}}
            @if ($visibleChildren->isNotEmpty())
                <ul class="mt-3 ml-6 space-y-1 border-l border-gray-200 pl-3 dark:border-white/10">
                    @foreach ($visibleChildren as $child)
                        @php
                            $childCancelled = $child->isCancelled();
                            $childRefunded = $record->itemRefundedCents($child);
                            $childBadge = $child->addonBadgeKey();
                        @endphp
                        <li @class([
                            'flex flex-wrap items-center gap-2 text-sm',
                            'text-gray-600 dark:text-gray-400' => ! $childCancelled,
                            'text-gray-500 line-through dark:text-gray-500' => $childCancelled,
                        ])>
                            <span>+ {{ $child->quantity }} × {{ $child->ticketType?->tr('name') }}</span>
                            @if ($childBadge)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-700 ring-1 ring-inset ring-emerald-600/20 no-underline dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/30">{{ __('tickets.addon_badge_'.$childBadge) }}</span>
                            @endif
                            @if ($childCancelled)
                                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset bg-red-100 text-red-700 ring-red-600/30 dark:bg-red-400/15 dark:text-red-300 dark:ring-red-400/40 no-underline">
                                    {{ __('admin.orders.item_status.cancelled') }}
                                </span>
                            @elseif ($childRefunded > 0)
                                {{-- Sub-fase 7.2e.1bis5 (decisión #158): badge
                                     unificado "Reembolsado" sin formato propio.
                                     El importe agregado vive en el bloque
                                     "Totales del producto" más abajo. --}}
                                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset bg-amber-100 text-amber-700 ring-amber-600/30 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/40">
                                    {{ __('admin.orders.refunded_badge') }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Sección "Totales del producto" (sub-fase 7.2e.1bis2,
                 feedback 2026-05-30): bloque agregado abajo del item con:
                  • Línea Producto: importe del principal.
                  • Línea Complementos: suma de complementos (si los hay).
                  • Línea Total del producto: suma final.
                  • Badges financieros DENTRO: ↩ Devuelto / ⚠ Pendiente refund.
                 Separado visualmente con border-top sutil para diferenciar
                 inventario (arriba) de cálculos (abajo). --}}
            @php
                // EL LIBRO de la reserva (`DECISIONES #305`; T3·2): las líneas por producto y
                // complemento siguen diciendo QUÉ se compró (cantidad × unitario); el dinero movido
                // —movimientos, Total, pagos, saldo— lo compone `OrderBook` y lo pinta el partial
                // compartido `reservation-financials`, el mismo del bloque del pedido y del calendario.
                $book = \App\Domain\Booking\Services\OrderBook::forReservation($record, $item);
                $fmt = fn (int $cents) => \App\Domain\Platform\Services\Money::format($cents);
            @endphp

            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-white/10 text-sm">
                {{-- Sub-fase 7.2e.1bis5 (decisión #158, punto 4 feedback): título
                     explícito "Totales del producto" para diferenciar del bloque
                     "Totales del pedido" de la card Resumen. --}}
                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('admin.orders.item_financial.heading') }}
                </div>
                <div class="space-y-1">
                    @if ($visibleChildren->isNotEmpty())
                        {{-- Desglose por línea: principal + cada complemento como "cantidad ×
                             precio unitario" → importe cobrado, con su badge INCLUIDO/GRATIS y, si
                             solo parte va incluida, el aviso "(N incluida)". --}}
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-gray-600 dark:text-gray-400">{{ __('admin.orders.item_financial.principal') }} · {{ $item->quantity }} × {{ $fmt($item->unit_price) }}</span>
                            <span @class(['whitespace-nowrap text-gray-800 dark:text-gray-200', 'line-through' => $isItemCancelled])>{{ $fmt($item->chargedSubtotalCents()) }}</span>
                        </div>
                        @foreach ($visibleChildren as $child)
                            @php($cBadge = $child->addonBadgeKey())
                            @php($cNote = $child->partialFreeNote())
                            <div @class(['flex items-start justify-between gap-3', 'text-gray-500 line-through dark:text-gray-500' => $child->isCancelled()])>
                                <span class="text-gray-600 dark:text-gray-400">
                                    {{ $child->ticketType?->tr('name') }}@if ($cBadge) <span class="inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-700 no-underline dark:bg-emerald-400/10 dark:text-emerald-300">{{ __('tickets.addon_badge_'.$cBadge) }}</span>@endif
                                    · {{ $child->quantity }} × {{ $fmt($child->unit_price) }}@if ($cNote) <span class="text-xs text-gray-400 dark:text-gray-500">({{ $cNote }})</span>@endif
                                </span>
                                <span class="whitespace-nowrap text-gray-800 dark:text-gray-200">{{ $fmt($child->chargedSubtotalCents()) }}</span>
                            </div>
                        @endforeach
                    @endif

                    {{-- ⚠️ SOLO la forma con paréntesis en esta zona: el extractor de bloques PHP
                         de Blade casa desde el PRIMER uso con paréntesis de arriba hasta el primer
                         CIERRE de bloque que encuentre — introducir aquí un bloque con cierre dejó
                         media pantalla sin compilar, con el error señalando el final del fichero. --}}
                    @include('filament.orders.partials.reservation-financials', ['book' => $book, 'struck' => $isItemCancelled])

                    {{-- T5 adenda 4 (`[DECIDIDO owner]`, §25.10): el HISTORIAL a un clic desde el
                         desglose del producto — la foto no lista los cambios, y el «Pendiente de
                         devolución» de aquí arriba se explica en el historial. Va FUERA del partial
                         compartido a propósito: `reservation-financials` lo renderiza también el
                         modal del calendario, donde `viewOrderHistory` no existe. Solo cuando hay
                         CONSECUENCIAS que explicar, y lo decide el LIBRO (`hasHistoryToExplain()`:
                         una línea de valor que no sea el nacimiento, o una devolución) — el caso
                         simple no debe ganar ruido (el control lo fija). --}}
                    @if ($book->hasHistoryToExplain())
                        <button type="button" wire:click="mountAction('viewOrderHistory')"
                                class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                            {{ __('admin.orders.audit_cta.button') }}
                        </button>
                    @endif
                </div>
            </div>

            {{-- Línea de ACCIONES (#171/#172): Cancelar y Reembolsar viven dentro
                 del modal Gestionar; aquí quedan el calendario, la impresión de la
                 hoja de reserva y el botón Gestionar. --}}
            <div class="mt-3 flex items-center justify-end gap-3 flex-wrap">
                {{-- #179: icono de calendario → abre el calendario unificado en el
                     DÍA de esta reserva (vista de día). Visible si el item tiene
                     franja Y el usuario puede ver el calendario (gateado por
                     `calendar.view`, igual que `CalendarPage::canAccess`), junto al
                     botón de preparado. --}}
                @if ($item->slot && $authUser?->hasPermission('calendar.view'))
                    <x-filament::icon-button
                        tag="a"
                        :href="\App\Filament\Pages\CalendarPage::getUrl(['date' => $item->slot->date->format('Y-m-d')])"
                        icon="heroicon-o-calendar-days"
                        color="gray"
                        size="lg"
                        :label="__('admin.orders.btn_view_in_calendar')"
                    />
                @endif

                {{-- #183 + ④ (decisión clienta 2026-06-14): imprimir hoja de reserva (PDF A4) en una
                     pestaña nueva. DOS variantes en un desplegable: «Hoja de sala» (por defecto, SIN
                     precios — documento operativo) y «Con precios» (desglose económico completo,
                     `?precios=1`). Sin datos de cobro sensibles; la ruta revalida `orders.view` +
                     pertenencia al pedido. Dos enlaces reales `target="_blank"` (sin bloqueo de popups). --}}
                <x-filament::dropdown placement="bottom-end" teleport>
                    <x-slot name="trigger">
                        <x-filament::icon-button
                            type="button"
                            icon="heroicon-o-printer"
                            color="gray"
                            size="lg"
                            :label="__('admin.orders.slip.btn_print')"
                        />
                    </x-slot>
                    <x-filament::dropdown.list>
                        <x-filament::dropdown.list.item
                            tag="a"
                            :href="route('admin.orders.items.slip', [$record, $item])"
                            target="_blank"
                            icon="heroicon-o-document-text"
                        >
                            {{ __('admin.orders.slip.print_operational') }}
                        </x-filament::dropdown.list.item>
                        <x-filament::dropdown.list.item
                            tag="a"
                            :href="route('admin.orders.items.slip', [$record, $item]).'?precios=1'"
                            target="_blank"
                            icon="heroicon-o-banknotes"
                        >
                            {{ __('admin.orders.slip.print_with_prices') }}
                        </x-filament::dropdown.list.item>
                    </x-filament::dropdown.list>
                </x-filament::dropdown>

                {{-- #263: icono de ENLACE del formulario post-reserva, POR PRODUCTO. Solo en reservas
                     de cumpleaños con post-form (`isGuestFormReservation`) de un pedido PAGADO. Abre un
                     modal con el enlace firmado para copiarlo y enviarlo por WhatsApp/SMS (útil sobre
                     todo si el cliente no tiene email). El action revalida el gating (defensa). --}}
                @if ($record->status === \App\Domain\Booking\Models\Order::STATUS_PAID && $item->isGuestFormReservation())
                    <x-filament::icon-button
                        wire:click="mountAction('copyGuestFormLink', { item: {{ $item->id }} })"
                        icon="heroicon-o-link"
                        color="gray"
                        size="lg"
                        :label="__('admin.orders.copy_guest_form.btn_aria')"
                    />
                @endif

                {{-- Menores a cargo (tanda 5, D14·4/D14·6): «Asignar menores» — solo en ENTRADAS de un
                     titular con menores, para quien puede editar la línea y mientras la línea admite
                     cambios. El handler lo revalida TODO (defensa en profundidad, como Gestionar). --}}
                @if (! $isPack && $holderHasDependents && ($authUser?->hasPermission('orders.edit_item') ?? false) && $record->canEditItem($item))
                    <x-filament::icon-button
                        wire:click="mountAction('assignDependents', { item: {{ $item->id }} })"
                        icon="heroicon-o-users"
                        color="gray"
                        size="lg"
                        :label="__('admin.orders.dependents.btn_aria', ['name' => $ticketType?->tr('name') ?? '—'])"
                    />
                @endif

                {{-- P12: «Gestionar» pasa a ser un ICONO de lápiz (coherente con el resto de iconos de
                     la fila). Mismo `mountAction('manageItem')`; el aria-label conserva la semántica.
                     Visible siempre (incluso cancelados); decisión #159 (`viewItemDetail`→`manageItem`). --}}
                <x-filament::icon-button
                    wire:click="mountAction('manageItem', { item: {{ $item->id }} })"
                    icon="heroicon-o-pencil-square"
                    color="gray"
                    size="lg"
                    :label="__('admin.orders.item_detail.btn_aria', ['name' => $ticketType?->tr('name') ?? '—'])"
                />

                {{-- #173: los iconos de Cancelar y Reembolsar ya NO están en la
                     sub-card — ambos viven como botones al pie del modal Gestionar
                     (#171 reembolso, #172 cancelar). La línea de acciones queda con
                     Preparado + Gestionar. --}}
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.orders.no_items') }}</p>
    @endforelse
</div>
