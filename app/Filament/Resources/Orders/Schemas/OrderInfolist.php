<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Domain\Booking\Models\Order;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Users\UserResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Infolist del detalle de Order (sub-fase 7.2a refinada, decisiones #130–#137).
 *
 * Layout responsive con **Filament Flex** (flexbox real, NO CSS Grid):
 *
 *   Desktop (lg+, `Flex::from('lg')` = `flex-row items-start`):
 *   ┌──── 1/2 izq ────┐ ┌──── 1/2 der ────┐
 *   │ Resumen ✓      │ │                  │
 *   ├────────────────┤ │                  │
 *   │ Detalles  ▾   │ │   Productos      │
 *   ├────────────────┤ │                  │
 *   │ Pagos  ▾      │ │                  │
 *   └────────────────┘ └──────────────────┘
 *
 *   Mobile (1 col, `Flex` = `flex-col`):
 *   Orden visual: Resumen → Productos → Detalles → Pagos (vía CSS `order`).
 *
 * ⚠️ **Por qué Flex y NO CSS Grid (decisión #137)**: la versión #135–#136 usaba
 * CSS Grid con `grid-row: 1 / -1` para hacer que Productos abarcara todas las filas
 * de col 2. CSS Grid distribuye la altura de un item span entre las filas que
 * abarca → row 1 (Resumen) crecía a `items_height / 3`, dejando un gap visual
 * gigantesco entre Resumen y Detalles cuando Productos era alta. **Flexbox NO
 * tiene este problema**: cada columna en un `flex-row items-start` tiene su
 * propia altura, independiente de la otra. La columna izquierda se dimensiona
 * a su contenido natural; la columna derecha (Productos) crece según los items.
 *
 * **Mobile reorder**: en móvil queremos Resumen → Productos → Detalles → Pagos.
 * El Group de la izquierda contiene [Resumen, Detalles, Pagos], Productos es
 * sibling. Para meter Productos ENTRE Resumen y Detalles necesitamos que en
 * móvil el Group "se abra" — sus children pasen a ser flex children directos
 * del Flex parent. `display: contents` consigue exactamente eso, y CSS `order`
 * reordena por data-attribute. CSS detalle en `theme.css`.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    Group::make([
                        self::summarySection()->extraAttributes(['data-mobile-order' => '1']),
                        self::detailsSection()->extraAttributes(['data-mobile-order' => '3']),
                        self::paymentsSection()->extraAttributes(['data-mobile-order' => '4']),
                    ])->extraAttributes(['data-stack' => 'left']),

                    self::itemsSection()->extraAttributes(['data-mobile-order' => '2']),
                ])
                    ->from('lg')
                    // ViewRecord aplica `columns(2)` al schema por defecto (#131);
                    // sin `columnSpan('full')` el Flex se renderiza como span 1 de 2
                    // = 50% de ancho, dejando media página vacía. `'full'` lo extiende
                    // al ancho completo del wrapper.
                    ->columnSpan('full')
                    ->extraAttributes(['data-layout' => 'order-detail']),
            ]);
    }

    /**
     * "Resumen" — siempre abierta. Datos clave que el operativo necesita de un vistazo:
     * contacto del cliente (nombre + teléfono inline, P2) + bloque financiero. El estado del
     * pedido y el OPERATIVO viven JUNTOS en la H1 del detalle (P3/#132), no en esta card.
     */
    private static function summarySection(): Section
    {
        return Section::make(__('admin.orders.section_summary'))
            ->columns(1)
            ->schema([
                // P2: nombre + teléfono en UNA fila (inline). Nombre clicable → su ficha de usuario
                // (#182) solo si el operador puede verla (`users.manage`, admin); si no, texto plano.
                Grid::make(2)->schema([
                    TextEntry::make('user.name')
                        ->label(__('admin.orders.customer_name'))
                        ->placeholder('—')
                        ->url(fn (Order $record): ?string => (auth()->user()?->hasPermission('users.manage') ?? false) && $record->user
                            ? UserResource::getUrl('view', ['record' => $record->user])
                            : null)
                        ->color(fn (Order $record): ?string => (auth()->user()?->hasPermission('users.manage') ?? false) && $record->user
                            ? 'primary'
                            : null),

                    TextEntry::make('user.phone')
                        ->label(__('admin.orders.customer_phone'))
                        ->copyable()
                        ->placeholder('—'),
                ]),

                // Bloque financiero compacto (P2: agregados visibles + «ver más»; P4: cómo se pagó).
                View::make('filament.orders.partials.order-totals'),
            ]);
    }

    /**
     * "Detalles" — collapsed por defecto. Sub-secciones con Fieldset para separar
     * datos del pedido y del cliente.
     */
    private static function detailsSection(): Section
    {
        return Section::make(__('admin.orders.section_details'))
            ->collapsible()
            ->collapsed()
            ->columns(1)
            ->schema([
                Fieldset::make(__('admin.orders.details_order'))
                    ->columns(1)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('admin.orders.col_created_at'))
                            ->getStateUsing(fn (Order $record) => DisplayTime::format($record->created_at)),

                        TextEntry::make('expires_at')
                            ->label(__('admin.orders.col_expires_at'))
                            ->getStateUsing(fn (Order $record) => $record->expires_at ? DisplayTime::format($record->expires_at) : '—')
                            ->visible(fn (Order $record) => $record->displayStatus() === Order::STATUS_PENDING
                                && $record->expires_at !== null),

                        TextEntry::make('paid_at')
                            ->label(__('admin.orders.col_paid_at'))
                            ->getStateUsing(fn (Order $record) => $record->paid_at ? DisplayTime::format($record->paid_at) : '—')
                            ->visible(fn (Order $record) => $record->paid_at !== null),
                    ]),

                Fieldset::make(__('admin.orders.details_customer'))
                    ->columns(1)
                    ->schema([
                        TextEntry::make('user.email')
                            ->label(__('admin.orders.customer_email'))
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('user.locale')
                            ->label(__('admin.orders.customer_locale'))
                            ->getStateUsing(fn (Order $record) => $record->user?->locale
                                ? __('admin.orders.customer_locale_value.'.$record->user->locale)
                                : '—'),

                        TextEntry::make('user.waiver_accepted_at')
                            ->label(__('admin.orders.customer_waiver'))
                            ->getStateUsing(fn (Order $record) => $record->user?->waiver_accepted_at
                                ? DisplayTime::format($record->user->waiver_accepted_at)
                                : __('admin.orders.customer_waiver_missing'))
                            ->badge()
                            ->color(fn (Order $record) => $record->user?->waiver_accepted_at ? 'success' : 'warning'),
                    ]),

                // CTA al final de la card "Detalles" (sub-fase 7.2d, decisión
                // #151): descripción corta + botón "Ver historial completo"
                // que monta la Filament Action `viewOrderHistory` del
                // ViewOrder. El audit log agregado del Order (Order + Items)
                // se renderiza en un modal con paginación incremental.
                View::make('filament.orders.partials.order-audit-cta'),
            ]);
    }

    /**
     * "Pagos" — collapsed por defecto. Info técnica Redsys que solo se consulta
     * al reconciliar incidencias.
     */
    private static function paymentsSection(): Section
    {
        return Section::make(__('admin.orders.section_payments'))
            ->collapsible()
            ->collapsed()
            ->schema([
                View::make('filament.orders.payments-list'),
            ]);
    }

    private static function itemsSection(): Section
    {
        return Section::make(__('admin.orders.section_items'))
            ->schema([
                View::make('filament.orders.items-list'),
            ]);
    }
}
