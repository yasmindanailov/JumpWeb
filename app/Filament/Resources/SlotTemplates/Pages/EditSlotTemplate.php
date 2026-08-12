<?php

namespace App\Filament\Resources\SlotTemplates\Pages;

use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\SlotTemplates\Concerns\InteractsWithSlotTemplateForm;
use App\Filament\Resources\SlotTemplates\SlotTemplateResource;
use App\Models\SlotTemplate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditSlotTemplate extends EditRecord
{
    use InteractsWithSlotTemplateForm;

    protected static string $resource = SlotTemplateResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.slot_templates.edit_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteTemplateAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var SlotTemplate $record */
        $record = $this->record;

        return $this->prepareTemplateData($data, ignoreId: $record->id);
    }

    protected function afterSave(): void
    {
        /** @var SlotTemplate $record */
        $record = $this->record;

        AuditLogger::log('slots.template_updated', $record, [
            'zone_id' => $record->zone_id,
            'weekday' => $record->weekday,
            'start_time' => substr((string) $record->start_time, 0, 5),
            'capacity' => $record->capacity,
            'online_capacity' => $record->online_capacity,
            'is_active' => (bool) $record->is_active,
        ]);
    }

    private function deleteTemplateAction(): Action
    {
        return Action::make('deleteTemplate')
            ->label(__('admin.slot_templates.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('slots.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.slot_templates.actions.delete.modal_heading'))
            ->modalDescription(__('admin.slot_templates.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.slot_templates.actions.delete.submit'))
            ->action(function (SlotTemplate $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('slots.template_deleted', $record, [
                    'zone_id' => $record->zone_id,
                    'weekday' => $record->weekday,
                    'start_time' => substr((string) $record->start_time, 0, 5),
                ]);
                $record->delete();

                Notification::make()
                    ->title(__('admin.slot_templates.actions.delete.success'))
                    ->success()
                    ->send();

                $this->redirect(SlotTemplateResource::getUrl('index'));
            });
    }
}
