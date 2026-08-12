<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Domain\Identity\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Fase 7.11 — Tabla de roles: etiqueta legible (badge con color por rol), identificador
 * técnico y nº de permisos. Fila clicable → edición de la matriz. Sin acciones de
 * crear/borrar (roles base inmutables).
 */
class RoleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label(__('admin.access.col_role'))
                    ->badge()
                    ->getStateUsing(fn (Role $record): string => __('admin.users.roles.'.$record->name))
                    ->color(fn (Role $record): string => match ($record->name) {
                        'admin' => 'danger',
                        'staff' => 'warning',
                        default => 'primary',
                    }),

                TextColumn::make('name')
                    ->label(__('admin.access.col_name'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('permissions_count')
                    ->label(__('admin.access.col_permissions'))
                    ->getStateUsing(fn (Role $record): string => $record->name === 'admin'
                        ? __('admin.access.all_permissions')
                        : (string) ($record->permissions_count ?? $record->permissions()->count())),
            ])
            ->defaultSort('id')
            ->recordUrl(fn (Role $record): string => RoleResource::getUrl('edit', ['record' => $record]))
            ->toolbarActions([]);
    }
}
