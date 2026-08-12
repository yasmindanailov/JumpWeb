<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\OrderItem;
use App\Support\DashboardPeriod;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fase 7.4 iter2 — tabla «Reservas» del dashboard (decisión #14).
 *
 * Lista los productos principales (entradas + cumpleaños, pedidos pagados y
 * activos) cuya franja cae en el PERIODO seleccionado (hoy / esta semana / este
 * mes; del filtro compartido `pageFilters`, default HOY), ordenados por fecha+hora.
 * La columna de estado distingue preparado/sin preparar/finalizado y la fila
 * enlaza a la vista del pedido. Reutiliza la misma regla de "qué es una reserva"
 * que el calendario (`paidScheduledPrincipal`). Gateado por `calendar.view`.
 */
class ReservationsWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('calendar.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->reservationsQuery())
            ->heading(__('admin.dashboard.reservations.heading'))
            ->emptyStateHeading(__('admin.dashboard.reservations.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCalendar)
            ->columns([
                TextColumn::make('slot_when')
                    ->label(__('admin.dashboard.reservations.col_time'))
                    ->getStateUsing(fn (OrderItem $record): string => $this->whenLabel($record)),

                // P8 (refinamiento clienta 2026-06-13): el icono del tipo de producto (entrada → ticket,
                // cumpleaños → tarta) se TIÑE con el color de la zona y sustituye al antiguo ColorColumn
                // (cuadro de color en columna propia) → ahorra una columna. Se usa una ViewColumn con vista
                // propia porque `TextColumn->html()` pasa por el saneador de Filament, que elimina el `<svg>`
                // (no es «elemento seguro» de Symfony) → el icono desaparecía. La vista pinta el icono nativo
                // + tinte exacto por `currentColor`; el nombre de la zona va como tooltip (lo daba el ColorColumn).
                ViewColumn::make('product')
                    ->label(__('admin.dashboard.reservations.col_product'))
                    ->view('filament.widgets.reservation-product'),

                TextColumn::make('order.user.name')
                    ->label(__('admin.dashboard.reservations.col_customer'))
                    ->default('—'),

                TextColumn::make('quantity')
                    ->label(__('admin.dashboard.reservations.col_quantity'))
                    ->formatStateUsing(fn (OrderItem $record): string => $this->quantityLabel($record)),

                TextColumn::make('status')
                    ->label(__('admin.dashboard.reservations.col_status'))
                    ->badge()
                    ->getStateUsing(fn (OrderItem $record): string => $record->displayStatusForCustomer())
                    ->formatStateUsing(fn (string $state): string => __('admin.dashboard.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        OrderItem::STATUS_FINISHED => 'gray',
                        default => 'primary',
                    }),

                // Estado del formulario de reserva por-niño (#217), como en el calendario: ✓ verde =
                // enviado · ! ámbar = pendiente. Solo los packs que piden el post-form tienen estado
                // (`guestFormStatus()` → null en entradas / packs sin formulario → celda «—»). La
                // ticketType va eager-loaded en la query → sin N+1.
                TextColumn::make('guest_form')
                    ->label(__('admin.dashboard.reservations.col_form'))
                    ->badge()
                    ->placeholder('—')
                    ->getStateUsing(fn (OrderItem $record): ?string => $record->guestFormStatus())
                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                        OrderItem::GUEST_FORM_STATUS_OK => '✓ '.__('admin.dashboard.form_status.ok'),
                        OrderItem::GUEST_FORM_STATUS_PENDING => '! '.__('admin.dashboard.form_status.pending'),
                        default => null,
                    })
                    ->color(fn (?string $state): ?string => match ($state) {
                        OrderItem::GUEST_FORM_STATUS_OK => 'success',
                        OrderItem::GUEST_FORM_STATUS_PENDING => 'warning',
                        default => null,
                    }),
            ])
            ->recordUrl(fn (OrderItem $record): ?string => $record->order
                ? OrderResource::getUrl('view', ['record' => $record->order])
                : null);
    }

    /**
     * Items principales-pagados-activos cuya franja cae en el periodo. Join a
     * `slots` para ordenar por fecha+hora de la franja de forma portable
     * (`start_time` es 'HH:MM:SS' zero-padded → orden lexicográfico = cronológico).
     */
    protected function reservationsQuery(): Builder
    {
        $period = DashboardPeriod::fromValue($this->pageFilters['period'] ?? null);
        [$from, $to] = $period->range();

        return OrderItem::query()
            ->paidScheduledPrincipal()
            ->join('slots', 'slots.id', '=', 'order_items.slot_id')
            ->select('order_items.*')
            ->whereBetween('slots.date', [$from, $to])
            ->with(['slot', 'ticketType.zone', 'order:id,code,user_id', 'order.user:id,name'])
            ->orderBy('slots.date')
            ->orderBy('slots.start_time');
    }

    /** "ddd D MMM · HH:MM" en hora de pared del parque (date/start_time no son UTC). */
    protected function whenLabel(OrderItem $item): string
    {
        $slot = $item->slot;
        if ($slot === null) {
            return '—';
        }

        $date = $slot->date instanceof Carbon ? $slot->date : Carbon::parse((string) $slot->date);

        return $date->isoFormat('ddd D MMM').' · '.substr((string) $slot->start_time, 0, 5);
    }

    /** Packs → "N invitados"; entradas → cantidad. */
    protected function quantityLabel(OrderItem $item): string
    {
        if ($item->ticketType?->isPack()) {
            return __('tickets.guests_count', ['count' => $item->quantity]);
        }

        return (string) $item->quantity;
    }
}
