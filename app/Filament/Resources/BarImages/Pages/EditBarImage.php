<?php

namespace App\Filament\Resources\BarImages\Pages;

use App\Domain\Content\Models\BarImage;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\BarImages\BarImageResource;
use App\Filament\Resources\BarImages\Concerns\InteractsWithBarImageForm;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditBarImage extends EditRecord
{
    use InteractsWithBarImageForm;

    protected static string $resource = BarImageResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var BarImage $record */
        $record = $this->record;

        return __('admin.bar_images.edit_title', ['name' => (string) ($record->tr('alt') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteBarImageAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareBarImageData($data);
    }

    protected function afterSave(): void
    {
        /** @var BarImage $record */
        $record = $this->record;

        AuditLogger::log('content.bar_image_updated', $record, [
            'kind' => (string) $record->kind,
            'alt' => $record->tr('alt'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    private function deleteBarImageAction(): Action
    {
        return Action::make('deleteBarImage')
            ->label(__('admin.bar_images.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.bar_images.actions.delete.modal_heading'))
            ->modalDescription(__('admin.bar_images.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.bar_images.actions.delete.submit'))
            ->action(function (BarImage $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('content.bar_image_deleted', $record, ['alt' => $record->tr('alt')]);
                $record->delete();

                Notification::make()->title(__('admin.bar_images.actions.delete.success'))->success()->send();

                $this->redirect(BarImageResource::getUrl('index'));
            });
    }
}
