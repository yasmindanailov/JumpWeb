<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Tables\AuditLogTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visibilidad de incidencias (recomendación C, 2026-06-15) — página «Incidencias» del panel.
 *
 * Lector SOLO LECTURA de `audit_logs`, que ya existía como tabla append-only escrita por
 * `AuditLogger` pero NO tenía ninguna superficie global donde verla: las incidencias de dinero
 * (cobro duplicado/huérfano, cobro tras caducar, fallos de reembolso) quedaban «perdidas» en
 * `laravel.log`. Por defecto filtra a `AuditLog::CRITICAL_ACTIONS` (dinero + seguridad/RGPD); el
 * operador puede desmarcar el filtro para ver todo el registro de auditoría.
 *
 * **Inmutable:** sin crear/editar/borrar (refuerza la naturaleza append-only de la tabla).
 * **Acceso:** permiso `audit.view` (ya en `PermissionCatalog`, grupo «sistema»; el admin lo trae
 * vía `Gate::before`). Vive en el grupo de navegación «Sistema».
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $slug = 'incidencias';

    // Dentro del grupo «Sistema», tras Ajustes (10), antes/junto a Roles (40).
    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.audit.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.audit.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.audit.model_label_plural');
    }

    /**
     * Eager-load del autor y del `target` polimórfico: evita N+1 al pintar las columnas
     * «usuario» y «asociado a», y al construir el enlace al pedido (por `code`, no `id`).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'target']);
    }

    /**
     * Distintivo de navegación: nº de incidencias críticas de los últimos 7 días, en rojo. Señal
     * recientista (no hay estado «resuelto» en este alcance), defensiva ante BD aún sin migrar.
     */
    public static function getNavigationBadge(): ?string
    {
        return rescue(function (): ?string {
            $count = AuditLog::query()
                ->whereIn('action', AuditLog::CRITICAL_ACTIONS)
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            return $count > 0 ? (string) $count : null;
        }, null, false);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return AuditLogTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }

    // ─── Autorización: `audit.view` (admin vía Gate::before). Inmutable. ───────────

    public static function canViewAny(): bool
    {
        return self::canViewAudit();
    }

    public static function canView($record): bool
    {
        return self::canViewAudit();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canViewAudit();
    }

    public static function canCreate(): bool
    {
        return false; // audit_logs es append-only: solo lo escribe AuditLogger.
    }

    public static function canEdit($record): bool
    {
        return false; // registros inmutables.
    }

    public static function canDelete($record): bool
    {
        return false; // registros inmutables.
    }

    private static function canViewAudit(): bool
    {
        return auth()->user()?->hasPermission('audit.view') ?? false;
    }
}
