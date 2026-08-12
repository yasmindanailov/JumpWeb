<?php

namespace App\Filament\Widgets;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Enums\DashboardPeriod;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Fase 7.4 iter2 — contadores operativos del dashboard (decisión #14).
 *
 *  - «Reservas»: nº de reservas (productos principales pagados y no cancelados)
 *    cuya franja cae en el periodo. `COUNT` en BD.
 *  - «Ocupación»: suma de plazas (`seats`) reservadas en el periodo (todas las
 *    zonas), vía `SUM` en BD (barato a cualquier tamaño de periodo).
 *
 * El PERIODO (hoy / esta semana / este mes) llega del filtro compartido de la
 * página `Dashboard` (`pageFilters`); por defecto HOY. Gateado por `calendar.view`.
 */
class DashboardStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('calendar.view') ?? false;
    }

    protected function getStats(): array
    {
        $period = DashboardPeriod::fromValue($this->pageFilters['period'] ?? null);
        [$from, $to] = $period->range();

        // Ocupación: suma de plazas en TODO el periodo (incluye lo ya pasado del
        // periodo: "cuán lleno está / estuvo"). SUM en BD, sin cargar filas.
        $occupancy = (int) OrderItem::query()
            ->paidScheduledPrincipal()
            ->slotDateBetween($from, $to)
            ->sum('seats');

        // Reservas: nº de productos principales pagados y no cancelados cuya
        // franja cae en el periodo. COUNT en BD, sin cargar filas.
        $reservations = (int) OrderItem::query()
            ->paidScheduledPrincipal()
            ->slotDateBetween($from, $to)
            ->count();

        return [
            Stat::make(__('admin.dashboard.stats.reservations'), $reservations)
                ->description($period->label())
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('primary'),

            Stat::make(
                __('admin.dashboard.stats.occupancy'),
                __('admin.dashboard.stats.occupancy_value', ['count' => $occupancy]),
            )
                ->description($period->label())
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('primary'),
        ];
    }
}
