<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;

/**
 * Tabla de «Incidencias» (recomendación C, 2026-06-15): el registro de auditoría, con las
 * acciones críticas resaltadas en rojo. Por defecto solo muestra `CRITICAL_ACTIONS`; el operador
 * puede desmarcar el filtro para ver todo el registro. Cada incidencia ligada a un pedido enlaza
 * a su ficha. Tabla read-only (sin acciones de fila/masivas).
 */
class AuditLogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.audit.col_when'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('action')
                    ->label(__('admin.audit.col_action'))
                    ->badge()
                    ->color(fn (string $state): string => in_array($state, AuditLog::CRITICAL_ACTIONS, true) ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state): string => self::actionLabel($state)),

                TextColumn::make('user.name')
                    ->label(__('admin.audit.col_actor'))
                    ->default(__('admin.audit.actor_system')),

                TextColumn::make('target')
                    ->label(__('admin.audit.col_target'))
                    ->getStateUsing(fn (AuditLog $record): string => self::targetLabel($record)),

                TextColumn::make('payload')
                    ->label(__('admin.audit.col_detail'))
                    ->getStateUsing(fn (AuditLog $record): string => self::payloadSummary($record))
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('ip')
                    ->label(__('admin.audit.col_ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('solo_criticas')
                    ->label(__('admin.audit.filter_critical_only'))
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereIn('action', AuditLog::CRITICAL_ACTIONS)),
            ])
            // Enlace al pedido cuando la incidencia es sobre un Order; si no, fila no clicable.
            ->recordUrl(fn (AuditLog $record): ?string => self::recordUrl($record))
            ->recordActions([])
            ->toolbarActions([]);
    }

    /** Etiqueta i18n de la acción si existe; si no, el string crudo (registro de auditoría amplio). */
    private static function actionLabel(string $action): string
    {
        $key = 'admin.audit.actions.'.str_replace('.', '_', $action);

        return Lang::has($key) ? __($key) : $action;
    }

    private static function targetLabel(AuditLog $record): string
    {
        if ($record->target_type === null) {
            return '—';
        }

        // Pedido: mostrar el código público (JJ-XXXX), coherente con el resto del panel. El
        // `target` va eager-loaded; si el registro fue borrado, cae al identificador técnico.
        $target = $record->target;
        if ($target instanceof Order) {
            return __('admin.audit.target_order', ['code' => $target->code]);
        }

        return class_basename($record->target_type).' #'.$record->target_id;
    }

    /** Resumen inline del payload (sin PII por diseño). Robusto ante valores no escalares. */
    private static function payloadSummary(AuditLog $record): string
    {
        $payload = $record->payload;
        if (! is_array($payload) || $payload === []) {
            return '—';
        }

        return collect($payload)
            ->map(fn ($value, $key): string => $key.': '.(is_scalar($value) || $value === null
                ? (string) ($value ?? '—')
                : json_encode($value, JSON_UNESCAPED_UNICODE)))
            ->implode(' · ');
    }

    private static function recordUrl(AuditLog $record): ?string
    {
        // Pasamos el MODELO (eager-loaded), no el id: `OrderResource::getUrl` resuelve la URL por
        // `Order::getRouteKey()` = `code` (#130). Con el id entero saldría `/admin/orders/{id}` y
        // el binding por `code` daría 404. Null si el target no es un pedido o fue borrado.
        $target = $record->target;

        return $target instanceof Order
            ? OrderResource::getUrl('view', ['record' => $target])
            : null;
    }
}
