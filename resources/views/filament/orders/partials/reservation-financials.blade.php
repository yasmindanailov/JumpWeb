@php
    /**
     * EL LIBRO, pintado (`DECISIONES #305`; T3·2 de `specs/desglose-libro.md` §6.3.2).
     *
     * Un solo pintor para las tres superficies del panel: el bloque «Totales del pedido» (el libro
     * del PEDIDO), la sub-tarjeta de cada reserva y el modal del calendario (el libro de la
     * RESERVA). Recibe un {@see \App\Domain\Booking\Services\OrderBook} ya resuelto y lo TRANSCRIBE:
     * movimientos (cada uno con su signo y su fecha) → Total → pagos y devoluciones → Pagado → el
     * saldo con su clase. **No compone ni decide un importe** (D1: las nueve superficies enseñan el
     * MISMO libro; `LedgerSingleSourceTest`). Las etiquetas de cada línea las trae el libro
     * (`tickets.journal.*`, las mismas que lee el cliente); aquí viven solo los títulos y los
     * rótulos del saldo (`admin.orders.book.*`).
     *
     * ~~D-T3·1: el libro se enseña ENTERO en cuanto el bloque está a la vista, sin un segundo «ver
     * más»~~ → **REVERTIDA por el owner (`DECISIONES #318`, 2026-09-01)**: el libro va PLEGADO — de
     * un vistazo Total · Pagado · saldo — y UN CTA («Ver el desglose» / «Cerrar el desglose») abre el
     * detalle entero, tal como se pintaba antes. Con él se retiró el atajo «Ver historial completo»
     * que iba bajo el libro (T5 adenda 4). D-T3·2: cada línea con su signo; el Total y lo Pagado, sin
     * signo. D-T3·3: con «saldado» no hay importe.
     *
     * El pliegue es Alpine y NO oculta nada al servidor: el HTML lleva SIEMPRE todas las líneas (las
     * guardas de paridad leen el marcado; sin JS se ve todo). ⚠️ `x-data` va ANTES de `data-book`:
     * `BookSurfacesParityTest` cuenta el literal `data-book>`.
     *
     * Fue el value object de cinco cubos (`#196` → `#127`): el mismo dinero contado como canales
     * que había que volver a relacionar.
     *
     * @var \App\Domain\Booking\Services\OrderBook $book
     * @var bool $struck  tachar el Total (reserva cancelada)
     */
    use App\Domain\Booking\Services\Balance;
    use App\Domain\Booking\Services\Settlement;
    use App\Domain\Platform\Services\Money;

    $struck = $struck ?? false;
    $fmt = fn (int $cents): string => Money::format($cents, $book->currency);
    $signed = fn (int $cents): string => ($cents < 0 ? '−' : '+').Money::format(abs($cents), $book->currency);
    $kind = $book->balance->kind;
    // El color es por ROL (D-T3·12): lo que se paga, en el naranja de «falta por cobrar»; lo que se
    // devuelve, en el ámbar de «devuelto»; un libro que no cuadra, en peligro.
    $balanceClass = match ($kind) {
        Balance::KIND_PAY_AT_PARK, Balance::KIND_PAY_ONLINE => 'text-orange-600 dark:text-orange-400',
        Balance::KIND_REFUND_AT_PARK, Balance::KIND_REFUND_PENDING => 'text-amber-700 dark:text-amber-300',
        Balance::KIND_UNDER_REVIEW => 'text-danger-700 dark:text-danger-300',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="space-y-1 text-xs" x-data="{ open: false }" data-book>
    {{-- EL DETALLE, plegado (`#318`): las líneas de VALOR. Siempre en el HTML; Alpine lo abre. --}}
    <div class="space-y-1" x-show="open" x-cloak data-book-detail="movements">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.orders.book.movements') }}</div>
        @foreach ($book->movements as $m)
            <div class="flex items-start justify-between gap-3 pl-3" data-book-movement="{{ $m->kind }}">
                <span class="min-w-0 text-gray-700 dark:text-gray-300">{{ $m->label }} <span class="whitespace-nowrap text-gray-400 dark:text-gray-500">· {{ $m->occurredLabel }}</span></span>
                <span @class(['whitespace-nowrap', 'text-amber-700 dark:text-amber-300' => $m->amountCents < 0, 'text-gray-800 dark:text-gray-200' => $m->amountCents >= 0])>{{ $signed($m->amountCents) }}</span>
            </div>
            {{-- El MOTIVO de un descuento por cortesía (T4 del libro, D-T4·1 `[DECIDIDO owner]`): INTERNO.
                 Solo lo pinta el panel; no viaja por la API ni llega al cajón ni a los correos. Va FUERA de
                 la línea (`data-book-movement`) para que la etiqueta que lee el cliente siga siendo la misma. --}}
            @if ($m->note !== null)
                <div class="pl-6 text-[11px] italic text-gray-500 dark:text-gray-400" data-book-note>{{ __('admin.orders.book.movement_note', ['note' => $m->note]) }}</div>
            @endif
        @endforeach
    </div>
    {{-- DE UN VISTAZO (siempre visible): Total · Pagado · saldo. El filete superior solo separa del detalle abierto. --}}
    <div class="flex items-center justify-between gap-3 text-sm font-semibold" x-bind:class="open ? 'border-t border-gray-200 pt-1 dark:border-white/10' : ''" data-book-total>
        <span @class(['text-gray-800 dark:text-gray-200', 'line-through' => $struck])>{{ __('admin.orders.book.total') }}</span>
        <span @class(['text-gray-900 dark:text-gray-100', 'line-through' => $struck])>{{ $fmt($book->totalCents) }}</span>
    </div>

    @if ($book->settlements !== [])
        {{-- EL DETALLE, plegado: las líneas de DINERO. --}}
        <div class="space-y-1" x-show="open" x-cloak data-book-detail="settlements">
            <div class="pt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.orders.book.settlements') }}</div>
            @foreach ($book->settlements as $s)
                @php($effective = $s->status === Settlement::STATUS_SUCCEEDED)
                {{-- Una devolución EN CURSO o FALLIDA se lista (su etiqueta ya lo dice: la compone el libro)
                     atenuada, y no cuenta como pagado (spec §4.4): el saldo sigue diciendo «a devolver»
                     mientras el dinero no ha vuelto. --}}
                <div @class(['flex items-start justify-between gap-3 pl-3', 'opacity-70' => ! $effective]) data-book-settlement="{{ $s->kind }}" data-book-settlement-status="{{ $s->status }}">
                    <span class="min-w-0 text-gray-700 dark:text-gray-300">{{ $s->label }} <span class="whitespace-nowrap text-gray-400 dark:text-gray-500">· {{ $s->occurredLabel }}</span></span>
                    <span @class(['whitespace-nowrap', 'text-amber-700 dark:text-amber-300' => $s->amountCents < 0, 'text-gray-800 dark:text-gray-200' => $s->amountCents >= 0])>{{ $signed($s->amountCents) }}</span>
                </div>
            @endforeach
        </div>
    @endif
    <div class="flex items-center justify-between gap-3 text-sm font-semibold" x-bind:class="open ? 'border-t border-gray-200 pt-1 dark:border-white/10' : ''" data-book-paid>
        <span class="text-gray-800 dark:text-gray-200">{{ __('admin.orders.book.paid') }}</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $fmt($book->paidCents) }}</span>
    </div>

    {{-- El SALDO, por clase (spec §4.4): la clase la decide el libro y el rótulo la nombra; el signo
         va con la clase (a devolver, en negativo). Con «saldado» no hay importe (D-T3·3). --}}
    <div class="flex items-center justify-between gap-3 text-sm font-semibold {{ $balanceClass }}" data-book-balance="{{ $kind }}">
        <span>{{ __('admin.orders.book.balance_'.$kind) }}</span>
        @if ($book->balance->cents !== 0)
            <span class="whitespace-nowrap">{{ $book->balance->cents < 0 ? '−' : '' }}{{ $fmt(abs($book->balance->cents)) }}</span>
        @endif
    </div>
    @if ($kind === Balance::KIND_PAY_ONLINE && $book->balance->restAtParkCents > 0)
        <p class="pl-3 text-[11px] text-gray-500 dark:text-gray-400">{{ __('admin.orders.book.balance_rest_at_park', ['amount' => $fmt($book->balance->restAtParkCents)]) }}</p>
    @endif

    {{-- UN CTA (`#318`): abre y cierra el detalle. Sustituye al atajo «Ver historial completo» que iba
         aquí (T5 adenda 4); la puerta al historial sigue en la tarjeta «Detalles» del pedido.

         ⚠️ **`min-h-11` = 44 px, el mínimo táctil** (`#466`): medía **86×16** —es el hallazgo M7 de
         `auditoria-panel-admin.md`— y el panel se usa con el dedo. El alto lo pone la DIANA, no el
         texto: el rótulo sigue en `text-xs`, así que ninguna de las cuatro superficies que pintan el
         libro cambia de aspecto más allá de este control. --}}
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'"
            class="mt-1.5 inline-flex min-h-11 items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
            data-book-toggle>
        <span x-show="! open">{{ __('admin.orders.book.expand') }}</span>
        <span x-show="open" x-cloak>{{ __('admin.orders.book.collapse') }}</span>
    </button>
</div>
