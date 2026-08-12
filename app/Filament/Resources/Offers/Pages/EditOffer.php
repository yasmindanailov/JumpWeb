<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Offers\Concerns\InteractsWithOfferForm;
use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditOffer extends EditRecord
{
    use InteractsWithOfferForm;

    protected static string $resource = OfferResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Offer $record */
        $record = $this->record;

        return __('admin.offers.edit_title', ['name' => (string) ($record->tr('title') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteOfferAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareOfferData($data);
    }

    protected function afterSave(): void
    {
        /** @var Offer $record */
        $record = $this->record;

        AuditLogger::log('content.offer_updated', $record, [
            'title' => $record->tr('title'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    private function deleteOfferAction(): Action
    {
        return Action::make('deleteOffer')
            ->label(__('admin.offers.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.offers.actions.delete.modal_heading'))
            ->modalDescription(__('admin.offers.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.offers.actions.delete.submit'))
            ->action(function (Offer $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('content.offer_deleted', $record, ['title' => $record->tr('title')]);
                $record->delete();

                Notification::make()->title(__('admin.offers.actions.delete.success'))->success()->send();

                $this->redirect(OfferResource::getUrl('index'));
            });
    }
}
