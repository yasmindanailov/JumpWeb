<?php

namespace App\Filament\Resources\WristbandColors\Pages;

use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\WristbandColors\WristbandColorResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditWristbandColor extends EditRecord
{
    protected static string $resource = WristbandColorResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var WristbandColor $record */
        $record = $this->record;

        return __('admin.wristbands.edit_title', ['name' => $record->name_one]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->deleteAction()];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['hex'] = strtolower((string) ($data['hex'] ?? ''));

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var WristbandColor $record */
        $record = $this->record;

        AuditLogger::log('catalog.wristband_saved', $record, CreateWristbandColor::trail($record) + ['created' => false]);
    }

    /**
     * Borrar, solo si ningún producto lo lleva FIJO ({@see WristbandColorResource::canDelete()}): se vuelve a mirar al
     * confirmar, por si alguien se lo puso a un producto mientras tanto.
     */
    private function deleteAction(): Action
    {
        return Action::make('deleteWristband')
            ->label(__('admin.wristbands.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (WristbandColor $record): bool => WristbandColorResource::canDelete($record))
            ->requiresConfirmation()
            ->modalHeading(__('admin.wristbands.actions.delete.modal_heading'))
            ->modalDescription(__('admin.wristbands.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.wristbands.actions.delete.submit'))
            ->action(function (WristbandColor $record): void {
                $record = $record->fresh();
                if ($record === null || ! WristbandColorResource::canDelete($record)) {
                    Notification::make()->title(__('admin.wristbands.actions.delete.in_use'))->danger()->send();

                    return;
                }

                AuditLogger::log('catalog.wristband_deleted', $record, CreateWristbandColor::trail($record));
                $record->delete();

                Notification::make()->title(__('admin.wristbands.actions.delete.success'))->success()->send();
                $this->redirect(WristbandColorResource::getUrl('index'));
            });
    }
}
