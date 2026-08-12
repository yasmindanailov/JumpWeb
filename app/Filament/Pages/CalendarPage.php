<?php

namespace App\Filament\Pages;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\ReservationFinancials;
use App\Filament\Concerns\PrintsDaySummary;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Support\Icons\Heroicon;

/**
 * Fase 7.4 — Calendario unificado del panel (centro de mando, decisión #14).
 *
 * Vista mes/semana/día (FullCalendar) de los PRODUCTOS vendidos (`order_items`
 * principales de pedidos pagados), coloreados por zona y con el estado operativo
 * (activo/finalizado) superpuesto. El grid se monta cliente-side
 * (componente Alpine `jjCalendar`, `resources/js/admin/calendar.js`) y se
 * alimenta del feed JSON `admin.calendario.eventos`.
 *
 * Al pulsar un evento se abre esta Action read-only `viewCalendarItem`, que
 * resuelve el item y muestra su detalle + la referencia del pedido (con enlace
 * al `OrderResource`). El día NO es clicable (decisión de sesión 7.4).
 *
 * Autorización: permiso `calendar.view` (staff por defecto) + el middleware del
 * panel. `Page` ya trae `InteractsWithActions`, así que `mountAction(
 * 'viewCalendarItem', …)` resuelve `viewCalendarItemAction()`.
 */
class CalendarPage extends Page
{
    use PrintsDaySummary;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 10;   // Plan B · L1: primero en «Operativa»

    protected static ?string $slug = 'calendario';

    protected string $view = 'filament.pages.calendar';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('calendar.view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('calendar.view') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.calendar.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.calendar.nav_group');
    }

    public function getTitle(): string
    {
        return __('admin.calendar.title');
    }

    /**
     * Botón "Imprimir resumen del día" en la cabecera (decisión #184). La acción
     * vive en el trait `PrintsDaySummary` (compartida con el Escritorio).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->printDaySummaryAction()];
    }

    /**
     * Action read-only invocada desde el click en un evento del calendario.
     * Muestra el detalle del producto + la referencia de su pedido. Sin submit:
     * es un visor (la edición vive en el modal "Gestionar" del pedido).
     *
     * El detalle se renderiza como un componente `View` DENTRO del schema (patrón
     * de 7.2c) y NO con `modalContent`: en una `Page`, el modal de la action lee
     * la propiedad Livewire `mountedActionSchema0`, que solo se registra si la
     * action tiene schema con componentes. Un schema vacío (modalContent suelto)
     * rompía el render del modal en la Page.
     */
    public function viewCalendarItemAction(): Action
    {
        return Action::make('viewCalendarItem')
            ->modalHeading(__('admin.calendar.item_modal.heading'))
            ->modalSubmitAction(false)
            // El botón «Cerrar» del footer se retira (decisión clienta 2026-06-13): la «X» de la
            // cabecera del modal ya cierra. `modalCancelAction(false)` oculta la acción de cancelar.
            ->modalCancelAction(false)
            ->schema(fn (array $arguments): array => [
                ViewComponent::make('filament.admin.calendar.item-detail')
                    ->viewData(fn (): array => $this->calendarItemViewData($arguments)),
            ]);
    }

    /**
     * Datos para la vista del modal: el item + URL del pedido + totales del
     * producto (principal + complementos).
     *
     * @return array<string, mixed>
     */
    protected function calendarItemViewData(array $arguments): array
    {
        $item = $this->resolveCalendarItem($arguments);

        if ($item === null) {
            return ['item' => null, 'orderUrl' => null, 'userUrl' => null, 'slipUrl' => null,
                'principalCents' => 0, 'addonsCents' => 0, 'totalCents' => 0, 'rf' => null];
        }

        $children = $item->children->filter(fn (OrderItem $c) => ! $c->isCancelled());
        $principalCents = $item->chargedSubtotalCents();
        $addonsCents = $children->sum(fn (OrderItem $c) => $c->chargedSubtotalCents());
        // Robustez del desglose (#196): fuente ÚNICA del "Totales del producto"
        // (pagado online / a cobrar en puerta / devuelto / pendiente) — mismas
        // cifras que la sub-card del pedido y el PDF.
        $rf = $item->order ? ReservationFinancials::make($item->order, $item) : null;

        return [
            'item' => $item,
            'children' => $children,
            'orderUrl' => $item->order
                ? OrderResource::getUrl('view', ['record' => $item->order])
                : null,
            // Nombre del cliente clicable → su ficha (#182), solo si el operador puede
            // verla (`users.manage`, admin). Sin permiso → texto plano en el partial.
            'userUrl' => $item->order?->user && (auth()->user()?->hasPermission('users.manage') ?? false)
                ? UserResource::getUrl('view', ['record' => $item->order->user])
                : null,
            // Imprimir ESTA reserva (hoja individual #183) en pestaña nueva. Solo si hay pedido y el
            // operador tiene `orders.view` (lo que exige esa ruta). El modal ofrece DOS variantes
            // (#235/④, sin/con precios): la del desglose se deriva en el blade añadiendo `?precios=1`.
            'slipUrl' => $item->order && (auth()->user()?->hasPermission('orders.view') ?? false)
                ? route('admin.orders.items.slip', ['order' => $item->order, 'item' => $item])
                : null,
            'principalCents' => $principalCents,
            'addonsCents' => $addonsCents,
            'totalCents' => $principalCents + $addonsCents,
            'rf' => $rf,
        ];
    }

    /**
     * Resuelve el OrderItem del evento pulsado con sus relaciones de display.
     * El argumento llega como `{ item: id }` desde `mountAction`.
     */
    protected function resolveCalendarItem(array $arguments): ?OrderItem
    {
        $id = (int) ($arguments['item'] ?? $this->mountedActions[0]['arguments']['item'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        // #F9 (defensivo): el modal solo debe resolver items que SON eventos de
        // agenda — principal + con franja + pedido pagado + no cancelado: la MISMA
        // regla que emite el feed (CalendarEventsController). Sin el scope, un id
        // forjado por Livewire (mountAction) de un item cancelado se renderizaría
        // a precio completo. Conserva los eager-loads de presentación + los de
        // ReservationFinancials (#196).
        return OrderItem::query()
            ->paidScheduledPrincipal()
            ->with([
                'ticketType.zone', 'slot', 'order.user', 'children.ticketType',
                // Para ReservationFinancials del modal (desglose detallado #196) y el desglose
                // ↳ de puerta (#225 F2): `Order::reservationGateLines` resuelve item+ticketType
                // de cada cargo de edición vía `Order::items`, y el estado «finalizado» del
                // principal vía `items.slot` → eager-load ambos para evitar N+1.
                'order.adjustments', 'order.payments.refunds', 'order.items.ticketType', 'order.items.slot',
            ])->find($id);
    }
}
