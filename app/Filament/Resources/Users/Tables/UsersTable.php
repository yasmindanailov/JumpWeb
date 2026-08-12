<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.5 — Tabla de usuarios del panel (decisión #180).
 *
 * Columnas mínimas para identificar al cliente y su estado de cuenta de un
 * vistazo. Fila clicable → ficha de detalle. Filtro por cuentas anonimizadas.
 */
class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.col_name'))
                    ->searchable()
                    ->limit(40),

                TextColumn::make('email')
                    ->label(__('admin.users.col_email'))
                    ->searchable()
                    ->limit(40),

                TextColumn::make('roles.name')
                    ->label(__('admin.users.col_roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin.users.roles.'.$state))
                    ->placeholder('—'),

                TextColumn::make('account_state')
                    ->label(__('admin.users.col_status'))
                    ->badge()
                    ->state(fn (User $record): string => $record->isAnonymized() ? 'anonymized' : 'active')
                    ->formatStateUsing(fn (string $state): string => __('admin.users.status.'.$state))
                    ->color(fn (string $state): string => $state === 'anonymized' ? 'gray' : 'success'),

                TextColumn::make('email_verified_at')
                    ->label(__('admin.users.col_verified'))
                    ->badge()
                    ->state(fn (User $record): string => $record->email_verified_at
                        ? __('admin.users.verified')
                        : __('admin.users.unverified'))
                    ->color(fn (User $record): string => $record->email_verified_at ? 'success' : 'warning'),

                TextColumn::make('created_at')
                    ->label(__('admin.users.col_created_at'))
                    ->formatStateUsing(fn (?string $state) => $state ? DisplayTime::format($state) : '—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->filters([
                TernaryFilter::make('anonymized')
                    ->label(__('admin.users.filter_anonymized'))
                    ->placeholder(__('admin.users.filter_anonymized_all'))
                    ->trueLabel(__('admin.users.filter_anonymized_yes'))
                    ->falseLabel(__('admin.users.filter_anonymized_no'))
                    ->queries(
                        true: fn (Builder $q) => $q->where('email', 'like', '%@'.User::ANONYMIZED_EMAIL_DOMAIN),
                        false: fn (Builder $q) => $q->where('email', 'not like', '%@'.User::ANONYMIZED_EMAIL_DOMAIN),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->toolbarActions([
                // Sin acciones masivas: baja RGPD = acción individual de la ficha.
            ]);
    }
}
