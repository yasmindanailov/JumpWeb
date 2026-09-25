<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Domain\Booking\Models\Promotion;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Promotions\Concerns\InteractsWithPromotionForm;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditPromotion extends EditRecord
{
    use InteractsWithPromotionForm;

    protected static string $resource = PromotionResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Promotion $record */
        $record = $this->record;

        return __('admin.promotions.edit_title', ['name' => (string) ($record->tr('text') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deletePromotionAction(),
        ];
    }

    /**
     * El objetivo, que no es columna, se rellena desde las dos claves.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Promotion $record */
        $record = $this->record;
        $data['target'] = $record->target();

        return $data;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->preparePromotionData($data);
    }

    protected function afterSave(): void
    {
        /** @var Promotion $record */
        $record = $this->record;

        AuditLogger::log('catalog.promotion_updated', $record, $this->promotionAuditPayload($record));
    }

    /**
     * Una promoción no tiene dependientes: se borra libremente. Para que deje de salir sin perderla, se desactiva.
     */
    private function deletePromotionAction(): Action
    {
        return Action::make('deletePromotion')
            ->label(__('admin.promotions.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('catalog.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.promotions.actions.delete.modal_heading'))
            ->modalDescription(__('admin.promotions.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.promotions.actions.delete.submit'))
            ->action(function (Promotion $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('catalog.promotion_deleted', $record, $this->promotionAuditPayload($record));
                $record->delete();

                Notification::make()->title(__('admin.promotions.actions.delete.success'))->success()->send();

                $this->redirect(PromotionResource::getUrl('index'));
            });
    }
}
