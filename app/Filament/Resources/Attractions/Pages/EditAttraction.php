<?php

namespace App\Filament\Resources\Attractions\Pages;

use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Attractions\AttractionResource;
use App\Filament\Resources\Attractions\Concerns\InteractsWithAttractionForm;
use App\Models\Attraction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditAttraction extends EditRecord
{
    use InteractsWithAttractionForm;

    protected static string $resource = AttractionResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Attraction $record */
        $record = $this->record;

        return __('admin.attractions.edit_title', ['name' => (string) ($record->tr('name') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteAttractionAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareAttractionData($data);
    }

    protected function afterSave(): void
    {
        /** @var Attraction $record */
        $record = $this->record;

        AuditLogger::log('content.attraction_updated', $record, [
            'zone_id' => (int) $record->zone_id,
            'name' => $record->tr('name'),
            'is_active' => (bool) $record->is_active,
            'is_special' => (bool) $record->is_special,
            'ticket_type_id' => $record->ticket_type_id ? (int) $record->ticket_type_id : null,
            'position' => (int) $record->position,
        ]);
    }

    /**
     * Una atracción no tiene dependientes (ninguna FK la referencia): se puede borrar libremente.
     */
    private function deleteAttractionAction(): Action
    {
        return Action::make('deleteAttraction')
            ->label(__('admin.attractions.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.attractions.actions.delete.modal_heading'))
            ->modalDescription(__('admin.attractions.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.attractions.actions.delete.submit'))
            ->action(function (Attraction $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('content.attraction_deleted', $record, ['name' => $record->tr('name')]);
                $record->delete();

                Notification::make()->title(__('admin.attractions.actions.delete.success'))->success()->send();

                $this->redirect(AttractionResource::getUrl('index'));
            });
    }
}
