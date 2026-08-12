<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Domain\Content\Models\Faq;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Faqs\Concerns\InteractsWithFaqForm;
use App\Filament\Resources\Faqs\FaqResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditFaq extends EditRecord
{
    use InteractsWithFaqForm;

    protected static string $resource = FaqResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Faq $record */
        $record = $this->record;

        return __('admin.faqs.edit_title', ['name' => (string) ($record->tr('question') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteFaqAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareFaqData($data);
    }

    protected function afterSave(): void
    {
        /** @var Faq $record */
        $record = $this->record;

        AuditLogger::log('content.faq_updated', $record, [
            'question' => $record->tr('question'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    /**
     * Una FAQ no tiene dependientes (ninguna FK la referencia): se puede borrar libremente.
     * Confirmación + audit + redirección al listado.
     */
    private function deleteFaqAction(): Action
    {
        return Action::make('deleteFaq')
            ->label(__('admin.faqs.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.faqs.actions.delete.modal_heading'))
            ->modalDescription(__('admin.faqs.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.faqs.actions.delete.submit'))
            ->action(function (Faq $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('content.faq_deleted', $record, ['question' => $record->tr('question')]);
                $record->delete();

                Notification::make()->title(__('admin.faqs.actions.delete.success'))->success()->send();

                $this->redirect(FaqResource::getUrl('index'));
            });
    }
}
