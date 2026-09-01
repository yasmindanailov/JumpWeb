@php
    /**
     * @var \App\Domain\Booking\Models\Order $record
     *
     * «Totales del pedido» — EL LIBRO del pedido (`DECISIONES #305`; T3·2 de
     * `specs/desglose-libro.md` §6.3.2): cada gestión como una línea + o − con su fecha, un Total,
     * los pagos y devoluciones, lo Pagado y un SALDO con su clase. Lo compone
     * {@see \App\Domain\Booking\Services\OrderBook} y lo pinta el MISMO partial que la tarjeta de
     * cada reserva (`reservation-financials`): este blade decide solo lo que va ALREDEDOR —el aviso
     * de que el libro no cuadra y el atajo al historial—, no qué vale nada.
     *
     * Fue el bloque «valor-primero» de DOS EJES (`#196`/`#198` → `#127`): el mismo dinero contado
     * como canales ya sumados que el operador tenía que volver a relacionar (spec §1.2, medido
     * sobre pedidos reales). Sus claves `order_financial.*` se retiraron con él; quedan el título
     * y el aviso.
     *
     * Lectura sin N+1: eager-load defensivo de lo que el libro lee — las líneas con su franja y su
     * producto, los ajustes y los cobros con sus devoluciones.
     */
    use App\Domain\Booking\Services\OrderBook;

    $record->loadMissing([
        'payments.refunds',
        'adjustments',
        'items.ticketType',
        'items.slot',
    ]);

    $book = OrderBook::forOrder($record);
@endphp

<div class="rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ __('admin.orders.order_financial.heading') }}
    </div>

    {{-- ⚠️⚠️ **EL LIBRO NO CUADRA** (`DECISIONES #132`; D-T3·4). Al operador se le ENSEÑA —es quien
         puede arreglarlo— el libro entero y este aviso; al cliente se le ocultan las líneas de valor
         y el saldo y se le da una frase honesta: la asimetría es deliberada. --}}
    @unless ($book->isConsistent)
        <div class="mb-2 rounded-md bg-danger-50 p-2 text-xs leading-snug text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300"
             role="alert">
            <span class="font-semibold">{{ __('admin.orders.order_financial.no_cuadra_title') }}</span>
            {{ __('admin.orders.order_financial.no_cuadra_body') }}
        </div>
    @endunless

    {{-- El libro va PLEGADO con su propio CTA (`#318`). ⚠️ Aquí iba el atajo «Ver historial completo»
         (T5 adenda 4, `hasHistoryToExplain()`): el owner lo retiró en `#318` — el historial sigue a
         un clic en la tarjeta «Detalles». --}}
    @include('filament.orders.partials.reservation-financials', ['book' => $book])
</div>
