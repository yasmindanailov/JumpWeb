<?php

namespace App\Filament\Resources\BirthdayReminders\Tables;

use App\Domain\Identity\Models\BirthdayReminder;
use App\Domain\Platform\Services\DisplayTime;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * **La lista de «Avísame de fechas»** (`docs/specs/avisame-de-fechas.md` §4.4): quién lo pidió y su correo, para el cumple
 * de quién (el nombre, sin apellidos) y qué día, desde cuándo, y el estado. Todo sale de la autorización firmada: la fila no
 * copia datos. «Borrar», con confirmación.
 */
class BirthdayReminderTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query) => $query->with('authorization'))
            ->columns([
                TextColumn::make('guardian')
                    ->label(__('admin.birthday_reminders.col_guardian'))
                    ->getStateUsing(static fn (BirthdayReminder $record): string => (string) $record->authorization?->guardianFullName())
                    ->description(static fn (BirthdayReminder $record): string => (string) ($record->authorization->guardian_email ?? ''))
                    ->wrap(),
                TextColumn::make('child')
                    ->label(__('admin.birthday_reminders.col_child'))
                    ->getStateUsing(static fn (BirthdayReminder $record): string => trim((string) ($record->authorization->minor_name ?? ''))),
                TextColumn::make('birthday')
                    ->label(__('admin.birthday_reminders.col_birthday'))
                    ->getStateUsing(static fn (BirthdayReminder $record): string => DisplayTime::format($record->authorization?->minor_born_on, 'd/m')),
                TextColumn::make('accepted_at')
                    ->label(__('admin.birthday_reminders.col_since'))
                    ->formatStateUsing(static fn ($state): string => DisplayTime::format($state, 'd/m/Y')),
                TextColumn::make('state')
                    ->label(__('admin.birthday_reminders.col_state'))
                    ->badge()
                    ->getStateUsing(static fn (BirthdayReminder $record): string => self::state($record))
                    ->formatStateUsing(static fn (string $state, BirthdayReminder $record): string => __('admin.birthday_reminders.state.'.$state, [
                        'dia' => DisplayTime::format($record->sent_at, 'd/m/Y'),
                    ]))
                    ->color(static fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'revoked' => 'gray',
                        default => 'info',
                    }),
            ])
            ->defaultSort('accepted_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label(__('admin.birthday_reminders.col_state'))
                    ->options([
                        'waiting' => __('admin.birthday_reminders.filter.waiting'),
                        'sent' => __('admin.birthday_reminders.filter.sent'),
                        'revoked' => __('admin.birthday_reminders.filter.revoked'),
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'waiting' => $query->whereNull('revoked_at')->whereNull('sent_at'),
                        'sent' => $query->whereNull('revoked_at')->whereNotNull('sent_at'),
                        'revoked' => $query->whereNotNull('revoked_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label(__('admin.birthday_reminders.delete'))
                    ->modalDescription(__('admin.birthday_reminders.delete_confirm')),
            ])
            ->toolbarActions([]);
    }

    /** `revoked` (se dio de baja) · `sent` (ya salió su correo: `sent_at`) · `waiting` (todavía no). */
    public static function state(BirthdayReminder $record): string
    {
        return match (true) {
            ! $record->isLive() => 'revoked',
            $record->sent_at !== null => 'sent',
            default => 'waiting',
        };
    }
}
