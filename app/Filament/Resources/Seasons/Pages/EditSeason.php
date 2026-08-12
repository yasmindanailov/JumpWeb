<?php

namespace App\Filament\Resources\Seasons\Pages;

use App\Domain\Booking\Models\Season;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Seasons\Concerns\InteractsWithSeasonForm;
use App\Filament\Resources\Seasons\SeasonResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditSeason extends EditRecord
{
    use InteractsWithSeasonForm;

    protected static string $resource = SeasonResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Season $record */
        $record = $this->record;

        return __('admin.seasons.edit_title', ['name' => (string) $record->name]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteSeasonAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->validateSeasonData($data);
    }

    protected function afterSave(): void
    {
        /** @var Season $record */
        $record = $this->record;

        AuditLogger::log('slots.season_updated', $record, [
            'name' => $record->name,
            'range' => $record->start_date?->toDateString().'…'.$record->end_date?->toDateString(),
            'is_active' => (bool) $record->is_active,
        ]);
    }

    private function deleteSeasonAction(): Action
    {
        return Action::make('deleteSeason')
            ->label(__('admin.seasons.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('slots.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.seasons.actions.delete.modal_heading'))
            ->modalDescription(__('admin.seasons.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.seasons.actions.delete.submit'))
            ->action(function (Season $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('slots.season_deleted', $record, ['name' => $record->name]);
                $record->delete();

                Notification::make()
                    ->title(__('admin.seasons.actions.delete.success'))
                    ->success()
                    ->send();

                $this->redirect(SeasonResource::getUrl('index'));
            });
    }
}
